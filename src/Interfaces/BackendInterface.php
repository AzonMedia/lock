<?php
declare(strict_types=1);

namespace Azonmedia\Lock\Interfaces;

use Psr\Log\LoggerInterface;

interface BackendInterface
{

    public function get_logger() : LoggerInterface ;

    public function acquire_lock(string $resource, int $lock_level, int $lock_hold_microtime, int $lock_wait_microtime) : void ;

    public function release_lock(string $resource) : void ;
}