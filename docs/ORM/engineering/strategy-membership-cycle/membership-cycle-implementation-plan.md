# Membership Cycle Strategy: Current Status

This file replaces older plan-oriented notes.

## Current Status

- strategy class exists and is registered in `MemberService`
- explicit membership UUID is required for mutating actions
- add delegates to direct strategy after scope validation
- remove ends the target person-membership assignment
- shared queued bulk upload can target membership-cycle mode

## Current Limits

- no packaged cycle resolver or cycle-tab UI
- no dedicated cycle-only bulk-upload schema namespace in config
