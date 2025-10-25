<?php

namespace Testify;

final class TestCase
{
    /**
     * Execute a single test closure and capture status, error (if any), and duration.
     *
     * @param callable $fn
     * @return array{
     *   status: string,
     *   duration: float,
     *   error?: \Throwable
     * }
     */
    public function run(callable $fn): array
    {
        $start = microtime(true);

        try {
            $fn();
            $end = microtime(true);

            return [
                'status'   => 'passed',
                'duration' => $end - $start,
            ];
        } catch (\Throwable $e) {
            $end = microtime(true);

            return [
                'status'   => 'failed',
                'duration' => $end - $start,
                'error'    => $e,
            ];
        }
    }
}
