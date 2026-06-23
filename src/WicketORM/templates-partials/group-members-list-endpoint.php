<?php

/**
 * Hypermedia endpoint for group members list.
 */

use WicketORM\Services\GroupService;

if (!defined('ABSPATH')) {
    exit;
}

$group_uuid = isset($_GET['group_uuid']) ? sanitize_text_field((string) $_GET['group_uuid']) : '';
$org_uuid = isset($_GET['org_uuid']) ? sanitize_text_field((string) $_GET['org_uuid']) : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$query = isset($_GET['query']) ? sanitize_text_field((string) $_GET['query']) : '';

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
$result = $group_service->getGroupMembers($group_uuid, $org_identifier, [
    'page' => $page,
    'size' => $group_service->getGroupMemberPageSize(),
    'query' => $query,
    'org_uuid' => $org_uuid,
]);

$group_members = $result['members'] ?? [];
$group_pagination = $result['pagination'] ?? [];
$group_query = $result['query'] ?? '';
$group_members_list_endpoint = \WicketORM\Helpers\template_url() . 'group-members-list';
$group_members_list_target = 'group-members-list-container-' . sanitize_html_class($group_uuid);

$orgman_config = WicketORM\Services\ConfigService::getConfig();
$group_presentation = is_array($orgman_config['groups']['presentation'] ?? null)
    ? $orgman_config['groups']['presentation']
    : [];
$member_list_config = is_array($orgman_config['presentation']['member_list'] ?? null)
    ? $orgman_config['presentation']['member_list']
    : [];
$account_status_config = is_array($member_list_config['account_status'] ?? null)
    ? $member_list_config['account_status']
    : [];
$use_unified_member_list = (bool) ($group_presentation['use_unified_member_list'] ?? false);
if ($use_unified_member_list) {
    $mode = 'groups';
    // Group members already carry all card data (name, email, role); set lazy_loaded=true
    // so the unified template renders details inline instead of firing SSE data-init fetches
    // that query the org membership endpoint (which does not contain group members).
    // Also promote the singular 'role' field to 'roles' and 'current_roles' arrays so the
    // unified template's role resolution logic picks up group-level roles correctly.
    $group_members = array_map(static function (array $member): array {
        $member['lazy_loaded'] = true;
        if (!empty($member['role']) && is_string($member['role'])) {
            $member['roles'] = [$member['role']];
            $member['current_roles'] = [$member['role']];
        }

        return $member;
    }, $group_members);
    $members = $group_members;
    $pagination = $group_pagination;
    $query = $group_query;
    $members_list_endpoint = $group_members_list_endpoint;
    $members_list_target = $group_members_list_target;
    $membershipService = new WicketORM\Services\MembershipService();
    $membership_uuid = $org_uuid ? $membershipService->getMembershipForOrganization($org_uuid) : '';
    $show_edit_permissions = (bool) ($group_presentation['show_edit_permissions'] ?? false);
    $show_account_status = (bool) ($account_status_config['enabled'] ?? true);
    $show_add_member_button = true;
    $show_remove_button = true;
    include __DIR__ . '/members-list-unified.php';
} else {
    include __DIR__ . '/members-list-groups.php';
}
