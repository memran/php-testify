# PHP-Testify

PHP-Testify is a lightweight testing layer built on top of PHPUnit. It adds a fluent `expect()` API, `describe()` / `it()` style specs, a custom CLI runner, and watch mode while remaining easy to adopt in plain PHP projects.

## Requirements

- PHP 8.2 or newer
- Composer

## Installation

```bash
composer require --dev memran/php-testify
```

Create `phpunit.config.php` in your project root:

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

```php
<?php

use function Testify\describe;
use function Testify\expect;
use function Testify\it;

describe('array operations', function (): void {
    it('adds values', function (): void {
        $numbers = [1, 2, 3];

        expect($numbers)->toContain(2);
        expect($numbers)->toHaveLength(3);
    });
});
```

Run the suite with:

```bash
php bin/testify
```

Useful flags:

```bash
php bin/testify --filter array
php bin/testify --verbose
php bin/testify --watch
```

## Assertion API

Core assertions include:

```php
expect($value)->toBe('exact');
expect($value)->toEqual(['loose' => 'match']);
expect($value)->toBeTruthy();
expect($value)->not()->toBeNull();
expect($value)->toBeGreaterThan(10);
expect($array)->toContain('item');
expect($callable)->toThrow(RuntimeException::class);
```

Lifecycle hooks are available inside `describe()` blocks:

```php
beforeAll(fn () => $this->boot());
beforeEach(fn () => $this->resetState());
afterEach(fn () => $this->cleanup());
afterAll(fn () => $this->disconnect());
```

## Project Layout

- `src/` library runtime, runner, watcher, and assertions
- `bin/testify` CLI entrypoint
- `tests/` repository fixtures and PHPUnit unit tests
- `phpunit.config.php` sample config used by the package integration suite

## Development Workflow

Install dependencies and run the quality gates:

```bash
composer install
composer test
composer test:package
composer analyse
composer lint
composer fix
composer ci
```

- `composer test` runs the repository PHPUnit suite from `tests/Unit`
- `composer test:package` runs the package through its own CLI against fixture specs
- `composer analyse` runs PHPStan
- `composer lint` runs PHP-CS-Fixer in dry-run mode
- `composer fix` applies formatting fixes

## Static Analysis and CI

- PHPStan config: `phpstan.neon.dist`
- PHPUnit config: `phpunit.xml.dist`
- GitHub Actions workflow: `.github/workflows/ci.yml`

## Security and Production Notes

- Watch mode spawns the child runner without shell command interpolation.
- The runner validates config files and test pattern entries before loading them.
- Keep `phpunit.config.php` under source control and point `bootstrap` only to trusted files.

## Contributing

Repository-specific contributor guidance lives in [AGENTS.md](AGENTS.md). Keep changes small, add regression tests for behavior changes, and run `composer ci` before opening a pull request.
