<?php

/**
 * Hypermedia process handler for initiating a member export.
 *
 * Handles POST: validates nonce + permission, enqueues export job, sends SSE response.
 */

use WicketORM\Services\ConfigService;
use WicketORM\Services\MemberExportService;

if (!defined('ABSPATH')) {
    exit;
}

$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ('POST' !== strtoupper($request_method)) {
    return;
}

$org_uuid = isset($_POST['org_id']) ? sanitize_text_field(wp_unslash($_POST['org_id'])) : '';
$org_dom_suffix = isset($_POST['org_dom_suffix'])
    ? sanitize_html_class((string) wp_unslash($_POST['org_dom_suffix']))
    : sanitize_html_class($org_uuid ?: 'default');

$error_signals = [$org_dom_suffix . '_exportSubmitting' => false];

// Nonce check
$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
if (!$nonce || !wp_verify_nonce($nonce, 'wicket_orgman_export_' . $org_uuid)) {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('Security verification failed. Please refresh and try again.', 'wicket-acc'),
        '#export-messages-' . $org_dom_suffix,
        $error_signals
    );

    return;
}

if (empty($org_uuid)) {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('Organization identifier is missing.', 'wicket-acc'),
        '#export-messages-' . $org_dom_suffix,
        $error_signals
    );

    return;
}

// Permission check
if (!WicketORM\Helpers\PermissionHelper::can_edit_members($org_uuid)) {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::renderError(
        __('You do not have permission to export members for this organization.', 'wicket-acc'),
        '#export-messages-' . $org_dom_suffix,
        $error_signals
    );

    return;
}

$membership_uuid = isset($_POST['membership_uuid']) ? sanitize_text_field(wp_unslash($_POST['membership_uuid'])) : '';
$recipient_email = isset($_POST['recipient_email'])
    ? sanitize_email(wp_unslash($_POST['recipient_email']))
    : sanitize_email(wp_get_current_user()->user_email ?? '');

$export_service = new MemberExportService(new ConfigService());
$result = $export_service->enqueueExport($org_uuid, $membership_uuid, $recipient_email);

status_header(200);

if (is_wp_error($result)) {
    WicketORM\Helpers\DatastarSSE::renderError(
        $result->get_error_message(),
        '#export-messages-' . $org_dom_suffix,
        $error_signals
    );

    return;
}

WicketORM\Helpers\DatastarSSE::renderSuccess(
    sprintf(
        /* translators: %s: email address */
        esc_html__('Your export has been queued. You will receive an email at %s when the download link is ready.', 'wicket-acc'),
        esc_html($recipient_email)
    ),
    '#export-messages-' . $org_dom_suffix,
    [$org_dom_suffix . '_exportSubmitting' => false, $org_dom_suffix . '_exportQueued' => true]
);
