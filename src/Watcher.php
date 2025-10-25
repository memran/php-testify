<?php

namespace Testify;

/**
 * Watcher
 *
 * A lightweight file-watching loop for php-testify.
 *
 * It:
 *  - Scans project files (src + tests by default)
 *  - Records last modification times
 *  - On change, re-runs the test runner as a child process
 *
 * This keeps the parent process alive, so we don't hit "class redeclare" issues.
 */
final class Watcher
{
    private string $configFile;

    /** @var array{filter:?string,colors:bool,verbose:bool} */
    private array $options;

    /**
     * Paths / globs to watch.
     * You can refine this if you want.
     *
     * @var array<int,string>
     */
    private array $watchGlobs = [
        // source code
        __DIR__ . '/../src/*.php',
        __DIR__ . '/../src/**/*.php',
        // tests
        __DIR__ . '/../tests/*.php',
        __DIR__ . '/../tests/**/*.php',
        // configuration
        __DIR__ . '/../phpunit.config.php',
    ];

    /**
     * @param string $configFile absolute path to phpunit.config.php
     * @param array{filter:?string,colors:bool,verbose:bool} $options
     */
    public function __construct(string $configFile, array $options)
    {
        $this->configFile = $configFile;
        $this->options = [
            'filter'  => $options['filter']  ?? null,
            'colors'  => (bool)($options['colors'] ?? true),
            'verbose' => (bool)($options['verbose'] ?? false),
        ];
    }

    /**
     * Blocking loop:
     *  - run tests once immediately
     *  - then poll for file changes
     *  - on change, clear screen (optional) and rerun
     */
    public function watchLoop(): void
    {
        $lastState = $this->snapshotFiles();

        $this->printBanner();
        $this->runChildOnce();

        while (true) {
            // tiny sleep to avoid pegging CPU
            usleep(300_000); // 300ms

            $currentState = $this->snapshotFiles();

            if ($this->changed($lastState, $currentState)) {
                $lastState = $currentState;

                $this->clearScreen();
                $this->printBanner("file change detected, re-running tests…");
                $this->runChildOnce();
            }
        }
    }

    /**
     * Take a snapshot => array(filepath => mtime int)
     */
    private function snapshotFiles(): array
    {
        $files = [];

        foreach ($this->watchGlobs as $pattern) {
            foreach ($this->globRecursive($pattern) as $file) {
                if (is_file($file)) {
                    $files[$file] = @filemtime($file) ?: 0;
                }
            }
        }

        ksort($files);
        return $files;
    }

    /**
     * Compare two snapshots.
     * If any file added/removed/mtime changed => true
     */
    private function changed(array $old, array $new): bool
    {
        if (count($old) !== count($new)) {
            return true;
        }

        foreach ($new as $path => $mtime) {
            if (!array_key_exists($path, $old)) {
                return true;
            }
            if ($old[$path] !== $mtime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Run the test suite in a FRESH php process with the same flags
     * (no --watch this time).
     *
     * We shell out to: php bin/testify.php [--filter x] [--verbose] [--no-colors]
     */
    private function runChildOnce(): void
    {
        $php = escapeshellarg(PHP_BINARY);

        $cmd = [$php, escapeshellarg(__DIR__ . '/../bin/testify')];

        if ($this->options['filter'] !== null && $this->options['filter'] !== '') {
            $cmd[] = '--filter';
            $cmd[] = escapeshellarg($this->options['filter']);
        }

        if ($this->options['verbose']) {
            $cmd[] = '--verbose';
        }

        if ($this->options['colors'] === false) {
            $cmd[] = '--no-colors';
        }

        // join into a command line string
        $commandline = implode(' ', $cmd);

        // Windows-safe-ish: use `popen/pclose` or `shell_exec`. We'll keep it simple:
        // We want to stream output live, not just capture after.
        // proc_open is the most controllable.
        $descriptorspec = [
            0 => ['pipe', 'r'],  // STDIN
            1 => ['pipe', 'w'],  // STDOUT
            2 => ['pipe', 'w'],  // STDERR
        ];

        $proc = proc_open($commandline, $descriptorspec, $pipes, getcwd());

        if (!\is_resource($proc)) {
            fwrite(STDERR, "[php-testify:watch] Failed to start child process.\n");
            return;
        }

        fclose($pipes[0]); // we won't send input

        // Stream stdout/stderr from child to our stdout
        $this->streamPipe($pipes[1], STDOUT);
        $this->streamPipe($pipes[2], STDERR);

        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_close($proc);
    }

    /**
     * Copy all available data from $from to $to until EOF.
     */
    private function streamPipe($from, $to): void
    {
        stream_set_blocking($from, false);

        // read until EOF
        while (!feof($from)) {
            $chunk = fgets($from);
            if ($chunk === false) {
                // brief pause before next attempt
                usleep(10_000);
                continue;
            }
            fwrite($to, $chunk);
        }
    }

    /**
     * crude recursive glob:
     * - pattern may contain "**" meaning recurse directories
     * - we expand ** manually
     */
    private function globRecursive(string $pattern): array
    {
        // if no ** just glob it normally
        if (strpos($pattern, '**') === false) {
            $g = glob($pattern) ?: [];
            return $g;
        }

        // split on ** to get base dir and tail pattern
        // e.g. /path/src/**/.php  -> base=/path/src/  tail=/*.php
        [$base, $tail] = explode('**', $pattern, 2);
        if ($base === '') {
            $base = '.';
        }

        $base = rtrim($base, '/\\');

        $results = [];

        // BFS / DFS directory walk
        $dirs = [$base];
        while ($dirs) {
            $dir = array_pop($dirs);
            // match current dir with tail
            $g = glob($dir . $tail) ?: [];
            foreach ($g as $match) {
                $results[] = $match;
            }

            // queue subdirs
            $children = glob($dir . '/*', GLOB_ONLYDIR) ?: [];
            foreach ($children as $cdir) {
                $dirs[] = $cdir;
            }
        }

        return $results;
    }

    private function clearScreen(): void
    {
        // We keep this lightweight. On Windows modern terminals accept ANSI clear.
        // If someone doesn't want clears in CI they just won't use --watch in CI.
        if ($this->supportsAnsi()) {
            // ESC[2J = clear screen, ESC[H = cursor home
            echo "\033[2J\033[H";
        } else {
            echo str_repeat(PHP_EOL, 50);
        }
    }

    private function printBanner(string $msg = "watch mode active"): void
    {
        $bar = str_repeat('═', 50);
        echo $bar . PHP_EOL;
        echo "[php-testify] {$msg}" . PHP_EOL;
        echo "Press Ctrl+C to stop." . PHP_EOL;
        echo $bar . PHP_EOL . PHP_EOL;
    }

    private function supportsAnsi(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            // assume Windows 10+ terminal where ANSI is fine
            return true;
        }
        return function_exists('posix_isatty')
            ? @posix_isatty(STDOUT) === true
            : true;
    }
}
