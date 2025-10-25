<?php

namespace Testify;

use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Throwable;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Runner for php-testify.
 *
 * - PHPUnit tests + describe/it specs
 * - Hooks: beforeAll / afterAll / beforeEach / afterEach
 * - CLI flags: --filter, --no-colors, --verbose
 * - Per-test & per-suite timing
 * - Slowest-suites benchmark
 * - Status: PASS / FAIL / SKIP / INCOMPLETE / WARN
 * - Compact summary table
 * 
 */
final class Runner
{
    /** @var array<string,mixed> */
    private array $config;

    /** @var array{filter:?string,colors:bool,verbose:bool} */
    private array $options;

    /**
     * @param string $configFile Absolute path to phpunit.config.php
     * @param array{filter:?string,colors:bool,verbose:bool} $options
     */
    public function __construct(string $configFile, array $options = [
        'filter'  => null,
        'colors'  => true,
        'verbose' => false,
    ])
    {
        if (!is_file($configFile)) {
            throw new RuntimeException("Config file not found: {$configFile}");
        }

        $cfg = require $configFile;
        if (!is_array($cfg)) {
            throw new RuntimeException("Config file must return array: {$configFile}");
        }

        $this->config = $cfg;

        $defaultColors = isset($cfg['colors']) ? (bool)$cfg['colors'] : true;

        $this->options = [
            'filter'  => $options['filter']  ?? null,
            'colors'  => array_key_exists('colors', $options) ? (bool)$options['colors'] : $defaultColors,
            'verbose' => (bool)($options['verbose'] ?? false),
        ];
    }

    /**
     * Entry point. Returns exit code (0 = all green / no FAIL).
     */
    public function run(): int
    {
        $this->bootstrap();
        $this->loadTestsFromPatterns();

        // Bridge describe()/it() into generated PHPUnit-style classes
        $bridge = new SpecBridge();
        $bridge->materializeSpecClasses();

        return $this->executeAll();
    }

    private function bootstrap(): void
    {
        $bootstrapFile = $this->config['bootstrap'] ?? null;

        if (!is_string($bootstrapFile) || $bootstrapFile === '') {
            throw new RuntimeException("Invalid bootstrap path in config.");
        }

        if (!is_file($bootstrapFile)) {
            throw new RuntimeException("Bootstrap file not found: {$bootstrapFile}");
        }

        require $bootstrapFile;
    }

    private function loadTestsFromPatterns(): void
    {
        $patterns = $this->config['test_patterns'] ?? [];

        if (!is_array($patterns) || $patterns === []) {
            throw new RuntimeException("No test_patterns defined in config.");
        }

        foreach ($patterns as $pattern) {
            $files = glob($pattern) ?: [];
            foreach ($files as $file) {
                require_once $file;
            }
        }
    }

