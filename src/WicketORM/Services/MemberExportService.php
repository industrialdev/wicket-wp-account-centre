<?php

declare(strict_types=1);

namespace WicketORM\Services;

use WicketWP\Support\CsvExporter;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles CSV member exports.
 *
 * Two paths share one download mechanism (handleDownload on `init`, streaming
 * via readfile):
 *   - SYNC:  roster at or below the configured threshold is built in-request
 *            to a temp file, then a tokenized GET URL is issued. Avoids WP-Cron
 *            for the common small-roster case (cron only fires on traffic).
 *   - ASYNC: larger rosters go through WP-Cron batches and email the link.
 *
 * Columns derive from BulkMemberUploadService::getExportColumns() so an
 * exported CSV round-trips through the roster bulk-upload without drift.
 * AD14: every cell write goes through WicketWP\Support\CsvExporter.
 */
class MemberExportService
{
    public const OPTION_KEY = 'wicket_orgman_export_job_ids';
    public const JOB_OPTION_PREFIX = 'wicket_orgman_export_job_';
    public const TOKEN_OPTION_PREFIX = 'wicket_orgman_dl_token_';
    public const CRON_HOOK = 'wicket_orgman_process_member_export';
    public const CLEANUP_HOOK = 'wicket_orgman_cleanup_member_export';
    public const QUERY_VAR = 'wicket_orgman_download_export';

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var CsvExporter
     */
    private $csvExporter;

    /**
     * @var BulkMemberUploadService|null Lazily instantiated; only when columns are needed.
     */
    private $bulkUploadService;

    /**
     * @param ConfigService|null           $configService
     * @param BulkMemberUploadService|null $bulkUploadService Injected for testability.
     */
    public function __construct(?ConfigService $configService = null, ?BulkMemberUploadService $bulkUploadService = null)
    {
        $this->configService = $configService ?? new ConfigService();
        $this->csvExporter = new CsvExporter();
        $this->bulkUploadService = $bulkUploadService;
    }

    /**
     * Resolve the export column set from the upload service (single source).
     *
     * @return array<string, array{enabled: bool, header: string}>
     */
    private function resolveExportColumns(): array
    {
        if ($this->bulkUploadService === null) {
            $this->bulkUploadService = new BulkMemberUploadService($this->configService);
        }

        return $this->bulkUploadService->getExportColumns();
    }

    /**
     * Enqueue a new export job and schedule the first cron batch.
     *
     * @param string $org_id
     * @param string $membership_uuid
     * @param string $recipient_email
     * @return array<string, mixed>|WP_Error
     */
    public function enqueueExport(string $org_id, string $membership_uuid, string $recipient_email)
    {
        if ($org_id === '') {
            return new WP_Error('export_missing_org', __('Organization ID is required.', 'wicket-acc'));
        }
        if ($membership_uuid === '') {
            return new WP_Error('export_missing_membership', __('Membership UUID is required.', 'wicket-acc'));
        }
        if (!is_email($recipient_email)) {
            return new WP_Error('export_invalid_email', __('A valid recipient email is required.', 'wicket-acc'));
        }

        $existing = $this->findActiveJobForOrg($org_id, $recipient_email);
        if ($existing !== null) {
            return new WP_Error(
                'export_duplicate_active_job',
                sprintf(
                    __('An export for this organization is already in progress (job: %1$s, status: %2$s).', 'wicket-acc'),
                    (string) ($existing['id'] ?? ''),
                    (string) ($existing['status'] ?? '')
                )
            );
        }

        $config = $this->configService->getFullConfig();
        $export_config = is_array($config['exports'] ?? null) ? $config['exports'] : [];
        $batch_size = max(1, min(500, (int) ($export_config['batch_size'] ?? 50)));

        $job_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('orgman_export_', true);
        $job_id = sanitize_key(str_replace('-', '', (string) $job_id));

        $job = [
            'id'              => $job_id,
            'status'          => 'queued',
            'created_at'      => $this->nowIso8601(),
            'updated_at'      => $this->nowIso8601(),
            'completed_at'    => null,
            'org_id'          => $org_id,
            'membership_uuid' => $membership_uuid,
            'recipient_email' => $recipient_email,
            'file_path'       => '',
            'download_token'  => '',
            'current_page'    => 1,
            'total_pages'     => null,
            'total_processed' => 0,
            'batch_size'      => $batch_size,
        ];

        $this->saveJob($job);

        if (!$this->scheduleNextBatch($job_id, 2)) {
            $job['status'] = 'failed';
            $job['updated_at'] = $this->nowIso8601();
            $this->saveJob($job);
            $this->logActivity('error', 'Export job enqueue failed: unable to schedule first batch', ['job_id' => $job_id]);

            return new WP_Error('export_schedule_failed', __('Unable to schedule background export.', 'wicket-acc'));
        }

        $this->logActivity('info', 'Export job queued', [
            'job_id'          => $job_id,
            'org_id'          => $org_id,
            'membership_uuid' => $membership_uuid,
            'batch_size'      => $batch_size,
        ]);

        return ['job_id' => $job_id, 'status' => 'queued'];
    }

