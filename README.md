# PHP-Testify

[![CI](https://img.shields.io/github/actions/workflow/status/memran/php-testify/ci.yml?branch=main&label=CI)](https://github.com/memran/php-testify/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/memran/php-testify)](https://packagist.org/packages/memran/php-testify)
[![Downloads](https://img.shields.io/packagist/dt/memran/php-testify)](https://packagist.org/packages/memran/php-testify)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/packagist/l/memran/php-testify)](LICENSE)

PHP-Testify brings an expressive `expect()` API, `describe()` / `it()` specs, a readable CLI runner, and watch mode to standard PHPUnit-based projects. It stays small, framework-agnostic, and compatible with plain Composer workflows.

## Features

- Fluent assertions such as `toBe()`, `toEqual()`, `toContain()`, `toThrow()`, and negation with `not()`
- Two test styles in the same project: PHPUnit classes and Jest/Vitest-like specs
- Built-in lifecycle hooks: `beforeAll`, `afterAll`, `beforeEach`, `afterEach`
- Filtered runs, verbose output, and watch mode from the `bin/testify` CLI
- Static analysis, formatting, and CI setup ready for package contributors

## Requirements

- PHP 8.2 or newer
- Composer

## Installation

```bash
composer require --dev memran/php-testify
```

Create a `phpunit.config.php` file in your project root:

```php
<?php

return [
    'bootstrap' => __DIR__ . '/vendor/autoload.php',
    'test_patterns' => [
        __DIR__ . '/tests/*Test.php',
        __DIR__ . '/tests/*_test.php',
    ],
];
```

## Quick Start

Write your first spec:

```php
<?php

declare(strict_types=1);

use function Testify\describe;
use function Testify\expect;
use function Testify\it;

describe('cart totals', function (): void {
    it('adds line items', function (): void {
        $items = [12, 8, 5];

        expect(array_sum($items))->toBe(25);
        expect($items)->toHaveLength(3);
    });
});
```

Run it:

```bash
php bin/testify
```

## Tutorials

### 1. Write a spec with hooks

```php
<?php

declare(strict_types=1);

use function Testify\beforeEach;
use function Testify\describe;
use function Testify\expect;
use function Testify\it;

describe('account state', function (): void {
    $balance = 0;

    beforeEach(function () use (&$balance): void {
        $balance = 100;
    });

    it('withdraws funds', function () use (&$balance): void {
        $balance -= 40;

        expect($balance)->toBe(60);
        expect($balance)->not()->toBe(100);
    });
});
```

### 2. Mix PHPUnit and Testify in one project

`tests/InvoiceCalculatorTest.php`

```php
<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InvoiceCalculatorTest extends TestCase
{
    public function testRoundsTaxAmount(): void
    {
        self::assertSame(13, (int) round(12.6));
    }
}
```

`tests/invoice_expectations_test.php`

```php
<?php

declare(strict_types=1);

use function Testify\describe;
use function Testify\expect;
use function Testify\it;

describe('invoice presentation', function (): void {
    it('contains a currency symbol', function (): void {
        expect('$12.50')->toContain('$');
    });
});
```

### 3. Run focused feedback loops

```bash
php bin/testify --filter invoice
php bin/testify --verbose
php bin/testify --watch
```

Use `--filter` to run only matching PHPUnit methods or spec names. Use `--watch` during local development to re-run the suite after file changes without shell interpolation.

## Assertion Examples

```php
expect($value)->toBe('exact');
expect($value)->toEqual(['loose' => 'match']);
expect($value)->toBeTruthy();
expect($value)->toBeGreaterThan(10);
expect($array)->toContain('item');
expect($callable)->toThrow(RuntimeException::class);
expect($object)->toBeInstanceOf(User::class);
expect($object)->not()->toBeSameObject($otherObject);
```

## Project Layout

- `src/` runtime classes, assertions, suite registry, runner, and watch support
- `bin/testify` CLI entrypoint
- `tests/` package fixtures plus PHPUnit coverage for internal behavior
- `phpunit.config.php` fixture config used by the package integration suite

## Development

```bash
composer install
composer test
composer test:package
composer analyse
composer lint
composer fix
composer ci
```

- `composer test` runs the internal PHPUnit suite from `tests/Unit`
- `composer test:package` runs the package through its own CLI against fixture specs
- `composer analyse` runs PHPStan
- `composer lint` runs PHP-CS-Fixer in dry-run mode
- `composer fix` applies formatting fixes
- `composer ci` runs the full local quality gate

## Tooling Status

- PHPStan 2.x configuration: [phpstan.neon.dist](phpstan.neon.dist)
- PHPUnit configuration: [phpunit.xml.dist](phpunit.xml.dist)
- GitHub Actions workflow: [.github/workflows/ci.yml](.github/workflows/ci.yml)
- Contributor guide: [AGENTS.md](AGENTS.md)

## Security and Production Notes

- Watch mode spawns child processes with argument arrays, not shell-built command strings.
- The runner validates `bootstrap` and `test_patterns` before loading files.
- Keep `phpunit.config.php` under version control and point it only at trusted bootstrap files.

## Contributing

Run `composer ci` before opening a pull request. Keep changes focused, add regression tests for behavior changes, and document any CLI or configuration changes in this README.
