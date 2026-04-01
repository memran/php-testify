<?php

declare(strict_types=1);

namespace Testify;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Throwable;

/**
 * Executes PHPUnit classes and Testify describe()/it() suites in one pass.
 */
final class Runner
{
    /** @var array<string, mixed> */
    private array $config;

    /** @var array{filter:?string,colors:bool,verbose:bool,groups:list<string>,excludeGroups:list<string>} */
    private array $options;

    /**
     * @param array{
     *   filter?:?string,
     *   colors?:bool,
     *   verbose?:bool,
     *   groups?:list<string>,
     *   excludeGroups?:list<string>
     * } $options
     */
    public function __construct(string $configFile, array $options = [])
    {
        if (!is_file($configFile)) {
            throw new RuntimeException("Config file not found: {$configFile}");
        }

        $config = require $configFile;
        if (!is_array($config)) {
            throw new RuntimeException("Config file must return array: {$configFile}");
        }

        $defaultColors = isset($config['colors']) ? (bool) $config['colors'] : true;

        $this->config = $config;
        $this->options = [
            'filter' => $options['filter'] ?? null,
            'colors' => array_key_exists('colors', $options) ? (bool) $options['colors'] : $defaultColors,
            'verbose' => (bool) ($options['verbose'] ?? false),
            'groups' => $this->normalizeGroups($options['groups'] ?? []),
            'excludeGroups' => $this->normalizeGroups($options['excludeGroups'] ?? []),
        ];
    }

    public function run(): int
    {
        $this->bootstrap();

        $suiteRegistry = TestSuite::getInstance();
        $suiteRegistry->reset();

        $this->loadTestsFromPatterns();

        return $this->executeAll($suiteRegistry->all());
    }

    private function bootstrap(): void
    {
        $bootstrapFile = $this->config['bootstrap'] ?? null;

        if (!is_string($bootstrapFile) || $bootstrapFile === '') {
            throw new RuntimeException('Invalid bootstrap path in config.');
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
            throw new RuntimeException('No test_patterns defined in config.');
        }

        foreach ($this->expandTestFiles(array_values($patterns)) as $file) {
            require_once $file;
        }
    }

    /**
     * @param list<array{
     *   id:int,
     *   name:string,
     *   parentId:?int,
     *   groups:list<string>,
     *   beforeAll:list<callable>,
     *   afterAll:list<callable>,
     *   beforeEach:list<callable>,
     *   afterEach:list<callable>,
     *   tests:list<array{
     *     id:int,
     *     name:string,
     *     fn:callable,
     *     groups:list<string>,
     *     datasets:list<array{name:?string,args:list<mixed>}>,
     *     skip:?string,
     *     incomplete:?string
     *   }>,
     *   children:list<array>
     * }> $specSuites
     */
    private function executeAll(array $specSuites): int
    {
        $runStart = microtime(true);
        $suiteDurations = [];
        $stats = [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'incomplete' => 0,
            'warned' => 0,
        ];

        foreach ($this->collectPhpUnitTestClasses() as $className) {
            $duration = $this->executePhpUnitSuite($className, $stats);
            if ($duration !== null) {
                $suiteDurations[] = [
                    'class' => $className,
                    'duration' => $duration,
                ];
            }
        }

        foreach ($specSuites as $suite) {
            $duration = $this->executeSpecSuiteTree($suite, [], [], [], '', $stats);
            if ($duration !== null) {
                $suiteDurations[] = [
                    'class' => $this->suitePath($suite['name'], ''),
                    'duration' => $duration,
                ];
            }
        }

        $totalDuration = microtime(true) - $runStart;
        usort(
            $suiteDurations,
            static fn (array $left, array $right): int => $right['duration'] <=> $left['duration']
        );

        $exitCode = $stats['failed'] > 0 ? 1 : 0;

        echo 'Summary' . PHP_EOL;
        echo $this->renderSummaryTable($stats, $totalDuration, $exitCode, $this->options['colors']) . PHP_EOL;

        if ($suiteDurations !== []) {
            echo 'Slowest suites:' . PHP_EOL;

            foreach (array_slice($suiteDurations, 0, 5) as $index => $suiteInfo) {
                echo '  ' . ($index + 1) . '. '
                    . $this->padRight($suiteInfo['class'], 50)
                    . ' '
                    . $this->dim($this->msOnly($suiteInfo['duration']), $this->options['colors'])
                    . PHP_EOL;
            }
        }

        return $exitCode;
    }

