<?php

/**
 * Groups Strategy for Roster Management.
 */

namespace WicketORM\Services\Strategies;

use WicketORM\Services\ConfigService;
use WicketORM\Services\ConnectionService;
use WicketORM\Services\GroupService;
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
 * Implements the groups mode for roster management.
 */
class GroupsStrategy implements RosterManagementStrategy
{
    /**
     * @var ConnectionService|null
     */
    private $connectionService = null;

    /**
     * @var PersonService|null
     */
    private $personService = null;

    /**
     * @var NotificationService|null
     */
    private $notificationService = null;

    /**
     * @var OrganizationService|null
     */
    private $organizationService = null;

    /**
     * @var GroupService|null
     */
    private $groupService = null;

    /**
     * @var PermissionService|null
     */
    private $permissionService = null;

    /**
     * @var \WC_Logger|null
     */
    private $logger = null;

    /**
     * @var TouchpointService|null
     */
    private $touchpointService = null;

    /**
     * @var MembershipService|null
     */
    private $membershipService = null;

    /**
     * @var ConfigService|null
     */
    private $configService = null;

    public function addMember($org_id, $member_data, $context = [])
    {
        $logger = $this->getLogger();
        $log_context = [
            'source' => 'wicket-orgman',
            'strategy' => 'groups',
            'org_id' => $org_id,
            'member_email' => $member_data['email'] ?? null,
            'group_uuid' => $context['group_uuid'] ?? null,
        ];

        try {
            $logger->info('Groups strategy add_member invoked', $log_context);

            if (empty($context['group_uuid'])) {
                $logger->error('Groups strategy missing group_uuid', $log_context);

                return new \WP_Error('missing_group_uuid', 'Group UUID is required for this operation.');
            }

            $group_uuid = $context['group_uuid'];
            $role_slug = sanitize_key((string) ($context['role'] ?? $context['roster_role'] ?? 'member'));
            $roster_roles = $this->groupService()->getRosterRoles();
            if (!in_array($role_slug, $roster_roles, true)) {
                $logger->warning('Groups strategy invalid roster role', array_merge($log_context, [
                    'role' => $role_slug,
                ]));

                return new \WP_Error('invalid_role', 'Invalid roster role for group membership.');
            }

            $current_person = wp_get_current_user();
            $manager_uuid = $current_person ? (string) $current_person->user_login : '';
            $manager_access = $this->groupService()->canManageGroup($group_uuid, $manager_uuid);
            if (empty($manager_access['allowed'])) {
                $logger->warning('Groups strategy access denied', array_merge($log_context, [
                    'manager_uuid' => $manager_uuid,
                ]));

                return new \WP_Error('no_group_access', 'You do not have permission to manage this group.');
            }

            $org_identifier = (string) ($manager_access['org_identifier'] ?? '');
            $org_uuid = (string) ($manager_access['org_uuid'] ?? $org_id);

            // Enforce organization-level seat availability when org membership exists.
            if (!empty($org_uuid)) {
                $logger->debug('Groups strategy checking seat availability', array_merge($log_context, [
                    'org_uuid' => $org_uuid,
                ]));
                $seat_result = $this->ensureMembershipSeatAvailability($org_uuid, $log_context);
                if (is_wp_error($seat_result)) {
                    $logger->warning('Groups strategy add_member blocked: seat limit', array_merge($log_context, [
                        'error' => $seat_result->get_error_message(),
                    ]));

                    return $seat_result;
                }
            }

            $person_uuid = $this->personService()->createOrGetPerson(
                $member_data['first_name'],
                $member_data['last_name'],
                $member_data['email'],
                []
            );

            if (is_wp_error($person_uuid)) {
                $logger->error('Groups strategy failed to create/get person', array_merge($log_context, [
                    'error' => $person_uuid->get_error_message(),
                ]));

                return $person_uuid;
            }

            $log_context['person_uuid'] = $person_uuid;
            $logger->debug('Groups strategy resolved person', $log_context);

            $existing_group_member_id = $this->groupService()->findGroupMemberId(
                $group_uuid,
                $person_uuid,
                $org_identifier,
                [$role_slug],
                $org_uuid
            );
            if ($existing_group_member_id !== '') {
                $logger->info('Groups strategy duplicate group member blocked', array_merge($log_context, [
                    'role' => $role_slug,
                    'existing_group_member_id' => $existing_group_member_id,
                ]));

                return new \WP_Error('group_member_exists', 'This member already has this role in the group.');
            }

            if (!empty($org_uuid)) {
                $has_relationship = $this->connectionService()->personHasRelationship($person_uuid, $org_uuid);
                $config = $this->configService()->getFullConfig();
                if (is_wp_error($has_relationship) || !$has_relationship) {
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

                    $logger->debug('Groups strategy creating org connection', $log_context);
                    $connection_payload = $this->connectionService()->buildConnectionPayload(
                        $person_uuid,
                        $org_uuid,
                        'person_to_organization',
                        $relationship_type,
                        $relationship_description
                    );
                    $connection_result = $this->connectionService()->createConnection($connection_payload);

                    if (is_wp_error($connection_result)) {
                        $logger->error('Groups strategy failed to create connection', array_merge($log_context, [
                            'error' => $connection_result->get_error_message(),
                        ]));

                        return new \WP_Error('connection_failed', $connection_result->get_error_message() ?? 'Failed to create organization connection.');
                    }
                }
            }

            $orgman_config = $this->configService()->getFullConfig();
            $groups_config = is_array($orgman_config['groups'] ?? null) ? $orgman_config['groups'] : [];
            $group_roles = is_array($groups_config['roles'] ?? null) ? $groups_config['roles'] : [];
            $seat_limited_roles = is_array($group_roles['seat_limited'] ?? null)
                ? $group_roles['seat_limited']
                : (
                    is_array($groups_config['seat_limited_roles'] ?? null)
                        ? $groups_config['seat_limited_roles']
                        : [$group_roles['member'] ?? ($groups_config['member_role'] ?? 'member')]
                );

            if (in_array($role_slug, $seat_limited_roles, true)) {
                $existing_members = $this->groupService()->getGroupMembers($group_uuid, $org_identifier, [
                    'page' => 1,
                    'size' => 50,
                    'query' => '',
                    'org_uuid' => $org_uuid,
                ]);
                foreach ($existing_members['members'] ?? [] as $member) {
                    if (sanitize_key((string) ($member['role'] ?? '')) === $role_slug) {
                        $logger->info('Groups strategy seat already occupied', array_merge($log_context, [
                            'role' => $role_slug,
                        ]));

                        return new \WP_Error('seat_unavailable', "This group already has a person '{$role_slug}' for your organization.");
                    }
                }
            }

            $logger->debug('Groups strategy adding member to group', array_merge($log_context, [
                'role' => $role_slug,
            ]));
            $custom_data_source = '' !== $org_identifier ? $org_identifier : $org_uuid;
            $custom_data_field = $this->groupService()->buildCustomDataField($custom_data_source, $role_slug);
            $group_member_result = $this->groupService()->createGroupMember($person_uuid, $group_uuid, $role_slug, $custom_data_field);
            $logger->debug('Groups strategy createGroupMember returned', array_merge($log_context, [
                'is_wp_error' => is_wp_error($group_member_result),
            ]));
            if (is_wp_error($group_member_result)) {
                return $group_member_result;
            }

            $group_details = function_exists('wicket_get_group') ? wicket_get_group($group_uuid) : null;
            $group_name = $group_details['data']['attributes']['name'] ?? 'Unknown Group';

            $logger->debug('Groups strategy sending notification', array_merge($log_context, [
                'group_name' => $group_name,
            ]));
            // NOTE: On local/dev hosts this often fails because SMTP is not configured
            // (expected outside staging/production). Keep non-blocking.
            $notification_result = $this->notificationService()->emailToPersonOnGroupAssignment($person_uuid, [
                'person_email'      => $member_data['email'],
                'notification_type' => 'group_assignment',
                'org_id'            => $org_uuid ?: $org_id,
                'group_name'        => $group_name,
            ]);

            if (is_wp_error($notification_result)) {
                $logger->error('Groups strategy email notification failed', array_merge($log_context, [
                    'error' => $notification_result->get_error_message(),
                ]));
            } else {
                $logger->info('Groups strategy email notification sent', array_merge($log_context, [
                    'group_name' => $group_name,
                ]));
            }

            if ($this->touchpointService()->isAvailable()) {
                try {
                    $touchpoint_context = array_merge($context, [
                        'strategy' => 'groups',
                    ]);
                    $this->touchpointService()->logMemberAdded($person_uuid, $org_uuid ?: $org_id, $member_data, $touchpoint_context);
                    $logger->debug('Groups strategy touchpoint logged for member addition', $log_context);
                } catch (\Throwable $e) {
                    $logger->error('Groups strategy touchpoint write failed', array_merge($log_context, [
                        'error' => $e->getMessage(),
                    ]));
                }
            }

            $logger->info('Groups strategy member addition complete', $log_context);

            return [
                'status' => 'success',
                'message' => 'Member added to group successfully.',
                'person_uuid' => $person_uuid,
            ];

        } catch (\Exception $e) {
            $logger->error('Groups strategy add_member exception', array_merge($log_context, [
                'exception' => $e->getMessage(),
            ]));

            return new \WP_Error('add_group_member_exception', $e->getMessage());
        }
    }

