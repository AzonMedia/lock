<?php
declare(strict_types=1);

namespace Azonmedia\Lock\Interfaces;

use Psr\Log\LoggerInterface;

interface LockManagerInterface
{


    public const DEFAULT_LOCK_HOLD_MICROTIME = 15 * 1000000;
    public const DEFAULT_LOCK_WAIT_MICROTIME = 20 * 1000000;

    public function get_logger() : LoggerInterface ;

    /**
     * The returned acquired lock may have a higher lock level than the requested - this can happen if a lock at a higher level was already acquired in a previous stack frame.
     * @param string $resource
     * @param int $lock_level
     * @param string $ScopeReference
     * @param int $lock_hold_microtime
     * @param int $lock_wait_microtime
     * @return Lock
     */
    public function acquire_lock(string $resource, int $lock_level, &$ScopeReference = '&', int $lock_hold_microtime = self::DEFAULT_LOCK_HOLD_MICROTIME, int $lock_wait_microtime = self::DEFAULT_LOCK_WAIT_MICROTIME) : LockInterface ;

    /**
     * @param string $resource
     * @param string $ScopeReference
     */
    public function release_lock(string $resource, &$ScopeReference = '&') : void ;

    /**
     * Ensures that the provided code is executed only by one thread.
     * @param callable $callable
     * @param int $lock_level
     */
    public function execute_with_lock(callable $callable, int $lock_level) /* mixed */ ;

    /**
     * Releases all locks obtained by this execution.
     * To be called by the destructor (as a cleanup).
     */
    public function release_all_own_locks() : void ;

}