    /**
     * Build the export in-request (sync) when the roster is at or below the
     * configured threshold; otherwise delegate to the async cron path.
     *
     * The count is read from the first real page fetch (`meta.page.total_items`),
     * not a separate probe round-trip. The defensive fallback overestimates
     * (total_pages * batch_size) which biases to async — fails safe.
     *
     * Sync path writes to a temp file fully BEFORE issuing a token, so an MDP
     * failure mid-stream produces no partial file and no token — the caller
     * surfaces an error instead of handing the user a truncated CSV. The token
     * uses the same 14-day TTL and CLEANUP_HOOK as the async path; both share
     * {@see handleDownload()} for the actual stream-to-browser.
     *
     * @param string $org_id
     * @param string $membership_uuid
     * @return array{mode: string, token?: string, job_id?: string}|WP_Error
     *     mode 'sync'  -> caller issues a browser redirect to the token URL.
     *     mode 'async' -> caller shows the "queued, email coming" message.
     */
    public function streamExport(string $org_id, string $membership_uuid, ?string $recipient_email = null)
    {
        if ($org_id === '') {
            return new WP_Error('export_missing_org', __('Organization ID is required.', 'wicket-acc'));
        }
        if ($membership_uuid === '') {
            return new WP_Error('export_missing_membership', __('Membership UUID is required.', 'wicket-acc'));
        }

        $config = $this->configService->getFullConfig();
        $export_config = is_array($config['exports'] ?? null) ? $config['exports'] : [];
        $batch_size = max(1, min(500, (int) ($export_config['batch_size'] ?? 50)));
        $threshold = max(0, (int) ($export_config['sync_threshold'] ?? 250));

        // Fetch page 1 — count comes free, no separate probe.
        $first = $this->fetchPersonMembershipsPage($membership_uuid, 1, $batch_size);
        if (is_wp_error($first)) {
            return $first;
        }

        $total_items = (int) ($first['meta']['page']['total_items'] ?? 0);
        if ($total_items === 0) {
            // Defensive fallback: overestimate -> biases async -> fails safe.
            $total_pages = max(1, (int) ($first['meta']['page']['total_pages'] ?? 1));
            $total_items = $total_pages * $batch_size;
        }

        // Over threshold (or threshold disabled=0 forces async) -> async path.
        if ($threshold === 0 || $total_items > $threshold) {
            $recipient = $recipient_email ?? sanitize_email(wp_get_current_user()->user_email ?? '');
            if (!is_email($recipient)) {
                return new WP_Error('export_invalid_email', __('A valid recipient email is required.', 'wicket-acc'));
            }
            $result = $this->enqueueExport($org_id, $membership_uuid, $recipient);
            if (is_wp_error($result)) {
                return $result;
            }

            return ['mode' => 'async', 'job_id' => (string) ($result['job_id'] ?? '')];
        }

        // Sync path: build the file in-request.
        $job_id = $this->generateJobId();
        $init = $this->initCsvFile($job_id, $export_config);
        if (is_wp_error($init)) {
            return $init;
        }
        $file_path = $init;
        $columns = $this->resolveExportColumns();

        $fh = fopen($file_path, 'a');
        if ($fh === false) {
            wp_delete_file($file_path);

            return new WP_Error('export_file_failed', __('Unable to write to export file.', 'wicket-acc'));
        }

        try {
            // Page 1 members.
            $total_pages = max(1, (int) ($first['meta']['page']['total_pages'] ?? 1));
            $processed = $this->writeMembersPage($first, $columns, $fh);

            // Pages 2..N.
            for ($page = 2; $page <= $total_pages; $page++) {
                $next = $this->fetchPersonMembershipsPage($membership_uuid, $page, $batch_size);
                if (is_wp_error($next)) {
                    fclose($fh);
                    wp_delete_file($file_path);

                    return $next;
                }
                $processed += $this->writeMembersPage($next, $columns, $fh);
            }
        } finally {
            if (is_resource($fh)) {
                fclose($fh);
            }
        }

        // File built. Issue token + schedule cleanup (same lifecycle as async).
        $token = bin2hex(random_bytes(32));
        $job = [
            'id'              => $job_id,
            'status'          => 'completed',
            'created_at'      => $this->nowIso8601(),
            'updated_at'      => $this->nowIso8601(),
            'completed_at'    => $this->nowIso8601(),
            'org_id'          => $org_id,
            'membership_uuid' => $membership_uuid,
            'recipient_email' => '',
            'file_path'       => $file_path,
            'download_token'  => $token,
            'current_page'    => $total_pages,
            'total_pages'     => $total_pages,
            'total_processed' => $processed,
            'batch_size'      => $batch_size,
        ];
        $this->saveJob($job);
        $this->saveToken($job_id, $token, $export_config);
        $this->scheduleCleanup($job_id, $export_config);

        $this->logActivity('info', 'Sync export completed', [
            'job_id'          => $job_id,
            'org_id'          => $org_id,
            'total_processed' => $processed,
        ]);

        return ['mode' => 'sync', 'token' => $token];
    }

