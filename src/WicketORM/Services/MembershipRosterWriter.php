<?php

declare(strict_types=1);

namespace WicketORM\Services;

use WicketORM\Services\Strategies\RosterManagementStrategy;

/**
 * Membership roster write core.
 *
 * Owns membership mutation orchestration: role updates, relationship type
 * updates, and relationship description updates.
 *
 * This is an internal module. Callers should continue to use MemberService
 * as the compatibility surface during migration.
 */
class MembershipRosterWriter
{
    /**
     * @var ConfigService
     */
    private ConfigService $configService;

    /**
     * @var array
     */
    protected array $config;

    /**
     * @var MembershipRosterReader
     */
    private MembershipRosterReader $reader;

    /**
     * @var ConnectionService|null
     */
    protected ?ConnectionService $connectionService = null;

    /**
     * @var RosterManagementStrategy[]
     */
    public array $strategies = [];

    /**
     * Constructor.
     *
     * @param ConfigService          $configService
     * @param MembershipRosterReader $reader
     */
    public function __construct(ConfigService $configService, MembershipRosterReader $reader, array &$config)
    {
        $this->configService = $configService;
        $this->config = &$config;
        $this->reader = $reader;
        $this->initStrategies();
    }

    /**
     * Initialize the available strategies.
     */
    private function initStrategies(): void
    {
        $this->strategies['cascade'] = new Strategies\CascadeStrategy();
        $this->strategies['direct'] = new Strategies\DirectAssignmentStrategy();
        $this->strategies['groups'] = new Strategies\GroupsStrategy();
        $this->strategies['membership_cycle'] = new Strategies\MembershipCycleStrategy();
    }

    /**
     * Get the current roster management strategy.
     *
     * @return RosterManagementStrategy
     */
    private function getStrategy(): RosterManagementStrategy
    {
        $mode = $this->configService->getRosterMode();

        return $this->strategies[$mode] ?? $this->strategies['cascade'];
    }

    /**
     * Lazily instantiate ConnectionService.
     */
    private function connectionService(): ConnectionService
    {
        if (!isset($this->connectionService)) {
            $this->connectionService = new ConnectionService();
        }

        return $this->connectionService;
    }

