<?php
// tests/BasicFunctionalityTest.php

require_once __DIR__ . '/../vendor/autoload.php';

use function Testify\{
    describe,
    test,
    it,
    beforeEach,
    expect,
    runTests
};

describe('Basic Functionality', function () {
    test('basic assertion', function () {
        expect(true)->toBeTrue();
        expect(false)->toBeFalse();
    });

    test('array operations', function () {
        $array = [1, 2, 3];
        expect($array)->toHaveLength(3);
        expect($array)->toContain(2);
    });

    test('string operations', function () {
        expect('hello')->toBe('hello');
        expect('world')->toHaveLength(5);
    });
});

// Note: runTests() is called in the runner file