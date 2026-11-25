# AGENTS.md

Universal guidance for AI coding agents working in this repository. Tool-specific files (`CLAUDE.md`, `.clinerules/`, `.github/copilot-instructions.md`) defer to this file.

## Project

Seme Framework: a small, lightweight PHP MVC framework (MIT). Intentionally "off road": manual, minimal, no code generators, no namespaces in app code. Package `seme/framework`, PSR-4 `seme\` -> `kero/`. Current version constant is in `index.php` (`SEME_VERSION`).

- Runtime: PHP 8.2+ (CI matrix: 8.2, 8.3, 8.4, 8.5; Docker images also exist for 7.4/8.3/8.4/8.5). Extensions: `mbstring`, `mysqli`, `gd`.
- Dev dependency: PHPUnit ^11.5.

## Layout

| Path | Purpose |
|------|---------|
| `index.php` | Entry point; defines constants such as `SEME_VERSION`, boots `SENE_Runner` |
| `kero/sine/` | Core classes: `SENE_Controller`, `SENE_Model`, `SENE_Engine`, `SENE_MySQLi_Engine`, `SENE_Input`, `SENE_Runner` |
| `kero/Functions.php` | Global helper functions and error handler |
| `kero/lib/` | Bundled third-party/legacy libs (FPDF, WideImage, email, purifier). Do not refactor |
| `app/config/` | Environment config (`development.php`; `staging.php.bak`) |
| `app/controller`, `app/model`, `app/view`, `app/core` | Application code (user-owned; mostly empty in the repo) |
| `tests/Unit/` | PHPUnit tests; `tests/Fixtures/` support files; `tests/bootstrap.php` defines constants and `$GLOBALS['SEMEDIR']` |
| `docker/`, `docker-compose.yml` | Per-PHP-version images and nginx config |
| `upgrades.md` | Upgrade notes between versions |

## Commands

```bash
composer install
vendor/bin/phpunit --configuration phpunit.xml --testsuite Unit   # what CI runs
vendor/bin/phpunit --filter SomeTest                              # single test
php -l path/to/file.php                                           # syntax check
```

Note: the `kero/bin/test.sh` script and README refer to a `Dev` testsuite that no longer exists in `phpunit.xml`; use `Unit`.

## Conventions

- Core classes use the `SENE_` prefix and live in `kero/sine/`; controllers extend `SENE_Controller`, models extend `SENE_Model`. Match the existing style of the file you edit (snake_case for query-builder methods like `where_in`, `group_by`; camelCase for newer helpers like `trimJoiner`, `quoteIdentifier`).
- Keep the framework lightweight: no new Composer runtime dependencies, no large abstractions.
- Maintain compatibility with every PHP version in the CI matrix. Do not use syntax newer than PHP 8.2 in `kero/`.
- Tests use PHPUnit attributes (`#[CoversClass]`, `#[DataProvider]`), `declare(strict_types=1)`, and stubs instead of a real database (see `StubMysqliResult` in `SENE_MySQLi_EngineTest.php`). Tests must not touch a MySQL server and must produce no output (`beStrictAboutOutputDuringTests`, `failOnRisky`, `failOnWarning` are on).

## Security-sensitive areas (be careful)

`SENE_MySQLi_Engine` builds SQL strings. Recent history shows raw-SQL allowances being added and reverted (`order_by`, `group_by`, `between`, `where_in`) as injection fixes.

- Never concatenate user input into SQL. Use `esc()`, `quoteIdentifier()`, `assertColumn()`, `assertAlias()`.
- Do not loosen identifier validation or re-enable raw SQL on a builder method without explicit instruction.
- Any change to query generation needs a unit test asserting the full generated SQL, including a hostile-input case.
- AES encryption on insert exists; do not log keys or plaintext values, and do not hardcode secrets. Config files under `app/config/` must not contain real credentials.

## Workflow

- Commit messages follow the existing history: `TYPE: lowercase description`, where TYPE is `FEAT`, `FIX`, `REVERT`, `DROP`, etc. Mention the version bump when applicable (e.g. `... on 4.0.17`).
- Work on a branch (not `master`); open PRs against `master`.
- Run the Unit suite before declaring work done. Report failures honestly.
- Do not commit generated or local files: `vendor/`, `docs/`, `clover.xml`, `junit.xml`, `.phpunit.result.cache`, `seme.log`.
- Do not edit `composer.lock` or bump `SEME_VERSION` unless asked.
