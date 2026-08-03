---
title: "Current Library Spec"
audience: [developer, implementer]
source_files: ["src/OrgMan.php", "src/Services/", "src/Services/Strategies/", "src/Controllers/"]
---

# Current Library Spec

This file describes what the library does today.

## Supported Strategy Keys

- `direct`
- `cascade`
- `groups`
- `membership_cycle`

Strategy selection is driven by `membership.strategy`. See [STRATEGIES.md](STRATEGIES.md) for behavior.

## Supported Account Screens

- organization list and detail flows
- organization member management
- organization contacts roster (when `contacts.enabled = true`)
- group member management in groups mode
- supplemental-members purchase flow for additional seats (single-SKU and multi-tier)
- organization-members-bulk page when bulk upload UI is enabled

## Implemented Runtime Capabilities

- singleton bootstrap through `WicketORM\OrgMan`
- strategy-based member add and remove flows
- shared CSV bulk-upload process handler
- async CSV member export with secure download tokens (opt-in via `exports.enabled`)
- MDP engagement/donation data display with configurable sections (opt-in via `engagement.enabled`)
- group-member add and remove flows
- contacts roster (President, CEO, Treasurer, etc.) via relationship types, with on-add role grants and on-removal role strips
- additional-seats checkout integration (single-SKU and multi-tier)
- organization, member, membership, group, contact, and permission services
- config-driven unified and legacy member views
- hypermedia partial endpoint via `TemplateHelper`
- per-tier seat fulfillment with idempotency (`tier_seats_applied`) and partial-fulfilment retry safety (`tier_seats_partial`)

## Current Runtime Gaps

- no bundled automated tests in this package
- no cycle-tab resolver UI for `membership_cycle`
- no packaged documentation guarantee that additional-seats UI propagation is cycle-specific across every membership-cycle surface
- the contacts roster ships its own page and process handlers; it does not currently reuse the members-list bulk patterns

## Bulk Upload

Shared bulk upload is implemented through `templates-partials/process/bulk-upload-members.php`.

Current characteristics:

- disabled by default (`presentation.member_list.show_bulk_upload = false`)
- additive only
- strategy-aware through `MemberService`
- available to non-groups and membership-cycle flows when the UI flag is enabled

## Additional Seats

Two flows are supported today. Both flow through `WicketORM\Services\AdditionalSeatsService`.

Single-SKU (default):

- Gravity Forms capture
- WooCommerce cart and checkout handoff
- order processing hooks in `OrgMan`
- membership ID and organization UUID persistence in session, user meta, order item meta, and order meta
- one WooCommerce product per org (`integrations.additional_seats.sku`)
- legacy quantity cap of `900` hard-enforced even if the GF quantity field allows more

Multi-tier (opt-in via `tier_mode = true`):

- one WooCommerce product per tier, either mapped explicitly (`tier_skus`) or derived as `{sku}-{tier-slug}`
- tier slug passed from the purchase callout to Gravity Forms through `integrations.additional_seats.tier_slug_field`
- tier classification happens from the cart item data (truth source), not from user meta
- fulfilment bumps the matching tier's `organization_memberships.max_assignments` by the line-item quantity
- per-line-item idempotency (`tier_seats_applied`) and partial-fulfilment retry safety (`tier_seats_partial`)
- admin setup warning when `tier_mode = true` but `tier_skus` is empty (also surfaces expected form slug and tier-slug field parameter)

Current documentation limit:

- the library stores `membership_id` and uses it during seat processing
- the package does not currently ship a dedicated cycle-specific UI layer proving every membership-cycle surface passes the intended membership context

## Contacts Roster

Relationship-only contacts roster (President, President Elect, Secretary, CEO, Treasurer, Main Contact). Disabled by default.

Current characteristics:

- activated via `contacts.enabled = true`
- rendered on the `organization-contacts` page slug
- `contacts.relationship_types.roster` defines which relationship slugs count as contacts (default: `president`, `president_elect`, `secretary`, `ceo`, `treasurer`, `main_contact`)
- permission flags `can_add`, `can_remove`, `can_view` default to `membership_manager`
- on add: auto-assigns `org_editor` and `membership_manager` (configurable via `contacts.on_add.assign_roles`)
- on removal: strips those roles, unless the person still has an active membership (`contacts.on_removal.skip_strip_if_has_membership = true`)
- separate from `membership.strategy` — works in any strategy mode
- contacts are relationship-only; no active membership is required to appear on the roster

## Member Export

Async CSV export is implemented through `MemberExportService` with WP-Cron batch processing.

Current characteristics:

- disabled by default (`exports.enabled = false`)
- secure download tokens with expiration and max-download limits
- WP-Cron based batch processing for large datasets
- configurable CSV columns and batch size
- REST endpoints: `/org-management/v1/exports/initiate`, `/org-management/v1/exports/status`

Export flow:

1. User triggers export via member list toolbar (when enabled)
2. Job queued with isolated option record in wp_options
3. WP-Cron processes members page-by-page from MDP
4. CSV written to uploads directory with secure token
5. Download link emailed to requesting user
6. Token expires after configured days or max downloads

## Engagement Display

MDP engagement/donation data display is implemented through `EngagementService` with configurable sections.

Current characteristics:

- disabled by default (`engagement.enabled = false`)
- configurable sections (Foundation, PAC, custom)
- badge parsing from person tags via regex patterns
- per-field formatting (currency, date, yesno, string)
- active membership conditional rendering
- REST endpoint: `/org-management/v1/engagement/person`

Section behavior:

- Foundation: Always shown (if enabled) - displays giving totals and donor levels
- PAC: Only shown when person is an active member (if enabled)
- Custom sections: Can be added via config with any MDP data_fields mapping

Active membership check:

- Uses `member_org_uuids` config array to determine active status
- Falls back to `wicket_get_person_active_memberships()` if list is empty
- Controls visibility of sections with `requires_active_membership => true`