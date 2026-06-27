# Direct Strategy: Logic

Direct strategy is the most explicit organization-membership flow in the package.

## Current Logic

- resolve person record first
- resolve membership for the target organization
- ensure the person has an org relationship
- assign the person to the membership seat
- assign roles
- emit touchpoint and email side effects where available
