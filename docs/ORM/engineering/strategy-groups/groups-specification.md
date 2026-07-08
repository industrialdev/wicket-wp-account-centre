---
title: "Groups Strategy: Specification"
audience: [developer, implementer]
slug: groups-specification
source_files:
  - "src/Services/Strategies/GroupsStrategy.php"
  - "src/Services/MemberService.php"
---

# Groups Strategy: Specification

## Current Contract

- strategy key: `groups`
- registered in `MemberService`
- requires `group_uuid` for mutations
- uses `GroupService` for access and member operations
- changes top-level management UI to groups mode
- uses shared queued bulk upload when enabled
