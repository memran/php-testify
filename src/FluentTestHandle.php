<?php

declare(strict_types=1);

namespace Testify;

final class FluentTestHandle
{
    public function __construct(
        private TestSuite $suite,
        private int $suiteId,
        private int $testId
    ) {
    }

    /**
     * @param iterable<mixed> $datasets
     */
    public function with(iterable $datasets): self
    {
        $this->suite->setTestDatasets($this->suiteId, $this->testId, $datasets);

        return $this;
    }

    public function skip(?string $reason = null): self
    {
        $this->suite->markTestSkipped($this->suiteId, $this->testId, $reason);

        return $this;
    }

    public function incomplete(?string $reason = null): self
    {
        $this->suite->markTestIncomplete($this->suiteId, $this->testId, $reason);

        return $this;
    }

    public function todo(?string $reason = null): self
    {
        return $this->incomplete($reason ?? 'Todo');
    }

    public function group(string ...$groups): self
    {
        $this->suite->addTestGroups($this->suiteId, $this->testId, array_values($groups));

        return $this;
    }

    public function tag(string ...$groups): self
    {
        return $this->group(...$groups);
    }
}
