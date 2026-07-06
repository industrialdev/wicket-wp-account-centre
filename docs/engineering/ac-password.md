---
title: "Ac Password"
audience: [developer, agent]
php_class: WicketAcc\Blocks\ChangePassword
source_files: ["src/Blocks/ChangePassword.php", "templates-wicket/blocks/change-password.hb.php"]
---

# AC Password Block Documentation

## Overview
The Password block provides functionality for users to change their password. It handles password validation, form processing, and error handling through the Wicket API client.

## Block Architecture

The block is registered with **HyperBlocks** (`estebanforge/hyperblocks`), the block library that pairs with HyperFields. Registration happens in `ChangePassword::registerBlock()` on `init` priority 9 — ahead of HyperBlocks' own `Bootstrap::registerBlocks` (`init` priority 10) so the fluent block lands in the registry before iteration.

### Files
```
src/Blocks/ChangePassword.php                         # registration + form handler
templates-wicket/blocks/change-password.hb.php        # render template (.hb.php)
assets/css/blocks/change-password.css                 # enqueued via the block's style handle
```

### Render model
HyperBlocks renders the `.hb.php` template in an **isolated scope** (a closure with `extract($attributes)`). Field values arrive as plain PHP variables (`$form_title`, `$form_instructions`); there is no `$this` or `self`. Form-submission errors are bridged across that scope boundary via the public static `ChangePassword::getFormErrors()`, which reads the `private static $form_errors` populated by the form handler.

## Core Functionality

### Implementation Details

1. **Form Processing** — `processWicketPasswordForm()` hooks `init` and runs on POST. It validates inputs, calls the MDP people API, redirects on success, or stores errors for the next render.
2. **Password Validation** — current password verification, new password validation, confirmation matching, error collection.
3. **API Integration** — current user's API client, person data via Wicket API, response/error handling.

### Features

1. **Block fields** (HyperBlocks, with editor defaults applied via `setDefault`):
   - `form_title` (text, default "Change Password")
   - `form_instructions` (textarea, default "Enter your current password and choose a new one.")
2. **Editor metadata** — `setCategory('wicket-account-center')`, `setIcon('lock')`, `setDescription`, `setKeywords`, `setStyle('wicket-acc-password-block')`. The category slug matches the filter registered in `src/Blocks.php`.
3. **Security** — capability checks on the MDP path; passwords are read with `wp_unslash()` only (never `sanitize_text_field`, which would strip special characters and corrupt passwords).

## Error Handling
- Empty field validation
- Password mismatch detection
- API error handling
- User feedback messages

## Integration Points
- MDP Person API
- WordPress user system
- HyperBlocks Registry (`HyperBlocks\Registry::getInstance()->registerFluentBlock()`)

## Related Documentation
- [Base Block](../engineering/base-block.md)
