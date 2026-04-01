<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Testify\Watcher;

final class WatcherTest extends TestCase
{
    private string $tempDir;
    private string $configFile;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/php-testify-watcher-' . bin2hex(random_bytes(6));

        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . '/tests', 0777, true);
        file_put_contents($this->tempDir . '/bootstrap.php', "<?php\n");
        file_put_contents($this->tempDir . '/tests/example_test.php', "<?php\n");
        $this->configFile = $this->tempDir . '/phpunit.config.php';

        file_put_contents(
            $this->configFile,
            <<<PHP
            <?php

            return [
                'bootstrap' => __DIR__ . '/bootstrap.php',
                'test_patterns' => [
                    __DIR__ . '/tests/*_test.php',
                ],
            ];
            PHP
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $entry) {
                $path = $entry->getPathname();
                $entry->isDir() ? @rmdir($path) : @unlink($path);
            }
        }

        @rmdir($this->tempDir);
    }

    public function testBuildChildCommandReturnsArgumentListWithoutShellStringBuilding(): void
    {
        $watcher = new Watcher($this->configFile, [
            'filter' => 'ExampleSuite',
            'colors' => false,
            'verbose' => true,
        ]);

        $command = $watcher->buildChildCommand();

        self::assertSame(PHP_BINARY, $command[0]);
        self::assertStringEndsWith('/bin/testify', str_replace('\\', '/', $command[1]));
        self::assertSame(['--filter', 'ExampleSuite', '--verbose', '--no-colors'], array_slice($command, 2));
    }

    public function testRunOnceReturnsSuccessfulExitCodeForAValidProject(): void
    {
        file_put_contents(
            $this->tempDir . '/tests/example_test.php',
            <<<'PHP'
            <?php

            declare(strict_types=1);

            use function Testify\describe;
            use function Testify\expect;
            use function Testify\it;

            describe('watcher smoke test', function (): void {
                it('passes', function (): void {
                    expect(2 + 2)->toBe(4);
                });
            });
            PHP
        );

        $watcher = new Watcher($this->configFile, [
            'colors' => false,
            'verbose' => false,
        ]);

        $exitCode = $watcher->runOnce();

        self::assertSame(0, $exitCode);
    }

    public function testSnapshotTracksNestedPhpFiles(): void
    {
        mkdir($this->tempDir . '/tests/nested/deeper', 0777, true);
        file_put_contents($this->tempDir . '/tests/nested/deeper/nested_test.php', "<?php\n");

        $watcher = new Watcher($this->configFile, [
            'colors' => false,
            'verbose' => false,
        ]);

        /** @var array<string, int> $snapshot */
        $snapshot = \Closure::bind(
            function (): array {
                return $this->snapshotFiles();
            },
            $watcher,
            $watcher
        )();

        self::assertArrayHasKey($this->tempDir . '/tests/nested/deeper/nested_test.php', $snapshot);
    }

    public function testStreamProcessPipesDrainsStdoutAndStderr(): void
    {
        $watcher = new Watcher($this->configFile, [
            'colors' => false,
            'verbose' => false,
        ]);

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(
            [PHP_BINARY, '-r', 'for ($i = 0; $i < 1000; $i++) { fwrite(STDOUT, "o"); fwrite(STDERR, "e"); }'],
            $descriptorSpec,
            $pipes,
            $this->tempDir
        );

        self::assertIsResource($process);
        fclose($pipes[0]);

        $stdout = fopen('php://temp', 'w+');
        $stderr = fopen('php://temp', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        \Closure::bind(
            function (array $pipes, $stdout, $stderr, $process): void {
                $this->streamProcessPipes($pipes, $stdout, $stderr, $process);
            },
            $watcher,
            $watcher
        )($pipes, $stdout, $stderr, $process);

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        rewind($stdout);
        rewind($stderr);

        self::assertSame(0, $exitCode);
        self::assertSame(1000, strlen(stream_get_contents($stdout) ?: ''));
        self::assertSame(1000, strlen(stream_get_contents($stderr) ?: ''));

        fclose($stdout);
        fclose($stderr);
    }
}
