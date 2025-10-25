<?php
// src/Expectation.php

namespace Testify;

use PHPUnit\Framework\Assert;

class Expectation
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function toBe($expected): void
    {
        Assert::assertEquals($expected, $this->value);
    }

    public function toBeTrue(): void
    {
        Assert::assertTrue($this->value);
    }

    public function toBeFalse(): void
    {
        Assert::assertFalse($this->value);
    }

    public function toBeNull(): void
    {
        Assert::assertNull($this->value);
    }

    public function toBeTruthy(): void
    {
        Assert::assertTrue((bool) $this->value);
    }

    public function toBeFalsy(): void
    {
        Assert::assertFalse((bool) $this->value);
    }

    public function toEqual($expected): void
    {
        Assert::assertEquals($expected, $this->value);
    }

    public function toBeGreaterThan($expected): void
    {
        Assert::assertGreaterThan($expected, $this->value);
    }

    public function toBeGreaterThanOrEqual($expected): void
    {
        Assert::assertGreaterThanOrEqual($expected, $this->value);
    }

    public function toBeLessThan($expected): void
    {
        Assert::assertLessThan($expected, $this->value);
    }

    public function toBeLessThanOrEqual($expected): void
    {
        Assert::assertLessThanOrEqual($expected, $this->value);
    }

    public function toContain($expected): void
    {
        if (is_array($this->value) || $this->value instanceof \Traversable) {
            Assert::assertContains($expected, $this->value);
        } elseif (is_string($this->value)) {
            Assert::assertStringContainsString($expected, $this->value);
        } else {
            throw new \InvalidArgumentException('toContain can only be used with arrays, traversables, or strings');
        }
    }

    public function toHaveLength(int $expected): void
    {
        if (is_string($this->value)) {
            Assert::assertEquals($expected, strlen($this->value));
        } elseif (is_array($this->value) || $this->value instanceof \Countable) {
            Assert::assertCount($expected, $this->value);
        } else {
            throw new \InvalidArgumentException('toHaveLength can only be used with strings, arrays, or countable objects');
        }
    }

    public function toThrow(string $exceptionClass): void
    {
        $callback = $this->value;
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Value must be callable for toThrow');
        }

        try {
            $callback();
            Assert::fail('Expected exception was not thrown');
        } catch (\Throwable $e) {
            if ($exceptionClass !== null) {
                Assert::assertInstanceOf($exceptionClass, $e);
            }
        }
    }

    public function toMatchArray(array $expected): void
    {
        Assert::assertEquals($expected, $this->value);
    }

    public function toBeInstanceOf(string $expected): void
    {
        Assert::assertInstanceOf($expected, $this->value);
    }

    public function not(): self
    {
        return new NotExpectation($this->value);
    }
}
