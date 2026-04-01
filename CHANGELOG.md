# Changelog

## Unreleased

- Redesigned the fluent suite registry to support nested suites, per-test metadata, and inherited hook execution
- Added fluent datasets with `->with([...])`
- Added fluent skip and incomplete controls through `->skip()`, `->incomplete()`, `todo()`, `skip()`, and `incomplete()`
- Added fluent groups/tags plus `--group` and `--exclude-group` CLI filtering
- Expanded `expect()` with common daily assertions for counts, emptiness, string prefixes/suffixes, regex, array keys, numeric bounds, approximate equality, and exception message/code checks
- Clarified supported vs missing behavior in the README and production-readiness docs

## v1.0.0 — 2025-10-25

- Initial stable release
- Full Vitest-style DSL: describe/it/expect
- Watch mode
- Hook system (beforeAll, afterAll, beforeEach, afterEach)
- Compact colored summary table + timing
- PHPUnit 11 compatible
