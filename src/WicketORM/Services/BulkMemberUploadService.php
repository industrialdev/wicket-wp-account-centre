<?php

declare(strict_types=1);

namespace WicketORM\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles queued CSV bulk member uploads using WP-Cron batches.
 */
class BulkMemberUploadService
{
    public const OPTION_KEY = 'wicket_orgman_bulk_upload_job_ids';
    public const JOB_OPTION_PREFIX = 'wicket_orgman_bulk_upload_job_';
    public const CRON_HOOK = 'wicket_orgman_process_bulk_upload_job';

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var MemberService
     */
    private $member_service;

    /**
     * @var ConnectionService
     */
    private $connection_service;

    /**
     * Constructor.
     *
     * @param ConfigService|null $configService
     */
    public function __construct(?ConfigService $configService = null)
    {
        $this->configService = $configService ?? new ConfigService();
        $this->member_service = new MemberService($this->configService);
        $this->connection_service = new ConnectionService();
    }

    /**
     * Queue an upload job and schedule first cron batch.
     *
     * @param string $file_path
     * @param string $file_name
     * @param string $org_uuid
     * @param string $membership_uuid
     * @param string $roster_mode
     * @param string $group_uuid
     * @return array<string, mixed>|WP_Error
     */
    public function enqueueUpload(
        string $file_path,
        string $file_name,
        string $org_uuid,
        string $membership_uuid,
        string $roster_mode,
        string $group_uuid = ''
    ) {
        $config = $this->configService->getFullConfig();
        $bulk_upload_config = is_array($config['member_management']['bulk_upload'] ?? null)
            ? $config['member_management']['bulk_upload']
            : [];
        $bulk_column_definitions = $this->getBulkColumnDefinitions($bulk_upload_config);

        $file = fopen($file_path, 'r');
        if ($file === false) {
            $this->logActivity('error', 'Bulk upload enqueue failed: file unreadable', [
                'file_name' => $file_name,
                'org_uuid' => $org_uuid,
                'membership_uuid' => $membership_uuid,
                'roster_mode' => $roster_mode,
            ]);

            return new WP_Error('bulk_file_unreadable', __('Unable to read the uploaded CSV file.', 'wicket-acc'));
        }

        $header_row = fgetcsv($file, 0, ',', '"', '\\');
        if (!is_array($header_row) || empty($header_row)) {
            fclose($file);
            $this->logActivity('error', 'Bulk upload enqueue failed: invalid CSV header', [
                'file_name' => $file_name,
                'org_uuid' => $org_uuid,
                'membership_uuid' => $membership_uuid,
                'roster_mode' => $roster_mode,
            ]);

            return new WP_Error('bulk_header_invalid', __('CSV header row is missing or invalid.', 'wicket-acc'));
        }

        $column_index_map = $this->resolveHeaderIndex($header_row, $bulk_column_definitions);
        foreach ($bulk_column_definitions as $column_key => $column_definition) {
            if (
                !empty($column_definition['enabled'])
                && !empty($column_definition['required'])
                && (($column_index_map[$column_key] ?? -1) < 0)
            ) {
                fclose($file);
                $this->logActivity('error', 'Bulk upload enqueue failed: missing required CSV column', [
                    'file_name' => $file_name,
                    'org_uuid' => $org_uuid,
                    'membership_uuid' => $membership_uuid,
                    'roster_mode' => $roster_mode,
                    'missing_column' => (string) ($column_definition['header'] ?? $column_key),
                ]);

                return new WP_Error(
                    'bulk_required_column_missing',
                    sprintf(
                        __('CSV is missing required column: %s.', 'wicket-acc'),
                        esc_html((string) ($column_definition['header'] ?? $column_key))
                    )
                );
            }
        }

        $rows = [];
        $row_num = 1;
        while (($row = fgetcsv($file, 0, ',', '"', '\\')) !== false) {
            $row_num++;
            if (!is_array($row)) {
                continue;
            }

            $first_name = sanitize_text_field((string) ($row[$column_index_map['first_name']] ?? ''));
            $last_name = sanitize_text_field((string) ($row[$column_index_map['last_name']] ?? ''));
            $email = sanitize_email((string) ($row[$column_index_map['email']] ?? ''));
            $roles_raw = (!empty($bulk_column_definitions['roles']['enabled']) && ($column_index_map['roles'] ?? -1) >= 0)
                ? (string) ($row[$column_index_map['roles']] ?? '')
                : '';
            $relationship_raw = (!empty($bulk_column_definitions['relationship_type']['enabled']) && ($column_index_map['relationship_type'] ?? -1) >= 0)
                ? sanitize_text_field((string) ($row[$column_index_map['relationship_type']] ?? ''))
                : '';

            if ($first_name === '' && $last_name === '' && $email === '') {
                continue;
            }

            $rows[] = [
                'row_num' => $row_num,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'roles_raw' => $roles_raw,
                'relationship_raw' => $relationship_raw,
            ];
        }

        fclose($file);

        if (empty($rows)) {
            $this->logActivity('warning', 'Bulk upload enqueue rejected: no rows', [
                'file_name' => $file_name,
                'org_uuid' => $org_uuid,
                'membership_uuid' => $membership_uuid,
                'roster_mode' => $roster_mode,
            ]);

            return new WP_Error('bulk_no_rows', __('CSV has no member rows to process.', 'wicket-acc'));
        }

        $batch_size = $this->getBatchSize($bulk_upload_config);
        $file_sha256 = hash_file('sha256', $file_path);
        $file_sha256 = is_string($file_sha256) ? strtolower(trim($file_sha256)) : '';
        if ($file_sha256 !== '') {
            $existing_job = $this->findJobByHash($file_sha256);
            if (is_array($existing_job)) {
                $existing_job_id = (string) ($existing_job['id'] ?? '');
                $existing_status = (string) ($existing_job['status'] ?? 'queued');
                if (in_array($existing_status, ['queued', 'processing'], true)) {
                    $this->logActivity('warning', 'Bulk upload enqueue blocked: duplicate file already in progress', [
                        'existing_job_id' => $existing_job_id,
                        'existing_status' => $existing_status,
                        'file_name' => $file_name,
                        'file_sha256' => $file_sha256,
                        'org_uuid' => $org_uuid,
                        'membership_uuid' => $membership_uuid,
                    ]);

                    return new WP_Error(
                        'bulk_duplicate_active_job',
                        sprintf(
                            __('This exact same CSV is already in progress (matching file hash). Existing job: %1$s (status: %2$s).', 'wicket-acc'),
                            $existing_job_id,
                            $existing_status
                        )
                    );
                }

                $this->logActivity('warning', 'Bulk upload enqueue blocked: duplicate file already processed', [
                    'existing_job_id' => $existing_job_id,
                    'existing_status' => $existing_status,
                    'file_name' => $file_name,
                    'file_sha256' => $file_sha256,
                    'org_uuid' => $org_uuid,
                    'membership_uuid' => $membership_uuid,
                ]);

                return new WP_Error(
                    'bulk_duplicate_finished_job',
                    sprintf(
                        __('This exact same CSV was already processed before (matching file hash). Existing job: %1$s (status: %2$s). Please upload a different CSV with different users.', 'wicket-acc'),
                        $existing_job_id,
                        $existing_status
                    )
                );
            }
        }

        $job_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('orgman_bulk_', true);
        $job_id = sanitize_key(str_replace('-', '', (string) $job_id));

        $job = [
            'id' => $job_id,
            'status' => 'queued',
            'created_at' => $this->nowIso8601(),
            'updated_at' => $this->nowIso8601(),
            'file_name' => sanitize_file_name($file_name),
            'file_sha256' => $file_sha256,
            'org_uuid' => $org_uuid,
            'membership_uuid' => $membership_uuid,
            'roster_mode' => $roster_mode,
            'group_uuid' => $group_uuid,
            'total_records' => count($rows),
            'processed' => 0,
            'added' => 0,
            'skipped' => 0,
            'failed' => 0,
            'next_offset' => 0,
            'batch_size' => $batch_size,
            'error_snippets' => [],
            'seen_emails' => [],
            'rows' => $rows,
        ];

        $this->saveJob($job);
        $this->logActivity('info', 'Bulk upload job queued', [
            'job_id' => $job_id,
            'file_name' => $job['file_name'],
            'file_sha256' => $file_sha256,
            'org_uuid' => $org_uuid,
            'membership_uuid' => $membership_uuid,
            'group_uuid' => $group_uuid,
            'roster_mode' => $roster_mode,
            'total_records' => $job['total_records'],
            'batch_size' => $batch_size,
        ]);

        if (!$this->scheduleNextBatch($job_id, 2)) {
            $job['status'] = 'failed';
            $job['updated_at'] = $this->nowIso8601();
            $this->appendErrorSnippet($job, __('Unable to schedule background processing.', 'wicket-acc'));
            $this->saveJob($job);
            $this->logActivity('error', 'Bulk upload job queue failed: unable to schedule first batch', [
                'job_id' => $job_id,
                'file_name' => $job['file_name'],
            ]);

            return new WP_Error('bulk_schedule_failed', __('Unable to schedule background processing.', 'wicket-acc'));
        }

        return [
            'job_id' => $job_id,
            'status' => $job['status'],
            'total_records' => $job['total_records'],
            'batch_size' => $batch_size,
        ];
    }

