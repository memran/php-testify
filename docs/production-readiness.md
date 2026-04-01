# Production Readiness Checklist

This file defines the minimum release bar for calling `php-testify` production ready.

For this project, "production ready" does not mean "a demo works" or "the current tests are green."
It means a real PHP team can adopt the fluent API in a PHPUnit-based project without regularly falling back to raw `TestCase` classes because essential testing workflows are missing or unreliable.

## Release Principle

`php-testify` should be:

- function-first for day-to-day developer experience
- compatible with PHPUnit as the underlying testing ecosystem
- predictable across Linux, macOS, and Windows
- explicit about what is supported, partially supported, and intentionally unsupported

## Production Release Gate

All sections below should be complete before tagging a production release.

### 1. Core Fluent Test Authoring

- `[x]` `describe()`, `it()`, `test()`, and `expect()` work for normal usage
- `[x]` `beforeAll()`, `afterAll()`, `beforeEach()`, and `afterEach()` work reliably
- `[ ]` Nested `describe()` blocks have defined and tested parent-child behavior
- `[ ]` Nested hooks inherit and execute predictably
- `[ ]` Fluent tests have stable internal metadata for labels and execution state
- `[ ]` Fluent tests support skip state
- `[ ]` Fluent tests support incomplete or todo state
- `[ ]` Fluent tests support datasets
- `[ ]` Fluent tests support groups or tags

### 2. Fluent Assertion Coverage

Minimum assertions expected for a production-ready fluent API:

- `[x]` strict equality
- `[x]` loose equality
- `[x]` truthy, falsy, true, false, null
- `[x]` greater than / less than
- `[x]` contains
- `[x]` length
- `[x]` throws exception by class
- `[x]` instance checks
- `[x]` negation with `not()`
- `[ ]` greater than or equal
- `[ ]` less than or equal
- `[ ]` empty / non-empty
- `[ ]` count assertions
- `[ ]` starts with / ends with
- `[ ]` regex matching
- `[ ]` array key assertions
- `[ ]` exception message assertions
- `[ ]` exception code assertions
- `[ ]` approximate float assertions

Optional for later minor releases:

- `[ ]` JSON assertions
- `[ ]` XML assertions
- `[ ]` file-system assertions
- `[ ]` output assertions

### 3. Fluent API Coverage For Common PHPUnit Workflows

The project does not need to clone every PHPUnit feature before release, but it does need coverage for the most common developer workflows.

Required:

- `[ ]` parameterized tests through a fluent dataset API
- `[ ]` test skipping through fluent syntax
- `[ ]` incomplete or todo semantics through fluent syntax
- `[ ]` suite or test grouping through fluent syntax
- `[ ]` CLI filtering compatible with fluent grouping or tagging

Can be deferred if clearly documented:

- `[ ]` dependency chaining equivalent to `#[Depends]`
- `[ ]` requirement guards for OS, PHP version, and extensions
- `[ ]` does-not-perform-assertions semantics
- `[ ]` TestDox-style labels

### 4. Runner And Suite Architecture

The current flat suite registry is enough for a prototype, but not enough for a strong release if datasets, tags, nested suites, and skip states are going to exist.

Production release requirement:

- `[ ]` Replace or evolve the current flat suite array structure into a model that supports per-test metadata
- `[ ]` Support parent-child suite relationships
- `[ ]` Support inherited hook composition
- `[ ]` Support dataset-expanded test cases
- `[ ]` Support test labels and stable identifiers
- `[ ]` Keep execution semantics deterministic
- `[ ]` Keep failure reporting understandable when hooks fail

### 5. CLI And Developer Experience

Required for a production release:

- `[x]` one-shot runner
- `[x]` `--filter`
- `[x]` `--watch`
- `[x]` `--verbose`
- `[x]` `--no-colors`
- `[ ]` group or tag filtering
- `[ ]` `--help`
- `[ ]` `--version`
- `[ ]` clearer filtering semantics than raw substring matching

Release-quality expectation:

- `[ ]` output is readable in local terminals
- `[ ]` output is stable in CI logs
- `[ ]` suite and test labels are clear enough to debug failures quickly

### 6. Cross-Platform Stability

Required:

- `[ ]` CI is green on Linux
- `[ ]` CI is green on Windows
- `[ ]` CI is green on all supported PHP versions
- `[ ]` line endings are normalized for tooling stability
- `[ ]` watcher behavior is covered by regression tests for Windows path handling

Recommended support target for first stable release:

- PHP 8.2
- PHP 8.3

### 7. Documentation Quality

Required:

- `[ ]` README clearly explains the fluent API-first product goal
- `[ ]` README clearly shows installation and first-test examples
- `[ ]` README documents supported fluent assertions
- `[ ]` README documents supported hooks and test-writing patterns
- `[ ]` README documents watch mode and filtering
- `[ ]` README documents which PHPUnit features are available only through class-based tests
- `[ ]` README does not overstate PHPUnit compatibility

Supporting docs:

- `[x]` compatibility checklist exists
- `[x]` roadmap exists
- `[ ]` release notes explain user-visible changes for each tag

### 8. Test Suite Quality

Required:

- `[ ]` each fluent feature has direct regression coverage
- `[ ]` nested suite behavior has direct regression coverage
- `[ ]` watch mode has regression coverage
- `[ ]` cross-platform path handling has regression coverage
- `[ ]` package-level integration test covers real CLI execution
- `[ ]` test names describe behavior, not implementation details

Recommended:

- `[ ]` add parity-oriented fixtures for major fluent features
- `[ ]` add explicit fixtures for unsupported or intentionally different behavior

### 9. Versioning And Release Hygiene

Required before tagging:

- `[ ]` working tree is clean
- `[ ]` changelog entry exists for the new version
- `[ ]` version scope matches the actual release
- `[ ]` docs and README reflect the current feature set
- `[ ]` no known red CI jobs remain open for the release commit

Versioning guidance:

- patch release: bug fixes only
- minor release: new fluent APIs, new assertions, new CLI features
- major release: breaking API or execution-semantic changes

## Recommended Minimum Scope For The Next Real Release

If the goal is a defendable stable release, the minimum feature set should be:

1. suite model redesign for metadata and nested suites
2. fluent skip and incomplete support
3. fluent datasets
4. fluent groups or tags plus CLI filtering
5. assertion expansion for common daily usage
6. explicit supported-vs-missing documentation in the README
7. green CI on the supported PHP matrix

## Release Decision Rule

Do not tag a release as production ready unless all required items in sections 1 through 9 are complete.

Until then, describe the project as:

- usable
- actively developing
- suitable for experimentation or early adoption

and not as fully production ready.
