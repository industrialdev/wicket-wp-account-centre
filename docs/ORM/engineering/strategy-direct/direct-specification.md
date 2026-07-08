---
title: "Direct Strategy: Specification"
audience: [developer, implementer]
slug: direct-specification
source_files:
  - "src/Services/MemberService.php"
  - "src/Services/Strategies/DirectAssignmentStrategy.php"
---

# Direct Strategy: Specification

## Current Contract

- strategy key: `direct`
- registered in `MemberService`
- default strategy from `OrgManConfig`
- add uses explicit seat assignment and role assignment
- remove expects `person_membership_id` context
- explicit membership overrides are supported and org-scope validated
