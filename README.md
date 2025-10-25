# 🧪 PHP-Testify

A modern, expressive testing library for PHP with a fluent and intuitive API, built on top of PHPUnit.

## 📦 Stats & Status

![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)
![Downloads](https://img.shields.io/packagist/dt/memran/php-testify?style=for-the-badge)
![Version](https://img.shields.io/packagist/v/memran/php-testify?style=for-the-badge)

**Supported PHP Versions:** 8.0, 8.1, 8.2, 8.3, 8.4

## Installation

```bash
composer require memran/php-testify --dev
```

## Configuration

Create `phpunit.config.php` in your project root:

```bash
<?php

return [
    'bootstrap' => __DIR__ . '/vendor/autoload.php',
    'test_patterns' => [
        __DIR__ . '/tests/*Test.php',     // PHPUnit-style classes
        __DIR__ . '/tests/*_test.php',    // describe/it style
    ],
];
```

# Quick Start

Create your first test file:

```bash
<?php
// tests/ExampleTest.php

describe('Array operations', function() {
    $array = [];

    beforeEach(function() use (&$array) {
        $array = [1, 2, 3];
    });

    test('array should have initial values', function() use (&$array) {
        expect($array)->toHaveLength(3);
        expect($array)->toContain(2);
        expect($array[0])->toBe(1);
    });

    it('should allow adding elements', function() use (&$array) {
        $array[] = 4;
        expect($array)->toHaveLength(4);
        expect($array)->toContain(4);
    });
});
```

Run your tests:

```bash
php tests/ExampleTest.php
```

## Core API

### Test Structure

Organize your tests using describe blocks:

```bash
describe('User authentication', function() {
    describe('Login functionality', function() {
        // Tests go here
    });

    describe('Password reset', function() {
        // Tests go here
    });
});
```

## Writing Tests

Use `test` or `it` to define individual test cases:

```bash
<?php
test('user can login with valid credentials', function() {
// Test implementation
});

it('should reject invalid passwords', function() {
// Test implementation
});
```

## Assertions

PHP-Testify provides a fluent assertion API:

```bash
expect($value)->toBe(5);
expect($value)->toBeTrue();
expect($value)->toBeFalse();
expect($value)->toBeNull();
expect($value)->toBeTruthy();
expect($value)->toBeFalsy();
expect($value)->toEqual(['key' => 'value']);
expect($value)->toBeGreaterThan(10);
expect($value)->toBeLessThan(20);
expect($string)->toContain('substring');
expect($array)->toContain('item');
expect($string)->toHaveLength(10);
expect($array)->toHaveLength(5);
expect($function)->toThrow(InvalidArgumentException::class);
expect($object)->toBeInstanceOf(User::class);
```

## Negative Assertions

Use `not()` for negative assertions:

```bash
expect($value)->not()->toBeNull();
expect($array)->not()->toContain('forbidden');
expect($string)->not()->toHaveLength(0);
```

## Lifecycle Hooks

Set up and tear down your test environment:

```bash
describe('Database tests', function() {
    beforeAll(function() {
        // Runs once before all tests in this block
        setupDatabase();
    });

    afterAll(function() {
        // Runs once after all tests in this block
        cleanupDatabase();
    });

    beforeEach(function() {
        // Runs before each test
        startTransaction();
    });

    afterEach(function() {
        // Runs after each test
        rollbackTransaction();
    });

    test('database operation', function() {
        // Test that uses database
    });
});
```

## Complete Example

```bash
<?php

use function Testify\describe;
use function Testify\it;
use function Testify\expect;
use function Testify\beforeAll;
use function Testify\afterAll;
use function Testify\beforeEach;
use function Testify\afterEach;

class DummyUser
{
    public string $name = 'Ada';
}

describe('php-testify expectation API', function () {
    // we'll mutate these in hooks to prove hooks work
    $shared = [
        'bootCount' => 0,
        'eachCount' => 0,
        'cleanup'   => [],
        'numbers'   => [],
    ];

    beforeAll(function () use (&$shared) {
        // runs once before all tests
        $shared['bootCount']++;
        $shared['numbers'] = [2, 4, 6];
    });

    afterAll(function () use (&$shared) {
        // runs once after all tests
        $shared['cleanup'][] = 'afterAll-called';
        // final assertion on lifecycle
        expect($shared['bootCount'])->toBe(1);
        expect($shared['cleanup'])->toContain('afterAll-called');
    });

    beforeEach(function () use (&$shared) {
        // runs before every it()
        $shared['eachCount']++;
        $shared['x'] = 10;
        $shared['y'] = 5;
        $shared['str'] = 'hello world';
        $shared['arr'] = ['alpha', 'beta', 'gamma'];
        $shared['user'] = new DummyUser();
        $shared['nullish'] = null;
    });

    afterEach(function () use (&$shared) {
        // runs after every it()
        // prove that something happened during test, then clean it
        if (isset($shared['dirty'])) {
            unset($shared['dirty']);
        }
    });

    it('toBe / toEqual basics', function () use (&$shared) {
        expect($shared['x'])->toBe(10);
        expect($shared['x'])->toEqual(10); // == is same in this case

        // Arrays: === would fail, but toEqual uses loose equality (==)
        $a = ['key' => 'value'];
        $b = ['key' => 'value'];
        expect($a)->toEqual($b);

        // sanity: strict equality vs loose (just to confirm not() also works)
        expect($a)->not()->toBe($b);
    });

    it('truthiness / falsiness / null', function () use (&$shared) {
        expect(true)->toBeTrue();
        expect(false)->toBeFalse();
        expect($shared['nullish'])->toBeNull();

        expect(1)->toBeTruthy();
        expect("nonempty")->toBeTruthy();
        expect(0)->toBeFalsy();
        expect("")->toBeFalsy();

        // negated versions
        expect($shared['nullish'])->not()->toBeTruthy();
        expect("")->not()->toBeTruthy();
        expect("x")->not()->toBeFalsy();
    });

    it('numeric comparisons', function () use (&$shared) {
        expect($shared['x'])->toBeGreaterThan($shared['y']); // 10 > 5
        expect($shared['y'])->toBeLessThan($shared['x']);   // 5 < 10

        // negated
        expect($shared['y'])->not()->toBeGreaterThan($shared['x']);
        expect($shared['x'])->not()->toBeLessThan($shared['y']);
    });

    it('containment and lengths', function () use (&$shared) {
        // strings
        expect($shared['str'])->toContain('hello');
        expect($shared['str'])->toContain('world');

        // arrays
        expect($shared['arr'])->toContain('alpha');
        expect($shared['arr'])->toContain('beta');

        // negated contain
        expect($shared['arr'])->not()->toContain('delta');
        expect($shared['str'])->not()->toContain('nope');

        // lengths
        expect($shared['str'])->toHaveLength(11); // "hello world" length 11
        expect($shared['arr'])->toHaveLength(3);

        // negated length
        expect($shared['arr'])->not()->toHaveLength(99);
        expect($shared['str'])->not()->toHaveLength(0);
    });

    it('instance and class checks', function () use (&$shared) {
        expect($shared['user'])->toBeInstanceOf(DummyUser::class);
        expect($shared['user'])->not()->toBeInstanceOf(\stdClass::class);
    });

    it('exception expectations with toThrow', function () {
        $willThrow = function () {
            throw new InvalidArgumentException("bad arg");
        };

        // should PASS
        expect($willThrow)->toThrow(InvalidArgumentException::class);

        // and negation should PASS because different exception
        $wontThrowThis = function () {
            throw new RuntimeException("other");
        };
        expect($wontThrowThis)->not()->toThrow(InvalidArgumentException::class);

        // also ensure something that throws anything matches default Throwable::class
        $anyThrow = function () {
            throw new \LogicException("xxx");
        };
        expect($anyThrow)->toThrow(\Throwable::class);
    });

    it('lifecycle hooks actually mutated shared state', function () use (&$shared) {
        // beforeAll ran once at entire suite start
        expect($shared['bootCount'])->toBe(1);

        // beforeEach increments eachCount for each test
        expect($shared['eachCount'])->toBeGreaterThan(0);

        // we can "dirty" something to prove afterEach cleans it next test
        $shared['dirty'] = 'temp-marker';

        // data from beforeAll should still exist
        expect($shared['numbers'])->toContain(2);
        expect($shared['numbers'])->toContain(6);
        expect($shared['numbers'])->not()->toContain(999);
    });

    it('afterEach cleaned previous dirty state', function () use (&$shared) {
        // If afterEach ran after last test, "dirty" should be gone now.
        // We simulate expectation with negated truthy.
        $isDirtyPresent = array_key_exists('dirty', $shared);
        expect($isDirtyPresent)->toBeFalse();
        expect($isDirtyPresent)->not()->toBeTrue();
    });
});

```

### Best Practices

- **Descriptive test names**: Use clear, descriptive names for describe blocks and tests
- **One assertion per test**: Focus each test on a single behavior
- **Use lifecycle hooks**: Set up test data in beforeEach rather than repeating code
- **Keep tests independent**: Tests should not depend on each other
- **Test edge cases**: Include tests for error conditions and boundary cases

### Available Commands

```bash
# Run all tests
composer test

```

# Contributing

Contributions are welcome! Please feel free to submit pull requests or open issues for bugs and feature requests.

# License

MIT License - see LICENSE file for details.
