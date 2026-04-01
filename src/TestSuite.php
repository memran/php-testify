<?php

declare(strict_types=1);

namespace Testify;

final class TestSuite
{
    /**
     * Structure of $suites:
     *
     * [
     *   [
     *     'name'        => string,
     *     'tests'       => [ ['name' => string, 'fn' => callable], ... ],
     *     'beforeAll'   => list<callable>,
     *     'afterAll'    => list<callable>,
     *     'beforeEach'  => list<callable>,
     *     'afterEach'   => list<callable>,
     *   ],
     *   ...
     * ]
     *
     * Notes:
     * - We collect hook callables as they are registered
     *   so SpecBridge can generate methods that execute them.
     *
     * @var list<array{
     *   name: string,
     *   tests: array<int, array{name: string, fn: callable}>,
     *   beforeAll: list<callable>,
     *   afterAll: list<callable>,
     *   beforeEach: list<callable>,
     *   afterEach: list<callable>
     * }>
     */
    private array $suites = [];

    /** @var list<int> */
    private array $activeSuiteStack = [];

    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add a new describe() block.
     */
    public function addSuite(string $name, callable $callback): void
    {
        $suiteIndex = \count($this->suites);

        $this->suites[] = [
            'name'        => $name,
            'tests'       => [],
            'beforeAll'   => [],
            'afterAll'    => [],
            'beforeEach'  => [],
            'afterEach'   => [],
        ];

        $this->activeSuiteStack[] = $suiteIndex;

        try {
            // Run the body so that it()/beforeEach() etc. get registered.
            $callback();
        } finally {
            array_pop($this->activeSuiteStack);
        }
    }

    /**
     * Add an it() test to the most recent suite.
     */
    public function addTest(string $name, callable $fn): void
    {
        $idx = $this->requireActiveSuiteIndex();

        $this->suites[$idx]['tests'][] = [
            'name' => $name,
            'fn'   => $fn,
        ];
    }

    /**
     * Register hook callables to the active suite.
     */
    public function addBeforeAll(callable $fn): void
    {
        $this->requireActiveSuite()['beforeAll'][] = $fn;
    }

    public function addAfterAll(callable $fn): void
    {
        $this->requireActiveSuite()['afterAll'][] = $fn;
    }

    public function addBeforeEach(callable $fn): void
    {
        $this->requireActiveSuite()['beforeEach'][] = $fn;
    }

    public function addAfterEach(callable $fn): void
    {
        $this->requireActiveSuite()['afterEach'][] = $fn;
    }

    public function reset(): void
    {
        $this->suites = [];
        $this->activeSuiteStack = [];
    }

    /**
     * Helper for hook setters.
     */
    /**
     * @return array{
     *   name: string,
     *   tests: array<int, array{name: string, fn: callable}>,
     *   beforeAll: list<callable>,
     *   afterAll: list<callable>,
     *   beforeEach: list<callable>,
     *   afterEach: list<callable>
     * }
     */
    private function &requireActiveSuite(): array
    {
        $idx = $this->requireActiveSuiteIndex();

        return $this->suites[$idx];
    }

    private function requireActiveSuiteIndex(): int
    {
        $idx = $this->activeSuiteStack[\count($this->activeSuiteStack) - 1] ?? null;
        if (!\is_int($idx)) {
            throw new \RuntimeException(
                "No active describe() context. Define hooks and tests inside describe()."
            );
        }

        return $idx;
    }

    /**
     * Get all suites.
     *
     * @return list<array{
     *   name: string,
     *   tests: array<int, array{name:string, fn:callable}>,
     *   beforeAll: list<callable>,
     *   afterAll: list<callable>,
     *   beforeEach: list<callable>,
     *   afterEach: list<callable>,
     * }>
     */
    public function all(): array
    {
        return $this->suites;
    }
}
