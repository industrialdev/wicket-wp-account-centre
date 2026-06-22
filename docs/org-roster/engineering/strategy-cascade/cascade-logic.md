# Cascade Strategy: Logic

Cascade strategy keeps the library aligned with legacy cascade-oriented member-management behavior.

## Current Logic

- create or resolve person
- resolve membership for the target organization
- ensure person-to-organization relationship exists
- perform seat assignment and role side effects through the current cascade path
- emit notifications and touchpoints where available
