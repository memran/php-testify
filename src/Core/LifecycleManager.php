<?php

namespace Testify\Core;

use Testify\Core\Contracts\LifecycleManagerInterface;

class LifecycleManager implements LifecycleManagerInterface
{
    private array $beforeEachCallbacks = [];
    private array $afterEachCallbacks = [];
    private array $beforeAllCallbacks = [];
    private array $afterAllCallbacks = [];

    public function addBeforeEach(callable $callback): void
    {
        $this->beforeEachCallbacks[] = $callback;
    }

    public function addAfterEach(callable $callback): void
    {
        $this->afterEachCallbacks[] = $callback;
    }

    public function addBeforeAll(callable $callback): void
    {
        $this->beforeAllCallbacks[] = $callback;
    }

    public function addAfterAll(callable $callback): void
    {
        $this->afterAllCallbacks[] = $callback;
    }

    public function executeBeforeEach(): void
    {
        if (empty($this->beforeEachCallbacks)) {
            return;
        }
        foreach ($this->beforeEachCallbacks as $callback) {
            $callback();
        }
    }

    public function executeAfterEach(): void
    {
        foreach ($this->afterEachCallbacks as $callback) {
            $callback();
        }
    }

    public function executeBeforeAll(): void
    {
        foreach ($this->beforeAllCallbacks as $callback) {
            $callback();
        }
    }

    public function executeAfterAll(): void
    {
        foreach ($this->afterAllCallbacks as $callback) {
            $callback();
        }
    }
}
