<?php

/**
 * Export job status partial.
 *
 * Variables:
 *   $job_id (string)
 *   $status (array|null) from MemberExportService::getJobStatus()
 */
if (!defined('ABSPATH')) {
    exit;
}

$job_id = isset($job_id) ? sanitize_key((string) $job_id) : '';
$status = isset($status) && is_array($status) ? $status : null;

if ($status === null || $job_id === '') {
    return;
}

$state = (string) ($status['status'] ?? '');
$total = (int) ($status['total_processed'] ?? 0);
$pages = $status['total_pages'];
$current = (int) ($status['current_page'] ?? 1);
?>

<div class="wt_export-status">
    <?php if ($state === 'completed') : ?>
        <?php
        get_component('alert', [
            'classes' => ['wt_bg-green-100', 'wt_border', 'wt_border-green-400', 'wt_text-green-700'],
            'content' => esc_html(sprintf(
                /* translators: %d: number of members exported */
                _n('Export complete — %d member exported.', 'Export complete — %d members exported.', $total, 'wicket-acc'),
                $total
            )),
        ]);
        ?>
    <?php elseif ($state === 'failed') : ?>
        <?php
        get_component('alert', [
            'classes' => ['wt_bg-red-100', 'wt_border', 'wt_border-red-400', 'wt_text-red-700'],
            'content' => esc_html__('Export failed. Please try again or contact support.', 'wicket-acc'),
        ]);
        ?>
    <?php elseif (in_array($state, ['queued', 'processing'], true)) : ?>
        <?php
        $progress_message = $pages !== null
            ? esc_html(sprintf(
                /* translators: 1: current page, 2: total pages */
                __('Export in progress — page %1$d of %2$d…', 'wicket-acc'),
                $current,
                (int) $pages
            ))
            : esc_html__('Export queued — processing will begin shortly…', 'wicket-acc');

        get_component('alert', [
            'classes' => ['wt_bg-blue-100', 'wt_border', 'wt_border-blue-400', 'wt_text-blue-700'],
            'content' => $progress_message,
        ]);
        ?>
    <?php endif; ?>
</div>
