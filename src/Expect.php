<?php

declare(strict_types=1);

namespace Testify;

use Throwable;
use Traversable;

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
        $actual = $this->requireNumericValue('toBeGreaterThan');

        $this->check(
            $actual > $threshold,
            "Expected {$this->repr($this->value)} to be greater than {$threshold}"
        );
    }

    public function toBeLessThan(float|int $threshold): void
    {
        $actual = $this->requireNumericValue('toBeLessThan');

        $this->check(
            $actual < $threshold,
            "Expected {$this->repr($this->value)} to be less than {$threshold}"
        );
    }

    public function toBeGreaterThanOrEqual(float|int $threshold): void
    {
        $actual = $this->requireNumericValue('toBeGreaterThanOrEqual');

        $this->check(
            $actual >= $threshold,
            "Expected {$this->repr($this->value)} to be greater than or equal to {$threshold}"
        );
    }

    public function toBeLessThanOrEqual(float|int $threshold): void
    {
        $actual = $this->requireNumericValue('toBeLessThanOrEqual');

        $this->check(
            $actual <= $threshold,
            "Expected {$this->repr($this->value)} to be less than or equal to {$threshold}"
        );
    }

    public function toBeCloseTo(float|int $expected, float|int $delta = 0.00001): void
    {
        $actual = $this->requireNumericValue('toBeCloseTo');

        $this->check(
            abs($actual - $expected) <= $delta,
            "Expected {$this->repr($this->value)} to be within {$delta} of {$expected}"
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
        } elseif ($v instanceof Traversable) {
            foreach ($v as $entry) {
                if ($entry === $item) {
                    $found = true;
                    break;
                }
            }
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
            throw new TestFailureException(
                'toHaveLength expects a string, array, or Countable value'
            );
        }

        $this->check(
            $actual === $len,
            "Expected length {$len}, got {$actual}"
        );
    }

    public function toHaveCount(int $count): void
    {
        $value = $this->value;

        if (!is_array($value) && !is_countable($value)) {
            throw new TestFailureException(
                'toHaveCount expects an array or Countable value'
            );
        }

        $actual = count($value);

        $this->check(
            $actual === $count,
            "Expected count {$count}, got {$actual}"
        );
    }

    public function toBeEmpty(): void
    {
        $value = $this->value;

        if (is_string($value)) {
            $isEmpty = $value === '';
        } elseif (is_array($value) || is_countable($value)) {
            $isEmpty = count($value) === 0;
        } else {
            $isEmpty = empty($value);
        }

        $this->check($isEmpty, 'Expected value to be empty');
    }

    public function toStartWith(string $prefix): void
    {
        if (!is_string($this->value)) {
            throw new TestFailureException('toStartWith expects a string value');
        }

        $this->check(
            str_starts_with($this->value, $prefix),
            "Expected {$this->repr($this->value)} to start with {$this->repr($prefix)}"
        );
    }

    public function toEndWith(string $suffix): void
    {
        if (!is_string($this->value)) {
            throw new TestFailureException('toEndWith expects a string value');
        }

        $this->check(
            str_ends_with($this->value, $suffix),
            "Expected {$this->repr($this->value)} to end with {$this->repr($suffix)}"
        );
    }

    public function toMatch(string $pattern): void
    {
        if (!is_string($this->value)) {
            throw new TestFailureException('toMatch expects a string value');
        }

        $result = @preg_match($pattern, $this->value);
        if ($result === false) {
            throw new TestFailureException("Invalid regular expression: {$pattern}");
        }

        $this->check(
            $result === 1,
            "Expected {$this->repr($this->value)} to match {$this->repr($pattern)}"
        );
    }

    public function toHaveKey(string|int $key): void
    {
        if (!is_array($this->value)) {
            throw new TestFailureException('toHaveKey expects an array value');
        }

        $this->check(
            array_key_exists($key, $this->value),
            "Expected array to contain key {$this->repr($key)}"
        );
    }

    public function toHaveKeyWithValue(string|int $key, mixed $expected): void
    {
        if (!is_array($this->value)) {
            throw new TestFailureException('toHaveKeyWithValue expects an array value');
        }

        $this->check(
            array_key_exists($key, $this->value) && $this->value[$key] === $expected,
            "Expected array key {$this->repr($key)} to equal {$this->repr($expected)}"
        );
    }

    public function toThrow(string $exceptionClass = Throwable::class): void
    {
        $this->assertThrownException($exceptionClass);
    }

    public function toThrowWithMessage(string $exceptionClass, string $expectedMessage): void
    {
        $thrown = $this->assertThrownException($exceptionClass);

        $this->check(
            $thrown->getMessage() === $expectedMessage,
            "Expected exception message {$this->repr($expectedMessage)}, got {$this->repr($thrown->getMessage())}"
        );
    }

    public function toThrowWithCode(string $exceptionClass, int|string $expectedCode): void
    {
        $thrown = $this->assertThrownException($exceptionClass);

        $this->check(
            $thrown->getCode() === $expectedCode,
            "Expected exception code {$this->repr($expectedCode)}, got {$this->repr($thrown->getCode())}"
        );
    }

    /**
     * @return Throwable
     */
    private function assertThrownException(string $exceptionClass): Throwable
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

        if ($thrown === null) {
            throw new TestFailureException("Expected exception {$exceptionClass}, but nothing was thrown");
        }

        return $thrown;
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
        if (is_string($v)) {
            return '"' . $v . '"';
        }

        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }

        if ($v === null) {
            return 'null';
        }

        if (is_array($v)) {
            return 'array(' . count($v) . ')';
        }

        if (is_object($v)) {
            return 'object(' . get_class($v) . ')';
        }

        return (string)$v;
    }

    private function requireNumericValue(string $method): int|float
    {
        if (!is_int($this->value) && !is_float($this->value)) {
            throw new TestFailureException(
                sprintf('%s expects an integer or float, got %s', $method, get_debug_type($this->value))
            );
        }

        return $this->value;
    }
}
