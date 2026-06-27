# RFC: Config & Cache Cleanup (Incremental)

## Context

Settings retrieval and cache operations are dispersed across multiple overlapping classes:
- `ConfigService` (object instance with domain-specific getters)
- `ConfigHelper` (static utility class referencing `OrgManConfig` directly)
- `CacheService` (transient manager with version salting)
- `OrgManConfig::get()` (static config source, called directly in 25+ sites)

Additionally, `OrganizationService` implements its own private cache-aside methods (`getCachedData`, `setCachedData`) that bypass `CacheService` entirely, operating on raw transients without version salting.

A full hexagonal refactor (consolidated `RosterEnvInterface`, Ports & Adapters) was analyzed and found to touch 12+ service classes, 22+ template files, 25+ direct config call sites, and 7 services with no constructor dependency. 10 breaking changes, 13 missing functionality gaps, 4 unresolved design concerns. The risk/reward for future-proofing does not justify a single 10-phase migration.

Instead: 5 incremental improvements that deliver 80% of the structural benefit at near-zero risk. Each is independently shippable. Each leaves the codebase cleaner for the day a full consolidation is warranted.

---

## 1. Standardize on `ConfigService` as Single Access Point

### Problem
25+ call sites across strategies, services, helpers, and controllers call `\WicketORM\Config\OrgManConfig::get()` directly. This bypasses any per-field WordPress filters that `ConfigService` applies and creates split-brain config access.

**Affected files:**
- All 4 strategies (`CascadeStrategy`, `DirectAssignmentStrategy`, `GroupsStrategy`, `MembershipCycleStrategy`)
- `PermissionService`, `PermissionHelper`
- `EngagementService`, `MemberExportService`, `NotificationService`
- `ConnectionService`, `OrganizationBatchService`, `GroupService`
- `MembershipRosterWriter`, `MembershipService`
- `ConfigurationController`, `OrgMan`
- Template files: `content-organization-*.php`, `process/remove-group-member.php`

### What to do
Replace each `\WicketORM\Config\OrgManConfig::get()` call with a `ConfigService` call. Two patterns exist:

**Pattern A: Services that already have `ConfigService` or `$this->config`**
Most services store `$this->config = OrgManConfig::get()` in their constructor. Replace the stored array with `$this->configService` (already injected in many cases), then read via typed getter methods.

**Pattern B: Services with no `ConfigService` dependency**
Services like `PermissionService`, `ConnectionService`, `GroupService`, `OrganizationBatchService` store `$this->config` but take no `ConfigService` in their constructor. Either:
- Add `ConfigService` to their constructor (preferred if the service already has other constructor dependencies)
- Add a generic `getConfigValue(string $key, mixed $default = null): mixed` method to `ConfigService` and use it via lazy instantiation (preferred if the service is widely instantiated in templates)

**Pattern C: Templates**
Template files that call `OrgManConfig::get()` directly (`content-organization-*.php`, `process/remove-group-member.php`) should use the `$configService` variable they already create via `new ConfigService()`.

### How to enforce
Add a PHP_CS_Fixer or custom sniff that flags `\WicketORM\Config\OrgManConfig::get()` calls outside of `ConfigService`, `ConfigHelper`, and `OrgMan`. This prevents regression.

### Scope per PR
One file or one strategy per PR. Each PR is reviewable in minutes. No cross-cutting risk.

### Why this matters first
Every other improvement builds on top of a single config access point. If the codebase still reaches around `ConfigService` directly, any future consolidation has to chase two patterns instead of one.

---

## 2. Fix the `PermissionHelper::function_exists()` Bug

### Problem
`PermissionHelper` calls `ConfigHelper` static methods with defensive `function_exists()` guards:

```php
$manage_roles = function_exists('\WicketORM\Helpers\ConfigHelper::get_manage_members_roles')
    ? ConfigHelper::get_manage_members_roles()
    : ['membership_manager', 'membership_owner'];
```

`function_exists()` with a class method string always returns `false` in PHP. It checks for plain functions, not class methods. This works today only because `ConfigHelper` is autoloaded and the ternary always takes the truthy branch. The fallback is dead code that masks a real defect.

If `ConfigHelper` is ever removed (any future refactor), these calls silently fall back to hardcoded role defaults. No error. No log. Role permissions would revert to defaults on every page load.

**Affected call sites:**
- `PermissionHelper::user_can_manage_members()` (line 398-401)
- `PermissionHelper::user_can_edit_organization()` (line 472-475)
- `PermissionHelper::user_has_any_management_role()` (line 501-506)
- `PermissionHelper::user_can_purchase_seats()` (line 546-551)
- `PermissionHelper::user_can_manage_members_any_org()` (line 624-627)

