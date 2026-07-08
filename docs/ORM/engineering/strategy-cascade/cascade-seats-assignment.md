---
title: "Cascade Strategy: Seats"
audience: [developer, implementer]
slug: cascade-seats-assignment
source_files:
  - "src/Services/MembershipService.php"
  - "src/Services/Strategies/CascadeStrategy.php"
---

# Cascade Strategy: Seats

Cascade mode still targets organization memberships for seat consumption.

## Current Behavior

- add flow requires a resolvable organization membership
- seat-limit messaging is shared with non-groups views
- additional-seat purchase flow is shared across the library
