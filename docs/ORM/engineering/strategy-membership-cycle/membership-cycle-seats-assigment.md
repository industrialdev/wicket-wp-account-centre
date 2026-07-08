---
title: "Membership Cycle Strategy: Seats"
audience: [developer, implementer]
slug: membership-cycle-seats
source_files:
  - "src/Services/Strategies/MembershipCycleStrategy.php"
  - "src/Services/AdditionalSeatsService.php"
---

# Membership Cycle Strategy: Seats

Membership-cycle mode is intended to keep seat mutations tied to an explicit membership record.

## Current Behavior

- add and bulk-upload paths can carry explicit membership context
- additional-seats flow stores membership context in session, cart item meta, and order meta
- checkout hooks update seat counts using membership information when it is available

## Multi-Tier Additional Seats

When `integrations.additional_seats.tier_mode = true`, the additional-seats flow works with multi-tier orgs (orgs that hold several active membership tiers at once):

- Each tier resolves its own WooCommerce product (mapped via `tier_skus` or derived as `{sku}-{tier-slug}`).
- The purchase callout passes the membership tier slug to Gravity Forms through `tier_slug_field` (default `tier-slug`).
- Order completion is fulfilled per line item: each tier line item bumps the matching tier's `organization_memberships.max_assignments`.
- Idempotency is per line item (`tier_seats_applied` order meta); partial-fulfilment retries use `tier_seats_partial`.
- Truth comes from the cart item data, not user meta — a single user-meta record cannot represent several memberships.

ESCRS is the current production exemplar of multi-tier additional seats paired with the membership_cycle strategy.

## Important Limit

Do not describe the current package as having a fully cycle-specific seat UI across every surface.