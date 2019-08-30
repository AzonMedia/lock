<?php


namespace Azonmedia\Lock\Backends;


use Azonmedia\Lock\Interfaces\BackendInterface;

class NullBackend
implements BackendInterface
{

    public function __construct()
    {
        //no dependencies
    }

    public function acquire_lock(string $resource, int $lock_level, int $lock_hold_microtime, int $lock_wait_microtime) : void
    {
        //does nothing
    }

    public function release_lock(string $resource) : void
    {
        //does nothing
    }
}