    /**
     * Fetch one page of active person_memberships for an org-membership.
     *
     * @return array<string, mixed>|WP_Error
     */
    private function fetchPersonMembershipsPage(string $membership_uuid, int $page, int $size)
    {
        if (!function_exists('wicket_api_client')) {
            return new WP_Error('export_no_api', __('Wicket API client is unavailable.', 'wicket-acc'));
        }

        try {
            $client = wicket_api_client();
            $response = $client->get(
                '/organization_memberships/' . rawurlencode($membership_uuid) . '/person_memberships?' . http_build_query([
                    'page'    => ['number' => $page, 'size' => $size],
                    'sort'    => 'person_family_name',
                    'include' => 'person',
                    'filter'  => ['status_eq' => 'Active'],
                ])
            );
        } catch (\Throwable $e) {
            return new WP_Error('export_api_error', $e->getMessage());
        }

        if (is_wp_error($response)) {
            return $response;
        }

        return $response;
    }

    /**
     * Write all members from one API response page to the CSV handle.
     *
     * @param array<string, mixed>                           $response
     * @param array<string, array{enabled: bool, header: string}> $columns
     * @param resource                                       $fh
     * @return int Number of rows written.
     */
    private function writeMembersPage(array $response, array $columns, $fh): int
    {
        $members = is_array($response['data'] ?? null) ? $response['data'] : [];
        $person_lookup = [];
        foreach (is_array($response['included'] ?? null) ? $response['included'] : [] as $included) {
            if (($included['type'] ?? '') === 'people') {
                $person_lookup[(string) ($included['id'] ?? '')] = is_array($included['attributes'] ?? null)
                    ? $included['attributes']
                    : [];
            }
        }

        $written = 0;
        foreach ($members as $member) {
            $person_id = (string) ($member['relationships']['person']['data']['id'] ?? '');
            $person_data = is_array($person_lookup[$person_id] ?? null) ? $person_lookup[$person_id] : [];
            $this->csvExporter->writeRow($this->buildCsvRow($member, $person_data, $columns), $fh);
            $written++;
        }

        return $written;
    }

    /**
     * @return string Sanitized job id (uuid with dashes stripped).
     */
    private function generateJobId(): string
    {
        $raw = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('orgman_export_', true);

        return sanitize_key(str_replace('-', '', (string) $raw));
    }

