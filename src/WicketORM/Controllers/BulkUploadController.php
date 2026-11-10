<?php

/**
 * Bulk upload controller.
 */

namespace WicketORM\Controllers;

use WicketORM\Services\BulkMemberUploadService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles REST routes for the CSV bulk member upload.
 */
class BulkUploadController extends ApiController
{
    /**
     * @var BulkMemberUploadService
     */
    private $bulk_upload_service;

    /**
     * @param BulkMemberUploadService $bulk_upload_service
     */
    public function __construct(BulkMemberUploadService $bulk_upload_service)
    {
        $this->bulk_upload_service = $bulk_upload_service;
        $this->namespace = 'wicket-acc/v1/bulk-upload';
    }

    /**
     * Register REST routes.
     */
    public function registerRoutes()
    {
        register_rest_route($this->namespace, '/status', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getBulkUploadStatus'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);
    }

    /**
     * Return the current status of a bulk upload job as HTML.
     *
     * Access is scoped to the same right that created the job: org-level
     * add-member permission for seat strategies, group management for groups
     * mode. Anything inconclusive fails closed with an empty 403 so the job's
     * existence does not leak (mirrors the member export status endpoint).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getBulkUploadStatus(WP_REST_Request $request)
    {
        $job_id = sanitize_key($request->get_param('job_id'));

        if (empty($job_id)) {
            return new WP_REST_Response('', 200);
        }

        $status = $this->bulk_upload_service->getJobStatus($job_id);

        if (!is_array($status)) {
            // Unknown or pruned job: render the stop-poll notice (no job
            // details) so the modal's poller halts instead of spinning. The
            // suffix comes from the poller itself; it only names the signal
            // to flip and authorizes nothing.
            return $this->templateResponse('bulk-upload-status', [
                'job_id' => $job_id,
                'status' => null,
                'signal_suffix' => sanitize_key((string) $request->get_param('suffix')),
            ]);
        }

        if (!self::currentUserCanViewJobStatus($status)) {
            return new WP_REST_Response('', 403);
        }

        return $this->templateResponse('bulk-upload-status', [
            'job_id' => $job_id,
            'status' => $status,
            'signal_suffix' => self::statusSignalSuffix($status),
        ]);
    }

    /**
     * Whether the current user may see a job's status.
     *
     * Mirrors the enqueue-time gate in process/bulk-upload-members.php: seat
     * strategies check org-level add-member permission, groups mode checks
     * group management (a distinct role model — an org-level membership
     * manager is not automatically every group's manager). Empty identifiers
     * fail closed.
     *
     * @param array<string, mixed> $status Job status payload.
     * @return bool
     */
    public static function currentUserCanViewJobStatus(array $status): bool
    {
        $roster_mode = (string) ($status['roster_mode'] ?? '');
        $org_uuid = (string) ($status['org_uuid'] ?? '');
        $group_uuid = (string) ($status['group_uuid'] ?? '');

        if ($roster_mode === 'groups') {
            if ($group_uuid === '') {
                return false;
            }

            $current_user = wp_get_current_user();
            $manager_uuid = $current_user ? (string) $current_user->user_login : '';
            $access = (new \WicketORM\Services\GroupService())->canManageGroup($group_uuid, $manager_uuid);

            return !empty($access['allowed']);
        }

        if ($org_uuid === '') {
            return false;
        }

        return \WicketORM\Helpers\PermissionHelper::can_add_members($org_uuid);
    }

    /**
     * Signal suffix for the status partial, mirroring the polling modal's
     * three-way DOM suffix (org_uuid -> group_uuid -> 'default') so the
     * terminal stop-signal patch flips the signal the modal actually reads.
     *
     * @param array<string, mixed> $status Job status payload.
     * @return string
     */
    public static function statusSignalSuffix(array $status): string
    {
        $dom_suffix_raw = (string) ($status['org_uuid'] ?? '');
        if ($dom_suffix_raw === '') {
            $dom_suffix_raw = (string) ($status['group_uuid'] ?? '');
        }
        if ($dom_suffix_raw === '') {
            $dom_suffix_raw = 'default';
        }

        return str_replace('-', '_', sanitize_key($dom_suffix_raw));
    }
}