    /**
     * @param class-string<PhpUnitTestCase> $className
     * @param array{total:int,passed:int,failed:int,skipped:int,incomplete:int,warned:int} $stats
     */
    private function executePhpUnitSuite(string $className, array &$stats): ?float
    {
        if ($this->options['groups'] !== [] || $this->options['excludeGroups'] !== []) {
            return null;
        }

        $methods = $this->filterPhpUnitMethods($this->collectTestMethods($className));
        if ($methods === []) {
            return null;
        }

        $suiteStart = microtime(true);
        $this->printSuiteHeader($className);

        try {
            $this->callStaticIfExists($className, 'setUpBeforeClass');
        } catch (Throwable $throwable) {
            $this->recordLifecycleFailure($className, $methods, 'setUpBeforeClass', $throwable, $stats);
            $this->printSuiteFooter($suiteStart);

            return microtime(true) - $suiteStart;
        }

        foreach ($methods as $methodName) {
            $this->executePhpUnitTestCase($className, $methodName, $stats);
        }

        try {
            $this->callStaticIfExists($className, 'tearDownAfterClass');
        } catch (Throwable $throwable) {
            $this->recordSingleFailure($className . '::tearDownAfterClass', 'Suite teardown failed: ' . $throwable->getMessage(), $stats);
        }

        $this->printSuiteFooter($suiteStart);

        return microtime(true) - $suiteStart;
    }

    /**
     * @param array<string, mixed> $suite
     * @param list<callable> $inheritedBeforeEach
     * @param list<callable> $inheritedAfterEach
     * @param list<string> $ancestorGroups
     * @param array{total:int,passed:int,failed:int,skipped:int,incomplete:int,warned:int} $stats
     */
    private function executeSpecSuiteTree(
        array $suite,
        array $inheritedBeforeEach,
        array $inheritedAfterEach,
        array $ancestorGroups,
        string $parentPath,
        array &$stats
    ): ?float {
        $suitePath = $this->suitePath($suite['name'], $parentPath);
        $effectiveGroups = $this->mergeGroups($ancestorGroups, $suite['groups']);
        $filteredTests = $this->filterSpecTests($suite['tests'], $suitePath, $effectiveGroups);
        $childrenToRun = [];

        foreach ($suite['children'] as $child) {
            if ($this->suiteContainsRunnableTests($child, $suitePath, $effectiveGroups)) {
                $childrenToRun[] = $child;
            }
        }

        if ($filteredTests === [] && $childrenToRun === []) {
            return null;
        }

        $suiteStart = microtime(true);
        $this->printSuiteHeader($suitePath);

        $beforeAll = $suite['beforeAll'];
        $afterAll = $suite['afterAll'];
        $beforeEach = array_values(array_merge($inheritedBeforeEach, $suite['beforeEach']));
        $afterEach = array_values(array_merge($suite['afterEach'], $inheritedAfterEach));

        try {
            $this->executeHooks($beforeAll);
        } catch (Throwable $throwable) {
            $labels = array_map(
                fn (array $test): string => $this->buildSpecLabel($suitePath, $test['name'], null),
                $filteredTests
            );
            $this->recordLifecycleFailure($suitePath, $labels, 'beforeAll', $throwable, $stats);
            $this->printSuiteFooter($suiteStart);

            return microtime(true) - $suiteStart;
        }

        foreach ($filteredTests as $test) {
            $this->executeSpecTest($suitePath, $test, $effectiveGroups, $beforeEach, $afterEach, $stats);
        }

        foreach ($childrenToRun as $child) {
            $this->executeSpecSuiteTree(
                $child,
                $beforeEach,
                $afterEach,
                $effectiveGroups,
                $suitePath,
                $stats
            );
        }

        try {
            $this->executeHooks($afterAll);
        } catch (Throwable $throwable) {
            $this->recordSingleFailure($suitePath . '::afterAll', 'Suite teardown failed: ' . $throwable->getMessage(), $stats);
        }

        $this->printSuiteFooter($suiteStart);

        return microtime(true) - $suiteStart;
    }

