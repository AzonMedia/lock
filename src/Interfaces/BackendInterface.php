<?php
declare(strict_types=1);

namespace Azonmedia\Lock\Interfaces;

interface BackendInterface
{
    public function acquire_lock(string $resource, int $lock_level, int $lock_hold_microtime, int $lock_wait_microtime) : void ;

    public function release_lock(string $resource) : void ;
}