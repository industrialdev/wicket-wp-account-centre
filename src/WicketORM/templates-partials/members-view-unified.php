<?php

declare(strict_types=1);

use WicketORM\Helpers as OrgHelpers;

if (!defined('ABSPATH')) {
    exit;
}

$mode = isset($mode) ? (string) $mode : 'direct';
$org_uuid = isset($org_uuid) ? (string) $org_uuid : '';
$group_uuid = isset($group_uuid) ? (string) $group_uuid : '';
$org_identifier = isset($org_identifier) ? (string) $org_identifier : '';

$orgman_config = WicketORM\Services\ConfigService::getConfig();
$presentation_config = is_array($orgman_config['presentation'] ?? null) ? $orgman_config['presentation'] : [];
$view_config = is_array($presentation_config['member_view'] ?? null) ? $presentation_config['member_view'] : [];
$member_list_config = is_array($presentation_config['member_list'] ?? null) ? $presentation_config['member_list'] : [];
$account_status_config = is_array($member_list_config['account_status'] ?? null) ? $member_list_config['account_status'] : [];
$groups_view_config = is_array($orgman_config['groups']['presentation'] ?? null) ? $orgman_config['groups']['presentation'] : [];

$search_clear_requires_submit = (bool) ($view_config['search_clear_requires_submit'] ?? false);
if ($mode === 'groups' && isset($groups_view_config['search_clear_requires_submit'])) {
    $search_clear_requires_submit = (bool) $groups_view_config['search_clear_requires_submit'];
}

$members = isset($members) && is_array($members) ? $members : [];
$pagination = isset($pagination) && is_array($pagination) ? $pagination : [];
$query = isset($query) ? (string) $query : '';
$membersResult = isset($membersResult) && is_array($membersResult) ? $membersResult : [];

$members_list_target = isset($members_list_target)
    ? (string) $members_list_target
    : 'members-list-container-' . sanitize_html_class($org_uuid ?: 'default');

$members_list_endpoint = isset($members_list_endpoint)
    ? (string) $members_list_endpoint
    : OrgHelpers\template_url() . 'members-list';

$members_list_separator = str_contains($members_list_endpoint, '?') ? '&' : '?';

if ($mode === 'groups') {
    $members_list_target = $members_list_target ?: 'group-members-list-container-' . sanitize_html_class($group_uuid ?: 'default');
}

$encoded_org_uuid = rawurlencode((string) $org_uuid);
$encoded_group_uuid = rawurlencode((string) $group_uuid);

$search_action = '';
if ($mode === 'groups') {
    $search_action = "@get('{$members_list_endpoint}{$members_list_separator}group_uuid={$encoded_group_uuid}&org_uuid={$encoded_org_uuid}&page=1&query=' + encodeURIComponent(" . '$searchQuery' . '))';
} else {
    $search_action = "@get('{$members_list_endpoint}{$members_list_separator}org_uuid={$encoded_org_uuid}&page=1&query=' + encodeURIComponent(" . '$searchQuery' . '))';
}
$search_success = '$listLoading = false; ' . wp_sprintf("select('#%s') | set(html)", $members_list_target);

$signals = [
    'searchQuery' => $query,
    'searchSubmitted' => false,
];

$membership_uuid = isset($membership_uuid) ? (string) $membership_uuid : '';
$membershipService = new WicketORM\Services\MembershipService();
$configService = new WicketORM\Services\ConfigService();
$additional_seats_service = new WicketORM\Services\AdditionalSeatsService($configService);
if ($membership_uuid === '' && $org_uuid !== '') {
    $membership_uuid = $membershipService->getMembershipForOrganization($org_uuid);
}
$encoded_membership_uuid = rawurlencode((string) $membership_uuid);
$membership_query_fragment = $membership_uuid !== '' ? "&membership_uuid={$encoded_membership_uuid}" : '';
if ($mode !== 'groups') {
    $search_action = "@get('{$members_list_endpoint}{$members_list_separator}org_uuid={$encoded_org_uuid}{$membership_query_fragment}&page=1&query=' + encodeURIComponent(" . '$searchQuery' . '))';
}

// Implementer setup warning: computed before role checks so admins always see it
// regardless of their org role.
$is_admin = current_user_can('administrator');
$additional_seats_enabled = $configService->isAdditionalSeatsEnabled();
$setup_issues = ($additional_seats_enabled && $is_admin)
    ? $additional_seats_service->getAdditionalSeatsSetupIssues()
    : [];

$can_purchase_seats = $org_uuid ? $additional_seats_service->canPurchaseAdditionalSeats($org_uuid) : false;
$purchase_url = ($can_purchase_seats && $membership_uuid)
    ? $additional_seats_service->getPurchaseFormUrl($org_uuid, $membership_uuid)
    : '';

$show_edit_permissions = isset($show_edit_permissions)
    ? (bool) $show_edit_permissions
    : (bool) ($member_list_config['show_edit_permissions'] ?? true);
if ($mode === 'groups') {
    $show_edit_permissions = (bool) ($groups_view_config['show_edit_permissions'] ?? false);
}
$show_remove_button_by_config = (bool) ($member_list_config['show_remove_button'] ?? true);
$show_bulk_upload = (bool) ($member_list_config['show_bulk_upload'] ?? false);
$show_add_member_button = true;
$show_remove_button = true;
if ($mode !== 'groups') {
    $show_add_member_button = OrgHelpers\PermissionHelper::can_add_members($org_uuid);
    $show_remove_button = $show_remove_button_by_config && OrgHelpers\PermissionHelper::can_remove_members($org_uuid);
}

$search_submit_action = '$listLoading = true; $searchSubmitted = true; ' . $search_action;
$clear_action = '(' . '$listLoading' . ' = true, ' . '$searchQuery' . " = '', " . '$searchSubmitted' . " = false, {$search_action})";