    /**
     * Process one scheduled batch for a queued upload job.
     *
     * @param string $job_id
     * @return void
     */
    public function processScheduledJob(string $job_id): void
    {
        $job = $this->getJob($job_id);
        if (empty($job) || !is_array($job)) {
            return;
        }

        $status = (string) ($job['status'] ?? '');
        if ($status === 'completed' || $status === 'failed') {
            return;
        }

        $job['status'] = 'processing';
        $job['updated_at'] = $this->nowIso8601();
        $this->logActivity('info', 'Bulk upload batch started', [
            'job_id' => $job_id,
            'status' => $status,
            'processed' => (int) ($job['processed'] ?? 0),
            'total_records' => (int) ($job['total_records'] ?? 0),
        ]);

        $rows = is_array($job['rows'] ?? null) ? $job['rows'] : [];
        $total_records = (int) ($job['total_records'] ?? 0);
        $next_offset = max(0, (int) ($job['next_offset'] ?? 0));
        $batch_size = max(1, (int) ($job['batch_size'] ?? 25));

        if (empty($rows) || $total_records <= 0 || $next_offset >= $total_records) {
            $job['status'] = 'completed';
            $job['completed_at'] = $this->nowIso8601();
            $job['updated_at'] = $this->nowIso8601();
            $job['rows'] = [];
            $job['seen_emails'] = [];
            $this->saveJob($job);
            $this->logActivity('info', 'Bulk upload job completed with no pending rows', [
                'job_id' => $job_id,
                'processed' => (int) ($job['processed'] ?? 0),
                'added' => (int) ($job['added'] ?? 0),
                'skipped' => (int) ($job['skipped'] ?? 0),
                'failed' => (int) ($job['failed'] ?? 0),
            ]);

            return;
        }

        $config = $this->configService->getFullConfig();
        $bulk_upload_config = is_array($config['member_management']['bulk_upload'] ?? null)
            ? $config['member_management']['bulk_upload']
            : [];
        $bulk_column_definitions = $this->getBulkColumnDefinitions($bulk_upload_config);
        $relationship_bulk_config = is_array($bulk_upload_config['relationship_type'] ?? null)
            ? $bulk_upload_config['relationship_type']
            : [];

        $permissions_field_config = $config['member_management']['forms']['add_member']['fields']['permissions'] ?? [];
        $allowed_roles = is_array($permissions_field_config['allowlist'] ?? null)
            ? $permissions_field_config['allowlist']
            : [];
        $excluded_roles = is_array($permissions_field_config['denylist'] ?? null)
            ? $permissions_field_config['denylist']
            : [];

        $relationship_column_enabled = (bool) ($bulk_column_definitions['relationship_type']['enabled'] ?? false);
        $relationship_required = $relationship_column_enabled
            && (bool) ($relationship_bulk_config['required'] ?? ($bulk_column_definitions['relationship_type']['required'] ?? false));

        $relationship_allowed_types_raw = is_array($relationship_bulk_config['allowed_types'] ?? null)
            ? $relationship_bulk_config['allowed_types']
            : [];
        $relationship_allowed_types = array_values(array_filter(array_map(static function ($type): string {
            return sanitize_key((string) $type);
        }, $relationship_allowed_types_raw)));

        $relationship_aliases = is_array($relationship_bulk_config['aliases'] ?? null)
            ? $relationship_bulk_config['aliases']
            : [];
        $relationship_types_map = is_array($config['relationships']['labels']['custom'] ?? null)
            ? $config['relationships']['labels']['custom']
            : [];
        $relationship_lookup = $this->buildRelationshipLookup($relationship_types_map, $relationship_aliases);

        $start = $next_offset;
        $end = min($start + $batch_size, $total_records);
        $seen_emails = is_array($job['seen_emails'] ?? null) ? $job['seen_emails'] : [];
        $group_service_instance = null;
        if ((string) ($job['roster_mode'] ?? '') === 'groups') {
            $group_service_instance = new GroupService();
        }

        for ($index = $start; $index < $end; $index++) {
            $row_data = is_array($rows[$index] ?? null) ? $rows[$index] : [];

            $row_num = (int) ($row_data['row_num'] ?? ($index + 2));
            $first_name = sanitize_text_field((string) ($row_data['first_name'] ?? ''));
            $last_name = sanitize_text_field((string) ($row_data['last_name'] ?? ''));
            $email = sanitize_email((string) ($row_data['email'] ?? ''));
            $roles_raw = (string) ($row_data['roles_raw'] ?? '');
            $relationship_raw = sanitize_text_field((string) ($row_data['relationship_raw'] ?? ''));

            $job['processed'] = (int) ($job['processed'] ?? 0) + 1;

            if ($email === '' || !is_email($email) || $first_name === '' || $last_name === '') {
                $job['failed'] = (int) ($job['failed'] ?? 0) + 1;
                $this->appendErrorSnippet(
                    $job,
                    sprintf(__('Row %d skipped: missing required name/email fields.', 'wicket-acc'), $row_num)
                );
                $this->logActivity('warning', 'Bulk upload row failed validation', [
                    'job_id' => $job_id,
                    'row_num' => $row_num,
                    'email' => $email,
                ]);
                continue;
            }

            $email_key = strtolower($email);
            if (isset($seen_emails[$email_key])) {
                $job['skipped'] = (int) ($job['skipped'] ?? 0) + 1;
                $this->logActivity('info', 'Bulk upload row skipped duplicate email in file', [
                    'job_id' => $job_id,
                    'row_num' => $row_num,
                    'email' => $email,
                ]);
                continue;
            }

            $resolved_relationship_type = $this->resolveRelationshipType($relationship_raw, $relationship_lookup);
            if ($relationship_column_enabled && $relationship_required && trim($relationship_raw) === '') {
                $job['skipped'] = (int) ($job['skipped'] ?? 0) + 1;
                $this->appendErrorSnippet(
                    $job,
                    sprintf(__('Row %d skipped: relationship type is required for bulk upload.', 'wicket-acc'), $row_num)
                );
                $this->logActivity('warning', 'Bulk upload row skipped: missing required relationship type', [
                    'job_id' => $job_id,
                    'row_num' => $row_num,
                    'email' => $email,
                ]);
                continue;
            }

            if ($relationship_raw !== '' && !$this->isAllowedRelationshipType($resolved_relationship_type, $relationship_allowed_types)) {
                $job['failed'] = (int) ($job['failed'] ?? 0) + 1;
                $this->appendErrorSnippet(
                    $job,
                    sprintf(__('Row %d failed: relationship type is not allowed.', 'wicket-acc'), $row_num)
                );
                $this->logActivity('warning', 'Bulk upload row failed: disallowed relationship type', [
                    'job_id' => $job_id,
                    'row_num' => $row_num,
                    'email' => $email,
                    'relationship_type' => $resolved_relationship_type,
                ]);
                continue;
            }

            $membership_uuid = (string) ($job['membership_uuid'] ?? '');
            if ((string) ($job['roster_mode'] ?? '') === 'groups') {
                $group_uuid = (string) ($job['group_uuid'] ?? '');
                $org_uuid = (string) ($job['org_uuid'] ?? '');
                if (
                    $this->activeGroupMembershipExists(
                        $group_uuid,
                        $org_uuid,
                        $email,
                        $group_service_instance instanceof GroupService ? $group_service_instance : null
                    )
                ) {
                    $job['skipped'] = (int) ($job['skipped'] ?? 0) + 1;
                    $seen_emails[$email_key] = true;
                    $this->logActivity('info', 'Bulk upload row skipped: active group membership already exists', [
                        'job_id' => $job_id,
                        'row_num' => $row_num,
                        'email' => $email,
                        'group_uuid' => $group_uuid,
                        'org_uuid' => $org_uuid,
                    ]);
                    continue;
                }
            } elseif (
                $this->activeMembershipExists($membership_uuid, $email)
                || $this->activeMembershipExistsByPerson($membership_uuid, $email)
            ) {
                $job['skipped'] = (int) ($job['skipped'] ?? 0) + 1;
                $seen_emails[$email_key] = true;
                $this->logActivity('info', 'Bulk upload row skipped: active membership already exists', [
                    'job_id' => $job_id,
                    'row_num' => $row_num,
                    'email' => $email,
                    'membership_uuid' => $membership_uuid,
                ]);
                continue;
            }

            $roles = [];
            if ($roles_raw !== '') {
                $roles = preg_split('/[|]+/', $roles_raw) ?: [];
                $roles = array_map(static function ($role): string {
                    return sanitize_text_field(trim((string) $role));
                }, $roles);
                $roles = array_values(array_filter($roles, static function ($role): bool {
                    return $role !== '';
                }));
                $roles = \WicketORM\Helpers\PermissionHelper::filter_role_submission(
                    $roles,
                    $allowed_roles,
                    $excluded_roles
                );
            }

            $member_data = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
            ];
            $context = [
                'roles' => $roles,
                'membership_uuid' => $membership_uuid,
            ];

            if ($resolved_relationship_type !== '') {
                $context['relationship_type'] = $resolved_relationship_type;
            }

            if ((string) ($job['roster_mode'] ?? '') === 'groups') {
                $group_uuid = (string) ($job['group_uuid'] ?? '');
                $default_group_role = sanitize_key((string) (($config['groups']['roles']['member'] ?? 'member')));
                $roster_roles = $group_service_instance instanceof GroupService
                    ? $group_service_instance->getRosterRoles()
                    : [];
                if (empty($roster_roles)) {
                    $roster_roles = [$default_group_role];
                }

                $normalized_roles = array_values(array_filter(array_map(static function ($role): string {
                    return sanitize_key((string) $role);
                }, $roles)));

                $row_role = $default_group_role;
                foreach ($normalized_roles as $candidate_role) {
                    if (in_array($candidate_role, $roster_roles, true)) {
                        $row_role = $candidate_role;
                        break;
                    }
                }

                $context['group_uuid'] = $group_uuid;
                $context['role'] = $row_role;
            }

            $result = $this->member_service->addMember((string) ($job['org_uuid'] ?? ''), $member_data, $context);
            if (is_wp_error($result)) {
                $error_code = (string) $result->get_error_code();
                if ($error_code === 'group_member_exists') {
                    $job['skipped'] = (int) ($job['skipped'] ?? 0) + 1;
                    $seen_emails[$email_key] = true;
                    $this->logActivity('info', 'Bulk upload row skipped: member already assigned to group', [
                        'job_id' => $job_id,
                        'row_num' => $row_num,
                        'email' => $email,
                    ]);
                    continue;
                }

                $job['failed'] = (int) ($job['failed'] ?? 0) + 1;
                $this->appendErrorSnippet(
                    $job,
                    sprintf(
                        __('Row %1$d failed (%2$s): %3$s', 'wicket-acc'),
                        $row_num,
                        esc_html($email),
                        esc_html($result->get_error_message())
                    )
                );
                $this->logActivity('error', 'Bulk upload row add_member failed', [
                    'job_id' => $job_id,
                    'row_num' => $row_num,
                    'email' => $email,
                    'error' => $result->get_error_message(),
                ]);
                continue;
            }

            $job['added'] = (int) ($job['added'] ?? 0) + 1;
            $seen_emails[$email_key] = true;
            $this->logActivity('info', 'Bulk upload row added successfully', [
                'job_id' => $job_id,
                'row_num' => $row_num,
                'email' => $email,
            ]);
        }

