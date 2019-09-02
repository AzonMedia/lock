<?php
declare(strict_types=1);

namespace Azonmedia\Lock;

class ScopeReference
{

    /**
     * These callbacks will be executed on object destruction
     * @var array Array of callbacks
     */
    protected $callbacks = [];

    public function __construct(callable $callback = NULL)
    {
        if ($callback) {
            $this->add_callback($callback);
        }
    }

    public function __destruct()
    {
        $this->execute_callbacks();
    }

    public function add_callback(callable $callback): void
    {
        $this->callbacks[] = $callback;
    }

    private function execute_callbacks(): void
    {
        foreach ($this->callbacks as $callback) {
            $callback();
        }
    }

}
