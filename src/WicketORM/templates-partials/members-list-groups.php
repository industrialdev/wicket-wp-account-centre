<?php
/**
 * Group members list partial.
 */

use WicketORM\Services\AdditionalSeatsService;
use WicketORM\Services\ConfigService;
use WicketORM\Services\MembershipService;

if (!defined('ABSPATH')) {
    exit;
}

$group_uuid = isset($_GET['group_uuid']) ? sanitize_text_field((string) $_GET['group_uuid']) : '';
$org_uuid = isset($_GET['org_uuid']) ? sanitize_text_field((string) $_GET['org_uuid']) : '';

$members = isset($group_members) && is_array($group_members) ? $group_members : [];
$pagination = isset($group_pagination) && is_array($group_pagination) ? $group_pagination : [];
$query = isset($group_query) ? (string) $group_query : '';

$members_list_endpoint = isset($group_members_list_endpoint) ? (string) $group_members_list_endpoint : WicketORM\Helpers\TemplateHelper::template_url() . 'group-members-list';
$members_list_target = isset($group_members_list_target) ? (string) $group_members_list_target : 'group-members-list-container-' . sanitize_html_class($group_uuid ?: 'default');

$page = (int) ($pagination['currentPage'] ?? 1);
$total_pages = (int) ($pagination['totalPages'] ?? 1);
$page_size = (int) ($pagination['pageSize'] ?? 15);
$total_items = (int) ($pagination['totalItems'] ?? count($members));

$page = max(1, $page);
$total_pages = max(1, $total_pages);

$membershipService = new MembershipService();
$configService = new ConfigService();
$additional_seats_service = new AdditionalSeatsService($configService);
$member_service = new WicketORM\Services\MemberService($configService);

$membership_uuid = '';
if (!empty($org_uuid)) {
    $membership_uuid = $membershipService->getMembershipForOrganization($org_uuid);
}

$has_seats_available = true;
$max_seats = null;
$active_seats = 0;
$purchase_seats_url = '';
$can_purchase_seats = false;
if ($membership_uuid) {
    $membership_data = $membershipService->getOrgMembershipData($membership_uuid);
    if ($membership_data && isset($membership_data['data']['attributes'])) {
        $max_seats = $membershipService->getEffectiveMaxAssignments($membership_data);
        $active_seats = (int) ($membership_data['data']['attributes']['active_assignments_count'] ?? 0);
        if ($max_seats !== null && $active_seats >= (int) $max_seats) {
            $has_seats_available = false;
        }
    }

    if (!$has_seats_available) {
        $can_purchase_seats = $additional_seats_service->canPurchaseAdditionalSeats($org_uuid);
        if ($can_purchase_seats) {
            $purchase_seats_url = $additional_seats_service->getPurchaseFormUrl($org_uuid, $membership_uuid);
        }
    }
}

$base_query_args = [
    'group_uuid' => $group_uuid,
    'org_uuid' => $org_uuid,
    'query' => $query,
    'size' => $page_size,
];

$build_url = static function (int $page_number) use ($members_list_endpoint, $base_query_args) {
    $args = array_merge($base_query_args, ['page' => $page_number]);
    $separator = str_contains($members_list_endpoint, '?') ? '&' : '?';
    $query_args = http_build_query($args, '', '&', PHP_QUERY_RFC3986);

    return $members_list_endpoint . $separator . $query_args;
};

$build_action = static function (int $page_number) use ($build_url) {
    return '$listLoading = true; @get(\'' . $build_url($page_number) . '\')';
};

$remove_member_endpoint = WicketORM\Helpers\TemplateHelper::template_url() . 'process/remove-group-member';
$refresh_action = "@get('" . $build_url(1) . "') >> select('#" . $members_list_target . "') | set(html)";