        $job['seen_emails'] = $seen_emails;
        $job['next_offset'] = $end;
        $job['updated_at'] = $this->nowIso8601();

        if ($end >= $total_records) {
            $job['status'] = 'completed';
            $job['completed_at'] = $this->nowIso8601();
            $job['rows'] = [];
            $job['seen_emails'] = [];

            $membership_uuid = (string) ($job['membership_uuid'] ?? '');
            if ((int) ($job['added'] ?? 0) > 0 && $membership_uuid !== '') {
                $orgman_instance = \WicketORM\OrgMan::getInstance();
                $orgman_instance->clearMembersCache($membership_uuid);
            }

            $this->saveJob($job);
            $this->logActivity('info', 'Bulk upload job completed', [
                'job_id' => $job_id,
                'processed' => (int) ($job['processed'] ?? 0),
                'added' => (int) ($job['added'] ?? 0),
                'skipped' => (int) ($job['skipped'] ?? 0),
                'failed' => (int) ($job['failed'] ?? 0),
            ]);

            return;
        }

        $job['status'] = 'queued';
        $this->saveJob($job);
        $this->logActivity('info', 'Bulk upload batch finished; scheduling next batch', [
            'job_id' => $job_id,
            'processed' => (int) ($job['processed'] ?? 0),
            'next_offset' => (int) ($job['next_offset'] ?? 0),
            'total_records' => $total_records,
        ]);

