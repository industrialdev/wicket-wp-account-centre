<?php

/**
 * Hypermedia partial for Add Group Member processing.
 */

use starfederation\datastar\enums\ElementPatchMode;
use starfederation\datastar\ServerSentEventGenerator;
use WicketORM\Services\ConfigService;
use WicketORM\Services\MemberService;

if (!defined('ABSPATH')) {
    exit;
}

$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ('POST' !== strtoupper($request_method)) {
    return;
}

$message_target = '#group-member-add-messages';

$logger = \Wicket()->log();
$log_context = [
    'source' => 'wicket-orgman',
    'action' => 'add_group_member',
    'user_id' => get_current_user_id(),
];

$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
if (!$nonce || !wp_verify_nonce($nonce, 'wicket-orgman-add-group-member')) {
    $logger->warning('Add group member invalid nonce', $log_context);
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(__('Invalid or missing security token. Please refresh and try again.', 'wicket-acc'), $message_target, ['addMemberSubmitting' => false, 'addMemberSuccess' => false, 'membersLoading' => false]);

    return;
}

$group_uuid = isset($_POST['group_uuid']) ? sanitize_text_field(wp_unslash($_POST['group_uuid'])) : '';
$org_uuid = isset($_POST['org_uuid']) ? sanitize_text_field(wp_unslash($_POST['org_uuid'])) : '';
$role = isset($_POST['role']) ? sanitize_text_field(wp_unslash($_POST['role'])) : '';

$log_context['group_uuid'] = $group_uuid;
$log_context['org_uuid'] = $org_uuid;
$log_context['role'] = $role;
$logger->info('Add group member request received', $log_context);

if (empty($group_uuid)) {
    $logger->error('Add group member missing group_uuid', $log_context);
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(__('Group identifier missing.', 'wicket-acc'), $message_target, ['addMemberSubmitting' => false, 'addMemberSuccess' => false, 'membersLoading' => false]);

    return;
}

$member_data = [
    'first_name' => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
    'last_name'  => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
    'email'      => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
];

$log_context['member_email'] = $member_data['email'];

$configService = new ConfigService();
$member_service = new MemberService($configService);

$context = [
    'group_uuid' => $group_uuid,
    'role' => $role,
];

$result = $member_service->addMember($org_uuid, $member_data, $context);
if (is_wp_error($result)) {
    $logger->error('Add group member failed', array_merge($log_context, [
        'error' => $result->get_error_message(),
    ]));
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError($result->get_error_message(), $message_target, ['addMemberSubmitting' => false, 'addMemberSuccess' => false, 'membersLoading' => false, 'addMemberFormError' => true]);

    return;
}

$logger->info('Add group member succeeded', $log_context);

$full_name = trim(($member_data['first_name'] ?? '') . ' ' . ($member_data['last_name'] ?? ''));
$success_message = wp_sprintf(
    /* translators: 1: member full name, 2: member email address */
    __('Successfully added %1$s with email %2$s.', 'wicket-acc'),
    $full_name !== '' ? $full_name : __('the member', 'wicket-acc'),
    (string) ($member_data['email'] ?? '')
);

$original_group_uuid = $_GET['group_uuid'] ?? null;
$original_org_uuid = $_GET['org_uuid'] ?? null;
$original_page = $_GET['page'] ?? null;
$original_query = $_GET['query'] ?? null;

$_GET['group_uuid'] = $group_uuid;
$_GET['org_uuid'] = $org_uuid;
$_GET['page'] = 1;
$_GET['query'] = '';

ob_start();
include dirname(__DIR__) . '/group-members-list-endpoint.php';
$members_list_html = (string) ob_get_clean();

if ($original_group_uuid === null) {
    unset($_GET['group_uuid']);
} else {
    $_GET['group_uuid'] = $original_group_uuid;
}
if ($original_org_uuid === null) {
    unset($_GET['org_uuid']);
} else {
    $_GET['org_uuid'] = $original_org_uuid;
}
if ($original_page === null) {
    unset($_GET['page']);
} else {
    $_GET['page'] = $original_page;
}
if ($original_query === null) {
    unset($_GET['query']);
} else {
    $_GET['query'] = $original_query;
}

status_header(200);
$orgman_config = ConfigService::getConfig();
$groups_config = is_array($orgman_config['groups'] ?? null) ? $orgman_config['groups'] : [];
$groups_presentation = is_array($groups_config['presentation'] ?? null)
    ? $groups_config['presentation']
    : (is_array($groups_config['ui'] ?? null) ? $groups_config['ui'] : []);
$auto_close_on_success = (bool) ($groups_presentation['add_member_auto_close_on_success'] ?? false);
$auto_close_delay_seconds = max(0, (int) ($groups_presentation['add_member_auto_close_delay_seconds'] ?? 7));
$generator = new ServerSentEventGenerator();
$generator->sendHeaders();
$generator->patchSignals([
    'addMemberSubmitting' => false,
    'addMemberSuccess' => true,
    'addMemberSuccessMessage' => $success_message,
    'membersLoading' => false,
    'autoCloseCountdown' => $auto_close_on_success ? $auto_close_delay_seconds : 0,
]);
$generator->patchElements($members_list_html, [
    'selector' => '#group-members-list-container-' . sanitize_html_class($group_uuid),
    'mode' => ElementPatchMode::Outer,
]);

return;
