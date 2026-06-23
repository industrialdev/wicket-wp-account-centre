<?php

/**
 * Hypermedia partial for Remove Member modal processing.
 *
 * Renders the form (GET) and processes submissions (POST).
 */

use WicketORM\Services\ConfigService;
use WicketORM\Services\ConnectionService;

if (!defined('ABSPATH')) {
    exit;
}

// Handle POST submissions
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ('POST' === strtoupper($request_method)) {
    // Validate nonce.
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'wicket-orgman-remove-member')) {
        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderError(__('Invalid or missing security token. Please refresh and try again.', 'wicket-acc'), '#remove-member-messages', ['removeMemberSubmitting' => false, 'membersLoading' => false]);

        return;
    }

    $org_uuid = isset($_POST['org_uuid']) ? sanitize_text_field(wp_unslash($_POST['org_uuid'])) : '';
    $org_dom_suffix = isset($_POST['org_dom_suffix'])
        ? sanitize_html_class((string) wp_unslash($_POST['org_dom_suffix']))
        : sanitize_html_class($org_uuid ?: 'default');
    $membership_uuid = isset($_POST['membership_uuid']) ? sanitize_text_field(wp_unslash($_POST['membership_uuid'])) : '';
    $person_uuid = isset($_POST['person_uuid']) ? sanitize_text_field(wp_unslash($_POST['person_uuid'])) : '';
    $person_name = isset($_POST['person_name']) ? sanitize_text_field(wp_unslash($_POST['person_name'])) : '';
    $person_email = isset($_POST['person_email']) ? sanitize_email(wp_unslash($_POST['person_email'])) : '';
    $person_connection_ids = isset($_POST['connection_id']) ? sanitize_text_field(wp_unslash($_POST['connection_id'])) : '';
    $person_membership_id = isset($_POST['person_membership_id']) ? sanitize_text_field(wp_unslash($_POST['person_membership_id'])) : '';

    // Require org_uuid and person_uuid, but allow either connection_id OR person_membership_id
    if (empty($org_uuid) || empty($person_uuid) || (empty($person_connection_ids) && empty($person_membership_id))) {
        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderError(__('Organization UUID, person UUID, and connection IDs are required.', 'wicket-acc'), '#remove-member-messages', ['removeMemberSubmitting' => false, 'membersLoading' => false]);

        return;
    }

    // Check if user has permission to remove members
    if (!WicketORM\Helpers\PermissionHelper::can_remove_members($org_uuid)) {
        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderError(__('You do not have permission to remove members from this organization.', 'wicket-acc'), '#remove-member-messages', ['removeMemberSubmitting' => false, 'membersLoading' => false]);

        return;
    }

    // Handle comma-separated connection IDs (like legacy system)
    if (is_string($person_connection_ids) && strpos($person_connection_ids, ',') !== false) {
        $connection_ids = explode(',', $person_connection_ids);
    } else {
        $connection_ids = [$person_connection_ids];
    }
    $connection_ids = array_filter(array_map('trim', $connection_ids));

    try {
        $configService = new ConfigService();
        $roster_mode = (string) $configService->getRosterMode();
        $orgman_config = ConfigService::getConfig();
        $presentation_config = is_array($orgman_config['presentation'] ?? null) ? $orgman_config['presentation'] : [];
        $member_view_config = is_array($presentation_config['member_view'] ?? null) ? $presentation_config['member_view'] : [];
        $remove_auto_close_on_success = (bool) ($member_view_config['add_member_auto_close_on_success'] ?? false);
        $remove_auto_close_delay_seconds = max(0, (int) ($member_view_config['add_member_auto_close_delay_seconds'] ?? 7));
        $preserve_direct_relationship = (bool) ($orgman_config['member_management']['removal']['direct']['preserve_relationship'] ?? false);
        $membershipService = new WicketORM\Services\MembershipService();
        $permissionService = new WicketORM\Services\PermissionService();
        $organizationService = new WicketORM\Services\OrganizationService();

        if ($roster_mode === 'membership_cycle' && empty($membership_uuid)) {
            status_header(200);
            WicketORM\Helpers\DatastarSSE::renderError(
                __('Membership UUID is required for membership cycle removals.', 'wicket-acc'),
                '#remove-member-messages',
                ['removeMemberSubmitting' => false, 'membersLoading' => false]
            );

            return;
        }

        $cycle_config = is_array($orgman_config['membership']['cycle'] ?? null)
            ? $orgman_config['membership']['cycle']
            : [];
        $access_permissions = is_array($orgman_config['access']['permissions'] ?? null)
            ? $orgman_config['access']['permissions']
            : [];

        $prevent_owner_removal = ($roster_mode === 'membership_cycle')
            ? (bool) ($cycle_config['prevent_owner_removal'] ?? true)
            : (bool) ($access_permissions['prevent_owner_removal'] ?? false);
        $owner_must_have_membership_owner = (bool) ($access_permissions['owner_removal_requires_membership_owner_role'] ?? false);

        if ($prevent_owner_removal) {
            $org_owner = $organizationService->getOrganizationOwner($org_uuid);
            $is_org_owner = !is_wp_error($org_owner)
                && $org_owner
                && isset($org_owner->uuid)
                && (string) $org_owner->uuid === $person_uuid;

            if ($is_org_owner) {
                $owner_role_match = true;
                if ($owner_must_have_membership_owner) {
                    $current_roles = $permissionService->getPersonCurrentRolesByOrgId($person_uuid, $org_uuid);
                    $owner_role_match = is_array($current_roles) && in_array('membership_owner', $current_roles, true);
                }

                if ($owner_role_match) {
                    status_header(200);
                    WicketORM\Helpers\DatastarSSE::renderError(
                        __('The organization owner (Primary Member) cannot be removed.', 'wicket-acc'),
                        '#remove-member-messages',
                        ['removeMemberSubmitting' => false, 'membersLoading' => false]
                    );

                    return;
                }
            }
        }

        // Get person name for success message
        $member_name = 'Member';
        if (!empty($person_name)) {
            $member_name = $person_name;
        } elseif (function_exists('wicket_get_person_by_id')) {
            $person_data = wicket_get_person_by_id($person_uuid);
            if ($person_data) {
                // Handle both array and object formats
                $attributes = null;
                if (is_array($person_data) && !empty($person_data['data']['attributes'])) {
                    $attributes = $person_data['data']['attributes'];
                } elseif (is_object($person_data) && method_exists($person_data, 'data') && method_exists($person_data->data(), 'attributes')) {
                    $attributes = $person_data->data()->attributes();
                }

                if ($attributes) {
                    $first_name = $attributes['first_name'] ?? '';
                    $last_name = $attributes['last_name'] ?? '';
                    $member_name = trim($first_name . ' ' . $last_name);
                    if (empty($member_name)) {
                        $member_name = 'Member';
                    }
                }
            }
        }

        $removal_success = false;
        $connection_service = new ConnectionService();

        // 1. End all person memberships for this person/email in this organization membership
        if (!empty($membership_uuid) && !empty($person_uuid)) {
            try {
                $client = wicket_api_client();
                $filter_data = [
                    'filter' => [
                        'organization_membership_uuid_in' => [$membership_uuid],
                        'person_id_eq' => $person_uuid,
                    ],
                ];

                $response = $client->post('/person_memberships/query', ['json' => $filter_data]);

                if (!is_wp_error($response) && !empty($response['data'])) {
                    foreach ($response['data'] as $p_membership) {
                        $p_membership_id = $p_membership['id'] ?? null;
                        $p_membership_active = $p_membership['attributes']['active'] ?? false;

                        if ($p_membership_id && $p_membership_active) {
                            $result = $membershipService->endPersonMembershipToday($p_membership_id);
                            if (!is_wp_error($result)) {
                                $removal_success = true;
                            } else {
                                \Wicket()->log()->error('Failed to end extra membership', [
                                    'source' => 'wicket-orgman',
                                    'id' => $p_membership_id,
                                    'error' => $result->get_error_message(),
                                ]);
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                \Wicket()->log()->error('Membership query exception', [
                    'source' => 'wicket-orgman',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 2. Fallback or explicit removal of the provided person membership ID
        if (!empty($person_membership_id)) {
            $result = $membershipService->endPersonMembershipToday($person_membership_id);

            if (is_wp_error($result)) {
                \Wicket()->log()->error('Failed to end-date primary person membership', [
                    'source' => 'wicket-orgman',
                    'person_membership_id' => $person_membership_id,
                    'error' => $result->get_error_message(),
                ]);
            } else {
                $removal_success = true;
            }
        }

        if ($roster_mode === 'membership_cycle') {
            if (!$removal_success) {
                status_header(200);
                WicketORM\Helpers\DatastarSSE::renderError(
                    __('Failed to remove member. Person membership ID is required.', 'wicket-acc'),
                    '#remove-member-messages',
                    ['removeMemberSubmitting' => false, 'membersLoading' => false]
                );

                return;
            }

            if (!empty($membership_uuid)) {
                $orgman_instance = WicketORM\OrgMan::get_instance();
                $orgman_instance->clearMembersCache($membership_uuid);
            }

            $success_message = sprintf(
                esc_html__('Successfully removed %1$s from the organization.', 'wicket-acc'),
                '<strong>' . esc_html($member_name) . '</strong>'
            );
            $element_patches = WicketORM\Helpers\MemberListRefresh::buildOrgMembersListPatches(
                $org_uuid,
                $membership_uuid,
                $org_dom_suffix,
                1,
                ''
            );

            status_header(200);
            WicketORM\Helpers\DatastarSSE::renderSuccess($success_message, '#remove-member-messages', [
                'removeMemberSubmitting' => false,
                'removeMemberSuccess' => true,
                'membersLoading' => false,
                'autoCloseCountdown' => $remove_auto_close_on_success ? $remove_auto_close_delay_seconds : 0,
            ], 0, 'remove-countdown', $element_patches);

            return;
        }

        $should_end_relationships = $roster_mode !== 'direct' || !$preserve_direct_relationship;
        $use_direct_action_time_relationship_end = $roster_mode === 'direct' && !$preserve_direct_relationship;

        // 3. End ALL connections for this person to this organization
        if (!empty($person_uuid) && $should_end_relationships) {
            $connections = $connection_service->getPersonConnectionsById($person_uuid);
            if (!empty($connections['data'])) {
                foreach ($connections['data'] as $conn) {
                    $conn_org_id = $conn['relationships']['organization']['data']['id'] ?? null;
                    $conn_id = $conn['id'] ?? null;
                    $conn_active = $conn['attributes']['active'] ?? false;

                    if ($conn_org_id === $org_uuid && $conn_id && $conn_active) {
                        $result = $use_direct_action_time_relationship_end
                            ? $connection_service->endRelationshipAtActionTime($person_uuid, $conn_id, $org_uuid)
                            : $connection_service->endRelationshipToday($person_uuid, $conn_id, $org_uuid);
                        if (!is_wp_error($result)) {
                            $removal_success = true;
                        } else {
                            \Wicket()->log()->error('Failed to end extra connection', [
                                'source' => 'wicket-orgman',
                                'id' => $conn_id,
                                'error' => $result->get_error_message(),
                            ]);
                        }
                    }
                }
            }
        }

        // 4. Fallback or explicit removal of the provided connection IDs
        if (!empty($connection_ids) && $should_end_relationships) {
            foreach ($connection_ids as $connection_id) {
                if (empty($connection_id)) {
                    continue;
                }
                $result = $use_direct_action_time_relationship_end
                    ? $connection_service->endRelationshipAtActionTime($person_uuid, $connection_id, $org_uuid)
                    : $connection_service->endRelationshipToday($person_uuid, $connection_id, $org_uuid);

                if (is_wp_error($result)) {
                    \Wicket()->log()->error('Failed to end primary relationship', [
                        'source' => 'wicket-orgman',
                        'person_uuid' => $person_uuid,
                        'connection_id' => $connection_id,
                        'org_uuid' => $org_uuid,
                        'error' => $result->get_error_message(),
                    ]);
                } else {
                    $removal_success = true;
                }
            }
        }

        // 2. Remove all org-scoped roles
        $roles_to_remove = $permissionService->getPersonCurrentRolesByOrgId($person_uuid, $org_uuid);
        if (!empty($roles_to_remove)) {
            $role_removal_result = $permissionService->removePersonRolesFromOrg($person_uuid, $roles_to_remove, $org_uuid);

            if (is_wp_error($role_removal_result)) {
                \Wicket()->log()->error('Failed to remove roles', [
                    'source' => 'wicket-orgman',
                    'person_uuid' => $person_uuid,
                    'org_uuid' => $org_uuid,
                    'roles' => $roles_to_remove,
                    'error' => $role_removal_result->get_error_message(),
                ]);
            }
        }

        // If still no success, throw an error
        if (!$removal_success) {
            status_header(200);
            WicketORM\Helpers\DatastarSSE::renderError(__('Failed to remove member. Person membership ID is required.', 'wicket-acc'), '#remove-member-messages', ['removeMemberSubmitting' => false, 'membersLoading' => false]);

            return;
        }

        // Success message
        $success_message = sprintf(
            esc_html__('Successfully removed %1$s from the organization.', 'wicket-acc'),
            '<strong>' . esc_html($member_name) . '</strong>'
        );

        // Clear members cache for this organization after successful removal
        if ($removal_success && !empty($membership_uuid)) {
            $orgman_instance = WicketORM\OrgMan::get_instance();
            $orgman_instance->clearMembersCache($membership_uuid);
        }
        $element_patches = WicketORM\Helpers\MemberListRefresh::buildOrgMembersListPatches(
            $org_uuid,
            $membership_uuid,
            $org_dom_suffix,
            1,
            ''
        );

        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderSuccess($success_message, '#remove-member-messages', [
            'removeMemberSubmitting' => false,
            'removeMemberSuccess' => true,
            'membersLoading' => false,
            'autoCloseCountdown' => $remove_auto_close_on_success ? $remove_auto_close_delay_seconds : 0,
        ], 0, 'remove-countdown', $element_patches);

        return;

    } catch (Throwable $e) {
        status_header(200);
        \Wicket()->log()->error('remove-member modal failed: ' . $e->getMessage(), ['source' => 'wicket-orgman', 'org_uuid' => $org_uuid, 'person_uuid' => $person_uuid, 'connection_ids' => $person_connection_ids]);
        WicketORM\Helpers\DatastarSSE::renderError(__('An unexpected error occurred. Please try again.', 'wicket-acc'), '#remove-member-messages', ['removeMemberSubmitting' => false, 'membersLoading' => false]);

        return;
    }
}

// For GET requests, this file should not be accessed directly
status_header(405);
echo json_encode(['error' => 'Method not allowed']);
