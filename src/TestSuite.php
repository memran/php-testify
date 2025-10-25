<?php

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
     * Add a new describe() block.
     */
    public function addSuite(string $name, callable $callback): void
    {
        $this->suites[] = [
            'name'        => $name,
            'tests'       => [],
            'beforeAll'   => [],
            'afterAll'    => [],
            'beforeEach'  => [],
            'afterEach'   => [],
        ];

        // Run the body so that it()/beforeEach() etc. get registered
        $callback();
    }

    /**
     * Add an it() test to the most recent suite.
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

    /**
     * Helper for hook setters.
     */
    private function &requireActiveSuite(): array
    {
        $idx = \count($this->suites) - 1;
        if ($idx < 0) {
            throw new \RuntimeException(
                "No active describe() while registering a hook. Did you call beforeEach() outside describe()?"
            );
        }

        return $this->suites[$idx];
    }

    /**
     * Get all suites.
     *
     * @return array<int, array{
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
