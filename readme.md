# Seme Framework

[![PHPUnit](https://github.com/drosanda/seme-framework/actions/workflows/phpunit.yml/badge.svg)](https://github.com/drosanda/seme-framework/actions/workflows/phpunit.yml) [![Coverage Status](https://codecov.io/gh/drosanda/seme-framework/branch/master/graphs/badge.svg?branch=master)](https://codecov.io/github/drosanda/seme-framework?branch=master) [![Website seme.framework.web.id](https://img.shields.io/website-up-down-green-red/http/seme.framework.web.id)](https://seme.framework.web.id/) [![Bugs](https://sonarcloud.io/api/project_badges/measure?project=drosanda_seme-framework&metric=bugs)](https://sonarcloud.io/dashboard?id=drosanda_seme-framework) [![Code Smells](https://sonarcloud.io/api/project_badges/measure?project=drosanda_seme-framework&metric=code_smells)](https://sonarcloud.io/dashboard?id=drosanda_seme-framework) [![Duplicated Lines (%)](https://sonarcloud.io/api/project_badges/measure?project=drosanda_seme-framework&metric=duplicated_lines_density)](https://sonarcloud.io/dashboard?id=drosanda_seme-framework) [![Lines of Code](https://sonarcloud.io/api/project_badges/measure?project=drosanda_seme-framework&metric=ncloc)](https://sonarcloud.io/dashboard?id=drosanda_seme-framework) [![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=drosanda_seme-framework&metric=sqale_rating)](https://sonarcloud.io/dashboard?id=drosanda_seme-framework) [![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=drosanda_seme-framework&metric=alert_status)](https://sonarcloud.io/dashboard?id=drosanda_seme-framework)

[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=drosanda_seme-framework&metric=reliability_rating)](https://sonarcloud.io/dashboard?id=drosanda_seme-framework) [![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=drosanda_seme-framework&metric=security_rating)](https://sonarcloud.io/dashboard?id=drosanda_seme-framework) [![Technical Debt](https://sonarcloud.io/api/project_badges/measure?project=drosanda_seme-framework&metric=sqale_index)](https://sonarcloud.io/dashboard?id=drosanda_seme-framework) [![Vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=drosanda_seme-framework&metric=vulnerabilities)](https://sonarcloud.io/dashboard?id=drosanda_seme-framework) ![GitHub last commit](https://img.shields.io/github/last-commit/drosanda/apptanya)


Seme Framework PHP MVC Framework for creating small and medium app that needed for fast delivery. At first version of Seme Framework used for building API (Middle Ware) for another Application such as android, iOS, etc. And now as increasing of requirement, Seme Framework has expand the limit for creating Small and Medium App.

## Documentation

Read the official documentation at [seme.framework.web.id](https://seme.framework.web.id). Now only available in English.

## Requirements

- PHP 8.2 or newer (CI runs on 8.2, 8.3, 8.4, and 8.5)
- PHP extensions: `mbstring`, `mysqli`, `gd`
- [Composer](https://getcomposer.org/) (only needed for development and running the tests)

> **Security note:** PHP releases before 8.2.35, 8.3.36, 8.4.27, and 8.5.12 are reported to be affected by critical remote-code-execution flaws (see [issue #24](https://github.com/drosanda/seme-framework/issues/24)). Always run on a patched, supported PHP release. PHP 7.4 and 8.1 are end-of-life, so the 7.4 Docker image is kept for legacy use only and should not be exposed to the internet.

## Version

Latest version of Seme Framework is 4.0.3. See the [release tags](https://github.com/drosanda/seme-framework/tags) and the [commit history](https://github.com/drosanda/seme-framework/commits/master) for what changed, and [upgrades.md](upgrades.md) for upgrade notes.

## Key Feature

This framework suitable for Programmer that only know about Manual way of code. Not like this time, everything build up automatically through console. Seme Framework has taken different way of code, If you want build small app, why we need lots of library. Here is the key feature of Seme Framework:

- Small but not Tiny. Seme Framework growth on SME Industries with many requirement but can suppress the hosting price.
- Not Too serious. You can played with Seme Framework at your own logic. Its free and almost has no restriction to developing your solution.
- Admin Friendly. Seme Framework has re-routing feature for admin page, without refactoring the MVC.
- Theme Engine. Seme Framework has feature who can PUSH the content inside the layout view. Also support theming engine, if you want change the Style, you just clone the original one and modified safely.

## Testing

Install the dev dependencies, then run the unit test suite from the framework's working directory:

```bash
composer install
vendor/bin/phpunit --configuration phpunit.xml --testsuite Unit
```

Tests use stubs and never connect to a real database. The same command runs on GitHub Actions for every pull request.

## Docker

Ready-made PHP-FPM images and an nginx config are provided in `docker/` (PHP 8.2 to 8.5, plus a legacy 7.4). The default `docker-compose.yml` uses PHP 8.5:

```bash
docker network create seme_webstack_network   # first time only
docker compose up --build
```

Then open <http://localhost:8080>. The images pin an exact PHP patch release (for example `php:8.5.12-fpm-trixie`), so bump the tag in each `docker/<version>/Dockerfile` and rebuild with `--pull` whenever PHP publishes a security release.

## Contributing and AI Agents

Please read [CONTRIBUTING.md](CONTRIBUTING.md) first. Guidance for AI coding assistants is in [AGENTS.md](AGENTS.md), which Claude Code, Cline, and GitHub Copilot also follow through their own instruction files.

## Spread Seme Framework
If you pleased, you can add our badge into your readme.md:
```
[![With](https://img.shields.io/badge/With-SemeFramework4-fb898f?style=flat)](https://seme.framework.web.id/)

```

## License

Seme Framework licensed under MIT License 2.0
