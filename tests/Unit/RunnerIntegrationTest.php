<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RunnerIntegrationTest extends TestCase
{
    public function testRepositoryRunnerCompletesSuccessfullyAgainstRepositoryFixtures(): void
    {
        $command = [PHP_BINARY, __DIR__ . '/../../bin/testify', '--no-colors'];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__, 2));

        self::assertIsResource($process);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        self::assertSame('', $stderr);
        self::assertSame(0, $exitCode, $stdout);
        self::assertStringContainsString('Summary', $stdout);
        self::assertStringContainsString('ExitCode', $stdout);
        self::assertStringContainsString('Suite: php-testify expectation API', $stdout);
        self::assertStringNotContainsString('Testify\\Generated', $stdout);
    }
}
