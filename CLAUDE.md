# CLAUDE.md

@AGENTS.md

## Claude Code specifics

- `AGENTS.md` above is the source of truth for project layout, commands, conventions, and security rules. Keep this file for Claude-only notes.
- Before changing anything in `kero/sine/SENE_MySQLi_Engine.php` (2,000+ lines), read the surrounding methods with offset/limit rather than the whole file.
- After editing PHP, run `php -l <file>` and the relevant PHPUnit test (`vendor/bin/phpunit --filter <Name>`), then the full `Unit` suite before finishing.
- `.claude/settings.local.json` is personal and machine-local; do not commit it.
- Only commit or push when asked.
