<?php
/**
 * Group members partial.
 */

use WicketORM\Services\AdditionalSeatsService;
use WicketORM\Services\ConfigService;
use WicketORM\Services\GroupService;
use WicketORM\Services\MembershipService;

if (!defined('ABSPATH')) {
    exit;
}

$group_uuid = isset($_GET['group_uuid']) ? sanitize_text_field((string) $_GET['group_uuid']) : '';
$org_uuid = isset($_GET['org_uuid']) ? sanitize_text_field((string) $_GET['org_uuid']) : '';

if (empty($group_uuid)) {
    echo '<p class="wt_text-gray-500">' . esc_html__('No group selected.', 'wicket-acc') . '</p>';

    return;
}

$group_service = new GroupService();
$current_user = wp_get_current_user();
$access = $group_service->canManageGroup($group_uuid, (string) $current_user->user_login);
if (empty($access['allowed'])) {
    echo '<p class="wt_text-gray-500">' . esc_html__('You do not have permission to manage this group.', 'wicket-acc') . '</p>';

    return;
}

$org_identifier = (string) ($access['org_identifier'] ?? '');
$org_uuid = !empty($access['org_uuid']) ? (string) $access['org_uuid'] : $org_uuid;
if (empty($org_uuid) && function_exists('wicket_get_group')) {
    $group_data = wicket_get_group($group_uuid);
    if (is_array($group_data)) {
        $org_uuid = $group_data['data']['relationships']['organization']['data']['id'] ?? $org_uuid;
    }
}

$membershipService = new MembershipService();
$configService = new ConfigService();
$additional_seats_service = new AdditionalSeatsService($configService);
$orgman_config = ConfigService::getConfig();
$clear_form_on_error = $orgman_config['member_management']['forms']['add_member']['clear_form_on_error'] ?? false;

$membership_uuid = '';
if ($org_uuid) {
    $membership_uuid = $membershipService->getMembershipForOrganization($org_uuid);
}

$result = $group_service->getGroupMembers($group_uuid, $org_identifier, [
    'page' => 1,
    'size' => $group_service->getGroupMemberPageSize(),
    'query' => '',
    'org_uuid' => $org_uuid,
]);

$members = $result['members'] ?? [];
$pagination = $result['pagination'] ?? [];
$query = $result['query'] ?? '';

$members_list_endpoint = \WicketORM\Helpers\template_url() . 'group-members-list';
$members_list_target = 'group-members-list-container-' . sanitize_html_class($group_uuid);
$encoded_group_uuid = rawurlencode($group_uuid);
$encoded_org_uuid = rawurlencode($org_uuid);

$members_list_separator = str_contains($members_list_endpoint, '?') ? '&' : '?';
$search_action = "@get('{$members_list_endpoint}{$members_list_separator}group_uuid={$encoded_group_uuid}&org_uuid={$encoded_org_uuid}&page=1&query=' + encodeURIComponent(" . '$searchQuery' . '))';
$search_success = '$listLoading = false; ' . wp_sprintf("select('#%s') | set(html)", $members_list_target);
$signals = [
    'searchQuery' => $query,
    'searchSubmitted' => false,
];
$group_presentation = is_array($orgman_config['groups']['presentation'] ?? null)
    ? $orgman_config['groups']['presentation']
    : [];
$member_list_config = is_array($orgman_config['presentation']['member_list'] ?? null)
    ? $orgman_config['presentation']['member_list']
    : [];
// Group members already carry all card data (name, email, role); set lazy_loaded=true
// so the unified template renders details inline instead of firing SSE data-init fetches
// that query the org membership endpoint (which does not contain group members).
// Also promote the singular 'role' field to 'roles' and 'current_roles' arrays so the
// unified template's role resolution logic picks up group-level roles correctly.
if ((bool) ($group_presentation['use_unified_member_list'] ?? false)) {
    $members = array_map(static function (array $member): array {
        $member['lazy_loaded'] = true;
        if (!empty($member['role']) && is_string($member['role'])) {
            $member['roles'] = [$member['role']];
            $member['current_roles'] = [$member['role']];
        }

        return $member;
    }, $members);
}
$account_status_config = is_array($member_list_config['account_status'] ?? null)
    ? $member_list_config['account_status']
    : [];
$group_roles = is_array($orgman_config['groups']['roles'] ?? null)
    ? $orgman_config['groups']['roles']
    : [];
