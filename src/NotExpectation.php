<?php
// src/NotExpectation.php

namespace Testify;

use PHPUnit\Framework\Assert;

class NotExpectation extends Expectation
{
    public function toBe($expected): void
    {
        Assert::assertNotEquals($expected, $this->value);
    }

    public function toBeTrue(): void
    {
        Assert::assertNotTrue($this->value);
    }

    public function toBeFalse(): void
    {
        Assert::assertNotFalse($this->value);
    }

    public function toBeNull(): void
    {
        Assert::assertNotNull($this->value);
    }

    public function toEqual($expected): void
    {
        Assert::assertNotEquals($expected, $this->value);
    }

    public function toContain($expected): void
    {
        if (is_array($this->value) || $this->value instanceof \Traversable) {
            Assert::assertNotContains($expected, $this->value);
        } elseif (is_string($this->value)) {
            Assert::assertStringNotContainsString($expected, $this->value);
        } else {
            throw new \InvalidArgumentException('toContain can only be used with arrays, traversables, or strings');
        }
    }

    public function toBeInstanceOf(string $expected): void
    {
        Assert::assertNotInstanceOf($expected, $this->value);
    }
}
