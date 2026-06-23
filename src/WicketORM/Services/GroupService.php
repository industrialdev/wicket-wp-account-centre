<?php

/**
 * Group Service for Org Management.
 */

namespace WicketORM\Services;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class GroupService
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var \WC_Logger|null
     */
    private $logger = null;

    public function __construct()
    {
        $this->config = ConfigService::getConfig();
    }

    /**
     * Get groups config with defaults applied.
     *
     * @return array
     */
    private function getGroupsConfig(): array
    {
        $groups = $this->config['groups'] ?? [];

        return is_array($groups) ? $groups : [];
    }

    /**
     * Current point-in-time timestamp in UTC.
     *
     * @return string
     */
    private function currentTimestamp(): string
    {
        if (function_exists('wicket_time_get_current_iso8601_utc')) {
            return wicket_time_get_current_iso8601_utc();
        }

        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Current UTC day-start timestamp.
     *
     * @return string
     */
    private function currentDayStartTimestamp(): string
    {
        if (function_exists('wicket_time_get_mdp_day_start_iso8601_utc')) {
            return wicket_time_get_mdp_day_start_iso8601_utc();
        }

        return (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Resolve group-member removal anchor.
     *
     * @return string
     */
    private function getRemovalAnchor(): string
    {
        $groups_config = $this->getGroupsConfig();
        $group_anchor = $groups_config['removal']['end_date_anchor'] ?? null;
        if (is_string($group_anchor) && trim($group_anchor) !== '') {
            return sanitize_key($group_anchor);
        }

        return sanitize_key((string) ($this->config['removal']['end_date_anchor'] ?? 'action_time'));
    }

    /**
     * Resolve the timestamp used to evaluate whether a group membership is still active.
     *
     * @return \DateTimeImmutable
     */
    private function getActiveBoundary(): \DateTimeImmutable
    {
        $timestamp = $this->getRemovalAnchor() === 'day_start_utc'
            ? $this->currentDayStartTimestamp()
            : $this->currentTimestamp();

        try {
            return new \DateTimeImmutable($timestamp);
        } catch (\Throwable $e) {
            return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }
    }

    /**
     * Parse a group membership date value into a comparable timestamp.
     *
     * @param mixed $value
     * @return \DateTimeImmutable|null
     */
    private function parseGroupMembershipDate($value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable(trim($value));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Determine whether a group membership record should be considered active for this site config.
     *
     * @param array $membership
     * @return bool
     */
    private function isGroupMembershipActiveRecord(array $membership): bool
    {
        $attributes = is_array($membership['attributes'] ?? null) ? $membership['attributes'] : [];
        $boundary = $this->getActiveBoundary();
        $start_at = $this->parseGroupMembershipDate($attributes['start_date'] ?? ($attributes['starts_at'] ?? null));
        $end_at = $this->parseGroupMembershipDate($attributes['end_date'] ?? ($attributes['ends_at'] ?? null));

        if ($start_at instanceof \DateTimeImmutable && $start_at > $boundary) {
            return false;
        }

        if ($end_at instanceof \DateTimeImmutable && $end_at <= $boundary) {
            return false;
        }

        return true;
    }

    /**
     * Get manage roles.
     *
     * @return array
     */
    public function getManageRoles(): array
    {
        $roles = $this->getGroupsConfig()['roles']['management'] ?? [];
        if (!is_array($roles)) {
            $roles = [];
        }
        $roles = array_values(array_filter(array_map('sanitize_key', $roles)));
        $this->getLogger()->debug('Manage roles resolved', [
            'source' => 'wicket-orgman',
            'roles' => $roles,
        ]);

        return $roles;
    }

    /**
     * Get roster roles.
     *
     * @return array
     */
    public function getRosterRoles(): array
    {
        $roles = $this->getGroupsConfig()['roles']['roster'] ?? [];
        if (!is_array($roles)) {
            $roles = [];
        }
        $roles = array_values(array_filter(array_map('sanitize_key', $roles)));
        $this->getLogger()->debug('Roster roles resolved', [
            'source' => 'wicket-orgman',
            'roles' => $roles,
        ]);

        return $roles;
    }

    /**
     * Get roster management tag name.
     *
     * @return string
     */
    public function getRosterTagName(): string
    {
        $tag = (string) ($this->getGroupsConfig()['matching']['tag_name'] ?? '');
        $tag = trim($tag);
        $this->getLogger()->debug('Roster tag name', [
            'source' => 'wicket-orgman',
            'tag' => $tag,
        ]);

        return $tag;
    }

    /**
     * Determine if a group should be included based on tag.
     *
     * @param array $group
     * @return bool
     */
    private function groupHasRosterTag(array $group): bool
    {
        $tag = $this->getRosterTagName();
        if ('' === $tag) {
            return true;
        }

        $tags = $group['attributes']['tags'] ?? [];
        if (!is_array($tags)) {
            return false;
        }

        $case_sensitive = (bool) ($this->getGroupsConfig()['tag_case_sensitive'] ?? true);
        foreach ($tags as $tag_value) {
            $tag_value = is_string($tag_value) ? $tag_value : '';
            if ($case_sensitive && $tag_value === $tag) {
                return true;
            }
            if (!$case_sensitive && strcasecmp($tag_value, $tag) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure group payload includes tags; fallback to group details endpoint when omitted.
     *
     * @param string $group_id
     * @param array  $group
     * @return array
     */
    private function ensureGroupTags(string $group_id, array $group): array
    {
        $group_attrs = is_array($group['attributes'] ?? null) ? $group['attributes'] : [];
        $group_tags = $group_attrs['tags'] ?? null;
        $group_org_uuid = (string) ($group['relationships']['organization']['data']['id'] ?? '');
        if (is_array($group_tags) && !empty($group_tags) && $group_org_uuid !== '') {
            return $group;
        }

        if ($group_id === '' || !function_exists('wicket_api_client')) {
            return $group;
        }

        static $group_details_cache = [];
        if (isset($group_details_cache[$group_id])) {
            return $group_details_cache[$group_id];
        }

        try {
            $details = wicket_api_client()->get('/groups/' . rawurlencode($group_id));
            $detail_attrs = is_array($details) ? ($details['data']['attributes'] ?? []) : [];
            if (is_array($detail_attrs)) {
                if (!isset($group['attributes']) || !is_array($group['attributes'])) {
                    $group['attributes'] = [];
                }
                if (isset($detail_attrs['tags']) && is_array($detail_attrs['tags'])) {
                    $group['attributes']['tags'] = $detail_attrs['tags'];
                }
            }
            $detail_relationships = is_array($details) ? ($details['data']['relationships'] ?? []) : [];
            if (is_array($detail_relationships)) {
                $detail_org = $detail_relationships['organization']['data']['id'] ?? '';
                if (!isset($group['relationships']) || !is_array($group['relationships'])) {
                    $group['relationships'] = [];
                }
                if ($detail_org !== '') {
                    $group['relationships']['organization'] = [
                        'data' => [
                            'type' => 'organizations',
                            'id' => $detail_org,
                        ],
                    ];
                }
            }
        } catch (\Throwable $e) {
            $this->getLogger()->error('Group detail tag fetch failed', [
                'source' => 'wicket-orgman',
                'group_id' => $group_id,
                'error' => $e->getMessage(),
            ]);
        }

        $group_details_cache[$group_id] = $group;

        return $group;
    }

    /**
     * Get page size for group list.
     *
     * @return int
     */
    public function getGroupListPageSize(): int
    {
        $size = (int) ($this->getGroupsConfig()['list']['page_size'] ?? 15);

        return max(1, $size);
    }

    /**
     * Get page size for group member list.
     *
     * @return int
     */
    public function getGroupMemberPageSize(): int
    {
        $size = (int) ($this->getGroupsConfig()['list']['member_page_size'] ?? 15);

        return max(1, $size);
    }

    /**
     * Get current user group memberships.
     *
     * @param string $person_uuid
     * @param array  $args
     * @return array|false
     */
    public function getPersonGroupMemberships(string $person_uuid, array $args = [])
    {
        if (empty($person_uuid) || !function_exists('wicket_api_client')) {
            return false;
        }

        $page = max(1, (int) ($args['page'] ?? 1));
        $size = max(1, (int) ($args['size'] ?? $this->getGroupListPageSize()));
        $active = isset($args['active']) ? (bool) $args['active'] : true;

        $endpoint = '/group_members';
        $query = [
            'include' => 'group,organization',
            'page' => [
                'number' => $page,
                'size' => $size,
            ],
            'filter' => [
                'active_true' => $active ? 'true' : 'false',
                'person_uuid_eq' => $person_uuid,
            ],
        ];

        try {
            $client = wicket_api_client();
            $this->getLogger()->info('Fetching group memberships', [
                'source' => 'wicket-orgman',
                'person_uuid' => $person_uuid,
                'endpoint' => $endpoint,
                'page' => $page,
                'size' => $size,
                'active' => $active,
            ]);

            $response = $client->get($endpoint, ['query' => $query]);
            if ($active && is_array($response) && !empty($response['data']) && is_array($response['data'])) {
                $response['data'] = array_values(array_filter($response['data'], function ($item): bool {
                    return is_array($item) && $this->isGroupMembershipActiveRecord($item);
                }));
                if (isset($response['meta']['page']) && is_array($response['meta']['page'])) {
                    $response['meta']['page']['total_items'] = count($response['data']);
                }
            }

            return $response;
        } catch (\Throwable $e) {
            $this->getLogger()->error('Failed fetching group memberships', [
                'source' => 'wicket-orgman',
                'person_uuid' => $person_uuid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Build lookup map from included resources.
     *
     * @param array $included
     * @return array
     */
    private function buildIncludedLookup(array $included): array
    {
        $lookup = [];
        foreach ($included as $item) {
            $type = $item['type'] ?? '';
            $id = $item['id'] ?? '';
            if ($type && $id) {
                if (!isset($lookup[$type])) {
                    $lookup[$type] = [];
                }
                $lookup[$type][$id] = $item;
            }
        }

        return $lookup;
    }

    /**
     * Extract organization identifier from group membership custom_data_field.
     *
     * @param array $group_membership
     * @param string $fallback_org_uuid
     * @return string
     */
    public function extractOrgIdentifier(array $group_membership, string $fallback_org_uuid = ''): string
    {
        $info = $group_membership['attributes']['custom_data_field'] ?? null;
        $config = $this->getGroupsConfig()['additional_info'] ?? [];
        $expected_key = isset($config['key']) ? (string) $config['key'] : '';
        $value_field = isset($config['value_field']) ? (string) $config['value_field'] : '';
        $fallback_to_org_uuid = (bool) ($config['fallback_to_org_uuid'] ?? true);

        $membership_id = $group_membership['id'] ?? 'unknown';
        $person_id = $group_membership['relationships']['person']['data']['id'] ?? 'unknown';

        if (is_array($info)) {
            $key = $info['key'] ?? '';
            if ($expected_key === '' || $key === $expected_key) {
                $value = $info['value'] ?? null;
                if ('' !== $value_field && is_array($value) && isset($value[$value_field])) {
                    $value = $value[$value_field];
                }
                if (is_string($value) && '' !== trim($value)) {
                    $this->getLogger()->debug('extractOrgIdentifier: resolved from custom_data_field', [
                        'source' => 'wicket-orgman',
                        'membership_id' => $membership_id,
                        'person_id' => $person_id,
                        'key' => $key,
                        'value_field' => $value_field,
                        'resolved' => trim($value),
                    ]);

                    return trim($value);
                }
            }
            $this->getLogger()->debug('extractOrgIdentifier: custom_data_field present but no match', [
                'source' => 'wicket-orgman',
                'membership_id' => $membership_id,
                'person_id' => $person_id,
                'expected_key' => $expected_key,
                'actual_key' => $key,
                'value_field' => $value_field,
                'raw_value' => $info['value'] ?? null,
            ]);
        } else {
            $this->getLogger()->debug('extractOrgIdentifier: no custom_data_field', [
                'source' => 'wicket-orgman',
                'membership_id' => $membership_id,
                'person_id' => $person_id,
            ]);
        }

        if ($fallback_to_org_uuid && $fallback_org_uuid) {
            $this->getLogger()->debug('extractOrgIdentifier: fallback to org_uuid', [
                'source' => 'wicket-orgman',
                'membership_id' => $membership_id,
                'person_id' => $person_id,
                'fallback_org_uuid' => $fallback_org_uuid,
            ]);

            return $fallback_org_uuid;
        }

        $this->getLogger()->debug('extractOrgIdentifier: returning empty', [
            'source' => 'wicket-orgman',
            'membership_id' => $membership_id,
            'person_id' => $person_id,
        ]);

        return '';
    }

    /**
     * Normalize organization scope token for comparisons.
     *
     * @param string $value
     * @return string
     */
    private function normalizeScopeToken(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;

        return trim($value);
    }

    /**
     * Resolve comparable scope tokens from a candidate identifier/name.
     *
     * @param string $candidate
     * @param bool   $expand_lookup
     * @return array<int, string>
     */
    private function resolveScopeTokens(string $candidate, bool $expand_lookup = true): array
    {
        $tokens = [];
        $normalized_candidate = $this->normalizeScopeToken($candidate);
        if ($normalized_candidate !== '') {
            $tokens[$normalized_candidate] = true;
        }

        if (!$expand_lookup || $candidate === '' || !function_exists('wicket_get_organization') || !function_exists('isValidUuid') || !isValidUuid($candidate)) {
            return array_keys($tokens);
        }

        static $organization_scope_cache = [];
        if (!array_key_exists($candidate, $organization_scope_cache)) {
            $resolved_tokens = [];
            try {
                $organization_response = wicket_get_organization($candidate);
                if (is_array($organization_response)) {
                    $data = $organization_response['data'] ?? [];
                    $resolved_id = is_array($data) ? (string) ($data['id'] ?? '') : '';
                    if ($resolved_id !== '') {
                        $resolved_tokens[] = $resolved_id;
                    }

                    $attrs = is_array($data) ? ($data['attributes'] ?? []) : [];
                    if (is_array($attrs)) {
                        foreach ([
                            'legal_name',
                            'legal_name_en',
                            'legal_name_fr',
                            'name',
                            'name_en',
                            'name_fr',
                            'alternate_name',
                            'alternate_name_en',
                            'alternate_name_fr',
                        ] as $name_key) {
                            $candidate_value = isset($attrs[$name_key]) && is_string($attrs[$name_key])
                                ? $attrs[$name_key]
                                : '';
                            if ($candidate_value !== '') {
                                $resolved_tokens[] = $candidate_value;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                $resolved_tokens = [];
            }

            $organization_scope_cache[$candidate] = $resolved_tokens;
        }

        foreach ((array) $organization_scope_cache[$candidate] as $resolved_token) {
            $normalized_resolved = $this->normalizeScopeToken((string) $resolved_token);
            if ($normalized_resolved !== '') {
                $tokens[$normalized_resolved] = true;
            }
        }

        return array_keys($tokens);
    }

    /**
     * Determine whether a member belongs to the requested organization scope.
     *
     * @param array  $member
     * @param string $org_identifier
     * @param string $org_uuid
     * @return bool
     */
    private function memberMatchesOrgScope(array $member, string $org_identifier, string $org_uuid = ''): bool
    {
        if ($org_identifier === '' && $org_uuid === '') {
            $this->getLogger()->debug('memberMatchesOrgScope: empty scope — matching all', [
                'source' => 'wicket-orgman',
                'person_id' => $member['relationships']['person']['data']['id'] ?? 'unknown',
            ]);

            return true;
        }

        $target_tokens = [];
        $scope_candidates = $org_identifier !== ''
            ? [$org_identifier]
            : ($org_uuid !== '' ? [$org_uuid] : []);
        foreach ($scope_candidates as $target_candidate) {
            foreach ($this->resolveScopeTokens((string) $target_candidate, true) as $token) {
                $target_tokens[$token] = true;
            }
        }
        if (empty($target_tokens)) {
            $this->getLogger()->debug('memberMatchesOrgScope: no target tokens resolved — matching all', [
                'source' => 'wicket-orgman',
                'org_identifier' => $org_identifier,
                'org_uuid' => $org_uuid,
            ]);

            return true;
        }

        $member_scope_tokens = [];
        $member_org_uuid = (string) ($member['relationships']['organization']['data']['id'] ?? '');
        $member_identifier = $this->extractOrgIdentifier($member, $member_org_uuid);

        $this->getLogger()->debug('memberMatchesOrgScope: evaluating member', [
            'source' => 'wicket-orgman',
            'person_id' => $member['relationships']['person']['data']['id'] ?? 'unknown',
            'membership_id' => $member['id'] ?? 'unknown',
            'target_org_identifier' => $org_identifier,
            'target_org_uuid' => $org_uuid,
            'member_org_uuid' => $member_org_uuid,
            'member_identifier' => $member_identifier,
        ]);

        foreach ($this->resolveScopeTokens($member_identifier, false) as $token) {
            $member_scope_tokens[$token] = true;
        }
        foreach ($this->resolveScopeTokens($member_org_uuid, true) as $token) {
            $member_scope_tokens[$token] = true;
        }

        $is_match = false;
        if (!empty($member_scope_tokens)) {
            foreach (array_keys($target_tokens) as $token) {
                if (isset($member_scope_tokens[$token])) {
                    $is_match = true;
                    break;
                }
            }
        }

        if (!$is_match) {
            $this->getLogger()->debug('memberMatchesOrgScope: NO MATCH', [
                'source' => 'wicket-orgman',
                'person_id' => $member['relationships']['person']['data']['id'] ?? 'unknown',
                'membership_id' => $member['id'] ?? 'unknown',
                'target_tokens' => array_keys($target_tokens),
                'member_tokens' => array_keys($member_scope_tokens),
                'member_org_uuid' => $member_org_uuid,
                'member_identifier' => $member_identifier,
            ]);
        } else {
            $this->getLogger()->info('memberMatchesOrgScope: MATCH', [
                'source' => 'wicket-orgman',
                'person_id' => $member['relationships']['person']['data']['id'] ?? 'unknown',
                'membership_id' => $member['id'] ?? 'unknown',
                'target_org_identifier' => $org_identifier,
                'target_org_uuid' => $org_uuid,
                'member_org_uuid' => $member_org_uuid,
                'member_identifier' => $member_identifier,
                'target_tokens' => array_keys($target_tokens),
                'member_tokens' => array_keys($member_scope_tokens),
            ]);
        }

        return $is_match;
    }

    public function buildCustomDataField(string $org_identifier, string $role_slug = ''): ?array
    {
        if ('' === $org_identifier) {
            return null;
        }

        $config = $this->getGroupsConfig()['additional_info'] ?? [];
        $key = isset($config['key']) ? (string) $config['key'] : '';
        $value_field = isset($config['value_field']) ? (string) $config['value_field'] : '';

        if ('' === $key) {
            return null;
        }

        $value = '' !== $value_field ? [$value_field => $org_identifier] : $org_identifier;

        // Send $schema as null — the API auto-assigns the correct JsonSchema
        // for the GroupMember type via validate_custom_data_field_data.
        return [
            '$schema' => null,
            'value' => $value,
        ];
    }

    /**
     * Get manageable groups for person.
     *
     * @param string $person_uuid
     * @param array  $args
     * @return array
     */
    public function getManageableGroups(string $person_uuid, array $args = []): array
    {
        $page = max(1, (int) ($args['page'] ?? 1));
        $size = max(1, (int) ($args['size'] ?? $this->getGroupListPageSize()));
        $query = isset($args['query']) ? sanitize_text_field((string) $args['query']) : '';
        $include_all_roles = isset($args['include_all_roles']) ? (bool) $args['include_all_roles'] : false;

        $response = $this->getPersonGroupMemberships($person_uuid, [
            'page' => $page,
            'size' => $size,
            'active' => true,
        ]);

        $groups = [];
        $meta = [
            'page' => [
                'number' => $page,
                'size' => $size,
                'total_pages' => 1,
                'total_items' => 0,
            ],
        ];

        if (!is_array($response) || empty($response['data'])) {
            $this->getLogger()->debug('No group memberships found', [
                'source' => 'wicket-orgman',
                'person_uuid' => $person_uuid,
            ]);

            $groups = $this->applyManagerGroupFallback($person_uuid, $query, $groups);
            $meta['page']['total_items'] = count($groups);

            return ['data' => $groups, 'meta' => $meta];
        }

        $included_lookup = $this->buildIncludedLookup($response['included'] ?? []);
        $manage_roles = $this->getManageRoles();

        foreach ($response['data'] as $membership) {
            $role_slug = sanitize_key((string) ($membership['attributes']['type'] ?? ''));
            $can_manage = in_array($role_slug, $manage_roles, true);
            if (!$include_all_roles && !$can_manage) {
                continue;
            }
            if ($include_all_roles && !$can_manage) {
                $this->getLogger()->debug('Including non-manage group role for groups landing list', [
                    'source' => 'wicket-orgman',
                    'person_uuid' => $person_uuid,
                    'role_slug' => $role_slug,
                    'group_id' => $membership['relationships']['group']['data']['id'] ?? '',
                ]);
            }

            $group_id = $membership['relationships']['group']['data']['id'] ?? '';
            if (!$group_id || empty($included_lookup['groups'][$group_id])) {
                continue;
            }

            $group = $this->ensureGroupTags($group_id, $included_lookup['groups'][$group_id]);
            $group_attrs = is_array($group) ? ($group['attributes'] ?? []) : [];
            $group_tags = is_array($group_attrs) ? ($group_attrs['tags'] ?? null) : null;
            $this->getLogger()->debug('Group membership included group tags', [
                'source' => 'wicket-orgman',
                'group_id' => $group_id,
                'tags_present' => is_array($group_tags),
                'tags' => is_array($group_tags) ? $group_tags : null,
            ]);
            if (!$this->groupHasRosterTag($group)) {
                $this->getLogger()->debug('Group skipped by tag filter', [
                    'source' => 'wicket-orgman',
                    'group_id' => $group_id,
                    'tags' => is_array($group_tags) ? $group_tags : null,
                ]);
                continue;
            }

            $org_id = $membership['relationships']['organization']['data']['id'] ?? '';
            if (empty($org_id)) {
                $org_id = $group['relationships']['organization']['data']['id'] ?? '';
            }
            $org_identifier = $this->extractOrgIdentifier($membership, $org_id);
            if (empty($org_id) && empty($org_identifier)) {
                $this->getLogger()->debug('Group has no organization scope metadata; keeping as manageable group', [
                    'source' => 'wicket-orgman',
                    'group_id' => $group_id,
                    'membership_id' => $membership['id'] ?? '',
                ]);
            }
            $organization = $included_lookup['organizations'][$org_id] ?? null;
            $org_name = '';
            if (is_array($organization)) {
                $org_attrs = $organization['attributes'] ?? [];
                $org_name = $org_attrs['legal_name'] ?? $org_attrs['legal_name_en'] ?? $org_attrs['name'] ?? '';
            }
            if ($org_name === '' && function_exists('wicket_get_organization')) {
                static $resolved_org_name_cache = [];
                $org_candidates = array_values(array_unique(array_filter([
                    (string) $org_id,
                    (string) $org_identifier,
                ], static function ($value): bool {
                    return is_string($value) && trim($value) !== '';
                })));

                foreach ($org_candidates as $org_candidate) {
                    if (!array_key_exists($org_candidate, $resolved_org_name_cache)) {
                        $resolved_name = '';
                        if (function_exists('isValidUuid') && isValidUuid($org_candidate)) {
                            try {
                                $organization_response = wicket_get_organization($org_candidate);
                                $organization_attrs = is_array($organization_response)
                                    ? ($organization_response['data']['attributes'] ?? [])
                                    : [];
                                if (is_array($organization_attrs)) {
                                    $resolved_name = (string) (
                                        $organization_attrs['legal_name']
                                        ?? $organization_attrs['legal_name_en']
                                        ?? $organization_attrs['name']
                                        ?? ''
                                    );
                                }
                            } catch (\Throwable $e) {
                                $resolved_name = '';
                            }
                        }
                        $resolved_org_name_cache[$org_candidate] = $resolved_name;
                    }

                    $candidate_name = (string) ($resolved_org_name_cache[$org_candidate] ?? '');
                    if ($candidate_name !== '') {
                        $org_name = $candidate_name;
                        if ($org_id === '') {
                            $org_id = $org_candidate;
                        }
                        break;
                    }
                }
            }
            if ($org_name === '' && $org_identifier !== '') {
                $org_name = $org_identifier;
            }
            $group_name = $group['attributes']['name'] ?? $group['attributes']['name_en'] ?? $group['attributes']['name_fr'] ?? '';

            if ($query !== '') {
                $haystack = strtolower($group_name);
                if (false === strpos($haystack, strtolower($query))) {
                    continue;
                }
            }

            $groups[] = [
                'group' => $group,
                'group_membership' => $membership,
                'org_uuid' => $org_id,
                'org_identifier' => $org_identifier,
                'org_name' => $org_name,
                'role_slug' => $role_slug,
                'can_manage' => $can_manage,
            ];
        }

        $page_meta = $response['meta']['page'] ?? [];
        if (is_array($page_meta)) {
            $meta['page'] = array_merge($meta['page'], $page_meta);
        }

        $groups = $this->applyManagerGroupFallback($person_uuid, $query, $groups);

        $meta['page']['total_items'] = count($groups);

        $this->getLogger()->info('Manageable groups resolved', [
            'source' => 'wicket-orgman',
            'person_uuid' => $person_uuid,
            'count' => count($groups),
            'page' => $page,
            'size' => $size,
            'include_all_roles' => $include_all_roles,
        ]);

        return [
            'data' => $groups,
            'meta' => $meta,
        ];
    }

    /**
     * Check whether current user can manage a group.
     *
     * @param string $group_uuid
     * @param string $person_uuid
     * @return array{allowed: bool, org_uuid: string, org_identifier: string, role_slug: string}
     */
    public function canManageGroup(string $group_uuid, string $person_uuid): array
    {
        $result = [
            'allowed' => false,
            'org_uuid' => '',
            'org_identifier' => '',
            'role_slug' => '',
        ];

        if (empty($group_uuid) || empty($person_uuid)) {
            return $result;
        }

        // Try direct group membership check first.
        // Increase page size to avoid missing memberships if person has many.
        $memberships = $this->getPersonGroupMemberships($person_uuid, [
            'page' => 1,
            'size' => 100,
            'active' => true,
        ]);

        if (is_array($memberships) && !empty($memberships['data'])) {
            $included_lookup = $this->buildIncludedLookup($memberships['included'] ?? []);
            $manage_roles = $this->getManageRoles();
            foreach ($memberships['data'] as $membership) {
                $role_slug = sanitize_key((string) ($membership['attributes']['type'] ?? ''));
                if (!in_array($role_slug, $manage_roles, true)) {
                    continue;
                }

                $membership_group = $membership['relationships']['group']['data']['id'] ?? '';
                if ($membership_group !== $group_uuid) {
                    continue;
                }

                $org_id = $membership['relationships']['organization']['data']['id'] ?? '';
                if (empty($org_id) && $membership_group && isset($included_lookup['groups'][$membership_group])) {
                    $group_item = $included_lookup['groups'][$membership_group];
                    $org_id = $group_item['relationships']['organization']['data']['id'] ?? $org_id;
                }
                $org_identifier = $this->extractOrgIdentifier($membership, $org_id);

                // If org_identifier is a UUID, try to resolve a name for better association matching.
                if ($org_identifier === $org_id && function_exists('wicket_get_organization')) {
                    try {
                        $org_response = wicket_get_organization($org_id);
                        $org_attrs = is_array($org_response) ? ($org_response['data']['attributes'] ?? []) : [];
                        $resolved_name = (string) ($org_attrs['legal_name'] ?? $org_attrs['legal_name_en'] ?? $org_attrs['name'] ?? '');
                        if ($resolved_name !== '') {
                            $org_identifier = $resolved_name;
                        }
                    } catch (\Throwable $e) {
                        // Fallback to UUID
                    }
                }

                $result = [
                    'allowed' => true,
                    'org_uuid' => $org_id,
                    'org_identifier' => $org_identifier,
                    'role_slug' => $role_slug,
                ];
                $this->getLogger()->info('Group access granted via direct group membership', [
                    'source' => 'wicket-orgman',
                    'group_uuid' => $group_uuid,
                    'person_uuid' => $person_uuid,
                    'role_slug' => $role_slug,
                    'org_uuid' => $org_id,
                    'org_identifier' => $org_identifier,
                ]);
                break;
            }
        }

        if (!$result['allowed']) {
            $result = $this->checkManagerGroupAccess($group_uuid, $person_uuid, $result);
        }

        $this->getLogger()->info('Group access evaluated', [
            'source' => 'wicket-orgman',
            'group_uuid' => $group_uuid,
            'person_uuid' => $person_uuid,
            'allowed' => $result['allowed'],
            'org_uuid' => $result['org_uuid'],
            'org_identifier' => $result['org_identifier'],
            'role_slug' => $result['role_slug'],
        ]);

        return $result;
    }

    /**
     * Fetch group members list (roster roles only), filtered by org identifier.
     *
     * @param string $group_uuid
     * @param string $org_identifier
     * @param array  $args
     * @return array
     */
    public function getGroupMembers(string $group_uuid, string $org_identifier, array $args = []): array
    {
        $page = max(1, (int) ($args['page'] ?? 1));
        $size = max(1, (int) ($args['size'] ?? $this->getGroupMemberPageSize()));
        $query = isset($args['query']) ? sanitize_text_field((string) $args['query']) : '';
        $org_uuid = isset($args['org_uuid']) ? sanitize_text_field((string) $args['org_uuid']) : '';
        $active = isset($args['active']) ? (bool) $args['active'] : true;

        $roles = $this->getRosterRoles();
        $role_param = implode(',', $roles);

        $this->getLogger()->debug('Group members fetch', [
            'source' => 'wicket-orgman',
            'group_uuid' => $group_uuid,
            'page' => $page,
            'size' => $size,
            'query' => $query,
            'org_identifier' => $org_identifier,
            'org_uuid' => $org_uuid,
        ]);

        return $this->getFilteredGroupMembersPage($group_uuid, $org_identifier, [
            'page' => $page,
            'size' => $size,
            'query' => $query,
            'org_uuid' => $org_uuid,
            'active' => $active,
            'role_param' => $role_param,
        ]);
    }

    /**
     * Fetch all relevant raw group-member pages, filter locally, then paginate the filtered set.
     *
     * The upstream API paginates before this library filters by org scope. That can leave
     * sparse first pages when unrelated memberships occupy the raw page window. Re-page
     * after filtering so UI pagination matches what users actually see.
     *
     * @param string $group_uuid
     * @param string $org_identifier
     * @param array  $context
     * @return array
     */
    private function getFilteredGroupMembersPage(string $group_uuid, string $org_identifier, array $context): array
    {
        $page = (int) ($context['page'] ?? 1);
        $size = (int) ($context['size'] ?? $this->getGroupMemberPageSize());
        $query = (string) ($context['query'] ?? '');
        $org_uuid = (string) ($context['org_uuid'] ?? '');
        $active = isset($context['active']) ? (bool) $context['active'] : true;
        $role_param = (string) ($context['role_param'] ?? '');

        $page = max(1, $page);
        $size = max(1, $size);

        $raw_page = 1;
        $raw_total_pages = 1;
        $filtered_members = [];

        do {
            $response = null;
            if ($query !== '' && function_exists('wicket_search_group_members')) {
                $response = wicket_search_group_members($group_uuid, $query, [
                    'per_page' => $size,
                    'page' => $raw_page,
                    'active' => true,
                    'role' => $role_param,
                ]);
            } elseif (function_exists('wicket_get_group_members')) {
                $response = wicket_get_group_members($group_uuid, [
                    'per_page' => $size,
                    'page' => $raw_page,
                    'active' => true,
                    'role' => $role_param,
                ]);
            }

            if (is_wp_error($response) || !is_array($response)) {
                $this->getLogger()->warning('Group members response error', [
                    'source' => 'wicket-orgman',
                    'error' => is_wp_error($response) ? $response->get_error_message() : 'invalid_response',
                    'group_uuid' => $group_uuid,
                    'page' => $raw_page,
                ]);

                return [
                    'members' => [],
                    'pagination' => [
                        'currentPage' => $page,
                        'totalPages' => 1,
                        'pageSize' => $size,
                        'totalItems' => 0,
                    ],
                    'query' => $query,
                ];
            }

            $members_before_filter = count($response['data'] ?? []);
            $filtered_batch = $this->extractFilteredGroupMembers($response, $org_identifier, $org_uuid, $active);
            $members_after_filter = count($filtered_batch);

            $this->getLogger()->debug('Group members batch filtered', [
                'source' => 'wicket-orgman',
                'group_uuid' => $group_uuid,
                'raw_page' => $raw_page,
                'before' => $members_before_filter,
                'after' => $members_after_filter,
                'org_identifier' => $org_identifier,
                'org_uuid' => $org_uuid,
            ]);

            $filtered_members = array_merge($filtered_members, $filtered_batch);

            $page_meta = $response['meta']['page'] ?? [];
            $raw_total_pages = is_array($page_meta)
                ? max(1, (int) ($page_meta['total_pages'] ?? $raw_total_pages))
                : 1;
            $raw_page++;
        } while ($raw_page <= $raw_total_pages);

        $total_items = count($filtered_members);
        $total_pages = max(1, (int) ceil($total_items / $size));
        $page = min($page, $total_pages);
        $offset = ($page - 1) * $size;

        $this->getLogger()->info('Group members normalized', [
            'source' => 'wicket-orgman',
            'count' => count($filtered_members),
            'page' => $page,
            'total_pages' => $total_pages,
            'raw_total_pages' => $raw_total_pages,
        ]);

        return [
            'members' => array_slice($filtered_members, $offset, $size),
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $total_pages,
                'pageSize' => $size,
                'totalItems' => $total_items,
            ],
            'query' => $query,
        ];
    }

    /**
     * Find group member id for a person and role within a group.
     *
     * @param string $group_uuid
     * @param string $person_uuid
     * @param string $org_identifier
     * @param array  $roles
     * @return string
     */
    public function findGroupMemberId(
        string $group_uuid,
        string $person_uuid,
        string $org_identifier,
        array $roles = [],
        string $org_uuid = ''
    ): string {
        if (empty($group_uuid) || empty($person_uuid)) {
            return '';
        }

        $roles = !empty($roles) ? $roles : $this->getRosterRoles();
        $role_param = implode(',', $roles);

        $response = null;
        if (function_exists('wicket_get_group_members')) {
            $response = wicket_get_group_members($group_uuid, [
                'per_page' => 100,
                'page' => 1,
                'active' => true,
                'role' => $role_param,
            ]);
        }

        if (!is_array($response) || empty($response['data'])) {
            return '';
        }

        foreach ($response['data'] as $item) {
            if (($context['active'] ?? true) && !$this->isGroupMembershipActiveRecord($item)) {
                continue;
            }

            $member_person = $item['relationships']['person']['data']['id'] ?? '';
            if ($member_person !== $person_uuid) {
                continue;
            }

            if (!$this->memberMatchesOrgScope($item, $org_identifier, $org_uuid)) {
                continue;
            }

            return (string) ($item['id'] ?? '');
        }

        return '';
    }

    /**
     * Normalize group member response.
     *
     * @param array|\WP_Error|null $response
     * @param string $org_identifier
     * @param array $context
     * @return array
     */
    private function normalizeGroupMembersResponse($response, string $org_identifier, array $context): array
    {
        $page = (int) ($context['page'] ?? 1);
        $size = (int) ($context['size'] ?? $this->getGroupMemberPageSize());
        $query = (string) ($context['query'] ?? '');
        $org_uuid = (string) ($context['org_uuid'] ?? '');
        $active = isset($context['active']) ? (bool) $context['active'] : true;

        $members = [];
        $pagination = [
            'currentPage' => $page,
            'totalPages' => 1,
            'pageSize' => $size,
            'totalItems' => 0,
        ];

        if (is_wp_error($response) || !is_array($response)) {
            $this->getLogger()->warning('Group members response error', [
                'source' => 'wicket-orgman',
                'error' => is_wp_error($response) ? $response->get_error_message() : 'invalid_response',
            ]);

            return [
                'members' => $members,
                'pagination' => $pagination,
                'query' => $query,
            ];
        }

        $included_lookup = $this->buildIncludedLookup($response['included'] ?? []);
        $data = $response['data'] ?? [];

        foreach ($data as $item) {
            if ($active && !$this->isGroupMembershipActiveRecord($item)) {
                continue;
            }

            $person_id = $item['relationships']['person']['data']['id'] ?? '';
            if (!$person_id) {
                continue;
            }

            if (!$this->memberMatchesOrgScope($item, $org_identifier, $org_uuid)) {
                continue;
            }

            $person = $included_lookup['people'][$person_id] ?? null;
            $attributes = is_array($person) ? ($person['attributes'] ?? []) : [];
            $given = (string) ($attributes['given_name'] ?? '');
            $family = (string) ($attributes['family_name'] ?? '');
            $full_name = trim(trim($given) . ' ' . trim($family));

            $email = (string) ($attributes['primary_email_address'] ?? '');
            if ('' === $email && isset($attributes['email'])) {
                $email = (string) $attributes['email'];
            }
            if ('' === $email && isset($attributes['primary_email'])) {
                $email = (string) $attributes['primary_email'];
            }

            $confirmed_at = $attributes['user']['confirmed_at']
                ?? $attributes['confirmed_at']
                ?? null;

            $members[] = [
                'group_member_id' => $item['id'] ?? '',
                'person_uuid' => $person_id,
                'full_name' => $full_name,
                'email' => $email,
                'role' => $item['attributes']['type'] ?? '',
                'confirmed_at' => $confirmed_at,
                'custom_data_field' => $item['attributes']['custom_data_field'] ?? null,
            ];
        }

        $page_meta = $response['meta']['page'] ?? [];
        if (is_array($page_meta)) {
            $pagination['currentPage'] = (int) ($page_meta['number'] ?? $pagination['currentPage']);
            $pagination['totalPages'] = (int) ($page_meta['total_pages'] ?? $pagination['totalPages']);
            $pagination['pageSize'] = (int) ($page_meta['size'] ?? $pagination['pageSize']);
            $pagination['totalItems'] = (int) ($page_meta['total_items'] ?? count($members));
        } else {
            $pagination['totalItems'] = count($members);
        }

        $this->getLogger()->info('Group members normalized', [
            'source' => 'wicket-orgman',
            'count' => count($members),
            'page' => $pagination['currentPage'],
            'total_pages' => $pagination['totalPages'],
        ]);

        return [
            'members' => $members,
            'pagination' => $pagination,
            'query' => $query,
        ];
    }

    /**
     * Extract UI-ready group members from a raw API response using local filtering rules.
     *
     * @param array  $response
     * @param string $org_identifier
     * @param string $org_uuid
     * @param bool   $active
     * @return array<int, array<string, mixed>>
     */
    private function extractFilteredGroupMembers(array $response, string $org_identifier, string $org_uuid, bool $active): array
    {
        $members = [];
        $included_lookup = $this->buildIncludedLookup($response['included'] ?? []);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        foreach ($data as $item) {
            if ($active && !$this->isGroupMembershipActiveRecord($item)) {
                continue;
            }

            $person_id = $item['relationships']['person']['data']['id'] ?? '';
            if ($person_id === '') {
                continue;
            }

            if (!$this->memberMatchesOrgScope($item, $org_identifier, $org_uuid)) {
                continue;
            }

            $person = $included_lookup['people'][$person_id] ?? null;
            $attributes = is_array($person) ? ($person['attributes'] ?? []) : [];
            $given = (string) ($attributes['given_name'] ?? '');
            $family = (string) ($attributes['family_name'] ?? '');
            $full_name = trim(trim($given) . ' ' . trim($family));

            $email = (string) ($attributes['primary_email_address'] ?? '');
            if ($email === '' && isset($attributes['email'])) {
                $email = (string) $attributes['email'];
            }
            if ($email === '' && isset($attributes['primary_email'])) {
                $email = (string) $attributes['primary_email'];
            }

            $confirmed_at = $attributes['user']['confirmed_at']
                ?? $attributes['confirmed_at']
                ?? null;

            $members[] = [
                'group_member_id' => $item['id'] ?? '',
                'person_uuid' => $person_id,
                'full_name' => $full_name,
                'email' => $email,
                'role' => $item['attributes']['type'] ?? '',
                'confirmed_at' => $confirmed_at,
                'custom_data_field' => $item['attributes']['custom_data_field'] ?? null,
            ];
        }

        return $members;
    }

    /**
     * Create group membership with custom data.
     *
     * @param string $person_uuid
     * @param string $group_uuid
     * @param string $role_slug
     * @param array|null $custom_data_field
     * @return array|\WP_Error
     */
    public function createGroupMember(string $person_uuid, string $group_uuid, string $role_slug, $custom_data_field = null)
    {
        // Prefer base helper for backwards-compatible group create behavior.
        // Extended helper now supports optional custom_data_field without altering defaults.
        if (function_exists('wicket_add_group_member')) {
            $helper_response = wicket_add_group_member($person_uuid, $group_uuid, $role_slug, [
                'start_date' => $this->currentTimestamp(),
                'end_date' => null,
                'skip_if_exists' => false,
                'custom_data_field' => $custom_data_field,
            ]);

            if (is_wp_error($helper_response)) {
                return $helper_response;
            }

            return $helper_response;
        }

        if (!function_exists('wicket_api_client')) {
            return new \WP_Error('missing_client', 'MDP API client unavailable.');
        }

        $payload = [
            'data' => [
                'type' => 'group_members',
                'attributes' => [
                    'type' => $role_slug,
                    'start_date' => $this->currentTimestamp(),
                    'end_date' => null,
                    'custom_data_field' => $custom_data_field,
                    'person_id' => $person_uuid,
                ],
                'relationships' => [
                    'group' => [
                        'data' => [
                            'type' => 'groups',
                            'id' => $group_uuid,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $client = wicket_api_client();
            $this->getLogger()->info('Creating group member', [
                'source' => 'wicket-orgman',
                'group_uuid' => $group_uuid,
                'person_uuid' => $person_uuid,
                'role' => $role_slug,
                'custom_data_field' => $custom_data_field,
            ]);

            return $client->post('group_members', ['json' => $payload]);
        } catch (\Throwable $e) {
            return new \WP_Error('wicket_api_error', $e->getMessage());
        }
    }

    /**
     * End-date or delete group membership.
     *
     * @param string $group_member_id
     * @return array|\WP_Error
     */
    public function removeGroupMember(string $group_member_id)
    {
        if (empty($group_member_id) || !function_exists('wicket_api_client')) {
            return new \WP_Error('missing_param', 'Group member id is required.');
        }

        $mode = (string) ($this->getGroupsConfig()['removal']['mode'] ?? 'end_date');
        if ('delete' === $mode && function_exists('wicket_remove_group_member')) {
            $deleted = wicket_remove_group_member($group_member_id);
            if ($deleted) {
                $this->getLogger()->info('Deleted group member', [
                    'source' => 'wicket-orgman',
                    'group_member_id' => $group_member_id,
                ]);

                return ['status' => 'success'];
            }

            return new \WP_Error('delete_failed', 'Unable to delete group member.');
        }

        $removal_config = $this->getGroupsConfig()['removal'] ?? [];
        $format = (string) ($removal_config['end_date_format'] ?? 'Y-m-d\TH:i:s\Z');
        $anchor = $this->getRemovalAnchor();
        if ($format === 'Y-m-d\TH:i:s\Z') {
            $end_date = $anchor === 'day_start_utc'
                ? $this->currentDayStartTimestamp()
                : $this->currentTimestamp();
        } else {
            $end_date = (new \DateTimeImmutable('now', wp_timezone()))->format($format);
        }

        $payload = [
            'data' => [
                'type' => 'group_members',
                'id' => $group_member_id,
                'attributes' => [
                    'end_date' => $end_date,
                ],
            ],
        ];

        try {
            $client = wicket_api_client();
            $this->getLogger()->info('End-dating group member', [
                'source' => 'wicket-orgman',
                'group_member_id' => $group_member_id,
                'end_date' => $end_date,
            ]);

            return $client->patch('group_members/' . rawurlencode($group_member_id), ['json' => $payload]);
        } catch (\Throwable $e) {
            return new \WP_Error('wicket_api_error', $e->getMessage());
        }
    }

    /**
     * Check if a person holds the manager MDP org role for a specific group.
     *
     * @param string $group_uuid
     * @param string $person_uuid
     * @param array  $result     Current result (allowed: false)
     * @return array Updated result
     */
    private function checkManagerGroupAccess(string $group_uuid, string $person_uuid, array $result): array
    {
        $log_ctx = ['source' => 'wicket-orgman', 'group_uuid' => $group_uuid, 'person_uuid' => $person_uuid];

        $this->getLogger()->debug('checkManagerGroupAccess: entering MDP role fallback', $log_ctx);

        $all_manager_access = $this->resolveAllManagerOrgAccess($person_uuid);
        if (empty($all_manager_access)) {
            $this->getLogger()->debug('checkManagerGroupAccess: person holds no manager MDP role — access denied', $log_ctx);

            return $result;
        }

        try {
            if (!function_exists('wicket_api_client')) {
                return $result;
            }

            $raw = wicket_api_client()->get('/groups/' . rawurlencode($group_uuid));
            $group_data = is_array($raw) ? ($raw['data'] ?? []) : [];

            if (empty($group_data)) {
                return $result;
            }

            $group_org_uuid = (string) ($group_data['relationships']['organization']['data']['id'] ?? '');

            if ($this->groupHasRosterTag($group_data)) {
                // Any membership_manager can access any roster-tagged group.
                // Member list scoping happens downstream in getGroupMembers() via memberMatchesOrgScope().
                $primary_access = $all_manager_access[0];

                $this->getLogger()->info('checkManagerGroupAccess: roster tag confirmed — MDP manager access granted', array_merge($log_ctx, [
                    'mdp_org_uuid' => $primary_access['org_uuid'],
                    'group_org_uuid' => $group_org_uuid,
                ]));

                return [
                    'allowed'        => true,
                    'org_uuid'       => $primary_access['org_uuid'],
                    'org_identifier' => $primary_access['org_identifier'],
                    'role_slug'      => '',
                ];
            }
        } catch (\Throwable $e) {
            $this->getLogger()->error('checkManagerGroupAccess: exception', array_merge($log_ctx, ['error' => $e->getMessage()]));
        }

        return $result;
    }

    /**
     * Augment a groups list with groups accessible via the manager MDP org role.
     *
     * @param string $person_uuid
     * @param string $query       Current search string (empty = no filter)
     * @param array  $groups      Groups already collected
     * @return array Updated groups list
     */
    private function applyManagerGroupFallback(string $person_uuid, string $query, array $groups): array
    {
        $roster_strategy = (string) ($this->config['membership']['strategy'] ?? 'direct');
        if ('groups' !== $roster_strategy) {
            return $groups;
        }

        $all_manager_access = $this->resolveAllManagerOrgAccess($person_uuid);
        if (empty($all_manager_access)) {
            return $groups;
        }

        $existing_ids = [];
        foreach ($groups as $g) {
            $gid = (string) ($g['group']['id'] ?? '');
            if ($gid !== '') {
                $existing_ids[$gid] = true;
            }
        }

        // Fetch ALL roster-tagged groups regardless of owning org.
        // Member scoping happens downstream in getGroupMembers() via memberMatchesOrgScope().
        $all_tagged = $this->fetchAllRosterTaggedGroups();

        // Use the first manager access entry for org_uuid/org_identifier on appended entries.
        // These drive member-scoping in getGroupMembers() — members from other orgs are filtered out.
        $primary_access = $all_manager_access[0];
        $mgr_org_uuid = $primary_access['org_uuid'];
        $mgr_org_identifier = $primary_access['org_identifier'];
        $org_name = ($mgr_org_identifier !== $mgr_org_uuid) ? $mgr_org_identifier : '';

        foreach ($all_tagged as $group) {
            $group_id = (string) ($group['id'] ?? '');
            if ('' === $group_id || isset($existing_ids[$group_id])) {
                continue;
            }

            $group_attrs = is_array($group['attributes'] ?? null) ? $group['attributes'] : [];
            $group_name = $group_attrs['name'] ?? $group_attrs['name_en'] ?? $group_attrs['name_fr'] ?? '';

            if ($query !== '' && false === stripos($group_name, $query)) {
                continue;
            }

            $groups[] = [
                'group'            => $group,
                'group_membership' => [],
                'org_uuid'         => $mgr_org_uuid,
                'org_identifier'   => $mgr_org_identifier,
                'org_name'         => $org_name,
                'role_slug'        => '',
                'can_manage'       => true,
            ];
            $existing_ids[$group_id] = true;
        }

        return $groups;
    }

    /**
     * Fetch ALL groups tagged with the roster-management tag, regardless of owning org.
     *
     * Uses server-side tags_name_eq filter when available, with local tag verification
     * as a safety net. Paginates through all results.
     *
     * @return array Array of group data objects
     */
    private function fetchAllRosterTaggedGroups(): array
    {
        $log_ctx = ['source' => 'wicket-orgman'];

        if (!function_exists('wicket_api_client')) {
            $this->getLogger()->warning('fetchAllRosterTaggedGroups: wicket_api_client unavailable', $log_ctx);

            return [];
        }

        $roster_tag = $this->getRosterTagName();
        $log_ctx['roster_tag'] = $roster_tag;

        $this->getLogger()->debug('fetchAllRosterTaggedGroups: fetching all tagged groups', $log_ctx);

        try {
            $tagged = [];
            $page = 1;
            $total_pages = 1;

            do {
                $filter = [];
                if ($roster_tag !== '') {
                    $filter['tags_name_eq'] = $roster_tag;
                }

                $response = wicket_api_client()->get('/groups', [
                    'query' => [
                        'page'   => ['number' => $page, 'size' => 100],
                        'filter' => $filter,
                        'sort'   => 'name_en',
                    ],
                ]);

                $batch = is_array($response) ? ($response['data'] ?? []) : [];

                $this->getLogger()->debug('fetchAllRosterTaggedGroups: API batch', array_merge($log_ctx, [
                    'page' => $page,
                    'batch_count' => count($batch),
                ]));

                foreach ($batch as $group) {
                    if (!is_array($group)) {
                        continue;
                    }
                    // Local tag check as safety net (handles case-sensitivity config).
                    if (!$this->groupHasRosterTag($group)) {
                        continue;
                    }
                    $tagged[] = $group;
                }

                $page_meta = is_array($response) ? ($response['meta']['page'] ?? []) : [];
                $total_pages = is_array($page_meta)
                    ? max(1, (int) ($page_meta['total_pages'] ?? 1))
                    : 1;
                $page++;
            } while ($page <= $total_pages);

            $this->getLogger()->info('fetchAllRosterTaggedGroups: complete', array_merge($log_ctx, [
                'tagged_count' => count($tagged),
            ]));

            return $tagged;
        } catch (\Throwable $e) {
            $this->getLogger()->error('fetchAllRosterTaggedGroups: API request failed', array_merge($log_ctx, [
                'error' => $e->getMessage(),
            ]));

            return [];
        }
    }

    /**
     * Resolve all org UUIDs and identifiers for a person holding the manager MDP org role.
     *
     * Reads access.roles.manager from config, queries /people/{uuid}/roles,
     * and returns an array of all org-scoped matches.
     *
     * @param string $person_uuid
     * @return array<int, array{org_uuid: string, org_identifier: string}>
     */
    private function resolveAllManagerOrgAccess(string $person_uuid): array
    {
        $log_ctx = ['source' => 'wicket-orgman', 'person_uuid' => $person_uuid];

        if ('' === $person_uuid) {
            $this->getLogger()->warning('resolveAllManagerOrgAccess: called with empty person_uuid', $log_ctx);

            return [];
        }

        if (!function_exists('wicket_api_client')) {
            $this->getLogger()->warning('resolveAllManagerOrgAccess: wicket_api_client unavailable', $log_ctx);

            return [];
        }

        $config = ConfigService::getConfig();
        $manager_role = sanitize_key((string) ($config['access']['roles']['manager'] ?? ''));

        if ('' === $manager_role) {
            $this->getLogger()->warning('resolveAllManagerOrgAccess: access.roles.manager not set in config — cannot resolve manager access', $log_ctx);

            return [];
        }

        $log_ctx['manager_role'] = $manager_role;
        $this->getLogger()->debug('resolveAllManagerOrgAccess: scanning MDP roles for manager role', $log_ctx);

        try {
            $response = wicket_api_client()->get('/people/' . rawurlencode($person_uuid) . '/roles', [
                'page' => ['number' => 1, 'size' => 100],
                'sort' => '-global,name',
            ]);

            if (!isset($response['data']) || !is_array($response['data'])) {
                $this->getLogger()->warning('resolveAllManagerOrgAccess: roles API returned no data', $log_ctx);

                return [];
            }

            $all_access = [];
            foreach ($response['data'] as $role) {
                $role_name = sanitize_key((string) ($role['attributes']['name'] ?? ''));

                if ($role_name !== $manager_role) {
                    $this->getLogger()->debug('resolveAllManagerOrgAccess: skipping non-manager role', [
                        'source' => 'wicket-orgman',
                        'role_name' => $role_name,
                        'expected' => $manager_role,
                    ]);
                    continue;
                }

                if (!empty($role['attributes']['global'])) {
                    $this->getLogger()->debug('resolveAllManagerOrgAccess: skipping global role', [
                        'source' => 'wicket-orgman',
                        'role_name' => $role_name,
                    ]);
                    continue;
                }

                $resource = $role['relationships']['resource']['data']
                    ?? $role['relationships']['organization']['data']
                    ?? null;
                $org_uuid = is_array($resource) ? (string) ($resource['id'] ?? '') : '';
                $resource_type = strtolower((string) (is_array($resource) ? ($resource['type'] ?? '') : ''));

                if ('' === $org_uuid) {
                    $this->getLogger()->debug('resolveAllManagerOrgAccess: skipping role with no org resource', [
                        'source' => 'wicket-orgman',
                        'role_name' => $role_name,
                    ]);
                    continue;
                }

                if ('' !== $resource_type && !in_array($resource_type, ['organizations', 'organization'], true)) {
                    $this->getLogger()->debug('resolveAllManagerOrgAccess: skipping role with invalid resource type', [
                        'source' => 'wicket-orgman',
                        'role_name' => $role_name,
                        'resource_type' => $resource_type,
                    ]);
                    continue;
                }

                // Resolve a human-readable org identifier (association name) for scope matching.
                $org_identifier = $org_uuid;
                if (function_exists('wicket_get_organization')) {
                    try {
                        $org_response = wicket_get_organization($org_uuid);
                        $org_attrs = is_array($org_response) ? ($org_response['data']['attributes'] ?? []) : [];
                        if (is_array($org_attrs)) {
                            $resolved = (string) (
                                $org_attrs['legal_name']
                                ?? $org_attrs['legal_name_en']
                                ?? $org_attrs['name']
                                ?? ''
                            );
                            if ('' !== $resolved) {
                                $org_identifier = $resolved;
                            }
                        }
                    } catch (\Throwable $e) {
                        // Keep UUID as fallback
                    }
                }

                $all_access[] = ['org_uuid' => $org_uuid, 'org_identifier' => $org_identifier];
            }

            $this->getLogger()->info('resolveAllManagerOrgAccess: resolved manager access', array_merge($log_ctx, [
                'count' => count($all_access),
            ]));

            return $all_access;
        } catch (\Throwable $e) {
            $this->getLogger()->error('resolveAllManagerOrgAccess: roles API request failed', array_merge($log_ctx, [
                'error' => $e->getMessage(),
            ]));
        }

        return [];
    }

    /**
     * Resolve the org UUID and identifier for a person holding the manager MDP role.
     *
     * @param string $person_uuid
     * @return array{org_uuid: string, org_identifier: string} or empty array
     */
    private function resolveManagerOrgAccess(string $person_uuid): array
    {
        $all = $this->resolveAllManagerOrgAccess($person_uuid);

        return !empty($all) ? $all[0] : [];
    }

    /**
     * Retrieve shared logger.
     *
     * @return \WC_Logger
     */
    private function getLogger()
    {
        if (null === $this->logger) {
            $this->logger = \Wicket()->log();
        }

        return $this->logger;
    }
}
