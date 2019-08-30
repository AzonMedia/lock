<?php


namespace Azonmedia\Lock\Backends;


use Azonmedia\Lock\Exceptions\LockException;
use Azonmedia\Lock\Interfaces\BackendInterface;
use Azonmedia\Lock\Interfaces\LockInterface;
use Azonmedia\Lock\Lock;

class SwooleTableBackend
implements BackendInterface
{

    /**
     * The default swoole table size in rows.
     * This means that this is the maximum number of locks it can handle.
     */
    public const DEFAULT_SWOOLE_TABLE_SIZE = 10000;

    /**
     * The time it will wait between reattempting to obtain the lock in microseconds
     */
    public const DEFAULT_WAIT_STEP_MICROTIME = 1000;//cat be lower than 1000 microseconds (1msec) as swoole co::sleep() as of 4.4.4 does not support smaller

    /**
     * The "resource" is the key and the below is the structure for each key.
     */
    public const SWOOLE_TABLE_STRUCTURE = [
        'worker_id'                 => [
            'type'                      => \Swoole\Table::TYPE_INT,
            //'size'                      => 2,
            'size'                      => 4,
        ],
        'coroutine_id'              => [
            'type'                      => \Swoole\Table::TYPE_INT,
            'size'                      => 8,
        ],
        'lock_level'                => [
            'type'                      => \Swoole\Table::TYPE_INT,
            'size'                      => 1,
        ],
        'lock_obtained_microtime'   => [ //when was the lock obtained in microseconds
            'type'                      => \Swoole\Table::TYPE_INT,
            'size'                      => 8,
        ],
        'lock_hold_microtime'       => [ //for how long (in microseconds) the lock should be kept. After that time it is no longer considered active and the record is discarded and deleted.
            'type'                      => \Swoole\Table::TYPE_INT,
            'size'                      => 8,
        ]
    ];

    /**
     * @var int
     */
    protected $swoole_table_size;

    /**
     * @var int
     */
    protected $wait_step_microtime;


    /**
     * @var \Swoole\Table
     */
    protected $SwooleTable;

    public function __construct(int $swoole_table_size = self::DEFAULT_SWOOLE_TABLE_SIZE, int $wait_step_microtime = self::DEFAULT_WAIT_STEP_MICROTIME)
    {
        if (\Co::getCid() > 1) {
            throw new \RunTimeException(sprintf('Instances from %s need to be created before the swoole server is started. This instance is created in a coroutine whcih suggests it is being created inside the request (or other) handler of the server.', __CLASS__));
        }

        if ($wait_step_microtime < 1000) {
            throw new \InvalidArgumentException(sprintf('The $wait_step_microtime has to be 1000 or more microseconds.'));
        }

        $this->swoole_table_size = $swoole_table_size;
        $this->wait_step_microtime = $wait_step_microtime;

        $this->SwooleTable = new \Swoole\Table($this->swoole_table_size);
        foreach (self::SWOOLE_TABLE_STRUCTURE as $key_name => $key_type) {
            $this->SwooleTable->column($key_name, $key_type['type'], $key_type['size']);
        }
        $this->SwooleTable->create();
    }

    public function __destruct()
    {
        $this->SwooleTable->destroy();
        $this->SwooleTable = NULL;
    }

    /**
     * It is a blocking function - either obtains the lock or throws an exception.
     * In terms of Swoole - it is not coroutine blocking. Uses co::sleep() instead of sleep().
     * @param string $resource
     * @param int $lock_level
     * @param int $lock_hold_microtime
     * @param int $lock_wait_microtime
     * @throws LockExceptionInterface
     */
    public function acquire_lock(string $resource, int $lock_level, int $lock_hold_microtime, int $lock_wait_microtime) : void
    {


        if (\Co::getCid() === -1) {
            throw new \RunTimeException(sprintf('The %s() method can be used only in coroutine context.'));
        }

        if (!isset(LockInterface::LOCK_MAP[$lock_level])) {
            throw new \InvalidArgumentException(sprintf('The provided lock level %s is not valid. For the valid lock levels please see %s.'), $lock_level, LockInterface::class);
        }

        $lock_requested_time = microtime(TRUE) * 1000000;

        do {

            $try_to_obtain = FALSE;

            $existing_lock = $this->SwooleTable->get($resource);

            if ($existing_lock) {
                //the lock exists but this may be an expired record
                //it may happened that it wasnt released on time or wasnt released at all or it may be a valid lock
                $current_microtime = (int) (microtime(TRUE) * 1000000);
                if ($existing_lock['lock_obtained_microtime'] + $existing_lock['lock_hold_microtime'] < $current_microtime) {
                    //the lock has expired
                    //remove the record
                    $this->SwooleTable->del($resource);
                    $try_to_obtain = TRUE;
                } else {
                    //the lock is valid... wait
                }
            } else {

                $try_to_obtain = TRUE;
            }


            if ($try_to_obtain) {
                //good - we can try to obtain it
                $lock_obtained_microtime = (int) (microtime(TRUE) * 1000000);
                $coroutine_id = \Co::getCid();
                //as there is currently no way to statically obtain the worker_id and passing it around would create another dependency we will use for now getmypid() instead
                //https://github.com/swoole/swoole-src/issues/2793
                $worker_id = getmypid();
                $lock_data = [
                    'worker_id' => $worker_id,
                    'coroutine_id' => $coroutine_id,
                    'lock_level' => $lock_level,
                    'lock_obtained_microtime' => $lock_obtained_microtime,
                    'lock_hold_microtime' => $lock_hold_microtime,
                ];
                $this->SwooleTable->set($resource, $lock_data);
                //we need to verify that the data was not overwritten due to race conditions by another thread
                $saved_lock_data = $this->SwooleTable->get($resource);
                if ($saved_lock_data && $saved_lock_data === $lock_data) {
                    //the lock has been obtained successfully
                    return;
                } else {
                    //wait... someone else just got in
                }
            }
            \Co::sleep($this->wait_step_microtime / 1000000);//do not block and let other coroutines to executine in the meantime
            $current_microtime = (int) (microtime(TRUE) * 1000000);
            if ($current_microtime - $lock_wait_microtime > $lock_requested_time) {
                //we couldnt obtain the lock in the allocated time
                throw new LockException(sprintf('The %s lock on resource %s could not be obtained.', LockInterface::LOCK_MAP[$lock_level], $resource));
            }
        } while (true);
    }

    public function release_lock(string $resource) : void
    {
        if (\Co::getCid() === -1) {
            throw new \RunTimeException(sprintf('The %s() method can be used only in coroutine context.'));
        }

        $this->SwooleTable->del($resource);
    }
}