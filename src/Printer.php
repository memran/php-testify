<?php

namespace Testify;

final class Printer
{
    private bool $useColors;

    /** @var float */
    private float $runStart;

    /** @var float */
    private float $runEnd;

    public function __construct(bool $useColors)
    {
        $this->useColors = $useColors;
        $this->runStart = microtime(true);
        $this->runEnd   = $this->runStart;
    }

    /**
     * Mark the end of the full run so we can print total duration.
     */
    public function markRunEnd(): void
    {
        $this->runEnd = microtime(true);
    }

    /**
     * Full run duration in seconds.
     */
    public function getRunDuration(): float
    {
        return $this->runEnd - $this->runStart;
    }

    /**
     * Suite header.
     */
    public function printStartSuite(string $suiteName): void
    {
        $bar = str_repeat('─', 32);
        // Example:
        // ────────────────────────────────
        //  Suite: Math basics
        // ────────────────────────────────
        echo $bar . PHP_EOL;
        echo ' Suite: ' . $suiteName . PHP_EOL;
        echo $bar . PHP_EOL . PHP_EOL;
    }

    /**
     * Print a passing test line.
     */
    public function printTestPass(string $testName, float $durationSeconds): void
    {
        $status = $this->green('PASS');
        $time   = $this->formatMs($durationSeconds);

        // "  PASS  adds numbers correctly        0.12ms"
        echo '  ' . $status . '  ' . $this->padRight($testName, 30) . ' ' . $this->dim($time) . PHP_EOL;
    }

    /**
     * Print a failing test line + reason.
     */
    public function printTestFail(string $testName, \Throwable $e, float $durationSeconds): void
    {
        $status = $this->red('FAIL');
        $time   = $this->formatMs($durationSeconds);

        echo '  ' . $status . '  ' . $this->padRight($testName, 30) . ' ' . $this->dim($time) . PHP_EOL;

        if ($e instanceof TestFailureException) {
            // assertion error
            echo '        ' . $this->red('Assertion failed') . ': ' . $e->getMessage() . PHP_EOL;
        } else {
            // runtime error
            echo '        ' . $this->red('Error') . ': ' . $e->getMessage() . PHP_EOL;
            echo '        ' . $this->dim('(' . get_class($e) . ' at ' . $e->getFile() . ':' . $e->getLine() . ')') . PHP_EOL;
        }

        echo PHP_EOL; // extra spacing after failed test block
    }

    /**
     * Professional summary block.
     */
    public function printSummaryBlock(int $total, int $passed, int $failed): void
    {
        $totalMs = $this->formatMs($this->getRunDuration());

        echo PHP_EOL;
        echo 'Summary' . PHP_EOL;
        echo '  Total:   ' . $total . ' tests' . PHP_EOL;
        echo '  Passed:  ' . $this->green((string)$passed) . PHP_EOL;
        echo '  Failed:  ' . ($failed > 0 ? $this->red((string)$failed) : (string)$failed) . PHP_EOL;
        echo '  Time:    ' . $this->dim($totalMs) . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * Final exit code line.
     */
    public function printExitLine(int $exitCode): void
    {
        $label = $exitCode === 0
            ? $this->green((string)$exitCode)
            : $this->red((string)$exitCode);

        echo 'Exit code: ' . $label . PHP_EOL;
    }

    /**
     * ---- helpers ---------------------------------------------------------
     */

    /**
     * Format seconds -> "0.12ms"
     */
    private function formatMs(float $seconds): string
    {
        $ms = $seconds * 1000.0;
        return number_format($ms, 2) . 'ms';
    }

    /**
     * Pad a string to a fixed width for alignment.
     *
     * @param string $text
     * @param int    $width
     */
    private function padRight(string $text, int $width): string
    {
        $len = mb_strlen($text, 'UTF-8');
        if ($len >= $width) {
            return $text;
        }
        return $text . str_repeat(' ', $width - $len);
    }

    private function green(string $text): string
    {
        return $this->colorWrap($text, '0;32');
    }

    private function red(string $text): string
    {
        return $this->colorWrap($text, '0;31');
    }

    private function dim(string $text): string
    {
        return $this->useColors
            ? "\033[2m{$text}\033[0m"
            : $text;
    }

    private function colorWrap(string $text, string $code): string
    {
        if ($this->useColors === false) {
            return $text;
        }
        return "\033[{$code}m{$text}\033[0m";
    }
}