    /**
     * Update member roles with active-membership guard and diff-based add/remove.
     *
     * @param string $personUuid     Person UUID.
     * @param string $orgUuid        Organization UUID.
     * @param string $membershipUuid Membership UUID.
     * @param array  $roles          Desired role list.
     * @return array|\WP_Error
     */
    public function updateMemberRoles($personUuid, $orgUuid, $membershipUuid, $roles)
    {
        if (!function_exists('wicket_api_client')) {
            return new \WP_Error('api_unavailable', 'Wicket API client is not available.');
        }

        if (empty($personUuid) || empty($orgUuid) || empty($membershipUuid)) {
            return new \WP_Error('invalid_params', 'Person UUID, organization UUID, and membership UUID are required.');
        }

        try {
            $client = wicket_api_client();
            $logger = \Wicket()->log();
            $log_context = ['source' => 'wicket-orgman', 'action' => 'update_member_roles'];

            // Get current person memberships (paginate to avoid selecting stale/inactive records on partial pages)
            $memberships_endpoint = '/organization_memberships/' . rawurlencode($membershipUuid) . '/person_memberships';
            $page = 1;
            $totalPages = 1;
            $person_memberships = [];

            do {
                $response = $client->get($memberships_endpoint . '?' . http_build_query([
                    'page[number]' => $page,
                    'page[size]' => 100,
                    'include' => 'person',
                ]));

                $rows = is_array($response['data'] ?? null) ? $response['data'] : [];
                foreach ($rows as $membership) {
                    $currentPersonId = $membership['relationships']['person']['data']['id'] ?? null;
                    if ($currentPersonId === $personUuid) {
                        $person_memberships[] = $membership;
                    }
                }

                $pageMeta = $response['meta']['page'] ?? [];
                $totalPages = max(1, (int) ($pageMeta['total_pages'] ?? 1));
                $page++;
            } while ($page <= $totalPages);

            if (empty($person_memberships)) {
                return new \WP_Error('membership_not_found', 'Person membership not found in this organization.');
            }

            if ($logger) {
                $logger->debug('update_member_roles candidate person_memberships', $log_context + [
                    'org_uuid' => $orgUuid,
                    'membership_uuid' => $membershipUuid,
                    'person_uuid' => $personUuid,
                    'candidate_count' => count($person_memberships),
                    'candidates' => array_map(static function (array $membership): array {
                        return [
                            'id' => (string) ($membership['id'] ?? ''),
                            'active' => $membership['attributes']['active'] ?? null,
                            'in_grace' => $membership['attributes']['in_grace'] ?? null,
                            'starts_at' => $membership['attributes']['starts_at'] ?? null,
                            'ends_at' => $membership['attributes']['ends_at'] ?? null,
                        ];
                    }, $person_memberships),
                ]);
            }

            // Prefer active/in_grace record when duplicates exist.
            usort($person_memberships, static function (array $a, array $b): int {
                $aActive = !empty($a['attributes']['active']) || !empty($a['attributes']['in_grace']);
                $bActive = !empty($b['attributes']['active']) || !empty($b['attributes']['in_grace']);
                if ($aActive !== $bActive) {
                    return $aActive ? -1 : 1;
                }

                $aEndsAt = strtotime((string) ($a['attributes']['ends_at'] ?? '')) ?: PHP_INT_MAX;
                $bEndsAt = strtotime((string) ($b['attributes']['ends_at'] ?? '')) ?: PHP_INT_MAX;
                if ($aEndsAt === $bEndsAt) {
                    return 0;
                }

                return ($aEndsAt > $bEndsAt) ? -1 : 1;
            });

            $person_membership = $person_memberships[0];

            if (!$person_membership) {
                return new \WP_Error('membership_not_found', 'Person membership not found in this organization.');
            }

            $require_active_membership = (bool) ($this->config['member_management']['edit']['require_active_membership_for_role_updates'] ?? false);
            if ($require_active_membership) {
                $has_active_row = false;
                foreach ($person_memberships as $membership) {
                    if (!empty($membership['attributes']['active']) || !empty($membership['attributes']['in_grace'])) {
                        $has_active_row = true;
                        break;
                    }
                }

                $is_active_membership = $has_active_row;
                if (!$is_active_membership) {
                    try {
                        // Fallback: ask API for "active now" rows to avoid stale/incomplete attributes on list endpoints.
                        $query_response = $client->post('/person_memberships/query', [
                            'json' => [
                                'filter' => [
                                    'organization_membership_uuid_in' => [$membershipUuid],
                                    'person_uuid_in' => [$personUuid],
                                    'active_at' => 'now',
                                ],
                                'page' => [
                                    'number' => 1,
                                    'size' => 1,
                                ],
                            ],
                        ]);

                        $query_rows = is_array($query_response['data'] ?? null) ? $query_response['data'] : [];
                        $is_active_membership = !empty($query_rows);
                    } catch (\Throwable $active_lookup_exception) {
                        if ($logger) {
                            $logger->warning('update_member_roles active_at fallback query failed', $log_context + [
                                'org_uuid' => $orgUuid,
                                'membership_uuid' => $membershipUuid,
                                'person_uuid' => $personUuid,
                                'error' => $active_lookup_exception->getMessage(),
                            ]);
                        }
                    }
                }

                if ($logger) {
                    $logger->debug('update_member_roles active-membership guard result', $log_context + [
                        'org_uuid' => $orgUuid,
                        'membership_uuid' => $membershipUuid,
                        'person_uuid' => $personUuid,
                        'has_active_row' => $has_active_row,
                        'is_active_membership' => $is_active_membership,
                    ]);
                }

                if (!$is_active_membership) {
                    return new \WP_Error(
                        'inactive_member_role_update_forbidden',
                        'Cannot update roles for an inactive member.'
                    );
                }
            }

            // Prepare the update payload with roles
            $attributes = $person_membership['attributes'] ?? [];
            $attributes['roles'] = $roles;

            $update_payload = [
                'data' => [
                    'type' => $person_membership['type'],
                    'id' => $person_membership['id'],
                    'attributes' => $attributes,
                ],
            ];

            // Update roles using the correct API approach
            // Based on legacy wicket_assign_role and wicket_remove_role functions

            // Define which roles we can manage (organization-specific roles only).
            // Respect edit-permissions modal allow/deny config so hidden roles are not removed.
            $manageable_roles = ['membership_manager', 'org_editor'];
            $permissions_modal_config = is_array($this->config['member_management']['permissions_modal'] ?? null)
                ? $this->config['member_management']['permissions_modal']
                : [];
            $modal_allowlist = is_array($permissions_modal_config['allowlist'] ?? null)
                ? $permissions_modal_config['allowlist']
                : [];
            if ($modal_allowlist === [] && is_array($permissions_modal_config['allowed_roles'] ?? null)) {
                $modal_allowlist = $permissions_modal_config['allowed_roles'];
            }
            $modal_denylist = is_array($permissions_modal_config['denylist'] ?? null)
                ? $permissions_modal_config['denylist']
                : [];
            if ($modal_denylist === [] && is_array($permissions_modal_config['excluded_roles'] ?? null)) {
                $modal_denylist = $permissions_modal_config['excluded_roles'];
            }

            $normalizeRoleList = function (array $role_list): array {
                $normalized_roles = [];
                foreach ($role_list as $role_name) {
                    $role_slug = $this->reader->normalizeRoleSlug((string) $role_name);
                    if ($role_slug === '') {
                        continue;
                    }
                    $normalized_roles[] = $role_slug;
                }

                return array_values(array_unique($normalized_roles));
            };

            $normalized_modal_allowlist = $normalizeRoleList($modal_allowlist);
            $normalized_modal_denylist = $normalizeRoleList($modal_denylist);
            $manageable_roles = $normalizeRoleList($manageable_roles);

            if (!empty($normalized_modal_allowlist)) {
                $manageable_roles = array_values(array_intersect($manageable_roles, $normalized_modal_allowlist));
            }

            if (!empty($normalized_modal_denylist)) {
                $manageable_roles = array_values(array_diff($manageable_roles, $normalized_modal_denylist));
            }

            // Only consider manageable roles for add/remove operations. Compare against org-scoped
            // role assignments to avoid false positives from roles held in other organizations.
            $desired_manageable_roles = array_values(array_intersect($roles, $manageable_roles));
            $current_manageable_roles = array_values(array_intersect(
                $this->reader->getPersonCurrentRolesByOrgId($personUuid, $orgUuid),
                $manageable_roles
            ));

            // Determine which manageable roles to add and which to remove
            $roles_to_add = array_diff($desired_manageable_roles, $current_manageable_roles);
            $roles_to_remove = array_diff($current_manageable_roles, $desired_manageable_roles);

            if ($logger) {
                $logger->debug('update_member_roles role diff', $log_context + [
                    'org_uuid' => $orgUuid,
                    'membership_uuid' => $membershipUuid,
                    'person_uuid' => $personUuid,
                    'requested_roles' => array_values($roles),
                    'manageable_roles' => $manageable_roles,
                    'current_manageable_roles' => array_values($current_manageable_roles),
                    'desired_manageable_roles' => array_values($desired_manageable_roles),
                    'roles_to_add' => array_values($roles_to_add),
                    'roles_to_remove' => array_values($roles_to_remove),
                ]);
            }

            // Remove roles that are no longer needed
            foreach ($roles_to_remove as $role_name) {
                if ($logger) {
                    $logger->debug('update_member_roles removing role', $log_context + [
                        'org_uuid' => $orgUuid,
                        'membership_uuid' => $membershipUuid,
                        'person_uuid' => $personUuid,
                        'role' => $role_name,
                    ]);
                }

                $remove_role_result = function_exists('wicket_remove_role')
                    ? wicket_remove_role($personUuid, $role_name, $orgUuid)
                    : false;

                if (!$remove_role_result) {
                    if ($logger) {
                        $logger->error('update_member_roles failed removing role', $log_context + [
                            'org_uuid' => $orgUuid,
                            'membership_uuid' => $membershipUuid,
                            'person_uuid' => $personUuid,
                            'role' => $role_name,
                        ]);
                    }

                    return new \WP_Error(
                        'role_remove_failed',
                        sprintf("Failed to remove role '%s'.", $role_name)
                    );
                }

                $org_roles_after_remove = $this->reader->getPersonCurrentRolesByOrgId($personUuid, $orgUuid);
                $role_still_present = in_array($role_name, $org_roles_after_remove, true);
                if ($role_still_present) {
                    if ($logger) {
                        $logger->error('update_member_roles remove reported success but role remains', $log_context + [
                            'org_uuid' => $orgUuid,
                            'membership_uuid' => $membershipUuid,
                            'person_uuid' => $personUuid,
                            'role' => $role_name,
                            'org_roles_after_remove' => array_values($org_roles_after_remove),
                        ]);
                    }

                    return new \WP_Error(
                        'role_remove_verify_failed',
                        sprintf("Failed to verify removal of role '%s'.", $role_name)
                    );
                }
            }

            // Add new roles
            foreach ($roles_to_add as $role_name) {
                if ($logger) {
                    $logger->debug('update_member_roles adding role', $log_context + [
                        'org_uuid' => $orgUuid,
                        'membership_uuid' => $membershipUuid,
                        'person_uuid' => $personUuid,
                        'role' => $role_name,
                    ]);
                }

                if (!function_exists('wicket_assign_role') || !wicket_assign_role($personUuid, $role_name, $orgUuid)) {
                    if ($logger) {
                        $logger->error('update_member_roles failed adding role', $log_context + [
                            'org_uuid' => $orgUuid,
                            'membership_uuid' => $membershipUuid,
                            'person_uuid' => $personUuid,
                            'role' => $role_name,
                        ]);
                    }

                    return new \WP_Error(
                        'role_add_failed',
                        sprintf("Failed to add role '%s'.", $role_name)
                    );
                }
            }

            // Get person data for response
            $person_data = $this->reader->getPersonById($personUuid);

            return [
                'success' => true,
                'first_name' => $person_data['data']['attributes']['first_name'] ?? '',
                'last_name' => $person_data['data']['attributes']['last_name'] ?? '',
                'roles' => $roles,
            ];
        } catch (\Exception $e) {
            if (isset($logger) && $logger) {
                $logger->error('update_member_roles exception', $log_context + [
                    'org_uuid' => $orgUuid,
                    'membership_uuid' => $membershipUuid,
                    'person_uuid' => $personUuid,
                    'requested_roles' => is_array($roles) ? array_values($roles) : [],
                    'error' => $e->getMessage(),
                ]);
            }

            return new \WP_Error('update_exception', 'Failed to update member roles: ' . $e->getMessage());
        }
    }

