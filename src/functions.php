<?php

declare(strict_types=1);

namespace Testify;

use PHPUnit\Framework\IncompleteTestError;
use PHPUnit\Framework\SkippedWithMessageException;

if (!\function_exists('Testify\\describe')) {
    function describe(string $name, callable $callback): FluentSuiteHandle
    {
        return TestSuite::getInstance()->addSuite($name, $callback);
    }
}

if (!\function_exists('Testify\\it')) {
    function it(string $name, callable $fn): FluentTestHandle
    {
        return TestSuite::getInstance()->addTest($name, $fn);
    }
}

if (!\function_exists('Testify\\test')) {
    function test(string $name, callable $fn): FluentTestHandle
    {
        return TestSuite::getInstance()->addTest($name, $fn);
    }
}

if (!\function_exists('Testify\\expect')) {
    function expect(mixed $actual): Expect
    {
        return new Expect($actual);
    }
}

if (!\function_exists('Testify\\runTestify')) {
    /**
     * @param array{colors?: bool} $options
     */
    function runTestify(array $options = []): int
    {
        $suiteData = TestSuite::getInstance()->all();
        $runner = new TestCase();

        $useColors = (bool) ($options['colors'] ?? false);
        $printer = new Printer($useColors);

        $passed = 0;
        $failed = 0;
        $total = 0;

        foreach ($suiteData as $block) {
            $printer->printStartSuite($block['name']);

            foreach ($block['tests'] as $test) {
                $total++;

                $result = $runner->run($test['fn']);

                if ($result['status'] === 'passed') {
                    $passed++;
                    $printer->printTestPass($test['name'], $result['duration']);
                    continue;
                }

                $failed++;
                $printer->printTestFail(
                    $test['name'],
                    $result['error'] ?? new TestFailureException('Unknown test failure'),
                    $result['duration']
                );
            }

            echo PHP_EOL;
        }

        $printer->markRunEnd();

        $exitCode = $failed === 0 ? 0 : 1;

        $printer->printSummaryBlock($total, $passed, $failed);
        $printer->printExitLine($exitCode);

        return $exitCode;
    }
}

if (!\function_exists('Testify\\beforeAll')) {
    function beforeAll(callable $fn): void
    {
        TestSuite::getInstance()->addBeforeAll($fn);
    }
}

if (!\function_exists('Testify\\afterAll')) {
    function afterAll(callable $fn): void
    {
        TestSuite::getInstance()->addAfterAll($fn);
    }
}

if (!\function_exists('Testify\\beforeEach')) {
    function beforeEach(callable $fn): void
    {
        TestSuite::getInstance()->addBeforeEach($fn);
    }
}

if (!\function_exists('Testify\\afterEach')) {
    function afterEach(callable $fn): void
    {
        TestSuite::getInstance()->addAfterEach($fn);
    }
}

if (!\function_exists('Testify\\group')) {
    function group(string ...$groups): void
    {
        TestSuite::getInstance()->addCurrentSuiteGroups(array_values($groups));
    }
}

if (!\function_exists('Testify\\tag')) {
    function tag(string ...$groups): void
    {
        group(...$groups);
    }
}

if (!\function_exists('Testify\\skip')) {
    function skip(string $reason = 'Skipped'): never
    {
        throw new SkippedWithMessageException($reason);
    }
}

if (!\function_exists('Testify\\incomplete')) {
    function incomplete(string $reason = 'Incomplete'): never
    {
        throw new IncompleteTestError($reason);
    }
}

if (!\function_exists('Testify\\todo')) {
    function todo(string $reason = 'Todo'): never
    {
        incomplete($reason);
    }
}
