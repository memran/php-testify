<?php

declare(strict_types=1);

namespace Testify;

use RuntimeException;

final class TestSuite
{
    /**
     * @var array<int, array{
     *   id: int,
     *   name: string,
     *   parentId: ?int,
     *   groups: list<string>,
     *   beforeAll: list<callable>,
     *   afterAll: list<callable>,
     *   beforeEach: list<callable>,
     *   afterEach: list<callable>,
     *   tests: list<array{
     *     id: int,
     *     name: string,
     *     fn: callable,
     *     groups: list<string>,
     *     datasets: list<array{name:?string,args:list<mixed>}>,
     *     skip: ?string,
     *     incomplete: ?string
     *   }>,
     *   children: list<int>
     * }>
     */
    private array $suites = [];

    /** @var list<int> */
    private array $rootSuiteIds = [];

    /** @var list<int> */
    private array $activeSuiteStack = [];

    private int $nextSuiteId = 1;
    private int $nextTestId = 1;

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

    public function addSuite(string $name, callable $callback): FluentSuiteHandle
    {
        $suiteId = $this->nextSuiteId++;
        $parentId = $this->activeSuiteStack[\count($this->activeSuiteStack) - 1] ?? null;

        $this->suites[$suiteId] = [
            'id' => $suiteId,
            'name' => $name,
            'parentId' => $parentId,
            'groups' => [],
            'beforeAll' => [],
            'afterAll' => [],
            'beforeEach' => [],
            'afterEach' => [],
            'tests' => [],
            'children' => [],
        ];

        if (\is_int($parentId)) {
            $this->suites[$parentId]['children'][] = $suiteId;
        } else {
            $this->rootSuiteIds[] = $suiteId;
        }

        $this->activeSuiteStack[] = $suiteId;

        try {
            $callback();
        } finally {
            array_pop($this->activeSuiteStack);
        }

        return new FluentSuiteHandle($this, $suiteId);
    }

    public function addTest(string $name, callable $fn): FluentTestHandle
    {
        $suiteId = $this->requireActiveSuiteId();
        $testId = $this->nextTestId++;

        $this->suites[$suiteId]['tests'][] = [
            'id' => $testId,
            'name' => $name,
            'fn' => $fn,
            'groups' => [],
            'datasets' => [],
            'skip' => null,
            'incomplete' => null,
        ];

        return new FluentTestHandle($this, $suiteId, $testId);
    }

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
     * @param list<string> $groups
     */
    public function addSuiteGroups(int $suiteId, array $groups): void
    {
        $suite = &$this->requireSuiteById($suiteId);
        $suite['groups'] = $this->mergeGroups($suite['groups'], $groups);
    }

    /**
     * @param list<string> $groups
     */
    public function addCurrentSuiteGroups(array $groups): void
    {
        $this->addSuiteGroups($this->requireActiveSuiteId(), $groups);
    }

    public function markTestSkipped(int $suiteId, int $testId, ?string $reason): void
    {
        $test = &$this->requireTestById($suiteId, $testId);
        $test['skip'] = $reason ?? 'Skipped';
    }

    public function markTestIncomplete(int $suiteId, int $testId, ?string $reason): void
    {
        $test = &$this->requireTestById($suiteId, $testId);
        $test['incomplete'] = $reason ?? 'Incomplete';
    }

    /**
     * @param list<string> $groups
     */
    public function addTestGroups(int $suiteId, int $testId, array $groups): void
    {
        $test = &$this->requireTestById($suiteId, $testId);
        $test['groups'] = $this->mergeGroups($test['groups'], $groups);
    }

    /**
     * @param iterable<mixed> $datasets
     */
    public function setTestDatasets(int $suiteId, int $testId, iterable $datasets): void
    {
        $normalized = [];

        foreach ($datasets as $name => $dataset) {
            $datasetName = \is_string($name) && $name !== '' ? $name : null;

            if (\is_array($dataset) && array_key_exists('args', $dataset)) {
                $args = $dataset['args'];
                $normalized[] = [
                    'name' => isset($dataset['name']) && \is_string($dataset['name']) && $dataset['name'] !== '' ? $dataset['name'] : $datasetName,
                    'args' => \is_array($args) ? array_values($args) : [$args],
                ];
                continue;
            }

            if (\is_array($dataset)) {
                $normalized[] = [
                    'name' => $datasetName,
                    'args' => array_values($dataset),
                ];
                continue;
            }

            $normalized[] = [
                'name' => $datasetName,
                'args' => [$dataset],
            ];
        }

        $this->requireTestById($suiteId, $testId)['datasets'] = $normalized;
    }

    public function reset(): void
    {
        $this->suites = [];
        $this->rootSuiteIds = [];
        $this->activeSuiteStack = [];
        $this->nextSuiteId = 1;
        $this->nextTestId = 1;
    }

