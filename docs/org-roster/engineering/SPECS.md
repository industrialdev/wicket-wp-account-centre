---
title: "Current Library Spec"
audience: [developer, implementer]
source_files: ["src/OrgMan.php", "src/Services/Strategies/"]
---

# Current Library Spec

This file describes what the library does today.

## Supported Strategy Keys

- `direct`
- `cascade`
- `groups`
- `membership_cycle`

## Supported Account Screens

- organization list and detail flows
- organization member management
- group member management in groups mode
- supplemental-members purchase flow for additional seats
- organization-members-bulk page when bulk upload UI is enabled

## Implemented Runtime Capabilities

- singleton bootstrap through `WicketORM\OrgMan`
- strategy-based member add and remove flows
- shared CSV bulk-upload process handler
- async CSV member export with secure download tokens (opt-in via `exports.enabled`)
- MDP engagement/donation data display with configurable sections (opt-in via `engagement.enabled`)
- group-member add and remove flows
- additional-seats checkout integration
- organization, member, membership, group, and permission services
- config-driven unified and legacy member views
- hypermedia partial endpoint via `TemplateHelper`

## Current Runtime Gaps

- no bundled automated tests in this package
- no cycle-tab resolver UI for `membership_cycle`
- no packaged documentation guarantee that additional-seats UI propagation is cycle-specific across every membership-cycle surface

## Bulk Upload

Shared bulk upload is implemented through `templates-partials/process/bulk-upload-members.php`.

Current characteristics:

- disabled by default
- additive only
- strategy-aware through `MemberService`
- available to non-groups and membership-cycle flows when the UI flag is enabled

## Additional Seats

Current additional-seats flow includes:

- Gravity Forms capture
- WooCommerce cart and checkout handoff
- order processing hooks in `OrgMan`
- membership ID and organization UUID persistence in session, user meta, order item meta, and order meta

Current documentation limit:

- the library stores `membership_id` and uses it during seat processing
- the package does not currently ship a dedicated cycle-specific UI layer proving every membership-cycle surface passes the intended membership context

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