?>
<div class="members-list wt_relative" data-member-view="unified"
    data-signals:='<?php echo wp_json_encode([
        'membersLoading' => false,
        'listLoading' => false,
        'bulkUploadModalOpen' => false,
        'bulkUploadSubmitting' => false,
        'addMemberModalOpen' => false,
        'addMemberSubmitting' => false,
        'addMemberSuccess' => false,
        'addMemberSuccessMessage' => '',
        'autoCloseCountdown' => 0,
        'removeMemberModalOpen' => false,
        'removeMemberSubmitting' => false,
        'removeMemberSuccess' => false,
        'currentRemoveMemberUuid' => '',
        'currentRemoveMemberName' => '',
        'currentRemoveMemberEmail' => '',
        'currentRemoveMemberConnectionId' => '',
        'currentRemoveMemberPersonMembershipId' => '',
        'currentRemoveMemberGroupMemberId' => '',
        'currentRemoveMemberRole' => '',
        'editPermissionsModalOpen' => false,
        'editPermissionsSubmitting' => false,
        'editPermissionsSuccess' => false,
        'currentMemberUuid' => '',
        'currentMemberName' => '',
        'currentMemberRoles' => [],
        'currentMemberRelationshipType' => '',
        'currentMemberDescription' => '',
        'searchQuery' => $query,
        'searchSubmitted' => false,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>'
    data-on:datastar-fetch="if ((evt.detail.type === 'finished' || evt.detail.type === 'error') && evt.detail.el && evt.detail.el.closest('.members-search, .members-pagination')) { $listLoading = false }">

    <div class="members-search wt_flex wt_items-center wt_gap-2 wt_mb-6">
        <div class="members-search__field wt_relative wt_w-full">
            <div class="members-search__icon wt_absolute wt_inset-y-0 wt_left-0 wt_flex wt_items-center wt_pl-3 wt_pointer-events-none">
                <svg class="wt_w-5 wt_h-5 wt_text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <input
                type="text"
                id="<?php echo esc_attr(($mode === 'groups' ? 'group' : 'org') . '-members-search-' . sanitize_html_class($mode === 'groups' ? $group_uuid : $org_uuid)); ?>"
                data-bind="searchQuery"
                class="members-search__input wt_border wt_border-color wt_text-content wt_text-sm wt_rounded-md wt_focus_ring-2 wt_focus_ring-bg-interactive wt_focus_border-bg-interactive wt_block wt_w-full wt_pl-10 wt_p-2.5"
                placeholder="<?php esc_attr_e('Start typing to search for members...', 'wicket-acc'); ?>"
                data-on:keydown="if (evt.key === 'Enter') { <?php echo esc_attr($search_submit_action); ?> }"
                data-on:keydown__prevent-default="evt.key === 'Enter'"
                data-on:success="<?php echo esc_attr($search_success); ?>"
                data-indicator:members-loading>
        </div>
        <div class="members-search__actions wt_flex wt_items-center wt_gap-2">
            <button type="button" class="members-search__submit button button--primary wt_whitespace-nowrap component-button"
                data-on:click="<?php echo esc_attr($search_submit_action); ?>"
                data-on:success="<?php echo esc_attr($search_success); ?>"
                data-on:error="$listLoading = false"
                data-show="!$searchSubmitted"
                data-indicator:members-loading>
                <?php esc_html_e('Search', 'wicket-acc'); ?>
            </button>
            <button type="button" class="members-search__clear button button--secondary wt_whitespace-nowrap component-button"
                data-on:click="<?php echo esc_attr($clear_action); ?>"
                data-on:success="<?php echo esc_attr($search_success); ?>"
                data-on:error="$listLoading = false"
                data-show="$searchSubmitted && $searchQuery && $searchQuery.trim() !== ''"
                data-indicator:members-loading>
                <?php esc_html_e('Clear', 'wicket-acc'); ?>
            </button>
        </div>
    </div>

    <?php if ($mode === 'groups') : ?>
        <div id="group-member-messages" class="wt_mb-3"></div>
    <?php endif; ?>

    <?php
$members_list_endpoint = $members_list_endpoint;
$members_list_target = $members_list_target;
$show_edit_permissions = $show_edit_permissions;
$show_account_status = (bool) (($orgman_config['ui']['member_list']['account_status']['enabled'] ?? true));
if (array_key_exists('enabled', $account_status_config)) {
    $show_account_status = (bool) $account_status_config['enabled'];
}
$show_add_member_button = $show_add_member_button;
$show_remove_button = $show_remove_button;
include __DIR__ . '/members-list-unified.php';
?>

    <?php if (!empty($setup_issues)) : ?>
    <div class="orgman-setup-warning wt_mt-4 wt_p-4 wt_rounded-md" style="background:#fff8e1;border:2px solid #f9a825;color:#5d4037;">
        <h3 class="orgman-setup-warning__title wt_mt-0 wt_mb-2 wt_font-semibold wt_text-lg" style="display:flex;align-items:center;gap:0.4rem;">
            <span aria-hidden="true">⚠️</span> <?php esc_html_e('Visible to administrators only', 'wicket-acc'); ?>
        </h3>
        <p class="wt_font-semibold wt_mb-2">
            <?php esc_html_e('Additional Seats: Setup Incomplete.', 'wicket-acc'); ?>
        </p>
        <p class="wt_mb-2"><?php esc_html_e('The "Purchase Additional Seats" button is hidden because the following items are not yet configured:', 'wicket-acc'); ?></p>
        <ul style="list-style:disc;padding-left:1.25rem;margin:0 0 0.5rem;">
            <?php foreach ($setup_issues as $issue) : ?>
            <li><?php
                foreach ($issue['parts'] as $part) {
                    if ($part['type'] === 'token') {
                        echo '<code class="orgman-copy-token" data-copy-value="' . esc_attr($part['value']) . '" title="' . esc_attr__('Click to copy', 'wicket-acc') . '" style="cursor:pointer;background:#fff3cd;border:1px solid #f9a825;border-radius:3px;padding:1px 5px;font-family:monospace;font-size:0.9em;">' . esc_html($part['value']) . '</code>';
                    } else {
                        echo esc_html($part['value']);
                    }
                }
                ?></li>
            <?php endforeach; ?>
        </ul>
        <?php
        $orgman_cfg = $configService->getFullConfig();
