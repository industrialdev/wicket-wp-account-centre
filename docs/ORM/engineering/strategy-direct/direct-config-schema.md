---
title: "Direct Strategy: Config Schema"
audience: [developer, implementer]
slug: direct-config-schema
source_files:
  - "src/Config/OrgManConfig.php"
---

# Direct Strategy: Config Schema

Direct mode uses the shared library config without a dedicated `direct` namespace.

## Most Relevant Keys

- `membership.strategy = direct`
- `member_management.addition.*`
- `access.permissions.*`
- `relationships.*`
- `presentation.member_list.*`
- `presentation.member_view.*`
- `member_management.forms.add_member.*`
- `member_management.bulk_upload.*`
