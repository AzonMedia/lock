<?php

namespace Azonmedia\Lock;

use Azonmedia\Lock\Interfaces\BackendInterface;
use Azonmedia\Lock\Interfaces\LockManagerInterface;

/**
 * Class LockManager
 * @link https://en.wikipedia.org/wiki/Distributed_lock_manager
 * @package Azonmedia\lock
 */
class LockManager
implements LockManagerInterface
{

    public const DEFAULT_LOCK_HOLD_MICROTIME = 120 * 1000000;
    public const DEFAULT_LOCK_WAIT_MICROTIME = 120 * 1000000;

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
     * @return Lock
     */
    public function acquire_lock(string $resource, int $lock_level, &$ScopeReference = '&', int $lock_hold_microtime = self::DEFAULT_LOCK_HOLD_MICROTIME, int $lock_wait_microtime = self::DEFAULT_LOCK_WAIT_MICROTIME) : Lock
    {

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
        $acquire = TRUE;
        $lock_level_to_set = $lock_level;
        if (!empty($this->lock_stack[$resource])) {
            //no matter what is the lock level of the previous lock we need to extend the lock like it was acquired just now
            $PreviousLock = $this->lock_stack[$resource][count($this->lock_stack[$resource]) - 1]['lock'];
            $previous_lock_level = $PreviousLock->get_lock_level();
            if ($previous_lock_level < $lock_level) {
                //we cant lower the lock level so the lock level is raised to the previous one and reacquire is done at this new level
                $lock_level_to_set = $previous_lock_level;
            }
            $Lock = $PreviousLock;
        }
        if (empty($Lock)) {
            //there is was no previous lock for this resource from this execution
            $Lock = new Lock($this->Backend, $resource, $lock_level, $lock_hold_microtime, $lock_wait_microtime);
        }
        $Lock->acquire($lock_level, $lock_hold_microtime);//acquire or reacquire (if there was a previous lock)
        $this->lock_stack[$resource][] = ['lock' => $Lock, 'lock_level' => $lock_level];
        return $Lock;

    }

    public function release_lock(string $resource, &$ScopeReference = '&') : void
    {
        if ($resource && $ScopeReference instanceof ScopeReference) {
            throw new \InvalidArgumentException(sprintf('Both $resource and $ScopeReference provided to %s.', __METHOD__ ));
        }

        if ($resource) {
            print 'AAA';
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
            print 'BBB';
            $ScopeReference = NULL;//this should trigger the destructor and the release

        } else {
            throw new \InvalidArgumentException(sprintf('No resource or a scope reference was provided to %s.',__METHOD__ ));
        }

    }

    public function execute_with_lock(callable $callable, int $lock_level)
    {

    }

    public function release_all_own_locks() : void
    {

    }

    public function __destruct()
    {
        $this->release_all_own_locks();
    }
}