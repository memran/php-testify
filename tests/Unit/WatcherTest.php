<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Testify\Watcher;

final class WatcherTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/php-testify-watcher-' . bin2hex(random_bytes(6));

        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . '/tests', 0777, true);
        file_put_contents($this->tempDir . '/bootstrap.php', "<?php\n");
        file_put_contents($this->tempDir . '/tests/example_test.php', "<?php\n");
        file_put_contents(
            $this->tempDir . '/phpunit.config.php',
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
        @unlink($this->tempDir . '/tests/example_test.php');
        @unlink($this->tempDir . '/bootstrap.php');
        @unlink($this->tempDir . '/phpunit.config.php');
        @rmdir($this->tempDir . '/tests');
        @rmdir($this->tempDir);
    }

    public function testBuildChildCommandReturnsArgumentListWithoutShellStringBuilding(): void
    {
        $watcher = new Watcher($this->tempDir . '/phpunit.config.php', [
            'filter' => 'ExampleSuite',
            'colors' => false,
            'verbose' => true,
        ]);

        $command = $watcher->buildChildCommand();

        self::assertSame(PHP_BINARY, $command[0]);
        self::assertStringEndsWith('/bin/testify', $command[1]);
        self::assertSame(['--filter', 'ExampleSuite', '--verbose', '--no-colors'], array_slice($command, 2));
    }
}