$use_unified_view = (bool) ($group_presentation['use_unified_member_view'] ?? false);
if ($use_unified_view) {
    $mode = 'groups';
    $members_list_endpoint = $members_list_endpoint;
    $members_list_target = $members_list_target;
    $members = $members;
    $pagination = $pagination;
    $query = $query;
    include __DIR__ . '/members-view-unified.php';

    return;
}
?>
<div
    class="group-members wt_relative"
    data-signals:='<?php echo wp_json_encode([
        'membersLoading' => false,
        'listLoading' => false,
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
        'currentRemoveMemberGroupMemberId' => '',
        'currentRemoveMemberRole' => '',
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
            <?php
$searchSubmitAction = '$listLoading = true; $searchSubmitted = true; ' . $search_action;
$clearAction = "(\$listLoading = true, \$searchQuery = '', \$searchSubmitted = false, {$search_action})";
?>
            <input
                type="text"
                data-bind="searchQuery"
                class="members-search__input wt_border wt_border-color wt_text-content wt_text-sm wt_rounded-md wt_focus_ring-2 wt_focus_ring-bg-interactive wt_focus_border-bg-interactive wt_block wt_w-full wt_pl-10 wt_p-2.5"
                placeholder="<?php esc_attr_e('Start typing to search for members...', 'wicket-acc'); ?>"
                data-on:success="<?php echo esc_attr($search_success); ?>"
                data-indicator:members-loading
                data-on:keydown="if (evt.key === 'Enter') { <?php echo esc_attr($searchSubmitAction); ?> }"
                data-on:keydown__prevent-default="evt.key === 'Enter'">
        </div>
        <div class="members-search__actions wt_flex wt_items-center wt_gap-2">
            <button class="members-search__submit button button--primary wt_whitespace-nowrap component-button"
                data-on:click="<?php echo esc_attr($searchSubmitAction); ?>"
                data-on:success="<?php echo esc_attr($search_success); ?>"
                data-show="!$searchSubmitted"
                data-indicator:members-loading"><?php esc_html_e('Search', 'wicket-acc'); ?></button>
            <button class="members-search__clear button button--secondary wt_whitespace-nowrap component-button"
                data-on:click="<?php echo esc_attr($clearAction); ?>"
                data-on:success="<?php echo esc_attr($search_success); ?>"
                data-show="$searchSubmitted && $searchQuery && $searchQuery.trim() !== ''"
                data-indicator:members-loading"><?php esc_html_e('Clear', 'wicket-acc'); ?></button>
        </div>
    </div>

    <?php
$group_members = $members;
$group_pagination = $pagination;
$group_query = $query;
$group_members_list_endpoint = $members_list_endpoint;
$group_members_list_target = $members_list_target;
$use_unified_view = (bool) ($group_presentation['use_unified_member_view'] ?? false);
if ($use_unified_view) {
    $mode = 'groups';
    $members = $group_members;
    $pagination = $group_pagination;
    $query = $group_query;
    $membership_uuid = $membership_uuid;
    $show_edit_permissions = (bool) ($group_presentation['show_edit_permissions'] ?? false);
    $show_account_status = (bool) ($account_status_config['enabled'] ?? true);
    $show_add_member_button = true;
    $show_remove_button = true;
    $members_list_endpoint = $group_members_list_endpoint;
    $members_list_target = $group_members_list_target;
    include __DIR__ . '/members-view-unified.php';
} else {
    $use_unified_member_list = (bool) ($group_presentation['use_unified_member_list'] ?? false);
    if ($use_unified_member_list) {
        $mode = 'groups';
        $members = $group_members;
        $pagination = $group_pagination;
        $query = $group_query;
        $membership_uuid = $membership_uuid;
        $show_edit_permissions = (bool) ($group_presentation['show_edit_permissions'] ?? false);
        $show_account_status = (bool) ($account_status_config['enabled'] ?? true);
        $show_add_member_button = true;
        $show_remove_button = true;
        $members_list_endpoint = $group_members_list_endpoint;
        $members_list_target = $group_members_list_target;
        include __DIR__ . '/members-list-unified.php';
    } else {
        include __DIR__ . '/members-list-groups.php';
    }
}
?>

    <?php
if ($use_unified_view) {
    return;
}
$can_purchase_seats = $org_uuid ? $additional_seats_service->canPurchaseAdditionalSeats($org_uuid, $membership_uuid) : false;
$purchase_url = ($can_purchase_seats && $membership_uuid)
    ? $additional_seats_service->getPurchaseFormUrl($org_uuid, $membership_uuid)
    : '';
