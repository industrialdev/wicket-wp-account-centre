<?php

declare(strict_types=1);

use WicketORM\Helpers as OrgHelpers;

/*
 * Members list Datastar partial.
 *
 * This partial can be rendered directly (hypermedia request) or included server-side.
 * It expects the following optional variables to be defined prior to include:
 * - $members (array)
 * - $pagination (array)
 * - $org_uuid (string)
 * - $query (string)
 * - $members_list_endpoint (string)
 * - $members_list_target (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

$org_uuid = isset($org_uuid) ? (string) $org_uuid : '';
if (empty($org_uuid) && isset($org_uuid_for_partial)) {
    $org_uuid = (string) $org_uuid_for_partial;
}

if (empty($org_uuid) && isset($_GET['org_uuid'])) {
    $org_uuid = sanitize_text_field((string) $_GET['org_uuid']);
}

$members_list_target = isset($members_list_target)
    ? (string) $members_list_target
    : 'members-list-container-' . sanitize_html_class($org_uuid ?: 'default');

$members_list_endpoint = isset($members_list_endpoint)
    ? (string) $members_list_endpoint
    : OrgHelpers\template_url() . 'members-list';
$members_list_separator = str_contains($members_list_endpoint, '?') ? '&' : '?';
$encodedOrgUuid = rawurlencode((string) $org_uuid);

$update_permissions_endpoint = OrgHelpers\template_url() . 'process/update-permissions';
$update_permissions_error_actions = "console.error('Failed to update permissions'); \$editPermissionsSubmitting = false; \$membersLoading = false;";
$edit_permissions_reset_actions = "\$editPermissionsSuccess = false; \$editPermissionsSubmitting = false; \$currentMemberUuid = ''; \$currentMemberName = ''; \$currentMemberRoles = []; \$currentMemberRelationshipType = ''; (() => { const messages = document.getElementById('update-permissions-messages'); if (messages) messages.innerHTML = ''; })();";
$remove_member_endpoint = OrgHelpers\template_url() . 'process/remove-member';
$remove_member_error_actions = "console.error('Failed to remove member'); \$removeMemberSubmitting = false; \$membersLoading = false;";

$page = isset($pagination['currentPage']) ? (int) $pagination['currentPage'] : 1;
$total_pages = isset($pagination['totalPages']) ? (int) $pagination['totalPages'] : 1;
$page_size = isset($pagination['pageSize']) ? (int) $pagination['pageSize'] : 15;
$total_items = isset($pagination['totalItems']) ? (int) $pagination['totalItems'] : 0;
$query = isset($query) ? (string) $query : '';

// Ensure membership_uuid is available (might be passed as membership_uuid or membershipUuid)
if (!isset($membership_uuid) && isset($membershipUuid)) {
    $membership_uuid = $membershipUuid;
}
if (!isset($membership_uuid) && isset($_GET['membership_uuid'])) {
    $membership_uuid = sanitize_text_field((string) $_GET['membership_uuid']);
}
$membership_query_fragment = '';
if (!empty($membership_uuid)) {
    $membership_query_fragment = '&membership_uuid=' . rawurlencode((string) $membership_uuid);
}
$update_permissions_local_sync_actions = "(() => { const modal = document.getElementById('editPermissionsModal'); if (!modal) return; const selected = Array.from(modal.querySelectorAll('input[name=\"roles[]\"]:checked')).map((node) => node.value); const selectedJson = JSON.stringify(selected); document.querySelectorAll('.edit-permissions-button[data-member-uuid=\"' + \$currentMemberUuid + '\"]').forEach((btn) => { btn.dataset.memberRoles = selectedJson; }); \$currentMemberRoles = selected; })();";
$update_permissions_success_actions = "console.log('Permissions updated successfully'); \$editPermissionsSubmitting = false; \$editPermissionsSuccess = true; \$membersLoading = false; {$update_permissions_local_sync_actions}";
$remove_member_reset_actions = "(() => { const modal = document.getElementById('removeMemberModal'); const messages = modal ? modal.querySelector('#remove-member-messages') : document.getElementById('remove-member-messages'); if (messages) messages.innerHTML = ''; if (modal && modal.open) modal.close(); })(); \$removeMemberModalOpen = false; \$removeMemberSubmitting = false; \$removeMemberSuccess = false; \$membersLoading = false; \$autoCloseCountdown = 0; \$currentRemoveMemberUuid = ''; \$currentRemoveMemberName = ''; \$currentRemoveMemberEmail = ''; \$currentRemoveMemberConnectionId = ''; \$currentRemoveMemberPersonMembershipId = '';";
$remove_member_success_actions = "console.log('Member removed successfully'); \$removeMemberSubmitting = false; \$removeMemberSuccess = true; \$membersLoading = false;";

$orgman_config = WicketORM\Services\ConfigService::getConfig();
$presentation_config = is_array($orgman_config['presentation'] ?? null)
    ? $orgman_config['presentation']
    : [];
$member_list_config = is_array($presentation_config['member_list'] ?? null)
    ? $presentation_config['member_list']
    : [];
$member_view_config = is_array($presentation_config['member_view'] ?? null)
    ? $presentation_config['member_view']
    : [];
$show_remove_button_by_config = (bool) ($member_list_config['show_remove_button'] ?? true);
$show_bulk_upload = (bool) ($member_list_config['show_bulk_upload'] ?? false);
$seat_limit_message = (string) ($member_list_config['seat_limit_message'] ?? __('All seats have been assigned. Please purchase additional seats to add more members.', 'wicket-acc'));
$remove_policy_callout = is_array($member_list_config['remove_policy_callout'] ?? null)
    ? $member_list_config['remove_policy_callout']
    : [];
$remove_policy_callout_placement = (string) ($remove_policy_callout['placement'] ?? 'above_members');
$account_status_config = is_array($member_list_config['account_status'] ?? null)
    ? $member_list_config['account_status']
    : [];
$show_account_status = (bool) ($account_status_config['enabled'] ?? true);
$show_unconfirmed_label = (bool) ($account_status_config['show_unconfirmed_label'] ?? true);
$confirmed_tooltip = (string) ($account_status_config['confirmed_tooltip'] ?? __('Account confirmed', 'wicket-acc'));
$unconfirmed_tooltip = (string) ($account_status_config['unconfirmed_tooltip'] ?? __('Account not confirmed', 'wicket-acc'));
$unconfirmed_label = (string) ($account_status_config['unconfirmed_label'] ?? __('Account not confirmed', 'wicket-acc'));
$remove_member_auto_close_on_success = (bool) ($member_view_config['add_member_auto_close_on_success'] ?? false);
$remove_member_auto_close_delay_seconds = max(0, (int) ($member_view_config['add_member_auto_close_delay_seconds'] ?? 7));
if ((!isset($members) || !is_array($members)) && !empty($org_uuid)) {
    $configService = new WicketORM\Services\ConfigService();
    $member_service = new WicketORM\Services\MemberService($configService);
    $membership = new WicketORM\Services\MembershipService();
    if (!isset($membership_uuid)) {
        $membership_uuid = $membership->getMembershipForOrganization($org_uuid);
    }
    $page = max(1, isset($_GET['page']) ? (int) $_GET['page'] : 1);
    $page_size_param = isset($_GET['size']) ? (int) $_GET['size'] : $page_size;
    $query = isset($_GET['query']) ? sanitize_text_field((string) $_GET['query']) : $query;

    if (!empty($membership_uuid)) {
        $result = $member_service->getMembers(
            $membership_uuid,
            $org_uuid,
            [
                'page' => $page,
                'size' => max(1, $page_size_param),
                'query' => $query,
            ],
            true // Enable lazy loading
        );

        $members = $result['members'] ?? [];
        $pagination = $result['pagination'] ?? [];
        $page = (int) ($pagination['currentPage'] ?? $page);
        $total_pages = (int) ($pagination['totalPages'] ?? $total_pages);
        $page_size = (int) ($pagination['pageSize'] ?? $page_size);
        $total_items = (int) ($pagination['totalItems'] ?? $total_items);
    }
}

// Seat availability check
$max_seats = 0;
$active_seats = 0;
$has_seats_available = true;

if (!empty($membership_uuid)) {
    $membershipService = new WicketORM\Services\MembershipService();
    $membership_data = $membershipService->getOrgMembershipData($membership_uuid);

    if ($membership_data && isset($membership_data['data']['attributes'])) {
        $max_seats = $membershipService->getEffectiveMaxAssignments($membership_data);
        $active_seats = (int) ($membership_data['data']['attributes']['active_assignments_count'] ?? 0);

        if ($max_seats !== null && $active_seats >= (int) $max_seats) {
            $has_seats_available = false;
        }
    }
}

$members = isset($members) && is_array($members) ? $members : [];
$total_pages = max(1, $total_pages);
$page = min(max(1, $page), $total_pages);

// Load available roles for the edit permissions modal
$permissionService = new WicketORM\Services\PermissionService();
$available_roles = $permissionService->getAvailableRoles();

// Load config for relationship type editing
$role_display_map = $orgman_config['access']['roles']['labels'] ?? [];
$role_descriptions = $orgman_config['access']['roles']['descriptions'] ?? [];

// Filter out membership_owner if configured to prevent assignment
if (!empty($orgman_config['access']['permissions']['prevent_owner_assignment'])) {
    unset($available_roles['membership_owner']);
}

$edit_permissions_config = $orgman_config['member_management']['permissions_modal'] ?? [];
$edit_allowed_roles = is_array($edit_permissions_config['allowlist'] ?? null)
    ? $edit_permissions_config['allowlist']
    : [];
$edit_excluded_roles = is_array($edit_permissions_config['denylist'] ?? null)
    ? $edit_permissions_config['denylist']
    : [];

$available_roles = OrgHelpers\PermissionHelper::filter_role_choices(
    $available_roles,
    $edit_allowed_roles,
    $edit_excluded_roles
);

$allow_relationship_editing = $orgman_config['member_management']['forms']['add_member']['allow_relationship_type_editing'] ?? false;
$relationship_types = $orgman_config['relationships']['labels']['custom'] ?? [];
$show_remove_button = $show_remove_button_by_config && OrgHelpers\PermissionHelper::can_remove_members($org_uuid);
// When this partial is rendered via the hypermedia endpoint (e.g. a member-search
// GET), Datastar morphs top-level elements by id. The modal blocks below have no
// id on their wrapper div, which triggers PatchElementsNoTargetsFound. The modals
// already live in the page shell, so skip emitting them on endpoint requests.
$is_hypermedia_request = OrgHelpers\is_orgman_template_request();
$show_remove_policy_callout = (
    !$show_remove_button
    && !empty($remove_policy_callout['enabled'])
    && !empty($remove_policy_callout['message'])
);

// Get current user UUID for owner comparison
$current_user_uuid = function_exists('wicket_current_person_uuid') ? wicket_current_person_uuid() : null;
$encodedOrgUuid = rawurlencode((string) $org_uuid);

$base_query_args = [
    'org_uuid' => $org_uuid,
    'query'    => $query,
    'size'     => $page_size,
];
if (!empty($membership_uuid)) {
    $base_query_args['membership_uuid'] = $membership_uuid;
}

$build_url = static function (int $page_number) use ($members_list_endpoint, $base_query_args) {
    $args = array_merge($base_query_args, ['page' => $page_number]);
    $separator = str_contains($members_list_endpoint, '?') ? '&' : '?';
    $query_args = http_build_query($args, '', '&', PHP_QUERY_RFC3986);

    return $members_list_endpoint . $separator . $query_args;
};

$build_action = static function (int $page_number) use ($build_url) {
    $url = $build_url($page_number);

    return '$listLoading = true; @get(\'' . $url . '\')';
};

$no_members_message = __('No members found.', 'wicket-acc');

?>
<div
    id="<?php echo esc_attr($members_list_target); ?>"
    class="wt_mt-6 wt_mb-6 wt_flex wt_flex-col wt_gap-1 wt_relative"
    data-page="<?php echo esc_attr((string) $page); ?>"
    data-attr:aria-busy="$listLoading">
    <div class="members-loading-state wt_flex wt_flex-col wt_items-center wt_justify-center wt_gap-4 wt_rounded-card wt_border wt_border-color wt_bg-white wt_shadow-sm wt_text-center"
        data-show="$listLoading"
        style="display: none;">
        <span class="wt_loader" aria-hidden="true"></span>
        <p class="members-loading-state__message wt_text-base wt_font-semibold wt_text-content wt_leading-normal" role="status" aria-live="polite">
            <?php esc_html_e('Processing. Please wait...', 'wicket-acc'); ?>
        </p>
    </div>

    <div data-show="!$listLoading">

    <?php if ((bool) ($member_list_config['show_assignment_info'] ?? true)): ?>
    <div class="wt_text-xl wt_font-semibold wt_mb-3">
        <?php if ($max_seats !== null): ?>
            <?php printf(esc_html__('Seats assigned: %1$d / %2$d', 'wicket-acc'), (int) $active_seats, (int) $max_seats); ?>
        <?php else: ?>
            <?php esc_html_e('Number of assigned people:', 'wicket-acc'); ?>
            <?php echo (int) $total_items; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($show_remove_policy_callout && $remove_policy_callout_placement === 'above_members') : ?>
        <div class="wt_mt-1 wt_mb-3 wt_p-4 wt_border wt_border-yellow-200 wt_bg-yellow-50 wt_rounded-md wt_text-sm wt_text-yellow-900">
            <?php if (!empty($remove_policy_callout['title'])) : ?>
                <p class="wt_font-semibold wt_mb-1"><?php echo esc_html((string) $remove_policy_callout['title']); ?></p>
            <?php endif; ?>
            <p class="wt_mb-0">
                <?php echo esc_html((string) $remove_policy_callout['message']); ?>
                <?php if (!empty($remove_policy_callout['email'])) : ?>
                    <br>
                    <a class="wt_text-interactive underline wt_hover_decoration_none" href="mailto:<?php echo esc_attr((string) $remove_policy_callout['email']); ?>">
                        <?php echo esc_html((string) $remove_policy_callout['email']); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (empty($members)) : ?>
        <p class="wt_text-gray-500 wt_p-4"><?php echo esc_html($no_members_message); ?></p>
    <?php else : ?>
        <?php foreach ($members as $member) :
            $member_uuid = $member['person_uuid'] ?? null;
            $member_email = $member['email'] ?? '';
            $current_roles = !empty($member['current_roles']) ? $member['current_roles'] : ($member['roles'] ?? []);
            $formatted_roles = array_map(static function ($role) use ($role_display_map) {
                if (isset($role_display_map[$role])) {
                    return $role_display_map[$role];
                }

                return ucwords(str_replace('_', ' ', (string) $role));
            }, is_array($current_roles) ? $current_roles : []);
            $roles_text = !empty($formatted_roles) ? implode(', ', $formatted_roles) : '—';
            ?>
            <?php
                // Create person UUID without dashes for unique IDs
                $person_uuid_no_dashes = $member_uuid ? str_replace('-', '', $member_uuid) : uniqid('member', true);
            ?>
            <div class="member-card wt_bg-light-neutral wt_rounded-card wt_p-6 wt_transition-opacity wt_duration-300"
                id="member-card-<?php echo esc_attr($person_uuid_no_dashes); ?>">
                <div class="wt_flex wt_w-full md_wt_flex-row wt_items-start wt_justify-between wt_gap-4">
                    <div class="wt_flex wt_flex-col wt_gap-2 wt_w-full md_wt_w-4-5">
                        <div class="wt_flex wt_flex-col sm_wt_flex-row wt_items-start sm_wt_items-center wt_gap-2">
                            <div class="wt_flex wt_items-center wt_gap-2">
                                <?php if (OrgHelpers\Helper::should_show_member_name()) : ?>
                                <h3 class="wt_text-xl wt_font-medium wt_text-content wt_mb-0">
                                    <?php echo esc_html($member['full_name'] ?? ''); ?>
                                </h3>
                                <?php endif; ?>
                                <div id="member-status-<?php echo esc_attr($person_uuid_no_dashes); ?>" class="wt_inline-flex wt_items-center" data-member-status="<?php echo esc_attr($person_uuid_no_dashes); ?>">
                                    <?php if (!empty($member['lazy_loaded'])) : ?>
                                        <?php if ($show_account_status) : ?>
                                            <?php if (!empty($member['confirmed_at'])) : ?>
                                                <span class="wt_text-content" title="<?php echo esc_attr($confirmed_tooltip); ?>">
                                                    <span class="wt_inline-block wt_w-2 wt_h-2 wt_rounded-full wt_bg-green-500" aria-hidden="true"></span>
                                                </span>
                                            <?php else : ?>
                                                <span class="wt_text-content" title="<?php echo esc_attr($unconfirmed_tooltip); ?>">
                                                    <span class="wt_inline-block wt_w-2 wt_h-2 wt_rounded-full wt_bg-gray-400" aria-hidden="true"></span>
                                                </span>
                                                <?php if ($show_unconfirmed_label && $unconfirmed_label !== '') : ?>
                                                    <span class="wt_text-warning wt_whitespace-nowrap wt_ml-1 wt_text-2xs" title="<?php echo esc_attr($unconfirmed_tooltip); ?>">
                                                        <?php echo esc_html($unconfirmed_label); ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($member['is_owner'])) : ?>
                            <div class="wt_flex wt_items-center wt_gap-2">
                                <strong><?php esc_html_e('Organization Owner', 'wicket-acc'); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($member['title']) && OrgHelpers\Helper::should_show_member_job_title()) : ?>
                            <p class="member-job-title wt_text-sm wt_text-content">
                                <?php echo esc_html($member['title']); ?>
                            </p>
                        <?php endif; ?>

                        <div id="member-details-<?php echo esc_attr($person_uuid_no_dashes); ?>" class="wt_flex wt_flex-col wt_gap-2" data-member-details="<?php echo esc_attr($person_uuid_no_dashes); ?>">
                            <?php if (empty($member['lazy_loaded'])) : ?>
                                <?php
                                    $lazy_details_url = add_query_arg([
                                        'template'        => 'member-details',
                                        'person_uuid'     => (string) $member_uuid,
                                        'org_uuid'        => (string) $org_uuid,
                                        'membership_uuid' => (string) ($membership_uuid ?? ''),
                                    ], OrgHelpers\template_url());
                                ?>
                                <div class="wt_p-2 wt_bg-gray-50 wt_rounded"
                                    data-init="@get('<?php echo esc_js($lazy_details_url); ?>')">
                                    <div class="wt_flex wt_flex-col wt_gap-2">
                                        <div class="wt_skeleton wt_skeleton-text wt_skeleton-text--medium"></div>
                                        <div class="wt_skeleton wt_skeleton-text wt_skeleton-text--long"></div>
                                        <div class="wt_skeleton wt_skeleton-text wt_skeleton-text--short"></div>
                                    </div>
                                </div>
                            <?php else : ?>
                                <?php
                                $has_details = false;
                                // Check if relationship type should be hidden
                                if (!empty($member['relationship_names']) && OrgHelpers\Helper::should_show_member_relationship_type()) :
                                    $has_details = true;
                                    ?>
                                    <div class="wt_flex wt_items-center wt_gap-2">
                                        <span class="wt_text-content"><?php echo esc_html($member['relationship_names']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($member_email) && OrgHelpers\Helper::should_show_member_email()) :
                                    $has_details = true;
                                    ?>
                                    <div class="wt_flex wt_items-center wt_gap-2">
                                        <a href="mailto:<?php echo esc_attr($member_email); ?>" class="wt_text-sm wt_text-interactive wt_hover_underline">
                                            <?php echo esc_html($member_email); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (OrgHelpers\Helper::should_show_member_roles()) :
                                    $has_details = true;
                                    ?>
                                    <div class="wt_flex wt_items-baseline wt_gap-2 wt_text-sm">
                                        <strong><?php esc_html_e('Roles:', 'wicket-acc'); ?></strong>
                                        <span class="wt_text-content"><?php echo esc_html($roles_text); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!$has_details) : ?>
                                    <div class="wt_text-content wt_text-secondary wt_text-sm wt_italic" data-empty-details="true">
                                        <?php esc_html_e('No additional details available', 'wicket-acc'); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="wt_flex wt_flex-col sm_wt_flex-row wt_items-stretch sm_wt_items-start wt_gap-2 wt_justify-between md_wt_auto wt_shrink-0">
                            <button type="button" class="acc-edit-button edit-permissions-button button button--primary wt_inline-flex wt_items-center wt_justify-between wt_gap-2 wt_px-4 wt_py-2 wt_text-sm wt_border wt_border-bg-interactive wt_transition-colors wt_whitespace-nowrap component-button"
                                data-member-uuid="<?php echo esc_attr((string) ($member_uuid ?? '')); ?>"
                                data-member-roles="<?php echo esc_attr(wp_json_encode(array_values((array) $current_roles))); ?>"
                                data-on:click="
                                $editPermissionsSuccess = false;
                                $editPermissionsSubmitting = false;
                                (() => { const messages = document.getElementById('update-permissions-messages'); if (messages) messages.innerHTML = ''; })();
                                $currentMemberUuid = '<?php echo esc_js($member_uuid ?? ''); ?>';
                                $currentMemberName = '<?php echo esc_js($member['full_name'] ?? ''); ?>';
                                (() => { try { $currentMemberRoles = JSON.parse(evt.currentTarget.dataset.memberRoles || '[]'); } catch (e) { $currentMemberRoles = []; } })();
                                $currentMemberRelationshipType = '<?php echo esc_js($member['relationship_type'] ?? ''); ?>';
                                $editPermissionsModalOpen = true
                            ">
                            <?php esc_html_e('Edit Permissions', 'wicket-acc'); ?>
                            <svg class="wt_w-4 wt_h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-6M18.414 8.414 19.5 7.328a2 2 0 0 0 0-2.828 2 2 0 0 0-2.828 0L15.586 5.586M18.414 8.414l-6.036 6.036a2 2 0 0 1-1.388.584L8.414 15.586l.586-2.95A2 2 0 0 1 10 11.248l5.586-5.662M18.414 8.414 15.586 5.586" />
                            </svg>
                        </button>
                        <?php
                            // Hide Remove button for organization owner (any viewer)
                            $is_member_owner = !empty($member['is_owner']);
            ?>
                        <?php if ($show_remove_button && !$is_member_owner): ?>
                            <button type="button" class="acc-remove-button remove-member-button button button--secondary wt_inline-flex wt_items-center wt_justify-between wt_gap-2 wt_px-4 wt_py-2 wt_bg-light-neutral wt_text-sm wt_border wt_border-bg-interactive wt_transition-colors wt_whitespace-nowrap component-button"
                                data-on:click="
                                    $removeMemberSuccess = false;
                                    $removeMemberSubmitting = false;
                                    (() => { const messages = document.getElementById('remove-member-messages'); if (messages) messages.innerHTML = ''; })();
                                    $currentRemoveMemberUuid = '<?php echo esc_js($member_uuid ?? ''); ?>';
                                    $currentRemoveMemberName = '<?php echo esc_js($member['full_name'] ?? ''); ?>';
                                    $currentRemoveMemberEmail = '<?php echo esc_js($member['email'] ?? ''); ?>';
                                    $currentRemoveMemberConnectionId = '<?php echo esc_js($member['person_connection_ids'] ?? ''); ?>';
                                    $currentRemoveMemberPersonMembershipId = '<?php echo esc_js($member['person_membership_id'] ?? ''); ?>';
                                    $removeMemberModalOpen = true
                                ">
                                <?php esc_html_e('Remove', 'wicket-acc'); ?>
                                <svg class="wt_w-4 wt_h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <nav class="members-pagination wt_mt-6 wt_flex wt_flex-col wt_gap-4" aria-label="<?php esc_attr_e('Members pagination', 'wicket-acc'); ?>">
        <div class="members-pagination__info wt_w-full wt_text-left wt_text-sm wt_text-content">
            <?php
            if ($total_items > 0) {
                $first = (($page - 1) * $page_size) + 1;
                $last = min($total_items, $page * $page_size);
                echo esc_html(sprintf(__('Showing %1$d–%2$d of %3$d', 'wicket-acc'), $first, $last, $total_items));
            } else {
                // Pagination hidden when no members - esc_html_e('No members to display.', 'wicket-acc');
            }
?>
        </div>
        <?php if ($total_pages > 1) : ?>
            <div class="members-pagination__controls wt_w-full wt_flex wt_items-center wt_gap-2 wt_justify-end wt_self-end">
                <?php $show_prev = $page > 1; ?>
                <?php if ($show_prev) : ?>
                    <button type="button"
                        class="members-pagination__btn members-pagination__btn--prev button button--secondary wt_px-3 wt_py-2 wt_text-sm component-button"
                        data-on:click="<?php echo esc_attr($build_action($page - 1)); ?>"
                        data-on:success="<?php echo esc_attr('$listLoading = false; ' . wp_sprintf("select('#%s') | set(html)", $members_list_target)); ?>"
                        data-indicator:members-loading>
                        <?php esc_html_e('Previous', 'wicket-acc'); ?>
                    </button>
                <?php endif; ?>
                <div class="members-pagination__pages wt_flex wt_items-center wt_gap-1">
                    <?php for ($i = 1; $i <= $total_pages; $i++) :
                        $is_current = ($i === $page);
                        ?>
                        <button type="button"
                            class="members-pagination__btn members-pagination__btn--page button wt_px-3 wt_py-2 wt_text-sm <?php echo $is_current ? 'button--primary' : 'button--secondary'; ?> component-button"
                            <?php if ($is_current) : ?>disabled<?php endif; ?>
                            <?php if (!$is_current) : ?>data-on:click="<?php echo esc_attr($build_action($i)); ?>" <?php endif; ?>
                            data-on:success="<?php echo esc_attr('$listLoading = false; ' . wp_sprintf("select('#%s') | set(html)", $members_list_target)); ?>"
                            data-indicator:members-loading>
                            <?php echo esc_html((string) $i); ?>
                        </button>
                    <?php endfor; ?>
                </div>
                <?php $show_next = $page < $total_pages; ?>
                <?php if ($show_next) : ?>
                    <button type="button"
                        class="members-pagination__btn members-pagination__btn--next button button--secondary wt_px-3 wt_py-2 wt_text-sm component-button"
                        data-on:click="<?php echo esc_attr($build_action($page + 1)); ?>"
                        data-on:success="<?php echo esc_attr('$listLoading = false; ' . wp_sprintf("select('#%s') | set(html)", $members_list_target)); ?>"
                        data-indicator:members-loading>
                        <?php esc_html_e('Next', 'wicket-acc'); ?>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </nav>

    <?php if (OrgHelpers\PermissionHelper::can_add_members($org_uuid)): ?>
        <div class="wt_mt-6">
            <?php if ($has_seats_available): ?>
                <button type="button"
                    class="button button--primary add-member-button wt_w-full wt_py-2 component-button"
                    data-on:click="$addMemberSuccess = false; $addMemberSubmitting = false; $addMemberSuccessMessage = ''; (() => { const modal = document.getElementById('membersAddModal'); const form = modal ? modal.querySelector('form') : document.querySelector('#membersAddModal form'); if (form && form.reset) form.reset(); const messages = document.querySelector('[id^=\'add-member-messages-\']'); if (messages) messages.innerHTML = ''; })(); $addMemberModalOpen = true"><?php esc_html_e('Add Member', 'wicket-acc'); ?></button>
                <?php if ($show_bulk_upload) : ?>
                    <div class="wt_mt-3">
                        <button type="button"
                            class="button button--primary add-member-button wt_w-full wt_py-2 component-button"
                            data-on:click="$bulkUploadModalOpen = true"><?php esc_html_e('Bulk Upload Members', 'wicket-acc'); ?></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!$has_seats_available): ?>
                <div class="wt_mt-2 wt_p-3 wt_bg-yellow-50 wt_border wt_border-yellow-200 wt_rounded-md wt_text-yellow-800 wt_text-sm">
                    <div class="wt_flex wt_items-center wt_gap-2">
                        <svg class="wt_w-5 wt_h-5 wt_text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <span><?php echo esc_html($seat_limit_message); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php
    // WWID-1907: roster download. Gated on can_edit_members (plan §2.7), NOT
    // can_add_members — a user who can edit can download even when seats are
    // full. Rendered outside the seats-available block. The partial self-gates
    // on exports.enabled.
    if (OrgHelpers\PermissionHelper::can_edit_members($org_uuid)) :
        $org_dom_suffix = sanitize_html_class($org_uuid ?: 'default');
        $org_roster_count = $total_items;
        include __DIR__ . '/export-members-modal.php';
    endif;
    ?>

    <?php if ($show_remove_policy_callout && $remove_policy_callout_placement === 'below_members') : ?>
        <div class="wt_mt-2 wt_mb-0 wt_p-4 wt_border wt_border-yellow-200 wt_bg-yellow-50 wt_rounded-md wt_text-sm wt_text-yellow-900">
            <?php if (!empty($remove_policy_callout['title'])) : ?>
                <p class="wt_font-semibold wt_mb-1"><?php echo esc_html((string) $remove_policy_callout['title']); ?></p>
            <?php endif; ?>
            <p class="wt_mb-0">
                <?php echo esc_html((string) $remove_policy_callout['message']); ?>
                <?php if (!empty($remove_policy_callout['email'])) : ?>
                    <br>
                    <a class="wt_text-interactive underline wt_hover_decoration_none" href="mailto:<?php echo esc_attr((string) $remove_policy_callout['email']); ?>">
                        <?php echo esc_html((string) $remove_policy_callout['email']); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
    </div>
</div>

<?php if (!$is_hypermedia_request): ?>
<!-- Edit Permissions Modal - Single modal using pure Datastar -->
<div class="wt_mt-6" data-signals='{"editPermissionsModalOpen": false, "editPermissionsSubmitting": false, "editPermissionsSuccess": false, "currentMemberUuid": "", "currentMemberName": "", "currentMemberRoles": [], "currentMemberRelationshipType": "", "removeMemberModalOpen": false, "removeMemberSubmitting": false, "removeMemberSuccess": false, "currentRemoveMemberUuid": "", "currentRemoveMemberName": "", "currentRemoveMemberEmail": "", "currentRemoveMemberConnectionId": "", "currentRemoveMemberPersonMembershipId": ""}'>
    <dialog id="editPermissionsModal" class="modal wt_m-auto max_wt_3xl wt_rounded-md wt_shadow-md backdrop_wt_bg-black-50"
        data-show="$editPermissionsModalOpen"
        data-effect="if ($editPermissionsModalOpen) el.showModal(); else el.close();"
        data-on:close="
            ($membersLoading = false);
            $editPermissionsModalOpen = false;
            <?php echo esc_attr($edit_permissions_reset_actions); ?>
        ">
        <div class="wt_bg-white wt_p-6 wt_relative">
            <button type="button" class="orgman-modal__close wt_absolute wt_right-4 wt_top-4 wt_text-lg wt_font-semibold"
                data-on:click="
                    $editPermissionsModalOpen = false;
                    <?php echo esc_attr($edit_permissions_reset_actions); ?>
                " data-show="!$editPermissionsSuccess"
                data-class="{ 'wt_pointer-events-none': $editPermissionsSubmitting, 'wt_opacity-50': $editPermissionsSubmitting }"
                data-attr:aria-disabled="$editPermissionsSubmitting ? 'true' : 'false'">
                ×
            </button>

            <h2 class="wp-block-heading has-heading-sm-font-size wt_text-2xl wt_font-semibold wt_mb-4">
                <span
                    data-text="$currentMemberName ? '<?php echo esc_js(__('Edit Permissions for', 'wicket-acc')); ?> ' + $currentMemberName : '<?php echo esc_js(__('Edit Permissions', 'wicket-acc')); ?>'">
                    <?php echo esc_html__('Edit Permissions', 'wicket-acc'); ?>
                </span>
            </h2>

            <div id="update-permissions-messages">
                <!-- Messages will be inserted here by Datastar -->
            </div>

            <form
                method="POST"
                data-show="!$editPermissionsSuccess"
                data-on:submit="if(!$editPermissionsSubmitting){ $editPermissionsSubmitting = true; $membersLoading = true; @post('<?php echo esc_js($update_permissions_endpoint); ?>', { contentType: 'form' }); }"
                data-on:submit__prevent-default="true"
                data-on:success="<?php echo esc_attr($update_permissions_success_actions); ?>"
                data-on:error="<?php echo esc_attr($update_permissions_error_actions); ?>"
                data-on:reset="$editPermissionsSubmitting = false; $membersLoading = false"
            >
                <input type="hidden" name="org_uuid" value="<?php echo esc_attr($org_uuid); ?>">
                <input type="hidden" name="org_dom_suffix" value="<?php echo esc_attr(sanitize_html_class($org_uuid ?: 'default')); ?>">
                <input type="hidden" name="membership_uuid" value="<?php echo esc_attr($membership_uuid ?? ''); ?>">
                <input type="hidden" name="person_uuid" data-attr:value="$currentMemberUuid">
                <input type="hidden" name="person_name" data-attr:value="$currentMemberName">
                <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wicket-orgman-update-permissions')); ?>">

                <?php if ($allow_relationship_editing && !empty($relationship_types)): ?>
                <div class="wt_mb-6">
                    <label class="wt_block wt_text-sm wt_font-medium wt_mb-2" for="edit-member-relationship-type">
                        <?php esc_html_e('Relationship Type', 'wicket-acc'); ?>
                    </label>
                    <select id="edit-member-relationship-type" name="relationship_type"
                        data-bind="currentMemberRelationshipType"
                        class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2">
                        <option value=""><?php esc_html_e('Select a relationship type', 'wicket-acc'); ?></option>
                        <?php foreach ($relationship_types as $type_key => $type_label): ?>
                            <option value="<?php echo esc_attr($type_key); ?>">
                                <?php echo esc_html($type_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="wt_mb-6">
                    <p class="wt_font-bold wt_mb-3"><?php esc_html_e('Roles', 'wicket-acc'); ?></p>
                    <?php if (!empty($available_roles)): ?>
                        <div class="wt_space-y-2">
                            <?php foreach ($available_roles as $slug => $role): ?>
                                <div class="wt_flex wt_items-center wt_gap-2">
                                    <label class="wt_flex wt_items-center wt_gap-2 wt_cursor-pointer">
                                        <input
                                            type="checkbox"
                                            name="roles[]"
                                            value="<?php echo esc_attr($slug); ?>"
                                            class="form-checkbox wt_h-4 wt_w-4 wt_text-bg-interactive wt_rounded wt_focus_ring-bg-interactive"
                                            data-attr:checked="$currentMemberRoles.includes('<?php echo esc_js($slug); ?>')"
                                        >
                                        <span class="wt_text-sm wt_text-content"><?php echo esc_html($role); ?></span>
                                        <?php if (!empty($role_descriptions[$slug])): ?>
                                            <span class="wt_text-sm wt_text-content-secondary wt_ml-1"><?php echo esc_html($role_descriptions[$slug]); ?></span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="wt_text-sm wt_text-content"><?php esc_html_e('No roles available.', 'wicket-acc'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="wt_flex wt_justify-end wt_gap-3" data-show="!$editPermissionsSuccess">
                    <button
                        type="button"
                        data-on:click="
                            $editPermissionsModalOpen = false;
                            <?php echo esc_attr($edit_permissions_reset_actions); ?>
                        "
                        class="button button--secondary wt_px-4 wt_py-2 wt_text-sm component-button"
                        data-class="{ 'wt_pointer-events-none': $editPermissionsSubmitting, 'wt_opacity-50': $editPermissionsSubmitting }"
                        data-attr:aria-disabled="$editPermissionsSubmitting ? 'true' : 'false'"
                    ><?php esc_html_e('Cancel', 'wicket-acc'); ?></button>
                    <button
                        type="submit"
                        class="button button--primary wt_button_submit_async wt_inline-flex wt_items-center wt_gap-2 wt_px-4 wt_py-2 wt_text-sm component-button"
                        data-class="{ 'wt_pointer-events-none': $editPermissionsSubmitting, 'wt_opacity-50': $editPermissionsSubmitting, 'wt_is-loading': $editPermissionsSubmitting }"
                        data-attr:aria-disabled="$editPermissionsSubmitting ? 'true' : 'false'"
                    >
                        <span class="wt_submit_label">
                            <?php esc_html_e('Save Permissions', 'wicket-acc'); ?>
                        </span>
                        <span
                            class="wt_loader wt_loader_button wt_submit_loader"
                            aria-hidden="true"></span>
                    </button>
                </div>
            </form>
            <div class="wt_flex wt_justify-end wt_pt-4" data-show="$editPermissionsSuccess">
                <button
                    type="button"
                    class="button button--primary wt_px-4 wt_py-2 wt_text-sm component-button"
                    data-on:click="
                        $editPermissionsModalOpen = false;
                        <?php echo esc_attr($edit_permissions_reset_actions); ?>
                    "
                ><?php esc_html_e('Close', 'wicket-acc'); ?></button>
            </div>
        </div>
    </dialog>
</div>
<?php endif; ?>

<?php if ($show_remove_button && !$is_hypermedia_request): ?>
<!-- Remove Member Modal -->
<div class="wt_mt-6">
    <dialog id="removeMemberModal" class="modal wt_m-auto max_wt_md wt_rounded-md wt_shadow-md backdrop_wt_bg-black-50"
        data-show="$removeMemberModalOpen"
        data-effect="if ($removeMemberModalOpen) el.showModal(); else el.close();"
        data-on:close="<?php echo esc_attr($remove_member_reset_actions); ?>">
        <div class="wt_bg-white wt_p-6 wt_relative">
            <button type="button" class="orgman-modal__close wt_absolute wt_right-4 wt_top-4 wt_text-lg wt_font-semibold"
                data-on:click="<?php echo esc_attr($remove_member_reset_actions); ?>" data-show="!$removeMemberSuccess"
                data-class="{ 'wt_pointer-events-none': $removeMemberSubmitting, 'wt_opacity-50': $removeMemberSubmitting }"
                data-attr:aria-disabled="$removeMemberSubmitting ? 'true' : 'false'">
                ×
            </button>
            <h2 class="wp-block-heading has-heading-sm-font-size wt_text-2xl wt_font-semibold wt_mb-4"><?php esc_html_e('Remove Member', 'wicket-acc'); ?></h2>
            <div id="remove-member-messages">
                <!-- Messages will be inserted here by Datastar -->
            </div>

            <div data-show="!$removeMemberSuccess">
                <p class="wt_mb-6">
                    <span data-class_wt_hidden="$currentRemoveMemberName === ''">
                        <?php echo esc_html(__('Are you sure you want to remove this member from the organization?', 'wicket-acc')); ?>
                    </span>
                    <span data-class_wt_hidden="$currentRemoveMemberName !== ''">
                        <?php echo esc_html__('Are you sure you want to remove', 'wicket-acc'); ?>
                        <span data-text="$currentRemoveMemberName"></span>&nbsp;<?php echo esc_html__('from this organization?', 'wicket-acc'); ?>
                    </span>
                    <br>
                    <?php esc_html_e('This action cannot be undone.', 'wicket-acc'); ?>
                </p>

                <form
                    method="POST"
                    data-on:submit="if(!$removeMemberSubmitting){ $removeMemberSubmitting = true; $membersLoading = true; @post('<?php echo esc_js($remove_member_endpoint); ?>', { contentType: 'form' }); }"
                    data-on:submit__prevent-default="true"
                    data-on:success="<?php echo esc_attr($remove_member_success_actions); ?>"
                    data-on:error="<?php echo esc_attr($remove_member_error_actions); ?>"
                    data-on:reset="$removeMemberSubmitting = false; $membersLoading = false"
                >
                    <input type="hidden" name="org_uuid" value="<?php echo esc_attr($org_uuid); ?>">
                    <input type="hidden" name="org_dom_suffix" value="<?php echo esc_attr(sanitize_html_class($org_uuid ?: 'default')); ?>">
                    <input type="hidden" name="membership_uuid" value="<?php echo esc_attr($membership_uuid ?? ''); ?>">
                    <input type="hidden" name="person_uuid" data-attr:value="$currentRemoveMemberUuid">
                    <input type="hidden" name="person_name" data-attr:value="$currentRemoveMemberName">
                    <input type="hidden" name="person_email" data-attr:value="$currentRemoveMemberEmail">
                    <input type="hidden" name="connection_id" data-attr:value="$currentRemoveMemberConnectionId">
                    <input type="hidden" name="person_membership_id" data-attr:value="$currentRemoveMemberPersonMembershipId">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wicket-orgman-remove-member')); ?>">

                    <div class="wt_flex wt_justify-end wt_gap-3">
                        <button
                            type="button"
                            data-on:click="<?php echo esc_attr($remove_member_reset_actions); ?>"
                            class="button button--secondary wt_px-4 wt_py-2 wt_text-sm component-button"
                            data-class="{ 'wt_pointer-events-none': $removeMemberSubmitting, 'wt_opacity-50': $removeMemberSubmitting }"
                            data-attr:aria-disabled="$removeMemberSubmitting ? 'true' : 'false'"
                        ><?php esc_html_e('Cancel', 'wicket-acc'); ?></button>
                        <button
                            type="submit"
                            class="button button--danger wt_button_submit_async wt_inline-flex wt_items-center wt_gap-2 wt_px-4 wt_py-2 wt_text-sm component-button"
                            data-class="{ 'wt_pointer-events-none': $removeMemberSubmitting, 'wt_opacity-50': $removeMemberSubmitting, 'wt_is-loading': $removeMemberSubmitting }"
                            data-attr:aria-disabled="$removeMemberSubmitting ? 'true' : 'false'"
                        >
                            <span class="wt_submit_label" data-show="!$removeMemberSubmitting">
                                <?php esc_html_e('Remove Member', 'wicket-acc'); ?>
                            </span>
                            <span
                                class="wt_loader wt_loader_button wt_submit_loader"
                                data-show="$removeMemberSubmitting"
                                aria-hidden="true"
                            ></span>
                        </button>
                    </div>
                </form>
            </div>
            <div class="wt_pt-4" data-show="$removeMemberSuccess">
                <?php if ($remove_member_auto_close_on_success) : ?>
                    <p class="wt_text-sm wt_text-content wt_mb-3" data-show="$autoCloseCountdown > 0"
                        data-on-interval__duration.1000="if ($autoCloseCountdown > 1) { $autoCloseCountdown-- } else if ($autoCloseCountdown === 1) { <?php echo esc_attr($remove_member_reset_actions); ?> }">
                        <?php esc_html_e('This dialog will close automatically in', 'wicket-acc'); ?>
                        <span class="wt_font-semibold" data-text="$autoCloseCountdown"></span>
                        <?php esc_html_e('seconds.', 'wicket-acc'); ?>
                    </p>
                <?php endif; ?>
                <div class="wt_flex wt_justify-end">
                    <button
                        type="button"
                        class="button button--primary wt_px-4 wt_py-2 wt_text-sm component-button"
                        data-on:click="<?php echo esc_attr($remove_member_reset_actions); ?>"
                    ><?php esc_html_e('Close', 'wicket-acc'); ?></button>
                </div>
            </div>
        </div>
    </dialog>
</div>
<?php endif; ?>
