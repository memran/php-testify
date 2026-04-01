<?php

declare(strict_types=1);

namespace Testify;

final class FluentSuiteHandle
{
    public function __construct(
        private TestSuite $suite,
        private int $suiteId
    ) {
    }

    public function group(string ...$groups): self
    {
        $this->suite->addSuiteGroups($this->suiteId, array_values($groups));

        return $this;
    }

    public function tag(string ...$groups): self
    {
        return $this->group(...$groups);
    }
}
