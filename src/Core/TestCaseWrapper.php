<?php

namespace Testify\Core;

use PHPUnit\Framework\TestCase;
use Testify\Core\Contracts\LifecycleManagerInterface;

class TestCaseWrapper extends TestCase
{
    private $testCallback;
    private LifecycleManagerInterface $lifecycleManager;
    private string $testName;

    public function __construct(
        string $testName,
        callable $testCallback,
        LifecycleManagerInterface $lifecycleManager
    ) {
        parent::__construct($testName);
        $this->testName = $testName;
        $this->testCallback = $testCallback;
        $this->lifecycleManager = $lifecycleManager;
    }

    public function runTest(): void
    {
        $this->lifecycleManager->executeBeforeEach();

        try {
            ($this->testCallback)();
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            $this->lifecycleManager->executeAfterEach();
        }
    }

    public function getName(bool $withDataSet = true): string
    {
        return $this->testName;
    }
}