    /**
     * Execute all test classes with:
     * - hook lifecycle
     * - status classification
     * - timing capture
     * - summary output
     */
    private function executeAll(): int
    {
        $allClasses = $this->collectPhpUnitTestClasses();
        $runStart   = microtime(true);

        $filter   = $this->options['filter'];
        $verbose  = $this->options['verbose'];
        $useColor = $this->options['colors'];

        $globalStats = [
            'total'      => 0,
            'passed'     => 0,
            'failed'     => 0,
            'skipped'    => 0,
            'incomplete' => 0,
            'warned'     => 0,
        ];

        $suiteDurations = []; // for slowest-suite listing

        foreach ($allClasses as $className) {
            $methods = $this->collectTestMethods($className);

            // apply --filter
            if ($filter !== null && $filter !== '') {
                $methods = array_values(array_filter(
                    $methods,
                    fn($m) => stripos($m, $filter) !== false
                ));
            }

            if (!$methods) {
                continue;
            }

            $suiteStart = microtime(true);

            $bar = str_repeat('─', 40);
            echo $bar . PHP_EOL;
            echo " Suite: {$className}" . PHP_EOL;
            echo $bar . PHP_EOL;

            $this->callStaticIfExists($className, 'setUpBeforeClass');

            foreach ($methods as $methodName) {
                $globalStats['total']++;

                $singleStart = microtime(true);

                $status   = 'PASS'; // PASS|FAIL|SKIP|INCOMPLETE|WARN
                $errorMsg = null;

                try {
                    $testObj = $this->instantiateTestCase($className, $methodName);

                    // beforeEach hooks (and user setUp)
                    $this->callIfExists($testObj, 'setUp');

                    // test body
                    $this->callRequired($testObj, $methodName);

                    // afterEach hooks (and user tearDown)
                    $this->callIfExists($testObj, 'tearDown');
                } catch (Throwable $t) {
                    $status   = $this->classifyThrowable($t);
                    $errorMsg = $t->getMessage();

                    // try tearDown() even on failure/skip/etc.
                    if (isset($testObj)) {
                        try {
                            $this->callIfExists($testObj, 'tearDown');
                        } catch (Throwable $ignored) {
                            // ignore teardown secondary failure
                        }
                    }
                }

                // global counters by status
                switch ($status) {
                    case 'PASS':
                        $globalStats['passed']++;
                        break;
                    case 'FAIL':
                        $globalStats['failed']++;
                        break;
                    case 'SKIP':
                        $globalStats['skipped']++;
                        break;
                    case 'INCOMPLETE':
                        $globalStats['incomplete']++;
                        break;
                    case 'WARN':
                        $globalStats['warned']++;
                        break;
                }

                $singleEnd  = microtime(true);
                $durationMs = $this->fmtMs($singleStart, $singleEnd);

                $label = $verbose
                    ? "{$className}::{$methodName}"
                    : $methodName;

                [$tagText, $tagColor] = $this->statusPresentation($status);

                echo '  ' . $this->color($tagText, $tagColor, $useColor)
                    . '  ' . $this->padRight($label, 40)
                    . ' ' . $this->dim($durationMs, $useColor)
                    . PHP_EOL;

                if ($status !== 'PASS' && $errorMsg !== null && $errorMsg !== '') {
                    echo '        '
                        . $this->color('↳ ', $tagColor, $useColor)
                        . $errorMsg
                        . PHP_EOL;
                }
            }

            $this->callStaticIfExists($className, 'tearDownAfterClass');

            $suiteEnd = microtime(true);

            $suiteDurations[] = [
                'class'    => $className,
                'duration' => $suiteEnd - $suiteStart, // seconds
            ];

            echo PHP_EOL
                . "Suite time: "
                . $this->dim($this->fmtMs($suiteStart, $suiteEnd), $useColor)
                . PHP_EOL
                . PHP_EOL;
        }

        $runEnd   = microtime(true);
        $totalDur = $runEnd - $runStart; // seconds float

        // Sort by duration desc and grab top 5
        usort($suiteDurations, function ($a, $b) {
            if ($a['duration'] === $b['duration']) {
                return 0;
            }
            return ($a['duration'] < $b['duration']) ? 1 : -1;
        });
        $topSuites = array_slice($suiteDurations, 0, 5);

        // Compute exit code (nonzero only if any FAIL)
        $exitCode = $globalStats['failed'] > 0 ? 1 : 0;

        // Render compact summary table
        echo "Summary" . PHP_EOL;
        echo $this->renderSummaryTable(
            $globalStats,
            $totalDur,
            $exitCode,
            $useColor
        ) . PHP_EOL;

        // Slowest suites list under the table
        if (count($topSuites) > 0) {
            echo "Slowest suites:" . PHP_EOL;
            $rank = 1;
            foreach ($topSuites as $suiteInfo) {
                $cls = $suiteInfo['class'];
                $ms  = $this->msOnly($suiteInfo['duration']); // duration seconds -> ms text
                echo "  {$rank}. "
                    . $this->padRight($cls, 50)
                    . ' '
                    . $this->dim($ms, $useColor)
                    . PHP_EOL;
                $rank++;
            }
        }

        return $exitCode;
    }