        if (!$this->scheduleNextBatch((string) $job['id'], 2)) {
            $job['status'] = 'failed';
            $job['updated_at'] = $this->nowIso8601();
            $this->appendErrorSnippet($job, __('Unable to schedule next background batch.', 'wicket-acc'));
            $this->saveJob($job);
            $this->logActivity('error', 'Bulk upload job failed: unable to schedule next batch', [
                'job_id' => (string) ($job['id'] ?? ''),
            ]);
        }
    }

    /**
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
            'id' => (string) ($job['id'] ?? ''),
            'status' => (string) ($job['status'] ?? ''),
            'file_name' => (string) ($job['file_name'] ?? ''),
            'file_sha256' => (string) ($job['file_sha256'] ?? ''),
            'created_at' => (string) ($job['created_at'] ?? ''),
            'updated_at' => (string) ($job['updated_at'] ?? ''),
            'completed_at' => (string) ($job['completed_at'] ?? ''),
            'total_records' => (int) ($job['total_records'] ?? 0),
            'processed' => (int) ($job['processed'] ?? 0),
            'added' => (int) ($job['added'] ?? 0),
            'skipped' => (int) ($job['skipped'] ?? 0),
            'failed' => (int) ($job['failed'] ?? 0),
            'batch_size' => (int) ($job['batch_size'] ?? 0),
            'error_snippets' => is_array($job['error_snippets'] ?? null) ? $job['error_snippets'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $bulk_upload_config
     * @return int
     */
    private function getBatchSize(array $bulk_upload_config): int
    {
        $batch_size = (int) ($bulk_upload_config['batch_size'] ?? 25);

        return max(1, min(500, $batch_size));
    }

    /**
     * @param array<string, mixed> $job
     * @return void
     */
    private function appendErrorSnippet(array &$job, string $message): void
    {
        $snippets = is_array($job['error_snippets'] ?? null) ? $job['error_snippets'] : [];
        if (count($snippets) >= 5) {
            return;
        }

        $snippets[] = $message;
        $job['error_snippets'] = $snippets;
    }

    /**
     * @param string $job_id
     * @param int $delay_seconds
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
        if (!is_array($job)) {
            return null;
        }

        return $job;
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
     * @param array<int, string> $job_ids
     * @return array<int, string>
     */
    private function pruneJobs(array $job_ids): array
    {
        if (count($job_ids) <= 20) {
            return $job_ids;
        }

        $jobs_with_time = [];
        foreach ($job_ids as $job_id) {
            $job = get_option(self::JOB_OPTION_PREFIX . $job_id, null);
            if (!is_array($job)) {
                continue;
            }
            $jobs_with_time[] = [
                'id' => $job_id,
                'updated_at' => (string) ($job['updated_at'] ?? ''),
            ];
        }

        usort($jobs_with_time, static function ($left, $right): int {
            $left_time = strtotime((string) ($left['updated_at'] ?? '')) ?: 0;
            $right_time = strtotime((string) ($right['updated_at'] ?? '')) ?: 0;

            return $right_time <=> $left_time;
        });

        $pruned = array_map(static function ($entry): string {
            return (string) ($entry['id'] ?? '');
        }, array_slice($jobs_with_time, 0, 20));

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
     * @param string $file_sha256
     * @return array<string, mixed>|null
     */
    private function findJobByHash(string $file_sha256): ?array
    {
        if ($file_sha256 === '') {
            return null;
        }

        $job_ids = get_option(self::OPTION_KEY, []);
        if (!is_array($job_ids)) {
            return null;
        }

        foreach ($job_ids as $job_id) {
            $job_id = sanitize_key((string) $job_id);
            if ($job_id === '') {
                continue;
            }

            $job = get_option(self::JOB_OPTION_PREFIX . $job_id, null);
            if (!is_array($job)) {
                continue;
            }

            $existing_hash = strtolower(trim((string) ($job['file_sha256'] ?? '')));
            if ($existing_hash !== '' && hash_equals($existing_hash, $file_sha256)) {
                return $job;
            }
        }

        return null;
    }

    /**
     * @param string $level
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    private function logActivity(string $level, string $message, array $context = []): void
    {
        $normalized_context = array_merge(['source' => 'wicket-orgman-bulk-upload'], $context);

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

    /**
     * @param array<string, mixed> $bulk_upload_config
     * @return array<string, array<string, mixed>>
     */
    private function getBulkColumnDefinitions(array $bulk_upload_config): array
    {
        $bulk_columns_config = is_array($bulk_upload_config['columns'] ?? null)
            ? $bulk_upload_config['columns']
            : [];
        $default_bulk_columns = [
            'first_name' => [
                'enabled' => true,
                'required' => true,
                'header' => 'First Name',
                'aliases' => ['first name', 'firstname', 'first'],
            ],
            'last_name' => [
                'enabled' => true,
                'required' => true,
                'header' => 'Last Name',
                'aliases' => ['last name', 'lastname', 'last'],
            ],
            'email' => [
                'enabled' => true,
                'required' => true,
                'header' => 'Email Address',
                'aliases' => ['email address', 'email', 'e-mail'],
            ],
            'relationship_type' => [
                'enabled' => true,
                'required' => true,
                'header' => 'Relationship Type',
                'aliases' => ['relationship type', 'relationship'],
            ],
            'roles' => [
                'enabled' => true,
                'required' => false,
                'header' => 'Roles',
                'aliases' => ['roles', 'permissions', 'role'],
            ],
        ];

        $bulk_column_definitions = [];
        foreach ($default_bulk_columns as $column_key => $defaults) {
            $column_config = is_array($bulk_columns_config[$column_key] ?? null)
                ? $bulk_columns_config[$column_key]
                : [];
            $bulk_column_definitions[$column_key] = [
                'enabled' => (bool) ($column_config['enabled'] ?? $defaults['enabled']),
                'required' => (bool) ($column_config['required'] ?? $defaults['required']),
                'header' => sanitize_text_field((string) ($column_config['header'] ?? $defaults['header'])),
                'aliases' => is_array($column_config['aliases'] ?? null) ? $column_config['aliases'] : $defaults['aliases'],
            ];
        }

        foreach (['first_name', 'last_name', 'email'] as $required_identity_column) {
            $bulk_column_definitions[$required_identity_column]['enabled'] = true;
            $bulk_column_definitions[$required_identity_column]['required'] = true;
        }

        if (isset($bulk_column_definitions['relationship_type'])) {
            $relationship_bulk_config = is_array($bulk_upload_config['relationship_type'] ?? null)
                ? $bulk_upload_config['relationship_type']
                : [];
            $bulk_column_definitions['relationship_type']['required'] = (bool) (
                $relationship_bulk_config['required'] ?? $bulk_column_definitions['relationship_type']['required']
            );
        }

        return $bulk_column_definitions;
    }

    /**
     * @param array<int, string> $headers
     * @param array<string, array<string, mixed>> $bulk_column_definitions
     * @return array<string, int>
     */
    private function resolveHeaderIndex(array $headers, array $bulk_column_definitions): array
    {
        return wicket_csv_resolve_headers($headers, $bulk_column_definitions);
    }

    /**
     * @param array<string, mixed> $relationship_types_map
     * @param array<string, mixed> $relationship_aliases
     * @return array<string, string>
     */
    private function buildRelationshipLookup(array $relationship_types_map, array $relationship_aliases): array
    {
        $lookup = [];
        foreach ($relationship_types_map as $slug => $label) {
            $clean_slug = sanitize_key((string) $slug);
            if ($clean_slug === '') {
                continue;
            }

            $lookup[$this->normalizeRelationshipValue($clean_slug)] = $clean_slug;
            $lookup[$this->normalizeRelationshipValue((string) $label)] = $clean_slug;
        }

        foreach ($relationship_aliases as $alias => $mapped_slug) {
            $alias_key = $this->normalizeRelationshipValue((string) $alias);
            $clean_slug = sanitize_key((string) $mapped_slug);
            if ($alias_key === '' || $clean_slug === '') {
                continue;
            }

            $lookup[$alias_key] = $clean_slug;
        }

        return $lookup;
    }

    /**
     * @param string $value
     * @return string
     */
    private function normalizeRelationshipValue(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return (string) $value;
    }

    /**
     * @param string $raw_value
     * @param array<string, string> $relationship_lookup
     * @return string
     */
    private function resolveRelationshipType(string $raw_value, array $relationship_lookup): string
    {
        $normalized = $this->normalizeRelationshipValue($raw_value);
        if ($normalized === '') {
            return '';
        }

        return $relationship_lookup[$normalized] ?? sanitize_key($normalized);
    }

    /**
     * @param string $relationship_type
     * @param array<int, string> $relationship_allowed_types
     * @return bool
     */
    private function isAllowedRelationshipType(string $relationship_type, array $relationship_allowed_types): bool
    {
        if ($relationship_type === '') {
            return false;
        }

        if (empty($relationship_allowed_types)) {
            return true;
        }

        return in_array($relationship_type, $relationship_allowed_types, true);
    }

    /**
     * @param string $membership_uuid
     * @param string $email
     * @return bool
     */
    private function activeMembershipExists(string $membership_uuid, string $email): bool
    {
        if ($membership_uuid === '' || $email === '') {
            return false;
        }

        return wicket_person_in_membership($membership_uuid, $email);
    }

    /**
     * @param string $membership_uuid
     * @param string $email
     * @return bool
     */
    private function activeMembershipExistsByPerson(string $membership_uuid, string $email): bool
    {
        if ($membership_uuid === '' || $email === '') {
            return false;
        }

        $person_uuid = $this->resolvePersonUuidByEmail($email);
        if ($person_uuid === '') {
            return false;
        }

        return wicket_person_has_membership($person_uuid, $membership_uuid);
    }

    /**
     * @param string $group_uuid
     * @param string $org_uuid
     * @param string $email
     * @param GroupService|null $group_service
     * @return bool
     */
    private function activeGroupMembershipExists(
        string $group_uuid,
        string $org_uuid,
        string $email,
        ?GroupService $group_service = null
    ): bool {
        if ($group_uuid === '' || $email === '') {
            return false;
        }

        $person_uuid = $this->resolvePersonUuidByEmail($email);
        if ($person_uuid === '') {
            return false;
        }

        $group_service ??= new GroupService();
        $page = 1;
        $max_pages = 10;

        while ($page <= $max_pages) {
            $memberships = $group_service->getPersonGroupMemberships($person_uuid, [
                'page' => $page,
                'size' => 100,
                'active' => true,
            ]);

            if (!is_array($memberships) || empty($memberships['data']) || !is_array($memberships['data'])) {
                return false;
            }

            foreach ($memberships['data'] as $membership) {
                $membership_group_uuid = (string) ($membership['relationships']['group']['data']['id'] ?? '');
                if ($membership_group_uuid !== $group_uuid) {
                    continue;
                }

                if ($org_uuid !== '') {
                    $membership_org_uuid = (string) ($membership['relationships']['organization']['data']['id'] ?? '');
                    if ($membership_org_uuid !== '' && $membership_org_uuid !== $org_uuid) {
                        continue;
                    }
                }

                return true;
            }

            $total_pages = (int) ($memberships['meta']['page']['total_pages'] ?? $page);
            if ($page >= max(1, $total_pages)) {
                break;
            }

            $page++;
        }

        return false;
    }

    /**
     * @param string $email
     * @return string
     */
    private function resolvePersonUuidByEmail(string $email): string
    {
        if ($email === '') {
            return '';
        }

        try {
            $person = wicket_get_person_by_email($email);
            if (!$person) {
                return '';
            }

            if (is_array($person)) {
                return (string) ($person['id'] ?? ($person['data']['id'] ?? ''));
            }

            if (is_object($person)) {
                return (string) ($person->id ?? '');
            }
        } catch (\Throwable $e) {
            \Wicket()->log()->error('Bulk upload person lookup failed: ' . $e->getMessage(), [
                'source' => 'wicket-orgman',
                'email' => $email,
            ]);
        }

        return '';
    }
}
