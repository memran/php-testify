<?php

declare(strict_types=1);

use function Testify\{describe, expect, it};

describe('Expectation API', function () {
    it('handles truthiness', function () {
        expect(true)->toBeTrue();
        expect(false)->toBeFalsy();
        expect(null)->not()->toBeTruthy();
    });

    it('handles numbers', function () {
        expect(10)->toBeGreaterThan(5);
        expect(5)->toBeLessThan(10);
    });

    it('checks arrays and strings', function () {
        expect(['a', 'b'])->toContain('a');
        expect('hello world')->toContain('world');
        expect('abc')->toHaveLength(3);
    });

    it('checks thrown exceptions', function () {
        $fn = fn () => throw new InvalidArgumentException("fail");
        expect($fn)->toThrow(InvalidArgumentException::class);
    });

    it('supports negation', function () {
        expect('')->not()->toBeTruthy();
        expect(['x'])->not()->toContain('y');
    });
});
