# Fluent API Roadmap For PHPUnit Feature Coverage

This roadmap is for building `php-testify` as a fluent, function-first testing experience on top of PHPUnit.

The goal is not to push users back toward long `TestCase` classes. The goal is to let developers write shorter tests with:

- `describe()`
- `it()` / `test()`
- hooks like `beforeEach()`
- fluent assertions through `expect()`

while still covering more of PHPUnit's feature set underneath.

## Product Direction

Core principle:

- PHPUnit is the execution and compatibility foundation.
- The primary user experience is the fluent function API.
- Class-based PHPUnit tests remain a compatibility path, not the main design center.

That means roadmap priority should be:

1. expose PHPUnit features through fluent syntax
2. make fluent suite execution robust
3. improve fluent assertions
4. keep class-based support working as compatibility

## Milestone 1: Fluent API Product Baseline

Goal:

- Make the project direction explicit in the repo and prepare the codebase for fluent-feature expansion.

Scope:

- product positioning
- compatibility mapping
- architecture note
- fixture layout for fluent parity work

Task breakdown:

- `[ ]` Update docs to state that the fluent function API is the primary product
- `[ ]` Add a feature-mapping doc from PHPUnit concepts to fluent Testify APIs
- `[ ]` Create `tests/Parity` or `tests/Features` structure organized around fluent feature equivalence
- `[ ]` Record the architecture decision for how PHPUnit powers fluent tests under the hood
- `[ ]` Define a naming convention for future fluent APIs such as datasets, tags, skip controls, and labels

Exit criteria:

- The repo clearly states that function-first DX is the main goal.
- New feature work can be framed as "How does this look in fluent API form?"

## Milestone 2: Fluent Suite Model Redesign

Goal:

- Upgrade the suite registry from a simple list of hooks and closures into a model that can support real PHPUnit-like features through fluent syntax.

Scope:

- suite/test metadata
- nested suites
- labels and hierarchy
- per-test execution metadata

Dependencies:

- Milestone 1 complete

Task breakdown:

- `[ ]` Replace the current flat suite array model with structured suite/test records
- `[ ]` Add parent-child relationships for nested `describe()` blocks
- `[ ]` Support inherited hook composition for nested suites
- `[ ]` Add metadata fields for:
- skip state
- incomplete/todo state
- groups/tags
- datasets
- human-readable labels
- dependency links
- `[ ]` Ensure the runner can execute fluent tests from structured metadata instead of raw closures alone
- `[ ]` Add tests for nested suite registration and inherited hook behavior

Exit criteria:

- Fluent tests can carry metadata without another registry redesign.
- Nested suites behave intentionally and predictably.

## Milestone 3: Fluent Datasets

Goal:

- Expose PHPUnit-style data provider power through a short fluent API.

Scope:

- parameterized tests
- dataset naming
- dataset output labels

Dependencies:

- Milestone 2 complete

Task breakdown:

- `[ ]` Design a fluent dataset API
- Possible directions:
- `it('adds', fn ($a, $b, $sum) => ...)->with([...])`
- `dataset('numbers', [...])` plus reuse
- `test()->with([...])`
- `[ ]` Decide whether datasets should be inline, reusable, or both
- `[ ]` Add internal support for expanding one fluent test into many executable cases
- `[ ]` Add named dataset support in output
- `[ ]` Add parity fixtures that compare expected behavior with PHPUnit data-provider workflows
- `[ ]` Add CLI filtering behavior for dataset-expanded fluent cases

Exit criteria:

- Developers can write parameterized tests fluently without dropping to class-based PHPUnit syntax.

## Milestone 4: Fluent Skip, Todo, And State Controls

Goal:

- Give fluent tests first-class state controls that cover common PHPUnit outcomes.

Scope:

- skip
- incomplete
- todo/pending semantics
- conditional guards

Dependencies:

- Milestone 2 complete

Task breakdown:

- `[ ]` Design fluent skip APIs
- Possible directions:
- `it(...)->skip()`
- `skip('reason', fn () => ...)`
- conditional `skipIf(...)`
- `[ ]` Design fluent incomplete/todo APIs
- `[ ]` Store skip/incomplete metadata in the suite model
- `[ ]` Support runtime skip/incomplete signaling from inside fluent tests
- `[ ]` Ensure output and summary reflect these states cleanly
- `[ ]` Add regression tests for suite-level and test-level state controls

Exit criteria:

- Common non-pass states are available directly in fluent syntax.

## Milestone 5: Fluent Tags And Groups

Goal:

- Expose PHPUnit-style grouping through a simple fluent tagging system.

Scope:

- per-test tags/groups
- per-suite tags/groups
- CLI selection

Dependencies:

- Milestone 2 complete

Task breakdown:

- `[ ]` Design fluent tag/group APIs
- Possible directions:
- `it(...)->group('slow')`
- `describe(...)->group('integration')`
- `tag('api')`
- `[ ]` Add metadata storage for tags/groups
- `[ ]` Implement CLI filtering by include-group and exclude-group
- `[ ]` Support inheritance or merging for suite-level groups
- `[ ]` Show group-aware labels in verbose output if useful
- `[ ]` Add parity tests around selection behavior

Exit criteria:

- Developers can organize and select fluent tests without reverting to PHPUnit attributes.