    /**
     * Process one scheduled batch. Called by the CRON_HOOK action.
     *
     * @param string $job_id
     * @return void
     */
    public function processScheduledJob(string $job_id): void
    {
        $job = $this->getJob($job_id);
        if (empty($job) || !is_array($job)) {
            $this->logActivity('error', 'Export job not found', ['job_id' => $job_id]);

            return;
        }

        $status = (string) ($job['status'] ?? '');
        if ($status === 'completed' || $status === 'failed' || $status === 'expired') {
            return;
        }

        if (!function_exists('wicket_api_client')) {
            $this->failJob($job, __('Wicket API client is unavailable.', 'wicket-acc'));

            return;
        }

        $job['status'] = 'processing';
        $job['updated_at'] = $this->nowIso8601();
        $this->saveJob($job);

        $config = $this->configService->getFullConfig();
        $export_config = is_array($config['exports'] ?? null) ? $config['exports'] : [];
        $batch_size = max(1, (int) ($job['batch_size'] ?? 50));
        $current_page = max(1, (int) ($job['current_page'] ?? 1));
        $membership_uuid = (string) ($job['membership_uuid'] ?? '');
        $org_id = (string) ($job['org_id'] ?? '');

        // Initialise CSV file on the first batch
        if ($current_page === 1) {
            $init = $this->initCsvFile($job_id, $export_config);
            if (is_wp_error($init)) {
                $this->failJob($job, $init->get_error_message());

                return;
            }
            $job['file_path'] = $init;
        }

        $file_path = (string) ($job['file_path'] ?? '');
        if ($file_path === '' || !file_exists($file_path)) {
            $this->failJob($job, __('Export file is missing or inaccessible.', 'wicket-acc'));

            return;
        }

        // Fetch one page from MDP
        try {
            $client = wicket_api_client();
            $response = $client->get(
                '/organization_memberships/' . rawurlencode($membership_uuid) . '/person_memberships?' . http_build_query([
                    'page'    => ['number' => $current_page, 'size' => $batch_size],
                    'sort'    => 'person_family_name',
                    'include' => 'person',
                    'filter'  => ['status_eq' => 'Active'],
                ])
            );
        } catch (\Throwable $e) {
            $this->failJob($job, $e->getMessage());

            return;
        }

        if (is_wp_error($response)) {
            $this->failJob($job, $response->get_error_message());

            return;
        }

        $members = is_array($response['data'] ?? null) ? $response['data'] : [];
        $total_pages = max(1, (int) ($response['meta']['page']['total_pages'] ?? 1));

        $job['total_pages'] = $total_pages;

        // Build a person UUID → attributes lookup from included resources
        $person_lookup = [];
        foreach (is_array($response['included'] ?? null) ? $response['included'] : [] as $included) {
            if (($included['type'] ?? '') === 'people') {
                $person_lookup[(string) ($included['id'] ?? '')] = is_array($included['attributes'] ?? null)
                    ? $included['attributes']
                    : [];
            }
        }

        $fh = fopen($file_path, 'a');
        if ($fh === false) {
            $this->failJob($job, __('Unable to write to export file.', 'wicket-acc'));

            return;
        }

        $columns = $this->resolveExportColumns();
        foreach ($members as $member) {
            $person_id = (string) ($member['relationships']['person']['data']['id'] ?? '');
            $person_data = is_array($person_lookup[$person_id] ?? null) ? $person_lookup[$person_id] : [];
            $this->csvExporter->writeRow($this->buildCsvRow($member, $person_data, $columns), $fh);
            $job['total_processed']++;
        }

        fclose($fh);

        if ($current_page >= $total_pages) {
            // Export complete — generate token, notify, schedule cleanup
            $token = bin2hex(random_bytes(32));
            $job['download_token'] = $token;
            $job['status'] = 'completed';
            $job['completed_at'] = $this->nowIso8601();
            $job['updated_at'] = $this->nowIso8601();
            $this->saveJob($job);

            $this->saveToken($job_id, $token, $export_config);
            $this->sendCompletionEmail($job, $token, $export_config);
            $this->scheduleCleanup($job_id, $export_config);

            $this->logActivity('info', 'Export job completed', [
                'job_id'          => $job_id,
                'total_processed' => $job['total_processed'],
            ]);

            return;
        }

        // More pages — chain next batch
        $job['current_page'] = $current_page + 1;
        $job['status'] = 'queued';
        $job['updated_at'] = $this->nowIso8601();
        $this->saveJob($job);

        $this->logActivity('info', 'Export batch finished; scheduling next', [
            'job_id'       => $job_id,
            'current_page' => $job['current_page'],
            'total_pages'  => $total_pages,
        ]);

        if (!$this->scheduleNextBatch($job_id, 2)) {
            $this->failJob($job, __('Unable to schedule next export batch.', 'wicket-acc'));
        }
    }

