<?php

namespace Testify;

use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Throwable;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;


final class Runner
{
    /** @var array<string,mixed> */
    private array $config;

    /**
     * @param string $configFile Absolute path to phpunit.config.php
     */
    public function __construct(string $configFile)
    {
        if (!is_file($configFile)) {
            throw new RuntimeException("Config file not found: {$configFile}");
        }

        $cfg = require $configFile;
        if (!is_array($cfg)) {
            throw new RuntimeException("Config file must return array: {$configFile}");
        }

        $this->config = $cfg;
    }

    /**
     * Entry point for composer test.
     *
     * @return int exit code (0 = all passed, 1 = any failed/error)
     */
    public function run(): int
    {
        $this->bootstrap();
        $this->loadTestsFromPatterns();

        // materialize our describe()/it() specs into PHPUnit-compatible classes
        $bridge = new SpecBridge();
        $bridge->materializeSpecClasses();

        // Now actually execute everything
        return $this->executeAll();
    }

    /**
     * Load composer autoload, etc.
     */
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

    /**
     * Bring all tests into memory.
     *
     * - For PHPUnit-style tests (FooTest extends PHPUnit\Framework\TestCase):
     *   just including the file declares the class.
     *
     * - For spec-style tests (describe()/it()):
     *   including the file immediately registers tests into Testify\TestSuite.
     */
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
     * Execute all PHPUnit\Framework\TestCase subclasses we can find.
     * We do not rely on PHPUnit internals that changed in v11.
     *
     * Algorithm:
     *  - Find all subclasses of PHPUnit\Framework\TestCase
     *  - For each class:
     *      * reflect public test methods (test* or #[Test])
     *      * run them one by one (fresh instance per test)
     *      * keep timing + status
     *  - Print pretty summary
     *  - Return exit code
     */
    private function executeAll(): int
    {
        $allClasses = $this->collectPhpUnitTestClasses();

        $runStart = microtime(true);

        $globalTotal  = 0;
        $globalPassed = 0;
        $globalFailed = 0;

        // nice readable header per class
        foreach ($allClasses as $className) {
            $methods = $this->collectTestMethods($className);
            if (!$methods) {
                continue;
            }

            $bar = str_repeat('─', 40);
            echo $bar . PHP_EOL;
            echo " Suite: {$className}" . PHP_EOL;
            echo $bar . PHP_EOL;

            // Call optional setUpBeforeClass() once, if defined
            $this->callStaticIfExists($className, 'setUpBeforeClass');

            foreach ($methods as $methodName) {
                $globalTotal++;

                $singleStart = microtime(true);

                $status = 'PASS';
                $errorMsg = null;

                try {
                    // Fresh instance for every test method, like PHPUnit does
                    $testObj = $this->instantiateTestCase($className, $methodName);

                    // setUp()
                    $this->callIfExists($testObj, 'setUp');

                    // actual test
                    $this->callRequired($testObj, $methodName);

                    // tearDown()
                    $this->callIfExists($testObj, 'tearDown');
                } catch (Throwable $t) {
                    $status = 'FAIL';
                    $errorMsg = $t->getMessage();

                    // try to ensure tearDown() is still called if test body blew up
                    if (isset($testObj)) {
                        try {
                            $this->callIfExists($testObj, 'tearDown');
                        } catch (Throwable $ignored) {
                            // swallow secondary teardown errors
                        }
                    }
                }

                if ($status === 'PASS') {
                    $globalPassed++;
                } else {
                    $globalFailed++;
                }

                $singleEnd = microtime(true);
                $durationMs = $this->fmtMs($singleStart, $singleEnd);

                // Output per test
                if ($status === 'PASS') {
                    echo '  ' . $this->green('PASS') . '  '
                        . $this->padRight($methodName, 30)
                        . ' ' . $this->dim($durationMs)
                        . PHP_EOL;
                } else {
                    echo '  ' . $this->red('FAIL') . '  '
                        . $this->padRight($methodName, 30)
                        . ' ' . $this->dim($durationMs)
                        . PHP_EOL;

                    if ($errorMsg !== null && $errorMsg !== '') {
                        echo '        ' . $this->red('↳ ') . $errorMsg . PHP_EOL;
                    }
                }
            }

            // Call optional tearDownAfterClass() once, if defined
            $this->callStaticIfExists($className, 'tearDownAfterClass');

            echo PHP_EOL;
        }

        $runEnd = microtime(true);
        $totalMs = $this->fmtMs($runStart, $runEnd);

        // Summary block
        echo "Summary" . PHP_EOL;
        echo "  Total:   {$globalTotal} tests" . PHP_EOL;
        echo "  Passed:  " . $this->green((string)$globalPassed) . PHP_EOL;
        echo "  Failed:  " . ($globalFailed > 0 ? $this->red((string)$globalFailed) : (string)$globalFailed) . PHP_EOL;
        echo "  Time:    " . $this->dim($totalMs) . PHP_EOL;

        $exitCode = $globalFailed > 0 ? 1 : 0;
        echo PHP_EOL . "Exit code: " . ($exitCode === 0 ? $this->green('0') : $this->red((string)$exitCode)) . PHP_EOL;

        return $exitCode;
    }

    /**
     * Find all classes in memory that extend PHPUnit\Framework\TestCase.
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

        // unique & stable order
        $out = array_values(array_unique($out));

        sort($out);

        return $out;
    }

    /**
     * Return the list of public test methods for a given PHPUnit test class.
     * We treat:
     *   - methods starting with "test"
     *   - OR methods tagged with #[PHPUnit\Framework\Attributes\Test]
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
                // skip inherited methods from PHPUnit base unless re-declared
                continue;
            }

            $name = $m->getName();

            $isNameStyle = str_starts_with($name, 'test');

            $isAttributeStyle = false;
            foreach ($m->getAttributes() as $attr) {
                // PHPUnit 10/11 attribute
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
     * Create a new instance of a PHPUnit test case for the given method.
     * PHPUnit's TestCase historically accepts the test method name in the constructor.
     *
     * If that signature ever changes in PHPUnit 11.x, we'll try fallback no-arg.
     */
    private function instantiateTestCase(string $className, string $methodName): object
    {
        try {
            return new $className($methodName);
        } catch (Throwable $e) {
            // fallback: maybe no-arg constructor in newer PHPUnit.
            return new $className();
        }
    }

    /**
     * Call $object->$method() if it exists (protected or public).
     * This lets us call setUp()/tearDown() even if they're protected.
     */
    private function callIfExists(object $obj, string $method): void
    {
        if (!method_exists($obj, $method)) {
            return;
        }

        $rm = new ReflectionMethod($obj, $method);
        $rm->setAccessible(true);

        // run and translate assertion failures into exceptions (they already are),
        // let them bubble so outer try/catch records FAIL.
        $rm->invoke($obj);
    }

    /**
     * Call static $className::$method() if it exists.
     * Used for setUpBeforeClass / tearDownAfterClass.
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
     * Call a REQUIRED public/protected test method itself.
     * If assertion fails, PHPUnit will typically throw ExpectationFailedException
     * (extends AssertionFailedError). We just let it bubble.
     */
    private function callRequired(object $obj, string $method): void
    {
        $rm = new ReflectionMethod($obj, $method);
        $rm->setAccessible(true);
        $rm->invoke($obj);
    }

    /**
     * formatting helpers (colors + timing)
     */

    private function fmtMs(float $start, float $end): string
    {
        $ms = ($end - $start) * 1000.0;
        return number_format($ms, 2) . 'ms';
    }

    private function green(string $text): string
    {
        return $this->supportsColor() ? "\033[0;32m{$text}\033[0m" : $text;
    }

    private function red(string $text): string
    {
        return $this->supportsColor() ? "\033[0;31m{$text}\033[0m" : $text;
    }

    private function dim(string $text): string
    {
        return $this->supportsColor() ? "\033[2m{$text}\033[0m" : $text;
    }

    private function padRight(string $text, int $width): string
    {
        $len = mb_strlen($text, 'UTF-8');
        if ($len >= $width) {
            return $text;
        }
        return $text . str_repeat(' ', $width - $len);
    }

    private function supportsColor(): bool
    {
        // simple heuristic for Windows Terminal / ANSI support
        if (DIRECTORY_SEPARATOR === '\\') {
            // modern Windows terminals support ANSI escapes,
            // but if you want to be super safe, you could make this configurable.
            return true;
        }

        return function_exists('posix_isatty')
            ? @posix_isatty(STDOUT) === true
            : true;
    }
}
