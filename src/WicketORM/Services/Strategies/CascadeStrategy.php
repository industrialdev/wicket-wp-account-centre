<?php

/**
 * Cascade Strategy for Roster Management.
 */

namespace WicketORM\Services\Strategies;

use WicketORM\Services\ConfigService;
use WicketORM\Services\ConnectionService;
use WicketORM\Services\MembershipService;
use WicketORM\Services\NotificationService;
use WicketORM\Services\OrganizationService;
use WicketORM\Services\PermissionService;
use WicketORM\Services\PersonService;
use WicketORM\Services\TouchpointService;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Implements the cascade mode for roster management.
 */
class CascadeStrategy implements RosterManagementStrategy
{
    /**
     * @var PermissionService|null
     */
    private $permissionService = null;

    /**
     * @var ConnectionService|null
     */
    private $connectionService = null;

    /**
     * @var OrganizationService|null
     */
    private $organizationService = null;

    /**
     * @var PersonService|null
     */
    private $personService = null;

    /**
     * @var MembershipService|null
     */
    private $membershipService = null;

    /**
     * @var NotificationService|null
     */
    private $notificationService = null;

    /**
     * @var ConfigService|null
     */
    private $configService = null;

    /**
     * @var \WC_Logger|null
     */
    private $logger = null;

    /**
     * @var TouchpointService|null
     */
    private $touchpointService = null;

    /**
     * Get person's current roles for a specific organization.
     *
     * @param string $person_uuid The UUID of the person
     * @param string $org_id The ID of the organization
     * @return array An array of current role slugs
     */
    private function getPersonCurrentRolesByOrgId($person_uuid, $org_id): array
    {
        if (!isset($this->permissionService)) {
            $this->permissionService = new PermissionService();
        }

        return $this->permissionService->getPersonCurrentRolesByOrgId($person_uuid, $org_id);
    }

    /**
     * Assign an org role only when it is not already present.
     *
     * @param string $person_uuid
     * @param string $role
     * @param string $org_id
     * @return true|\WP_Error
     */
    private function assignRoleIfMissing(string $person_uuid, string $role, string $org_id)
    {
        $role = sanitize_key($role);
        if ('' === $role) {
            return new \WP_Error('invalid_role', 'Role is required.');
        }

        if (!function_exists('wicket_assign_role')) {
            return new \WP_Error('missing_dependency', 'Role assignment helper is unavailable.');
        }

        $current_roles = $this->getPersonCurrentRolesByOrgId($person_uuid, $org_id);
        if (in_array($role, $current_roles, true)) {
            return true;
        }

        try {
            $result = wicket_assign_role($person_uuid, $role, $org_id);
        } catch (\Throwable $e) {
            return new \WP_Error('role_assignment_failed', $e->getMessage());
        }

        if (false === $result) {
            return new \WP_Error('role_assignment_failed', sprintf('Failed assigning role %s.', $role));
        }

        return true;
    }

    /**
     * Lazily instantiate ConnectionService.
     *
     * @return ConnectionService
     */
    private function connectionService(): ConnectionService
    {
        if (!isset($this->connectionService)) {
            $this->connectionService = new ConnectionService();
        }

        return $this->connectionService;
    }

    /**
     * Lazily instantiate OrganizationService.
     *
     * @return OrganizationService
     */
    private function organizationService(): OrganizationService
    {
        if (!isset($this->organizationService)) {
            $this->organizationService = new OrganizationService();
        }

        return $this->organizationService;
    }

    /**
     * Lazily instantiate PersonService.
     *
     * @return PersonService
     */
    private function personService(): PersonService
    {
        if (!isset($this->personService)) {
            $this->personService = new PersonService();
        }

        return $this->personService;
    }

    /**
     * Lazily instantiate MembershipService.
     *
     * @return MembershipService
     */
    private function membershipService(): MembershipService
    {
        if (!isset($this->membershipService)) {
            $this->membershipService = new MembershipService();
        }

        return $this->membershipService;
    }

    /**
     * Lazily instantiate TouchpointService.
     *
     * @return TouchpointService
     */
    private function touchpointService(): TouchpointService
    {
        if (!isset($this->touchpointService)) {
            $this->touchpointService = new TouchpointService();
        }

        return $this->touchpointService;
    }

