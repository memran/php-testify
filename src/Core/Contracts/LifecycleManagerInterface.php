<?php

namespace Testify\Core\Contracts;

interface LifecycleManagerInterface
{
    public function addBeforeEach(callable $callback): void;
    public function addAfterEach(callable $callback): void;
    public function addBeforeAll(callable $callback): void;
    public function addAfterAll(callable $callback): void;
    public function executeBeforeEach(): void;
    public function executeAfterEach(): void;
    public function executeBeforeAll(): void;
    public function executeAfterAll(): void;
}
