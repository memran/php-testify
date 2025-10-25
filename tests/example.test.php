<?php
// tests/example.test.php

require_once __DIR__ . '/../vendor/autoload.php';

use function Testify\{
    describe,
    test,
    it,
    beforeEach,
    afterEach,
    beforeAll,
    afterAll,
    expect,
    runTests
};

describe('Array operations', function () {
    $array = [];

    beforeAll(function () {
        echo "Running before all tests in this describe block\n";
    });

    beforeEach(function () use (&$array) {
        $array = [1, 2, 3];
    });

    afterEach(function () use (&$array) {
        $array = [];
    });

    test('array should have initial values', function () use (&$array) {
        expect($array)->toHaveLength(3);
        expect($array)->toContain(2);
        expect($array[0])->toBe(1);
    });

    it('should allow adding elements', function () use (&$array) {
        $array[] = 4;
        expect($array)->toHaveLength(4);
        expect($array)->toContain(4);
    });

    describe('nested describe block', function () {
        test('nested test', function () {
            expect(true)->toBeTrue();
        });
    });
});

describe('String operations', function () {
    test('string length', function () {
        expect('hello')->toHaveLength(5);
        expect('hello')->toContain('ell');
    });

    test('string comparison', function () {
        expect('hello')->toBe('hello');
        expect('hello')->not()->toBe('world');
    });
});

describe('Math operations', function () {
    test('number comparisons', function () {
        expect(5)->toBeGreaterThan(3);
        expect(5)->toBeLessThan(10);
        expect(5)->toEqual(5);
    });
});

describe('Exception testing', function () {
    test('should throw exception', function () {
        expect(function () {
            throw new \InvalidArgumentException('Test exception');
        })->toThrow(\InvalidArgumentException::class);
    });
});

// Run all tests
runTests();
