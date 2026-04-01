<?php

declare(strict_types=1);

namespace Testify;

use RuntimeException;

/**
 * Lightweight polling watcher for php-testify.
 */
final class Watcher
{
    private string $configFile;

    /** @var array{filter:?string,colors:bool,verbose:bool} */
    private array $options;

    /** @var list<string> */
    private array $watchPatterns;

    /**
     * @param array{filter?:?string,colors?:bool,verbose?:bool} $options
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
        ];
        $this->watchPatterns = $this->buildWatchPatterns();
    }

    public function watchLoop(): void
    {
        $lastState = $this->snapshotFiles();

        $this->printBanner();
        $this->runChildOnce();

        for (;;) {
            usleep(300_000);

            $currentState = $this->snapshotFiles();

            if ($this->changed($lastState, $currentState)) {
                $lastState = $currentState;

                $this->clearScreen();
                $this->printBanner('file change detected, re-running tests...');
                $this->runChildOnce();
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function snapshotFiles(): array
    {
        $files = [];

        foreach ($this->watchPatterns as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
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

    private function runChildOnce(): void
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($this->buildChildCommand(), $descriptorSpec, $pipes, getcwd() ?: null);

        if (!is_resource($process)) {
            fwrite(STDERR, "[php-testify:watch] Failed to start child process.\n");

            return;
        }

        fclose($pipes[0]);

        $this->streamPipe($pipes[1], STDOUT);
        $this->streamPipe($pipes[2], STDERR);

        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_close($process);
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

        if ($this->options['colors'] === false) {
            $command[] = '--no-colors';
        }

        return $command;
    }

    /**
     * @param resource $from
     * @param resource $to
     */
    private function streamPipe($from, $to): void
    {
        stream_set_blocking($from, false);

        while (!feof($from)) {
            $chunk = fgets($from);
            if ($chunk === false) {
                usleep(10_000);
                continue;
            }

            fwrite($to, $chunk);
        }
    }

    /**
     * @return list<string>
     */
    private function buildWatchPatterns(): array
    {
        $config = require $this->configFile;
        if (!is_array($config)) {
            throw new RuntimeException("Config file must return array: {$this->configFile}");
        }

        $projectRoot = dirname($this->configFile);
        $patterns = [realpath($this->configFile) ?: $this->configFile];

        $bootstrap = $config['bootstrap'] ?? null;
        if (is_string($bootstrap) && $bootstrap !== '') {
            $patterns[] = $bootstrap;
        }

        $testPatterns = $config['test_patterns'] ?? [];
        if (is_array($testPatterns)) {
            foreach ($testPatterns as $pattern) {
                if (is_string($pattern) && $pattern !== '') {
                    $patterns[] = $pattern;
                }
            }
        }

        foreach (['src', 'tests'] as $directory) {
            $fullPath = $projectRoot . DIRECTORY_SEPARATOR . $directory;
            if (is_dir($fullPath)) {
                $patterns[] = $fullPath . DIRECTORY_SEPARATOR . '*.php';
                $patterns[] = $fullPath . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*.php';
            }
        }

        $normalized = array_values(array_unique(array_filter($patterns, static fn (mixed $value): bool => is_string($value) && $value !== '')));
        sort($normalized);

        return $normalized;
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