    /**
     * Add a member to an organization using the cascade method.
     *
     * @param string $org_id The organization ID.
     * @param array  $member_data Data for the new member.
     * @return array|\WP_Error
     */
    public function addMember($org_id, $member_data, $context = [])
    {
        $logger = $this->getLogger();
        $log_context = [
            'source' => 'wicket-orgman',
            'strategy' => 'cascade',
            'org_id' => $org_id,
            'member_email' => $member_data['email'] ?? null,
        ];

        try {
            $logger->info('[OrgMan] Cascade strategy add_member invoked', $log_context);

            $required_functions = [
                'wicket_assign_role',
            ];
            foreach ($required_functions as $func) {
                if (!function_exists($func)) {
                    $logger->error('[OrgMan] Missing legacy dependency for cascade add_member', array_merge($log_context, [
                        'function' => $func,
                    ]));

                    return new \WP_Error('missing_function', "Legacy function {$func} not found.");
                }
            }

            $person_uuid = $this->personService()->createOrGetPerson(
                $member_data['first_name'],
                $member_data['last_name'],
                $member_data['email'],
                []
            );

            if (is_wp_error($person_uuid)) {
                $logger->error('[OrgMan] Cascade strategy failed to create person', array_merge($log_context, [
                    'error' => $person_uuid->get_error_message(),
                ]));

                return $person_uuid;
            }
            $log_context['person_uuid'] = $person_uuid;
            $logger->debug('[OrgMan] Cascade strategy person resolved', $log_context);

            $membership_uuid = $this->membershipService()->getCurrentPersonMembershipsByOrganization($org_id);

            if (is_wp_error($membership_uuid)) {
                $logger->error('[OrgMan] Cascade strategy failed to locate membership uuid', array_merge($log_context, [
                    'error' => $membership_uuid->get_error_message(),
                ]));

                return $membership_uuid;
            }

            if (!$membership_uuid) {
                $logger->error('[OrgMan] Cascade strategy missing corporate membership for org', $log_context);

                return new \WP_Error('no_membership', 'Could not find a valid corporate membership for this organization.');
            }
            $log_context['membership_uuid'] = $membership_uuid;

            $has_membership = $this->connectionService()->personHasMembership($person_uuid, $membership_uuid);
            $config = $this->configService()->getFullConfig();
            if (is_wp_error($has_membership)) {
                $logger->error('[OrgMan] Cascade membership lookup failed', array_merge($log_context, [
                    'error' => $has_membership->get_error_message(),
                ]));

                return $has_membership;
            }

            if (!$has_membership) {
                $seat_check = $this->ensureSeatAvailability($membership_uuid, $log_context);
                if (is_wp_error($seat_check)) {
                    return $seat_check;
                }
                $repaired_stale_relationship = false;

                [$relationship_type, $relationship_description] = $this->resolveRelationshipInputs(
                    $config,
                    $context,
                    $member_data
                );

                $has_relationship = $this->connectionService()->personHasRelationship($person_uuid, $org_id);
                if (is_wp_error($has_relationship)) {
                    return $has_relationship;
                }

                $repair_stale_relationship = (bool) ($config['member_management']['addition']['repair_stale_relationship_without_membership'] ?? true);
                $protected_types = (array) ($config['member_management']['addition']['protected_relationship_types'] ?? []);
                $logger->debug('[OrgMan] Cascade stale relationship repair decision', array_merge($log_context, [
                    'has_membership' => false,
                    'has_relationship' => (bool) $has_relationship,
                    'repair_stale_relationship' => $repair_stale_relationship,
                    'protected_relationship_types' => array_values($protected_types),
                    'resolved_relationship_type_for_new_connection' => $relationship_type,
                ]));
                if ($has_relationship && $repair_stale_relationship) {
                    $logger->info('[OrgMan] Cascade stale relationship detected without membership', array_merge($log_context, [
                        'repair_enabled' => true,
                    ]));

                    $end_result = $this->connectionService()->endActivePersonOrganizationConnections($person_uuid, $org_id, $protected_types);
                    if (is_wp_error($end_result)) {
                        $logger->error('[OrgMan] Cascade stale relationship end-date failed', array_merge($log_context, [
                            'error' => $end_result->get_error_message(),
                        ]));

                        return $end_result;
                    }

                    $logger->info('[OrgMan] Cascade stale relationship end-dated', array_merge($log_context, [
                        'ended_connection_count' => (int) ($end_result['count'] ?? 0),
                        'ended_connection_ids' => $end_result['connection_ids'] ?? [],
                    ]));

                    $has_relationship = false;
                    $repaired_stale_relationship = true;
                }

                if (!$has_relationship) {
                    // Create person-to-organization connection
                    $connection_payload = $this->connectionService()->buildConnectionPayload(
                        $person_uuid,
                        $org_id,
                        'person_to_organization',
                        $relationship_type,
                        $relationship_description
                    );
                    $connection_response = $this->connectionService()->createConnection($connection_payload);

                    if (is_wp_error($connection_response)) {
                        $logger->error('[OrgMan] Cascade strategy failed to create connection', array_merge($log_context, [
                            'error' => $connection_response->get_error_message(),
                        ]));

                        return new \WP_Error('connection_failed', $connection_response->get_error_message() ?? 'Failed to create organization connection.');
                    }
                    $logger->debug('[OrgMan] Cascade strategy created org connection', $log_context);
                    if ($repaired_stale_relationship) {
                        $logger->info('[OrgMan] Cascade stale relationship recreated', array_merge($log_context, [
                            'relationship_type' => $relationship_type,
                            'relationship_description' => $relationship_description,
                        ]));
                    }
                }
            } else {
                $logger->info('[OrgMan] Cascade strategy rejecting duplicate member', $log_context);
                $email = $member_data['email'] ?? '';

                return new \WP_Error(
                    'member_already_exists',
                    $email !== ''
                        ? sprintf('A member with email %s already exists in this organization.', $email)
                        : 'This person is already a member of this organization.'
                );
            }

            // Get configuration for member addition settings
            $base_member_role = $config['member_management']['addition']['base_member_role'] ?? 'member';
            $auto_assign_roles = $config['member_management']['addition']['auto_assign_roles'] ?? [];

            // Assign base member role
            $base_role_result = $this->assignRoleIfMissing($person_uuid, $base_member_role, $org_id);
            if (is_wp_error($base_role_result)) {
                $logger->error('[OrgMan] Cascade base role assignment failed', array_merge($log_context, [
                    'role' => $base_member_role,
                    'error' => $base_role_result->get_error_message(),
                ]));

                return $base_role_result;
            }
            $logger->debug('[OrgMan] Cascade base role assigned', array_merge($log_context, [
                'role' => $base_member_role,
            ]));

            // Assign auto-roles from config
            foreach ($auto_assign_roles as $role) {
                $role_result = $this->assignRoleIfMissing($person_uuid, $role, $org_id);
                if (is_wp_error($role_result)) {
                    $logger->error('[OrgMan] Cascade auto-role assignment failed', array_merge($log_context, [
                        'role' => $role,
                        'error' => $role_result->get_error_message(),
                    ]));

                    return $role_result;
                }
            }
            if (!empty($auto_assign_roles)) {
                $logger->debug('[OrgMan] Cascade auto roles assigned', array_merge($log_context, [
                    'roles' => $auto_assign_roles,
                ]));
            }

            // Handle additional roles from context (e.g., org_editor, membership_manager)
            $additional_roles = $context['roles'] ?? $member_data['roles'] ?? [];

            // Check for relationship-based permissions
            if (!empty($config['access']['permissions']['relationship_grants']['enabled'])) {
                $relationship_type = $context['relationship_type'] ?? $member_data['relationship_type'] ?? '';
                $relationship_roles_map = $config['access']['permissions']['relationship_grants']['roles_by_type'] ?? [];

                if ($relationship_type && isset($relationship_roles_map[$relationship_type])) {
                    $mapped_roles = $relationship_roles_map[$relationship_type];
                    if (is_array($mapped_roles)) {
                        $additional_roles = array_unique(array_merge($additional_roles, $mapped_roles));
                    }
                }
            }

            // Filter out membership_owner if configured to prevent assignment
            if (!empty($config['access']['permissions']['prevent_owner_assignment'])) {
                $additional_roles = array_values(array_diff($additional_roles, ['membership_owner']));
            }

            if (!empty($additional_roles)) {
                foreach ($additional_roles as $role) {
                    $role_result = $this->assignRoleIfMissing($person_uuid, $role, $org_id);
                    if (is_wp_error($role_result)) {
                        $logger->error('[OrgMan] Cascade additional role assignment failed', array_merge($log_context, [
                            'role' => $role,
                            'error' => $role_result->get_error_message(),
                        ]));

                        return $role_result;
                    }
                }
            }

            $notification_result = $this->notificationService()->sendPersonToOrgAssignmentEmail($person_uuid, $org_id);
            if (is_wp_error($notification_result)) {
                $logger->error('[OrgMan] Cascade notification email failed', array_merge($log_context, [
                    'error' => $notification_result->get_error_message(),
                ]));
            } else {
                $logger->info('[OrgMan] Cascade notification email sent', $log_context);
            }

            if ($this->touchpointService()->isAvailable()) {
                try {
                    $touchpoint_context = array_merge($context, [
                        'strategy' => 'cascade',
                    ]);
                    $this->touchpointService()->logMemberAdded($person_uuid, $org_id, $member_data, $touchpoint_context);
                    $logger->debug('[OrgMan] Cascade touchpoint logged for member addition', $log_context);
                } catch (\Throwable $e) {
                    $logger->error('[OrgMan] Cascade touchpoint write failed', array_merge($log_context, [
                        'error' => $e->getMessage(),
                    ]));
                }
            }

            $logger->info('[OrgMan] Cascade strategy member addition completed', $log_context);

            return [
                'status' => 'success',
                'message' => 'Member added successfully.',
                'person_uuid' => $person_uuid,
            ];

        } catch (\Exception $e) {
            $logger->error('[OrgMan] Cascade strategy add_member exception', array_merge($log_context, [
                'exception' => $e->getMessage(),
            ]));

            return new \WP_Error('add_member_exception', $e->getMessage());
        }
    }

