# Phase 0 Notes — Baseline and Contract Capture

## 0.1 Membership Member Row Contract (non-groups)

Fields consumed by templates/SSE:

| Field | Required | Consumers | Notes |
|---|---|---|---|
| person_uuid | yes | members-list, members-list-unified, member-details, edit/remove buttons | primary identifier |
| full_name | yes | members-list, members-list-unified | display name |
| email | yes | members-list, members-list-unified, member-details | contact link |
| title | no | members-list, members-list-unified | job title display |
| roles | yes | members-list, members-list-unified, member-details | fallback when current_roles absent |
| current_roles | yes | members-list, members-list-unified, member-details | preferred role source |
| is_owner | yes | members-list, members-list-unified | owner badge + remove button guard |
| lazy_loaded | yes | members-list, members-list-unified, member-details | controls skeleton vs detail rendering |
| confirmed_at | yes | members-list, members-list-unified, member-details | account status indicator |
| relationship_names | no | members-list, members-list-unified, member-details | relationship type display |
| relationship_type | no | members-list, members-list-unified | used in edit-permissions modal data |
| relationship_description | no | members-list-unified, member-details | description display |
| person_connection_ids | no | members-list, members-list-unified | passed to remove-member modal |
| person_membership_id | no | members-list, members-list-unified | passed to remove-member modal |

Derived fields set by consumers:
- `is_confirmed` — derived from `confirmed_at` in member-details.php
- `lazy_loaded` — set to `true` by member-details.php SSE endpoint

## 0.2 Group Member Row Contract

Fields consumed by group templates:

| Field | Required | Consumers | Notes |
|---|---|---|---|
| person_uuid | yes | group-members-list | primary identifier |
| full_name | yes | group-members-list | display name |
| email | no | group-members-list | contact link |
| role | yes | group-members-list | single group role display |
| group_member_id | yes | group-members-list | passed to remove-group-member modal |

Note: `is_confirmed` is checked via `MemberService::isUserConfirmed()` in members-list-groups.php, not from the row itself.

## 0.3 Read-Path Caller Inventory

| Caller | Method | Type |
|---|---|---|
| templates-partials/members-list.php | `getMembers()` | template (hypermedia) |
| templates-partials/organization-members.php | `getMembers()` | template (server-side include) |
| templates-partials/member-details.php | `getMemberByPersonUuid()` | SSE endpoint |
| src/Services/MemberService.php | `getMembershipMembers()` | internal (called by getMembers) |
| src/Services/MemberService.php | `searchMembers()` | internal (calls getMembers) |

Group read callers:

| Caller | Method | Type |
|---|---|---|
| templates-partials/group-members.php | `GroupService::getGroupMembers()` | template |
| templates-partials/members-list-groups-endpoint.php | `GroupService::getGroupMembers()` | hypermedia endpoint |
| src/Services/MemberService.php | `getGroupMembers()` | facade (delegates to GroupService) |
| src/Services/Strategies/GroupsStrategy.php | `GroupService::getGroupMembers()` | strategy internal |

## 0.4 Mutation/Update Caller Inventory

| Caller | Method | Type |
|---|---|---|
| templates-partials/process/add-member.php | `addMember()` | hypermedia handler |
| templates-partials/process/remove-member.php | `removeMember()` | hypermedia handler |
| templates-partials/process/add-group-member.php | `addMember()` | hypermedia handler (groups) |
| templates-partials/process/remove-group-member.php | `removeMember()` | hypermedia handler (groups) |
| templates-partials/process/update-permissions.php | `updateMemberRoles()` / `updateMemberRelationship()` / `updateMemberDescription()` | hypermedia handler |
| src/Services/BulkMemberUploadService.php | `addMember()` | background job |
| src/OrgMan.php | `clearMembersCache()` | cache bridge |

## 0.5 Cache Key Families and Invalidation

### Current cache keys (read path)

| Cache | Key pattern | TTL source |
|---|---|---|
| membership list | `orgman_members_{hash(membershipUuid+page+size+lazy+gen)}` | default cache duration |
| membership search | `orgman_search_{hash(membershipUuid+searchTerm+page+size)}` | search cache duration |
| lazy member details | `orgman_lazy_details_{hash(personUuid+orgUuid+membershipUuid+gen)}` | default cache duration |
| membership metadata | `orgman_membership_data_{hash(membershipUuid)}` | default cache duration |
| person roles | `orgman_person_roles_{hash(personUuid+orgUuid)}` | default cache duration |

### Generation mechanism

- `getMembershipGeneration($membershipUuid)` — reads `orgman_mgen_{hash(membershipUuid)}`
- `bumpMembershipGeneration($membershipUuid)` — increments the generation counter
- list and lazy-detail keys include the generation, so a bump automatically stales them

### Current invalidation behavior

- `invalidateMemberCache()` bumps generation + deletes metadata key + deletes legacy "initial" keys
- **Search cache does NOT include generation** — search results may remain stale after mutations
- Process handlers and `OrgMan` both call `clearMembersCache()` after mutations

### Known gap

Search cache invalidation is not guaranteed by the generation bump. This is a correctness risk.