    /**
     * Update member relationship type and optionally sync roles.
     *
     * @param string $personUuid       The person UUID
     * @param string $orgUuid          The organization UUID
     * @param string $relationshipType The new relationship type
     * @return array|\WP_Error Updated member data or WP_Error on failure
     */
    public function updateMemberRelationship($personUuid, $orgUuid, $relationshipType)
    {
        if (!function_exists('wicket_api_client')) {
            return new \WP_Error('api_unavailable', 'Wicket API client is not available.');
        }

        if (empty($personUuid) || empty($orgUuid) || empty($relationshipType)) {
            return new \WP_Error('invalid_params', 'Person UUID, organization UUID, and relationship type are required.');
        }

        try {
            // Update the connection type
            $connection_result = $this->connectionService()->updateConnectionType($personUuid, $orgUuid, $relationshipType);

            if (is_wp_error($connection_result)) {
                return $connection_result;
            }

            // Check if we should automatically update roles based on relationship type
            $config = $this->configService->getFullConfig();
            $relationship_based_permissions = $config['access']['permissions']['relationship_grants']['enabled'] ?? false;

            if ($relationship_based_permissions) {
                // Get the role mapping for this relationship type
                $relationship_roles_map = $config['access']['permissions']['relationship_grants']['roles_by_type'] ?? [];
                $new_roles = $relationship_roles_map[$relationshipType] ?? [];

                // Get all possible relationship-type-based roles
                $all_relationship_roles = [];
                foreach ($relationship_roles_map as $roles) {
                    $all_relationship_roles = array_merge($all_relationship_roles, $roles);
                }
                $all_relationship_roles = array_unique($all_relationship_roles);

                // Get current roles
                $current_roles = $this->reader->getPersonCurrentRolesByOrgId($personUuid, $orgUuid);

                // Determine which relationship-based roles to remove
                $roles_to_remove = array_intersect($current_roles, $all_relationship_roles);

                // Determine which relationship-based roles to add
                $roles_to_add = array_diff($new_roles, $current_roles);

                // Remove old relationship-based roles
                if (!empty($roles_to_remove)) {
                    foreach ($roles_to_remove as $role) {
                        if (function_exists('wicket_remove_role')) {
                            wicket_remove_role($personUuid, $role, $orgUuid);
                        }
                    }
                }

                // Add new relationship-based roles
                if (!empty($roles_to_add)) {
                    foreach ($roles_to_add as $role) {
                        if (function_exists('wicket_assign_role')) {
                            wicket_assign_role($personUuid, $role, $orgUuid);
                        }
                    }
                }
            }

            // Get person data for response
            $person_data = $this->reader->getPersonById($personUuid);

            return [
                'success' => true,
                'first_name' => $person_data['data']['attributes']['first_name'] ?? '',
                'last_name' => $person_data['data']['attributes']['last_name'] ?? '',
                'relationship_type' => $relationshipType,
            ];
        } catch (\Exception $e) {
            return new \WP_Error('update_exception', 'Failed to update member relationship: ' . $e->getMessage());
        }
    }

