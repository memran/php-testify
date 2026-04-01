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

    /**
     * @return array{exitCode:int,stdout:string,stderr:string}
     */
    private function runRunnerSubprocess(): array
    {
        $command = [PHP_BINARY, dirname(__DIR__, 2) . '/bin/testify', '--no-colors'];
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