if ($can_purchase_seats && !empty($purchase_url)) :
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
endif;
?>

    <?php
$groups_config = is_array($orgman_config['groups'] ?? null) ? $orgman_config['groups'] : [];
$groups_presentation = is_array($groups_config['presentation'] ?? null)
    ? $groups_config['presentation']
    : (is_array($groups_config['ui'] ?? null) ? $groups_config['ui'] : []);
$add_member_auto_close_on_success = (bool) ($groups_presentation['add_member_auto_close_on_success'] ?? false);
$add_member_auto_close_delay_seconds = max(0, (int) ($groups_presentation['add_member_auto_close_delay_seconds'] ?? 7));
$add_member_auto_close_delay_ms = $add_member_auto_close_delay_seconds * 1000;
$add_member_modal_reset_actions = "(() => { const modal = document.getElementById('groupMembersAddModal'); const messages = modal ? modal.querySelector('#group-member-add-messages') : document.getElementById('group-member-add-messages'); if (messages) messages.innerHTML = ''; const form = modal ? modal.querySelector('form') : document.querySelector('#groupMembersAddModal form'); if (form && form.reset) form.reset(); })(); \$membersLoading = false; \$addMemberSubmitting = false; \$addMemberSuccess = false; \$autoCloseCountdown = 0; \$addMemberModalOpen = false; \$addMemberSuccessMessage = '';";
$add_member_request_close_actions = '$addMemberModalOpen = false;';
$add_member_success_actions = "console.log('Group member added successfully'); \$addMemberSubmitting = false; \$membersLoading = false; \$addMemberSuccess = true;";
if ($add_member_auto_close_on_success && $add_member_auto_close_delay_seconds > 0) {
    $add_member_success_actions .= " \$autoCloseCountdown = {$add_member_auto_close_delay_seconds};";
}
$add_member_error_actions = "console.error('Failed to add group member'); \$addMemberSubmitting = false; \$membersLoading = false; \$addMemberSuccess = false; \$autoCloseCountdown = 0;";
if ($clear_form_on_error) {
    $add_member_error_actions .= " el.closest('form').reset();";
}
$remove_member_success_actions = "console.log('Group member removed successfully'); \$removeMemberSubmitting = false; \$membersLoading = false; \$removeMemberSuccess = true;";
$remove_member_error_actions = "console.error('Failed to remove group member'); \$removeMemberSubmitting = false; \$membersLoading = false;";
$remove_member_reset_actions = "(() => { const modal = document.getElementById('groupMembersRemoveModal'); const messages = modal ? modal.querySelector('#remove-member-messages') : document.getElementById('remove-member-messages'); if (messages) messages.innerHTML = ''; })(); \$removeMemberModalOpen = false; \$removeMemberSubmitting = false; \$removeMemberSuccess = false; \$membersLoading = false; \$autoCloseCountdown = 0; \$currentRemoveMemberUuid = ''; \$currentRemoveMemberName = ''; \$currentRemoveMemberEmail = ''; \$currentRemoveMemberGroupMemberId = ''; \$currentRemoveMemberRole = '';";
$remove_member_request_close_actions = '$removeMemberModalOpen = false;';
$add_member_endpoint = WicketORM\Helpers\TemplateHelper::template_url() . 'process/add-group-member';
$remove_member_endpoint = WicketORM\Helpers\TemplateHelper::template_url() . 'process/remove-group-member';
$group_roles = is_array($groups_config['roles'] ?? null) ? $groups_config['roles'] : [];
$member_role = $group_roles['member'] ?? ($groups_config['member_role'] ?? 'member');
$observer_role = $group_roles['observer'] ?? ($groups_config['observer_role'] ?? 'observer');
?>

    <dialog id="groupMembersAddModal"
        class="modal wt_m-auto max_wt_3xl wt_rounded-md wt_shadow-md backdrop_wt_bg-black-50"
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

            <div id="group-member-add-messages" class="wt_mb-3"></div>

            <form
                method="post"
                class="wt_flex wt_flex-col wt_gap-4"
                data-show="!$addMemberSuccess"
                data-on:submit="if(!$addMemberSubmitting){ $addMemberSubmitting = true; $membersLoading = true; @post('<?php echo esc_js($add_member_endpoint); ?>', { contentType: 'form' }); }"
                data-on:submit__prevent-default="true"
                data-on:success="<?php echo esc_attr($add_member_success_actions); ?>"
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
                        <span class="wt_submit_label" data-show="!$addMemberSubmitting">
                            <?php esc_html_e('Add Member', 'wicket-acc'); ?>
                        </span>
                        <span class="wt_loader wt_loader_button wt_submit_loader"
                            data-show="$addMemberSubmitting"
                            aria-hidden="true"></span>
                    </button>
                </div>
            </form>
            <div class="wt_pt-4" data-show="$addMemberSuccess">
                <?php if ($add_member_auto_close_on_success) : ?>
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
        </div>
    </dialog>

    <dialog id="groupMembersRemoveModal" class="modal wt_m-auto max_wt_md wt_rounded-md wt_shadow-md backdrop_wt_bg-black-50"
        data-show="$removeMemberModalOpen"
        data-effect="if ($removeMemberModalOpen) el.showModal(); else el.close();"
        data-on:close="<?php echo esc_attr($remove_member_reset_actions); ?>">
        <div class="wt_bg-white wt_p-6 wt_relative">
            <button type="button" class="orgman-modal__close wt_absolute wt_right-4 wt_top-4 wt_text-lg wt_font-semibold"
                data-on:click="<?php echo esc_attr($remove_member_request_close_actions); ?>" data-show="!$removeMemberSuccess"
                data-class="{ 'wt_pointer-events-none': $removeMemberSubmitting, 'wt_opacity-50': $removeMemberSubmitting }"
                data-attr:aria-disabled="$removeMemberSubmitting ? 'true' : 'false'">
                ×
            </button>
            <h2 class="wp-block-heading has-heading-sm-font-size wt_text-2xl wt_font-semibold wt_mb-4"><?php esc_html_e('Remove Member', 'wicket-acc'); ?></h2>
            <div id="remove-member-messages"></div>

            <div data-show="!$removeMemberSuccess">
                <p class="wt_mb-6">
                    <span data-class_wt_hidden="$currentRemoveMemberName === ''">
                        <?php echo esc_html__('Are you sure you want to remove this member from the group?', 'wicket-acc'); ?>
                    </span>
                    <span data-class_wt_hidden="$currentRemoveMemberName !== ''">
                        <?php echo esc_html__('Are you sure you want to remove', 'wicket-acc'); ?>
                        <span data-text="$currentRemoveMemberName"></span>&nbsp;<?php echo esc_html__('from this group?', 'wicket-acc'); ?>
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
                    data-on:reset="$removeMemberSubmitting = false; $membersLoading = false">
                    <input type="hidden" name="group_uuid" value="<?php echo esc_attr($group_uuid); ?>">
                    <input type="hidden" name="org_uuid" value="<?php echo esc_attr($org_uuid); ?>">
                    <input type="hidden" name="person_uuid" data-attr:value="$currentRemoveMemberUuid">
                    <input type="hidden" name="group_member_id" data-attr:value="$currentRemoveMemberGroupMemberId">
                    <input type="hidden" name="role" data-attr:value="$currentRemoveMemberRole">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wicket-orgman-remove-group-member')); ?>">

                    <div class="wt_flex wt_justify-end wt_gap-3">
                        <button
                            type="button"
                            data-on:click="<?php echo esc_attr($remove_member_request_close_actions); ?>"
                            class="button button--secondary wt_px-4 wt_py-2 wt_text-sm component-button"
                            data-class="{ 'wt_pointer-events-none': $removeMemberSubmitting, 'wt_opacity-50': $removeMemberSubmitting }"
                            data-attr:aria-disabled="$removeMemberSubmitting ? 'true' : 'false'"><?php esc_html_e('Cancel', 'wicket-acc'); ?></button>
                        <button
                            type="submit"
                            class="button button--danger wt_button_submit_async wt_inline-flex wt_items-center wt_gap-2 wt_px-4 wt_py-2 wt_text-sm component-button"
                            data-class="{ 'wt_pointer-events-none': $removeMemberSubmitting, 'wt_opacity-50': $removeMemberSubmitting, 'wt_is-loading': $removeMemberSubmitting }"
                            data-attr:aria-disabled="$removeMemberSubmitting ? 'true' : 'false'">
                            <span class="wt_submit_label" data-show="!$removeMemberSubmitting">
                                <?php esc_html_e('Remove Member', 'wicket-acc'); ?>
                            </span>
                            <span
                                class="wt_loader wt_loader_button wt_submit_loader"
                                data-show="$removeMemberSubmitting"
                                aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
            <div class="wt_pt-4" data-show="$removeMemberSuccess">
                <?php if ($add_member_auto_close_on_success) : ?>
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
</div>
