<?php

namespace Testify;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use RuntimeException;
use ReflectionClass;

/**
 * SpecBridge converts Testify's describe()/it() suites
 * into runtime-generated PHPUnit test classes.
 *
 * Each describe() block becomes:
 *   final class Testify\Generated\<SuiteName>_<N>_TestifySpec extends PHPUnit\Framework\TestCase
 *
 * That generated class now also wires hooks:
 *   - beforeAll()   -> static setUpBeforeClass()
 *   - afterAll()    -> static tearDownAfterClass()
 *   - beforeEach()  -> setUp()
 *   - afterEach()   -> tearDown()
 *
 * And each it() becomes a public test_*() method.
 */
final class SpecBridge
{
    /**
     * Generate PHPUnit-compatible classes for all suites.
     *
     * @return array<int, class-string> FQCNs created (not currently used by Runner directly,
     *                                   but good to keep return signature for possible future logic)
     */
    public function materializeSpecClasses(): array
    {
        $suites = TestSuite::getInstance()->all();
        $createdClasses = [];

        foreach ($suites as $suiteIndex => $suite) {
            $fqcn = $this->makeClassName($suite['name'], $suiteIndex);

            $code = $this->generatePhpUnitClassCode(
                $fqcn,
                $suite['tests'],
                $suite['beforeAll'],
                $suite['afterAll'],
                $suite['beforeEach'],
                $suite['afterEach'],
            );

            $ok = eval($code);
            if ($ok === false) {
                throw new RuntimeException(
                    "Failed to eval generated test class for suite '{$suite['name']}'"
                );
            }

            // Inject closures for tests & hooks into the generated class
            $testClosures      = [];
            foreach ($suite['tests'] as $t) {
                $testClosures[] = $t['fn'];
            }

            $beforeAllClosures   = $suite['beforeAll'];
            $afterAllClosures    = $suite['afterAll'];
            $beforeEachClosures  = $suite['beforeEach'];
            $afterEachClosures   = $suite['afterEach'];

            $refClass = new ReflectionClass($fqcn);

            // set __specClosures
            $prop = $refClass->getProperty('__specClosures');
            $prop->setAccessible(true);
            $prop->setValue(null, $testClosures);

            // set hook arrays
            $map = [
                '__beforeAll'  => $beforeAllClosures,
                '__afterAll'   => $afterAllClosures,
                '__beforeEach' => $beforeEachClosures,
                '__afterEach'  => $afterEachClosures,
            ];

            foreach ($map as $propName => $value) {
                $p = $refClass->getProperty($propName);
                $p->setAccessible(true);
                $p->setValue(null, $value);
            }

            $createdClasses[] = $fqcn;
        }

        return $createdClasses;
    }

    /**
     * Make FQCN for a generated suite class.
     */
    private function makeClassName(string $suiteName, int $index): string
    {
        $base = preg_replace('/[^A-Za-z0-9_]+/', '_', $suiteName);
        if ($base === '' || $base === null) {
            $base = 'Suite';
        }
        if (!preg_match('/^[A-Za-z_]/', $base)) {
            $base = 'T_' . $base;
        }

        return 'Testify\\Generated\\' . $base . '_' . $index . '_TestifySpec';
    }

