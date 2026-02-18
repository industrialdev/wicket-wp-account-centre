# Repository Guidelines

## Project Structure & Module Organization
This repository is a WordPress plugin (`wicket-wp-account-centre`). Core PHP code lives in `src/` (PSR-4 namespace `WicketAcc\\`), with legacy/compatibility code in `includes/`. Frontend assets are in `assets/` (`css/`, `js/`, `images/`). Template files are under `templates-wicket/`. Tests are split into `tests/unit/` and `tests/Browser/`. Documentation is in `docs/`, and automation/scripts live in `.ci/`.

## Build, Test, and Development Commands
Use Composer scripts from the plugin root:
- `composer install`: install PHP dependencies and generate autoload.
- `composer lint`: dry-run PHP-CS-Fixer checks.
- `composer format` or `composer cs:fix`: auto-fix code style.
- `composer test`: run unit suite (`pest --testsuite unit`).
- `composer test:unit`: run only unit tests.
- `composer test:browser`: run browser tests (requires local WP + `.env`).
- `composer test:coverage`: generate HTML coverage in `coverage/`.
- `composer check`: run lint + tests.
- `composer production`: production build (`--no-dev`, optimized autoload).

## Coding Style & Naming Conventions
Target PHP 8.2+, strict typing, and PSR-12 style. Follow `.editorconfig`: 4 spaces for PHP, 2 spaces for CSS/YAML, LF line endings. Prefer early returns and small focused methods. Use verb-based method names (`getUserData`) and noun-based variables (`userData`). Keep WordPress code idiomatic: sanitize/validate input, escape output, check capabilities, and use nonces for state-changing actions.

## Testing Guidelines
Frameworks: Pest + PHPUnit + Brain Monkey. Place unit tests in `tests/unit/` with `*Test.php` suffix; extend `AbstractTestCase` for WP mocking. Browser tests go in `tests/Browser/` (see `.env.example` for required vars such as `WICKET_BROWSER_BASE_URL`). Run `composer test:unit` before pushing and `composer test:browser` for UI/auth changes.

## Commit & Pull Request Guidelines
Recent history shows short, imperative commit subjects (for example, `restore format`, `introduces new filters`). Keep commits focused and atomic. PRs should include:
- clear summary and scope
- linked issue/task
- test evidence (`composer lint`, `composer test`, and browser test notes when relevant)
- screenshots for UI/template updates
- changelog update in `CHANGELOG.md` for notable features/fixes