    public function removeMember($org_id, $person_uuid, $context = [])
    {
        $logger = $this->getLogger();
        $log_context = [
            'source' => 'wicket-orgman',
            'strategy' => 'cascade',
            'org_id' => $org_id,
            'person_uuid' => $person_uuid,
        ];

        try {
            $person_membership_id = sanitize_text_field((string) ($context['person_membership_id'] ?? ''));

            if ('' === $person_membership_id) {
                return new \WP_Error('missing_person_membership_id', 'Person membership ID is required to remove a member.');
            }

            $config = $this->configService()->getFullConfig();
            $access_permissions = is_array($config['access']['permissions'] ?? null) ? $config['access']['permissions'] : [];
            $prevent_owner_removal = (bool) ($access_permissions['prevent_owner_removal'] ?? false);
            $owner_must_have_membership_owner = (bool) ($access_permissions['owner_removal_requires_membership_owner_role'] ?? false);

            if ($prevent_owner_removal && !empty($org_id)) {
                $org_owner = $this->organizationService()->getOrganizationOwner($org_id);
                $owner_uuid = '';
                if (is_array($org_owner)) {
                    $owner_uuid = $org_owner['id'] ?? ($org_owner['uuid'] ?? ($org_owner['data']['id'] ?? ''));
                } elseif (is_object($org_owner)) {
                    $owner_uuid = $org_owner->id ?? ($org_owner->uuid ?? '');
                }

                $is_org_owner = !is_wp_error($org_owner)
                    && $org_owner
                    && !empty($owner_uuid)
                    && (string) $owner_uuid === (string) $person_uuid;

                if ($is_org_owner) {
                    $owner_role_match = true;
                    if ($owner_must_have_membership_owner) {
                        $current_roles = $this->permissionService()->getPersonCurrentRolesByOrgId($person_uuid, $org_id);
                        $owner_role_match = is_array($current_roles) && in_array('membership_owner', $current_roles, true);
                    }

                    if ($owner_role_match) {
                        return new \WP_Error('owner_removal_forbidden', 'The organization owner (Primary Member) cannot be removed.');
                    }
                }
            }

            $remove_result = $this->membershipService()->endPersonMembershipToday($person_membership_id);
            if (is_wp_error($remove_result)) {
                $logger->error('[OrgMan] Cascade strategy failed to end person membership', array_merge($log_context, [
                    'error' => $remove_result->get_error_message(),
                ]));

                return $remove_result;
            }

            $roles_to_remove = $this->permissionService()->getPersonCurrentRolesByOrgId($person_uuid, $org_id);
            if (!empty($roles_to_remove)) {
                $roles_result = $this->permissionService()->removePersonRolesFromOrg($person_uuid, $roles_to_remove, $org_id);
                if (is_wp_error($roles_result)) {
                    return $roles_result;
                }
            }

            if ($this->touchpointService()->isAvailable()) {
                try {
                    $touchpoint_context = array_merge($context, [
                        'strategy' => 'cascade',
                    ]);
                    $this->touchpointService()->logMemberRemoved($person_uuid, $org_id, $touchpoint_context);
                    $logger->debug('[OrgMan] Cascade touchpoint logged for member removal', $log_context);
                } catch (\Throwable $e) {
                    $logger->error('[OrgMan] Cascade removal touchpoint write failed', array_merge($log_context, [
                        'error' => $e->getMessage(),
                    ]));
                }
            }

            $logger->info('[OrgMan] Cascade strategy removed member successfully', $log_context);

            return ['status' => 'success', 'message' => 'Member removed successfully.'];
        } catch (\Exception $e) {
            $logger->error('[OrgMan] Cascade strategy remove_member exception', array_merge($log_context, [
                'exception' => $e->getMessage(),
            ]));

            return new \WP_Error('remove_member_exception', $e->getMessage());
        }
    }

