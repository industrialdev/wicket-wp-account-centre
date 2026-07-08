---
title: "Cascade Strategy: Config Schema"
audience: [developer, implementer]
slug: cascade-config-schema
source_files:
  - "src/Config/OrgManConfig.php"
---

# Cascade Strategy: Config Schema

Cascade mode uses shared config keys rather than a dedicated `cascade` namespace.

## Most Relevant Keys

- `membership.strategy = cascade`
- `membership.resolution.prefer_current_cycle`
- `member_management.addition.*`
- `access.permissions.*`
- `relationships.*`
- `presentation.member_list.*`
- `presentation.member_view.*`
- `member_management.bulk_upload.*`
