<?php

declare(strict_types=1);

use WicketORM\Helpers as OrgHelpers;

/*
 * Unified members list partial.
 *
 * Expects (when available):
 * - $mode (string): direct|cascade|groups
 * - $members (array)
 * - $pagination (array)
 * - $org_uuid (string)
 * - $query (string)
 * - $members_list_endpoint (string)
 * - $members_list_target (string)
 * - $membership_uuid (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

$mode = isset($mode) ? (string) $mode : 'direct';
$org_uuid = isset($org_uuid) ? (string) $org_uuid : '';
$query = isset($query) ? (string) $query : '';

$members_list_target = isset($members_list_target)
    ? (string) $members_list_target
    : 'members-list-container-' . sanitize_html_class($org_uuid ?: 'default');

$members_list_endpoint = isset($members_list_endpoint)
    ? (string) $members_list_endpoint
    : OrgHelpers\template_url() . 'members-list';

$page = isset($pagination['currentPage']) ? (int) $pagination['currentPage'] : 1;
$total_pages = isset($pagination['totalPages']) ? (int) $pagination['totalPages'] : 1;
$page_size = isset($pagination['pageSize']) ? (int) $pagination['pageSize'] : 15;
$total_items = isset($pagination['totalItems']) ? (int) $pagination['totalItems'] : 0;

$members = isset($members) && is_array($members) ? $members : [];
$total_pages = max(1, $total_pages);
$page = min(max(1, $page), $total_pages);

$orgman_config = WicketORM\Services\ConfigService::getConfig();
$role_display_map = $orgman_config['access']['roles']['labels'] ?? [];
$presentation_config = is_array($orgman_config['presentation'] ?? null)
    ? $orgman_config['presentation']
    : [];
$member_list_config = is_array($presentation_config['member_list'] ?? null)
    ? $presentation_config['member_list']
    : [];
$ui_config = is_array($orgman_config['ui']['member_list'] ?? null)
    ? $orgman_config['ui']['member_list']
    : $member_list_config;
$show_edit_permissions_default = (bool) ($member_list_config['show_edit_permissions'] ?? true);
$show_remove_button_default = (bool) ($member_list_config['show_remove_button'] ?? true);
$seat_limit_message = (string) ($member_list_config['seat_limit_message'] ?? __('All seats have been assigned. Please purchase additional seats to add more members.', 'wicket-acc'));
$remove_policy_callout = is_array($member_list_config['remove_policy_callout'] ?? null)
    ? $member_list_config['remove_policy_callout']
    : [];
$remove_policy_callout_placement = (string) ($remove_policy_callout['placement'] ?? 'above_members');
$show_edit_permissions = isset($show_edit_permissions)
    ? (bool) $show_edit_permissions
    : $show_edit_permissions_default;

$groups_presentation_config = is_array($orgman_config['groups']['presentation'] ?? null)
    ? $orgman_config['groups']['presentation']
    : [];
if ($mode === 'groups' && isset($groups_presentation_config['show_edit_permissions'])) {
    $show_edit_permissions = (bool) $groups_presentation_config['show_edit_permissions'];
}

$account_status_config = is_array($ui_config['account_status'] ?? null)
    ? $ui_config['account_status']
    : [];
$canonical_account_status_config = is_array($member_list_config['account_status'] ?? null)
    ? $member_list_config['account_status']
    : [];
if ($canonical_account_status_config !== []) {
    $account_status_config = $canonical_account_status_config;
}
$show_account_status_default = (bool) ($account_status_config['enabled'] ?? true);
$show_account_status = isset($show_account_status) ? (bool) $show_account_status : $show_account_status_default;
$show_unconfirmed_label = (bool) ($account_status_config['show_unconfirmed_label'] ?? true);
$confirmed_tooltip = (string) ($account_status_config['confirmed_tooltip'] ?? __('Account confirmed', 'wicket-acc'));
$unconfirmed_tooltip = (string) ($account_status_config['unconfirmed_tooltip'] ?? __('Account not confirmed', 'wicket-acc'));
$unconfirmed_label = (string) ($account_status_config['unconfirmed_label'] ?? __('Account not confirmed', 'wicket-acc'));
$show_add_member_button = isset($show_add_member_button) ? (bool) $show_add_member_button : true;
$show_remove_button = isset($show_remove_button) ? (bool) $show_remove_button : $show_remove_button_default;
$show_bulk_upload = isset($show_bulk_upload)
    ? (bool) $show_bulk_upload
    : (bool) ($member_list_config['show_bulk_upload'] ?? false);

$base_query_args = [
    'org_uuid' => $org_uuid,
    'query' => $query,
    'size' => $page_size,
];

$membership_uuid = isset($membership_uuid) ? (string) $membership_uuid : '';
if ($mode !== 'groups' && $membership_uuid !== '') {
    $base_query_args['membership_uuid'] = $membership_uuid;
}

if ($mode === 'groups' && isset($group_uuid) && $group_uuid !== '') {
    $base_query_args['group_uuid'] = (string) $group_uuid;
}

$build_url = static function (int $page_number) use ($members_list_endpoint, $base_query_args) {
    $args = array_merge($base_query_args, ['page' => $page_number]);
    $separator = str_contains($members_list_endpoint, '?') ? '&' : '?';
    $query_args = http_build_query($args, '', '&', PHP_QUERY_RFC3986);

    return $members_list_endpoint . $separator . $query_args;
};

$build_action = static function (int $page_number) use ($build_url) {
    return '$listLoading = true; @get(\'' . $build_url($page_number) . '\')';
};

$max_seats = null;
$active_seats = 0;
$has_seats_available = true;
$current_user_uuid = function_exists('wicket_current_person_uuid') ? wicket_current_person_uuid() : null;

if ($membership_uuid !== '') {
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

// Lazy-detail endpoint: each card initially renders with only the data that's cheap to
// compute server-side (name, email, owner, confirmed_at). Org-scoped roles, relationship
// names, and description require per-person API calls and are deferred to this endpoint,
// which patches fragments back via SSE into #member-status-<uuid> and #member-details-<uuid>.
$lazy_details_base_args = [
    'template'        => 'member-details',
    'org_uuid'        => (string) $org_uuid,
    'membership_uuid' => (string) $membership_uuid,
];
if ($mode === 'groups') {
    $lazy_details_base_args['mode'] = 'groups';
    if (isset($group_uuid) && $group_uuid !== '') {
        $lazy_details_base_args['group_uuid'] = (string) $group_uuid;
    }
}

$role_label = __('Role(s):', 'wicket-acc');
$show_remove_policy_callout = (
    $mode !== 'groups'
    && !$show_remove_button
    && !empty($remove_policy_callout['enabled'])
    && !empty($remove_policy_callout['message'])
);

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
    <div class="members-seat-summary wt_text-xl wt_font-semibold wt_mb-3">
        <?php if ($max_seats !== null): ?>
            <span class="members-seat-summary__label"><?php esc_html_e('Seats assigned:', 'wicket-acc'); ?></span>&nbsp;<span class="members-seat-summary__value"><?php echo esc_html((string) (int) $active_seats); ?></span><span class="members-seat-summary__separator">/</span><span class="members-seat-summary__max"><?php echo esc_html((string) (int) $max_seats); ?></span>
        <?php else: ?>
            <span class="members-seat-summary__label"><?php esc_html_e('Number of assigned people:', 'wicket-acc'); ?></span>&nbsp;<span class="members-seat-summary__value"><?php echo esc_html((string) (int) $total_items); ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($show_remove_policy_callout && $remove_policy_callout_placement === 'above_members') : ?>
        <?php
        $callout_content = '';
        if (!empty($remove_policy_callout['title'])) {
            $callout_content .= '<p class="wt_font-semibold wt_mb-1">' . esc_html((string) $remove_policy_callout['title']) . '</p>';
        }
        $callout_content .= '<p class="wt_mb-0">' . esc_html((string) $remove_policy_callout['message']);
        if (!empty($remove_policy_callout['email'])) {
            $callout_content .= '<br><a class="wt_text-interactive underline wt_hover_decoration-none" href="mailto:' . esc_attr((string) $remove_policy_callout['email']) . '">' . esc_html((string) $remove_policy_callout['email']) . '</a>';
        }
        $callout_content .= '</p>';

        get_component('alert', [
            'classes' => ['wt_mt-1', 'wt_mb-3', 'wt_p-4', 'wt_border', 'wt_border-yellow-200', 'wt_bg-yellow-50', 'wt_rounded-md', 'wt_text-sm', 'wt_text-yellow-900'],
            'content' => $callout_content,
        ]);
        ?>    <?php endif; ?>

    <?php if (empty($members)) : ?>
        <p class="wt_text-gray-500 wt_p-4"><?php esc_html_e('No members found.', 'wicket-acc'); ?></p>
    <?php else : ?>
        <?php foreach ($members as $member) :
            $member_uuid = $member['person_uuid'] ?? '';
            $member_name = $member['full_name'] ?? '';
            $member_email = $member['email'] ?? '';
            $member_title = $member['title'] ?? '';
            $member_role_label = $member['role'] ?? '';

            $current_roles = [];
            if (!empty($member['current_roles']) && is_array($member['current_roles'])) {
                $current_roles = $member['current_roles'];
            } elseif (!empty($member['roles']) && is_array($member['roles'])) {
                $current_roles = $member['roles'];
            } elseif ($member_role_label !== '') {
                $current_roles = [$member_role_label];
            }

            $formatted_roles = array_map(static function ($role) use ($role_display_map) {
                $role = (string) $role;
                if (isset($role_display_map[$role])) {
                    return $role_display_map[$role];
                }

                return ucwords(str_replace('_', ' ', $role));
            }, $current_roles);
            $roles_text = !empty($formatted_roles) ? implode(', ', $formatted_roles) : '—';

            $person_uuid_no_dashes = $member_uuid ? str_replace('-', '', $member_uuid) : uniqid('member', true);
            $lazy_loaded = !empty($member['lazy_loaded']);
            $is_confirmed = $show_account_status ? !empty($member['confirmed_at']) : null;
            $relationship_names = $member['relationship_names'] ?? '';
            $is_owner = !empty($member['is_owner']);
            $is_member_owner = $is_owner;
            $lazy_details_url = add_query_arg(
                array_merge($lazy_details_base_args, ['person_uuid' => (string) $member_uuid]),
                OrgHelpers\template_url()
            );
            ?>
            <div class="member-card wt_bg-light-neutral wt_rounded-card wt_p-6 wt_transition-opacity wt_duration-300"
                id="member-<?php echo esc_attr($person_uuid_no_dashes); ?>"
                data-show="!$listLoading">
                <div class="wt_flex wt_w-full md_wt_flex-row wt_items-start wt_justify-between wt_gap-4">
                    <div class="wt_flex wt_flex-col wt_gap-2 wt_w-full md_wt_w-4-5">
                        <div class="wt_flex wt_flex-col sm_wt_flex-row wt_items-start sm_wt_items-center wt_gap-2">
                            <div class="wt_flex wt_items-center wt_gap-2">
                                <?php if (OrgHelpers\Helper::should_show_member_name()) : ?>
                                <h3 class="wt_text-xl wt_font-medium wt_text-content wt_mb-0">
                                    <?php echo esc_html($member_name); ?>
                                </h3>
                                <?php endif; ?>
                                <div id="member-status-<?php echo esc_attr($person_uuid_no_dashes); ?>"
                                     data-member-status="<?php echo esc_attr($person_uuid_no_dashes); ?>"
                                     class="wt_inline-flex wt_items-center">
                                    <?php if ($show_account_status) : ?>
                                        <?php if ($is_confirmed !== null) : ?>
                                            <?php if ($is_confirmed) : ?>
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
                                        <?php else : ?>
                                            <span class="wt_skeleton wt_skeleton-badge" aria-hidden="true"></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($is_owner) : ?>
                            <div class="wt_flex wt_items-center wt_gap-2">
                                <strong><?php esc_html_e('Organization Owner', 'wicket-acc'); ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($member_title !== '' && OrgHelpers\Helper::should_show_member_job_title()) : ?>
                            <p class="member-job-title wt_text-sm wt_text-content">
                                <?php echo esc_html($member_title); ?>
                            </p>
                        <?php endif; ?>

                        <div id="member-details-<?php echo esc_attr($person_uuid_no_dashes); ?>"
                             data-member-details="<?php echo esc_attr($person_uuid_no_dashes); ?>"
                             class="wt_flex wt_flex-col wt_gap-2">
                            <?php if (!$lazy_loaded) : ?>
                                <div class="wt_p-2 wt_bg-gray-50 wt_rounded member-details-skeleton-<?php echo esc_attr($person_uuid_no_dashes); ?>"
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
                                if (!empty($member['relationship_description']) && OrgHelpers\Helper::should_show_member_description()) :
                                    $has_details = true;
                                    ?>
                                    <p class="member-description wt_text-sm wt_text-content wt_mb-0">
                                        <?php echo esc_html($member['relationship_description']); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($relationship_names !== '' && OrgHelpers\Helper::should_show_member_relationship_type()) :
                                    $has_details = true;
                                    ?>
                                    <div class="wt_flex wt_items-center wt_gap-2">
                                        <span class="wt_text-content"><?php echo esc_html($relationship_names); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($member_email !== '' && OrgHelpers\Helper::should_show_member_email()) :
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
                                        <strong><?php echo esc_html($role_label); ?></strong>
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
                        <?php if ($show_edit_permissions && $mode !== 'groups') : ?>
                            <button type="button" class="acc-edit-button edit-permissions-button button button--primary wt_inline-flex wt_items-center wt_justify-between wt_gap-2 wt_px-4 wt_py-2 wt_text-sm wt_border wt_border-bg-interactive wt_transition-colors wt_whitespace-nowrap component-button"
                                data-member-uuid="<?php echo esc_attr($member_uuid); ?>"
                                data-member-roles="<?php echo esc_attr(wp_json_encode(array_values((array) $current_roles))); ?>"
                                data-on:click="
                                    $editPermissionsSuccess = false;
                                    $editPermissionsSubmitting = false;
                                    (() => {
                                        const el = document.getElementById('update-permissions-messages');
                                        if (el) el.innerHTML = '';
                                    })();
                                    $currentMemberUuid = '';
                                    $currentMemberName = '';
                                    $currentMemberRoles = [];
                                    $currentMemberRelationshipType = '';
                                    $currentMemberDescription = '';
                                    $currentMemberUuid = '<?php echo esc_js($member_uuid); ?>';
                                    $currentMemberName = '<?php echo esc_js($member_name); ?>';
                                    (() => { try { $currentMemberRoles = JSON.parse(evt.currentTarget.dataset.memberRoles || '[]'); } catch (e) { $currentMemberRoles = []; } })();
                                    $currentMemberRelationshipType = '<?php echo esc_js($member['relationship_type'] ?? ''); ?>';
                                    $currentMemberDescription = '<?php echo esc_js($member['relationship_description'] ?? ''); ?>';
                                    $editPermissionsModalOpen = true
                                ">
                                <?php esc_html_e('Edit Permissions', 'wicket-acc'); ?>
                                <svg class="wt_w-4 wt_h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-6M18.414 8.414 19.5 7.328a2 2 0 0 0 0-2.828 2 2 0 0 0-2.828 0L15.586 5.586M18.414 8.414l-6.036 6.036a2 2 0 0 1-1.388.584L8.414 15.586l.586-2.95A2 2 0 0 1 10 11.248l5.586-5.662M18.414 8.414 15.586 5.586" />
                                </svg>
                            </button>
                        <?php endif; ?>

                        <?php if ($show_remove_button && !$is_member_owner) : ?>
                            <button type="button" class="acc-remove-button remove-member-button button button--secondary wt_inline-flex wt_items-center wt_justify-between wt_gap-2 wt_px-4 wt_py-2 wt_bg-light-neutral wt_text-sm wt_border wt_border-bg-interactive wt_transition-colors wt_whitespace-nowrap component-button"
                                data-on:click="
                                    $removeMemberSuccess = false;
                                    $removeMemberSubmitting = false;
                                    (() => { const el = document.getElementById('remove-member-messages'); if (el) el.innerHTML = ''; })();
                                    $currentRemoveMemberUuid = '<?php echo esc_js($member_uuid); ?>';
                                    $currentRemoveMemberName = '<?php echo esc_js($member_name); ?>';
                                    $currentRemoveMemberEmail = '<?php echo esc_js($member_email); ?>';
                                    <?php if ($mode === 'groups') : ?>
                                    $currentRemoveMemberGroupMemberId = '<?php echo esc_js($member['group_member_id'] ?? ''); ?>';
                                    $currentRemoveMemberRole = '<?php echo esc_js($member_role_label); ?>';
                                    <?php else : ?>
                                    $currentRemoveMemberConnectionId = '<?php echo esc_js($member['person_connection_ids'] ?? ''); ?>';
                                    $currentRemoveMemberPersonMembershipId = '<?php echo esc_js($member['person_membership_id'] ?? ''); ?>';
                                    <?php endif; ?>
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

    <?php if ($total_items > 0) : ?>
    <nav class="members-pagination wt_mt-6 wt_flex wt_flex-col wt_gap-4" aria-label="<?php esc_attr_e('Members pagination', 'wicket-acc'); ?>">
        <div class="members-pagination__info wt_w-full wt_text-left wt_text-sm wt_text-content">
            <?php
                $first = (($page - 1) * $page_size) + 1;
        $last = min($total_items, $page * $page_size);
        echo esc_html(sprintf(__('Showing %1$d–%2$d of %3$d', 'wicket-acc'), $first, $last, $total_items));
        ?>
        </div>
        <?php if ($total_pages > 1) : ?>
            <div class="members-pagination__controls wt_w-full wt_flex wt_items-center wt_gap-2 wt_justify-end wt_self-end">
                <?php $show_prev = $page > 1; ?>
                <?php if ($show_prev) : ?>
                    <?php
                get_component('button', [
                    'variant' => 'secondary',
                    'label' => __('Previous', 'wicket-acc'),
                    'type' => 'button',
                    'classes' => ['members-pagination__btn', 'members-pagination__btn--prev', 'wt_px-3', 'wt_py-2', 'wt_text-sm'],
                    'atts' => [
                        'data-on:click' => $build_action($page - 1),
                        'data-on:success' => '$listLoading = false; ' . wp_sprintf("select('#%s') | set(html)", $members_list_target),
                        'data-indicator:members-loading' => true,
                    ],
                ]);
                    ?>
                <?php endif; ?>
                <div class="members-pagination__pages wt_flex wt_items-center wt_gap-1">
                    <?php for ($i = 1; $i <= $total_pages; $i++) :
                        $is_current = ($i === $page);
                        $page_button_atts = [
                            'data-on:success' => '$listLoading = false; ' . wp_sprintf("select('#%s') | set(html)", $members_list_target),
                            'data-indicator:members-loading' => true,
                        ];

                        if (!$is_current) {
                            $page_button_atts['data-on:click'] = $build_action($i);
                        }

                        get_component('button', [
                            'variant' => $is_current ? 'primary' : 'secondary',
                            'label' => (string) $i,
                            'type' => 'button',
                            'classes' => ['members-pagination__btn', 'members-pagination__btn--page', 'wt_px-3', 'wt_py-2', 'wt_text-sm'],
                            'disabled' => $is_current,
                            'atts' => $page_button_atts,
                        ]);
                        ?>
                    <?php endfor; ?>
                </div>
                <?php $show_next = $page < $total_pages; ?>
                <?php if ($show_next) : ?>
                    <?php
                        get_component('button', [
                            'variant' => 'secondary',
                            'label' => __('Next', 'wicket-acc'),
                            'type' => 'button',
                            'classes' => ['members-pagination__btn', 'members-pagination__btn--next', 'wt_px-3', 'wt_py_2', 'wt_text-sm'],
                            'atts' => [
                                'data-on:click' => $build_action($page + 1),
                                'data-on:success' => '$listLoading = false; ' . wp_sprintf("select('#%s') | set(html)", $members_list_target),
                                'data-indicator:members-loading' => true,
                            ],
                        ]);
                    ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <?php if ($show_add_member_button) : ?>
        <div class="wt_mt-6">
            <?php if ($has_seats_available) : ?>
                <button type="button"
                    class="button button--primary add-member-button wt_w-full wt_py-2 component-button"
                    data-on:click="$addMemberSuccess = false; $addMemberSubmitting = false; $addMemberSuccessMessage = ''; (() => { const modal = document.getElementById('membersAddModal'); const form = modal ? modal.querySelector('form') : document.querySelector('#membersAddModal form'); if (form && form.reset) form.reset(); const messages = document.querySelector('[id^=\'add-member-messages-\']'); if (messages) messages.innerHTML = ''; })(); $addMemberModalOpen = true"><?php esc_html_e('Add Member', 'wicket-acc'); ?></button>
                <?php if ($mode !== 'groups' && $show_bulk_upload) : ?>
                    <div class="wt_mt-3">
                        <button type="button"
                            class="button button--primary add-member-button wt_w-full wt_py-2 component-button"
                            data-on:click="$bulkUploadModalOpen = true"><?php esc_html_e('Bulk Upload Members', 'wicket-acc'); ?></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!$has_seats_available) : ?>
            <?php
            get_component('alert', [
                'classes' => ['wt_mt-2', 'wt_p-3', 'wt_bg-yellow-50', 'wt_border', 'wt_border-yellow-200', 'wt_rounded-md', 'wt_text-yellow-800', 'wt_text-sm'],
                'content' => '<div class="wt_flex wt_items-center wt_gap-2"><svg class="wt_w-5 wt_h-5 wt_text-yellow-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg><span>' . esc_html($seat_limit_message) . '</span></div>',
            ]);
                ?>            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($show_remove_policy_callout && $remove_policy_callout_placement === 'below_members') : ?>
        <?php
        $callout_content = '';
        if (!empty($remove_policy_callout['title'])) {
            $callout_content .= '<p class="wt_font-semibold wt_mb-1">' . esc_html((string) $remove_policy_callout['title']) . '</p>';
        }
        $callout_content .= '<p class="wt_mb-0">' . esc_html((string) $remove_policy_callout['message']);
        if (!empty($remove_policy_callout['email'])) {
            $callout_content .= '<br><a class="wt_text-interactive underline wt_hover_decoration-none" href="mailto:' . esc_attr((string) $remove_policy_callout['email']) . '">' . esc_html((string) $remove_policy_callout['email']) . '</a>';
        }
        $callout_content .= '</p>';

        get_component('alert', [
            'classes' => ['wt_mt-2', 'wt_mb-0', 'wt_p-4', 'wt_border', 'wt_border-yellow-200', 'wt_bg-yellow-50', 'wt_rounded-md', 'wt_text-sm', 'wt_text-yellow-900'],
            'content' => $callout_content,
        ]);
        ?>
    <?php endif; ?>
    </div>
</div>
