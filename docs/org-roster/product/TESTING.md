---
title: "Testing"
audience: [implementer, developer]
source_files: ["src/OrgMan.php"]
---

# Testing

This package does not currently ship a `tests/` directory or a `composer test` script.

Older docs referenced Pest and unit tests. That is not the current state of this library repository.

## Available Verification Commands

```bash
composer check
composer check:case-collisions
php -l src/OrgMan.php
```

`composer check` currently runs PHP CS Fixer in dry-run mode if the dev dependency is installed.

## Practical Validation

When changing this library, validate:

- PHP syntax on touched PHP files
- account-page rendering on the supported page slugs
- strategy-specific add/remove flows
- bulk upload when `presentation.member_list.show_bulk_upload = true`
- additional-seats Gravity Forms and WooCommerce handoff when that integration is in use

## Current Documentation Rule

Do not claim Pest, Brain Monkey, or bundled unit-test coverage for this package unless those files are added to the repository.