    /**
     * @param array<string, mixed> $test
     * @param list<string> $suiteGroups
     * @param list<callable> $beforeEach
     * @param list<callable> $afterEach
     * @param array{total:int,passed:int,failed:int,skipped:int,incomplete:int,warned:int} $stats
     */
    private function executeSpecTest(
        string $suitePath,
        array $test,
        array $suiteGroups,
        array $beforeEach,
        array $afterEach,
        array &$stats
    ): void {
        $datasets = $test['datasets'] !== []
            ? $test['datasets']
            : [['name' => null, 'args' => []]];

        foreach ($datasets as $dataset) {
            $start = microtime(true);
            $status = 'PASS';
            $message = null;
            $afterEachAttempted = false;

            if ($test['skip'] !== null) {
                $status = 'SKIP';
                $message = $test['skip'];
            } elseif ($test['incomplete'] !== null) {
                $status = 'INCOMPLETE';
                $message = $test['incomplete'];
            } else {
                try {
                    $this->executeHooks($beforeEach);
                    ($test['fn'])(...$dataset['args']);
                    $this->executeHooksOnce($afterEach, $afterEachAttempted);
                } catch (Throwable $throwable) {
                    $status = $this->classifyThrowable($throwable);
                    $message = $throwable->getMessage();

                    try {
                        $this->executeHooksOnce($afterEach, $afterEachAttempted);
                    } catch (Throwable) {
                    }
                }
            }

            $label = $this->buildSpecLabel($suitePath, $test['name'], $dataset['name']);
            $this->reportTestResult($label, $status, $message, microtime(true) - $start, $stats);
        }
    }

    /**
     * @param list<callable> $hooks
     */
    private function executeHooks(array $hooks): void
    {
        foreach ($hooks as $hook) {
            $hook();
        }
    }

    /**
     * @param list<callable> $hooks
     */
    private function executeHooksOnce(array $hooks, bool &$attempted): void
    {
        if ($attempted) {
            return;
        }

        $attempted = true;
        $this->executeHooks($hooks);
    }

    /**
     * @param list<string> $methods
     * @return list<string>
     */
    private function filterPhpUnitMethods(array $methods): array
    {
        $filter = $this->options['filter'];
        if ($filter === null || $filter === '') {
            return $methods;
        }

        return array_values(
            array_filter(
                $methods,
                static fn (string $method): bool => stripos($method, $filter) !== false
            )
        );
    }

    /**
     * @param list<array{
     *   id:int,
     *   name:string,
     *   fn:callable,
     *   groups:list<string>,
     *   datasets:list<array{name:?string,args:list<mixed>}>,
     *   skip:?string,
     *   incomplete:?string
     * }> $tests
     * @param list<string> $suiteGroups
     * @return list<array{
     *   id:int,
     *   name:string,
     *   fn:callable,
     *   groups:list<string>,
     *   datasets:list<array{name:?string,args:list<mixed>}>,
     *   skip:?string,
     *   incomplete:?string
     * }>
     */
    private function filterSpecTests(array $tests, string $suitePath, array $suiteGroups): array
    {
        return array_values(
            array_filter(
                $tests,
                function (array $test) use ($suitePath, $suiteGroups): bool {
                    $groups = $this->mergeGroups($suiteGroups, $test['groups']);

                    if (!$this->matchesGroupFilters($groups)) {
                        return false;
                    }

                    $filter = $this->options['filter'];
                    if ($filter === null || $filter === '') {
                        return true;
                    }

                    if (stripos($test['name'], $filter) !== false || stripos($suitePath, $filter) !== false) {
                        return true;
                    }

                    foreach ($test['datasets'] as $dataset) {
                        if ($dataset['name'] !== null && stripos($dataset['name'], $filter) !== false) {
                            return true;
                        }
                    }

                    return false;
                }
            )
        );
    }

    /**
     * @param array{total:int,passed:int,failed:int,skipped:int,incomplete:int,warned:int} $stats
     */
    private function executePhpUnitTestCase(string $className, string $methodName, array &$stats): void
    {
        $start = microtime(true);
        $status = 'PASS';
        $message = null;
        $tearDownAttempted = false;

        try {
            $testObject = $this->instantiateTestCase($className, $methodName);
            $this->callIfExists($testObject, 'setUp');
            $this->callRequired($testObject, $methodName);
            $this->callIfExistsOnce($testObject, 'tearDown', $tearDownAttempted);
        } catch (Throwable $throwable) {
            $status = $this->classifyThrowable($throwable);
            $message = $throwable->getMessage();

            if (isset($testObject)) {
                try {
                    $this->callIfExistsOnce($testObject, 'tearDown', $tearDownAttempted);
                } catch (Throwable) {
                }
            }
        }

        $label = $this->options['verbose'] ? "{$className}::{$methodName}" : $methodName;
        $this->reportTestResult($label, $status, $message, microtime(true) - $start, $stats);
    }

