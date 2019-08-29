<?php


namespace Azonmedia\Lock;


use Azonmedia\Lock\Interfaces\BackendInterface;
use Azonmedia\Lock\Interfaces\LockManagerInterface;

/**
 * Class CoroutineLockManager
 * To be used in coroutine context.
 * It creates a separate instance of LockManager for each coroutine.
 * This instance is destroyed at the end of the coroutine (bu using defer())
 * @package Azonmedia\lock
 */
class CoroutineLockManager
implements LockManagerInterface
{

    /**
     * A LockManager for each coroutine is created
     * @var array Array of LockManager
     */
    protected $lock_managers = [];

    /**
     * @var BackendInterface
     */
    protected $Backend;

    public function __construct(BackendInterface $Backend)
    {
        $this->Backend = $Backend;
    }

    public function acquire_lock(string $resource, int $lock_level, &$ScopeReference = '&', int $lock_hold_microtime = LockManager::DEFAULT_LOCK_HOLD_MICROTIME, int $lock_wait_microtime = LockManager::DEFAULT_LOCK_WAIT_MICROTIME): Lock
    {
        if (\Co::getCid() === -1) {
            throw new \RuntimeException(sprintf('The %s must be used/created in Coroutine context. When outside Coroutine context please use %s.', __CLASS__, LockManager::class));
        }
        $root_cid = self::get_root_coroutine_id();//this is the coroutine handling the request in Swoole\Http\Server context
        if (empty($this->lock_managers[$root_cid])) {
            $this->lock_managers[$root_cid] = new LockManager($this->Backend);
            defer(function () use ($root_cid) : void //this registers a new shutdown function for each coroutine
            {
                unset($this->lock_managers[$root_cid]);//this should destroy the LockManager for this coroutine
            });
        }
        return $this->lock_managers[$root_cid]->acquire_lock($resource, $lock_level, $ScopeReference, $lock_hold_microtime, $lock_wait_microtime);
    }

    public function release_lock(string $resource, &$ScopeReference = '&'): void
    {
        if (\Co::getCid() === -1) {
            throw new \RuntimeException(sprintf('The %s must be used/created in Coroutine context. When outside Coroutine context please use %s.', __CLASS__, LockManager::class));
        }
        $root_cid = self::get_root_coroutine_id();
        if (!isset($this->lock_managers[$root_cid])) {
            throw new \LogicException(sprintf('The %s has no %s for root coroutine %s.', __CLASS__, LockManager::class, $root_cid));
        }
        $this->lock_managers[$root_cid]->release_lock($resource, $ScopeReference);
    }

    public static function get_root_coroutine_id() : int
    {
        $cid = \Co::getCid();
        if ($cid > -1) {
            do {
                $pcid = \Co::getPcid($cid);
                $cid = $pcid;
            } while ($pcid !== -1);
        }
        return $cid;
    }

    public function execute_with_lock(callable $callable, int $lock_level) /* mixed */
    {

    }

    public function release_all_own_locks() : void
    {
        throw new \RuntimeException(sprintf('The method %s can not be invoked on %s. Instead it can be used on %s.', __FUNCTION__, __CLASS__, LockManager::class));
    }
}