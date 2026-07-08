---
title: "WordPress Filters Reference"
audience: [developer]
php_class: ConfigService
source_files: ["src/Services/ConfigService.php", "src/Config/OrgManConfig.php", "src/Helpers/Helper.php"]
---

# WordPress Filters Applied by This Library

Every `apply_filters` call in the codebase, enumerated so future refactors don't silently break hooks that external consumers depend on.

---

## Global Config Filters

Applied by `OrgManConfig::get()` on every invocation. These receive and return the full config array.

| Filter | Source | Purpose |
|---|---|---|
| `wicket/org-roster/config` | `OrgManConfig.php:484` | Primary config override. Receives full config array, must return array. |
| `wicket/acc/orgman/config` | `OrgManConfig.php:485` | Legacy alias. Same signature. Applied after the primary filter. |

Both filters run on every `OrgManConfig::get()` call (no caching). Order matters: `wicket/org-roster/config` runs first, then `wicket/acc/orgman/config` receives its output.

---

## Per-Field Filters (Library-Owned)

Applied by `ConfigService` methods. Each wraps a single config value read from `OrgManConfig::get()`. Consumers can override individual settings without touching the full config array.

| Filter | Source | Config path | Type | Default |
|---|---|---|---|---|
| `wicket/org-roster/additional_seats_enabled` | `ConfigService.php:42` | `integrations.additional_seats.enabled` | `bool` | `false` |
| `wicket/org-roster/additional_seats_sku` | `ConfigService.php:55` | `integrations.additional_seats.sku` | `string` | `'additional-seats'` |
| `wicket/org-roster/additional_seats_discount_sku` | `ConfigService.php:68` | `integrations.additional_seats.discount_sku` | `string` | `'corporate-seat-discount'` |
| `wicket/org-roster/additional_seats_form_id` | `ConfigService.php:90` | `integrations.additional_seats.form_id` | `int` | `0` (auto-detected from slug if possible) |
| `wicket/org-roster/additional_seats_min_quantity` | `ConfigService.php:143` | `integrations.additional_seats.min_quantity` | `int` | `1` |
| `wicket/org-roster/additional_seats_max_quantity` | `ConfigService.php:156` | `integrations.additional_seats.max_quantity` | `int` | `900` |
| `wicket/org-roster/additional_seats_tier_mode` | `ConfigService.php:247` | `integrations.additional_seats.tier_mode` | `bool` | `false` |
| `wicket/org-roster/additional_seats_tier_skus` | `ConfigService.php:275` | `integrations.additional_seats.tier_skus` | `array<string,string>` | `[]` |
| `wicket/org-roster/additional_seats_tier_slug_field` | `ConfigService.php:294` | `integrations.additional_seats.tier_slug_field` | `string` | `'tier-slug'` |
| `wicket/org-roster/allowed_document_types` | `ConfigService.php:171` | `integrations.documents.allowed_types` | `array` | `['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','gif']` |
| `wicket/org-roster/max_document_size` | `ConfigService.php:184` | `integrations.documents.max_size` | `int` | `10485760` (10 MB) |
| `wicket/org-roster/business_info_seat_limit` | `ConfigService.php:197` | `integrations.business_info.seat_limit_info` | `string\|null` | `null` |

### Interaction with global filters

Per-field filters run **inside** `ConfigService` methods, after the config array has already been resolved (including global filters). The resolution order for any value is:

1. `OrgManConfig::get()` builds the default array
2. `wicket/org-roster/config` filter mutates the full array
3. `wicket/acc/orgman/config` filter mutates the full array
4. `ConfigService` method extracts the value from the filtered array
5. Per-field filter wraps the extracted value

This means a per-field filter **always wins** over a global filter for that specific value, because it runs last.

### Refactor warning

Any code path that bypasses `ConfigService` (e.g., reading `OrgManConfig::get()` directly) skips all per-field filters. This is the primary reason to standardize on `ConfigService` as the single access point (see `docs/config-cache-consolidation-rfc.md`, Step 1).

---

## External Filters Consumed (Not Owned)

These are WordPress/WPML/plugin filters that this library **calls** but does not define. Documented here for completeness.

| Filter | Source | Purpose |
|---|---|---|
| `wpml_object_id` | `ConfigService.php:110-112` | Translates Gravity Form IDs to the current language. Called inside `getLocalizedFormId()`. |
| `wpml_object_id` | `ConfigService.php:227` | Translates `my-account` CPT post IDs for supplemental members URL. Called inside `getSupplementalMembersUrl()`. |
| `wpml_object_id` | `Helper.php:342` | Translates `my-account` CPT post IDs for general page URLs. Called inside `getMyAccountPageUrl()`. |

All three are guarded by `wicket_is_multilang_active()` checks. They only fire when WPML or a compatible multilingual plugin is active.

---

## Utility Filters

| Filter | Source | Purpose |
|---|---|---|
| `wicket_orgman_log_levels` | `Helper.php:55` | Overrides allowed log levels per environment. Receives `array $allowed_levels, string $env`. Returns `array`. |

---

## Quick Reference: Total Count

| Category | Count |
|---|---|
| Global config filters | 2 |
| Per-field config filters | 9 |
| External filters consumed | 1 (`wpml_object_id`, 3 call sites) |
| Utility filters | 1 |
| **Total `apply_filters` calls** | **22** |

> Count includes the three tier-mode filters added with the multi-tier additional seats flow.