$orgman_config = ConfigService::getConfig();
$show_assignment_info = (bool) ($orgman_config['presentation']['member_list']['show_assignment_info'] ?? true);

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
    <div id="group-member-messages" class="wt_mb-3"></div>
    <?php if ($show_assignment_info): ?>
    <div class="wt_text-xl wt_font-semibold wt_mb-3">
        <?php if ($max_seats !== null) : ?>
            <?php printf(esc_html__('Seats assigned: %1$d / %2$d', 'wicket-acc'), (int) $active_seats, (int) $max_seats); ?>
        <?php else : ?>
            <?php esc_html_e('Number of assigned people:', 'wicket-acc'); ?>
            <?php echo (int) $total_items; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!$has_seats_available) : ?>
        <div class="wt_rounded-md wt_bg-light-neutral wt_p-4">
            <p class="wt_text-sm wt_text-content">
                <?php esc_html_e('No seats available. Please purchase additional seats to add more members.', 'wicket-acc'); ?>
            </p>
            <?php if ($can_purchase_seats && $purchase_seats_url) : ?>
                <div class="wt_mt-3">
                    <a class="button button--primary additional-seats-cta" href="<?php echo esc_url($purchase_seats_url); ?>">
                        <?php esc_html_e('Purchase Additional Seats', 'wicket-acc'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($members)) : ?>
        <p class="wt_text-gray-500 wt_p-4"><?php esc_html_e('No members found.', 'wicket-acc'); ?></p>
    <?php else : ?>
        <?php foreach ($members as $member) :
            $member_uuid = $member['person_uuid'] ?? '';
            $member_name = $member['full_name'] ?? '';
            $member_email = $member['email'] ?? '';
            $member_role_label = $member['role'] ?? '';
            $group_member_id = $member['group_member_id'] ?? '';
            $is_confirmed = !empty($member['confirmed_at']);
            $is_member_owner = !empty($member['is_owner']);
            ?>
            <div class="member-card wt_bg-light-neutral wt_rounded-card wt_p-6 wt_transition-opacity wt_duration-300">
                <div class="wt_flex wt_w-full md_wt_flex-row wt_items-start wt_justify-between wt_gap-4">
                    <div class="wt_flex wt_flex-col wt_gap-2 wt_w-full md_wt_w-4-5">
                        <div class="wt_flex wt_flex-col sm_wt_flex-row wt_items-start sm_wt_items-center wt_gap-2">
                            <div class="wt_flex wt_items-center wt_gap-2">
                                <?php if (WicketORM\Helpers\Helper::should_show_member_name()) : ?>
                                <h3 class="wt_text-xl wt_font-medium wt_text-content wt_mb-0">
                                    <?php echo esc_html($member_name); ?>
                                </h3>
                                <?php endif; ?>
                                <?php if ($is_confirmed) : ?>
                                    <span class="wt_text-content" title="<?php esc_attr_e('Account confirmed', 'wicket-acc'); ?>">
                                        <span class="wt_inline-block wt_w-2 wt_h-2 wt_rounded-full wt_bg-green-500" aria-hidden="true"></span>
                                    </span>
                                <?php else : ?>
                                    <span class="wt_text-content" title="<?php esc_attr_e('Account not confirmed', 'wicket-acc'); ?>">
                                        <span class="wt_inline-block wt_w-2 wt_h-2 wt_rounded-full wt_bg-gray-400" aria-hidden="true"></span>
                                    </span>
                                    <span class="wt_text-warning wt_whitespace-nowrap" title="<?php esc_attr_e('Account not confirmed', 'wicket-acc'); ?>">
                                        <?php esc_html_e('Account not confirmed', 'wicket-acc'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($member_email && WicketORM\Helpers\Helper::should_show_member_email()) : ?>
                            <div class="wt_flex wt_items-center wt_gap-2">
                                <a href="mailto:<?php echo esc_attr($member_email); ?>" class="wt_text-sm wt_text-interactive wt_hover_underline">
                                    <?php echo esc_html($member_email); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <?php if ($member_role_label && WicketORM\Helpers\Helper::should_show_member_roles()) : ?>
                            <div class="wt_flex wt_items-baseline wt_gap-2 wt_text-sm">
                                <strong><?php esc_html_e('Role:', 'wicket-acc'); ?></strong>
                                <span class="wt_text-content">
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', $member_role_label))); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_member_owner) : ?>
                    <div class="wt_flex wt_flex-col sm_wt_flex-row wt_items-stretch sm_wt_items-start wt_gap-2 wt_justify-between md_wt_auto wt_shrink-0">
                        <button type="button" class="acc-remove-button remove-member-button button button--secondary wt_inline-flex wt_items-center wt_justify-between wt_gap-2 wt_px-4 wt_py-2 wt_bg-light-neutral wt_text-sm wt_border wt_border-bg-interactive wt_transition-colors wt_whitespace-nowrap component-button"
                            data-on:click="
                                $removeMemberSuccess = false;
                                $removeMemberSubmitting = false;
                                (() => { const el = document.getElementById('remove-member-messages'); if (el) el.innerHTML = ''; })();
                                $currentRemoveMemberUuid = '<?php echo esc_js($member_uuid); ?>';
                                $currentRemoveMemberName = '<?php echo esc_js($member_name); ?>';
                                $currentRemoveMemberEmail = '<?php echo esc_js($member_email); ?>';
                                $currentRemoveMemberGroupMemberId = '<?php echo esc_js($group_member_id); ?>';
                                $currentRemoveMemberRole = '<?php echo esc_js($member_role_label); ?>';
                                $removeMemberModalOpen = true
                            ">
                            <?php esc_html_e('Remove', 'wicket-acc'); ?>
                            <svg class="wt_w-4 wt_h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <nav class="members-pagination wt_mt-6 wt_flex wt_flex-col wt_gap-4" aria-label="<?php esc_attr_e('Group members pagination', 'wicket-acc'); ?>">
        <div class="members-pagination__info wt_w-full wt_text-left wt_text-sm wt_text-content">
            <?php
                if ($total_items > 0) {
                    $first = (($page - 1) * $page_size) + 1;
                    $last = min($total_items, $page * $page_size);
                    echo esc_html(sprintf(__('Showing %1$d–%2$d of %3$d', 'wicket-acc'), $first, $last, $total_items));
                } else {
                    // Pagination hidden when no members
                }
?>
        </div>
        <?php if ($total_pages > 1) : ?>
            <div class="members-pagination__controls wt_w-full wt_flex wt_items-center wt_gap-2 wt_justify-end wt_self-end">
                <?php $prev_disabled = $page <= 1; ?>
                <button type="button"
                    class="members-pagination__btn members-pagination__btn--prev button button--secondary wt_px-3 wt_py-2 wt_text-sm component-button"
                    <?php if ($prev_disabled) : ?>disabled<?php endif; ?>
                    <?php if (!$prev_disabled) : ?>data-on:click="<?php echo esc_attr($build_action($page - 1)); ?>" <?php endif; ?>
                    data-on:success="<?php echo esc_attr('$listLoading = false; ' . wp_sprintf("select('#%s') | set(html)", $members_list_target)); ?>"
                    data-indicator:members-loading>
                    <?php esc_html_e('Previous', 'wicket-acc'); ?>
                </button>
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
                <?php $next_disabled = $page >= $total_pages; ?>
                <button type="button"
                    class="members-pagination__btn members-pagination__btn--next button button--secondary wt_px-3 wt_py-2 wt_text-sm component-button"
                    <?php if ($next_disabled) : ?>disabled<?php endif; ?>
                    <?php if (!$next_disabled) : ?>data-on:click="<?php echo esc_attr($build_action($page + 1)); ?>" <?php endif; ?>
                    data-on:success="<?php echo esc_attr('$listLoading = false; ' . wp_sprintf("select('#%s') | set(html)", $members_list_target)); ?>"
                    data-indicator:members-loading>
                    <?php esc_html_e('Next', 'wicket-acc'); ?>
                </button>
            </div>
        <?php endif; ?>
    </nav>

    <div class="wt_mt-6">
        <?php
        // Always show the 'Add Member' button if the user can manage the group.
        // The backend (GroupsStrategy) handles seat availability enforcement for seat-limited roles.
        // This allows adding non-seat-limited roles (like Observers) even when seats are full.
?>
        <button type="button"
            class="button button--primary add-member-button wt_w-full wt_py-2 component-button"
            data-on:click="$addMemberSuccess = false; $addMemberSubmitting = false; $addMemberSuccessMessage = ''; (() => { const modal = document.getElementById('groupMembersAddModal'); if (!modal) return; const form = modal.querySelector('form'); if (form) form.reset(); const messages = modal.querySelector('#group-member-add-messages'); if (messages) messages.innerHTML = ''; })(); $addMemberModalOpen = true"><?php esc_html_e('Add Member', 'wicket-acc'); ?></button>
        
        <?php if (!$has_seats_available) : ?>
            <div class="wt_mt-2 wt_p-3 wt_bg-yellow-50 wt_border wt_border-yellow-200 wt_rounded-md wt_text-yellow-800 wt_text-sm">
                <div class="wt_flex wt_items-center wt_gap-2">
                    <svg class="wt_w-5 wt_h-5 wt_text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    <span><?php esc_html_e('All seats have been assigned. You can still add Observers, but purchase additional seats to add more Members.', 'wicket-acc'); ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    </div>
</div>
