<?php

declare(strict_types=1);

namespace Testify;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Lightweight polling watcher for php-testify.
 */
final class Watcher
{
    private string $configFile;

    /** @var array{filter:?string,colors:bool,verbose:bool,groups:list<string>,excludeGroups:list<string>} */
    private array $options;

    /** @var list<string> */
    private array $watchDirectories;

    /** @var list<string> */
    private array $watchFiles;

    /**
     * @param array{
     *   filter?:?string,
     *   colors?:bool,
     *   verbose?:bool,
     *   groups?:list<string>,
     *   excludeGroups?:list<string>
     * } $options
     */
    public function __construct(string $configFile, array $options)
    {
        if (!is_file($configFile)) {
            throw new RuntimeException("Config file not found: {$configFile}");
        }

        $this->configFile = $configFile;
        $this->options = [
            'filter'  => $options['filter'] ?? null,
            'colors'  => (bool) ($options['colors'] ?? true),
            'verbose' => (bool) ($options['verbose'] ?? false),
            'groups' => array_values(array_unique(array_filter(array_map('trim', $options['groups'] ?? []), static fn (string $group): bool => $group !== ''))),
            'excludeGroups' => array_values(array_unique(array_filter(array_map('trim', $options['excludeGroups'] ?? []), static fn (string $group): bool => $group !== ''))),
        ];
        ['directories' => $this->watchDirectories, 'files' => $this->watchFiles] = $this->buildWatchTargets();
    }

