<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;
use Testify\Watcher;

#[RequiresOperatingSystemFamily('Windows')]
final class WindowsWatcherIntegrationTest extends TestCase
{
    private string $tempDir;
    private string $configFile;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '\\php-testify-win-watcher-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir . '\\tests', 0777, true);

        file_put_contents($this->tempDir . '\\bootstrap.php', "<?php\r\n");
        file_put_contents(
            $this->tempDir . '\\tests\\example_test.php',
            <<<'PHP'
            <?php

            declare(strict_types=1);

            use function Testify\describe;
            use function Testify\expect;
            use function Testify\it;

            describe('windows watch smoke test', function (): void {
                it('passes on windows', function (): void {
                    expect('watch')->toBe('watch');
                });
            });
            PHP
        );

        $this->configFile = $this->tempDir . '\\phpunit.config.php';
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
        @unlink($this->tempDir . '\\tests\\example_test.php');
        @unlink($this->tempDir . '\\bootstrap.php');
        @unlink($this->configFile);
        @rmdir($this->tempDir . '\\tests');
        @rmdir($this->tempDir);
    }

    public function testWatchModeCanSpawnTheChildRunnerOnWindows(): void
    {
        $watcher = new Watcher($this->configFile, [
            'colors' => false,
            'verbose' => false,
        ]);

        $exitCode = $watcher->runOnce();

        self::assertSame(0, $exitCode);
    }
}