## Milestone 6: Fluent Assertion Expansion

Goal:

- Expand `expect()` into a practical day-to-day replacement for the most common PHPUnit assertions.

Scope:

- matcher design
- assertion families
- error quality

Dependencies:

- Milestone 1 complete

Task breakdown:

- `[ ]` Reorganize `Expect` around matcher families instead of one-off methods
- `[ ]` Implement assertion families in this order:

- Batch A: core equality
- `toBe`, `toEqual`, null, booleans, identity, inequality helpers

- Batch B: numeric
- greater/less, greater-or-equal, less-or-equal, approximate equality

- Batch C: strings
- contains, startsWith, endsWith, regex

- Batch D: arrays and iterables
- contains, keys, counts, empty/non-empty, subset-like checks

- Batch E: exceptions and callables
- throw type, message, code

- Batch F: structured content
- JSON, XML, files, output

- `[ ]` Improve assertion failure messages to make fluent tests easy to debug
- `[ ]` Decide whether any matchers should delegate internally to PHPUnit constraints

Exit criteria:

- The fluent assertion API covers common testing workflows without forcing users back to `$this->assert*`.

## Milestone 7: Fluent Dependencies And Advanced Test Metadata

Goal:

- Add more advanced PHPUnit-style execution features only once the fluent suite model is ready.

Scope:

- dependencies
- labels
- no-assertion semantics
- requirement guards

Dependencies:

- Milestones 2 and 3 complete

Task breakdown:

- `[ ]` Design fluent dependency APIs
- Possible directions:
- `it(...)->dependsOn('other test')`
- named test references inside a suite
- `[ ]` Implement dependency ordering and result propagation rules
- `[ ]` Design fluent no-assertion semantics if needed
- `[ ]` Design fluent requirement guards for OS/PHP/extension constraints
- `[ ]` Add human-readable labels or TestDox-style case descriptions where useful
- `[ ]` Add parity fixtures for advanced metadata behaviors

Exit criteria:

- More advanced PHPUnit semantics are available without abandoning the fluent API style.

## Milestone 8: CLI, Output, And Config For Fluent Workflows

Goal:

- Align the CLI and config experience with how fluent tests will actually be written and organized.

Scope:

- filtering
- output
- watch mode
- config shape

Dependencies:

- Milestones 3, 4, and 5 complete

Task breakdown:

- `[ ]` Add exact suite/test path filtering for fluent cases
- `[ ]` Add group/tag filtering flags
- `[ ]` Add dataset-aware labels in output
- `[ ]` Add `--help` and `--version`
- `[ ]` Improve verbose rendering for nested suites
- `[ ]` Decide whether `phpunit.config.php` stays primary or whether `phpunit.xml` should be bridged in
- `[ ]` Add config options that support fluent workflows directly
- Examples:
- default groups
- output preferences
- suite conventions
- `[ ]` Keep watch mode aligned with fluent workflows and dataset-expanded runs

Exit criteria:

- The CLI feels designed for fluent tests, not just adapted from a lower-level runner.

## Milestone 9: Compatibility Layer For Class-Based PHPUnit Tests

Goal:

- Maintain class-based PHPUnit support as a secondary compatibility path while fluent APIs grow.

Scope:

- class execution support
- metadata compatibility
- migration safety

Dependencies:

- Runs in parallel with other milestones as needed

Task breakdown:

- `[ ]` Keep existing class-based test execution stable while refactoring the fluent model
- `[ ]` Add targeted parity fixtures for class-based metadata features used internally for compatibility
- `[ ]` Decide which PHPUnit features will be:
- fully exposed through fluent API
- supported only through class-based compatibility
- intentionally out of scope
- `[ ]` Document when users still need class-based PHPUnit syntax

Exit criteria:

- Users can mix styles safely, but the product direction remains fluent-first.

## Suggested Delivery Order

Recommended order:

1. Milestone 1: Fluent API Product Baseline
2. Milestone 2: Fluent Suite Model Redesign
3. Milestone 3: Fluent Datasets
4. Milestone 4: Fluent Skip, Todo, And State Controls
5. Milestone 5: Fluent Tags And Groups
6. Milestone 6: Fluent Assertion Expansion
7. Milestone 7: Fluent Dependencies And Advanced Test Metadata
8. Milestone 8: CLI, Output, And Config For Fluent Workflows
9. Milestone 9: Compatibility Layer For Class-Based PHPUnit Tests

## Suggested Issue Breakdown

Create issues in user-facing slices, not just internal refactors.

Good issue examples:

- `[ ]` Design fluent dataset API
- `[ ]` Implement dataset expansion in suite runner
- `[ ]` Add `skip()` and `skipIf()` for fluent tests
- `[ ]` Add `group()` metadata and CLI filtering
- `[ ]` Add string matcher family to `expect()`
- `[ ]` Add exception message assertions to `expect()`
- `[ ]` Redesign nested suite metadata model
- `[ ]` Document which PHPUnit features still require class-based tests

## Success Metrics

Use these measures to judge progress:

- Developers can express more PHPUnit features without writing `extends TestCase`.
- The fluent API remains short and readable as features expand.
- New fluent features always land with regression tests.
- The project becomes clearer about which features are fluent-first versus compatibility-only.