    public function watchLoop(): void
    {
        $lastState = $this->snapshotFiles();

        $this->printBanner();
        $this->runOnce();

        for (;;) {
            usleep(300_000);

            $currentState = $this->snapshotFiles();

            if ($this->changed($lastState, $currentState)) {
                $lastState = $currentState;

                $this->clearScreen();
                $this->printBanner('file change detected, re-running tests...');
                $this->runOnce();
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function snapshotFiles(): array
    {
        $files = [];

        foreach ($this->watchFiles as $file) {
            if (is_file($file) && is_readable($file)) {
                $files[$file] = (int) (filemtime($file) ?: 0);
            }
        }

        foreach ($this->watchDirectories as $directory) {
            foreach ($this->recursivePhpFiles($directory) as $file) {
                if (is_file($file) && is_readable($file)) {
                    $files[$file] = (int) (filemtime($file) ?: 0);
                }
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * @param array<string, int> $old
     * @param array<string, int> $new
     */
    private function changed(array $old, array $new): bool
    {
        if (count($old) !== count($new)) {
            return true;
        }

        foreach ($new as $path => $mtime) {
            if (!array_key_exists($path, $old) || $old[$path] !== $mtime) {
                return true;
            }
        }

        return false;
    }

    public function runOnce(): int
    {
        return $this->runCommand($this->buildChildCommand());
    }

    /**
     * @param list<string> $command
     */
    private function runCommand(array $command): int
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, getcwd() ?: null);

        if (!is_resource($process)) {
            fwrite(STDERR, "[php-testify:watch] Failed to start child process.\n");
            return 1;
        }

        fclose($pipes[0]);

        $this->streamProcessPipes($pipes, STDOUT, STDERR, $process);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }

    /**
     * @return list<string>
     */
    public function buildChildCommand(): array
    {
        $command = [
            PHP_BINARY,
            __DIR__ . '/../bin/testify',
        ];

        if ($this->options['filter'] !== null && $this->options['filter'] !== '') {
            $command[] = '--filter';
            $command[] = $this->options['filter'];
        }

        if ($this->options['verbose']) {
            $command[] = '--verbose';
        }

        foreach ($this->options['groups'] as $group) {
            $command[] = '--group';
            $command[] = $group;
        }

        foreach ($this->options['excludeGroups'] as $group) {
            $command[] = '--exclude-group';
            $command[] = $group;
        }

        if ($this->options['colors'] === false) {
            $command[] = '--no-colors';
        }

        return $command;
    }

    /**
     * @param array<int, resource> $pipes
     * @param resource $stdoutTarget
     * @param resource $stderrTarget
     * @param resource|null $process
     */
    private function streamProcessPipes(array $pipes, $stdoutTarget, $stderrTarget, $process = null): void
    {
        $streams = [
            ['from' => $pipes[1], 'to' => $stdoutTarget],
            ['from' => $pipes[2], 'to' => $stderrTarget],
        ];

        foreach ($streams as $stream) {
            stream_set_blocking($stream['from'], false);
        }

        while ($streams !== []) {
            $read = array_map(static fn (array $stream) => $stream['from'], $streams);
            $write = [];
            $except = [];
            $selected = @stream_select($read, $write, $except, 0, 200000);

            if ($selected === false) {
                break;
            }

            $receivedData = false;

            if ($selected === 0) {
                $streams = array_values(array_filter(
                    $streams,
                    static fn (array $stream): bool => !feof($stream['from'])
                ));

                if ($streams !== [] && is_resource($process) && proc_get_status($process)['running'] === false) {
                    foreach ($streams as $index => $stream) {
                        $chunk = stream_get_contents($stream['from']);
                        if ($chunk !== false && $chunk !== '') {
                            fwrite($stream['to'], $chunk);
                            $receivedData = true;
                        }

                        if (feof($stream['from'])) {
                            unset($streams[$index]);
                        }
                    }

                    $streams = array_values($streams);

                    if (!$receivedData) {
                        break;
                    }
                }

                continue;
            }

            foreach ($streams as $index => $stream) {
                if (!in_array($stream['from'], $read, true)) {
                    continue;
                }

                $chunk = stream_get_contents($stream['from']);
                if ($chunk !== false && $chunk !== '') {
                    fwrite($stream['to'], $chunk);
                    $receivedData = true;
                }

                if (feof($stream['from'])) {
                    unset($streams[$index]);
                }
            }

            $streams = array_values($streams);
        }
    }

    /**
     * @return array{directories:list<string>,files:list<string>}
     */
    private function buildWatchTargets(): array
    {
        $config = require $this->configFile;
        if (!is_array($config)) {
            throw new RuntimeException("Config file must return array: {$this->configFile}");
        }

        $projectRoot = dirname($this->configFile);
        $files = [realpath($this->configFile) ?: $this->configFile];
        $directories = [];

        $bootstrap = $config['bootstrap'] ?? null;
        if (is_string($bootstrap) && $bootstrap !== '') {
            $files[] = $bootstrap;
        }

        $testPatterns = $config['test_patterns'] ?? [];
        if (is_array($testPatterns)) {
            foreach ($testPatterns as $pattern) {
                if (is_string($pattern) && $pattern !== '') {
                    foreach (glob($pattern) ?: [] as $file) {
                        if ($file !== '') {
                            $files[] = $file;
                        }
                    }
                }
            }
        }

        foreach (['src', 'tests'] as $directory) {
            $fullPath = $projectRoot . DIRECTORY_SEPARATOR . $directory;
            if (is_dir($fullPath)) {
                $directories[] = $fullPath;
            }
        }

        $directories = array_values(array_unique($directories));
        sort($directories);

        $files = array_values(array_unique(array_filter($files, static fn (string $value): bool => $value !== '')));
        sort($files);

        return [
            'directories' => $directories,
            'files' => $files,
        ];
    }

    /**
     * @return list<string>
     */
    private function recursivePhpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            if (strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            if ($path !== '') {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    private function clearScreen(): void
    {
        if ($this->supportsAnsi()) {
            echo "\033[2J\033[H";
            return;
        }

        echo str_repeat(PHP_EOL, 50);
    }

    private function printBanner(string $message = 'watch mode active'): void
    {
        $bar = str_repeat('=', 50);
        echo $bar . PHP_EOL;
        echo "[php-testify] {$message}" . PHP_EOL;
        echo "Press Ctrl+C to stop." . PHP_EOL;
        echo $bar . PHP_EOL . PHP_EOL;
    }

    private function supportsAnsi(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return true;
        }

        return function_exists('posix_isatty')
            ? @posix_isatty(STDOUT) === true
            : true;
    }
}