    /**
     * Create the PHP code string for the generated PHPUnit test class.
     *
     * @param string $fqcn
     * @param array<int, array{name:string, fn:callable}> $tests
     * @param list<callable> $beforeAll
     * @param list<callable> $afterAll
     * @param list<callable> $beforeEach
     * @param list<callable> $afterEach
     */
    private function generatePhpUnitClassCode(
        string $fqcn,
        array $tests,
        array $beforeAll,
        array $afterAll,
        array $beforeEach,
        array $afterEach,
    ): string {
        $lastSep   = strrpos($fqcn, '\\');
        $ns        = substr($fqcn, 0, $lastSep);
        $shortName = substr($fqcn, $lastSep + 1);

        // build method bodies for each `it()`
        $methodBlocks = [];
        foreach ($tests as $i => $test) {
            $methodName   = $this->makeMethodName($test['name'], $i);
            $methodBlocks[] = $this->generateMethodCode($methodName, $i);
        }

        $methodsJoined = implode("\n", $methodBlocks);

        // We can't inline closures in code; we fill them after eval().
        // So here we preload them as null.
        $testClosuresInit      = $this->nullArray(count($tests));
        $beforeAllClosuresInit = $this->nullArray(count($beforeAll));
        $afterAllClosuresInit  = $this->nullArray(count($afterAll));
        $beforeEachInit        = $this->nullArray(count($beforeEach));
        $afterEachInit         = $this->nullArray(count($afterEach));

        $code = <<<PHP
        namespace {$ns};

        use PHPUnit\\Framework\\TestCase as PhpUnitTestCase;
        use Testify\\TestFailureException;

        /**
         * AUTO-GENERATED by php-testify SpecBridge.
         * DO NOT EDIT.
         *
         * Hooks mapping:
         *   beforeAll()   -> setUpBeforeClass()
         *   afterAll()    -> tearDownAfterClass()
         *   beforeEach()  -> setUp()
         *   afterEach()   -> tearDown()
         */
        final class {$shortName} extends PhpUnitTestCase
        {
            /**
             * @var array<int, callable>
             */
            private static array \$__specClosures = {$testClosuresInit};

            /**
             * @var array<int, callable>
             */
            private static array \$__beforeAll = {$beforeAllClosuresInit};

            /**
             * @var array<int, callable>
             */
            private static array \$__afterAll = {$afterAllClosuresInit};

            /**
             * @var array<int, callable>
             */
            private static array \$__beforeEach = {$beforeEachInit};

            /**
             * @var array<int, callable>
             */
            private static array \$__afterEach = {$afterEachInit};

            /**
             * PHPUnit will call this before any test methods in this class
             * (in our Runner we do callStaticIfExists(..., 'setUpBeforeClass')).
             */
            public static function setUpBeforeClass(): void
            {
                foreach (self::\$__beforeAll as \$fn) {
                    \$fn();
                }
            }

            /**
             * PHPUnit will call this after all test methods in this class
             * (in our Runner we do callStaticIfExists(..., 'tearDownAfterClass')).
             */
            public static function tearDownAfterClass(): void
            {
                foreach (self::\$__afterAll as \$fn) {
                    \$fn();
                }
            }

            /**
             * PHPUnit calls setUp() before EACH test method.
             * Our Runner also explicitly calls setUp() on each test instance.
             */
            protected function setUp(): void
            {
                foreach (self::\$__beforeEach as \$fn) {
                    \$fn();
                }
            }

            /**
             * PHPUnit calls tearDown() after EACH test method.
             * Our Runner also calls tearDown() explicitly.
             */
            protected function tearDown(): void
            {
                foreach (self::\$__afterEach as \$fn) {
                    \$fn();
                }
            }

            /**
             * Pull a specific it() closure from the static registry.
             */
            public static function getSpecClosure(int \$index): callable
            {
                if (!array_key_exists(\$index, self::\$__specClosures)) {
                    throw new \\RuntimeException("Spec closure not found at index {\$index}");
                }
                return self::\$__specClosures[\$index];
            }

        {$methodsJoined}
        }

        PHP;

        return $code;
    }

    /**
     * Turn "adds numbers correctly" into "test_adds_numbers_correctly".
     */
    private function makeMethodName(string $testName, int $i): string
    {
        $base = preg_replace('/[^A-Za-z0-9_]+/', '_', strtolower($testName));
        if ($base === '' || $base === null) {
            $base = 'case_' . $i;
        }
        if (!preg_match('/^[A-Za-z_]/', $base)) {
            $base = 't_' . $base;
        }
        return 'test_' . $base;
    }

    /**
     * Each test method body just calls its matching closure.
     */
    private function generateMethodCode(string $methodName, int $idx): string
    {
        return <<<PHP

            /**
             * @return void
             */
            public function {$methodName}(): void
            {
                \$fn = self::getSpecClosure({$idx});
                \$fn();
            }

        PHP;
    }

    /**
     * Utility: create "[null, null, null]" for N entries.
     */
    private function nullArray(int $count): string
    {
        if ($count <= 0) {
            return '[]';
        }
        return '[' . implode(', ', array_fill(0, $count, 'null')) . ']';
    }
}
