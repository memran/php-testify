<?php

namespace Testify\Core;

use Testify\Core\Contracts\TestSuiteInterface;

class TestSuite implements TestSuiteInterface
{
    private array $tests = [];

    public function addTest(callable $test, string $description): void
    {
        $this->tests[] = [
            'callback' => $test,
            'description' => $description
        ];
    }

    public function getTests(): array
    {
        return $this->tests;
    }

    public function clearTests(): void
    {
        $this->tests = [];
    }
}