    private function printSuiteHeader(string $suiteName): void
    {
        $bar = str_repeat('─', 40);
        echo $bar . PHP_EOL;
        echo " Suite: {$suiteName}" . PHP_EOL;
        echo $bar . PHP_EOL;
    }

    private function printSuiteFooter(float $suiteStart): void
    {
        echo PHP_EOL
            . 'Suite time: '
            . $this->dim($this->fmtMs($suiteStart, microtime(true)), $this->options['colors'])
            . PHP_EOL
            . PHP_EOL;
    }

    /**
     * @param array{total:int,passed:int,failed:int,skipped:int,incomplete:int,warned:int} $stats
     */
    private function reportTestResult(
        string $label,
        string $status,
        ?string $message,
        float $durationSeconds,
        array &$stats
    ): void {
        $stats['total']++;

        switch ($status) {
            case 'PASS':
                $stats['passed']++;
                break;
            case 'FAIL':
                $stats['failed']++;
                break;
            case 'SKIP':
                $stats['skipped']++;
                break;
            case 'INCOMPLETE':
                $stats['incomplete']++;
                break;
            case 'WARN':
                $stats['warned']++;
                break;
        }

        [$tagText, $tagColor] = $this->statusPresentation($status);
        echo '  ' . $this->color($tagText, $tagColor, $this->options['colors'])
            . '  ' . $this->padRight($label, 40)
            . ' ' . $this->dim($this->msOnly($durationSeconds), $this->options['colors'])
            . PHP_EOL;

        if ($status !== 'PASS' && $message !== null && $message !== '') {
            echo '        '
                . $this->color('↳ ', $tagColor, $this->options['colors'])
                . $message
                . PHP_EOL;
        }
    }

    /**
     * @param list<string> $labels
     * @param array{total:int,passed:int,failed:int,skipped:int,incomplete:int,warned:int} $stats
     */
    private function recordLifecycleFailure(
        string $suiteName,
        array $labels,
        string $phase,
        Throwable $throwable,
        array &$stats
    ): void {
        foreach ($labels === [] ? ['suite lifecycle'] : $labels as $label) {
            $display = $this->options['verbose'] ? "{$suiteName}::{$label}" : $label;
            $this->recordSingleFailure($display, "Suite {$phase} failed: {$throwable->getMessage()}", $stats, 'suite lifecycle');
        }
    }

    /**
     * @param array{total:int,passed:int,failed:int,skipped:int,incomplete:int,warned:int} $stats
     */
    private function recordSingleFailure(
        string $label,
        string $message,
        array &$stats,
        string $durationLabel = 'suite lifecycle'
    ): void {
        $stats['total']++;
        $stats['failed']++;

        [$tagText, $tagColor] = $this->statusPresentation('FAIL');
        echo '  ' . $this->color($tagText, $tagColor, $this->options['colors'])
            . '  ' . $this->padRight($label, 40)
            . ' ' . $this->dim($durationLabel, $this->options['colors'])
            . PHP_EOL;
        echo '        '
            . $this->color('↳ ', $tagColor, $this->options['colors'])
            . $message
            . PHP_EOL;
    }

    /**
     * @param array{total:int,passed:int,failed:int,skipped:int,incomplete:int,warned:int} $stats
     */
    private function renderSummaryTable(array $stats, float $totalDurSec, int $exitCode, bool $useColor): string
    {
        $row = [
            'Total' => (string) $stats['total'],
            'Pass' => (string) $stats['passed'],
            'Fail' => (string) $stats['failed'],
            'Skipped' => (string) $stats['skipped'],
            'Incomplete' => (string) $stats['incomplete'],
            'Warning' => (string) $stats['warned'],
            'Time (ms)' => $this->msOnly($totalDurSec),
            'ExitCode' => (string) $exitCode,
        ];

        if ((int) $row['Fail'] > 0) {
            $row['Fail'] = $this->color($row['Fail'], 'red', $useColor);
        }
        if ((int) $row['Pass'] > 0) {
            $row['Pass'] = $this->color($row['Pass'], 'green', $useColor);
        }
        if ((int) $row['Skipped'] > 0) {
            $row['Skipped'] = $this->color($row['Skipped'], 'yellow', $useColor);
        }
        if ((int) $row['Incomplete'] > 0) {
            $row['Incomplete'] = $this->color($row['Incomplete'], 'cyan', $useColor);
        }
        if ((int) $row['Warning'] > 0) {
            $row['Warning'] = $this->color($row['Warning'], 'magenta', $useColor);
        }

        $row['ExitCode'] = $this->color($row['ExitCode'], $exitCode === 0 ? 'green' : 'red', $useColor);

        $headers = array_keys($row);
        $values = array_values($row);
        $widths = [];

        foreach ($headers as $index => $header) {
            $valueWidth = mb_strlen($this->stripAnsi($values[$index]), 'UTF-8');
            $widths[$index] = max(mb_strlen($header, 'UTF-8'), $valueWidth);
        }

        $top = '┌';
        $middle = '├';
        $bottom = '└';
        $headerLine = '│';
        $valueLine = '│';

        foreach ($headers as $index => $header) {
            $width = $widths[$index];
            $top .= str_repeat('─', $width + 2) . ($index === array_key_last($headers) ? '┐' : '┬');
            $middle .= str_repeat('─', $width + 2) . ($index === array_key_last($headers) ? '┤' : '┼');
            $bottom .= str_repeat('─', $width + 2) . ($index === array_key_last($headers) ? '┘' : '┴');
            $headerLine .= ' ' . $this->padBoth($header, $width) . ' │';
            $valueLine .= ' ' . $this->padBoth($values[$index], $width, $values[$index]) . ' │';
        }

        return implode(PHP_EOL, [$top, $headerLine, $middle, $valueLine, $bottom]);
    }