    /**
     * Create and return an ASCII table string for the summary.
     *
     * Columns:
     *   Total | Pass | Fail | Skipped | Incomplete | Warning | Time (ms) | ExitCode
     *
     * We align and draw a box for readability in CI logs.
     */
    private function renderSummaryTable(array $stats, float $totalDurSec, int $exitCode, bool $useColor): string
    {
        // Prepare row values as strings
        $row = [
            'Total'      => (string)$stats['total'],
            'Pass'       => (string)$stats['passed'],
            'Fail'       => (string)$stats['failed'],
            'Skipped'    => (string)$stats['skipped'],
            'Incomplete' => (string)$stats['incomplete'],
            'Warning'    => (string)$stats['warned'],
            'Time (ms)'  => $this->msOnly($totalDurSec),
            'ExitCode'   => (string)$exitCode,
        ];

        // For coloring inside the table: only highlight Fail and ExitCode if non-zero,
        // and highlight Pass if >0. We'll keep it subtle.
        if ((int)$row['Fail'] > 0) {
            $row['Fail'] = $this->color($row['Fail'], 'red', $useColor);
        }
        if ((int)$row['Pass'] > 0) {
            $row['Pass'] = $this->color($row['Pass'], 'green', $useColor);
        }
        if ((int)$row['Skipped'] > 0) {
            $row['Skipped'] = $this->color($row['Skipped'], 'yellow', $useColor);
        }
        if ((int)$row['Incomplete'] > 0) {
            $row['Incomplete'] = $this->color($row['Incomplete'], 'cyan', $useColor);
        }
        if ((int)$row['Warning'] > 0) {
            $row['Warning'] = $this->color($row['Warning'], 'magenta', $useColor);
        }
        if ($exitCode !== 0) {
            $row['ExitCode'] = $this->color($row['ExitCode'], 'red', $useColor);
        } else {
            $row['ExitCode'] = $this->color($row['ExitCode'], 'green', $useColor);
        }

        $headers = array_keys($row);
        $values  = array_values($row);

        // Compute column widths (max of header vs value)
        $widths = [];
        foreach ($headers as $i => $hdr) {
            // strip ANSI when measuring lengths for box padding
            $valPlain = $this->stripAnsi($values[$i]);
            $w = max(mb_strlen($hdr, 'UTF-8'), mb_strlen($valPlain, 'UTF-8'));
            $widths[$i] = $w;
        }

        // Build lines
        $top = '┌';
        $mid = '├';
        $bot = '└';
        $headerLine = '│';
        $valueLine  = '│';

        foreach ($headers as $i => $hdr) {
            $w = $widths[$i];
            $top      .= str_repeat('─', $w + 2) . ($i === count($headers) - 1 ? '┐' : '┬');
            $mid      .= str_repeat('─', $w + 2) . ($i === count($headers) - 1 ? '┤' : '┼');
            $bot      .= str_repeat('─', $w + 2) . ($i === count($headers) - 1 ? '┘' : '┴');

            $headerLine .= ' ' . $this->padBoth($hdr, $w) . ' │';
            $valueLine  .= ' ' . $this->padBoth($values[$i], $w, $values[$i]) . ' │';
        }

        return implode(PHP_EOL, [
            $top,
            $headerLine,
            $mid,
            $valueLine,
            $bot,
        ]);
    }

