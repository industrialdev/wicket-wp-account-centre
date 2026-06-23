<?php

/**
 * Hypermedia partial for Remove Group Member processing.
 */

use starfederation\datastar\enums\ElementPatchMode;
use WicketORM\Services\ConfigService;
use WicketORM\Services\MemberService;

if (!defined('ABSPATH')) {
    exit;
}

$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ('POST' !== strtoupper($request_method)) {
    return;
}

$logger = \Wicket()->log();
$log_context = [
    'source' => 'wicket-orgman',
    'action' => 'remove_group_member',
    'user_id' => get_current_user_id(),
];

$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
if (!$nonce || !wp_verify_nonce($nonce, 'wicket-orgman-remove-group-member')) {
    $logger->warning('Remove group member invalid nonce', $log_context);
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(__('Invalid or missing security token. Please refresh and try again.', 'wicket-acc'), '#remove-member-messages', ['removeMemberSubmitting' => false, 'membersLoading' => false]);

    return;
}

$group_uuid = isset($_POST['group_uuid']) ? sanitize_text_field(wp_unslash($_POST['group_uuid'])) : '';
$org_uuid = isset($_POST['org_uuid']) ? sanitize_text_field(wp_unslash($_POST['org_uuid'])) : '';
$person_uuid = isset($_POST['person_uuid']) ? sanitize_text_field(wp_unslash($_POST['person_uuid'])) : '';
$group_member_id = isset($_POST['group_member_id']) ? sanitize_text_field(wp_unslash($_POST['group_member_id'])) : '';
$role = isset($_POST['role']) ? sanitize_text_field(wp_unslash($_POST['role'])) : '';

$log_context['group_uuid'] = $group_uuid;
$log_context['org_uuid'] = $org_uuid;
$log_context['person_uuid'] = $person_uuid;
$log_context['group_member_id'] = $group_member_id;
$log_context['role'] = $role;
$logger->info('Remove group member request received', $log_context);

if (empty($group_uuid) || empty($person_uuid)) {
    $logger->error('Remove group member missing identifiers', $log_context);
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(__('Missing group or member identifiers.', 'wicket-acc'), '#remove-member-messages', ['removeMemberSubmitting' => false, 'membersLoading' => false]);

    return;
}

$configService = new ConfigService();
$orgman_config = ConfigService::getConfig();
$groups_config = is_array($orgman_config['groups'] ?? null) ? $orgman_config['groups'] : [];
$groups_presentation = is_array($groups_config['presentation'] ?? null)
    ? $groups_config['presentation']
    : (is_array($groups_config['ui'] ?? null) ? $groups_config['ui'] : []);
$remove_auto_close_on_success = (bool) ($groups_presentation['add_member_auto_close_on_success'] ?? false);
$remove_auto_close_delay_seconds = max(0, (int) ($groups_presentation['add_member_auto_close_delay_seconds'] ?? 7));
$member_service = new MemberService($configService);

$context = [
    'group_uuid' => $group_uuid,
    'group_member_id' => $group_member_id,
    'role' => $role,
];

$result = $member_service->removeMember($org_uuid, $person_uuid, $context);
if (is_wp_error($result)) {
    $logger->error('Remove group member failed', array_merge($log_context, [
        'error' => $result->get_error_message(),
    ]));
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError($result->get_error_message(), '#remove-member-messages', ['removeMemberSubmitting' => false, 'membersLoading' => false]);

    return;
}

$logger->info('Remove group member succeeded', $log_context);

$members_list_target = 'group-members-list-container-' . sanitize_html_class($group_uuid);
$list_html = '';
$original_get = $_GET;
$_GET['group_uuid'] = $group_uuid;
$_GET['org_uuid'] = $org_uuid;
$_GET['page'] = '1';
$_GET['query'] = '';
ob_start();
include dirname(__DIR__) . '/group-members-list-endpoint.php';
$list_html = (string) ob_get_clean();
$_GET = $original_get;

$element_patches = [];
if ($list_html !== '') {
    $element_patches[] = [
        'selector' => '#' . $members_list_target,
        'elements' => $list_html,
        'mode' => ElementPatchMode::Outer,
    ];
}

status_header(200);
WicketORM\Helpers\DatastarSSE::renderSuccess(
    __('Group member removed successfully.', 'wicket-acc'),
    '#remove-member-messages',
    [
        'removeMemberSubmitting' => false,
        'removeMemberSuccess' => true,
        'membersLoading' => false,
        'autoCloseCountdown' => $remove_auto_close_on_success ? $remove_auto_close_delay_seconds : 0,
    ],
    0,
    'countdown',
    $element_patches
);

return;
