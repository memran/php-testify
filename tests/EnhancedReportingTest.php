<?php
require_once __DIR__ . '/../vendor/autoload.php';

use function Testify\{describe, test, it, expect, runTests};

describe('Math Operations', function () {
    describe('Addition', function () {
        test('adds two numbers', function () {
            expect(1 + 1)->toBe(2);
        });

        test('adds negative numbers', function () {
            expect(-1 + -1)->toBe(-2);
        });
    });

    describe('Multiplication', function () {
        it('multiplies two numbers', function () {
            expect(2 * 3)->toBe(6);
        });

        it('handles zero multiplication', function () {
            expect(5 * 0)->toBe(0);
        });
    });
});

describe('String Operations', function () {
    test('string concatenation', function () {
        expect('hello' . ' ' . 'world')->toBe('hello world');
    });
});

runTests();
