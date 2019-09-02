<?php
declare(strict_types=1);

namespace Azonmedia\Lock;


use Azonmedia\Lock\Interfaces\BackendInterface;
use Azonmedia\Lock\Interfaces\LockInterface;


class Lock
implements LockInterface
{

    /**
     * @var int
     */
    protected $lock_requested_microtime;

    /**
     * The time when the lock was acquired for the first time
     * @var int
     */
    protected $lock_acquired_microtime;

    /**
     * The time when the lock was last reacquired
     * @var int
     */
    protected $lock_reacquired_microtime;

    /**
     * @var int
     */
    protected $lock_released_microtime;

    protected $Backend;
    protected $resource;
    protected $lock_level;
    protected $lock_hold_microtime;
    protected $lock_wait_microtime;

    protected $acquired_flag = FALSE;

    /**
     * Lock constructor.
     * @param BackendInterface $Backend
     * @param string $resource
     * @param int $lock_level
     * @param int $lock_hold_microtime
     * @param int $lock_wait_microtime
     * @param bool $acquire
     */
    public function __construct(BackendInterface $Backend, string $resource, int $lock_level, int $lock_hold_microtime, int $lock_wait_microtime, bool $acquire = TRUE)
    {
        if (!$resource) {
            throw new \InvalidArgumentException(sprintf('There is no resource provided.'));
        }
        if (!isset(LockInterface::LOCK_MAP[$lock_level])) {
            throw new \InvalidArgumentException(sprintf('The provided lock level %s is not valid. For the valid lock levels please see %s.'), $lock_level, LockInterface::class);
        }

        $this->lock_requested_microtime = microtime(TRUE) * 1000000;

        $this->Backend = $Backend;
        $this->resource = $resource;
        $this->lock_level = $lock_level;
        $this->lock_hold_microtime = $lock_hold_microtime;
        $this->lock_wait_microtime = $lock_wait_microtime;

        if ($acquire) {
            $this->acquire($this->lock_level, $this->lock_hold_microtime);
        }
    }

    public function __destruct()
    {
        $this->release();
    }

    /**
     * Can be used also for reacquiring (extending the lock and changing the lock level)
     * @param int $lock_level
     */
    public function acquire(int $lock_level, int $lock_hold_microtime) : void
    {
        if (!isset(LockInterface::LOCK_MAP[$lock_level])) {
            throw new \InvalidArgumentException(sprintf('The provided lock level %s is not valid. For the valid lock levels please see %s.'), $lock_level, LockInterface::class);
        }
        $this->lock_level = $lock_level;
        //$this->Backend->acquire_lock($this->resource, $lock_level, $this->lock_hold_microtime, $this->lock_wait_microtime);
        $this->Backend->acquire_lock($this->resource, $lock_level, $lock_hold_microtime, $this->lock_wait_microtime);
        if (!$this->lock_acquired_microtime) {
            $this->lock_acquired_microtime = microtime(TRUE) * 1000000;
        }
        $this->lock_reacquired_microtime = microtime(TRUE) * 1000000;
        $this->acquired_flag = TRUE;
    }

    public function is_acquired() : bool
    {
        return $this->acquired_flag;
    }

    public function get_resource() : string
    {
        return $this->resource;
    }

    public function get_lock_level() : int
    {
        return $this->lock_level;
    }

    public function release() : void
    {
        if ($this->is_acquired()) {
            $this->Backend->release_lock($this->resource);
            $this->lock_released_microtime = microtime(TRUE) * 1000000;
            $this->acquired_flag = FALSE;
        }
    }


}