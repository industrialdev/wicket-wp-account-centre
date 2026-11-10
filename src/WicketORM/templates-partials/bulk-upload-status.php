<?php

/**
 * Bulk upload job status partial. Polled via REST from the upload modal.
 *
 * Variables:
 *   $job_id (string)
 *   $status (array|null) from BulkMemberUploadService::getJobStatus()
 *   $signal_suffix (string) safe suffix matching the polling modal's signals
 */
if (!defined('ABSPATH')) {
    exit;
}

$job_id = isset($job_id) ? sanitize_key((string) $job_id) : '';
$status = isset($status) && is_array($status) ? $status : null;
$signal_suffix = isset($signal_suffix) ? preg_replace('/[^a-z0-9_]/', '', (string) $signal_suffix) : '';
$finished_signal = 'bulkUploadFinished' . $signal_suffix;

/**
 * Terminal states swap in a signal patch that stops the modal's poll, so the
 * interval does not run forever on a finished (or vanished) job.
 */
$stop_poll = static function () use ($finished_signal): string {
    return '<span data-signals="{ \'' . esc_attr($finished_signal) . '\': true }" class="wt_hidden" aria-hidden="true"></span>';
};

if ($status === null || $job_id === '') {
    // Job pruned or unknown. Say so and stop polling; the retry path is a
    // fresh upload of the same file (allowed unless every row was added).
    echo '<div class="wt_text-sm wt_text-content wt_mb-3">'
        . esc_html__('This upload job is no longer available. If the roster did not update, please upload the CSV again.', 'wicket-acc')
        . $stop_poll()
        . '</div>';

    return;
}

$state = (string) ($status['status'] ?? '');
$total = (int) ($status['total_records'] ?? 0);
$processed = (int) ($status['processed'] ?? 0);
$added = (int) ($status['added'] ?? 0);
$skipped = (int) ($status['skipped'] ?? 0);
$failed = (int) ($status['failed'] ?? 0);
$updated_at = (string) ($status['updated_at'] ?? '');

// WP-Cron is request-triggered; on a quiet site a batch can wait. After ten
// minutes without movement, say so instead of implying active progress.
$stale = in_array($state, ['queued', 'processing'], true)
    && $updated_at !== ''
    && (time() - (int) strtotime($updated_at)) > 600;
?>

<div class="orgman-bulk-upload-status">
    <?php if ($state === 'completed') : ?>
        <?php
        get_component('alert', [
            'classes' => ['wt_bg-green-100', 'wt_border', 'wt_border-green-400', 'wt_text-green-700'],
            'content' => esc_html(
                sprintf(
                    /* translators: 1: number added, 2: number skipped, 3: number failed */
                    __('Bulk upload complete — %1$d added, %2$d skipped, %3$d failed.', 'wicket-acc'),
                    $added,
                    $skipped,
                    $failed
                )
            ),
        ]);

        if ($failed > 0 && !empty($status['error_snippets'])) :
            echo '<ul class="wt_list-disc wt_pl-5 wt_text-sm wt_text-content wt_mt-1">';
            foreach (array_slice((array) $status['error_snippets'], 0, 5) as $snippet) {
                echo '<li>' . esc_html((string) $snippet) . '</li>';
            }
            echo '</ul>';
        endif;

        echo $stop_poll();
        ?>
    <?php elseif ($state === 'failed') : ?>
        <?php
        get_component('alert', [
            'classes' => ['wt_bg-red-100', 'wt_border', 'wt_border-red-400', 'wt_text-red-700'],
            'content' => esc_html__('Bulk upload failed. Please upload the CSV again or contact support.', 'wicket-acc'),
        ]);

        echo $stop_poll();
        ?>
    <?php elseif (in_array($state, ['queued', 'processing'], true)) : ?>
        <?php
        get_component('alert', [
            'classes' => ['wt_bg-blue-100', 'wt_border', 'wt_border-blue-400', 'wt_text-blue-700'],
            'content' => esc_html(
                $stale
                    ? sprintf(
                        /* translators: 1: rows processed, 2: total rows */
                        __('Still working — %1$d of %2$d row(s) processed. This is taking longer than expected; you can leave this page and check the roster later.', 'wicket-acc'),
                        $processed,
                        $total
                    )
                    : sprintf(
                        /* translators: 1: rows processed, 2: total rows */
                        __('Bulk upload in progress — %1$d of %2$d row(s) processed…', 'wicket-acc'),
                        $processed,
                        $total
                    )
            ),
        ]);
        ?>
    <?php else : ?>
        <?php
        // Unknown state: stop polling rather than spin forever.
        echo '<div class="wt_text-sm wt_text-content wt_mb-3">'
            . esc_html__('This upload job is in an unknown state. If the roster did not update, please upload the CSV again.', 'wicket-acc')
            . $stop_poll()
            . '</div>';
        ?>
    <?php endif; ?>
</div>
