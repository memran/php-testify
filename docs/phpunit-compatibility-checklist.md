# PHPUnit Feature Coverage Through Fluent API

This file tracks how much of PHPUnit's feature surface is currently exposed through `php-testify`'s fluent, function-first API.

Primary product direction:

- PHPUnit remains the compatibility foundation.
- `php-testify` should give developers a shorter, more fluent alternative to verbose class-based test writing.
- Class-based PHPUnit tests remain supported, but they are not the main developer experience target.

Status labels:

- `[x]` Supported
- `[-]` Partial support
- `[ ]` Missing

Notes:

- "Supported" means the feature is available today through the fluent API or through the current mixed runtime in a way the project can reasonably claim.
- "Partial support" means some behavior exists, but not yet in a complete, fluent, PHPUnit-aligned way.
- This checklist is based on the current code in `src/`, `bin/testify`, `README.md`, and the existing test suite.

## 1. Core Fluent Test Authoring

- `[x]` `describe()`
- `[x]` `it()`
- `[x]` `test()`
- `[x]` `expect()`
- `[x]` `beforeAll()`
- `[x]` `afterAll()`
- `[x]` `beforeEach()`
- `[x]` `afterEach()`
- `[x]` Prevents hooks from being declared outside `describe()`
- `[-]` Nested `describe()` semantics
Current behavior: nested suites register and run, but there is no full hierarchical suite model with inherited hooks and metadata.
- `[ ]` Per-test metadata in the fluent API
- `[ ]` Per-suite metadata in the fluent API
- `[ ]` Skip controls in the fluent API
- `[ ]` Incomplete/todo controls in the fluent API
- `[ ]` Group/tag controls in the fluent API
- `[ ]` Dataset/parameterization controls in the fluent API

## 2. Fluent Assertion API

- `[x]` `toBe()`
- `[x]` `toEqual()`
- `[x]` `toBeTrue()`
- `[x]` `toBeFalse()`
- `[x]` `toBeNull()`
- `[x]` `toBeTruthy()`
- `[x]` `toBeFalsy()`
- `[x]` `toBeGreaterThan()`
- `[x]` `toBeLessThan()`
- `[x]` `toContain()` for strings
- `[x]` `toContain()` for arrays
- `[x]` `toContain()` for `Traversable`
- `[x]` `toHaveLength()` for strings
- `[x]` `toHaveLength()` for arrays and `Countable`
- `[x]` `toThrow()`
- `[x]` `toBeSameObject()`
- `[x]` `toBeInstanceOf()`
- `[x]` Negation with `not()`
- `[-]` Cohesive matcher architecture
Current behavior: assertions work, but the API is still a growing list of methods rather than a well-organized matcher system.
- `[ ]` Greater-than-or-equal / less-than-or-equal assertions
- `[ ]` Empty/non-empty assertions
- `[ ]` Starts-with / ends-with assertions
- `[ ]` Regex assertions
- `[ ]` Array key/value assertions
- `[ ]` Count assertions beyond length
- `[ ]` Exception message assertions
- `[ ]` Exception code assertions
- `[ ]` Float tolerance assertions
- `[ ]` JSON assertions
- `[ ]` XML assertions
- `[ ]` File-system assertions
- `[ ]` Output assertions

## 3. PHPUnit Features Exposed Through Fluent Syntax

This section is the main product goal: can PHPUnit capabilities be used through fluent Testify APIs instead of requiring class-based `TestCase` usage?

- `[ ]` Fluent datasets equivalent to PHPUnit data providers
- `[ ]` Named datasets in fluent tests
- `[ ]` Fluent dependency chaining equivalent to `#[Depends]`
- `[ ]` Fluent group/tag registration equivalent to `#[Group]`
- `[ ]` Fluent skip API
- `[ ]` Fluent incomplete/todo API
- `[ ]` Fluent does-not-perform-assertions semantics
- `[ ]` Fluent requirement guards for OS/PHP/extension constraints
- `[ ]` Fluent TestDox-style labeling or human-readable case labels

## 4. Fluent Suite Execution Semantics

- `[x]` Runs fluent suites registered through `describe()`
- `[x]` Runs fluent hooks in basic suite order
- `[x]` Avoids retrying `afterEach()` after it throws
- `[-]` Nested hook inheritance
Current behavior: hooks are stored on the active suite only.
- `[ ]` Parent-child suite composition
- `[ ]` Shared metadata propagation through nested suites
- `[ ]` Dataset-expanded fluent test execution
- `[ ]` Dependency-aware fluent test execution
- `[ ]` Group-aware fluent filtering and execution
- `[ ]` Per-test skip/incomplete state in the suite model

