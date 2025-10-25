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
    'colors' => 'always',
    'verbose' => true,
    'stopOnFailure' => false,
    'extensions' => [
        'loadedExtensions' => [],
        'notLoadedExtensions' => []
    ],
    'testSuite' => [
        'name' => 'Testify Test Suite',
        'directories' => ['tests']
    ]
];
```

# Quick Start

Create your first test file:

```bash
<?php
// tests/ExampleTest.php

require_once __DIR__ . '/../vendor/autoload.php';

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
php
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
// tests/UserServiceTest.php

require_once __DIR__ . '/../vendor/autoload.php';

describe('UserService', function() {
    $userService = null;
    $testUser = null;

    beforeAll(function() use (&$userService) {
        $userService = new UserService();
    });

    beforeEach(function() use (&$testUser, &$userService) {
        $testUser = $userService->createUser('test@example.com', 'password123');
    });

    afterEach(function() use (&$testUser, &$userService) {
        if ($testUser) {
            $userService->deleteUser($testUser->id);
        }
    });

    describe('user creation', function() use (&$userService) {
        test('creates user with valid data', function() use (&$userService) {
            $user = $userService->createUser('new@example.com', 'securepass');

            expect($user)->toBeInstanceOf(User::class);
            expect($user->email)->toBe('new@example.com');
            expect($user->isActive)->toBeTrue();
        });

        test('rejects duplicate email', function() use (&$userService) {
            expect(function() use (&$userService) {
                $userService->createUser('test@example.com', 'password');
            })->toThrow(DuplicateEmailException::class);
        });
    });

    describe('user authentication', function() use (&$userService) {
        test('authenticates with valid credentials', function() use (&$userService) {
            $result = $userService->authenticate('test@example.com', 'password123');

            expect($result)->toBeTrue();
        });

        test('rejects invalid password', function() use (&$userService) {
            $result = $userService->authenticate('test@example.com', 'wrongpass');

            expect($result)->toBeFalse();
        });

        test('rejects non-existent user', function() use (&$userService) {
            $result = $userService->authenticate('nonexistent@example.com', 'password');

            expect($result)->toBeFalse();
        });
    });
});

// Run tests
runTests();
```

# Running Tests

## Single Test File

```bash
php tests/YourTestFile.php
```

## Multiple Test Files

Create a test runner:

```bash
<?php
// test-runner.php

require_once __DIR__ . '/vendor/autoload.php';

// Include all test files
foreach (glob(__DIR__ . '/tests/*.php') as $testFile) {
    require_once $testFile;
}

runTests();
```

Then run:

```bash
php test-runner.php
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

# Show current configuration
composer test:config

# Run tests without coverage (faster)
composer test:quick

# Generate coverage report
composer test:coverage

# Watch for changes and run tests automatically
composer test:watch

# Run specific test types
composer test:unit
composer test:feature
```

# Contributing

Contributions are welcome! Please feel free to submit pull requests or open issues for bugs and feature requests.

# License

MIT License - see LICENSE file for details.