    /**
     * Gather all subclasses of PHPUnit\Framework\TestCase that were loaded.
     *
     * @return array<int, class-string<PhpUnitTestCase>>
     */
    private function collectPhpUnitTestClasses(): array
    {
        $out = [];

        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, PhpUnitTestCase::class)) {
                $out[] = $class;
            }
        }

        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    /**
     * Find test methods in this PHPUnit test class.
     * We consider "test*" or #[PHPUnit\Framework\Attributes\Test]
     *
     * @param class-string<PhpUnitTestCase> $className
     * @return array<int,string>
     */
    private function collectTestMethods(string $className): array
    {
        $rc = new ReflectionClass($className);
        $methods = [];

        foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->getDeclaringClass()->getName() !== $className) {
                continue;
            }

            $name = $m->getName();

            $isNameStyle = str_starts_with($name, 'test');

            $isAttributeStyle = false;
            foreach ($m->getAttributes() as $attr) {
                if ($attr->getName() === 'PHPUnit\\Framework\\Attributes\\Test') {
                    $isAttributeStyle = true;
                    break;
                }
            }

            if ($isNameStyle || $isAttributeStyle) {
                $methods[] = $name;
            }
        }

        sort($methods);
        return $methods;
    }

    /**
     * Instantiate a PHPUnit test case for a given method.
     */
    private function instantiateTestCase(string $className, string $methodName): object
    {
        try {
            return new $className($methodName);
        } catch (Throwable $e) {
            return new $className();
        }
    }

    /**
     * call setUp() / tearDown() if present (protected or public)
     */
    private function callIfExists(object $obj, string $method): void
    {
        if (!method_exists($obj, $method)) {
            return;
        }

        $rm = new ReflectionMethod($obj, $method);
        $rm->setAccessible(true);
        $rm->invoke($obj);
    }

    /**
     * call setUpBeforeClass() / tearDownAfterClass() if present (static)
     */
    private function callStaticIfExists(string $className, string $method): void
    {
        if (!method_exists($className, $method)) {
            return;
        }

        $rm = new ReflectionMethod($className, $method);
        $rm->setAccessible(true);
        $rm->invoke(null);
    }

    /**
     * Call actual test method.
     */
    private function callRequired(object $obj, string $method): void
    {
        $rm = new ReflectionMethod($obj, $method);
        $rm->setAccessible(true);
        $rm->invoke($obj);
    }

    /**
     * Classify Throwable into FAIL / SKIP / INCOMPLETE / WARN.
     */
    private function classifyThrowable(Throwable $t): string
    {
        // helper closures for class detection
        $isAny = function (Throwable $th, array $candidates): bool {
            foreach ($candidates as $fqcn) {
                if (class_exists($fqcn) || interface_exists($fqcn)) {
                    if ($th instanceof $fqcn) {
                        return true;
                    }
                }
            }
            return false;
        };

        // SKIP
        if ($isAny($t, [
            'PHPUnit\\Framework\\SkippedTest',
            'PHPUnit\\Framework\\SkippedTestError',
        ])) {
            return 'SKIP';
        }

        // INCOMPLETE
        if ($isAny($t, [
            'PHPUnit\\Framework\\IncompleteTest',
            'PHPUnit\\Framework\\IncompleteTestError',
        ])) {
            return 'INCOMPLETE';
        }

        // WARN (risky/warning)
        if ($isAny($t, [
            'PHPUnit\\Framework\\RiskyTest',
            'PHPUnit\\Framework\\RiskyTestError',
            'PHPUnit\\Framework\\Warning',
        ])) {
            return 'WARN';
        }

        // FAIL (assertion-style)
        if ($isAny($t, [
            'PHPUnit\\Framework\\AssertionFailedError',
            'PHPUnit\\Framework\\ExpectationFailedException',
        ])) {
            return 'FAIL';
        }

        // default: treat anything else as FAIL
        return 'FAIL';
    }

    /**
     * Map status to [label, colorKey].
     */
    private function statusPresentation(string $status): array
    {
        return match ($status) {
            'PASS'       => ['PASS', 'green'],
            'FAIL'       => ['FAIL', 'red'],
            'SKIP'       => ['SKIP', 'yellow'],
            'INCOMPLETE' => ['INC ', 'cyan'],
            'WARN'       => ['WARN', 'magenta'],
            default      => [$status, 'red'],
        };
    }

    /**
     * ms formatting helpers
     */
    private function fmtMs(float $start, float $end): string
    {
        $ms = ($end - $start) * 1000.0;
        return number_format($ms, 2) . 'ms';
    }

    private function msOnly(float $sec): string
    {
        $ms = $sec * 1000.0;
        return number_format($ms, 2) . 'ms';
    }

    /**
     * For top suites we stored duration in seconds already,
     * so we can convert by calling msOnly().
     */
    private function fmtMsFloat(float $startSec, float $endSec): string
    {
        $ms = ($endSec - $startSec) * 1000.0;
        return number_format($ms, 2) . 'ms';
    }

    private function color(string $text, string $kind, bool $useColor): string
    {
        if (!$useColor) {
            return $text;
        }

        $code = match ($kind) {
            'green'   => '0;32',
            'red'     => '0;31',
            'yellow'  => '0;33',
            'cyan'    => '0;36',
            'magenta' => '0;35',
            'dim'     => '2',
            default   => '0',
        };

        return "\033[{$code}m{$text}\033[0m";
    }

    private function dim(string $text, bool $useColor): string
    {
        return $useColor
            ? "\033[2m{$text}\033[0m"
            : $text;
    }

    private function padRight(string $text, int $width): string
    {
        $len = mb_strlen($this->stripAnsi($text), 'UTF-8');
        if ($len >= $width) {
            return $text;
        }
        return $text . str_repeat(' ', $width - $len);
    }

    /**
     * Center-ish pad for table cells.
     * If the content has ANSI codes, measure plain, but return colored.
     */
    private function padBoth(string $text, int $width, ?string $rawForLen = null): string
    {
        $plain = $this->stripAnsi($rawForLen ?? $text);
        $len   = mb_strlen($plain, 'UTF-8');
        if ($len >= $width) {
            return $text;
        }

        $totalPad = $width - $len;
        $left  = intdiv($totalPad, 2);
        $right = $totalPad - $left;

        return str_repeat(' ', $left) . $text . str_repeat(' ', $right);
    }

    /**
     * Remove ANSI escape sequences so we can compute string widths correctly.
     */
    private function stripAnsi(string $text): string
    {
        // match ESC[...]m
        return preg_replace('/\x1B\[[0-9;]*m/', '', $text) ?? $text;
    }
}
