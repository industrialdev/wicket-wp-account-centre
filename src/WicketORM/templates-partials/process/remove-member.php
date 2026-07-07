<?php

/**
 * Hypermedia partial for Remove Member modal processing.
 *
 * Renders the form (GET) and processes submissions (POST).
 *
 * Removal business logic lives in MembershipRosterWriter::removeMember, which
 * dispatches to the active roster strategy. This modal is intentionally thin:
 * nonce + input sanitization, permission check, member-name resolution for the
 * success message, SSE rendering, and list refresh.
 */

use WicketORM\Services\ConfigService;
use WicketORM\Services\MemberService;

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

    // Require org_uuid and person_uuid, but allow either connection_id OR person_membership_id.
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

    try {
        $configService = new ConfigService();
        $roster_mode = (string) $configService->getRosterMode();
        $orgman_config = ConfigService::getConfig();
        $presentation_config = is_array($orgman_config['presentation'] ?? null) ? $orgman_config['presentation'] : [];
        $member_view_config = is_array($presentation_config['member_view'] ?? null) ? $presentation_config['member_view'] : [];
        $remove_auto_close_on_success = (bool) ($member_view_config['add_member_auto_close_on_success'] ?? false);
        $remove_auto_close_delay_seconds = max(0, (int) ($member_view_config['add_member_auto_close_delay_seconds'] ?? 7));

        // Cycle mode genuinely keys off a membership uuid; surface that as a clear
        // pre-check before any removal work. The strategy enforces it too.
        if ($roster_mode === 'membership_cycle' && empty($membership_uuid)) {
            status_header(200);
            WicketORM\Helpers\DatastarSSE::renderError(
                __('Membership UUID is required for membership cycle removals.', 'wicket-acc'),
                '#remove-member-messages',
                ['removeMemberSubmitting' => false, 'membersLoading' => false]
            );

            return;
        }

        // Resolve a friendly name for the success message.
        $member_name = 'Member';
        if (!empty($person_name)) {
            $member_name = $person_name;
        } elseif (function_exists('wicket_get_person_by_id')) {
            $person_data = wicket_get_person_by_id($person_uuid);
            if ($person_data) {
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

        // Dispatch to the strategy layer. The writer resolves the active roster mode,
        // invokes the strategy's removeMember, and invalidates the members cache.
        $member_service = new MemberService($configService);
        $context = [
            'membership_uuid' => $membership_uuid,
            'person_membership_id' => $person_membership_id,
            'connection_id' => $person_connection_ids,
            'person_email' => $person_email,
        ];

        $result = $member_service->removeMember($org_uuid, $person_uuid, $context);

        if (is_wp_error($result)) {
            status_header(200);
            WicketORM\Helpers\DatastarSSE::renderError(
                $result->get_error_message(),
                '#remove-member-messages',
                ['removeMemberSubmitting' => false, 'membersLoading' => false]
            );

            return;
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
