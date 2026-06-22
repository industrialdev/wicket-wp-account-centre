<?php

/**
 * Hypermedia partial for Update Permissions modal processing.
 *
 * Renders the form (GET) and processes submissions (POST).
 */

use WicketORM\Services\ConfigService;
use WicketORM\Services\MemberService;
use WicketORM\Services\MembershipService;

if (!defined('ABSPATH')) {
    exit;
}

// Handle POST submissions
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ('POST' === strtoupper($request_method)) {
    // Validate nonce.
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'wicket-orgman-update-permissions')) {
        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderError(__('Invalid or missing security token. Please refresh and try again.', 'wicket-acc'), '#update-permissions-messages', ['editPermissionsSubmitting' => false]);

        return;
    }

    $org_uuid = isset($_POST['org_uuid']) ? sanitize_text_field(wp_unslash($_POST['org_uuid'])) : '';
    $org_dom_suffix = isset($_POST['org_dom_suffix'])
        ? sanitize_html_class((string) wp_unslash($_POST['org_dom_suffix']))
        : sanitize_html_class($org_uuid ?: 'default');
    $membership_uuid = isset($_POST['membership_uuid']) ? sanitize_text_field(wp_unslash($_POST['membership_uuid'])) : '';
    $person_uuid = isset($_POST['person_uuid']) ? sanitize_text_field(wp_unslash($_POST['person_uuid'])) : '';
    $person_name = isset($_POST['person_name']) ? sanitize_text_field(wp_unslash($_POST['person_name'])) : '';

    if (empty($org_uuid) || empty($membership_uuid) || empty($person_uuid)) {
        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderError(__('Organization UUID, membership UUID, and person UUID are required.', 'wicket-acc'), '#update-permissions-messages', ['editPermissionsSubmitting' => false]);

        return;
    }

    $roles = [];
    if (isset($_POST['roles']) && is_array($_POST['roles'])) {
        $roles = array_map(static function ($role) {
            return sanitize_text_field(wp_unslash($role));
        }, $_POST['roles']);
    }

    // Get relationship type if provided
    $relationship_type = isset($_POST['relationship_type']) ? sanitize_text_field(wp_unslash($_POST['relationship_type'])) : '';
    $description = array_key_exists('description', $_POST) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : null;

    try {
        $configService = new ConfigService();
        $member_service = new MemberService($configService);
        $membershipService = new MembershipService();
        $logger = \Wicket()->log();
        $log_context = ['source' => 'wicket-orgman', 'action' => 'update-permissions'];

        // First update relationship type if provided and enabled
        $config = \WicketORM\Services\ConfigService::getConfig();
        $edit_permissions_config = $config['edit_permissions_modal'] ?? [];
        if (!is_array($edit_permissions_config) || $edit_permissions_config === []) {
            $edit_permissions_config = $config['member_management']['permissions_modal'] ?? [];
        }
        $edit_allowed_roles = is_array($edit_permissions_config['allowed_roles'] ?? null)
            ? $edit_permissions_config['allowed_roles']
            : [];
        if ($edit_allowed_roles === []) {
            $edit_allowed_roles = is_array($edit_permissions_config['allowlist'] ?? null)
                ? $edit_permissions_config['allowlist']
                : [];
        }
        $edit_excluded_roles = is_array($edit_permissions_config['excluded_roles'] ?? null)
            ? $edit_permissions_config['excluded_roles']
            : [];
        if ($edit_excluded_roles === []) {
            $edit_excluded_roles = is_array($edit_permissions_config['denylist'] ?? null)
                ? $edit_permissions_config['denylist']
                : [];
        }
        if (!empty($config['permissions']['prevent_owner_assignment'])) {
            $edit_excluded_roles[] = 'membership_owner';
        }
        if (!empty($config['access']['permissions']['prevent_owner_assignment'])) {
            if (!in_array('membership_owner', $edit_excluded_roles, true)) {
                $edit_excluded_roles[] = 'membership_owner';
            }
        }
        $roles = WicketORM\Helpers\PermissionHelper::filter_role_submission(
            $roles,
            $edit_allowed_roles,
            $edit_excluded_roles
        );
        if ($logger) {
            $logger->debug('[OrgMan] update-permissions role payload prepared', $log_context + [
                'org_uuid' => $org_uuid,
                'person_uuid' => $person_uuid,
                'submitted_roles' => isset($_POST['roles']) && is_array($_POST['roles']) ? array_values($_POST['roles']) : [],
                'allowed_roles' => array_values($edit_allowed_roles),
                'excluded_roles' => array_values(array_unique($edit_excluded_roles)),
                'filtered_roles' => array_values($roles),
            ]);
        }

        $allow_relationship_editing = $config['member_management']['forms']['add_member']['allow_relationship_type_editing'] ?? false;
        $form_fields = $config['member_management']['forms']['add_member']['fields'] ?? [];
        $allow_description_editing = $form_fields['description']['enabled'] ?? false;

        if ($allow_relationship_editing && !empty($relationship_type)) {
            $relationship_result = $member_service->updateMemberRelationship($person_uuid, $org_uuid, $relationship_type);

            if (is_wp_error($relationship_result)) {
                status_header(200);
                WicketORM\Helpers\DatastarSSE::renderError($relationship_result->get_error_message(), '#update-permissions-messages', ['editPermissionsSubmitting' => false]);

                return;
            }
        }

        if ($allow_description_editing && $description !== null) {
            $description_result = $member_service->updateMemberDescription($person_uuid, $org_uuid, $description);

            if (is_wp_error($description_result)) {
                status_header(200);
                WicketORM\Helpers\DatastarSSE::renderError($description_result->get_error_message(), '#update-permissions-messages', ['editPermissionsSubmitting' => false]);

                return;
            }
        }

        // Resolve current org membership UUID to avoid stale posted membership UUID on long-lived pages.
        $resolved_membership_uuid = (string) $membershipService->getMembershipForOrganization($org_uuid);
        $effective_membership_uuid = $resolved_membership_uuid !== '' ? $resolved_membership_uuid : $membership_uuid;

        if ($logger) {
            $logger->debug('[OrgMan] update-permissions membership UUID resolution', $log_context + [
                'org_uuid' => $org_uuid,
                'person_uuid' => $person_uuid,
                'posted_membership_uuid' => $membership_uuid,
                'resolved_membership_uuid' => $resolved_membership_uuid,
                'effective_membership_uuid' => $effective_membership_uuid,
            ]);
        }

        // Then update roles
        $result = $member_service->updateMemberRoles($person_uuid, $org_uuid, $effective_membership_uuid, $roles);

        if (is_wp_error($result)) {
            if ($logger) {
                $logger->warning('[OrgMan] update-permissions role update failed', $log_context + [
                    'org_uuid' => $org_uuid,
                    'person_uuid' => $person_uuid,
                    'membership_uuid' => $effective_membership_uuid,
                    'error_code' => $result->get_error_code(),
                    'error_message' => $result->get_error_message(),
                ]);
            }
            status_header(200);
            WicketORM\Helpers\DatastarSSE::renderError($result->get_error_message(), '#update-permissions-messages', ['editPermissionsSubmitting' => false]);

            return;
        }

        // Success message
        $full_name = trim(($result['first_name'] ?? '') . ' ' . ($result['last_name'] ?? ''));
        if ($full_name === '') {
            $full_name = trim($person_name);
        }
        if ($full_name === '') {
            $full_name = (string) __('this member', 'wicket-acc');
        }
        $success_message = sprintf(
            esc_html__('Successfully updated permissions for %1$s.', 'wicket-acc'),
            '<strong>' . esc_html($full_name) . '</strong>'
        );
        if ($logger) {
            $logger->info('[OrgMan] update-permissions completed successfully', $log_context + [
                'org_uuid' => $org_uuid,
                'person_uuid' => $person_uuid,
                'membership_uuid' => $effective_membership_uuid,
                'applied_roles' => array_values($roles),
            ]);
        }
        if ($effective_membership_uuid !== '') {
            $orgman_instance = WicketORM\OrgMan::get_instance();
            $orgman_instance->clearMembersCache($effective_membership_uuid);
        }
        $element_patches = WicketORM\Helpers\MemberListRefresh::buildOrgMembersListPatches(
            $org_uuid,
            $effective_membership_uuid,
            $org_dom_suffix,
            1,
            ''
        );

        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderSuccess($success_message, '#update-permissions-messages', [
            'editPermissionsSubmitting' => false,
            'editPermissionsSuccess' => true,
            'membersLoading' => false,
        ], 0, 'update-permissions-countdown', $element_patches);

        return;

    } catch (Throwable $e) {
        status_header(200);
        \Wicket()->log()->error('update-permissions modal failed: ' . $e->getMessage(), ['source' => 'wicket-orgman', 'org_uuid' => $org_uuid, 'membership_uuid' => $membership_uuid, 'person_uuid' => $person_uuid]);
        WicketORM\Helpers\DatastarSSE::renderError(__('An unexpected error occurred. Please try again.', 'wicket-acc'), '#update-permissions-messages', ['editPermissionsSubmitting' => false]);

        return;
    }
}

// For GET requests, this file should not be accessed directly
status_header(405);
echo json_encode(['error' => 'Method not allowed']);
