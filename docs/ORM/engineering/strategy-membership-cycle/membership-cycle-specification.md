---
title: "Membership Cycle Strategy: Specification"
audience: [developer, implementer]
slug: membership-cycle-specification
source_files:
  - "src/Services/Strategies/MembershipCycleStrategy.php"
---

# Membership Cycle Strategy: Specification

## Current Contract

- strategy key: `membership_cycle`
- registered in `MemberService`
- requires explicit membership context for add/remove
- validates org scope before mutation
- delegates add to direct strategy
- removes by ending a single person-membership assignment
- uses shared queued bulk upload when enabled

## Current Limits

- no packaged cycle resolver
- no packaged cycle-tab UI
- earlier planning notes should not be read as shipped feature guarantees