    /**
     * Ensure the target organization membership still has seat capacity.
     * Cascade strategy creates relationship only and lets downstream systems assign memberships.
     *
     * @param string $membership_uuid
     * @param array $log_context
     * @return true|\WP_Error
     */
    private function ensureSeatAvailability(string $membership_uuid, array $log_context)
    {
        $membership_data = $this->membershipService()->getOrgMembershipData($membership_uuid);
        if (!is_array($membership_data) || empty($membership_data['data'])) {
            return new \WP_Error('membership_data_missing', 'Membership details unavailable.');
        }

        $max_seats = $this->membershipService()->getEffectiveMaxAssignments($membership_data);
        $active_seats = (int) ($membership_data['data']['attributes']['active_assignments_count'] ?? 0);

        if ($max_seats !== null && $active_seats >= (int) $max_seats) {
            $this->getLogger()->warning('[OrgMan] Cascade add blocked by seat limit', array_merge($log_context, [
                'max_seats' => $max_seats,
                'active_seats' => $active_seats,
            ]));

            return new \WP_Error('seat_limit_reached', 'No seats available for this organization.');
        }

        return true;
    }

    /**
     * Resolve the relationship payload used for cascade connection creation.
     *
     * @param array $config
     * @param array $context
     * @param array $member_data
     * @return array{0:string,1:string}
     */
    private function resolveRelationshipInputs(array $config, array $context, array $member_data): array
    {
        $relationship_type = $context['relationship_type'] ?? $member_data['relationship_type'] ?? '';
        $relationship_type = is_string($relationship_type) ? sanitize_key($relationship_type) : '';
        $relationship_description = $context['relationship_description'] ?? $member_data['relationship_description'] ?? '';
        $relationship_description = is_string($relationship_description) ? sanitize_textarea_field($relationship_description) : '';
        $custom_types = $config['relationships']['labels']['custom'] ?? [];
        if ($relationship_type && !empty($custom_types) && !array_key_exists($relationship_type, $custom_types)) {
            $relationship_type = '';
        }
        if (empty($relationship_type)) {
            $relationship_type = \WicketORM\Helpers\RelationshipHelper::get_default_relationship_type();
        }

        return [$relationship_type, $relationship_description];
    }

    /**
     * Lazily instantiate NotificationService.
     *
     * @return NotificationService
     */
    private function notificationService(): NotificationService
    {
        if (!isset($this->notificationService)) {
            $this->notificationService = new NotificationService();
        }

        return $this->notificationService;
    }

    /**
     * Lazily instantiate ConfigService.
     *
     * @return ConfigService
     */
    private function configService(): ConfigService
    {
        if (!isset($this->configService)) {
            $this->configService = new ConfigService();
        }

        return $this->configService;
    }

    /**
     * Lazily instantiate PermissionService.
     *
     * @return PermissionService
     */
    private function permissionService(): PermissionService
    {
        if (!isset($this->permissionService)) {
            $this->permissionService = new PermissionService();
        }

        return $this->permissionService;
    }

    /**
     * Retrieve shared logger instance.
     *
     * @return \WC_Logger
     */
    private function getLogger()
    {
        if (null === $this->logger) {
            $this->logger = \Wicket()->log();
        }

        return $this->logger;
    }
}