## 5. Mixed Runtime Compatibility

This section tracks compatibility support rather than the primary UX.

- `[x]` Runs PHPUnit `TestCase` subclasses
- `[x]` Detects `test*` public methods
- `[x]` Detects PHPUnit methods marked with `#[Test]`
- `[x]` Supports `setUpBeforeClass()`
- `[x]` Supports `tearDownAfterClass()`
- `[x]` Supports `setUp()`
- `[x]` Supports `tearDown()`
- `[x]` Allows PHPUnit-native assertions in class-based tests
- `[-]` Handles lifecycle failures cleanly
Current behavior: lifecycle failures are reported, but full PHPUnit-equivalent behavior is not guaranteed.
- `[ ]` Executes `#[DataProvider]`
- `[ ]` Executes `#[Depends]`
- `[ ]` Executes `#[Group]`
- `[ ]` Executes `#[DoesNotPerformAssertions]`
- `[ ]` Executes PHPUnit requirement attributes in package runtime

## 6. Result Classification And Output

- `[x]` Reports pass/fail outcomes
- `[x]` Maps skipped PHPUnit tests to `SKIP`
- `[x]` Maps incomplete PHPUnit tests to `INCOMPLETE`
- `[x]` Maps risky/warning-style PHPUnit throwables to `WARN`
- `[x]` Returns non-zero exit code when failures occur
- `[x]` Prints a summary table
- `[x]` Shows suite timing and slowest suites
- `[x]` Supports color on/off output
- `[x]` Supports verbose labels
- `[-]` Fluent-test-aware labeling
Current behavior: labels are basic test names or suite-qualified names in verbose mode.
- `[ ]` Dataset labels in output
- `[ ]` Group/tag labels in output
- `[ ]` TestDox-style output for fluent tests
- `[ ]` Structured machine-readable output
- `[ ]` Rich diff output for assertion failures

## 7. CLI And Developer Experience

- `[x]` One-shot CLI execution through `bin/testify`
- `[x]` `--filter`
- `[x]` `--verbose`
- `[x]` `--no-colors`
- `[x]` `--watch`
- `[x]` Watch mode respawns the child runner without shell string interpolation
- `[x]` Watch mode tracks `src/` and `tests/` PHP files plus config/bootstrap files
- `[-]` Filtering semantics suitable for fluent test usage
Current behavior: filtering is substring-based only.
- `[ ]` Filter by fluent group/tag
- `[ ]` Filter by exact suite/test path
- `[ ]` Stop-on-failure controls
- `[ ]` `--help`
- `[ ]` `--version`
- `[ ]` Output modes for CI and machine consumers

## 8. Configuration

- `[x]` Requires `phpunit.config.php` in the current working directory
- `[x]` Supports `bootstrap`
- `[x]` Supports `test_patterns`
- `[x]` Supports default color config through `phpunit.config.php`
- `[-]` Configuration designed for fluent Testify projects
Current behavior: config is minimal and runtime-focused.
- `[ ]` Group or tag definitions in config
- `[ ]` Suite-level defaults in config
- `[ ]` Dataset/config helpers for fluent workflows
- `[ ]` Bridge strategy for `phpunit.xml` / `phpunit.xml.dist`

## 9. Mocks, Stubs, And Test Doubles

- `[-]` PHPUnit mocks may work in class-based tests
Current behavior: this is compatibility by underlying PHPUnit usage, not a fluent Testify API.
- `[ ]` Fluent mock or stub helpers
- `[ ]` Documented strategy for using PHPUnit doubles from fluent tests
- `[ ]` Verified parity tests for doubles used through Testify workflows

## 10. Documentation And Product Positioning

- `[x]` Documents `describe()` / `it()` / `expect()` usage
- `[x]` Documents mixed PHPUnit/spec workflows
- `[-]` Documents the fluent-API-first product goal explicitly
Current behavior: the README communicates DX goals, but not yet as a formal product principle.
- `[ ]` Document PHPUnit features as fluent Testify equivalents
- `[ ]` Document which features still require class-based PHPUnit tests
- `[ ]` Document intentional differences from raw PHPUnit UX

## Immediate Priority Backlog

- `[ ]` Add fluent datasets API that maps cleanly to PHPUnit-style data providers
- `[ ]` Add fluent skip/incomplete API
- `[ ]` Add fluent groups/tags API plus CLI filtering support
- `[ ]` Redesign suite metadata so fluent tests can carry datasets, tags, skip state, and labels
- `[ ]` Expand `expect()` by assertion families most commonly used in day-to-day tests
- `[ ]` Define how PHPUnit compatibility should be exposed through fluent syntax before adding more class-style parity work
