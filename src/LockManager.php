<?php
declare(strict_types=1);

namespace Azonmedia\Lock;

use Azonmedia\Lock\Interfaces\BackendInterface;
use Azonmedia\Lock\Interfaces\LockInterface;
use Azonmedia\Lock\Interfaces\LockManagerInterface;
use Azonmedia\Utilities\GeneralUtil;
use Guzaba2\Kernel\Kernel;

/**
 * Class LockManager
 * @link https://en.wikipedia.org/wiki/Distributed_lock_manager
 * @package Azonmedia\lock
 */
class LockManager
implements LockManagerInterface
{

    public const DEFAULT_LOCK_HOLD_MICROTIME = 2 * 1000000;
    public const DEFAULT_LOCK_WAIT_MICROTIME = 2 * 1000000;

    protected $Backend;

    protected $lock_stack = [];

    public function __construct(BackendInterface $Backend)
    {
        $this->Backend = $Backend;
    }


    /**
     * The returned acquired lock may have a higher lock level than the requested - this can happen if a lock at a higher level was already acquired in a previous stack frame.
     * @param string $resource
     * @param int $lock_level
     * @param string $ScopeReference
     * @param int $lock_hold_microtime
     * @param int $lock_wait_microtime
     * @return LockInterface
     */
    public function acquire_lock(string $resource, int $lock_level, &$ScopeReference = '&', int $lock_hold_microtime = self::DEFAULT_LOCK_HOLD_MICROTIME, int $lock_wait_microtime = self::DEFAULT_LOCK_WAIT_MICROTIME) : LockInterface
    {

        if (!$resource) {
            throw new \InvalidArgumentException(sprintf('There is no resource provided.'));
        }

        if (!isset(LockInterface::LOCK_MAP[$lock_level])) {
            throw new \InvalidArgumentException(sprintf('The provided lock level %s is not valid. For the valid lock levels please see %s.'), $lock_level, LockInterface::class);
        }

        if ($ScopeReference === '&') {
            //throw new \RuntimeException(sprintf('No scope reference is provided for the lock'));
            //allow this - means that no scope reference will be used for this lock (to be used when the lock will be released in another scope)
        } elseif ($ScopeReference instanceof ScopeReference) {
            throw new \RuntimeException(sprintf('There is an existing scope reference provided.'));
        } elseif ($ScopeReference !== NULL) {
            throw new \RuntimeException(sprintf('An unsupported value of type %s was provided as scope reference.'), gettype($ScopeReference) );
        } else {
            $Callback = function() use ($resource) : void
            {
                $LR = '&';
                $this->release_lock($resource, $LR);
            };
            $ScopeReference = new ScopeReference($Callback);
        }

        if (empty($this->lock_stack[$resource])) {
            $this->lock_stack[$resource] = [];
            //$this->lock_stack[$resource]['stack'] = [];
        }
        //befor actually acquiring the lock check the stack - it may have been acquired at this or higher level

        $lock_level_to_set = $lock_level;
        if (!empty($this->lock_stack[$resource])) {
            //no matter what is the lock level of the previous lock we need to extend the lock like it was acquired just now
            $PreviousLock = $this->lock_stack[$resource][count($this->lock_stack[$resource]) - 1]['lock'];
            //$previous_lock_level = $PreviousLock->get_lock_level();//wrong as the lock level in the $Lock object is elevated to the previous one.. this way once elevated it will be never dropped to a lower level
            $previous_lock_level = $this->lock_stack[$resource][count($this->lock_stack[$resource]) - 1]['lock_level'];
            if ($previous_lock_level > $lock_level) {
                //we cant lower the lock level so the lock level is raised to the previous one and reacquire is done at this new level
                $lock_level_to_set = $previous_lock_level;
            }
            $Lock = $PreviousLock;
        }
        if (empty($Lock)) {
            //there is was no previous lock for this resource from this execution
            $Lock = new Lock($this->Backend, $resource, $lock_level_to_set, $lock_hold_microtime, $lock_wait_microtime, $acquire = FALSE);
        }
        $Lock->acquire($lock_level_to_set, $lock_hold_microtime);//acquire or reacquire (if there was a previous lock)
        $this->lock_stack[$resource][] = ['lock' => $Lock, 'lock_level' => $lock_level];
        return $Lock;

    }

    /**
     * @param string $resource
     * @param string $ScopeReference
     */
    public function release_lock(string $resource, &$ScopeReference = '&') : void
    {
        if ($resource && $ScopeReference instanceof ScopeReference) {
            throw new \InvalidArgumentException(sprintf('Both $resource and $ScopeReference provided to %s.', __METHOD__ ));
        }

        if ($resource) {
            if (!isset($this->lock_stack[$resource])) {
                throw new \LogicException(sprintf('The LockManager stack has no data for resource %s.', $resource));
            }

            $Lock = $this->lock_stack[$resource][count($this->lock_stack[$resource]) - 1]['lock'];
            if (isset($this->lock_stack[$resource][count($this->lock_stack[$resource]) - 2])) {
                $lock_level_to_set = $this->lock_stack[$resource][count($this->lock_stack[$resource]) - 2]['lock_level'];
            }
            if (!empty($lock_level_to_set)) {
                $Lock->acquire($lock_level_to_set, 0);//restore to the lock level from the previous stack frame (this is expected to the a loewer level lock) without extending the lock time
            } else {
                $Lock->release();
            }

        } elseif ($ScopeReference instanceof ScopeReference) {
            $ScopeReference = NULL;//this should trigger the destructor and the release

        } else {
            throw new \InvalidArgumentException(sprintf('No resource or a scope reference was provided to %s.',__METHOD__ ));
        }

    }

    /**
     * Ensures that the provided code is executed only by one thread.
     * @param callable $callable
     * @param int $lock_level
     */
    public function execute_with_lock(callable $callable, int $lock_level) /* mixed */
    {
        $resource = GeneralUtil::get_callable_hash($callable);
        $this->acquire_lock($resource, LockInterface::WRITE_LOCK, $LR);
        $ret = $callable();
        $this->release_lock('', $LR);
        return $ret;
    }

    /**
     * Releases all locks obtained by this execution.
     * To be called by the destructor (as a cleanup).
     */
    public function release_all_own_locks() : void
    {
        foreach ($this->lock_stack as $resource=>$lock_data) {
            if (!empty($lock_data['lock'])) {
                if ($lock_data['lock']->is_acquired()) {
                    //in the general case this shouldnt happen
                    $lock_data['lock']->release();
                }

            }
        }
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->release_all_own_locks();
    }
}