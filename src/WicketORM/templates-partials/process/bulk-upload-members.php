<?php

declare(strict_types=1);

use WicketORM\Services\BulkMemberUploadService;
use WicketORM\Services\ConfigService;
use WicketORM\Services\GroupService;
use WicketORM\Services\MembershipService;

if (!defined('ABSPATH')) {
    exit;
}

$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ('POST' !== strtoupper((string) $request_method)) {
    return;
}

$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
if (!$nonce || !wp_verify_nonce($nonce, 'wicket-orgman-bulk-upload-members')) {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('Invalid or missing security token. Please refresh and try again.', 'wicket-acc'),
        '#bulk-upload-messages-default',
        ['membersLoading' => false, 'bulkUploadSubmitting' => false]
    );

    return;
}

$org_uuid = isset($_POST['org_uuid']) ? sanitize_text_field(wp_unslash($_POST['org_uuid'])) : '';
$group_uuid = isset($_POST['group_uuid']) ? sanitize_text_field(wp_unslash($_POST['group_uuid'])) : '';
$membership_uuid = isset($_POST['membership_uuid']) ? sanitize_text_field(wp_unslash($_POST['membership_uuid'])) : '';
$message_dom_suffix_source = $org_uuid !== '' ? $org_uuid : ($group_uuid !== '' ? $group_uuid : 'default');
$org_dom_suffix = sanitize_html_class($message_dom_suffix_source);
$message_target = '#bulk-upload-messages-' . $org_dom_suffix;
$orgman_config = \WicketORM\Services\ConfigService::getConfig();
$member_list_config = is_array($orgman_config['presentation']['member_list'] ?? null)
    ? $orgman_config['presentation']['member_list']
    : [];
$bulk_upload_enabled = (bool) ($member_list_config['show_bulk_upload'] ?? false);

if (!$bulk_upload_enabled) {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('Bulk upload is disabled.', 'wicket-acc'),
        $message_target,
        ['membersLoading' => false, 'bulkUploadSubmitting' => false]
    );

    return;
}

if (empty($_FILES['bulk_file']['tmp_name']) || !is_uploaded_file((string) $_FILES['bulk_file']['tmp_name'])) {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('Please select a valid CSV file.', 'wicket-acc'),
        $message_target,
        ['membersLoading' => false, 'bulkUploadSubmitting' => false]
    );

    return;
}

$configService = new ConfigService();
$roster_mode = (string) $configService->getRosterMode();
$membershipService = new MembershipService();

$group_access = [
    'allowed' => false,
    'org_uuid' => '',
    'org_identifier' => '',
    'role_slug' => '',
];
$group_service = null;

if ($roster_mode === 'groups') {
    if ($group_uuid === '') {
        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderError(
            __('Group identifier missing.', 'wicket-acc'),
            $message_target,
            ['membersLoading' => false, 'bulkUploadSubmitting' => false]
        );

        return;
    }

    $current_user = wp_get_current_user();
    $person_uuid = $current_user ? (string) $current_user->user_login : '';
    $group_service = new GroupService();
    $group_access = $group_service->canManageGroup($group_uuid, $person_uuid);

    if (empty($group_access['allowed'])) {
        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderError(
            __('You do not have permission to bulk add members to this group.', 'wicket-acc'),
            $message_target,
            ['membersLoading' => false, 'bulkUploadSubmitting' => false]
        );

        return;
    }

    $resolved_org_uuid = (string) ($group_access['org_uuid'] ?? '');
    if ($resolved_org_uuid !== '') {
        $org_uuid = $resolved_org_uuid;
    }
} else {
    if (empty($org_uuid)) {
        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderError(
            __('Organization identifier missing.', 'wicket-acc'),
            $message_target,
            ['membersLoading' => false, 'bulkUploadSubmitting' => false]
        );

        return;
    }

    if (!WicketORM\Helpers\PermissionHelper::can_add_members($org_uuid)) {
        status_header(200);
        WicketORM\Helpers\DatastarSSE::renderError(
            __('You do not have permission to bulk add members to this organization.', 'wicket-acc'),
            $message_target,
            ['membersLoading' => false, 'bulkUploadSubmitting' => false]
        );

        return;
    }
}

if ($membership_uuid === '' && $org_uuid !== '') {
    $membership_uuid = (string) $membershipService->getMembershipForOrganization($org_uuid);
}

if ($roster_mode !== 'groups' && $membership_uuid === '') {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('No active organization membership was found for this organization.', 'wicket-acc'),
        $message_target,
        ['membersLoading' => false, 'bulkUploadSubmitting' => false]
    );

    return;
}

$bulk_upload_service = new BulkMemberUploadService($configService);
$result = $bulk_upload_service->enqueueUpload(
    (string) $_FILES['bulk_file']['tmp_name'],
    sanitize_file_name((string) ($_FILES['bulk_file']['name'] ?? 'bulk-upload.csv')),
    $org_uuid,
    $membership_uuid,
    $roster_mode,
    $group_uuid
);

if (is_wp_error($result)) {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        $result->get_error_message(),
        $message_target,
        ['membersLoading' => false, 'bulkUploadSubmitting' => false]
    );

    return;
}

$job_id = (string) ($result['job_id'] ?? '');
$total_records = (int) ($result['total_records'] ?? 0);
$batch_size = (int) ($result['batch_size'] ?? 0);
$summary = sprintf(
    __('Bulk upload queued. Job %1$s will process %2$d row(s) in batches of %3$d.', 'wicket-acc'),
    esc_html($job_id),
    $total_records,
    $batch_size
);
$summary .= '<br>' . esc_html__('Processing runs in the background with WordPress Cron.', 'wicket-acc');

status_header(200);
WicketORM\Helpers\DatastarSSE::renderSuccess($summary, $message_target, [
    'membersLoading' => false,
    'bulkUploadSubmitting' => false,
], 0, 'bulk-upload-countdown');

return;
