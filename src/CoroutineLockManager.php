<?php
declare(strict_types=1);

namespace Azonmedia\Lock;


use Azonmedia\Lock\Interfaces\BackendInterface;
use Azonmedia\Lock\Interfaces\LockInterface;
use Azonmedia\Lock\Interfaces\LockManagerInterface;
use Azonmedia\Utilities\GeneralUtil;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;

/**
 * Class CoroutineLockManager
 * To be used in coroutine context.
 * It creates a separate instance of LockManager for each coroutine.
 * Each coroutine must have its own LockManager as this contains the lock_stack and this stack must not be shared between the coroutines (as they execute independently)
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

        //it is allowed to initialize the CoroutineLockManager outside coroutine as this is needed for the SwooleTableBackend
        //it is OK as long it is not used / methods invoked
//        if (\Swoole\Coroutine::getCid() === -1) {
//            throw new \RuntimeException(sprintf('The %s must be used/created in Coroutine context. When outside Coroutine context please use %s.', __CLASS__, LockManager::class));
//        }
    }

    public function get_logger(): LoggerInterface
    {
        return $this->Logger;
    }

    public function acquire_lock(string $resource, int $lock_level, &$ScopeReference = '&', int $lock_hold_microtime = LockManager::DEFAULT_LOCK_HOLD_MICROTIME, int $lock_wait_microtime = LockManager::DEFAULT_LOCK_WAIT_MICROTIME): LockInterface
    {
        $this->initialize_lock_manager();
        return Coroutine::getContext()->{LockManagerInterface::class}->acquire_lock($resource, $lock_level, $ScopeReference, $lock_hold_microtime, $lock_wait_microtime);
    }

    public function release_lock(string $resource, &$ScopeReference = '&'): void
    {
        $this->initialize_lock_manager();
        Coroutine::getContext()->{LockManagerInterface::class}->release_lock($resource, $ScopeReference);
    }

    //NOT USED
//    public static function get_root_coroutine_id() : int
//    {
//        if (\Swoole\Coroutine::getCid() === -1) {
//            throw new \RuntimeException(sprintf('The %s must be used in Coroutine context. When outside Coroutine context please use %s.', __CLASS__, LockManager::class));
//        }
//        do {
//            $cid = \Swoole\Coroutine::getCid();
//            $pcid = \Swoole\Coroutine::getPcid($cid);
//            if ($pcid === -1) {
//                break;
//            }
//            $cid = $pcid;
//        } while (true);
//
//        return $cid;
//    }

    public function execute_with_lock(callable $callable, int $lock_level) /* mixed */
    {
        $this->initialize_lock_manager();
        $resource = GeneralUtil::get_callable_hash($callable);
        $this->acquire_lock($resource, LockInterface::WRITE_LOCK, $LR);
        $ret = $callable();
        $this->release_lock('', $LR);
        return $ret;
    }

    public function release_all_own_locks() : void
    {
        $this->initialize_lock_manager();
        Coroutine::getContext()->{LockManagerInterface::class}->release_all_own_locks();
    }

    public function get_all_own_locks() : array
    {
        $this->initialize_lock_manager();
        return Coroutine::getContext()->{LockManagerInterface::class}->get_all_own_locks();
    }

    public function get_lock_level(string $resource) : ?int
    {
        $this->initialize_lock_manager();
        return Coroutine::getContext()->{LockManagerInterface::class}->get_lock_level($resource);
    }

    private function initialize_lock_manager()
    {
        if (Coroutine::getCid() === -1) {
            throw new \RuntimeException(sprintf('The %s must be used/created in Coroutine context. When outside Coroutine context please use %s.', __CLASS__, LockManager::class));
        }
        $Context = Coroutine::getContext();
        if (empty($Context->{LockManagerInterface::class})) {
            $Context->{LockManagerInterface::class} = new LockManager($this->Backend, $this->Logger);
        }
    }
}