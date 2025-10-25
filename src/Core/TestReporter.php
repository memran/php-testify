<?php

namespace Testify\Core;

class TestReporter
{
    private array $suiteStack = [];
    private int $indentLevel = 0;
    private array $currentSuiteResults = ['passed' => 0, 'failed' => 0];

    public function reportStart(string $suiteName): void
    {
        $this->suiteStack[] = $suiteName;
        $this->indentLevel = count($this->suiteStack) - 1;

        $indent = str_repeat('  ', $this->indentLevel);
        echo "{$indent}🧪 {$suiteName}\n";

        $this->currentSuiteResults = ['passed' => 0, 'failed' => 0];
    }

    public function reportSuccess(string $testName): void
    {
        $this->currentSuiteResults['passed']++;
        $indent = str_repeat('  ', $this->indentLevel + 1);
        echo "{$indent}✅ {$testName}\n";
    }

    public function reportFailure(string $testName, string $message): void
    {
        $this->currentSuiteResults['failed']++;
        $indent = str_repeat('  ', $this->indentLevel + 1);
        echo "{$indent}❌ {$testName}\n";
        echo "{$indent}   {$message}\n";
    }

    public function reportSuiteCompletion(): void
    {
        if (!empty($this->suiteStack)) {
            $suiteName = array_pop($this->suiteStack);
            $this->indentLevel = count($this->suiteStack);

            $indent = str_repeat('  ', $this->indentLevel);
            $total = $this->currentSuiteResults['passed'] + $this->currentSuiteResults['failed'];

            if ($this->currentSuiteResults['failed'] > 0) {
                echo "{$indent}📊 {$suiteName}: {$this->currentSuiteResults['passed']}/{$total} passed\n";
            }
        }
    }

    public function reportSummary(int $passed, int $failed, float $duration): void
    {
        $total = $passed + $failed;

        echo "\n" . str_repeat('=', 50) . "\n";
        echo "📊 TEST SUMMARY\n";
        echo str_repeat('=', 50) . "\n";

        if ($failed === 0) {
            echo "🎉 ALL TESTS PASSED! {$passed} tests passed\n";
        } else {
            echo "✅ {$passed} passed\n";
            echo "❌ {$failed} failed\n";
        }

        echo "⏱️  Duration: {$duration}s\n";
        echo str_repeat('=', 50) . "\n";
    }

    public function reportError(string $error): void
    {
        echo "💥 ERROR: {$error}\n";
    }

    public function reportWarning(string $warning): void
    {
        echo "⚠️  WARNING: {$warning}\n";
    }
}
