<?php

namespace Testify;

use PHPUnit\TextUI\Output\Printer;
use PHPUnit\TextUI\Output\Default\ProgressColorizer;
use PHPUnit\Util\Color;

/**
 * Minimal custom printer for phpunit.
 *
 * Goal of v1:
 * - prove PHPUnit is calling us
 * - intercept test start / result-ish text
 * - print using our own consistent style
 *
 * We'll refine formatting + summary after we confirm integration.
 */
final class TestifyPrinter implements Printer
{
    /** @var resource|string|null */
    private $out;

    /** @var int */
    private int $testCounter = 0;

    /** @var string|null */
    private ?string $currentSuite = null;

    /**
     * PHPUnit will instantiate us with no args.
     */
    public function __construct()
    {
        $this->out = \fopen('php://stdout', 'w');
    }

    public function print(string $buffer): void
    {
        // This method is called for "final" output (summary etc.)
        // We'll just forward for now.
        $this->writeRaw($buffer);
    }

    public function write(string $buffer): void
    {
        // PHPUnit streams a lot of incremental stuff here:
        // - "Starting test ..."
        // - dots for progress
        // - failure messages
        //
        // For now we'll try to detect simple patterns to produce our own nicer lines.
        //
        // Note: this is intentionally basic MVP to confirm that
        // PHPUnit is routing output through *our* printer.
        //
        $lines = \preg_split('/\r\n|\r|\n/', $buffer);

        foreach ($lines as $line) {
            $trim = \trim($line);

            // Empty -> just forward newline
            if ($trim === '') {
                $this->writeRaw("\n");
                continue;
            }

            // Detect PHPUnit telling us "Running X::testY"
            // Depending on config, PHPUnit does not normally emit this in default printer,
            // so initially we may not see it. We'll just dump text with color so we know we're alive.
            $this->writeRaw(Color::colorizeTextBox('fg-green', "[php-testify] " . $line) . "\n");
        }
    }

    public function flush(): void
    {
        if (\is_resource($this->out)) {
            \fflush($this->out);
        }
    }

    public function writeProgress(string $progress): void
    {
        // PHPUnit calls this for ".", "F", "S", etc.
        // Instead of printing dot/F/S, we'll intercept and print richer info later.
        //
        // For now, just swallow it so default dots disappear.
        // We'll come back and build:
        //   "  1/10. SampleTest::test_foo ... passed"
        //
        // noop for v1
    }

    public function writeProgressWithColor(string $color, string $progress): void
    {
        // Same idea as writeProgress(), but with color hint.
        // We'll swallow for now so PHPUnit doesn't draw the dots.
        //
        // noop for v1
    }

    public function addSpecification(
        string $type,
        string $message
    ): void {
        // optional for junit/teamcity printers etc.
    }

    public function addTestReport(
        string $name,
        string $status,
        float $time,
        ?string $message
    ): void {
        // PHPUnit >=10 reporters use this to report structured test info.
        //
        // This is GOLD for us. Here we can emit *exactly* the format you want.
        //
        // We'll implement it below.
        $this->testCounter++;

        $colorStatus = $status;
        if ($status === 'passed') {
            $colorStatus = Color::colorizeTextBox('fg-green', 'passed');
        } elseif ($status === 'skipped') {
            $colorStatus = Color::colorizeTextBox('fg-yellow', 'skipped');
        } elseif ($status === 'failed') {
            $colorStatus = Color::colorizeTextBox('fg-red', 'failed');
        } elseif ($status === 'error') {
            $colorStatus = Color::colorizeTextBox('fg-red', 'error');
        }

        // Split "SampleTest::test_adds_numbers"
        $suiteName = $name;
        $testName  = $name;
        if (\str_contains($name, '::')) {
            [$suiteName, $testName] = \explode('::', $name, 2);
        }

        // Detect suite changes and print suite header once
        if ($this->currentSuite !== $suiteName) {
            $this->currentSuite = $suiteName;
            $this->writeRaw("\n");
            $this->writeRaw("Suite: {$suiteName}\n");
        }

        // time is in seconds, convert to ms (rounded)
        $ms = (int)\round($time * 1000);

        // Example target format (simplified for now):
        //   1. test_adds_numbers ... passed , time: 0ms
        $this->writeRaw(
            "  {$this->testCounter}. {$testName} ... {$colorStatus} , time: {$ms}ms\n"
        );

        // If failed or error, show message
        if ($message !== null && $message !== '' && $status !== 'passed' && $status !== 'skipped') {
            // indent nicely
            $this->writeRaw("      {$message}\n");
        }
    }
    public function writeRaw(string $buffer): void
    {
        if (\is_resource($this->out)) {
            \fwrite($this->out, $buffer);
        } else {
            echo $buffer;
        }
    }
}
