<?php

namespace Testify;

use Throwable;

/**
 * Vitest/Jest-style fluent expectations.
 */
final class Expect
{
    private mixed $value;
    private bool $negate = false;

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    /**
     * expect($x)->not()->toBeNull();
     */
    public function not(): self
    {
        $clone = clone $this;
        $clone->negate = !$this->negate;
        return $clone;
    }

    private function check(bool $condition, string $message): void
    {
        if ($this->negate) {
            $condition = !$condition;
            $message = 'NOT: ' . $message;
        }

        if (!$condition) {
            throw new TestFailureException($message);
        }
    }

    public function toBe(mixed $expected): void
    {
        $this->check(
            $this->value === $expected,
            "Expected {$this->repr($this->value)} to be {$this->repr($expected)}"
        );
    }

    public function toEqual(mixed $expected): void
    {
        $this->check(
            $this->value == $expected,
            "Expected {$this->repr($this->value)} to equal {$this->repr($expected)}"
        );
    }

    public function toBeTrue(): void
    {
        $this->check(
            $this->value === true,
            "Expected value to be true"
        );
    }

    public function toBeFalse(): void
    {
        $this->check(
            $this->value === false,
            "Expected value to be false"
        );
    }

    public function toBeNull(): void
    {
        $this->check(
            $this->value === null,
            "Expected value to be null"
        );
    }

    public function toBeTruthy(): void
    {
        $this->check(
            (bool)$this->value === true,
            "Expected value to be truthy"
        );
    }

    public function toBeFalsy(): void
    {
        $this->check(
            (bool)$this->value === false,
            "Expected value to be falsy"
        );
    }

    public function toBeGreaterThan(float|int $threshold): void
    {
        $this->check(
            $this->value > $threshold,
            "Expected {$this->repr($this->value)} to be greater than {$threshold}"
        );
    }

    public function toBeLessThan(float|int $threshold): void
    {
        $this->check(
            $this->value < $threshold,
            "Expected {$this->repr($this->value)} to be less than {$threshold}"
        );
    }

    public function toContain(mixed $item): void
    {
        $v = $this->value;
        $found = false;

        if (is_string($v) && is_string($item)) {
            $found = str_contains($v, $item);
        } elseif (is_array($v)) {
            $found = in_array($item, $v, true);
        }

        $this->check(
            $found,
            "Expected value to contain {$this->repr($item)}"
        );
    }

    public function toHaveLength(int $len): void
    {
        $value = $this->value;

        if (is_string($value)) {
            $actual = strlen($value);
        } elseif (is_array($value)) {
            $actual = count($value);
        } elseif (is_countable($value)) {
            $actual = count($value);
        } else {
            $actual = 0;
        }

        $this->check(
            $actual === $len,
            "Expected length {$len}, got {$actual}"
        );
    }

    public function toThrow(string $exceptionClass = Throwable::class): void
    {
        $fn = $this->value;

        if (!is_callable($fn)) {
            throw new TestFailureException(
                "toThrow expects a callable, got " . gettype($fn)
            );
        }

        $thrown = null;
        try {
            $fn();
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $ok = $thrown instanceof $exceptionClass;

        $this->check(
            $ok,
            $thrown
                ? "Expected exception {$exceptionClass}, got " . get_class($thrown)
                : "Expected exception {$exceptionClass}, but nothing was thrown"
        );
    }
    public function toBeSameObject(object $expected): void
    {
        $this->check(
            $this->value === $expected,
            "Expected both variables to reference the same object"
        );
    }

    public function notToBeSameObject(object $expected): void
    {
        $this->check(
            $this->value !== $expected,
            "Expected variables to reference different objects"
        );
    }

    public function toBeInstanceOf(string $className): void
    {
        $this->check(
            $this->value instanceof $className,
            "Expected value to be instance of {$className}"
        );
    }

    private function repr(mixed $v): string
    {
        if (is_string($v)) return '"' . $v . '"';
        if (is_bool($v)) return $v ? 'true' : 'false';
        if ($v === null) return 'null';
        if (is_array($v)) return 'array(' . count($v) . ')';
        if (is_object($v)) return 'object(' . get_class($v) . ')';
        return (string)$v;
    }
}
