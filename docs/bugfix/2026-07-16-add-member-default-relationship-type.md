# Add Member: stop assigning a default "Main Contact" relationship

**Date:** 2026-07-16
**Ticket:** WWID-1988
**Component:** `src/WicketORM/` (org roster, migrated into wicket-wp-account-centre)
**Strategy affected:** direct assignment (also reached via `membership_cycle`, which delegates to it)
**Asana:** https://app.asana.com/1/1138832104141584/project/1214714078970424/task/1216608950386698?focus=true

## Symptom
Adding a member through the Add Member form assigned the person the "Main Contact"
person-to-organization relationship, even though no relationship type was selected.

## Root cause
The Add Member form's relationship-type field is disabled by default, so the form
submits no `relationship_type`. `DirectAssignmentStrategy::addMember()` then fell back
to the config default `relationships.addition.type`, which shipped as the literal
slug `position`. `position` is not a valid person-organization relationship slug in
Wicket, so the MDP resolved the connection to the tenant's default org relationship —
surfacing as "Main Contact" on this tenant. (An empty/blank type defaults the same way,
so the library must send *no* relationship at all, not just a blank one.)

## Fix
- `src/WicketORM/Config/OrgManConfig.php` — `relationships.addition.type` default changed
  from `position` to `''` (kept as a filterable knob for tenants that want a valid
  implicit default).
- `src/WicketORM/Services/Strategies/DirectAssignmentStrategy.php` — only create the
  `person_to_organization` connection when a relationship type was actually resolved;
  otherwise skip it. The membership seat is the member's org linkage.
- `src/WicketORM/Services/ConnectionService.php` — `buildConnectionPayload()` no longer
  requires `type`; it omits the attribute when empty instead of erroring
  (defense-in-depth; cascade/groups always pass a non-empty type).

## Verified (staging MDP, real `addMember` path)
Verified end-to-end against the staging MDP on the equivalent code in the standalone
`wicket-lib-org-roster` (line-for-line identical to this `src/WicketORM/` copy):

| | `addition.type` | connections created | roster relationship |
|---|---|---|---|
| Before | `position` | 1 × `main_contact` | "Main Contact" |
| After | `` (empty) | 0 | none |

Seat-only members still render correctly in the roster with no relationship label.
A genuinely valid slug (e.g. `president`) is preserved by the MDP; only blank/invalid
input gets defaulted.

The add-member flow and the relationship-based contacts roster are complementary. The
contacts roster (`ContactService`, `process/add-contact.php`) is the intended path for
assigning person-to-organization relationships and requires an explicit
`relationship_type`; it creates connections via `ConnectionService::ensurePersonConnection()`
and never routes through `buildConnectionPayload()`, so this fix is isolated from it.
`ConfigService::getFullConfig()` delegates to `OrgManConfig::get()`, so the
`addition.type = ''` default flows through the standardized config accessor unchanged.

## Out of scope
`GroupsStrategy` and `CascadeStrategy` still fall back to
`RelationshipHelper::get_default_relationship_type()` (`relationships.defaults.type`,
shipped as `Position`) and would exhibit the same defaulting if used.
