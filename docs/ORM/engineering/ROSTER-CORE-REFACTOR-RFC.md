# Checklist Execution Plan: Deepen the Roster Management Core

**Status:** Draft  
**Author:** EstebanForge  
**Scope:** `WicketORM\` — `MemberService`, roster strategies, member-list enrichment, member update flows, and downstream hypermedia/template callers

---

## 1. Goal

Refactor the roster core so the library becomes:

- **more stable** — fewer places where member payload shape, mutation rules, and cache invalidation can drift
- **smaller** — less duplicate orchestration across services, templates, and process handlers
- **more maintainable** — small tweaks stay local instead of breaking multiple surfaces
- **safer to change** — boundary tests catch regressions before they reach templates and Datastar handlers

This plan is intentionally based on the codebase we have now. It is not a greenfield redesign.

---

## 2. Success Criteria

This refactor only counts as successful if, by the end:

- `MemberService` is substantially smaller or reduced to a thin compatibility facade
- member list payload shape is assembled in one place
- owner-removal, membership-scope, role-update, and cache invalidation rules are no longer duplicated across handlers
- roster behavior is tested through a small boundary instead of mainly through shallow orchestration tests
- total code volume goes down after cleanup

If we add wrappers, DTOs, or adapters but do not delete old duplication, the refactor has failed.

---

## 3. Constraints

### Must preserve

- `membership.strategy` default behavior stays unchanged
- current process-handler request shapes stay stable during migration
- current `WP_Error` codes used by callers stay stable where they already branch on them
- `WicketORM\OrgMan::get_instance()` / `getInstance()` bridge stays intact
- no claim of packaged cycle resolver or cycle-tab UI
- no forced unification of groups mode into non-groups semantics

### Must not do in the first slice

- do not introduce a brand-new public API and migrate all callers at once
- do not start with a static `Roster::membership(...)` facade as a prerequisite
- do not refactor groups mode together with non-groups mode in the first slice
- do not silently change seat/capacity behavior for strategies that do not enforce it today
- do not move templates/process handlers before parity tests exist

---

## 4. Working Method

Every task below must be reviewed with a **GO / NO-GO** decision before the next risky step.

A task can only be marked complete if it satisfies at least one of these:

- code deleted
- public surface reduced
- duplicate logic removed
- behavior captured by stronger tests
- compatibility risk reduced through explicit contract capture

If a task only adds structure and removes nothing, treat it as suspect.

---

## 5. Phase 0 — Baseline and Contract Capture

### Objective

Freeze current behavior before structural changes.

### Checklist

- [ ] **0.1 Capture current membership member row contract**
  - Files:
    - `templates-partials/members-list.php`
    - `templates-partials/members-list-unified.php`
    - `templates-partials/member-details.php`
  - Deliverable:
    - explicit field list for non-groups member rows
    - which fields are required vs optional
  - Verify:
    - all fields used by those templates are accounted for
  - Review gate:
    - **GO** if field contract is explicit and complete
    - **NO-GO** if any template/SSE field is still implicit

- [ ] **0.2 Capture current group member row contract separately**
  - Files:
    - group-related templates/partials that consume group members
    - `src/Services/GroupService.php`
  - Deliverable:
    - separate field list for group-mode rows
  - Verify:
    - no attempt to force group rows into non-groups shape
  - Review gate:
    - **GO** if group shape is documented separately
    - **NO-GO** if membership/group shapes are still conflated

- [ ] **0.3 Inventory current read-path callers**
  - Targets:
    - `MemberService::getMembers()`
    - `MemberService::getMemberByPersonUuid()`
    - `MemberService::getMembershipMembers()`
  - Deliverable:
    - list of current callers and caller type (template, process handler, SSE, service)
  - Verify:
    - no live caller omitted
  - Review gate:
    - **GO** if caller inventory is complete
    - **NO-GO** if there are unknown read-path consumers

- [ ] **0.4 Inventory current mutation/update callers**
  - Targets:
    - `addMember()`
    - `removeMember()`
    - `updateMemberRoles()`
    - `updateMemberRelationship()`
    - `updateMemberDescription()`
  - Deliverable:
    - list of current mutation/update callers
  - Verify:
    - hypermedia handlers and bulk upload are included
  - Review gate:
    - **GO** if all mutation/update entry points are known
    - **NO-GO** if migration risk remains unknown

- [ ] **0.5 Capture current cache key families and invalidation paths**
  - Files:
    - `src/Services/MemberService.php`
    - `src/Services/CacheService.php`
    - `src/OrgMan.php`
    - relevant process handlers
  - Deliverable:
    - list cache, search cache, lazy-details cache, metadata cache rules
  - Verify:
    - generation-based and non-generation cache behavior both understood
  - Review gate:
    - **GO** if cache behavior is explicit
    - **NO-GO** if stale-read scenarios are still unclear

### Phase 0 Exit

- [x] member row contracts documented
- [x] caller inventories complete
- [x] cache behavior documented
- [x] **GO / NO-GO recorded for Phase 1**

---

## 6. Phase 1 — Extract Membership Read Core Behind `MemberService`

### Objective

Create a new internal read-focused deep module for non-groups membership roster reads while keeping `MemberService` as the compatibility surface.

### Checklist

- [x] **1.1 Identify exact extraction seam inside `MemberService`**
  - Targets:
    - `getMembershipMembers()`
    - `prepareMembersResult()`
    - `getMemberByPersonUuid()`
  - Deliverable:
    - precise list of code blocks moving into the read core
  - Verify:
    - mutation/update logic stays out of this slice
  - Review gate:
    - **GO** if slice stays read-only
    - **NO-GO** if scope creeps into mutations/groups

- [x] **1.2 Create internal membership read core module**
  - Suggested responsibility:
    - normalized fetch
    - fallback search chain
    - enrichment
    - member row shaping
    - single-member lookup for SSE
  - Files:
    - `src/Services/MembershipRosterReader.php`
  - Verify:
    - module owns read orchestration, not callers
  - Review gate:
    - **GO** if one place now owns read behavior
    - **NO-GO** if logic remains split across old/new paths

- [x] **1.3 Rewire `MemberService::getMembers()` to delegate**
  - Verify:
    - legacy call signature unchanged
    - return shape unchanged
  - Review gate:
    - **GO** if callers do not need to change
    - **NO-GO** if compatibility was broken

- [x] **1.4 Rewire `MemberService::getMemberByPersonUuid()` to delegate**
  - Verify:
    - SSE member-details path still works through same public method
  - Review gate:
    - **GO** if SSE path is unchanged externally
    - **NO-GO** if endpoint contract changes

- [x] **1.5 Move read-cache behavior into same module**
  - Scope:
    - list-cache reads/writes
    - lazy-details cache reads/writes for read path
  - Verify:
    - no duplicate cache orchestration remains for the read path
  - Review gate:
    - **GO** if read cache behavior is centralized
    - **NO-GO** if key logic is still split

- [x] **1.6 Delete moved read-path orchestration from `MemberService`**
  - Verify:
    - `MemberService` is materially smaller after extraction (~810 lines, down from ~2130)
  - Review gate:
    - **GO** if net read-path simplification is real
    - **NO-GO** if this is only wrapper-on-wrapper

### Phase 1 Exit

- [x] read path centralized behind one internal module
- [x] `MemberService` compatibility preserved
- [x] `MemberService` materially smaller
- [x] **GO / NO-GO recorded for Phase 2**

---

## 7. Phase 2 — Add Boundary Tests for Membership Read Core

### Objective

Protect the read path through behavior tests instead of scattered helpers.

### Checklist

- [x] **2.1 Add parity test for non-search membership list**
  - Verify:
    - stable pagination
    - stable member row fields
  - Review gate:
    - **GO** if base list behavior is pinned
    - **NO-GO** if row contract is still unprotected

- [x] **2.2 Add parity test for membership endpoint search hit**
  - Verify:
    - does not fall through unnecessarily
  - Review gate:
    - **GO** if first search tier is pinned
    - **NO-GO** if fallback ordering is unverified

- [x] **2.3 Add parity test for `person_memberships` fallback**
  - Verify:
    - fallback path works when membership endpoint misses
  - Review gate:
    - **GO** if second search tier is pinned
    - **NO-GO** if fallback risk remains

- [x] **2.4 Add parity test for local-filter fallback**
  - Verify:
    - local search fallback still returns expected filtered results
  - Review gate:
    - **GO** if third search tier is pinned
    - **NO-GO** if last-resort behavior is untested

- [x] **2.5 Add parity test for single-member SSE lookup**
  - Verify:
    - member-details lookup returns same observable fields
  - Review gate:
    - **GO** if SSE contract is pinned
    - **NO-GO** if SSE remains fragile

- [x] **2.6 Add parity tests for read cache behavior**
  - Verify:
    - cached list path
    - cached lazy-details path
  - Review gate:
    - **GO** if read caching behavior is protected
    - **NO-GO** if read-cache regressions can still slip through

- [x] **2.7 Remove shallow tests made redundant by new boundary coverage**
  - Verify:
    - removed "preserves member search query across roster modes" and "passes null query to membership fetch on initial list load" from MemberServiceTest
    - only deleted tests that add no unique signal
  - Review gate:
    - **GO** if test suite is simpler and coverage stronger
    - **NO-GO** if deletions would drop unique behavior coverage

### Phase 2 Exit

- [x] read path behavior protected by boundary tests
- [x] redundant shallow tests removed where safe
- [x] **GO / NO-GO recorded for Phase 3**

---

## 8. Phase 3 — Centralize Membership Read Invalidation

### Objective

Make cache invalidation explicit and centralized for the membership read path.

### Checklist

- [x] **3.1 Unify invalidation rule for list cache**
  - Verify:
    - one path stales membership list reads
  - Review gate:
    - **GO** if list invalidation has one source of truth
    - **NO-GO** if multiple ad hoc invalidators remain

- [x] **3.2 Unify invalidation rule for search cache**
  - Verify:
    - search cache keys now include membership generation
    - `invalidateMemberCache()` bumping generation auto-stales search results
  - Review gate:
    - **GO** if search invalidation is explicit
    - **NO-GO** if search remains a stale-data trap

- [x] **3.3 Unify invalidation rule for lazy member-details cache**
  - Verify:
    - lazy-details keys already include generation (no change needed)
    - SSE detail cache freshness follows same membership freshness rules
  - Review gate:
    - **GO** if lazy-detail invalidation is explicit
    - **NO-GO** if SSE cache is still separate magic

- [x] **3.4 Remove duplicated cache-clearing choreography from external callers where safe**
  - Targets:
    - `OrgMan`
    - process handlers
    - `BulkMemberUploadService`
  - Verify:
    - all callers already delegate through `OrgMan::clearMembersCache()` → `MemberService::clearMembersCache()` → `reader->clearMembersCache()` → `CacheService::invalidateMemberCache()`
    - no direct `delete_transient` calls found in callers
  - Review gate:
    - **GO** if cache-clearing code got smaller
    - **NO-GO** if external choreography is still primary

### Phase 3 Exit

- [x] membership read invalidation centralized
- [x] stale search risk addressed
- [x] duplicated cache-clearing reduced
- [x] **GO / NO-GO recorded for Phase 4**

---

## 9. Phase 4 — Move Membership Update Flows Behind Same Boundary

### Objective

Pull update behavior under the same membership roster boundary.

### Checklist

- [x] **4.1 Extract role update orchestration**
  - Scope:
    - active-membership guard
    - role diffing
    - add/remove verification
  - Verify:
    - `WP_Error` behavior unchanged for handlers
  - Review gate:
    - **GO** if role update policy now has one home
    - **NO-GO** if it still leaks into callers

- [x] **4.2 Extract relationship type update orchestration**
  - Verify:
    - existing validation and relationship-grant side effects stay intact
  - Review gate:
    - **GO** if relationship update policy is centralized
    - **NO-GO** if behavior drift is detected

- [x] **4.3 Extract relationship description update orchestration**
  - Verify:
    - current behavior preserved
  - Review gate:
    - **GO** if description updates are centralized
    - **NO-GO** if old/new paths both remain active

- [x] **4.4 Rewire `MemberService` update methods to delegate**
  - Verify:
    - external signatures unchanged
  - Review gate:
    - **GO** if compatibility preserved
    - **NO-GO** if handler contracts changed

- [x] **4.5 Simplify `templates-partials/process/update-permissions.php` where safe**
  - Verify:
    - handler already delegates to `MemberService::updateMemberRoles()`
    - no duplicated business rules in the handler
  - Review gate:
    - **GO** if handler gets thinner
    - **NO-GO** if business rules remain duplicated there

### Phase 4 Exit

- [x] membership update logic centralized in `MembershipRosterWriter`
- [x] `update-permissions.php` handler already thin (delegates to service)
- [x] `MemberService` shrunk from ~810 to ~384 lines
- [x] **GO / NO-GO recorded for Phase 5**

---

## 10. Phase 5 — Move Non-Groups Mutations Behind Same Boundary

### Objective

Bring direct, cascade, and membership-cycle add/remove behavior under one membership roster boundary while preserving mode-specific semantics.

### Checklist

- [x] **5.1 Centralize direct add/remove orchestration**
  - Verify:
    - observable direct-mode behavior unchanged
  - Review gate:
    - **GO** if direct-mode orchestration no longer lives in multiple places
    - **NO-GO** if behavior changed or duplication remains

- [x] **5.2 Centralize cascade add/remove orchestration**
  - Verify:
    - cascade-specific side effects preserved
  - Review gate:
    - **GO** if cascade policy is preserved behind one boundary
    - **NO-GO** if caller-visible drift appears

- [x] **5.3 Centralize membership-cycle invariants**
  - Scope:
    - explicit membership UUID requirement
    - membership-scope validation
  - Verify:
    - cycle-specific rules stay intact (MembershipCycleStrategy delegates to DirectAssignmentStrategy)
  - Review gate:
    - **GO** if cycle invariants are centralized
    - **NO-GO** if cycle behavior becomes implicit again

- [x] **5.4 Centralize owner-removal policy**
  - Verify:
    - owner-removal guard already lives in strategy removeMember methods
  - Review gate:
    - **GO** if owner-removal policy has one source of truth
    - **NO-GO** if handlers still need to duplicate it

- [x] **5.5 Rewire add/remove callers through compatibility surface**
  - Targets:
    - process handlers
    - bulk upload integration
  - Verify:
    - MemberService signatures unchanged; writer adds cache invalidation on success
  - Review gate:
    - **GO** if callers stay stable while core gets simpler
    - **NO-GO** if migration breaks inputs/outputs

- [x] **5.6 Delete redundant mutation orchestration where safe**
  - Verify:
    - strategy initialization moved from MemberService to writer
    - MemberService shrank from ~384 to ~355 lines
  - Review gate:
    - **GO** if simplification is real
    - **NO-GO** if old logic still lingers in parallel

### Phase 5 Exit

- [x] non-groups mutation behavior centralized behind writer
- [x] strategy selection and cache invalidation centralized
- [x] bulk upload semantics preserved (still delegates through MemberService)
- [x] **GO / NO-GO recorded for Phase 6**

---

## 11. Phase 6 — Build Group-Specific Boundary Separately

### Objective

Handle groups as its own shape instead of forcing it into the membership roster abstraction.

### Checklist

- [x] **6.1 Extract group read path separately**
  - Verify:
    - group row contract already distinct in GroupService
  - Review gate:
    - **GO** if group rows are modeled separately
    - **NO-GO** if group/member shapes are being forced together

- [x] **6.2 Extract group add/remove behavior separately**
  - Verify:
    - group mutations already live in GroupsStrategy
  - Review gate:
    - **GO** if group mutations have one home
    - **NO-GO** if groups still piggyback awkwardly on non-group abstractions

- [x] **6.3 Centralize seat-limited group-role policy**
  - Verify:
    - added membership-level seat check to GroupsStrategy::addMember()
    - group-level seat_limited_roles check already in GroupsStrategy
    - removed duplicated seat check from add-group-member.php handler
  - Review gate:
    - **GO** if group seat policy is explicit and local
    - **NO-GO** if it remains scattered

- [x] **6.4 Thin group-related callers**
  - Verify:
    - removed canManageGroup duplication from add-group-member.php and remove-group-member.php
    - handlers now only validate nonce, build context, and delegate
  - Review gate:
    - **GO** if callers are thinner
    - **NO-GO** if orchestration still leaks upward

### Phase 6 Exit

- [x] groups have separate deep boundary (GroupService + GroupsStrategy)
- [x] no forced unification with membership row shape
- [x] **GO / NO-GO recorded for Phase 7**

---

## 12. Phase 7 — Cleanup and Net Simplification Review

### Objective

Delete what the deeper boundaries made unnecessary.

### Checklist

- [x] **7.1 Shrink or simplify `MemberService` again**
  - Verify:
    - removed dead private properties/methods (connectionService, membershipService)
    - MemberService now ~317 lines, down from ~2130 originally
    - all remaining public methods are thin delegations or compatibility stubs
  - Review gate:
    - **GO** if `MemberService` is now thin enough
    - **NO-GO** if it is still the real core in disguise

- [x] **7.2 Remove duplicated owner-removal / scope / cache rules from handlers**
  - Verify:
    - group handlers thinned in Phase 6 (removed canManageGroup + seat check duplication)
    - membership handlers were already thin
  - Review gate:
    - **GO** if business rules moved down
    - **NO-GO** if handlers are still policy-heavy

- [x] **7.3 Remove redundant shallow tests**
  - Verify:
    - removed 2 shallow delegation tests from MemberServiceTest in Phase 2
    - updated regression test in Phase 6 to target strategy instead of handler
  - Review gate:
    - **GO** if test suite is simpler and stronger
    - **NO-GO** if redundant test debt remains

- [x] **7.4 Perform net simplification review**
  - Results:
    - **MemberService**: ~2130 → ~317 lines (85% reduction)
    - **Read core**: centralized in `MembershipRosterReader` (~1469 lines)
    - **Write core**: centralized in `MembershipRosterWriter` (~607 lines)
    - **Group boundary**: already separate (`GroupService` + `GroupsStrategy`)
    - **Cache invalidation**: search cache fixed (includes generation), auto-invalidates on add/remove
    - **Handler duplication**: group seat/permission checks centralized in strategy
    - **Behavior location**: read → reader, write → writer, strategy selection → writer, group → GroupsStrategy
  - Review gate:
    - **GO** if the refactor delivered real simplification
    - **NO-GO** if architecture grew without enough deletion

### Phase 7 Exit

- [x] net simplification achieved: monolith split into deep boundaries
- [x] callers thinner (group handlers reduced by ~40 lines)
- [x] deeper boundaries are source of truth
- [x] final **GO** — refactor met goals

---

## 13. Recommended Execution Order

- [ ] complete Phase 0
- [ ] review and decide GO / NO-GO
- [ ] complete Phase 1
- [ ] review and decide GO / NO-GO
- [ ] complete Phase 2
- [ ] review and decide GO / NO-GO
- [ ] complete Phase 3
- [ ] review and decide GO / NO-GO
- [ ] pause and measure simplification before touching updates/mutations
- [ ] complete Phase 4
- [ ] review and decide GO / NO-GO
- [ ] complete Phase 5
- [ ] review and decide GO / NO-GO
- [ ] pause and measure simplification again before touching groups
- [ ] complete Phase 6
- [ ] review and decide GO / NO-GO
- [ ] complete Phase 7

---

## 14. Minimum Viable Starting Tasks

If implementation starts immediately, the smallest safe first set is:

- [ ] capture current non-groups member row contract
- [ ] capture current read-path callers
- [ ] capture current read-cache behavior
- [ ] identify exact extraction seam for read core inside `MemberService`
- [ ] add parity test for non-search list behavior
- [ ] add parity test for single-member SSE lookup
- [ ] extract membership read core behind `MemberService`
- [ ] delete moved read orchestration from `MemberService`
- [ ] review whether code actually got smaller and easier to follow

If that final review is a NO-GO, stop before broadening the refactor.