### What to do
Replace all `function_exists()` guards with direct calls:

```php
// Before
$manage_roles = function_exists('\WicketORM\Helpers\ConfigHelper::get_manage_members_roles')
    ? ConfigHelper::get_manage_members_roles()
    : ['membership_manager', 'membership_owner'];

// After
$manage_roles = ConfigHelper::get_manage_members_roles();
```

`ConfigHelper` is part of this library. It will always be present. The guard provides zero protection and hides a latent defect.

### Why independently
This is a bug, not an improvement. It should ship regardless of whether any other cleanup happens. One PR. Five line changes. Zero risk.

---

## 3. Route `OrganizationService` Through `CacheService`

### Problem
`OrganizationService` has its own private `getCachedData()` and `setCachedData()` methods that call `get_transient()`/`set_transient()` directly. No version salt. No key hashing. No cache-enabled guard. This is a separate caching channel that:
- Does not respect the `platform.cache.enabled` flag the same way `CacheService` does (it checks `ConfigHelper::is_cache_enabled()` but then operates on raw transients)
- Uses un-salted keys while `CacheService` salts and hashes everything
- Cannot be invalidated by `CacheService::invalidateMemberCache()` or generational bumps

**Current code** (`OrganizationService.php`):
```php
private function getCachedData($cache_key)
{
    if (!\WicketORM\Helpers\ConfigHelper::is_cache_enabled()) {
        return false;
    }
    return get_transient($cache_key);
}

private function setCachedData($cache_key, $data)
{
    if (\WicketORM\Helpers\ConfigHelper::is_cache_enabled()) {
        $cache_duration = \WicketORM\Helpers\ConfigHelper::get_cache_duration();
        set_transient($cache_key, $data, $cache_duration);
    }
}
```

### What to do
Replace the private methods with a `CacheService` instance. `OrganizationService` already has a constructor with no dependencies; add `CacheService` as a lazy property (matching the pattern used by `MembershipRosterReader`).

```php
private ?CacheService $cacheService = null;

private function cacheService(): CacheService
{
    if (!isset($this->cacheService)) {
        $this->cacheService = new CacheService();
    }
    return $this->cacheService;
}
```

Then replace `getCachedData($key)` with `$this->cacheService()->get($key)` and `setCachedData($key, $data)` with `$this->cacheService()->set($key, $data)`.

### Caveat: existing un-salted keys
Any cached data stored by the current un-salted keys will not be hit after migration because `CacheService` salts and hashes keys. This is acceptable: stale transients expire naturally (default 5 minutes). No manual cleanup needed.

### Related: `MemberService::getMembers()` line 216
`MemberService::getMembers()` instantiates `new CacheService()` directly in the method body (not lazily) to pre-warm the lazy-details cache. This is the same category of issue: raw instantiation bypassing a service accessor. If Step 3 is in scope, this should be addressed in the same pass or explicitly noted as a follow-up.

### Scope
Primary: `OrganizationService.php` (`getCachedData`/`setCachedData` methods and their call sites). Secondary: `MemberService.php` line 216 raw `new CacheService()` instantiation.

---

## 4. Add `cacheRemember()` to `CacheService`

### Problem
Several call sites implement a manual cache-aside pattern: check if cached, if not fetch and store. This is verbose and inconsistent:

```php
// MembershipRosterReader (simplified)
$cached = $this->cacheService()->get($key);
if ($cached !== false) {
    return $cached;
}
$data = $apiClient->fetchMembers($membershipUuid, $page, $size);
$this->cacheService()->set($key, $data, $search_ttl);
return $data;
```

### What to do
Add a `remember()` method to `CacheService`:

```php
/**
 * Fetch from cache, or execute callback on miss and store the result.
 *
 * @param string        $key      Cache identifier.
 * @param callable():mixed $callback Evaluated on miss.
 * @param int|null      $ttl      Custom TTL. Falls back to configured duration.
 * @return mixed Cached value or callback result.
 */
public function remember(string $key, callable $callback, ?int $ttl = null): mixed
{
    $cached = $this->get($key);
    if ($cached !== false) {
        return $cached;
    }

    $value = $callback();
    $this->set($key, $value, $ttl);

    return $value;
}
```

Behavior: when cache is disabled, `$this->get()` returns `false`, the callback executes, and `$this->set()` no-ops (returns `false`). The callback result is returned without caching. This delegates cache-enabled checks to the existing `get()`/`set()` methods instead of duplicating the guard.

### Refactor targets (opt-in, file by file)
- `MembershipRosterReader::fetchMemberList()` (search cache)
- `MemberService::getMembershipData()` (membership cache)
- `OrganizationService` (after step 3 routes it through `CacheService`)

### Scope
Add the method to `CacheService`. Migrate callers one at a time. No existing callers are forced to change.

