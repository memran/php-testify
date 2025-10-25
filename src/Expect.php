<?php

namespace Testify;

final class Expect
{
    private mixed $actual;

    public function __construct(mixed $actual)
    {
        $this->actual = $actual;
    }

    /**
     * Strict identity (===).
     */
    public function toBe(mixed $expected, string $message = ''): void
    {
        if ($this->actual !== $expected) {
            $this->throwFail(
                $message !== '' ? $message : "Expected value to be (===) " . $this->export($expected) .
                    " but got " . $this->export($this->actual)
            );
        }
    }

    /**
     * Loose equality (==).
     */
    public function toEqual(mixed $expected, string $message = ''): void
    {
        if ($this->actual != $expected) { // intentional loose comparison
            $this->throwFail(
                $message !== '' ? $message : "Expected value to equal (==) " . $this->export($expected) .
                    " but got " . $this->export($this->actual)
            );
        }
    }

    public function toBeTrue(string $message = ''): void
    {
        if ($this->actual !== true) {
            $this->throwFail(
                $message !== '' ? $message : "Expected true but got " . $this->export($this->actual)
            );
        }
    }

    public function toBeFalse(string $message = ''): void
    {
        if ($this->actual !== false) {
            $this->throwFail(
                $message !== '' ? $message : "Expected false but got " . $this->export($this->actual)
            );
        }
    }

    public function toBeDefined(string $message = ''): void
    {
        if ($this->actual === null) {
            $this->throwFail(
                $message !== '' ? $message : "Expected value to be defined (not null)"
            );
        }
    }

    /**
     * Helper: throw a consistent exception so TestCase::run() can catch it.
     */
    private function throwFail(string $msg): void
    {
        throw new TestFailureException($msg);
    }

    /**
     * Pretty-print values in error output.
     */
    private function export(mixed $value): string
    {
        return var_export($value, true);
    }
}
