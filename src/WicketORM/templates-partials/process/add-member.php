<?php

/**
 * Hypermedia partial for Add Member modal processing.
 *
 * Renders the form (GET) and processes submissions (POST).
 */

use starfederation\datastar\enums\ElementPatchMode;
use starfederation\datastar\ServerSentEventGenerator;
use WicketORM\Services\ConfigService;
use WicketORM\Services\MemberService;

if (!defined('ABSPATH')) {
    exit;
}

// Handle POST submissions
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ('POST' === strtoupper($request_method)) {
    $posted_org_dom_suffix = isset($_POST['org_dom_suffix'])
        ? sanitize_html_class((string) wp_unslash($_POST['org_dom_suffix']))
        : sanitize_html_class((string) ($_POST['org_uuid'] ?? 'default'));
    $error_signals = [
        'addMemberSubmitting' => false,
        'membersLoading' => false,
        'addMemberSuccess' => false,
        'addMemberSuccessMessage' => '',
        'autoCloseCountdown' => 0,
    ];

    // Validate nonce.
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'wicket-orgman-add-member')) {
        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderError(
            __('Invalid or missing security token. Please refresh and try again.', 'wicket-acc'),
            '#add-member-messages-' . $posted_org_dom_suffix,
            $error_signals
        );

        return;
    }

    $org_uuid = isset($_POST['org_uuid']) ? sanitize_text_field(wp_unslash($_POST['org_uuid'])) : '';
    $membership_uuid = isset($_POST['membership_id']) ? sanitize_text_field(wp_unslash($_POST['membership_id'])) : '';
    $org_dom_suffix = isset($_POST['org_dom_suffix'])
        ? sanitize_html_class((string) wp_unslash($_POST['org_dom_suffix']))
        : sanitize_html_class($org_uuid ?: 'default');

    if (empty($org_uuid)) {
        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderError(
            __('Organization identifier missing.', 'wicket-acc'),
            '#add-member-messages-' . $org_dom_suffix,
            $error_signals
        );

        return;
    }

    if (!WicketORM\Helpers\PermissionHelper::can_add_members($org_uuid)) {
        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderError(
            __('You do not have permission to add members to this organization.', 'wicket-acc'),
            '#add-member-messages-' . $org_dom_suffix,
            $error_signals
        );

        return;
    }

    $requested_roster_mode = (new ConfigService())->getRosterMode();
    if ($requested_roster_mode === 'membership_cycle' && empty($membership_uuid)) {
        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderError(
            __('Membership UUID is required for membership cycle additions.', 'wicket-acc'),
            '#add-member-messages-' . $org_dom_suffix,
            $error_signals
        );

        return;
    }

    $member_data = [
        'first_name' => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
        'last_name'  => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
        'email'      => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
        'phone'      => isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '',
        'job_title'  => isset($_POST['job_title']) ? sanitize_text_field(wp_unslash($_POST['job_title'])) : '',
    ];

    // 1. Check for duplicates by email before creating/updating the person record.
    if (!empty($membership_uuid) && !empty($member_data['email']) && function_exists('wicket_person_in_membership')) {
        if (wicket_person_in_membership($membership_uuid, $member_data['email'])) {
            status_header(200);
            WicketORM\Helpers\DatastarSSE::renderError(
                sprintf(__('A member with the email %s already exists in this membership.', 'wicket-acc'), '<strong>' . esc_html($member_data['email']) . '</strong>'),
                '#add-member-messages-' . $org_dom_suffix,
                $error_signals
            );

            return;
        }
    }

    // Handle relationship type if provided
    $relationship_type = isset($_POST['relationship_type']) ? sanitize_text_field(wp_unslash($_POST['relationship_type'])) : '';
    $relationship_description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';

    $roles = [];
    if (isset($_POST['roles']) && is_array($_POST['roles'])) {
        $roles = array_map(static function ($role) {
            return sanitize_text_field(wp_unslash($role));
        }, $_POST['roles']);
    }

    $orgman_config = \WicketORM\Services\ConfigService::getConfig();
    $permissions_field_config = $orgman_config['member_management']['forms']['add_member']['fields']['permissions'] ?? [];
    $allowed_roles = is_array($permissions_field_config['allowlist'] ?? null)
        ? $permissions_field_config['allowlist']
        : [];
    $excluded_roles = is_array($permissions_field_config['denylist'] ?? null)
        ? $permissions_field_config['denylist']
        : [];

    $roles = WicketORM\Helpers\PermissionHelper::filter_role_submission(
        $roles,
        $allowed_roles,
        $excluded_roles
    );

    try {
        $configService = new ConfigService();
        $member_service = new MemberService($configService);

        $context = [
            'roles'            => $roles,
            'org_name'         => '',
            'membership_uuid'  => $membership_uuid,
            'relationship_type' => $relationship_type,
            'relationship_description' => $relationship_description,
        ];

        $result = $member_service->addMember($org_uuid, $member_data, $context);

        if (is_wp_error($result)) {
            status_header(200);
            WicketORM\Helpers\DatastarSSE::renderError(
                $result->get_error_message(),
                '#add-member-messages-' . $org_dom_suffix,
                array_merge($error_signals, ['addMemberFormError' => true])
            );

            return;
        }

        // Handle automatic communication opt-in if configured
        $auto_opt_in = $orgman_config['member_management']['addition']['auto_opt_in_communications'] ?? [];
        if (!empty($auto_opt_in['enabled']) && !empty($result['person_uuid'])) {
            if (function_exists('wicket_person_update_communication_preferences')) {
                $preferences = [];

                if (isset($auto_opt_in['email'])) {
                    $preferences['email'] = (bool) $auto_opt_in['email'];
                }

                if (!empty($auto_opt_in['sublists']) && is_array($auto_opt_in['sublists'])) {
                    $sublists = [];
                    foreach ($auto_opt_in['sublists'] as $sublist_key) {
                        $sublists[$sublist_key] = true;
                    }
                    $preferences['sublists'] = $sublists;
                }

                if (!empty($preferences)) {
                    $opt_in_result = wicket_person_update_communication_preferences($preferences, [
                        'person_uuid' => $result['person_uuid'],
                    ]);

                    if (!$opt_in_result) {
                        \Wicket()->log()->info('Auto-opt-in failed', [
                            'source' => 'wicket-orgman',
                            'person_uuid' => $result['person_uuid'],
                        ]);
                    }
                }
            } else {
                \Wicket()->log()->info('Auto-opt-in skipped: helper function missing', [
                    'source' => 'wicket-orgman',
                ]);
            }
        }

        // Clear members cache for this organization after successful addition
        $cache_membership_uuid = $membership_uuid;
        if ($cache_membership_uuid === '') {
            $membershipService = new WicketORM\Services\MembershipService();
            $cache_membership_uuid = (string) $membershipService->getMembershipForOrganization($org_uuid);
        }
        if ($cache_membership_uuid) {
            $orgman_instance = WicketORM\OrgMan::get_instance();
            $orgman_instance->clearMembersCache($cache_membership_uuid);
        }

        // Success message
        $full_name = trim(($member_data['first_name'] ?? '') . ' ' . ($member_data['last_name'] ?? ''));
        $success_message = wp_sprintf(
            /* translators: 1: member full name, 2: member email address */
            __('Successfully added %1$s with email %2$s.', 'wicket-acc'),
            $full_name !== '' ? $full_name : __('the member', 'wicket-acc'),
            (string) ($member_data['email'] ?? '')
        );

        $element_patches = WicketORM\Helpers\MemberListRefresh::buildOrgMembersListPatches(
            $org_uuid,
            (string) $cache_membership_uuid,
            $org_dom_suffix,
            1,
            ''
        );

        status_header(200);
        $orgman_config = \WicketORM\Services\ConfigService::getConfig();
        $presentation_config = is_array($orgman_config['presentation'] ?? null) ? $orgman_config['presentation'] : [];
        $member_view_config = is_array($presentation_config['member_view'] ?? null) ? $presentation_config['member_view'] : [];
        $auto_close_on_success = (bool) ($member_view_config['add_member_auto_close_on_success'] ?? false);
        $auto_close_delay_seconds = max(0, (int) ($member_view_config['add_member_auto_close_delay_seconds'] ?? 7));
        $generator = new ServerSentEventGenerator();
        $generator->sendHeaders();
        $generator->patchSignals([
            'addMemberSubmitting' => false,
            'membersLoading' => false,
            'addMemberSuccess' => true,
            'addMemberSuccessMessage' => $success_message,
            'autoCloseCountdown' => $auto_close_on_success ? $auto_close_delay_seconds : 0,
        ]);
        foreach ($element_patches as $patch) {
            $generator->patchElements((string) $patch['elements'], [
                'selector' => (string) $patch['selector'],
                'mode' => $patch['mode'] ?? ElementPatchMode::Outer,
            ]);
        }

        return;

    } catch (Throwable $e) {
        status_header(200);
        \Wicket()->log()->error('Member addition failed: ' . $e->getMessage(), [
            'source' => 'wicket-orgman',
            'org_uuid' => $org_uuid,
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
        ]);
        WicketORM\Helpers\DatastarSSE::renderError(
            __('An unexpected error occurred. Please try again.', 'wicket-acc'),
            '#add-member-messages-' . $org_dom_suffix,
            $error_signals
        );

        return;
    }
}
