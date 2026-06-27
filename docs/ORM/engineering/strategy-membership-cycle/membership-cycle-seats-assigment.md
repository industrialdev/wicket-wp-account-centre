# Membership Cycle Strategy: Seats

Membership-cycle mode is intended to keep seat mutations tied to an explicit membership record.

## Current Behavior

- add and bulk-upload paths can carry explicit membership context
- additional-seats flow stores membership context in session, cart item meta, and order meta
- checkout hooks update seat counts using membership information when it is available

## Important Limit

Do not describe the current package as having a fully cycle-specific seat UI across every surface.