    /**
     * @return list<array{
     *   id: int,
     *   name: string,
     *   parentId: ?int,
     *   groups: list<string>,
     *   beforeAll: list<callable>,
     *   afterAll: list<callable>,
     *   beforeEach: list<callable>,
     *   afterEach: list<callable>,
     *   tests: list<array{
     *     id: int,
     *     name: string,
     *     fn: callable,
     *     groups: list<string>,
     *     datasets: list<array{name:?string,args:list<mixed>}>,
     *     skip: ?string,
     *     incomplete: ?string
     *   }>,
     *   children: list<array>
     * }>
     */
    public function all(): array
    {
        $suites = [];

        foreach ($this->rootSuiteIds as $suiteId) {
            $suites[] = $this->buildSuiteTree($suiteId);
        }

        return $suites;
    }

    /**
     * @return array{
     *   id: int,
     *   name: string,
     *   parentId: ?int,
     *   groups: list<string>,
     *   beforeAll: list<callable>,
     *   afterAll: list<callable>,
     *   beforeEach: list<callable>,
     *   afterEach: list<callable>,
     *   tests: list<array{
     *     id: int,
     *     name: string,
     *     fn: callable,
     *     groups: list<string>,
     *     datasets: list<array{name:?string,args:list<mixed>}>,
     *     skip: ?string,
     *     incomplete: ?string
     *   }>,
     *   children: list<array>
     * }
     */
    private function buildSuiteTree(int $suiteId): array
    {
        $suite = $this->suites[$suiteId];
        $children = [];

        foreach ($suite['children'] as $childId) {
            $children[] = $this->buildSuiteTree($childId);
        }

        $suite['children'] = $children;

        return $suite;
    }

    /**
     * @return array{
     *   id: int,
     *   name: string,
     *   parentId: ?int,
     *   groups: list<string>,
     *   beforeAll: list<callable>,
     *   afterAll: list<callable>,
     *   beforeEach: list<callable>,
     *   afterEach: list<callable>,
     *   tests: list<array{
     *     id: int,
     *     name: string,
     *     fn: callable,
     *     groups: list<string>,
     *     datasets: list<array{name:?string,args:list<mixed>}>,
     *     skip: ?string,
     *     incomplete: ?string
     *   }>,
     *   children: list<int>
     * }
     */
    private function &requireActiveSuite(): array
    {
        return $this->requireSuiteById($this->requireActiveSuiteId());
    }

    private function requireActiveSuiteId(): int
    {
        $suiteId = $this->activeSuiteStack[\count($this->activeSuiteStack) - 1] ?? null;

        if (!\is_int($suiteId)) {
            throw new RuntimeException('No active describe() context. Define hooks and tests inside describe().');
        }

        return $suiteId;
    }

    /**
     * @return array{
     *   id: int,
     *   name: string,
     *   parentId: ?int,
     *   groups: list<string>,
     *   beforeAll: list<callable>,
     *   afterAll: list<callable>,
     *   beforeEach: list<callable>,
     *   afterEach: list<callable>,
     *   tests: list<array{
     *     id: int,
     *     name: string,
     *     fn: callable,
     *     groups: list<string>,
     *     datasets: list<array{name:?string,args:list<mixed>}>,
     *     skip: ?string,
     *     incomplete: ?string
     *   }>,
     *   children: list<int>
     * }
     */
    private function &requireSuiteById(int $suiteId): array
    {
        if (!isset($this->suites[$suiteId])) {
            throw new RuntimeException("Unknown suite id: {$suiteId}");
        }

        return $this->suites[$suiteId];
    }

    /**
     * @return array{
     *   id: int,
     *   name: string,
     *   fn: callable,
     *   groups: list<string>,
     *   datasets: list<array{name:?string,args:list<mixed>}>,
     *   skip: ?string,
     *   incomplete: ?string
     * }
     */
    private function &requireTestById(int $suiteId, int $testId): array
    {
        $suite = &$this->requireSuiteById($suiteId);

        foreach ($suite['tests'] as $index => $_test) {
            if ($suite['tests'][$index]['id'] === $testId) {
                return $suite['tests'][$index];
            }
        }

        throw new RuntimeException("Unknown test id: {$testId}");
    }

    /**
     * @param list<string> $existing
     * @param list<string> $incoming
     * @return list<string>
     */
    private function mergeGroups(array $existing, array $incoming): array
    {
        $merged = array_values(array_unique(array_merge($existing, $this->normalizeGroups($incoming))));
        sort($merged);

        return $merged;
    }

    /**
     * @param list<string> $groups
     * @return list<string>
     */
    private function normalizeGroups(array $groups): array
    {
        $normalized = [];

        foreach ($groups as $group) {
            $group = trim($group);
            if ($group === '') {
                continue;
            }

            $normalized[] = $group;
        }

        return array_values(array_unique($normalized));
    }
}
