<?php

declare(strict_types=1);

namespace WicketORM\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fetches MDP engagement/donation data for a person and org context.
 *
 * All field keys, section labels, badge patterns, and active-membership checks
 * are driven by config so any client can map their own MDP data shapes.
 */
class EngagementService
{
    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @param ConfigService|null $configService
     */
    public function __construct(?ConfigService $configService = null)
    {
        $this->configService = $configService ?? new ConfigService();
    }

    /**
     * Fetch and structure engagement data for a person in an org context.
     *
     * @param string $person_uuid
     * @param string $org_id      Used for active-membership check.
     * @return array<string, mixed>|WP_Error
     */
    public function getPersonEngagement(string $person_uuid, string $org_id)
    {
        if ($person_uuid === '') {
            return new WP_Error('engagement_missing_person', __('Person UUID is required.', 'wicket-acc'));
        }

        $client = function_exists('wicket_api_client') ? wicket_api_client() : null;

        if ($client === null) {
            return new WP_Error('engagement_missing_api', __('Wicket API client is unavailable.', 'wicket-acc'));
        }

        $config = $this->configService->getFullConfig();
        $engagement_config = is_array($config['engagement'] ?? null) ? $config['engagement'] : [];
        $sections_config = is_array($engagement_config['sections'] ?? null) ? $engagement_config['sections'] : [];
        $data_fields_key = (string) ($engagement_config['person_data_fields_key'] ?? 'data_fields');

        try {
            $response = $client->get('/people/' . rawurlencode($person_uuid));
        } catch (\Throwable $e) {
            return new WP_Error('engagement_api_error', $e->getMessage());
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $attributes = is_array($response['data']['attributes'] ?? null) ? $response['data']['attributes'] : [];
        $data_fields = is_array($attributes[$data_fields_key] ?? null) ? $attributes[$data_fields_key] : [];

        $is_active_member = $this->isActiveMember($person_uuid, $org_id, $engagement_config);
        $tags = $this->fetchPersonTags($person_uuid);

        $sections = [];
        $badges = [];

        foreach ($sections_config as $section_slug => $section) {
            if (empty($section['enabled'])) {
                continue;
            }

            $requires_membership = (bool) ($section['requires_active_membership'] ?? false);
            if ($requires_membership && !$is_active_member) {
                continue;
            }

            $fields = is_array($section['fields'] ?? null) ? $section['fields'] : [];
            $section_values = [];
            foreach ($fields as $field_key => $field_def) {
                $mdp_key = (string) ($field_def['mdp_key'] ?? $field_key);
                $format = (string) ($field_def['format'] ?? 'string');
                $raw = $data_fields[$mdp_key] ?? null;

                $section_values[$field_key] = [
                    'label' => (string) ($field_def['label'] ?? $field_key),
                    'value' => $this->formatValue($raw, $format),
                    'raw'   => $raw,
                ];
            }

            $sections[$section_slug] = [
                'label'  => (string) ($section['label'] ?? $section_slug),
                'fields' => $section_values,
            ];

            // Parse badge tags for this section
            $badge_pattern = (string) ($section['badge_pattern'] ?? '');
            $badge_label_template = (string) ($section['badge_label_template'] ?? '');
            $section_badges = [];

            if ($badge_pattern !== '') {
                foreach ($tags as $tag) {
                    if (preg_match($badge_pattern, $tag, $matches)) {
                        $year = $matches[1] ?? '';
                        $section_badges[] = str_replace('{year}', $year, $badge_label_template);
                    }
                }
            }

            $badges[$section_slug] = $section_badges;
        }

        return [
            'sections'         => $sections,
            'badges'           => $badges,
            'is_active_member' => $is_active_member,
        ];
    }

    /**
     * Fetch and structure engagement data for an organization.
     *
     * @param string $org_id
     * @return array<string, mixed>|WP_Error
     */
    public function getOrgEngagement(string $org_id)
    {
        if ($org_id === '') {
            return new WP_Error('engagement_missing_org', __('Organization ID is required.', 'wicket-acc'));
        }

        $client = function_exists('wicket_api_client') ? wicket_api_client() : null;

        if ($client === null) {
            return new WP_Error('engagement_missing_api', __('Wicket API client is unavailable.', 'wicket-acc'));
        }

        $config = $this->configService->getFullConfig();
        $engagement_config = is_array($config['engagement'] ?? null) ? $config['engagement'] : [];
        $sections_config = is_array($engagement_config['sections'] ?? null) ? $engagement_config['sections'] : [];
        $data_fields_key = (string) ($engagement_config['org_data_fields_key'] ?? 'data_fields');

        try {
            $response = $client->get('/organizations/' . rawurlencode($org_id));
        } catch (\Throwable $e) {
            return new WP_Error('engagement_api_error', $e->getMessage());
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $attributes = is_array($response['data']['attributes'] ?? null) ? $response['data']['attributes'] : [];
        $data_fields = is_array($attributes[$data_fields_key] ?? null) ? $attributes[$data_fields_key] : [];

        $sections = [];
        foreach ($sections_config as $section_slug => $section) {
            if (empty($section['enabled'])) {
                continue;
            }

            $fields = is_array($section['fields'] ?? null) ? $section['fields'] : [];
            $section_values = [];
            foreach ($fields as $field_key => $field_def) {
                $mdp_key = (string) ($field_def['mdp_key'] ?? $field_key);
                $format = (string) ($field_def['format'] ?? 'string');
                $raw = $data_fields[$mdp_key] ?? null;

                $section_values[$field_key] = [
                    'label' => (string) ($field_def['label'] ?? $field_key),
                    'value' => $this->formatValue($raw, $format),
                    'raw'   => $raw,
                ];
            }

            $sections[$section_slug] = [
                'label'  => (string) ($section['label'] ?? $section_slug),
                'fields' => $section_values,
            ];
        }

        return ['sections' => $sections];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Determine if a person has an active membership, using configured org UUIDs.
     *
     * @param string               $person_uuid
     * @param string               $org_id
     * @param array<string, mixed> $engagement_config
     * @return bool
     */
    private function isActiveMember(string $person_uuid, string $org_id, array $engagement_config): bool
    {
        if ($person_uuid === '') {
            return false;
        }

        $member_org_uuids = is_array($engagement_config['member_org_uuids'] ?? null)
            ? $engagement_config['member_org_uuids']
            : [];

        // When org UUIDs are configured, check membership against those orgs first
        if (!empty($member_org_uuids) && function_exists('wicket_api_client')) {
            try {
                $client = wicket_api_client();
                foreach ($member_org_uuids as $check_org_id) {
                    $check_org_id = sanitize_text_field((string) $check_org_id);
                    if ($check_org_id === '') {
                        continue;
                    }
                    $response = $client->get(
                        '/people/' . rawurlencode($person_uuid) . '/organization_memberships?' . http_build_query([
                            'filter' => [
                                'organization_uuid_eq' => $check_org_id,
                                'active_at'            => 'now',
                            ],
                        ])
                    );
                    if (!is_wp_error($response) && !empty($response['data']) && is_array($response['data'])) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
                // Fall through to generic check
            }
        }

        // Generic fallback: any active membership
        if (function_exists('wicket_get_person_active_memberships')) {
            try {
                $memberships = wicket_get_person_active_memberships($person_uuid);
                if (!empty($memberships)) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Swallow — can't determine active status
            }
        }

        // Last resort: check against the current org passed in
        if ($org_id !== '' && function_exists('wicket_api_client')) {
            try {
                $client = wicket_api_client();
                $response = $client->get(
                    '/people/' . rawurlencode($person_uuid) . '/organization_memberships?' . http_build_query([
                        'filter' => [
                            'organization_uuid_eq' => $org_id,
                            'active_at'            => 'now',
                        ],
                    ])
                );
                if (!is_wp_error($response) && !empty($response['data']) && is_array($response['data'])) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Swallow
            }
        }

        return false;
    }

    /**
     * Fetch all tag subjects for a person.
     *
     * @param string $person_uuid
     * @return list<string>
     */
    private function fetchPersonTags(string $person_uuid): array
    {
        if ($person_uuid === '' || !function_exists('wicket_api_client')) {
            return [];
        }

        try {
            $client = wicket_api_client();
            $response = $client->get('/people/' . rawurlencode($person_uuid) . '/tags');
            if (is_wp_error($response) || !is_array($response['data'] ?? null)) {
                return [];
            }

            $tags = [];
            foreach ($response['data'] as $tag) {
                $subject = (string) ($tag['attributes']['subject'] ?? ($tag['attributes']['name'] ?? ''));
                if ($subject !== '') {
                    $tags[] = $subject;
                }
            }

            return $tags;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Format a raw MDP value for display.
     *
     * @param mixed  $value
     * @param string $format currency|date|yesno|string
     * @return string
     */
    private function formatValue(mixed $value, string $format): string
    {
        if ($value === null || $value === '') {
            return __('N/A', 'wicket-acc');
        }

        switch ($format) {
            case 'currency':
                $amount = (float) $value;

                return '$' . number_format($amount, 2);

            case 'date':
                $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
                if (!$timestamp) {
                    return __('N/A', 'wicket-acc');
                }

                return date_i18n(get_option('date_format', 'Y-m-d'), $timestamp);

            case 'yesno':
                $normalized = strtolower(trim((string) $value));

                return in_array($normalized, ['yes', '1', 'true'], true)
                    ? __('Yes', 'wicket-acc')
                    : __('No', 'wicket-acc');

            default:
                return sanitize_text_field((string) $value);
        }
    }
}
