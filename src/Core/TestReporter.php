<?php

namespace Testify\Core;

use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;
use PHPUnit\TextUI\DefaultResultPrinter;
use PHPUnit\Framework\TestResult;


class TestReporter extends DefaultResultPrinter
{
    private int $testCaseCount = 0;
    private int $totalTestCount = 0;
    private int $currentTestCase = 0;
    private int $currentTestInCase = 0;
    private array $testCaseResults = [];
    private string $currentTestCaseName = '';
    private float $testCaseStartTime = 0;
    private array $currentCaseTests = [];

    public function startTestSuite(TestSuite $suite): void
    {
        if (str_contains($suite->getName(), 'TestSuite')) {
            $this->testCaseCount = count($suite->tests());
            $this->totalTestCount = $this->countTests($suite);
            parent::startTestSuite($suite);
        }
    }

    public function startTest(Test $test): void
    {
        if ($test instanceof TestCase) {
            $testCaseName = $this->getTestCaseName($test);

            if ($testCaseName !== $this->currentTestCaseName) {
                $this->currentTestCase++;
                $this->currentTestCaseName = $testCaseName;
                $this->currentTestInCase = 0;
                $this->currentCaseTests = [];
                $this->testCaseStartTime = microtime(true);
            }

            $this->currentTestInCase++;
        }

        parent::startTest($test);
    }

    public function endTest(Test $test, float $time): void
    {
        if ($test instanceof TestCase) {
            $status = $this->getTestStatus($test);
            $testName = $this->getTestName($test);
            $globalTestNumber = $this->getGlobalTestNumber();

            // Store test result for case summary
            $this->currentCaseTests[] = [
                'number' => $globalTestNumber,
                'name' => $testName,
                'status' => $status,
                'time' => $time
            ];

            $this->printTestResult($globalTestNumber, $testName, $status, $time);
        }

        parent::endTest($test, $time);
    }

    public function endTestSuite(TestSuite $suite): void
    {
        if (str_contains($suite->getName(), 'TestSuite') && !empty($this->currentCaseTests)) {
            $this->printTestCaseSummary();
        }

        parent::endTestSuite($suite);
    }

    private function printTestResult(int $number, string $testName, string $status, float $time): void
    {
        $timeFormatted = $this->formatTime($time);
        $numberFormatted = str_pad($number, strlen((string)$this->totalTestCount), ' ', STR_PAD_LEFT);

        $statusColor = $this->getStatusColor($status);
        $statusText = $this->getStatusText($status);

        $this->write("  {$numberFormatted}. {$testName} ... ");
        $this->writeWithColor($statusColor, $statusText, false);
        $this->write(", time: {$timeFormatted}\n");
    }

    private function printTestCaseSummary(): void
    {
        $passed = count(array_filter($this->currentCaseTests, fn($t) => $t['status'] === 'passed'));
        $total = count($this->currentCaseTests);
        $caseTime = microtime(true) - $this->testCaseStartTime;

        $caseNumber = str_pad($this->currentTestCase, strlen((string)$this->testCaseCount), ' ', STR_PAD_LEFT);
        $status = $passed === $total ? 'passed' : ($passed > 0 ? 'warning' : 'failed');

        $this->write("\n{$caseNumber}/{$this->testCaseCount}. {$this->currentTestCaseName} ... ");
        $this->writeWithColor($status, "{$status} ({$passed}/{$total})", false);
        $this->write(", time: {$this->formatTime($caseTime)}\n");
    }

    public function printResult(TestResult $result): void
    {
        $this->printHeader(result: $result);
        $this->printFooter($result);
    }

    protected function printHeader(TestResult $result): void
    {
        $this->write("\n\n");
        $this->writeWithColor('bold', "TEST EXECUTION RESULTS", true);
        $this->write("\n");
    }

    protected function printFooter(TestResult $result): void
    {
        $this->write("\n" . str_repeat('=', 60) . "\n");

        $assertCount = $result->count() > 0 ? $result->count() : 0;
        $testCount = $result->count();
        $passed = $testCount - $result->failureCount() - $result->errorCount() - $result->skippedCount();
        $failures = $result->failureCount() + $result->errorCount();
        $skipped = $result->skippedCount();

        $color = $failures === 0 ? 'fg-green' : 'fg-red';

        $summary = sprintf(
            "Test cases run: %d/%d, Tests passed: %d/%d, Asserts: %d, Failures: %d, Exceptions: %d, Skipped: %d",
            $this->testCaseCount,
            $this->testCaseCount,
            $passed,
            $testCount,
            $assertCount,
            $failures,
            $result->errorCount(),
            $skipped
        );

        $this->writeWithColor($color, $summary, true);
        $this->write(str_repeat('=', 60) . "\n");
    }

    private function getTestCaseName(TestCase $test): string
    {
        $class = get_class($test);
        return str_replace('Test', '', basename(str_replace('\\', '/', $class)));
    }

    private function getTestName(TestCase $test): string
    {
        return $test->getName();
    }

    private function getTestStatus(TestCase $test): string
    {
        // This would need to track actual test status
        // For now, return passed as placeholder
        return 'passed';
    }

    private function getGlobalTestNumber(): int
    {
        static $counter = 0;
        return ++$counter;
    }

    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'passed' => 'fg-green',
            'failed' => 'fg-red',
            'skipped' => 'fg-yellow',
            'error' => 'fg-red',
            'warning' => 'fg-yellow',
            default => 'fg-white'
        };
    }

    private function getStatusText(string $status): string
    {
        return match ($status) {
            'passed' => 'passed',
            'failed' => 'failed',
            'skipped' => 'skipped',
            'error' => 'error',
            'warning' => 'warning',
            default => $status
        };
    }

    private function formatTime(float $time): string
    {
        if ($time < 1) {
            return round($time * 1000) . 'ms';
        }
        return round($time, 2) . 's';
    }

    private function countTests(TestSuite $suite): int
    {
        $count = 0;
        foreach ($suite->tests() as $test) {
            if ($test instanceof TestSuite) {
                $count += $this->countTests($test);
            } else {
                $count++;
            }
        }
        return $count;
    }
}
