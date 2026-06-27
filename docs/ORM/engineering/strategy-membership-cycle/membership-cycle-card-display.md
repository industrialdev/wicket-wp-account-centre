# Membership Cycle Strategy: Card Display

Membership-cycle mode currently reuses the shared organization-centric UI.

## Current Display Behavior

- organization cards render through shared non-groups templates
- member list and view use shared templates
- unified view can be enabled through shared UI config
- organization-list cards source membership tier data from the org-scoped endpoint (`MembershipService::getOrganizationMemberships()`) rather than the manager's personal membership entries; all active and in-grace org memberships are shown per card

## Current Limits

- the package does not currently ship a cycle-tab or multi-cycle resolver UI
