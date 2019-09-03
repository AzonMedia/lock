<?php
declare(strict_types=1);

namespace Azonmedia\Lock;


use Azonmedia\Lock\Interfaces\BackendInterface;
use Azonmedia\Lock\Interfaces\LockInterface;
use Azonmedia\Lock\Interfaces\LockManagerInterface;
use Azonmedia\Utilities\GeneralUtil;
use Psr\Log\LoggerInterface;

/**
 * Class CoroutineLockManager
 * To be used in coroutine context.
 * It creates a separate instance of LockManager for each coroutine.
 * This instance is destroyed at the end of the coroutine (by using defer())
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

    /**
     * @var LoggerInterface
     */
    protected $Logger;

    public function __construct(BackendInterface $Backend, LoggerInterface $Logger)
    {
        $this->Backend = $Backend;
        $this->Logger = $Logger;
    }

    public function get_logger(): LoggerInterface
    {
        return $this->Logger;
    }

    public function acquire_lock(string $resource, int $lock_level, &$ScopeReference = '&', int $lock_hold_microtime = LockManager::DEFAULT_LOCK_HOLD_MICROTIME, int $lock_wait_microtime = LockManager::DEFAULT_LOCK_WAIT_MICROTIME): LockInterface
    {
        if (\Co::getCid() === -1) {
            throw new \RuntimeException(sprintf('The %s must be used/created in Coroutine context. When outside Coroutine context please use %s.', __CLASS__, LockManager::class));
        }
        $root_cid = self::get_root_coroutine_id();//this is the coroutine handling the request in Swoole\Http\Server context
        if (empty($this->lock_managers[$root_cid])) {
            $this->lock_managers[$root_cid] = new LockManager($this->Backend, $this->Logger);
            defer(function () use ($root_cid) : void //this registers a new shutdown function for each coroutine
            {
                unset($this->lock_managers[$root_cid]);//this should destroy the LockManager for this coroutine (as there shouldn't be any other references to this object)
            });
        }
        return $this->lock_managers[$root_cid]->acquire_lock($resource, $lock_level, $ScopeReference, $lock_hold_microtime, $lock_wait_microtime);
    }

    public function release_lock(string $resource, &$ScopeReference = '&'): void
    {
        if (\Co::getCid() === -1) {
            throw new \RuntimeException(sprintf('The %s must be used in Coroutine context. When outside Coroutine context please use %s.', __CLASS__, LockManager::class));
        }
        $root_cid = self::get_root_coroutine_id();
        if (!isset($this->lock_managers[$root_cid])) {
            throw new \LogicException(sprintf('The %s has no %s for root coroutine %s.', __CLASS__, LockManager::class, $root_cid));
        }
        $this->lock_managers[$root_cid]->release_lock($resource, $ScopeReference);
    }

    public static function get_root_coroutine_id() : int
    {
        if (\Co::getCid() === -1) {
            throw new \RuntimeException(sprintf('The %s must be used in Coroutine context. When outside Coroutine context please use %s.', __CLASS__, LockManager::class));
        }
        do {
            $cid = \Co::getCid();
            $pcid = \Co::getPcid($cid);
            if ($pcid === -1) {
                break;
            }
            $cid = $pcid;
        } while (true);

        return $cid;
    }

    public function execute_with_lock(callable $callable, int $lock_level) /* mixed */
    {
        if (\Co::getCid() === -1) {
            throw new \RuntimeException(sprintf('The %s must be used in Coroutine context. When outside Coroutine context please use %s.', __CLASS__, LockManager::class));
        }
        $resource = GeneralUtil::get_callable_hash($callable);
        $this->acquire_lock($resource, LockInterface::WRITE_LOCK, $LR);
        $ret = $callable();
        $this->release_lock('', $LR);
        return $ret;
    }

    public function release_all_own_locks() : void
    {
        if (\Co::getCid() === -1) {
            throw new \RuntimeException(sprintf('The %s must be used in Coroutine context. When outside Coroutine context please use %s.', __CLASS__, LockManager::class));
        }
        $root_cid = self::get_root_coroutine_id();
        if (!isset($this->lock_managers[$root_cid])) {
            throw new \LogicException(sprintf('The %s has no %s for root coroutine %s.', __CLASS__, LockManager::class, $root_cid));
        }
        $this->lock_managers[$root_cid]->release_all_own_locks();
    }

    public function get_all_own_locks() : array
    {
        if (\Co::getCid() === -1) {
            throw new \RuntimeException(sprintf('The %s must be used in Coroutine context. When outside Coroutine context please use %s.', __CLASS__, LockManager::class));
        }
        $root_cid = self::get_root_coroutine_id();
        if (!isset($this->lock_managers[$root_cid])) {
            throw new \LogicException(sprintf('The %s has no %s for root coroutine %s.', __CLASS__, LockManager::class, $root_cid));
        }
        return $this->lock_managers[$root_cid]->get_all_own_locks();
    }

    public function get_lock_level(string $resource) : ?int
    {
        if (\Co::getCid() === -1) {
            throw new \RuntimeException(sprintf('The %s must be used in Coroutine context. When outside Coroutine context please use %s.', __CLASS__, LockManager::class));
        }
        $root_cid = self::get_root_coroutine_id();
        if (!isset($this->lock_managers[$root_cid])) {
            throw new \LogicException(sprintf('The %s has no %s for root coroutine %s.', __CLASS__, LockManager::class, $root_cid));
        }
        return $this->lock_managers[$root_cid]->get_lock_level($resource);
    }
}