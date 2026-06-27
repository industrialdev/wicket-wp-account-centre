---
title: "Dead Code Audit"
audience: [developer]
source_files: ["src/OrgMan.php", "src/Controllers/", "src/Services/", "src/Helpers/"]
---

# Dead Code Audit

**Date:** 2026-03-19
**Scope:** org-roster code (`WicketORM\`) + all workspace consumers (theme, plugins, QA)

---

## Dead Class

| Class | File | Notes |
|-------|------|-------|
| `OrganizationBatchService` | `src/Services/OrganizationBatchService.php` | Never instantiated or referenced anywhere in the workspace |

## Dead Property

| Symbol | File | Notes |
|--------|------|-------|
| `OrgMan::$config` | `src/OrgMan.php` | Assigned in constructor, never read |

## Dead Methods

### OrgMan (`src/OrgMan.php`)

- [ ] `clearUserOrgCache()` — never called
- [ ] `clearAllOrgCache()` — never called

### ConfigurationController (`src/Controllers/ConfigurationController.php`)

- [ ] `enableAdditionalSeatsNotice()` — never called
- [ ] `enableAdditionalSeatsCss()` — never called
- [ ] `enableAdditionalSeatsJs()` — never called
- [ ] `addAdditionalSeatsNotice()` — only reachable via dead `enableAdditionalSeatsNotice()`
- [ ] `addAdditionalSeatsCss()` — only reachable via dead `enableAdditionalSeatsCss()`
- [ ] `addAdditionalSeatsJs()` — only reachable via dead `enableAdditionalSeatsJs()`
- [ ] `getAdditionalSeatsConfig()` — never called
- [ ] `isAdditionalSeatsEnabled()` — duplicates `ConfigService`; never called
- [ ] `getAdditionalSeatsSku()` — duplicates `ConfigService`; never called
- [ ] `getAdditionalSeatsFormId()` — duplicates `ConfigService`; never called
- [ ] `getAdditionalSeatsMinQuantity()` — duplicates `ConfigService`; never called
- [ ] `getAdditionalSeatsMaxQuantity()` — duplicates `ConfigService`; never called

### MemberService (`src/Services/MemberService.php`)

- [ ] `hasRole()`
- [ ] `personHasOrgRoles()`
- [ ] `getMembershipMembers()`
- [ ] `searchMembers()`
- [ ] `getFormattedRolesString()`
- [ ] `isCurrentUserConfirmed()`
- [ ] `checkUserConfirmation()`
- [ ] `getPersonById()`

### NotificationService (`src/Services/NotificationService.php`)

- [ ] `success()`
- [ ] `error()`
- [ ] `warning()`
- [ ] `info()`
- [ ] `addNotification()`
- [ ] `getNotifications()`
- [ ] `clearNotifications()`
- [ ] `convertWpError()`
- [ ] `generateJs()`
- [ ] `renderLegacy()`

### AdditionalSeatsService (`src/Services/AdditionalSeatsService.php`)

- [ ] `getAdditionalSeatsProductInfo()`
- [ ] `storePurchaseUserMeta()`
- [ ] `updateSubscriptionSeatCount()`

### PermissionHelper (`src/Helpers/PermissionHelper.php`)

- [ ] `role_check()`
- [ ] `can_edit_members()`
- [ ] `is_membership_owner()`
- [ ] `is_organization_membership_owner()`

### ConfigService (`src/Services/ConfigService.php`)

- [ ] `getSupplementalMembersUrl()`

### OrganizationService (`src/Services/OrganizationService.php`)

- [ ] `getMembershipUuid()`

### ConnectionService (`src/Services/ConnectionService.php`)

- [ ] `getActivePersonOrganizationConnections()`

### DocumentService (`src/Services/DocumentService.php`)

- [ ] `getDocuments()`
- [ ] `getDocumentsByOrg()`

### GroupService (`src/Services/GroupService.php`)

- [ ] `getManageRoles()`
- [ ] `extractOrgIdentifier()`

### RelationshipHelper (`src/Helpers/RelationshipHelper.php`)

- [ ] `is_valid_relationship_type()`
- [ ] `get_available_relationship_types()`

### GravityFormsHelper (`src/Helpers/GravityFormsHelper.php`)

- [ ] `is_supplemental_members_page()`
- [ ] `get_form_field_values()`

### Helper (`src/Helpers/Helper.php`)

- [ ] `log_critical()`

---

## Latent Bugs (Resolved)

### 1. NotificationService — snake_case method mismatch (Fixed)

`success()`, `error()`, `warning()`, and `info()` now correctly call `$this->addNotification()`.

### 2. Controller — permission callback mismatch (Fixed)

REST route registration now uses camelCase `checkLoggedIn` as a permission callback, matching `ApiController` definition.

### 3. OrgMan — API route registration (Fixed)

`OrgMan::registerApiRoutes()` now correctly looks for `registerRoutes` (camelCase).
