<?php

namespace Testify\Core\Contracts;

interface TestSuiteInterface
{
    public function addTest(callable $test, string $description): void;
    public function getTests(): array;
}