$orgman_form_slug = $orgman_cfg['integrations']['additional_seats']['form_slug'] ?? 'additional-seats';
$orgman_form_slug = is_string($orgman_form_slug) ? trim($orgman_form_slug) : 'additional-seats';
$orgman_tier_field = $configService->getAdditionalSeatsTierSlugField();
$orgman_token_attrs = 'title="' . esc_attr__('Click to copy', 'wicket-acc') . '" style="cursor:pointer;background:#fff3cd;border:1px solid #f9a825;border-radius:3px;padding:1px 5px;font-family:monospace;font-size:0.9em;"';
?>
        <ul class="orgman-setup-warning__config" style="list-style:none;padding-left:0;margin:0.5rem 0 0;border-top:1px solid #f9a825;padding-top:0.5rem;opacity:0.85;">
            <li style="margin-bottom:0.5rem;">
                <strong><?php esc_html_e('Expected Gravity Form slug:', 'wicket-acc'); ?></strong>
                <code class="orgman-copy-token" data-copy-value="<?php echo esc_attr($orgman_form_slug); ?>" <?php echo $orgman_token_attrs; // phpcs:ignore?>><?php echo esc_html($orgman_form_slug); ?></code><br>
                <em style="display:block;margin-top:0.25rem;"><?php esc_html_e('Map this slug to the additional-seats Gravity Form under Gravity Forms > Wicket Settings > Form Slug ID Mapping.', 'wicket-acc'); ?></em>
            </li>
            <?php if ($orgman_tier_field !== '') : ?>
            <li style="margin-bottom:0;">
                <strong><?php esc_html_e('Tier slug hidden-field parameter:', 'wicket-acc'); ?></strong>
                <code class="orgman-copy-token" data-copy-value="<?php echo esc_attr($orgman_tier_field); ?>" <?php echo $orgman_token_attrs; // phpcs:ignore?>><?php echo esc_html($orgman_tier_field); ?></code><br>
                <em style="display:block;margin-top:0.25rem;"><?php esc_html_e('Name of the hidden field (Parameter Name) on the form that receives the membership tier slug from the URL. GF conditional logic reads it to show only that tier’s quantity input, and the submission handler reads it to pick the right tier-specific product.', 'wicket-acc'); ?></em>
            </li>
            <?php endif; ?>
        </ul>
    </div>
    <script>
    (function () {
        if (window.__orgmanCopyTokenBound) return;
        window.__orgmanCopyTokenBound = true;
        document.addEventListener('click', function (e) {
            var token = e.target.closest('.orgman-copy-token');
            if (!token) return;
            var value = token.dataset.copyValue || token.textContent;
            if (!navigator.clipboard) return;
            navigator.clipboard.writeText(value).then(function () {
                var existing = token.querySelector('.orgman-copy-feedback');
                if (existing) existing.remove();
                var tip = document.createElement('span');
                tip.className = 'orgman-copy-feedback';
                tip.textContent = '✓ Copied!';
                tip.style.cssText = 'margin-left:6px;font-size:0.8em;color:#155724;font-family:sans-serif;font-weight:600;';
                token.appendChild(tip);
                token.style.background = '#d4edda';
                setTimeout(function () {
                    tip.remove();
                    token.style.background = '#fff3cd';
                }, 1500);
            });
        });
    }());
    </script>
    <?php endif; ?>

    <?php if ($can_purchase_seats && !empty($purchase_url)) : ?>
        <?php
    get_component('card-call-out', [
'title' => __('Need More Seats?', 'wicket-acc'),
'description' => __('Purchase additional seats for your organization membership to accommodate more team members.', 'wicket-acc'),
'style' => 'secondary',
'links' => [
    [
        'link' => [
            'title' => __('Purchase Additional Seats', 'wicket-acc'),
            'url' => $purchase_url,
            'target' => '_self',
        ],
        'link_style' => 'secondary',
    ],
],
'classes' => ['my-3'],
    ]);
        ?>
    <?php endif; ?>

    <?php
$groups_config = is_array($orgman_config['groups'] ?? null) ? $orgman_config['groups'] : [];
$groups_presentation = is_array($groups_config['presentation'] ?? null)
    ? $groups_config['presentation']
    : (is_array($groups_config['ui'] ?? null) ? $groups_config['ui'] : []);
$group_add_member_auto_close_on_success = (bool) ($groups_presentation['add_member_auto_close_on_success'] ?? false);
$group_add_member_auto_close_delay_seconds = max(0, (int) ($groups_presentation['add_member_auto_close_delay_seconds'] ?? 7));
$group_add_member_auto_close_delay_ms = $group_add_member_auto_close_delay_seconds * 1000;
$add_member_modal_reset_actions = "(() => { const modal = document.getElementById('membersAddModal'); const directMessages = modal ? modal.querySelector('[id^=\"add-member-messages-\"]') : document.querySelector('[id^=\"add-member-messages-\"]'); const groupMessages = modal ? modal.querySelector('#group-member-add-messages') : document.getElementById('group-member-add-messages'); if (directMessages) directMessages.innerHTML = ''; if (groupMessages) groupMessages.innerHTML = ''; const form = modal ? modal.querySelector('form') : document.querySelector('#membersAddModal form'); if (form && form.reset) form.reset(); })(); \$membersLoading = false; \$addMemberSubmitting = false; \$addMemberSuccess = false; \$autoCloseCountdown = 0; \$addMemberModalOpen = false; \$addMemberSuccessMessage = '';";
$add_member_request_close_actions = '$addMemberModalOpen = false;';
$org_add_member_auto_close_on_success = (bool) ($view_config['add_member_auto_close_on_success'] ?? false);
$org_add_member_auto_close_delay_seconds = max(0, (int) ($view_config['add_member_auto_close_delay_seconds'] ?? 7));
$add_member_success_actions = "console.log('Member added successfully'); \$addMemberSubmitting = false; \$membersLoading = false; \$addMemberSuccess = true;";
if ($org_add_member_auto_close_on_success && $org_add_member_auto_close_delay_seconds > 0) {
    $add_member_success_actions .= " \$autoCloseCountdown = {$org_add_member_auto_close_delay_seconds};";
}
$group_add_member_success_actions = "console.log('Group member added successfully'); \$addMemberSubmitting = false; \$membersLoading = false; \$addMemberSuccess = true;";
if ($group_add_member_auto_close_on_success && $group_add_member_auto_close_delay_seconds > 0) {
    $group_add_member_success_actions .= " \$autoCloseCountdown = {$group_add_member_auto_close_delay_seconds};";
}
$add_member_error_actions = "console.error('Failed to add member'); \$addMemberSubmitting = false; \$membersLoading = false; \$addMemberSuccess = false; \$autoCloseCountdown = 0;";
if ($clear_form_on_error) {
    $add_member_error_actions .= " el.closest('form').reset();";
}
$add_member_auto_close_enabled = ($mode === 'groups')
    ? $group_add_member_auto_close_on_success
    : $org_add_member_auto_close_on_success;

$remove_member_success_actions = "console.log('Member removed successfully'); \$removeMemberSubmitting = false; \$membersLoading = false; \$removeMemberSuccess = true;";
$remove_member_error_actions = "console.error('Failed to remove member'); \$removeMemberSubmitting = false; \$membersLoading = false;";
$remove_member_modal_id = 'membersRemoveModal';
if ($mode === 'groups') {
    $remove_member_modal_id = 'membersRemoveModal';
}
$remove_member_reset_actions = "(() => { const modal = document.getElementById('{$remove_member_modal_id}'); const messages = modal ? modal.querySelector('#remove-member-messages') : document.getElementById('remove-member-messages'); if (messages) messages.innerHTML = ''; })(); \$removeMemberModalOpen = false; \$removeMemberSubmitting = false; \$removeMemberSuccess = false; \$membersLoading = false; \$autoCloseCountdown = 0; \$currentRemoveMemberUuid = ''; \$currentRemoveMemberName = ''; \$currentRemoveMemberEmail = ''; \$currentRemoveMemberConnectionId = ''; \$currentRemoveMemberPersonMembershipId = ''; \$currentRemoveMemberGroupMemberId = ''; \$currentRemoveMemberRole = '';";
$remove_member_request_close_actions = '$removeMemberModalOpen = false;';
$remove_member_auto_close_enabled = ($mode === 'groups')
    ? $group_add_member_auto_close_on_success
    : $org_add_member_auto_close_on_success;