    /**
     * @return list<class-string<PhpUnitTestCase>>
     */
    private function collectPhpUnitTestClasses(): array
    {
        $classes = [];

        foreach (get_declared_classes() as $class) {
            if (!is_subclass_of($class, PhpUnitTestCase::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || $reflection->isInternal() || str_starts_with($class, 'PHPUnit\\')) {
                continue;
            }

            if ($reflection->getFileName() === false) {
                continue;
            }

            $classes[] = $class;
        }

        $classes = array_values(array_unique($classes));
        sort($classes);

        return $classes;
    }

    /**
     * @param class-string<PhpUnitTestCase> $className
     * @return list<string>
     */
    private function collectTestMethods(string $className): array
    {
        $methods = [];
        $reflectionClass = new ReflectionClass($className);

        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $className) {
                continue;
            }

            $isAttributeStyle = false;
            foreach ($method->getAttributes() as $attribute) {
                if ($attribute->getName() === 'PHPUnit\\Framework\\Attributes\\Test') {
                    $isAttributeStyle = true;
                    break;
                }
            }

            if (str_starts_with($method->getName(), 'test') || $isAttributeStyle) {
                $methods[] = $method->getName();
            }
        }

        sort($methods);

        return $methods;
    }

    private function instantiateTestCase(string $className, string $methodName): object
    {
        try {
            return new $className($methodName);
        } catch (Throwable) {
            return new $className();
        }
    }

    private function callIfExists(object $object, string $method): void
    {
        if (!method_exists($object, $method)) {
            return;
        }

        (new ReflectionMethod($object, $method))->invoke($object);
    }

    private function callIfExistsOnce(object $object, string $method, bool &$attempted): void
    {
        if ($attempted) {
            return;
        }

        $attempted = true;
        $this->callIfExists($object, $method);
    }

    private function callStaticIfExists(string $className, string $method): void
    {
        if (!method_exists($className, $method)) {
            return;
        }

        (new ReflectionMethod($className, $method))->invoke(null);
    }

    private function callRequired(object $object, string $method): void
    {
        (new ReflectionMethod($object, $method))->invoke($object);
    }

    private function classifyThrowable(Throwable $throwable): string
    {
        $matches = static function (Throwable $candidate, array $types): bool {
            foreach ($types as $type) {
                if ((class_exists($type) || interface_exists($type)) && $candidate instanceof $type) {
                    return true;
                }
            }

            return false;
        };

        if ($matches($throwable, [
            'PHPUnit\\Framework\\SkippedTest',
            'PHPUnit\\Framework\\SkippedTestError',
        ])) {
            return 'SKIP';
        }

        if ($matches($throwable, [
            'PHPUnit\\Framework\\IncompleteTest',
            'PHPUnit\\Framework\\IncompleteTestError',
        ])) {
            return 'INCOMPLETE';
        }

        if ($matches($throwable, [
            'PHPUnit\\Framework\\RiskyTest',
            'PHPUnit\\Framework\\RiskyTestError',
            'PHPUnit\\Framework\\Warning',
        ])) {
            return 'WARN';
        }

        return 'FAIL';
    }

    /**
     * @return array{0:string,1:string}
     */
    private function statusPresentation(string $status): array
    {
        return match ($status) {
            'PASS' => ['PASS', 'green'],
            'FAIL' => ['FAIL', 'red'],
            'SKIP' => ['SKIP', 'yellow'],
            'INCOMPLETE' => ['INC ', 'cyan'],
            'WARN' => ['WARN', 'magenta'],
            default => [$status, 'red'],
        };
    }

    private function fmtMs(float $start, float $end): string
    {
        return number_format(($end - $start) * 1000, 2) . 'ms';
    }

    private function msOnly(float $seconds): string
    {
        return number_format($seconds * 1000, 2) . 'ms';
    }

    private function color(string $text, string $kind, bool $useColor): string
    {
        if (!$useColor) {
            return $text;
        }

        $code = match ($kind) {
            'green' => '0;32',
            'red' => '0;31',
            'yellow' => '0;33',
            'cyan' => '0;36',
            'magenta' => '0;35',
            default => '0',
        };

        return "\033[{$code}m{$text}\033[0m";
    }

    private function dim(string $text, bool $useColor): string
    {
        return $useColor ? "\033[2m{$text}\033[0m" : $text;
    }

    private function padRight(string $text, int $width): string
    {
        $length = mb_strlen($this->stripAnsi($text), 'UTF-8');

        return $length >= $width ? $text : $text . str_repeat(' ', $width - $length);
    }

    private function padBoth(string $text, int $width, ?string $raw = null): string
    {
        $plain = $this->stripAnsi($raw ?? $text);
        $length = mb_strlen($plain, 'UTF-8');

        if ($length >= $width) {
            return $text;
        }

        $totalPad = $width - $length;
        $left = intdiv($totalPad, 2);
        $right = $totalPad - $left;

        return str_repeat(' ', $left) . $text . str_repeat(' ', $right);
    }

    private function stripAnsi(string $text): string
    {
        return preg_replace('/\x1B\[[0-9;]*m/', '', $text) ?? $text;
    }

    /**
     * @param list<mixed> $patterns
     * @return list<string>
     */
    private function expandTestFiles(array $patterns): array
    {
        $files = [];

        foreach ($patterns as $pattern) {
            if (!is_string($pattern) || $pattern === '') {
                throw new RuntimeException('test_patterns must only contain non-empty strings.');
            }

            foreach (glob($pattern) ?: [] as $file) {
                if (is_file($file) && is_readable($file)) {
                    $files[] = $file;
                }
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    /**
     * @param list<string> $groups
     * @return list<string>
     */
    private function normalizeGroups(array $groups): array
    {
        $normalized = [];

        foreach ($groups as $group) {
            $group = trim($group);
            if ($group === '') {
                continue;
            }

            $normalized[] = $group;
        }

        return array_values(array_unique($normalized));
    }

    private function buildSpecLabel(string $suitePath, string $testName, ?string $datasetName): string
    {
        $label = $this->options['verbose'] ? "{$suitePath}::{$testName}" : $testName;

        if ($datasetName !== null && $datasetName !== '') {
            $label .= ' [' . $datasetName . ']';
        }

        return $label;
    }

    private function suitePath(string $suiteName, string $parentPath): string
    {
        return $parentPath === '' ? $suiteName : $parentPath . ' > ' . $suiteName;
    }

    /**
     * @param list<string> $parentGroups
     * @param list<string> $localGroups
     * @return list<string>
     */
    private function mergeGroups(array $parentGroups, array $localGroups): array
    {
        $merged = array_values(array_unique(array_merge($parentGroups, $localGroups)));
        sort($merged);

        return $merged;
    }

    /**
     * @param list<string> $groups
     */
    private function matchesGroupFilters(array $groups): bool
    {
        if ($this->options['groups'] !== [] && array_intersect($this->options['groups'], $groups) === []) {
            return false;
        }

        if ($this->options['excludeGroups'] !== [] && array_intersect($this->options['excludeGroups'], $groups) !== []) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $suite
     * @param list<string> $ancestorGroups
     */
    private function suiteContainsRunnableTests(array $suite, string $parentPath, array $ancestorGroups): bool
    {
        $suitePath = $this->suitePath($suite['name'], $parentPath);
        $suiteGroups = $this->mergeGroups($ancestorGroups, $suite['groups']);

        if ($this->filterSpecTests($suite['tests'], $suitePath, $suiteGroups) !== []) {
            return true;
        }

        foreach ($suite['children'] as $child) {
            if ($this->suiteContainsRunnableTests($child, $suitePath, $suiteGroups)) {
                return true;
            }
        }

        return false;
    }
}