    /**
     * Handle a secure file download. Hooked to `init`.
     *
     * @return void
     */
    public function handleDownload(): void
    {
        $token = isset($_GET[self::QUERY_VAR]) ? sanitize_text_field((string) wp_unslash($_GET[self::QUERY_VAR])) : '';
        if ($token === '') {
            return;
        }

        $token_data = get_option(self::TOKEN_OPTION_PREFIX . $token, null);
        if (!is_array($token_data)) {
            status_header(404);
            wp_die(esc_html__('Download link not found.', 'wicket-acc'));
        }

        $expires_at = (int) ($token_data['expires_at'] ?? 0);
        if ($expires_at > 0 && time() > $expires_at) {
            status_header(410);
            wp_die(esc_html__('This download link has expired.', 'wicket-acc'));
        }

        $config = $this->configService->getFullConfig();
        $export_config = is_array($config['exports'] ?? null) ? $config['exports'] : [];
        $max_downloads = max(1, (int) ($export_config['max_downloads'] ?? 10));
        $download_count = (int) ($token_data['download_count'] ?? 0);

        if ($download_count >= $max_downloads) {
            status_header(410);
            wp_die(esc_html__('This download link has reached its maximum usage limit.', 'wicket-acc'));
        }

        $job_id = (string) ($token_data['job_id'] ?? '');
        $job = $this->getJob($job_id);
        $file_path = is_array($job) ? (string) ($job['file_path'] ?? '') : '';

        if ($file_path === '' || !file_exists($file_path)) {
            status_header(404);
            wp_die(esc_html__('Export file not found.', 'wicket-acc'));
        }

        $token_data['download_count'] = $download_count + 1;
        update_option(self::TOKEN_OPTION_PREFIX . $token, $token_data, false);

        $filename = 'org-members-' . sanitize_file_name($job_id) . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($file_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile

        if ($token_data['download_count'] >= $max_downloads) {
            $this->cleanupExpiredExport($job_id);
        }

        exit;
    }

    /**
     * Delete the CSV file and token. Called by CLEANUP_HOOK cron action.
     *
     * @param string $job_id
     * @return void
     */
    public function cleanupExpiredExport(string $job_id): void
    {
        $job = $this->getJob($job_id);
        if (!is_array($job)) {
            return;
        }

        $file_path = (string) ($job['file_path'] ?? '');
        if ($file_path !== '' && file_exists($file_path)) {
            wp_delete_file($file_path);
        }

        $token = (string) ($job['download_token'] ?? '');
        if ($token !== '') {
            delete_option(self::TOKEN_OPTION_PREFIX . $token);
        }

        $job['status'] = 'expired';
        $job['updated_at'] = $this->nowIso8601();
        $this->saveJob($job);

        $this->logActivity('info', 'Export job cleaned up', ['job_id' => $job_id]);
    }

    /**
     * Return a status summary for a job (safe for controller output).
     *
     * @param string $job_id
     * @return array<string, mixed>|null
     */
    public function getJobStatus(string $job_id): ?array
    {
        $job = $this->getJob($job_id);
        if (!is_array($job)) {
            return null;
        }

        return [
            'id'              => (string) ($job['id'] ?? ''),
            'status'          => (string) ($job['status'] ?? ''),
            'created_at'      => (string) ($job['created_at'] ?? ''),
            'updated_at'      => (string) ($job['updated_at'] ?? ''),
            'completed_at'    => (string) ($job['completed_at'] ?? ''),
            'org_id'          => (string) ($job['org_id'] ?? ''),
            'total_pages'     => $job['total_pages'],
            'current_page'    => (int) ($job['current_page'] ?? 1),
            'total_processed' => (int) ($job['total_processed'] ?? 0),
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build one CSV data row for a member, emitting only the columns that are
     * enabled in the shared export column set (same source as bulk upload).
     *
     * Column order matches {@see self::headerRow()}: first_name, last_name,
     * email, relationship_type, roles. Values are escaped downstream by
     * CsvExporter::writeRow(); here we only coerce to strings.
     *
     * NOTE on roles round-trip (WWID-1907 known limitation): export joins roles
     * with '|'; import splits on /[|]+/. Formats match, but resolvePermissionRoles()
     * emits only [membership_manager, org_editor, membership_owner] while import
     * re-applies the client's filter_role_submission allow/deny list. A role present
     * in the export can be silently dropped on re-import. 'roles' is optional for
     * upload so this is semantic data loss, not a validation failure. Aligning the
     * export's role emission with the import filter is a future ticket.
     *
     * @param array<string, mixed> $member     API person_membership resource.
     * @param array<string, mixed> $person_data Attributes from included person resource.
     * @param array<string, array{enabled: bool, header: string}> $columns
     * @return list<string>
     */
    private function buildCsvRow(array $member, array $person_data, array $columns): array
    {
        $row = [];

        if ($columns['first_name']['enabled'] ?? true) {
            $row[] = (string) ($person_data['given_name'] ?? '');
        }
        if ($columns['last_name']['enabled'] ?? true) {
            $row[] = (string) ($person_data['family_name'] ?? '');
        }
        if ($columns['email']['enabled'] ?? true) {
            $row[] = (string) ($person_data['primary_email_address'] ?? '');
        }
        if ($columns['relationship_type']['enabled'] ?? true) {
            $row[] = (string) ($member['attributes']['relationship_type'] ?? '');
        }
        if ($columns['roles']['enabled'] ?? true) {
            $row[] = implode('|', $this->resolvePermissionRoles($member));
        }

        return $row;
    }

    /**
     * The CSV header row, derived from the same shared column set as the data rows.
     * Headers are literal English by design (locale-invariant) so a CSV exported
     * under one site locale round-trips through upload regardless of locale.
     * DO NOT wrap in __().
     *
     * @param array<string, array{enabled: bool, header: string}> $columns
     * @return list<string>
     */
    private function headerRow(array $columns): array
    {
        $headers = [];
        foreach ($columns as $key => $def) {
            if ($def['enabled'] ?? false) {
                $headers[] = (string) ($def['header'] ?? $key);
            }
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $member
     * @return list<string>
     */
    private function resolvePermissionRoles(array $member): array
    {
        $roles = [];
        $member_roles = is_array($member['attributes']['roles'] ?? null) ? $member['attributes']['roles'] : [];
        $permission_roles = ['membership_manager', 'org_editor', 'membership_owner'];

        foreach ($member_roles as $role) {
            $slug = sanitize_key((string) ($role['name'] ?? ($role['slug'] ?? '')));
            if (in_array($slug, $permission_roles, true)) {
                $roles[] = $slug;
            }
        }

        return $roles;
    }

    /**
     * Create the CSV file in the uploads directory and write the header row.
     *
     * @param string               $job_id
     * @param array<string, mixed> $export_config
     * @return string|WP_Error Absolute path to the file.
     */
    private function initCsvFile(string $job_id, array $export_config): string|WP_Error
    {
        $upload_dir = wp_upload_dir();
        $dir_slug = sanitize_key((string) ($export_config['upload_dir_slug'] ?? 'wicket-exports'));
        $exports_dir = trailingslashit((string) ($upload_dir['basedir'] ?? '')) . $dir_slug;

        if (!wp_mkdir_p($exports_dir)) {
            return new WP_Error('export_dir_failed', __('Unable to create export directory.', 'wicket-acc'));
        }

        // Deny direct HTTP access to this directory. Apache-only; nginx sites
        // should add an equivalent location block. WWID-1907 noted this gap.
        $htaccess = $exports_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\nDeny from all\n"); // phpcs:ignore
        }

        $file_path = $exports_dir . '/' . sanitize_file_name($job_id) . '.csv';
        $fh = fopen($file_path, 'w');
        if ($fh === false) {
            return new WP_Error('export_file_failed', __('Unable to create export file.', 'wicket-acc'));
        }

        // Header row from the same shared column source the data rows use.
        // CsvExporter applies AD14 formula-injection escape. Headers are literal
        // English by design (locale-invariant round-trip) — see headerRow().
        $this->csvExporter->writeRow($this->headerRow($this->resolveExportColumns()), $fh);
        fclose($fh);

        return $file_path;
    }

    /**
     * @param string               $job_id
     * @param string               $token
     * @param array<string, mixed> $export_config
     * @return void
     */
    private function saveToken(string $job_id, string $token, array $export_config): void
    {
        $expiration_days = max(1, (int) ($export_config['token_expiration_days'] ?? 14));
        update_option(self::TOKEN_OPTION_PREFIX . $token, [
            'job_id'         => $job_id,
            'download_count' => 0,
            'created_at'     => time(),
            'expires_at'     => time() + ($expiration_days * DAY_IN_SECONDS),
        ], false);
    }

    /**
     * @param string               $job_id
     * @param array<string, mixed> $export_config
     * @return void
     */
    private function scheduleCleanup(string $job_id, array $export_config): void
    {
        $expiration_days = max(1, (int) ($export_config['token_expiration_days'] ?? 14));
        wp_schedule_single_event(time() + ($expiration_days * DAY_IN_SECONDS), self::CLEANUP_HOOK, [$job_id]);
    }

    /**
     * @param array<string, mixed> $job
     * @param string               $token
     * @param array<string, mixed> $export_config
     * @return void
     */
    private function sendCompletionEmail(array $job, string $token, array $export_config): void
    {
        $recipient = (string) ($job['recipient_email'] ?? '');
        if ($recipient === '' || !is_email($recipient)) {
            return;
        }

        $config = $this->configService->getFullConfig();
        $from_email = sanitize_email((string) ($config['integrations']['notifications']['confirmation_email_from'] ?? 'no-reply@wicketcloud.com'));
        $from_name = get_bloginfo('name');
        $expiration_days = max(1, (int) ($export_config['token_expiration_days'] ?? 14));
        $max_downloads = max(1, (int) ($export_config['max_downloads'] ?? 10));
        $download_url = add_query_arg([self::QUERY_VAR => $token], home_url('/'));

        $subject = __('Your member export is ready', 'wicket-acc');
        $message = '<p>' . esc_html(sprintf(
            /* translators: 1: number of days, 2: number of downloads */
            __('Your member export is ready. The download link is valid for %1$d day(s) and can be used up to %2$d time(s).', 'wicket-acc'),
            $expiration_days,
            $max_downloads
        )) . '</p>'
        . '<p><a href="' . esc_url($download_url) . '">' . esc_html__('Download Export', 'wicket-acc') . '</a></p>'
        . '<p>' . esc_html__('If the button above does not work, copy and paste this URL into your browser:', 'wicket-acc') . '<br>' . esc_url($download_url) . '</p>';

        wp_mail($recipient, $subject, $message, [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ]);
    }

    /**
     * @param array<string, mixed> $job
     * @return void
     */
    private function sendFailureEmail(array $job): void
    {
        $recipient = (string) ($job['recipient_email'] ?? '');
        if ($recipient === '' || !is_email($recipient)) {
            return;
        }

        $config = $this->configService->getFullConfig();
        $from_email = sanitize_email((string) ($config['integrations']['notifications']['confirmation_email_from'] ?? 'no-reply@wicketcloud.com'));
        $from_name = get_bloginfo('name');

        wp_mail(
            $recipient,
            __('Your member export could not be completed', 'wicket-acc'),
            '<p>' . esc_html__('Your member export could not be completed. Please try again or contact support.', 'wicket-acc') . '</p>',
            [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $from_name . ' <' . $from_email . '>',
            ]
        );
    }

    /**
     * @param array<string, mixed> $job
     * @param string               $message
     * @return void
     */
    private function failJob(array $job, string $message): void
    {
        $job['status'] = 'failed';
        $job['updated_at'] = $this->nowIso8601();
        $this->saveJob($job);
        $this->sendFailureEmail($job);
        $this->logActivity('error', 'Export job failed: ' . $message, ['job_id' => (string) ($job['id'] ?? '')]);
    }

    /**
     * @param string $org_id
     * @param string $recipient_email
     * @return array<string, mixed>|null
     */
    private function findActiveJobForOrg(string $org_id, string $recipient_email): ?array
    {
        $job_ids = get_option(self::OPTION_KEY, []);
        if (!is_array($job_ids)) {
            return null;
        }

        foreach ($job_ids as $job_id) {
            $job = $this->getJob(sanitize_key((string) $job_id));
            if (!is_array($job)) {
                continue;
            }
            if (
                (string) ($job['org_id'] ?? '') === $org_id
                && (string) ($job['recipient_email'] ?? '') === $recipient_email
                && in_array((string) ($job['status'] ?? ''), ['queued', 'processing'], true)
            ) {
                return $job;
            }
        }

        return null;
    }

    /**
     * @param string $job_id
     * @param int    $delay_seconds
     * @return bool
     */
    private function scheduleNextBatch(string $job_id, int $delay_seconds = 2): bool
    {
        if (!function_exists('wp_schedule_single_event')) {
            return false;
        }

        $timestamp = time() + max(1, $delay_seconds);

        return wp_schedule_single_event($timestamp, self::CRON_HOOK, [$job_id]) !== false;
    }

    /**
     * @param string $job_id
     * @return array<string, mixed>|null
     */
    private function getJob(string $job_id): ?array
    {
        $job = get_option(self::JOB_OPTION_PREFIX . $job_id, null);

        return is_array($job) ? $job : null;
    }

    /**
     * @param array<string, mixed> $job
     * @return void
     */
    private function saveJob(array $job): void
    {
        $job_id = (string) ($job['id'] ?? '');
        if ($job_id === '') {
            return;
        }

        update_option(self::JOB_OPTION_PREFIX . $job_id, $job, false);

        $job_ids = get_option(self::OPTION_KEY, []);
        if (!is_array($job_ids)) {
            $job_ids = [];
        }
        $job_ids = array_values(array_filter(array_map('sanitize_key', $job_ids)));
        if (!in_array($job_id, $job_ids, true)) {
            array_unshift($job_ids, $job_id);
        }
        $job_ids = $this->pruneJobs($job_ids);
        update_option(self::OPTION_KEY, $job_ids, false);
    }

    /**
     * Keep at most 20 export jobs; prune oldest completed/expired first.
     *
     * @param list<string> $job_ids
     * @return list<string>
     */
    private function pruneJobs(array $job_ids): array
    {
        if (count($job_ids) <= 20) {
            return $job_ids;
        }

        $jobs_with_time = [];
        foreach ($job_ids as $job_id) {
            $job = $this->getJob((string) $job_id);
            if (!is_array($job)) {
                continue;
            }
            $jobs_with_time[] = [
                'id'         => $job_id,
                'updated_at' => (string) ($job['updated_at'] ?? ''),
            ];
        }

        usort($jobs_with_time, static function (array $a, array $b): int {
            $a_time = strtotime((string) ($a['updated_at'] ?? '')) ?: 0;
            $b_time = strtotime((string) ($b['updated_at'] ?? '')) ?: 0;

            return $b_time <=> $a_time;
        });

        $pruned = array_map(static fn (array $e): string => (string) ($e['id'] ?? ''), array_slice($jobs_with_time, 0, 20));
        $to_remove = array_diff($job_ids, $pruned);
        foreach ($to_remove as $remove_id) {
            delete_option(self::JOB_OPTION_PREFIX . $remove_id);
        }

        return array_values(array_filter($pruned));
    }

    /**
     * @return string
     */
    private function nowIso8601(): string
    {
        return gmdate('c');
    }

    /**
     * @param string               $level
     * @param string               $message
     * @param array<string, mixed> $context
     * @return void
     */
    private function logActivity(string $level, string $message, array $context = []): void
    {
        $normalized_context = array_merge(['source' => 'wicket-orgman-member-export'], $context);
        $logger = \Wicket()->log();
        if (method_exists($logger, $level)) {
            $logger->{$level}($message, $normalized_context);

            return;
        }
        if (method_exists($logger, 'info')) {
            $logger->info($message, $normalized_context);

            return;
        }
        \Wicket()->log()->error($message . ' ' . wp_json_encode($normalized_context), ['source' => 'wicket-orgman']);
    }
}
