<?php

namespace Testify\Core;

use Testify\Core\Contracts\LifecycleManagerInterface;

class TestCaseFactory
{
    public function create(
        string $testName,
        callable $testCallback,
        LifecycleManagerInterface $lifecycleManager
    ): TestCaseWrapper {
        return new TestCaseWrapper($testName, $testCallback, $lifecycleManager);
    }
}