    /**
     * Update member relationship description.
     *
     * @param string $personUuid  The person UUID
     * @param string $orgUuid     The organization UUID
     * @param string $description The relationship description
     * @return true|\WP_Error True on success, WP_Error on failure
     */
    public function updateMemberDescription($personUuid, $orgUuid, $description)
    {
        if (!function_exists('wicket_api_client')) {
            return new \WP_Error('api_unavailable', 'Wicket API client is not available.');
        }

        if (empty($personUuid) || empty($orgUuid)) {
            return new \WP_Error('invalid_params', 'Person UUID and organization UUID are required.');
        }

        try {
            $description = is_string($description) ? sanitize_textarea_field($description) : '';

            return $this->connectionService()->updateConnectionDescription($personUuid, $orgUuid, $description);
        } catch (\Exception $e) {
            return new \WP_Error('update_exception', 'Failed to update member description: ' . $e->getMessage());
        }
    }

    /**
     * Add a member to an organization.
     *
     * Centralizes common validation, delegates mode-specific work to the
     * active strategy, and invalidates membership read caches on success.
     *
     * @param string $org_id      The organization ID.
     * @param array  $member_data Data for the new member.
     * @param array  $context     Additional context for the operation.
     * @return array|\WP_Error
     */
    public function addMember($org_id, $member_data, $context = [])
    {
        if (!function_exists('wicket_api_client')) {
            return new \WP_Error('api_unavailable', 'Wicket API client is not available.');
        }

        $result = $this->getStrategy()->addMember($org_id, $member_data, $context);

        // Invalidate read caches on success so the roster reflects the change immediately.
        if (!is_wp_error($result) && is_array($result)) {
            $membership_uuid = $context['membership_uuid'] ?? '';
            if ($membership_uuid !== '') {
                $this->reader->clearMembersCache($membership_uuid);
            }
        }

        return $result;
    }

    /**
     * Remove a member from an organization.
     *
     * Centralizes common validation, delegates mode-specific work to the
     * active strategy, and invalidates membership read caches on success.
     *
     * @param string $org_id      The organization ID.
     * @param string $person_uuid The UUID of the person to remove.
     * @param array  $context     Additional context for the operation.
     * @return array|\WP_Error
     */
    public function removeMember($org_id, $person_uuid, $context = [])
    {
        if (!function_exists('wicket_api_client')) {
            return new \WP_Error('api_unavailable', 'Wicket API client is not available.');
        }

        $result = $this->getStrategy()->removeMember($org_id, $person_uuid, $context);

        // Invalidate read caches on success so the roster reflects the change immediately.
        if (!is_wp_error($result) && is_array($result)) {
            $membership_uuid = $context['membership_uuid'] ?? '';
            if ($membership_uuid !== '') {
                $this->reader->clearMembersCache($membership_uuid);
            }
        }

        return $result;
    }
}
