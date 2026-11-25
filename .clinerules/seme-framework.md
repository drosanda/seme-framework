# Seme Framework: Cline rules

The universal project guidance lives in `AGENTS.md` at the repository root. Read it first; it covers layout, commands, conventions, and security rules.

## Cline specifics

- Run commands from the repository root. Use `vendor/bin/phpunit --configuration phpunit.xml --testsuite Unit` to verify changes (run `composer install` first if `vendor/` is missing).
- Prefer small, targeted edits over rewriting files. `kero/sine/SENE_MySQLi_Engine.php` is over 2,000 lines; read only the relevant range.
- Treat SQL generation in `SENE_MySQLi_Engine` as security-sensitive: never re-enable raw SQL or loosen identifier checks without explicit instruction, and add a unit test with hostile input for any change.
- Do not modify `kero/lib/` (bundled third-party libraries), `composer.lock`, or `SEME_VERSION` unless asked.
- Ask before running anything that touches a database, Docker, or the network. Tests must use stubs, not a live MySQL server.