### Why this matters
This is the highest-value convenience method from the full RFC proposal, delivered without any interface change. Every cache-aside call site gets simpler. Future unit tests can mock one method instead of three.

---

## 5. Document the Per-Field WordPress Filters

### Problem
`ConfigService` applies 9 distinct WordPress filters on individual config values. These are separate from the two global config filters in `OrgManConfig::get()`. External plugins may be hooked into them. Any future refactor that bypasses `ConfigService` silently breaks these hooks.

| Method | Filter hook |
|---|---|
| `isAdditionalSeatsEnabled()` | `wicket/org-roster/additional_seats_enabled` |
| `getAdditionalSeatsSku()` | `wicket/org-roster/additional_seats_sku` |
| `getAdditionalSeatsDiscountSku()` | `wicket/org-roster/additional_seats_discount_sku` |
| `getAdditionalSeatsFormId()` | `wicket/org-roster/additional_seats_form_id` |
| `getAdditionalSeatsMinQuantity()` | `wicket/org-roster/additional_seats_min_quantity` |
| `getAdditionalSeatsMaxQuantity()` | `wicket/org-roster/additional_seats_max_quantity` |
| `getAllowedDocumentTypes()` | `wicket/org-roster/allowed_document_types` |
| `getMaxDocumentSize()` | `wicket/org-roster/max_document_size` |
| `getBusinessInfoSeatLimitInfo()` | `wicket/org-roster/business_info_seat_limit` |

### What to do
Create an engineering doc at `docs/engineering/config-filters.md` that:
1. Lists every WordPress filter this library applies, with the method that applies it, the config path it overrides, the expected value type, and the default value. Note: `getLocalizedFormId()` and `getAdditionalSeatsFormIdForCurrentLanguage()` apply the external `wpml_object_id` filter (not a library-owned `wicket/` hook). These should be documented separately.
2. Documents the two global config filters (`wicket/org-roster/config`, `wicket/acc/orgman/config`) and their interaction with per-field filters.
3. Notes that any refactor touching `ConfigService` must preserve these filters or explicitly deprecate them with a migration path for consumers.

### Scope
Documentation only. No code changes. One new file, one entry in `docs/index.md`.

### Why this matters
These filters are invisible. Nobody on the team can name all 9 from memory. If a future refactor (including the full hexagonal consolidation) replaces `ConfigService` with a generic `config('path')` call, these hooks silently vanish. Documentation is the cheapest insurance against that.

---

## Execution Order

| Step | What | Risk | Files touched |
|---|---|---|---|
| 2 | Fix `function_exists()` bug | Low (active bug; defaults match for most sites) | 1 file, 6 lines |
| 5 | Document per-field filters | None | 1 new doc |
| 3 | Route `OrganizationService` through `CacheService` | Low | 1-2 files |
| 4 | Add `cacheRemember()` to `CacheService` | Low | 1 class + opt-in callers |
| 1 | Standardize on `ConfigService` | Low per PR | 25+ sites, one per PR |

Steps 2 and 5 are low/zero-risk warmups. Step 3 fixes the cache inconsistency. Step 4 adds the convenience method. Step 1 is the long tail that can run in parallel with everything else.

### Note on Step 1: `ConfigService` per-call overhead
`ConfigService` itself calls `OrgManConfig::get()` on every method invocation. `OrgManConfig::get()` reconstructs the full config array and runs two `apply_filters()` calls each time. Services that currently store `$this->config = OrgManConfig::get()` once in their constructor avoid this repeated work. Standardizing on `ConfigService` introduces additional filter pipeline executions per request.

This is acceptable if either (a) the number of extra filter calls per request is small (tens, not thousands), or (b) `ConfigService` is updated to cache the `OrgManConfig::get()` result internally (instance or static cache). Option (b) is a one-line change inside `ConfigService` and should be included in the first PR of Step 1.

---

## What This Enables Later

After these 5 steps ship, the codebase has:
- One config access pattern (through `ConfigService`)
- One cache channel (through `CacheService`, with `remember()` convenience)
- No latent bugs in permission guards
- Documented filter hooks for any future work

If the team later decides to pursue the full hexagonal consolidation (`RosterEnvInterface`, Ports & Adapters, `InMemoryRosterEnv`), the migration is smaller and safer because the access patterns are already unified. The full gap analysis from the original RFC investigation is preserved in this library's git history for reference.

### Note on `MembershipRosterReader` as a reference pattern
`MembershipRosterReader` is cited in Step 3 as the pattern for lazy `CacheService` injection. However, its constructor still calls `OrgManConfig::get()` directly alongside the injected `ConfigService`. This is a partially-migrated pattern. It should not be treated as a finished reference until Step 1 is applied to it.
