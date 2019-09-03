<?php
declare(strict_types=1);

namespace Azonmedia\Lock\Backends;


use Azonmedia\Lock\Interfaces\BackendInterface;
use Psr\Log\LoggerInterface;

class RedisBackend
implements BackendInterface
{
    protected $Logger;

    public function __construct(LoggerInterface $Logger)
    {
        $this->Logger = $Logger;
    }

    public function get_logger() : LoggerInterface
    {
        return $this->Logger;
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