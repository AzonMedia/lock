<?php
declare(strict_types=1);

namespace Azonmedia\Lock\Backends;


use Azonmedia\Lock\Interfaces\BackendInterface;

class RedisBackend
implements BackendInterface
{
    public function __construct()
    {
        //implement
    }

    public function acquire_lock(string $resource, int $lock_level, int $lock_hold_microtime, int $lock_wait_microtime) : void
    {
        //implement
    }

    public function release_lock(string $resource) : void
    {
        //implement
    }
}