<?php

declare(strict_types=1);

use WicketORM\Controllers\BulkUploadController;
use WicketORM\Services\BulkMemberUploadService;
use WicketORM\Services\ConfigService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SSE status poll endpoint for the bulk upload modal (WWID-1919).
 *
 * Served through the hypermedia template router (?action=hypermedia&template=process/bulk-upload-status).
 * The modal's interval calls @get on this URL while a job id is set; every
 * response is a Datastar SSE stream that patches the rendered status partial
 * into the modal's messages div. Terminal states also flip the modal's
 * finished signal so the poller stops.
 *
 * Replaces the retired REST /bulk-upload/status route, whose JSON-wrapped
 * body could never be merged by Datastar.
 *
 * Query params:
 *   job_id (string) The bulk upload job id (set as a signal by the enqueue response).
 *   suffix (string) JS-safe signal suffix ([a-z0-9_]) shared with the modal.
 *   target (string) CSS id selector of the modal's messages div.
 */

$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ('GET' !== strtoupper((string) $request_method)) {
    return;
}

$suffix = isset($_GET['suffix']) ? preg_replace('/[^a-z0-9_]/', '', (string) wp_unslash($_GET['suffix'])) : '';
$target = isset($_GET['target']) && preg_match('/^#[a-zA-Z0-9_-]+$/', (string) $_GET['target'])
    ? (string) $_GET['target']
    : '#bulk-upload-messages-default';
$finished_signal = 'bulkUploadFinished' . $suffix;

/**
 * Fail closed but stop the poller: an SSE patch with the finished signal set
 * ends the interval without leaking any job detail.
 */
$stop_poll = static function (string $message) use ($target, $finished_signal): void {
    status_header(200);
    WicketORM\Helpers\DatastarSSE::patchHtml(
        '<div class="wt_text-sm wt_text-content wt_mb-3">' . esc_html($message) . '</div>',
        $target,
        [$finished_signal => true]
    );
};

if (!is_user_logged_in()) {
    $stop_poll(__('Your session has expired. Please refresh the page.', 'wicket-acc'));

    return;
}

$job_id = isset($_GET['job_id']) ? sanitize_key(wp_unslash($_GET['job_id'])) : '';
if ($job_id === '') {
    $stop_poll(__('This upload job is no longer available. If the roster did not update, please upload the CSV again.', 'wicket-acc'));

    return;
}

$config_service = new ConfigService();
$status = (new BulkMemberUploadService($config_service))->getJobStatus($job_id);

if (!is_array($status)) {
    $stop_poll(__('This upload job is no longer available. If the roster did not update, please upload the CSV again.', 'wicket-acc'));

    return;
}

if (!BulkUploadController::currentUserCanViewJobStatus($status)) {
    $stop_poll(__('You do not have permission to view this upload.', 'wicket-acc'));

    return;
}

ob_start();
include __DIR__ . '/../bulk-upload-status.php';
$html = (string) ob_get_clean();

// Terminal states stop the poller explicitly. The partial also carries a
// stop_poll span whose data-signals re-init on patch; the explicit patch
// keeps the halt deterministic even if that span is restructured later.
$state = (string) ($status['status'] ?? '');
$signals = in_array($state, ['completed', 'failed'], true) ? [$finished_signal => true] : [];

status_header(200);
WicketORM\Helpers\DatastarSSE::patchHtml($html, $target, $signals);

return;
