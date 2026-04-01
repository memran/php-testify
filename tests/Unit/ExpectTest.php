<?php

declare(strict_types=1);

namespace Tests\Unit;

use ArrayIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Testify\Expect;
use Testify\TestFailureException;

final class ExpectTest extends TestCase
{
    public function testToContainSupportsTraversableValues(): void
    {
        $iterator = new ArrayIterator(['alpha', 'beta']);

        (new Expect($iterator))->toContain('beta');

        $this->addToAssertionCount(1);
    }

    public function testToHaveLengthRejectsUnsupportedValues(): void
    {
        $expect = new Expect(new \stdClass());

        $this->expectException(TestFailureException::class);
        $this->expectExceptionMessage('toHaveLength expects a string, array, or Countable value');

        $expect->toHaveLength(1);
    }

    public function testToThrowFailsWhenUnexpectedExceptionClassIsThrown(): void
    {
        $expect = new Expect(static function (): void {
            throw new \RuntimeException('boom');
        });

        $this->expectException(TestFailureException::class);
        $this->expectExceptionMessage('Expected exception InvalidArgumentException, got RuntimeException');

        $expect->toThrow(\InvalidArgumentException::class);
    }

    public function testExpandedMatchersCoverCommonDailyAssertions(): void
    {
        $payload = ['name' => 'Ada', 'age' => 12];

        (new Expect(10))->toBeGreaterThanOrEqual(10);
        (new Expect(10))->toBeLessThanOrEqual(10);
        (new Expect(3.14159))->toBeCloseTo(3.14, 0.01);
        (new Expect('php-testify'))->toStartWith('php');
        (new Expect('php-testify'))->toEndWith('fy');
        (new Expect('php-testify'))->toMatch('/test/i');
        (new Expect($payload))->toHaveKey('name');
        (new Expect($payload))->toHaveKeyWithValue('age', 12);
        (new Expect([]))->toBeEmpty();
        (new Expect(['a', 'b']))->toHaveCount(2);

        $this->addToAssertionCount(10);
    }

    public function testExceptionMessageAndCodeMatchersWork(): void
    {
        $expect = new Expect(static function (): void {
            throw new \RuntimeException('boom', 42);
        });

        $expect->toThrowWithMessage(\RuntimeException::class, 'boom');
        $expect->toThrowWithCode(\RuntimeException::class, 42);

        $this->addToAssertionCount(2);
    }

    #[DataProvider('invalidNumericAssertionProvider')]
    public function testNumericAssertionsRejectUnsupportedValues(string $method, mixed $value): void
    {
        $expect = new Expect($value);

        $this->expectException(TestFailureException::class);
        $this->expectExceptionMessage(sprintf('%s expects an integer or float', $method));

        $expect->{$method}(10);
    }

    /**
     * @return iterable<string, array{method: string, value: mixed}>
     */
    public static function invalidNumericAssertionProvider(): iterable
    {
        yield 'greater than with string' => [
            'method' => 'toBeGreaterThan',
            'value' => '10',
        ];

        yield 'less than with object' => [
            'method' => 'toBeLessThan',
            'value' => new \stdClass(),
        ];

        yield 'greater than or equal with string' => [
            'method' => 'toBeGreaterThanOrEqual',
            'value' => '10',
        ];

        yield 'less than or equal with object' => [
            'method' => 'toBeLessThanOrEqual',
            'value' => new \stdClass(),
        ];

        yield 'close to with string' => [
            'method' => 'toBeCloseTo',
            'value' => '10',
        ];
    }
}