    public function removeMember($org_id, $person_uuid, $context = [])
    {
        $logger = $this->getLogger();
        $log_context = [
            'source' => 'wicket-orgman',
            'strategy' => 'groups',
            'org_id' => $org_id,
            'person_uuid' => $person_uuid,
            'group_uuid' => $context['group_uuid'] ?? null,
        ];

        try {
            $logger->info('Groups strategy remove_member invoked', $log_context);

            if (empty($context['group_uuid'])) {
                $logger->error('Groups strategy remove_member missing group_uuid', $log_context);

                return new \WP_Error('missing_group_uuid', 'Group UUID is required for this operation.');
            }

            $group_uuid = $context['group_uuid'];
            $role_slug = sanitize_key((string) ($context['role'] ?? $context['roster_role'] ?? ''));

            $current_person = wp_get_current_user();
            $manager_uuid = $current_person ? (string) $current_person->user_login : '';
            $manager_access = $this->groupService()->canManageGroup($group_uuid, $manager_uuid);
            if (empty($manager_access['allowed'])) {
                $logger->warning('Groups strategy remove_member access denied', array_merge($log_context, [
                    'manager_uuid' => $manager_uuid,
                ]));

                return new \WP_Error('no_group_access', 'You do not have permission to manage this group.');
            }

            $org_identifier = (string) ($manager_access['org_identifier'] ?? '');
            $org_uuid = (string) ($manager_access['org_uuid'] ?? $org_id);

            $orgman_config = $this->configService()->getFullConfig();
            $access_permissions = is_array($orgman_config['access']['permissions'] ?? null) ? $orgman_config['access']['permissions'] : [];
            $prevent_owner_removal = (bool) ($access_permissions['prevent_owner_removal'] ?? false);
            $owner_must_have_membership_owner = (bool) ($access_permissions['owner_removal_requires_membership_owner_role'] ?? false);

            if ($prevent_owner_removal && !empty($org_uuid)) {
                $org_owner = $this->organizationService()->getOrganizationOwner($org_uuid);
                $is_org_owner = !is_wp_error($org_owner)
                    && $org_owner
                    && isset($org_owner->uuid)
                    && (string) $org_owner->uuid === (string) $person_uuid;

                if ($is_org_owner) {
                    $owner_role_match = true;
                    if ($owner_must_have_membership_owner) {
                        $current_roles = $this->permissionService()->getPersonCurrentRolesByOrgId($person_uuid, $org_uuid);
                        $owner_role_match = is_array($current_roles) && in_array('membership_owner', $current_roles, true);
                    }

                    if ($owner_role_match) {
                        $logger->warning('Groups strategy attempted to remove organization owner', $log_context);

                        return new \WP_Error('owner_removal_forbidden', 'The organization owner (Primary Member) cannot be removed.');
                    }
                }
            }

            $groups_config = is_array($orgman_config['groups'] ?? null) ? $orgman_config['groups'] : [];
            $group_roles = is_array($groups_config['roles'] ?? null) ? $groups_config['roles'] : [];
            $manage_roles = is_array($group_roles['management'] ?? null) ? $group_roles['management'] : [];

            $group_member_id = (string) ($context['group_member_id'] ?? '');
            if ('' === $group_member_id) {
                $group_member_id = $this->groupService()->findGroupMemberId($group_uuid, $person_uuid, $org_identifier, [], $org_uuid);
            }
            if ('' === $group_member_id) {
                $logger->error('Groups strategy could not locate group member', $log_context);

                return new \WP_Error('group_member_not_found', 'Could not find the person in the specified group.');
            }

            // Verify the role of the member we are about to remove if role_slug was not provided or to be extra safe.
            if (empty($role_slug) || in_array($role_slug, $manage_roles, true)) {
                if (function_exists('wicket_api_client')) {
                    try {
                        $member_raw = wicket_api_client()->get('group_members/' . rawurlencode($group_member_id));
                        $actual_role = sanitize_key((string) ($member_raw['data']['attributes']['type'] ?? ''));
                        if (in_array($actual_role, $manage_roles, true)) {
                            $logger->warning('Groups strategy attempted to remove managing role', array_merge($log_context, [
                                'role' => $actual_role,
                                'group_member_id' => $group_member_id,
                            ]));

                            return new \WP_Error('role_removal_forbidden', 'Managing roles cannot be removed.');
                        }
                    } catch (\Throwable $e) {
                        // If we can't verify, we should probably be cautious if we don't know the role.
                        if (empty($role_slug)) {
                            $logger->error('Groups strategy could not verify role before removal', $log_context);

                            return new \WP_Error('role_verification_failed', 'Could not verify member role before removal.');
                        }
                    }
                }
            }

            if ($role_slug && in_array($role_slug, $manage_roles, true)) {
                $logger->warning('Groups strategy attempted to remove managing role', array_merge($log_context, [
                    'role' => $role_slug,
                ]));

                return new \WP_Error('role_removal_forbidden', 'Managing roles cannot be removed.');
            }

            $remove_result = $this->groupService()->removeGroupMember($group_member_id);
            if (is_wp_error($remove_result)) {
                $logger->error('Groups strategy failed to remove group member', array_merge($log_context, [
                    'error' => $remove_result->get_error_message(),
                ]));

                return $remove_result;
            }

            if ($this->touchpointService()->isAvailable()) {
                try {
                    $touchpoint_context = array_merge($context, [
                        'strategy' => 'groups',
                    ]);
                    $this->touchpointService()->logMemberRemoved($person_uuid, $org_uuid ?: $org_id, $touchpoint_context);
                    $logger->debug('Groups strategy touchpoint logged for member removal', $log_context);
                } catch (\Throwable $e) {
                    $logger->error('Groups strategy removal touchpoint write failed', array_merge($log_context, [
                        'error' => $e->getMessage(),
                    ]));
                }
            }

            $logger->info('Groups strategy remove_member complete', $log_context);

            return ['status' => 'success', 'message' => 'Group member removed successfully.'];

        } catch (\Exception $e) {
            $logger->error('Groups strategy remove_member exception', array_merge($log_context, [
                'exception' => $e->getMessage(),
            ]));

            return new \WP_Error('remove_group_member_exception', $e->getMessage());
        }
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
     * Lazily instantiate GroupService.
     *
     * @return GroupService
     */
    private function groupService(): GroupService
    {
        if (!isset($this->groupService)) {
            $this->groupService = new GroupService();
        }

        return $this->groupService;
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
     * Ensure the target organization membership still has seat capacity.
     *
     * @param string $org_uuid
     * @param array  $log_context
     * @return true|\WP_Error
     */
    private function ensureMembershipSeatAvailability(string $org_uuid, array $log_context)
    {
        $membership_uuid = $this->membershipService()->getMembershipForOrganization($org_uuid);
        if (empty($membership_uuid)) {
            return true;
        }

        $membership_data = $this->membershipService()->getOrgMembershipData($membership_uuid);
        if (!is_array($membership_data) || empty($membership_data['data'])) {
            return true;
        }

        $max_seats = $this->membershipService()->getEffectiveMaxAssignments($membership_data);
        $active_seats = (int) ($membership_data['data']['attributes']['active_assignments_count'] ?? 0);

        if ($max_seats !== null && $active_seats >= (int) $max_seats) {
            $this->getLogger()->warning('Groups add blocked by seat limit', array_merge($log_context, [
                'max_seats' => $max_seats,
                'active_seats' => $active_seats,
            ]));

            return new \WP_Error('seat_limit_reached', 'No seats available for this organization.');
        }

        return true;
    }

    /**
     * Retrieve shared logger.
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