?>

    <?php if ($mode !== 'groups' && $show_edit_permissions) : ?>
        <?php
    $permissionService = new WicketORM\Services\PermissionService();
        $available_roles = $permissionService->getAvailableRoles();
        $role_descriptions = $orgman_config['access']['roles']['descriptions'] ?? [];

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

        $form_config = $orgman_config['member_management']['forms']['add_member']['fields'] ?? [];
        $clear_form_on_error = $orgman_config['member_management']['forms']['add_member']['clear_form_on_error'] ?? false;
        $allow_relationship_editing = $orgman_config['member_management']['forms']['add_member']['allow_relationship_type_editing'] ?? false;
        $relationship_types = $orgman_config['relationships']['labels']['custom'] ?? [];
        $update_permissions_endpoint = OrgHelpers\template_url() . 'process/update-permissions';
        $update_permissions_local_sync_actions = "(() => { const modal = document.getElementById('editPermissionsModal'); if (!modal) return; const selected = Array.from(modal.querySelectorAll('input[name=\"roles[]\"]:checked')).map((node) => node.value); const selectedJson = JSON.stringify(selected); document.querySelectorAll('.edit-permissions-button[data-member-uuid=\"' + \$currentMemberUuid + '\"]').forEach((btn) => { btn.dataset.memberRoles = selectedJson; }); \$currentMemberRoles = selected; })();";
        $update_permissions_success_actions = "console.log('Permissions updated successfully'); \$editPermissionsSubmitting = false; \$editPermissionsSuccess = true; \$membersLoading = false; {$update_permissions_local_sync_actions}";
        $update_permissions_error_actions = "console.error('Failed to update permissions'); \$editPermissionsSubmitting = false; \$membersLoading = false;";
        $edit_permissions_reset_actions = "\$editPermissionsSuccess = false; \$editPermissionsSubmitting = false; \$currentMemberUuid = ''; \$currentMemberName = ''; \$currentMemberRoles = []; \$currentMemberRelationshipType = ''; \$currentMemberDescription = ''; (() => { const messages = document.getElementById('update-permissions-messages'); if (messages) messages.innerHTML = ''; })();";
        ?>
        <div class="wt_mt-6" data-signals='{"editPermissionsModalOpen": false, "editPermissionsSubmitting": false, "editPermissionsSuccess": false, "currentMemberUuid": "", "currentMemberName": "", "currentMemberRoles": [], "currentMemberRelationshipType": "", "currentMemberDescription": "", "removeMemberModalOpen": false, "removeMemberSubmitting": false, "removeMemberSuccess": false, "currentRemoveMemberUuid": "", "currentRemoveMemberName": "", "currentRemoveMemberEmail": "", "currentRemoveMemberConnectionId": "", "currentRemoveMemberPersonMembershipId": ""}'>
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

                    <div id="update-permissions-messages"></div>

                    <form
                        method="POST"
                        data-show="!$editPermissionsSuccess"
                        data-on:submit="if(!$editPermissionsSubmitting){ $editPermissionsSubmitting = true; $membersLoading = true; @post('<?php echo esc_js($update_permissions_endpoint); ?>', { contentType: 'form' }); }"
                        data-on:submit__prevent-default="true"
                        data-on:success="<?php echo esc_attr($update_permissions_success_actions); ?>"
                        data-on:error="<?php echo esc_attr($update_permissions_error_actions); ?>"
                        data-on:reset="$editPermissionsSubmitting = false; $membersLoading = false">
                        <input type="hidden" name="org_uuid" value="<?php echo esc_attr($org_uuid); ?>">
                        <input type="hidden" name="org_dom_suffix" value="<?php echo esc_attr(sanitize_html_class($org_uuid ?: 'default')); ?>">
                        <input type="hidden" name="membership_uuid" value="<?php echo esc_attr($membership_uuid); ?>">
                        <input type="hidden" name="person_uuid" data-attr:value="$currentMemberUuid">
                        <input type="hidden" name="person_name" data-attr:value="$currentMemberName">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wicket-orgman-update-permissions')); ?>">

                        <?php if ($allow_relationship_editing && !empty($relationship_types)) : ?>
                            <div class="wt_mb-6">
                                <label class="wt_block wt_text-sm wt_font-medium wt_mb-2" for="edit-member-relationship-type">
                                    <?php esc_html_e('Relationship Type', 'wicket-acc'); ?>
                                </label>
                                <select id="edit-member-relationship-type" name="relationship_type"
                                    data-bind="currentMemberRelationshipType"
                                    class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2">
                                    <option value=""><?php esc_html_e('Select a relationship type', 'wicket-acc'); ?></option>
                                    <?php foreach ($relationship_types as $type_key => $type_label) : ?>
                                        <option value="<?php echo esc_attr($type_key); ?>">
                                            <?php echo esc_html($type_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <?php if ($form_config['description']['enabled'] ?? false): ?>
                            <div class="wt_mb-6">
                                <label class="wt_block wt_text-sm wt_font-medium wt_mb-2" for="edit-member-description">
                                    <?php echo esc_html($form_config['description']['label'] ?? __('Description', 'wicket-acc')); ?>
                                </label>
                                <?php if (($form_config['description']['input_type'] ?? 'textarea') === 'text'): ?>
                                    <input type="text" id="edit-member-description" name="description"
                                        data-bind="currentMemberDescription"
                                        class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2">
                                <?php else: ?>
                                    <textarea id="edit-member-description" name="description"
                                        data-bind="currentMemberDescription"
                                        class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2"
                                        rows="3"></textarea>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="wt_mb-6">
                            <p class="wt_font-bold wt_mb-3"><?php esc_html_e('Roles', 'wicket-acc'); ?></p>
                            <?php if (!empty($available_roles)) : ?>
                                <div class="wt_space-y-2">
                                    <?php foreach ($available_roles as $slug => $role) : ?>
                                        <div class="wt_flex wt_items-center wt_gap-2">
                                            <label class="wt_flex wt_items-center wt_gap-2 wt_cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    name="roles[]"
                                                    value="<?php echo esc_attr($slug); ?>"
                                                    class="form-checkbox wt_h-4 wt_w-4 wt_text-bg-interactive wt_rounded wt_focus_ring-bg-interactive"
                                                    data-attr:checked="$currentMemberRoles.includes('<?php echo esc_js($slug); ?>')">
                                                <span class="wt_text-sm wt_text-content"><?php echo esc_html($role); ?></span>
                                                <?php if (!empty($role_descriptions[$slug])): ?>
                                                    <span class="wt_text-sm wt_text-content-secondary wt_ml-1"><?php echo esc_html($role_descriptions[$slug]); ?></span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
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
                                data-attr:aria-disabled="$editPermissionsSubmitting ? 'true' : 'false'"><?php esc_html_e('Cancel', 'wicket-acc'); ?></button>
                            <button
                                type="submit"
                                class="button button--primary wt_button_submit_async wt_inline-flex wt_items-center wt_gap-2 wt_px-4 wt_py-2 wt_text-sm component-button"
                                data-class="{ 'wt_pointer-events-none': $editPermissionsSubmitting, 'wt_opacity-50': $editPermissionsSubmitting, 'wt_is-loading': $editPermissionsSubmitting }"
                                data-attr:aria-disabled="$editPermissionsSubmitting ? 'true' : 'false'">
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

    <?php
    $add_member_endpoint = ($mode === 'groups')
        ? OrgHelpers\template_url() . 'process/add-group-member'
        : OrgHelpers\template_url() . 'process/add-member';
?>

    <dialog id="membersAddModal" class="modal wt_m-auto max_wt_3xl wt_rounded-md wt_shadow-md backdrop_wt_bg-black-50"
        data-show="$addMemberModalOpen"
        data-effect="if ($addMemberModalOpen) el.showModal(); else el.close();"
        data-on:close="<?php echo esc_attr($add_member_modal_reset_actions); ?>">
        <div class="wt_bg-white wt_p-6 wt_relative">
            <button type="button" class="orgman-modal__close wt_absolute wt_right-4 wt_top-4 wt_text-lg wt_font-semibold"
                data-on:click="<?php echo esc_attr($add_member_request_close_actions); ?>" data-show="!$addMemberSuccess"
                data-class="{ 'wt_pointer-events-none': $addMemberSubmitting, 'wt_opacity-50': $addMemberSubmitting }"
                data-attr:aria-disabled="$addMemberSubmitting ? 'true' : 'false'">
                ×
            </button>

            <h2 class="wp-block-heading has-heading-sm-font-size wt_text-2xl wt_font-semibold wt_mb-4">
                <?php esc_html_e('Add Member', 'wicket-acc'); ?>
            </h2>

            <?php if ($mode === 'groups') : ?>
                <div id="group-member-add-messages" class="wt_mb-3"></div>
                <form
                    method="post"
                    class="wt_flex wt_flex-col wt_gap-4"
                    data-show="!$addMemberSuccess"
                    data-on:submit="if(!$addMemberSubmitting){ $addMemberSubmitting = true; $membersLoading = true; @post('<?php echo esc_js($add_member_endpoint); ?>', { contentType: 'form' }); }"
                    data-on:submit__prevent-default="true"
                    data-on:success="<?php echo esc_attr($group_add_member_success_actions); ?>"
                    data-on:error="<?php echo esc_attr($add_member_error_actions); ?>"
                    data-on:datastar-fetch="if (evt.detail.type === 'finished' && typeof $addMemberFormError !== 'undefined' && $addMemberFormError) { if (el && el.reset) el.reset(); $addMemberFormError = false; }"
                    data-on:reset="$addMemberSubmitting = false">
                    <input type="hidden" name="group_uuid" value="<?php echo esc_attr($group_uuid); ?>">
                    <input type="hidden" name="org_uuid" value="<?php echo esc_attr($org_uuid); ?>">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wicket-orgman-add-group-member')); ?>">

                    <div>
                        <label class="wt_block wt_text-sm wt_font-medium wt_mb-1" for="group-member-first-name">
                            <?php esc_html_e('First Name', 'wicket-acc'); ?>
                        </label>
                        <input id="group-member-first-name" name="first_name" type="text"
                            class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2" required>
                    </div>
                    <div>
                        <label class="wt_block wt_text-sm wt_font-medium wt_mb-1" for="group-member-last-name">
                            <?php esc_html_e('Last Name', 'wicket-acc'); ?>
                        </label>
                        <input id="group-member-last-name" name="last_name" type="text"
                            class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2" required>
                    </div>
                    <div>
                        <label class="wt_block wt_text-sm wt_font-medium wt_mb-1" for="group-member-email">
                            <?php esc_html_e('Email Address', 'wicket-acc'); ?>
                        </label>
                        <input id="group-member-email" name="email" type="email"
                            class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2"
                            placeholder="<?php echo esc_attr(__('user@mail.com', 'wicket-acc')); ?>" required>
                    </div>
                    <div>
                        <label class="wt_block wt_text-sm wt_font-medium wt_mb-1" for="group-member-role">
                            <?php esc_html_e('Role', 'wicket-acc'); ?>
                        </label>
                        <select id="group-member-role" name="role"
                            class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2">
                            <?php
                        $groups_config = is_array($orgman_config['groups'] ?? null) ? $orgman_config['groups'] : [];
                $group_roles = is_array($groups_config['roles'] ?? null) ? $groups_config['roles'] : [];
                $member_role = $group_roles['member'] ?? ($groups_config['member_role'] ?? 'member');
                $observer_role = $group_roles['observer'] ?? ($groups_config['observer_role'] ?? 'observer');
                ?>
                            <option value="<?php echo esc_attr($member_role); ?>"><?php esc_html_e('Member', 'wicket-acc'); ?></option>
                            <option value="<?php echo esc_attr($observer_role); ?>"><?php esc_html_e('Observer', 'wicket-acc'); ?></option>
                        </select>
                    </div>

                    <div class="wt_flex wt_justify-end wt_gap-3 wt_pt-4" data-show="!$addMemberSuccess">
                        <button type="button" class="button button--secondary component-button"
                            data-on:click="<?php echo esc_attr($add_member_request_close_actions); ?>"
                            data-class="{ 'wt_pointer-events-none': $addMemberSubmitting, 'wt_opacity-50': $addMemberSubmitting }"
                            data-attr:aria-disabled="$addMemberSubmitting ? 'true' : 'false'"><?php esc_html_e('Cancel', 'wicket-acc'); ?></button>
                        <button type="submit" class="button button--primary wt_button_submit_async wt_inline-flex wt_items-center wt_gap-2 component-button"
                            data-class="{ 'wt_pointer-events-none': $addMemberSubmitting, 'wt_opacity-50': $addMemberSubmitting, 'wt_is-loading': $addMemberSubmitting }"
                            data-attr:aria-disabled="$addMemberSubmitting ? 'true' : 'false'">
                            <span class="wt_submit_label">
                                <?php esc_html_e('Add Member', 'wicket-acc'); ?>
                            </span>
                            <span class="wt_loader wt_loader_button wt_submit_loader"
                                aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
                <div class="wt_pt-4" data-show="$addMemberSuccess">
                    <?php if ($add_member_auto_close_enabled) : ?>
                        <p class="wt_text-sm wt_text-content wt_mb-3" data-show="$autoCloseCountdown > 0"
                            data-on-interval__duration.1000="if ($autoCloseCountdown > 1) { $autoCloseCountdown-- } else if ($autoCloseCountdown === 1) { <?php echo esc_attr($add_member_request_close_actions); ?> }">
                            <?php esc_html_e('This dialog will close automatically in', 'wicket-acc'); ?>
                            <span class="wt_font-semibold" data-text="$autoCloseCountdown"></span>
                            <?php esc_html_e('seconds.', 'wicket-acc'); ?>
                        </p>
                    <?php endif; ?>
                    <div class="wt_mb-4 wt_bg-green-100 wt_border wt_border-green-400 wt_text-green-700 wt_px-4 wt_py-3 wt_rounded-sm" data-show="$addMemberSuccessMessage !== ''">
                        <p><strong><?php esc_html_e('Success!', 'wicket-acc'); ?></strong></p>
                        <p data-text="$addMemberSuccessMessage"></p>
                    </div>
                    <div class="wt_flex wt_justify-end">
                        <button type="button" class="button button--primary component-button"
                            data-on:click="<?php echo esc_attr($add_member_request_close_actions); ?>">
                            <?php esc_html_e('Close', 'wicket-acc'); ?>
                        </button>
                    </div>
                </div>
            <?php else : ?>
                <div id="add-member-messages-<?php echo esc_attr(sanitize_html_class($org_uuid ?: 'default')); ?>"></div>
                <form name="add_new_person_membership_form" id="add_new_person_membership_form"
                    class="wt_flex wt_flex-col wt_gap-4" method="POST"
                    data-show="!$addMemberSuccess"
                    data-on:submit="if(!$addMemberSubmitting){ $addMemberSubmitting = true; $membersLoading = true; @post('<?php echo esc_js($add_member_endpoint); ?>', { contentType: 'form' }); }"
                    data-on:submit__prevent-default="true"
                    data-on:success="<?php echo esc_attr($add_member_success_actions); ?>"
                    data-on:error="<?php echo esc_attr($add_member_error_actions); ?>"
                    data-on:datastar-fetch="if (evt.detail.type === 'finished' && typeof $addMemberFormError !== 'undefined' && $addMemberFormError) { if (el && el.reset) el.reset(); $addMemberFormError = false; }"
                    data-on:reset="$addMemberSubmitting = false">
                    <input type="hidden" name="org_uuid" value="<?php echo esc_attr($org_uuid); ?>">
                    <input type="hidden" name="org_dom_suffix" value="<?php echo esc_attr(sanitize_html_class($org_uuid ?: 'default')); ?>">
                    <input type="hidden" name="membership_id" value="<?php echo esc_attr($membership_uuid); ?>">
                    <input type="hidden" name="included_id" value="<?php echo esc_attr($membersResult['included_id'] ?? ''); ?>">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wicket-orgman-add-member')); ?>">

                    <?php
                    $form_config = $orgman_config['member_management']['forms']['add_member']['fields'] ?? [];
                $relationship_types = $orgman_config['relationships']['labels']['custom'] ?? [];
                ?>

                    <?php if ($form_config['first_name']['enabled'] ?? false) : ?>
                        <div>
                            <label class="wt_block wt_text-sm wt_font-medium wt_mb-1" for="new-member-first-name">
                                <?php echo esc_html($form_config['first_name']['label'] ?? __('First Name', 'wicket-acc')); ?>
                                <?php echo ($form_config['first_name']['required'] ?? false) ? '*' : ''; ?>
                            </label>
                            <input type="text" id="new-member-first-name" name="first_name"
                                <?php echo ($form_config['first_name']['required'] ?? false) ? 'required' : ''; ?>
                                class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2">
                        </div>
                    <?php endif; ?>

                    <?php if ($form_config['last_name']['enabled'] ?? false) : ?>
                        <div>
                            <label class="wt_block wt_text-sm wt_font-medium wt_mb-1" for="new-member-last-name">
                                <?php echo esc_html($form_config['last_name']['label'] ?? __('Last Name', 'wicket-acc')); ?>
                                <?php echo ($form_config['last_name']['required'] ?? false) ? '*' : ''; ?>
                            </label>
                            <input type="text" id="new-member-last-name" name="last_name"
                                <?php echo ($form_config['last_name']['required'] ?? false) ? 'required' : ''; ?>
                                class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2">
                        </div>
                    <?php endif; ?>

                    <?php if ($form_config['email']['enabled'] ?? false) : ?>
                        <div>
                            <label class="wt_block wt_text-sm wt_font-medium wt_mb-1" for="new-member-email">
                                <?php echo esc_html($form_config['email']['label'] ?? __('Email Address', 'wicket-acc')); ?>
                                <?php echo ($form_config['email']['required'] ?? false) ? '*' : ''; ?>
                            </label>
                            <input type="email" id="new-member-email" name="email"
                                <?php echo ($form_config['email']['required'] ?? false) ? 'required' : ''; ?>
                                class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2"
                                placeholder="<?php echo esc_attr(__('user@mail.com', 'wicket-acc')); ?>">
                        </div>
                    <?php endif; ?>

                    <?php if (($form_config['relationship_type']['enabled'] ?? false) && !empty($relationship_types)) : ?>
                        <div>
                            <label class="wt_block wt_text-sm wt_font-medium wt_mb-1" for="new-member-relationship-type">
                                <?php echo esc_html($form_config['relationship_type']['label'] ?? __('Relationship Type', 'wicket-acc')); ?>
                                <?php echo ($form_config['relationship_type']['required'] ?? false) ? '*' : ''; ?>
                            </label>
                            <select id="new-member-relationship-type" name="relationship_type"
                                <?php echo ($form_config['relationship_type']['required'] ?? false) ? 'required' : ''; ?>
                                class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2">
                                <option value=""><?php esc_html_e('Select a relationship type', 'wicket-acc'); ?></option>
                                <?php foreach ($relationship_types as $type_key => $type_label) : ?>
                                    <option value="<?php echo esc_attr($type_key); ?>">
                                        <?php echo esc_html($type_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($form_config['description']['enabled'] ?? false): ?>
                        <div>
                            <label class="wt_block wt_text-sm wt_font-medium wt_mb-1" for="new-member-description">
                                <?php echo esc_html($form_config['description']['label'] ?? __('Description', 'wicket-acc')); ?>
                                <?php echo ($form_config['description']['required'] ?? false) ? '*' : ''; ?>
                            </label>
                            <?php if (($form_config['description']['input_type'] ?? 'textarea') === 'text'): ?>
                                <input type="text" id="new-member-description" name="description"
                                    <?php echo ($form_config['description']['required'] ?? false) ? 'required' : ''; ?>
                                    class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2">
                            <?php else: ?>
                                <textarea id="new-member-description" name="description"
                                    <?php echo ($form_config['description']['required'] ?? false) ? 'required' : ''; ?>
                                    class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2"
                                    rows="3"></textarea>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php
                $permissions_field_config = $orgman_config['member_management']['forms']['add_member']['fields']['permissions'] ?? [];
$allowed_roles = $permissions_field_config['allowlist'] ?? [];
$excluded_roles = $permissions_field_config['denylist'] ?? [];
$permissionService = new WicketORM\Services\PermissionService();
$available_roles = $permissionService->getAvailableRoles();
$role_descriptions = $orgman_config['access']['roles']['descriptions'] ?? [];
if (!empty($orgman_config['access']['permissions']['prevent_owner_assignment'])) {
    unset($available_roles['membership_owner']);
}
$available_roles = OrgHelpers\PermissionHelper::filter_role_choices(
    $available_roles,
    is_array($allowed_roles) ? $allowed_roles : [],
    is_array($excluded_roles) ? $excluded_roles : []
);
?>

                    <?php if (!empty($available_roles)) : ?>
                        <fieldset class="wt_flex wt_flex-col wt_gap-2">
                            <legend class="wt_text-sm wt_font-medium"><?php esc_html_e('Security Roles', 'wicket-acc'); ?></legend>
                            <?php foreach ($available_roles as $role_slug => $role_name) : ?>
                                <label class="wt_flex wt_items-center wt_gap-2">
                                    <input type="checkbox" name="roles[]" value="<?php echo esc_attr($role_slug); ?>" class="form-checkbox">
                                    <span><?php echo esc_html($role_name); ?></span>
                                    <?php if (!empty($role_descriptions[$role_slug])): ?>
                                        <span class="wt_text-content-secondary wt_ml-1"><?php echo esc_html($role_descriptions[$role_slug]); ?></span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endif; ?>

                    <div class="wt_flex wt_justify-end wt_gap-3 wt_pt-4" data-show="!$addMemberSuccess">
                        <button type="button" class="button button--secondary component-button"
                            data-on:click="<?php echo esc_attr($add_member_request_close_actions); ?>"
                            data-class="{ 'wt_pointer-events-none': $addMemberSubmitting, 'wt_opacity-50': $addMemberSubmitting }"
                            data-attr:aria-disabled="$addMemberSubmitting ? 'true' : 'false'"><?php esc_html_e('Cancel', 'wicket-acc'); ?></button>
                        <button type="submit" class="button button--primary wt_button_submit_async wt_inline-flex wt_items-center wt_gap-2 component-button"
                            data-class="{ 'wt_pointer-events-none': $addMemberSubmitting, 'wt_opacity-50': $addMemberSubmitting, 'wt_is-loading': $addMemberSubmitting }"
                            data-attr:aria-disabled="$addMemberSubmitting ? 'true' : 'false'">
                            <span class="wt_submit_label"><?php esc_html_e('Add Member', 'wicket-acc'); ?></span>
                            <span class="wt_loader wt_loader_button wt_submit_loader"
                                aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
                <div class="wt_pt-4" data-show="$addMemberSuccess">
                    <?php if ($add_member_auto_close_enabled) : ?>
                        <p class="wt_text-sm wt_text-content wt_mb-3" data-show="$autoCloseCountdown > 0"
                            data-on-interval__duration.1000="if ($autoCloseCountdown > 1) { $autoCloseCountdown-- } else if ($autoCloseCountdown === 1) { <?php echo esc_attr($add_member_request_close_actions); ?> }">
                            <?php esc_html_e('This dialog will close automatically in', 'wicket-acc'); ?>
                            <span class="wt_font-semibold" data-text="$autoCloseCountdown"></span>
                            <?php esc_html_e('seconds.', 'wicket-acc'); ?>
                        </p>
                    <?php endif; ?>
                    <div class="wt_mb-4 wt_bg-green-100 wt_border wt_border-green-400 wt_text-green-700 wt_px-4 wt_py-3 wt_rounded-sm" data-show="$addMemberSuccessMessage !== ''">
                        <p><strong><?php esc_html_e('Success!', 'wicket-acc'); ?></strong></p>
                        <p data-text="$addMemberSuccessMessage"></p>
                    </div>
                    <div class="wt_flex wt_justify-end">
                        <button type="button" class="button button--primary component-button"
                            data-on:click="<?php echo esc_attr($add_member_request_close_actions); ?>">
                            <?php esc_html_e('Close', 'wicket-acc'); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </dialog>

    <?php if ($mode !== 'groups' && $show_add_member_button && $show_bulk_upload) : ?>
        <?php
        $bulk_upload_endpoint = OrgHelpers\template_url() . 'process/bulk-upload-members';
        $bulk_upload_messages_id = 'bulk-upload-messages-' . sanitize_html_class($org_uuid ?: 'default');
        $bulk_upload_wrapper_class = 'wt_rounded-md wt_border wt_border-color wt_bg-white wt_p-4';
        ?>
        <dialog id="membersBulkUploadModal" class="modal wt_m-auto max_wt_3xl wt_rounded-md wt_shadow-md backdrop_wt_bg-black-50"
            data-show="$bulkUploadModalOpen"
            data-effect="if ($bulkUploadModalOpen) el.showModal(); else el.close();"
            data-on:close="($membersLoading = false); $bulkUploadModalOpen = false">
            <div class="wt_bg-white wt_p-6 wt_relative">
                <button type="button" class="orgman-modal__close wt_absolute wt_right-4 wt_top-4 wt_text-lg wt_font-semibold"
                    data-on:click="$bulkUploadModalOpen = false"
                    data-class="{ 'wt_pointer-events-none': $bulkUploadSubmitting, 'wt_opacity-50': $bulkUploadSubmitting }"
                    data-attr:aria-disabled="$bulkUploadSubmitting ? 'true' : 'false'">
                    ×
                </button>
                <?php include __DIR__ . '/members-bulk-upload.php'; ?>
            </div>
        </dialog>
    <?php endif; ?>

    <?php if ($show_remove_button) : ?>
    <dialog id="membersRemoveModal" class="modal wt_m-auto max_wt_md wt_rounded-md wt_shadow-md backdrop_wt_bg-black-50"
        data-show="$removeMemberModalOpen"
        data-effect="if ($removeMemberModalOpen) el.showModal(); else el.close();"
        data-on:close="<?php echo esc_attr($remove_member_reset_actions); ?>">
        <div class="wt_bg-white wt_p-6 wt_relative">
            <button type="button" class="orgman-modal__close wt_absolute wt_right-4 wt_top-4 wt_text-lg wt_font-semibold"
                data-on:click="<?php echo esc_attr($remove_member_request_close_actions); ?>" data-show="!$removeMemberSuccess"
                data-class="{ 'wt_pointer-events-none': $removeMemberSubmitting, 'wt_opacity-50': $removeMemberSubmitting }"
                data-attr:aria-disabled="$removeMemberSubmitting ? 'true' : 'false'">×</button>
            <h2 class="wp-block-heading has-heading-sm-font-size wt_text-2xl wt_font-semibold wt_mb-4"><?php esc_html_e('Remove Member', 'wicket-acc'); ?></h2>
            <div id="remove-member-messages"></div>

            <div data-show="!$removeMemberSuccess">
                <p class="wt_mb-6">
                    <span data-class_wt_hidden="$currentRemoveMemberName === ''">
                        <?php echo esc_html__('Are you sure you want to remove this member?', 'wicket-acc'); ?>
                    </span>
                    <span data-class_wt_hidden="$currentRemoveMemberName !== ''">
                        <?php echo esc_html__('Are you sure you want to remove', 'wicket-acc'); ?>
                        <span data-text="$currentRemoveMemberName"></span>&nbsp;<?php echo esc_html__('from this organization?', 'wicket-acc'); ?>
                    </span>
                    <br>
                    <?php esc_html_e('This action cannot be undone.', 'wicket-acc'); ?>
                </p>

                <?php
                $remove_member_endpoint = ($mode === 'groups')
? OrgHelpers\template_url() . 'process/remove-group-member'
: OrgHelpers\template_url() . 'process/remove-member';
        ?>

                <form method="POST"
                    data-on:submit="if(!$removeMemberSubmitting){ $removeMemberSubmitting = true; $membersLoading = true; @post('<?php echo esc_js($remove_member_endpoint); ?>', { contentType: 'form' }); }"
                    data-on:submit__prevent-default="true"
                    data-on:success="<?php echo esc_attr($remove_member_success_actions); ?>"
                    data-on:error="<?php echo esc_attr($remove_member_error_actions); ?>"
                    data-on:reset="$removeMemberSubmitting = false; $membersLoading = false">

                    <?php if ($mode === 'groups') : ?>
                        <input type="hidden" name="group_uuid" value="<?php echo esc_attr($group_uuid); ?>">
                        <input type="hidden" name="org_uuid" value="<?php echo esc_attr($org_uuid); ?>">
                        <input type="hidden" name="person_uuid" data-attr:value="$currentRemoveMemberUuid">
                        <input type="hidden" name="group_member_id" data-attr:value="$currentRemoveMemberGroupMemberId">
                        <input type="hidden" name="role" data-attr:value="$currentRemoveMemberRole">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wicket-orgman-remove-group-member')); ?>">
                    <?php else : ?>
                        <input type="hidden" name="org_uuid" value="<?php echo esc_attr($org_uuid); ?>">
                        <input type="hidden" name="org_dom_suffix" value="<?php echo esc_attr(sanitize_html_class($org_uuid ?: 'default')); ?>">
                        <input type="hidden" name="membership_uuid" value="<?php echo esc_attr($membership_uuid); ?>">
                        <input type="hidden" name="person_uuid" data-attr:value="$currentRemoveMemberUuid">
                        <input type="hidden" name="person_name" data-attr:value="$currentRemoveMemberName">
                        <input type="hidden" name="person_email" data-attr:value="$currentRemoveMemberEmail">
                        <input type="hidden" name="connection_id" data-attr:value="$currentRemoveMemberConnectionId">
                        <input type="hidden" name="person_membership_id" data-attr:value="$currentRemoveMemberPersonMembershipId">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wicket-orgman-remove-member')); ?>">
                    <?php endif; ?>

                    <div class="wt_flex wt_justify-end wt_gap-3">
                        <button type="button"
                            data-on:click="<?php echo esc_attr($remove_member_request_close_actions); ?>"
                            class="button button--secondary wt_px-4 wt_py-2 wt_text-sm component-button"
                            data-class="{ 'wt_pointer-events-none': $removeMemberSubmitting, 'wt_opacity-50': $removeMemberSubmitting }"
                            data-attr:aria-disabled="$removeMemberSubmitting ? 'true' : 'false'"><?php esc_html_e('Cancel', 'wicket-acc'); ?></button>
                        <button type="submit" class="button button--danger wt_button_submit_async wt_inline-flex wt_items-center wt_gap-2 wt_px-4 wt_py-2 wt_text-sm component-button"
                            data-class="{ 'wt_pointer-events-none': $removeMemberSubmitting, 'wt_opacity-50': $removeMemberSubmitting, 'wt_is-loading': $removeMemberSubmitting }"
                            data-attr:aria-disabled="$removeMemberSubmitting ? 'true' : 'false'">
                            <span class="wt_submit_label" data-show="!$removeMemberSubmitting"><?php esc_html_e('Remove Member', 'wicket-acc'); ?></span>
                            <span class="wt_loader wt_loader_button wt_submit_loader"
                                data-show="$removeMemberSubmitting"
                                aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
            <div class="wt_pt-4" data-show="$removeMemberSuccess">
                <?php if ($remove_member_auto_close_enabled) : ?>
                    <p class="wt_text-sm wt_text-content wt_mb-3" data-show="$autoCloseCountdown > 0"
                        data-on-interval__duration.1000="if ($autoCloseCountdown > 1) { $autoCloseCountdown-- } else if ($autoCloseCountdown === 1) { <?php echo esc_attr($remove_member_request_close_actions); ?> }">
                        <?php esc_html_e('This dialog will close automatically in', 'wicket-acc'); ?>
                        <span class="wt_font-semibold" data-text="$autoCloseCountdown"></span>
                        <?php esc_html_e('seconds.', 'wicket-acc'); ?>
                    </p>
                <?php endif; ?>
                <div class="wt_flex wt_justify-end">
                    <button type="button" class="button button--primary wt_px-4 wt_py-2 wt_text-sm component-button"
                        data-on:click="<?php echo esc_attr($remove_member_request_close_actions); ?>">
                        <?php esc_html_e('Close', 'wicket-acc'); ?>
                    </button>
                </div>
            </div>
        </div>
    </dialog>
    <?php endif; ?>
</div>
