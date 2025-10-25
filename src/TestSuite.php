<?php

namespace Testify;

final class TestSuite
{
    /**
     * @var array<int, array{
     *   name: string,
     *   tests: array<int, array{name:string, fn:callable}>
     * }>
     */
    private array $suites = [];

    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a new "describe" block.
     *
     * @param string   $name
     * @param callable $callback The body of the describe() block.
     */
    public function addSuite(string $name, callable $callback): void
    {
        $this->suites[] = [
            'name' => $name,
            'tests' => [],
        ];

        // Execute the body so it() calls inside can register tests.
        $callback();
    }

    /**
     * Register a test ("it") into the most recent describe() block.
     *
     * @param string   $name
     * @param callable $fn
     */
    public function addTest(string $name, callable $fn): void
    {
        $idx = \count($this->suites) - 1;
        if ($idx < 0) {
            throw new \RuntimeException(
                "Cannot add test without an active suite. Call describe() first."
            );
        }

        $this->suites[$idx]['tests'][] = [
            'name' => $name,
            'fn'   => $fn,
        ];
    }

    /**
     * Return all collected suites/specs.
     *
     * @return array<int, array{
     *   name: string,
     *   tests: array<int, array{name:string, fn:callable}>
     * }>
     */
    public function all(): array
    {
        return $this->suites;
    }
}
