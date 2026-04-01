<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RunnerRegressionTest extends TestCase
{
    private string $tempDir;
    private string $configFile;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/php-testify-runner-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . '/tests', 0777, true);

        file_put_contents($this->tempDir . '/bootstrap.php', "<?php\nrequire " . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ";\n");

        $this->configFile = $this->tempDir . '/phpunit.config.php';
        file_put_contents(
            $this->configFile,
            <<<PHP
            <?php

            return [
                'bootstrap' => __DIR__ . '/bootstrap.php',
                'test_patterns' => [
                    __DIR__ . '/tests/*.php',
                ],
                'colors' => false,
            ];
            PHP
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/tests/*.php') ?: [] as $file) {
            @unlink($file);
        }

        @unlink($this->tempDir . '/bootstrap.php');
        @unlink($this->configFile);
        @rmdir($this->tempDir . '/tests');
        @rmdir($this->tempDir);
    }

    public function testPhpUnitTearDownIsNotRetriedAfterItThrows(): void
    {
        $counterFile = $this->tempDir . '/phpunit-teardown-count.txt';
        $testClass = 'FailingTearDownTest' . bin2hex(random_bytes(4));
        $counterFileExport = var_export($counterFile, true);

        file_put_contents(
            $this->tempDir . '/tests/phpunit_teardown.php',
            <<<PHP
            <?php

            declare(strict_types=1);

            final class {$testClass} extends PHPUnit\\Framework\\TestCase
            {
                protected function tearDown(): void
                {
                    \$count = (int) @file_get_contents({$counterFileExport});
                    file_put_contents({$counterFileExport}, (string) (\$count + 1));
                    throw new RuntimeException('tearDown failed');
                }

                public function test_passes_before_teardown(): void
                {
                    self::assertTrue(true);
                }
            }
            PHP
        );

        ['exitCode' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr] = $this->runRunnerSubprocess();

        self::assertSame(1, $exitCode, $stdout . "\n" . $stderr);
        self::assertSame('1', trim((string) file_get_contents($counterFile)));
    }

    public function testSpecAfterEachIsNotRetriedAfterItThrows(): void
    {
        $counterFile = $this->tempDir . '/spec-aftereach-count.txt';
        $counterFileExport = var_export($counterFile, true);

        file_put_contents(
            $this->tempDir . '/tests/spec_aftereach.php',
            <<<PHP
            <?php

            declare(strict_types=1);

            use function Testify\\afterEach;
            use function Testify\\describe;
            use function Testify\\it;

            describe('afterEach retries', function (): void {
                afterEach(function (): void {
                    \$count = (int) @file_get_contents({$counterFileExport});
                    file_put_contents({$counterFileExport}, (string) (\$count + 1));
                    throw new RuntimeException('afterEach failed');
                });

                it('runs once', function (): void {
                });
            });
            PHP
        );

        ['exitCode' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr] = $this->runRunnerSubprocess();

        self::assertSame(1, $exitCode, $stdout . "\n" . $stderr);
        self::assertSame('1', trim((string) file_get_contents($counterFile)));
    }

    public function testNestedSuitesInheritBeforeEachAndExpandDatasets(): void
    {
        file_put_contents(
            $this->tempDir . '/tests/nested_datasets.php',
            <<<'PHP'
            <?php

            declare(strict_types=1);

            use function Testify\beforeEach;
            use function Testify\describe;
            use function Testify\expect;
            use function Testify\it;

            describe('outer', function (): void {
                $state = ['count' => 0];

                beforeEach(function () use (&$state): void {
                    $state['count']++;
                });

                describe('inner', function () use (&$state): void {
                    it('uses inherited setup', function (int $input, int $expected) use (&$state): void {
                        expect($state['count'])->toBeGreaterThan(0);
                        expect($input * 2)->toBe($expected);
                    })->with([
                        'double two' => [2, 4],
                        'double three' => [3, 6],
                    ]);
                });
            });
            PHP
        );

        ['exitCode' => $exitCode, 'stdout' => $stdout] = $this->runRunnerSubprocess(['--filter', 'double']);

        self::assertSame(0, $exitCode, $stdout);
        self::assertStringContainsString('outer > inner', $stdout);
        self::assertStringContainsString('uses inherited setup [double two]', $stdout);
        self::assertStringContainsString('uses inherited setup [double three]', $stdout);
    }

    public function testFluentGroupsCanBeIncludedAndExcludedFromCli(): void
    {
        file_put_contents(
            $this->tempDir . '/tests/grouped_specs.php',
            <<<'PHP'
            <?php

            declare(strict_types=1);

            use function Testify\describe;
            use function Testify\expect;
            use function Testify\it;

            describe('api suite', function (): void {
                it('fast api test', function (): void {
                    expect(true)->toBeTrue();
                })->group('api', 'fast');

                it('slow api test', function (): void {
                    expect(true)->toBeTrue();
                })->group('api', 'slow');
            });
            PHP
        );

        ['exitCode' => $exitCode, 'stdout' => $stdout] = $this->runRunnerSubprocess(['--group', 'api', '--exclude-group', 'slow']);

        self::assertSame(0, $exitCode, $stdout);
        self::assertStringContainsString('fast api test', $stdout);
        self::assertStringNotContainsString('slow api test', $stdout);
    }

    public function testFluentSkipAndIncompleteStatesAreReported(): void
    {
        file_put_contents(
            $this->tempDir . '/tests/skip_and_incomplete.php',
            <<<'PHP'
            <?php

            declare(strict_types=1);

            use function Testify\describe;
            use function Testify\expect;
            use function Testify\incomplete;
            use function Testify\it;
            use function Testify\skip;

            describe('states', function (): void {
                it('is skipped by metadata', function (): void {
                    expect(true)->toBeTrue();
                })->skip('coming soon');

                it('is incomplete at runtime', function (): void {
                    incomplete('finish me');
                });

                it('can skip at runtime', function (): void {
                    skip('env not ready');
                });
            });
            PHP
        );

        ['exitCode' => $exitCode, 'stdout' => $stdout] = $this->runRunnerSubprocess();

        self::assertSame(0, $exitCode, $stdout);
        self::assertStringContainsString('SKIP', $stdout);
        self::assertStringContainsString('INC ', $stdout);
        self::assertStringContainsString('coming soon', $stdout);
        self::assertStringContainsString('finish me', $stdout);
        self::assertStringContainsString('env not ready', $stdout);
    }

    /**
     * @param list<string> $args
     * @return array{exitCode:int,stdout:string,stderr:string}
     */
    private function runRunnerSubprocess(array $args = []): array
    {
        $command = array_merge([PHP_BINARY, dirname(__DIR__, 2) . '/bin/testify', '--no-colors', '--verbose'], $args);
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $this->tempDir);
        self::assertIsResource($process);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'exitCode' => proc_close($process),
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }
}
