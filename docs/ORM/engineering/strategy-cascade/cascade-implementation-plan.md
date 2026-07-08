---
title: "Cascade Strategy: Current Status"
audience: [developer, implementer]
slug: cascade-implementation-plan
source_files:
  []
---

# Cascade Strategy: Current Status

This file replaces older plan-oriented notes.

## Current Status

- strategy class exists and is registered in `MemberService`
- cascade mode is available through `membership.strategy = cascade`
- add/remove behavior is implemented today
- bulk upload uses the shared queued uploader
