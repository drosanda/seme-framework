# Copilot instructions for Seme Framework

Full universal guidance is in [`AGENTS.md`](../AGENTS.md) at the repository root. Summary of what matters most:

## Project

Lightweight PHP MVC framework (PHP 8.2+; CI on 8.2, 8.3, 8.4, 8.5). Core classes are in `kero/sine/` with the `SENE_` prefix. Application code is in `app/`. Tests are in `tests/Unit/` (PHPUnit 11).

## When generating code

- Match the style of the surrounding file; do not introduce namespaces or new Composer runtime dependencies.
- Do not use syntax newer than PHP 8.2 in `kero/`.
- Do not touch `kero/lib/` (bundled third-party code).
- Never build SQL by concatenating user input. Use `esc()`, `quoteIdentifier()`, `assertColumn()`, `assertAlias()` in `SENE_MySQLi_Engine`. Do not re-enable raw SQL in `order_by`, `group_by`, `between`, or `where_in`.
- Never hardcode secrets, AES keys, or credentials.

## Tests

- Run: `vendor/bin/phpunit --configuration phpunit.xml --testsuite Unit`
- Use `declare(strict_types=1)`, PHPUnit attributes (`#[CoversClass]`, `#[DataProvider]`), and stubs rather than a real database. Tests must produce no output.
- Query-builder changes need a test asserting the full generated SQL, including hostile input.

## Commits and PRs

- Commit style: `FEAT: description`, `FIX: description`, `REVERT: ...`, `DROP: ...`.
- Branch off `master`; do not commit `vendor/`, `clover.xml`, `junit.xml`, or `seme.log`.
