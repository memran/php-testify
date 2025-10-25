<?php

namespace Testify;

use Testify\TestSuite;
use Testify\TestCase;
use Testify\Printer;

if (!\function_exists('describe')) {
    function describe(string $name, callable $callback): void
    {
        TestSuite::getInstance()->addSuite($name, $callback);
    }
}

if (!\function_exists('it')) {
    function it(string $name, callable $fn): void
    {
        TestSuite::getInstance()->addTest($name, $fn);
    }
}

if (!\function_exists('expect')) {
    function expect(mixed $actual): Expect
    {
        return new Expect($actual);
    }
}

/**
 * Execute all collected tests and render professional output.
 *
 * @param array{colors?:bool} $options
 * @return int exit code (0 if all pass, 1 if any fail)
 */
if (!\function_exists('runTestify')) {
    function runTestify(array $options = []): int
    {
        $suiteData = TestSuite::getInstance()->all();
        $runner    = new TestCase();

        $useColors = $options['colors'] ?? false;
        $printer   = new Printer($useColors);

        $passed = 0;
        $failed = 0;
        $total  = 0;

        foreach ($suiteData as $block) {
            $printer->printStartSuite($block['name']);

            foreach ($block['tests'] as $test) {
                $total++;

                $result = $runner->run($test['fn']);

                if ($result['status'] === 'passed') {
                    $passed++;
                    $printer->printTestPass(
                        $test['name'],
                        $result['duration']
                    );
                } else {
                    $failed++;
                    $printer->printTestFail(
                        $test['name'],
                        $result['error'],
                        $result['duration']
                    );
                }
            }

            echo PHP_EOL; // spacing between suites
        }

        // mark run complete so timing stops
        $printer->markRunEnd();

        $exitCode = ($failed === 0) ? 0 : 1;

        // professional summary box
        $printer->printSummaryBlock($total, $passed, $failed);
        $printer->printExitLine($exitCode);

        return $exitCode;
    }
}
