<?php
declare(strict_types=1);

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

    public const SWOOLE_TABLE_RECORD_SERIALIZED_DATA_LENGTH = 1024;

    /**
     * The time it will wait between reattempting to obtain the lock in microseconds
     */
    public const DEFAULT_WAIT_STEP_MICROTIME = 1000;//cat be lower than 1000 microseconds (1msec) as swoole co::sleep() as of 4.4.4 does not support smaller

    /**
     * The "resource" is the key and the below is the structure for each key.
     */
    public const SWOOLE_TABLE_STRUCTURE = [
        'lock_data' => [
            'type'                      => \Swoole\Table::TYPE_STRING,
            'size'                      => self::SWOOLE_TABLE_RECORD_SERIALIZED_DATA_LENGTH,
        ]
    ];
    /*
     * $lock_data = [
     * 0 => [
     *          'worker_id'                 => 1,
     *          'coroutine_id'              => 44,
     *          'lock_level                 => 2,
     *          'lock_obtained_microtime'   => zzz,
     *          'lock_hold_microtime'       => xxx,
     *      ]
     * ];
     *
     *
     *
     */

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

        if (!isset(LockInterface::LOCK_MAP[$lock_level])) {
            throw new \InvalidArgumentException(sprintf('The provided lock level %s is not valid. For the valid lock levels please see %s.'), $lock_level, LockInterface::class);
        }

        $lock_requested_time = microtime(TRUE) * 1000000;

        $coroutine_id = \Co::getCid();
        //as there is currently no way to statically obtain the worker_id and passing it around would create another dependency we will use for now getmypid() instead
        //https://github.com/swoole/swoole-src/issues/2793
        $worker_id = getmypid();

        do {

            $try_to_obtain = FALSE;

            //$existing_lock = $this->SwooleTable->get($resource);
            $record = $this->SwooleTable->get($resource);

            if ($record) {
                $lock_data = unserialize($record['lock_data']);
                //do some cleanup
                foreach ($lock_data as $lock_key => $lock_datum) {
                    //first remove any expired lock data
                    $current_microtime = (int) (microtime(TRUE) * 1000000);
                    if ($lock_datum['lock_obtained_microtime'] + $lock_datum['lock_hold_microtime'] < $current_microtime) {
                        unset($lock_data[$lock_key]);
                    }
                    //then remove any own locks from the data (this data will be only saved if we obtain the lock so there is no case that the previous lock gets removed and the new one is not obtained (it will either wait or throw an exception without saving the data)
                    if ($lock_datum['worker_id'] === $worker_id && $lock_datum['coroutine_id'] === $coroutine_id) {
                        unset($lock_data[$lock_key]);
                    }
                }


                $lock_data = array_values($lock_data);

                if (count($lock_data)) {
                    //next check the compatibility
                    $lock_compatible = TRUE;
                    foreach ($lock_data as $lock_datum) {

                        if (!LockInterface::LOCK_COMPATIBILITY_MATRIX[$lock_datum['lock_level']][$lock_level]) {
                            $lock_compatible = FALSE;
                        }
                    }
                    if ($lock_compatible) {
                        $try_to_obtain = TRUE;
                    }
                } else {
                    //there are no locks
                    $try_to_obtain = TRUE;
                }

            } else {

                $try_to_obtain = TRUE;
            }
            unset($existing_lock, $lock_data, $lock_datum, $lock_compatible);


            if ($try_to_obtain) {
                //good - we can try to obtain it

                $lock_obtained_microtime = (int) (microtime(TRUE) * 1000000);
                $new_lock_data = [
                    'worker_id' => $worker_id,
                    'coroutine_id' => $coroutine_id,
                    'lock_level' => $lock_level,
                    'lock_obtained_microtime' => $lock_obtained_microtime,
                    'lock_hold_microtime' => $lock_hold_microtime,
                ];
                if (!empty($lock_data)) {
                    $lock_data[] = $new_lock_data;
                } else {
                    $lock_data = [$new_lock_data];
                }
                $record['lock_data'] = serialize($lock_data);
                if (strlen($record['lock_data']) > self::SWOOLE_TABLE_RECORD_SERIALIZED_DATA_LENGTH) {
                    throw new \RuntimeException(sprintf('Unable to update lock data. The resulting SwooleTable record has serialized lock data exceeding %s.', self::SWOOLE_TABLE_RECORD_SERIALIZED_DATA_LENGTH));
                }

                //$this->SwooleTable->set($resource, $lock_data);
                $this->SwooleTable->set($resource, $record);
                //we need to verify that the data was not overwritten due to race conditions by another thread
                //$saved_lock_data = $this->SwooleTable->get($resource);
                $saved_record = $this->SwooleTable->get($resource);
                if ($saved_record && $saved_record === $record) {
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

        //$this->SwooleTable->del($resource);
        //unset($this->SwooleTable[$resource]);
        $coroutine_id = \Co::getCid();
        //as there is currently no way to statically obtain the worker_id and passing it around would create another dependency we will use for now getmypid() instead
        //https://github.com/swoole/swoole-src/issues/2793
        $worker_id = getmypid();

        $record = $this->SwooleTable->get($resource);
        if (!$record) {
            //the lock has already been released
            return;
        } else {
            $lock_data = unserialize($record['lock_data']);
            foreach ($lock_data as $lock_key=>$lock_datum) {
                if ($lock_datum['worker_id'] === $worker_id && $lock_datum['coroutine_id'] === $coroutine_id) {
                    unset($lock_data[$lock_key]);
                }
            }
            $lock_data = array_values($lock_data);
            if (count($lock_data)) {
                $this->SwooleTable->set($resource, $lock_data);
            } else {
                $this->SwooleTable->del($resource);
            }
        }
    }
}