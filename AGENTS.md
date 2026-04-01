# Repository Guidelines

## Project Structure & Module Organization
`src/` contains the library runtime: `Runner.php`, `Watcher.php`, and printer/assertion classes that power the CLI and expectation API. `bin/testify` is the executable entrypoint used by Composer scripts. `tests/` includes both supported test styles: PHPUnit class-based files such as `SampleTest.php` and function-style specs such as `ExpectApi_test.php`. Root-level metadata and defaults live in `composer.json`, `phpunit.config.php`, `README.md`, and `CHANGELOG.md`.

## Build, Test, and Development Commands
Install dependencies with `composer install`. Run the repository test suite with `composer test` or `php bin/testify`; both load `phpunit.config.php` from the project root. Use `composer test -- --watch` for watch mode, or `php bin/testify --filter ExpectApi` to run a subset. If you need to verify Composer metadata only, `composer validate` is the safest quick check.

## Coding Style & Naming Conventions
Target PHP 8.0+ and keep code PSR-12 aligned: 4-space indentation, braces on the next line for classes and methods, and one class per file under the `Testify\\` namespace. Use `StudlyCase` for classes (`TestSuite`), `camelCase` for methods (`watchLoop`), and snake_case only where the repository already uses it for spec filenames (`example_expect_test.php`). Prefer small final classes and explicit return types when adding runtime code.

## Testing Guidelines
This project supports two patterns: PHPUnit classes named `*Test.php` and describe/it specs named `*_test.php`, as configured in `phpunit.config.php`. Add focused assertions for new behavior and keep test names descriptive, for example `test_adds_numbers` or `it('tracks hook cleanup', ...)`. Run `composer test` before opening a PR; exercise `--watch` when changing runner or watcher logic.

## Commit & Pull Request Guidelines
Recent history uses short, imperative commit subjects such as `Added toBeSameObject()` and `Update Readme`. Keep commits concise, scoped, and written in the present tense. Pull requests should include a short problem statement, the behavior change, and the exact test command used. Link related issues when applicable and include terminal output or screenshots only when changing CLI output.

## Configuration Tips
`bin/testify` expects a `phpunit.config.php` file in the current working directory and Composer autoloading to be available. When changing bootstrapping behavior, verify both local development mode (`../vendor/autoload.php`) and dependency-consumer mode (`../../../autoload.php`).
