---
title: "Cascade Strategy: Specification"
audience: [developer, implementer]
slug: cascade-specification
source_files:
  - "src/Services/MemberService.php"
  - "src/Services/Strategies/CascadeStrategy.php"
---

# Cascade Strategy: Specification

## Current Contract

- strategy key: `cascade`
- registered in `MemberService`
- intended for legacy cascade-oriented side effects
- uses shared organization screens
- uses shared queued bulk upload when enabled
