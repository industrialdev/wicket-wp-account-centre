<?php

declare(strict_types=1);

namespace WicketORM\Services;

/**
 * Membership roster read core.
 *
 * Owns membership list fetching, search fallback, enrichment,
 * member row shaping, SSE single-member lookup, and read cache behavior.
 *
 * This is an internal module. Callers should continue to use MemberService
 * as the compatibility surface during migration.
 */
class MembershipRosterReader
{
    /**
     * @var ConfigService
     */
    private ConfigService $configService;

    /**
     * @var array
     */
    protected array $config;

    /**
     * @var MembershipService|null
     */
    protected ?MembershipService $membershipService = null;

    /**
     * @var ConnectionService|null
     */
    protected ?ConnectionService $connectionService = null;

    /**
     * @var PermissionService|null
     */
    protected ?PermissionService $permissionService = null;

    /**
     * @var CacheService|null
     */
    protected ?CacheService $cacheService = null;

    /**
     * Constructor.
     *
     * @param ConfigService $configService
     */
    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
        $this->config = $this->configService->getFullConfig();
    }

    /**
     * Lazily instantiate MembershipService.
     *
     * @return MembershipService
     */
    private function membershipService(): MembershipService
    {
        if (!isset($this->membershipService)) {
            $this->membershipService = new MembershipService();
        }

        return $this->membershipService;
    }

    /**
     * Lazily instantiate ConnectionService.
     *
     * @return ConnectionService
     */
    private function connectionService(): ConnectionService
    {
        if (!isset($this->connectionService)) {
            $this->connectionService = new ConnectionService();
        }

        return $this->connectionService;
    }

    /**
     * Lazily instantiate PermissionService.
     *
     * @return PermissionService
     */
    private function permissionService(): PermissionService
    {
        if (!isset($this->permissionService)) {
            $this->permissionService = new PermissionService();
        }

        return $this->permissionService;
    }

    /**
     * Lazily instantiate CacheService.
     *
     * @return CacheService
     */
    private function cacheService(): CacheService
    {
        if (!isset($this->cacheService)) {
            $this->cacheService = new CacheService();
        }

        return $this->cacheService;
    }

    /**
     * Helper method to get cached data if cache is enabled.
     *
     * @param string $cache_key The cache key.
     * @return mixed|false Cached data or false if not found/disabled.
     */
    private function getCachedData($cache_key)
    {
        return $this->cacheService()->get($cache_key);
    }

    /**
     * Helper method to set cached data if cache is enabled.
     *
     * @param string   $cache_key The cache key.
     * @param mixed    $data      The data to cache.
     * @param int|null $duration  Optional TTL in seconds; null uses the configured default.
     * @return void
     */
    private function setCachedData($cache_key, $data, ?int $duration = null)
    {
        $this->cacheService()->set($cache_key, $data, $duration);
    }

    /**
     * Retrieve organization membership members via legacy helper.
     *
     * @param string $membershipUuid Membership identifier.
     * @param array  $args           Optional arguments (page, size, query).
     * @return array|null
     */
    public function getMembershipMembers(string $membershipUuid, array $args = []): ?array
    {
        if (empty($membershipUuid)) {
            return null;
        }

        $defaultPageSize = 15;

        $page = max(1, (int) ($args['page'] ?? 1));
        $size = max(1, (int) ($args['size'] ?? $defaultPageSize));
        $searchTerm = isset($args['query']) ? sanitize_text_field((string) $args['query']) : '';
        $isLazy = (bool) ($args['lazy'] ?? false);

        $logger = \Wicket()->log();
        $gen = $this->cacheService()->getMembershipGeneration($membershipUuid);

        // Cache initial load only (no search term)
        if (empty($searchTerm)) {
            $cache_key = 'orgman_members_' . md5($membershipUuid . $page . $size . (int) $isLazy . $gen);
            $cached_data = $this->getCachedData($cache_key);

            if (false !== $cached_data) {
                return $cached_data;
            }
        }

        if ('' !== $searchTerm) {
            $search_cache_key = 'orgman_search_' . md5($membershipUuid . $searchTerm . $page . $size . $gen);
            $cached_search = $this->getCachedData($search_cache_key);
            if (false !== $cached_search) {
                return $cached_search;
            }

            $search_ttl = \WicketORM\Helpers\ConfigHelper::get_search_cache_duration();

            try {
                $searchResult = $this->membershipService()->membershipSearchMembers(
                    $membershipUuid,
                    [
                        'page'  => $page,
                        'size'  => $size,
                        'query' => $searchTerm,
                    ]
                );

                if (!is_wp_error($searchResult) && is_array($searchResult)) {
                    $searchData = $searchResult['data'] ?? null;
                    if (is_array($searchData) && !empty($searchData)) {
                        $this->setCachedData($search_cache_key, $searchResult, $search_ttl);

                        return $searchResult;
                    }
                }

                if (is_wp_error($searchResult)) {
                }
            } catch (\Throwable $searchException) {
                $logger->error(
                    'MembershipService member search threw exception: ' . $searchException->getMessage(),
                    [
                        'source'          => 'wicket-orgman',
                        'membership_uuid' => $membershipUuid,
                        'query'           => $searchTerm,
                    ]
                );
            }
        }

        if ('' !== $searchTerm && function_exists('wicket_api_client')) {
            $search_cache_key ??= 'orgman_search_' . md5($membershipUuid . $searchTerm . $page . $size . $gen);
            $search_ttl ??= \WicketORM\Helpers\ConfigHelper::get_search_cache_duration();

            $queryArgs = [
                'page[number]' => $page,
                'page[size]'   => $size,
                'include'      => 'person,emails,phones',
            ];

            $payload = [
                'filter' => [
                    'organization_membership_uuid_in'                    => [$membershipUuid],
                    'person_full_name_or_person_emails_address_cont'     => $searchTerm,
                    'active_at'                                          => 'now',
                ],
            ];

            try {
                $client = wicket_api_client();
                $response = $client->post(
                    '/person_memberships/query?' . http_build_query($queryArgs),
                    ['json' => $payload]
                );

                $normalized = $this->normalizeMembershipResponse($response);
                if (null !== $normalized) {
                    $this->setCachedData($search_cache_key, $normalized, $search_ttl);

                    return $normalized;
                }
            } catch (\Throwable $searchException) {
                $logger->error(
                    'person_memberships query search failed: ' . $searchException->getMessage(),
                    [
                        'source'          => 'wicket-orgman',
                        'membership_uuid' => $membershipUuid,
                        'query'           => $searchTerm,
                    ]
                );
            }
        }

        $queryParams = [
            'page[number]' => $page,
            'page[size]'   => $size,
            'include'      => 'person,membership',
            'filter[active_at]' => 'now',
        ];

        if ('' !== $searchTerm) {
            $queryParams['filter[search]'] = $searchTerm;
        }

        if (function_exists('wicket_api_client')) {
            try {
                $client = wicket_api_client();
                $endpoint = '/organization_memberships/' . rawurlencode($membershipUuid) . '/person_memberships';
                $response = $client->get($endpoint . '?' . http_build_query($queryParams));

                $normalized = $this->normalizeMembershipResponse($response);
                if (null !== $normalized) {
                    // Cache initial load only (no search term)
                    if (empty($searchTerm)) {
                        $isLazy = (bool) ($args['lazy'] ?? false);
                        $gen = $this->cacheService()->getMembershipGeneration($membershipUuid);
                        $cache_key = 'orgman_members_' . md5($membershipUuid . $page . $size . (int) $isLazy . $gen);
                        $this->setCachedData($cache_key, $normalized);
                    }

                    return $normalized;
                }
            } catch (\Throwable $e) {
                $logger->error(
                    'Error fetching organization membership members: ' . $e->getMessage(),
                    [
                        'source'          => 'wicket-orgman',
                        'membership_uuid' => $membershipUuid,
                    ]
                );
            }
        }

        if ('' !== $searchTerm && function_exists('wicket_api_client')) {
            try {
                $client = wicket_api_client();
                $endpoint = '/organization_memberships/' . rawurlencode($membershipUuid) . '/person_memberships';
                $fallbackResponse = $client->get($endpoint . '?' . http_build_query([
                    'page[number]' => 1,
                    'page[size]' => max(100, $size),
                    'filter[active_at]' => 'now',
                    'include' => 'person,emails,phones',
                ]));

                $normalizedFallback = $this->normalizeMembershipResponse($fallbackResponse);
                if (is_array($normalizedFallback) && isset($normalizedFallback['data']) && is_array($normalizedFallback['data'])) {
                    $locallyFiltered = $this->filterMembershipResponseByQuery($normalizedFallback, $searchTerm);

                    return $locallyFiltered;
                }
            } catch (\Throwable $localFallbackException) {
                $logger->error(
                    'Local search fallback failed: ' . $localFallbackException->getMessage(),
                    [
                        'source' => 'wicket-orgman',
                        'membership_uuid' => $membershipUuid,
                        'query' => $searchTerm,
                    ]
                );
            }
        }

        $response = $this->membershipService()->getOrgMembershipMembers($membershipUuid, $args);
        if (is_wp_error($response)) {
            /** @var \WP_Error $response */
            $error_message = $response->get_error_message();
            \Wicket()->log()->error(
                'MembershipService::getOrgMembershipMembers() returned error',
                [
                    'source'          => 'wicket-orgman',
                    'membership_uuid' => $membershipUuid,
                    'error'           => $error_message,
                ]
            );

            return null;
        }
        $final_response = $this->normalizeMembershipResponse($response);

        // Cache initial load only (no search term)
        if (empty($searchTerm) && null !== $final_response) {
            $isLazy = (bool) ($args['lazy'] ?? false);
            $gen = $this->cacheService()->getMembershipGeneration($membershipUuid);
            $cache_key = 'orgman_members_' . md5($membershipUuid . $page . $size . (int) $isLazy . $gen);
            $this->setCachedData($cache_key, $final_response);
        }

        return $final_response;
    }

    /**
     * Filter a normalized membership response by search term using person name/email fields.
     *
     * @param array $response
     * @param string $query
     * @return array
     */
    private function filterMembershipResponseByQuery(array $response, string $query): array
    {
        $query = strtolower(trim($query));
        if ($query === '') {
            return $response;
        }

        $peopleIndex = [];
        $emailsByPerson = [];
        $included = is_array($response['included'] ?? null) ? $response['included'] : [];

        foreach ($included as $item) {
            $type = (string) ($item['type'] ?? '');
            $id = (string) ($item['id'] ?? '');
            if ($type === 'people' && $id !== '') {
                $peopleIndex[$id] = $item;
                continue;
            }

            if ($type === 'emails') {
                $personId = (string) ($item['relationships']['person']['data']['id'] ?? '');
                $emailAddress = (string) ($item['attributes']['address'] ?? '');
                if ($personId !== '' && $emailAddress !== '') {
                    if (!isset($emailsByPerson[$personId])) {
                        $emailsByPerson[$personId] = [];
                    }
                    $emailsByPerson[$personId][] = $emailAddress;
                }
            }
        }

        $filteredData = [];
        $sourceData = is_array($response['data'] ?? null) ? $response['data'] : [];
        foreach ($sourceData as $membershipRow) {
            $personId = (string) ($membershipRow['relationships']['person']['data']['id'] ?? '');
            $personAttrs = is_array($peopleIndex[$personId]['attributes'] ?? null) ? $peopleIndex[$personId]['attributes'] : [];

            $parts = [];
            $parts[] = (string) ($personAttrs['full_name'] ?? '');
            $parts[] = trim((string) ($personAttrs['first_name'] ?? '') . ' ' . (string) ($personAttrs['last_name'] ?? ''));
            $parts[] = (string) ($personAttrs['name'] ?? '');

            if (isset($emailsByPerson[$personId]) && is_array($emailsByPerson[$personId])) {
                foreach ($emailsByPerson[$personId] as $emailAddress) {
                    $parts[] = (string) $emailAddress;
                }
            }

            $haystack = strtolower(implode(' ', array_filter($parts, static function ($value) {
                return is_string($value) && $value !== '';
            })));

            if ($haystack !== '' && str_contains($haystack, $query)) {
                $filteredData[] = $membershipRow;
            }
        }

        $response['data'] = $filteredData;
        if (isset($response['meta']['page']) && is_array($response['meta']['page'])) {
            $response['meta']['page']['total_items'] = count($filteredData);
            $response['meta']['page']['total_pages'] = 1;
            $response['meta']['page']['number'] = 1;
        }

        return $response;
    }

    /**
     * Normalize relationship type slug for matching/filtering.
     *
     * @param string $type Raw relationship type value.
     * @return string
     */
    private function normalizeRelationshipType(string $type): string
    {
        $normalized = strtolower(trim($type));
        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace(['-', ' '], '_', $normalized);
        $normalized = (string) preg_replace('/_+/', '_', $normalized);
        $normalized = sanitize_key($normalized);

        $aliases = [
            'affiliation' => 'affiliate',
            'affiliated' => 'affiliate',
            'affiliation_relationship' => 'affiliate',
            'companyadmin' => 'company_admin',
            'companyadministrator' => 'company_admin',
            'regularmember' => 'regular_member',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    /**
     * Normalize a relationship-type list into unique slugs.
     *
     * @param array $types
     * @return array
     */
    private function normalizeRelationshipTypeList(array $types): array
    {
        $normalized = [];
        foreach ($types as $type) {
            $slug = $this->normalizeRelationshipType((string) $type);
            if ($slug === '') {
                continue;
            }
            $normalized[] = $slug;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Resolve a human-readable label for a relationship slug.
     *
     * @param string $slug
     * @param array  $labels
     * @return string
     */
    private function resolveRelationshipLabel(string $slug, array $labels): string
    {
        if (isset($labels[$slug]) && is_string($labels[$slug]) && trim($labels[$slug]) !== '') {
            return trim($labels[$slug]);
        }

        return ucwords(str_replace('_', ' ', $slug));
    }

    /**
     * Normalize role value into canonical slug for filtering/display.
     *
     * @param string $role
     * @return string
     */
    private function normalizeRoleSlugValue(string $role): string
    {
        $normalized = strtolower(trim($role));
        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace(['-', ' '], '_', $normalized);
        $normalized = (string) preg_replace('/_+/', '_', $normalized);

        return sanitize_key($normalized);
    }

    /**
     * Resolve role slug aliases from config.
     *
     * @return array<string, string>
     */
    private function getRoleSlugAliases(): array
    {
        $configuredAliases = (array) ($this->config['access']['roles']['aliases'] ?? []);

        $aliases = [];
        foreach ($configuredAliases as $sourceRole => $targetRole) {
            $sourceSlug = $this->normalizeRoleSlugValue((string) $sourceRole);
            $targetSlug = $this->normalizeRoleSlugValue((string) $targetRole);

            if ($sourceSlug === '' || $targetSlug === '') {
                continue;
            }

            $aliases[$sourceSlug] = $targetSlug;
        }

        return $aliases;
    }

    /**
     * Normalize role value into canonical slug for filtering/display.
     *
     * @param string $role
     * @return string
     */
    public function normalizeRoleSlug(string $role): string
    {
        $normalized = $this->normalizeRoleSlugValue($role);
        if ($normalized === '') {
            return '';
        }

        $aliases = $this->getRoleSlugAliases();

        return $aliases[$normalized] ?? $normalized;
    }

    /**
     * Normalize role-list values into canonical slugs.
     *
     * @param array $roles
     * @return array
     */
    private function normalizeRoleList(array $roles): array
    {
        $normalized = [];
        foreach ($roles as $role) {
            $slug = $this->normalizeRoleSlug((string) $role);
            if ($slug === '') {
                continue;
            }
            $normalized[] = $slug;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Filter role list by allowlist/excludelist and normalize output slugs.
     *
     * @param array $roles
     * @param array $allowlist
     * @param array $excludes
     * @return array
     */
    private function filterDisplayRoles(array $roles, array $allowlist = [], array $excludes = []): array
    {
        $allowLookup = !empty($allowlist) ? array_fill_keys($allowlist, true) : [];
        $excludeLookup = !empty($excludes) ? array_fill_keys($excludes, true) : [];

        $filtered = [];
        foreach ($roles as $role) {
            $slug = $this->normalizeRoleSlug((string) $role);
            if ($slug === '') {
                continue;
            }

            if (!empty($allowLookup) && !isset($allowLookup[$slug])) {
                continue;
            }

            if (!empty($excludeLookup) && isset($excludeLookup[$slug])) {
                continue;
            }

            $filtered[] = $slug;
        }

        return array_values(array_unique($filtered));
    }

    /**
     * Merge duplicate prepared member rows for the same person.
     *
     * @param array $existing
     * @param array $incoming
     * @return array
     */
    private function mergePreparedMemberRows(array $existing, array $incoming): array
    {
        $existing['roles'] = array_values(array_unique(array_merge(
            (array) ($existing['roles'] ?? []),
            (array) ($incoming['roles'] ?? [])
        )));

        $existing['current_roles'] = array_values(array_unique(array_merge(
            (array) ($existing['current_roles'] ?? []),
            (array) ($incoming['current_roles'] ?? [])
        )));

        if (empty($existing['current_roles']) && !empty($existing['roles'])) {
            $existing['current_roles'] = (array) $existing['roles'];
        }

        $existing['relationship_names_list'] = array_values(array_unique(array_merge(
            (array) ($existing['relationship_names_list'] ?? []),
            (array) ($incoming['relationship_names_list'] ?? [])
        )));

        $existing['relationship_slugs'] = array_values(array_unique(array_merge(
            (array) ($existing['relationship_slugs'] ?? []),
            (array) ($incoming['relationship_slugs'] ?? [])
        )));

        $existing['person_connection_ids_list'] = array_values(array_unique(array_merge(
            (array) ($existing['person_connection_ids_list'] ?? []),
            (array) ($incoming['person_connection_ids_list'] ?? [])
        )));

        if (empty($existing['relationship_description']) && !empty($incoming['relationship_description'])) {
            $existing['relationship_description'] = $incoming['relationship_description'];
        }

        if (empty($existing['person_membership_id']) && !empty($incoming['person_membership_id'])) {
            $existing['person_membership_id'] = $incoming['person_membership_id'];
        }

        if (!empty($incoming['is_owner'])) {
            $existing['is_owner'] = true;
        }

        foreach (['first_name', 'last_name', 'full_name', 'title', 'email', 'status', 'job_level', 'confirmed_at'] as $field) {
            if (empty($existing[$field]) && !empty($incoming[$field])) {
                $existing[$field] = $incoming[$field];
            }
        }

        return $existing;
    }

    /**
     * Convert internal prepared row arrays to template payload shape.
     *
     * @param array $memberRow
     * @return array
     */
    private function finalizePreparedMemberRow(array $memberRow): array
    {
        $relationshipNames = array_values(array_filter((array) ($memberRow['relationship_names_list'] ?? []), static function ($value): bool {
            return is_string($value) && trim($value) !== '';
        }));
        $relationshipSlugs = array_values(array_filter((array) ($memberRow['relationship_slugs'] ?? []), static function ($value): bool {
            return is_string($value) && trim($value) !== '';
        }));
        $personConnectionIds = array_values(array_filter((array) ($memberRow['person_connection_ids_list'] ?? []), static function ($value): bool {
            return is_string($value) && trim($value) !== '';
        }));

        $memberRow['relationship_names'] = !empty($relationshipNames) ? implode(', ', $relationshipNames) : null;
        $memberRow['relationship_type'] = !empty($relationshipSlugs) ? reset($relationshipSlugs) : null;
        $memberRow['person_connection_ids'] = !empty($personConnectionIds) ? implode(',', $personConnectionIds) : null;

        unset($memberRow['relationship_names_list'], $memberRow['relationship_slugs'], $memberRow['person_connection_ids_list']);

        return $memberRow;
    }

    /**
     * Clear the cached member list for a specific organization membership.
     *
     * @param string $membershipUuid The membership UUID.
     * @return void
     */
    public function clearMembersCache(string $membershipUuid): void
    {
        $this->cacheService()->invalidateMemberCache($membershipUuid);
    }

    /**
     * Retrieve formatted member data with pagination metadata.
     *
     * @param string $membershipUuid Organization membership identifier.
     * @param string $orgUuid        Organization identifier.
     * @param array  $args           Optional arguments (page, size, query).
     * @return array{
     *     members: array<int, array>,
     *     pagination: array<string, int>,
     *     org_uuid: string,
     *     query: string
     * }
     */
    public function getMembers(string $membershipUuid, string $orgUuid, array $args = [], bool $lazy = false): array
    {
        $page = max(1, (int) ($args['page'] ?? 1));
        $size = max(1, (int) ($args['size'] ?? 15));
        $query = isset($args['query']) ? sanitize_text_field((string) $args['query']) : '';

        $membersResponse = $this->getMembershipMembers(
            $membershipUuid,
            [
                'page'  => $page,
                'size'  => $size,
                'query' => $query ?: null,
                'lazy'  => $lazy,
            ]
        );

        $result = $this->prepareMembersResult(
            $membersResponse,
            [
                'org_uuid'        => $orgUuid,
                'membership_uuid' => $membershipUuid,
                'page'            => $page,
                'size'            => $size,
                'query'           => $query,
                'lazy'            => $lazy,
            ]
        );

        // Pre-warm lazy-details cache for each member when full data is available.
        // Covers both regular and search loads so SSE calls on subsequent renders hit cache.
        if (!$lazy && !empty($result['members'])) {
            $cacheService = $this->cacheService();
            $gen = $cacheService->getMembershipGeneration($membershipUuid);
            foreach ($result['members'] as $member) {
                $personUuid = $member['person_uuid'] ?? '';
                if ($personUuid !== '') {
                    $lazyCacheKey = 'orgman_lazy_details_' . md5($personUuid . $orgUuid . $membershipUuid . $gen);
                    $cacheService->set($lazyCacheKey, $member);
                }
            }
        }

        return $result;
    }

    /**
     * Search members with pagination support.
     *
     * @param string $membershipUuid Organization membership identifier.
     * @param string $orgUuid        Organization identifier.
     * @param string $search         Search term.
     * @param array  $args           Optional arguments (page, size).
     * @return array
     */
    public function searchMembers(string $membershipUuid, string $orgUuid, string $search, array $args = []): array
    {
        $args['query'] = $search;

        return $this->getMembers($membershipUuid, $orgUuid, $args);
    }

    /**
     * Fetch a single member's full data by person UUID via direct API query.
     * Used by the lazy-load SSE endpoint to avoid text-search limitations.
     *
     * @param string $personUuid     Person UUID to look up.
     * @param string $membershipUuid Organization membership UUID.
     * @param string $orgUuid        Organization UUID.
     * @return array|null Normalized member array, or null if not found.
     */
    public function getMemberByPersonUuid(string $personUuid, string $membershipUuid, string $orgUuid): ?array
    {
        if (!function_exists('wicket_api_client')) {
            return null;
        }

        try {
            $client = wicket_api_client();

            // Use the same nested endpoint pattern as getMembershipMembers()
            // GET /organization_memberships/{membershipUuid}/person_memberships
            $endpoint = '/organization_memberships/' . rawurlencode($membershipUuid) . '/person_memberships';
            $queryParams = [
                'page[number]' => 1,
                'page[size]'   => 100,
                'filter[active_at]' => 'now',
                'include'      => 'person,membership',
            ];

            $response = $client->get($endpoint . '?' . http_build_query($queryParams));
        } catch (\Throwable $e) {
            \Wicket()->log()->error(
                'getMemberByPersonUuid API call failed',
                [
                    'source'          => 'wicket-orgman',
                    'person_uuid'     => $personUuid,
                    'membership_uuid' => $membershipUuid,
                    'org_uuid'        => $orgUuid,
                    'error'           => $e->getMessage(),
                ]
            );

            return null;
        }

        if (!is_array($response) || empty($response['data'])) {
            \Wicket()->log()->info('getMemberByPersonUuid: No data returned from API', [
                'source'          => 'wicket-orgman',
                'person_uuid'     => $personUuid,
                'membership_uuid' => $membershipUuid,
                'org_uuid'        => $orgUuid,
                'response_keys'   => is_array($response) ? array_keys($response) : 'not_array',
            ]);

            return null;
        }

        // Filter results to find the matching person by person_id
        $members = $response['data'] ?? [];
        $matched_member = null;

        foreach ($members as $member) {
            $person_id = $member['relationships']['person']['data']['id'] ?? null;
            if ($person_id === $personUuid) {
                $matched_member = $member;
                break;
            }
        }

        if (!$matched_member) {
            \Wicket()->log()->info('getMemberByPersonUuid: No matching person found', [
                'source'          => 'wicket-orgman',
                'person_uuid'     => $personUuid,
                'membership_uuid' => $membershipUuid,
                'org_uuid'        => $orgUuid,
                'total_results'   => count($members),
            ]);

            return null;
        }

        // Reconstruct response with just the matched member
        $filtered_response = $response;
        $filtered_response['data'] = [$matched_member];

        \Wicket()->log()->info('getMemberByPersonUuid: Response structure', [
            'source' => 'wicket-orgman',
            'person_uuid' => $personUuid,
            'has_included' => isset($response['included']),
            'included_count' => isset($response['included']) ? count($response['included']) : 0,
            'included_types' => isset($response['included']) ? array_map(fn ($item) => $item['type'] ?? 'unknown', $response['included']) : [],
        ]);

        $result = $this->prepareMembersResult(
            $filtered_response,
            [
                'org_uuid'        => $orgUuid,
                'membership_uuid' => $membershipUuid,
                'page'            => 1,
                'size'            => 1,
                'query'           => '',
                'lazy'            => false,
            ]
        );

        return $result['members'][0] ?? null;
    }

    /**
     * Structure the members response for templates and partials.
     *
     * @param array|null $membersResponse Raw response from API/helpers.
     * @param array      $context         Context values: org_uuid, membership_uuid, page, size, query.
     * @return array
     */
    public function prepareMembersResult(?array $membersResponse, array $context): array
    {
        $logger = \Wicket()->log();

        $page = max(1, (int) ($context['page'] ?? 1));
        $size = max(1, (int) ($context['size'] ?? 15));
        $query = isset($context['query']) ? (string) $context['query'] : '';
        $orgUuid = (string) ($context['org_uuid'] ?? '');
        $membershipUuid = $context['membership_uuid'] ?? null;
        $isLazy = (bool) ($context['lazy'] ?? false);

        $rawMembers = [];
        if (is_array($membersResponse)) {
            if (isset($membersResponse['data']) && is_array($membersResponse['data'])) {
                $rawMembers = $membersResponse['data'];
            } elseif (isset($membersResponse[0])) {
                $rawMembers = $membersResponse;
            }
        }

        // Convert any stdClass objects in rawMembers to arrays
        $rawMembers = array_map(static function ($member) {
            if (is_object($member) && !is_array($member)) {
                return json_decode(json_encode($member), true);
            }

            return $member;
        }, $rawMembers);

        $ownerId = null;
        if (!empty($membershipUuid)) {
            try {
                $membershipData = $this->membershipService()->getOrgMembershipData((string) $membershipUuid);
                if (is_array($membershipData)) {
                    $ownerId = $membershipData['data']['relationships']['owner']['data']['id'] ?? null;
                }
            } catch (\Throwable $e) {
                $logger->warning(
                    'Failed to resolve membership owner: ' . $e->getMessage(),
                    [
                        'source'          => 'wicket-orgman',
                        'membership_uuid' => $membershipUuid,
                    ]
                );
            }
        }

        $peopleIndex = [];
        if (is_array($membersResponse) && isset($membersResponse['included']) && is_array($membersResponse['included'])) {
            foreach ($membersResponse['included'] as $included) {
                // Convert stdClass objects to arrays
                if (is_object($included) && !is_array($included)) {
                    $included = json_decode(json_encode($included), true);
                }

                $type = $included['type'] ?? '';
                $id = $included['id'] ?? '';

                if ($type === 'people' && $id !== '') {
                    $peopleIndex[$id] = $included;
                }
            }
        }

        $allowedTypes = $this->normalizeRelationshipTypeList((array) ($this->config['relationships']['filters']['allowlist'] ?? []));
        $excludedTypes = $this->normalizeRelationshipTypeList((array) ($this->config['relationships']['filters']['denylist'] ?? []));
        $displayRoleAllowlist = $this->normalizeRoleList((array) ($this->config['presentation']['member_list']['display_roles']['allowlist'] ?? []));
        $displayRoleExcludes = $this->normalizeRoleList((array) ($this->config['presentation']['member_list']['display_roles']['denylist'] ?? []));
        $relationshipTypeLabels = (array) ($this->config['relationships']['labels']['custom'] ?? []);

        $members = [];
        $membersWithoutPerson = [];

        $loopCounter = 0;
        $loopContinue = 0;
        $loopSuccess = 0;

        // Pre-fetch connections and roles for all unique people to avoid N+1 calls inside the loop.
        $connectionsByPerson = [];
        $rolesByPerson = [];
        if (!$isLazy && !empty($rawMembers) && !empty($orgUuid) && function_exists('wicket_api_client')) {
            $uniquePersonIds = [];
            foreach ($rawMembers as $member) {
                $personId = $member['relationships']['person']['data']['id']
                    ?? $member['person']['id']
                    ?? null;
                if ($personId && !in_array($personId, $uniquePersonIds, true)) {
                    $uniquePersonIds[] = $personId;
                }
            }

            if (!empty($uniquePersonIds)) {
                $client = wicket_api_client();
                foreach ($uniquePersonIds as $personId) {
                    // Fetch connections
                    try {
                        $endpoint = 'people/' . rawurlencode((string) $personId) . '/connections';
                        $params = [
                            'filter[connection_type_eq]' => 'all',
                            'sort' => '-created_at',
                        ];
                        $response = $client->get($endpoint, $params);

                        if (is_array($response) && isset($response['data']) && is_array($response['data'])) {
                            // Filter connections to this organization only
                            $orgConnections = array_filter($response['data'], static function ($conn) use ($orgUuid) {
                                $connOrgId = $conn['relationships']['organization']['data']['id'] ?? '';

                                return trim((string) $connOrgId) === trim((string) $orgUuid);
                            });

                            if (!empty($orgConnections)) {
                                $connectionsByPerson[$personId] = array_values($orgConnections);
                            } else {
                                $connectionsByPerson[$personId] = [];
                            }
                        }
                    } catch (\Throwable $e) {
                        $logger->warning('Failed to pre-fetch connections', ['person_id' => $personId, 'error' => $e->getMessage()]);
                    }

                    // Fetch roles for MDP permission roles merging
                    try {
                        $roleParams = [
                            'page' => ['number' => 1, 'size' => 100],
                            'include' => 'resource',
                            'sort' => '-global,name',
                        ];
                        $roleResponse = $client->get('/people/' . rawurlencode((string) $personId) . '/roles', $roleParams);

                        if (is_array($roleResponse) && isset($roleResponse['data']) && is_array($roleResponse['data'])) {
                            $orgRoles = [];
                            foreach ($roleResponse['data'] as $role) {
                                $resourceId = $role['relationships']['resource']['data']['id'] ?? '';
                                if ((string) $resourceId === (string) $orgUuid) {
                                    $roleName = $role['attributes']['name'] ?? '';
                                    if ($roleName !== '') {
                                        $orgRoles[] = $roleName;
                                    }
                                }
                            }
                            $rolesByPerson[$personId] = $orgRoles;
                        }
                    } catch (\Throwable $e) {
                        $logger->warning('Failed to pre-fetch roles', ['person_id' => $personId, 'error' => $e->getMessage()]);
                    }
                }
            }
        }

        foreach ($rawMembers as $idx => $member) {
            $loopCounter++;

            // Convert stdClass objects to arrays
            if (is_object($member) && !is_array($member)) {
                $member = json_decode(json_encode($member), true);
            }

            if (!is_array($member)) {
                $logger->debug('Skipping member: not an array', [
                    'source' => 'wicket-orgman',
                    'index' => $idx,
                    'member_type' => gettype($member),
                ]);
                $loopContinue++;
                continue;
            }

            $memberAttributes = $member['attributes'] ?? [];

            $personUuid = $member['relationships']['person']['data']['id']
                ?? $member['person']['id']
                ?? null;

            $personData = ($personUuid && isset($peopleIndex[$personUuid])) ? $peopleIndex[$personUuid] : null;
            $personAttributes = $personData['attributes'] ?? [];

            if (!$personData && $personUuid) {
                try {
                    $person = $this->getPersonById($personUuid);
                    if (is_array($person) && isset($person['data']['attributes'])) {
                        $personData = $person;
                        $personAttributes = $person['data']['attributes'];
                    }
                } catch (\Throwable $e) {
                    $logger->warning(
                        'Failed to fetch person by id',
                        [
                            'source'    => 'wicket-orgman',
                            'person_id' => $personUuid,
                            'error'     => $e->getMessage(),
                        ]
                    );
                }
            }

            $currentRolesList = [];
            if ($personUuid) {
                try {
                    // Use pre-fetched roles if available (non-lazy only), fallback for safety
                    if (!$isLazy && isset($rolesByPerson[$personUuid])) {
                        $rawRoles = $rolesByPerson[$personUuid];
                        $rolesList = $this->normalizeRoleList($rawRoles);
                    } else {
                        $rolesList = $this->getPersonCurrentRolesByOrgId($personUuid, $orgUuid);
                    }

                    if (is_array($rolesList)) {
                        $currentRolesList = array_values(array_filter(array_map('strval', $rolesList)));
                    }
                } catch (\Throwable $e) {
                    $logger->warning(
                        'Failed to process person current roles',
                        [
                            'source'    => 'wicket-orgman',
                            'person_id' => $personUuid,
                            'org_id'    => $orgUuid,
                            'error'     => $e->getMessage(),
                        ]
                    );
                }
            }
            $currentRolesList = $this->filterDisplayRoles($currentRolesList, $displayRoleAllowlist, $displayRoleExcludes);

            $firstName = $personAttributes['given_name']
                ?? $personAttributes['first_name']
                ?? $memberAttributes['person_first_name']
                ?? $memberAttributes['first_name']
                ?? '';

            $lastName = $personAttributes['family_name']
                ?? $personAttributes['last_name']
                ?? $memberAttributes['person_last_name']
                ?? $memberAttributes['last_name']
                ?? '';

            $email = $personAttributes['primary_email_address']
                ?? $personAttributes['email']
                ?? $memberAttributes['person_email']
                ?? $memberAttributes['email']
                ?? '';

            $title = $personAttributes['job_title']
                ?? $memberAttributes['person_title']
                ?? $memberAttributes['title']
                ?? '';

            $roles = [];
            if (!empty($memberAttributes['roles']) && is_array($memberAttributes['roles'])) {
                $roles = array_filter(array_map('strval', $memberAttributes['roles']));
            } elseif (!empty($memberAttributes['type'])) {
                $roles = [str_replace('_', ' ', (string) $memberAttributes['type'])];
            }
            $roles = $this->filterDisplayRoles($roles, $displayRoleAllowlist, $displayRoleExcludes);

            $relationshipSlugs = [];
            $relationshipNamesBySlug = [];
            $relationshipDescription = null;
            $personConnectionIds = []; // Store all connection IDs for this organization
            if ($personUuid && !$isLazy) {
                try {
                    // Use pre-fetched data if available, fallback for safety
                    if (isset($connectionsByPerson[$personUuid])) {
                        $connectionsData = $connectionsByPerson[$personUuid];
                    } else {
                        $connections = $this->connectionService()->getPersonConnectionsById($personUuid);
                        $connectionsData = $connections['data'] ?? [];
                    }

                    $activeOnlyConnections = (bool) ($this->config['relationships']['display']['member_card_active_only'] ?? false);
                    if (is_array($connectionsData) && !empty($connectionsData)) {
                        foreach ($connectionsData as $conn) {
                            $orgId = $conn['relationships']['organization']['data']['id'] ?? null;
                            if (trim((string) $orgId) !== trim((string) $orgUuid)) {
                                continue;
                            }

                            if ($activeOnlyConnections) {
                                $isConnectionActive = (bool) ($conn['attributes']['active'] ?? false);
                                if (!$isConnectionActive) {
                                    continue;
                                }
                            }

                            $rawRelationshipType = (string) ($conn['attributes']['type'] ?? '');
                            $slug = $this->normalizeRelationshipType($rawRelationshipType);
                            $connId = $conn['attributes']['uuid'] ?? null;

                            if ($slug !== '') {
                                $relationshipSlugs[] = $slug;
                                if (!isset($relationshipNamesBySlug[$slug])) {
                                    $relationshipNamesBySlug[$slug] = $this->resolveRelationshipLabel($slug, $relationshipTypeLabels);
                                }
                            }

                            if ($relationshipDescription === null) {
                                $connDescription = $conn['attributes']['description'] ?? null;
                                if (is_string($connDescription) && $connDescription !== '') {
                                    $relationshipDescription = $connDescription;
                                }
                            }

                            // Collect all connection IDs for this organization (like legacy system)
                            if ($connId) {
                                $personConnectionIds[] = $connId;
                            }
                        }

                        $relationshipSlugs = array_values(array_unique($relationshipSlugs));
                        if (in_array('primary_contact', $relationshipSlugs, true)) {
                            $relationshipSlugs = array_values(array_diff($relationshipSlugs, ['primary_contact']));
                            array_unshift($relationshipSlugs, 'primary_contact');
                        }
                    }
                } catch (\Throwable $e) {
                    $logger->warning(
                        'Failed to process person connections',
                        [
                            'source'    => 'wicket-orgman',
                            'person_id' => $personUuid,
                            'error'     => $e->getMessage(),
                        ]
                    );
                }
            }

            if (!empty($allowedTypes)) {
                $relationshipSlugs = array_values(array_filter($relationshipSlugs, static function ($slug) use ($allowedTypes): bool {
                    return in_array($slug, $allowedTypes, true);
                }));
            }

            if (!empty($excludedTypes)) {
                $relationshipSlugs = array_values(array_filter($relationshipSlugs, static function ($slug) use ($excludedTypes): bool {
                    return !in_array($slug, $excludedTypes, true);
                }));
            }

            if (!$isLazy && (!empty($allowedTypes) || !empty($excludedTypes)) && empty($relationshipSlugs)) {
                $logger->debug('Skipping member due to relationship filter', [
                    'source' => 'wicket-orgman',
                    'person_uuid' => $personUuid,
                    'isLazy' => $isLazy,
                    'allowedTypes' => $allowedTypes,
                    'excludedTypes' => $excludedTypes,
                    'relationshipSlugs' => $relationshipSlugs,
                ]);
                continue;
            }

            $relationshipNames = [];
            foreach ($relationshipSlugs as $slug) {
                $label = $relationshipNamesBySlug[$slug] ?? $this->resolveRelationshipLabel($slug, $relationshipTypeLabels);
                if ($label !== '') {
                    $relationshipNames[] = $label;
                }
            }

            // confirmed_at is embedded in person attributes as user.confirmed_at (not a separate users resource).
            $confirmedAt = $personAttributes['user']['confirmed_at']
                ?? $personAttributes['confirmed_at']
                ?? $memberAttributes['confirmed_at']
                ?? null;

            $memberRow = [
                'person_uuid'           => $personUuid,
                'first_name'            => $firstName,
                'last_name'             => $lastName,
                'full_name'             => trim($firstName . ' ' . $lastName),
                'title'                 => $title,
                'email'                 => $email,
                'roles'                 => $roles,
                'current_roles'         => !empty($currentRolesList) ? $currentRolesList : $roles,
                'confirmed_at'          => $confirmedAt,
                'status'                => $personAttributes['status'] ?? null,
                'job_level'             => $personAttributes['job_level'] ?? null,
                'relationship_names_list' => array_values(array_unique($relationshipNames)),
                'relationship_slugs'      => array_values(array_unique($relationshipSlugs)),
                'relationship_description' => $relationshipDescription,
                'is_owner'              => (!empty($ownerId) && $personUuid && $personUuid === $ownerId),
                'person_connection_ids_list' => array_values(array_unique(array_filter(array_map('strval', $personConnectionIds)))),
                'person_membership_id'  => $member['id'] ?? null,
                'lazy_loaded'           => !$isLazy,
            ];

            $personKey = is_string($personUuid) ? trim($personUuid) : '';
            if ($personKey !== '') {
                // Add all person_membership records (no deduplication by person)
                $members[] = $this->finalizePreparedMemberRow($memberRow);
                $loopSuccess++;
            } else {
                $membersWithoutPerson[] = $memberRow;
                $loopSuccess++;
            }
        }

        // Add members without person data (if any)
        foreach ($membersWithoutPerson as $memberRow) {
            $members[] = $this->finalizePreparedMemberRow($memberRow);
        }

        $totalItems = 0;
        if (is_array($membersResponse)) {
            if (isset($membersResponse['meta']['page']['total_items'])) {
                $totalItems = (int) $membersResponse['meta']['page']['total_items'];
            } elseif (isset($membersResponse['meta']['total'])) {
                $totalItems = (int) $membersResponse['meta']['total'];
            }
        }

        if (0 === $totalItems) {
            $totalItems = count($members);
        }

        $totalPages = (int) max(1, ceil($totalItems / max(1, $size)));

        return [
            'members'    => $members,
            'pagination' => [
                'currentPage' => $page,
                'totalPages'  => $totalPages,
                'pageSize'    => $size,
                'totalItems'  => $totalItems,
            ],
            'org_uuid'   => $orgUuid,
            'query'      => $query,
        ];
    }

    /**
     * Normalize the membership members response into an array structure.
     *
     * @param mixed $response Raw response from helper or API client.
     * @return array|null
     */
    private function normalizeMembershipResponse($response): ?array
    {
        if (is_array($response)) {
            return $response;
        }

        $body = null;

        if ($response instanceof \Psr\Http\Message\ResponseInterface) {
            $body = (string) $response->getBody();
        } elseif (is_object($response) && method_exists($response, 'body')) {
            $body = (string) $response->body();
        } elseif (is_object($response) && method_exists($response, 'getBody')) {
            $body = (string) $response->getBody();
        } elseif (is_string($response)) {
            $body = $response;
        }

        if (null === $body) {
            return null;
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            \Wicket()->log()->info(
                'Unable to decode membership response JSON',
                [
                    'source' => 'wicket-orgman',
                    'error'  => json_last_error_msg(),
                ]
            );

            return null;
        }

        return $decoded;
    }

    /**
     * Get current roles for a person in a specific organization using MDP API.
     *
     * @param string $personUuid The person UUID
     * @param string $orgUuid The organization UUID
     * @return array Array of role names
     */
    public function getPersonCurrentRolesByOrgId($personUuid, $orgUuid)
    {
        if (!empty($personUuid) && !empty($orgUuid)) {
            try {
                $permissionRoles = $this->permissionService()->getPersonCurrentRolesByOrgId((string) $personUuid, (string) $orgUuid);
                if (is_array($permissionRoles) && !empty($permissionRoles)) {
                    return $this->normalizeRoleList($permissionRoles);
                }
            } catch (\Throwable $e) {
                // Fall through to legacy endpoint request.
            }
        }

        if (!function_exists('wicket_api_client')) {
            return [];
        }

        $client = wicket_api_client();

        $response = $client->get('/people/' . $personUuid . '/roles', [
            'query' => [
                'page[number]' => 1,
                'page[size]' => 100,
                'fields[organizations][]' => 'legal_name_en',
                'fields[organizations][]' => 'legal_name_fr',
                'fields[organizations][]' => 'type',
                'include' => 'resource',
                'sort' => '-global,name',
            ],
        ]);

        if (isset($response['data'])) {
            $data = $response['data'];
            $roles = [];

            // Filter the roles based on the organization UUID
            foreach ($data as $role) {
                // Include org-specific roles
                if (
                    isset($role['relationships']['resource']['data']['id'])
                    && $role['relationships']['resource']['data']['id'] === $orgUuid
                ) {
                    $role_name = $role['attributes']['name'] ?? '';
                    if ($role_name !== '') {
                        $roles[] = $role_name;
                    }
                }
            }

            return $this->normalizeRoleList($roles);
        }

        return [];
    }

    /**
     * Get formatted roles string for display.
     *
     * @param string $personUuid The person UUID
     * @param string $orgUuid The organization UUID
     * @return string Formatted roles string
     */
    public function getFormattedRolesString($personUuid, $orgUuid)
    {
        $roles = $this->getPersonCurrentRolesByOrgId($personUuid, $orgUuid);

        if (empty($roles)) {
            return '';
        }

        return implode(', ', $roles);
    }

    /**
     * Get person data by UUID.
     *
     * @param string $personUuid The person UUID.
     * @return array|null
     */
    public function getPersonById($personUuid)
    {
        if (!function_exists('wicket_api_client')) {
            return null;
        }

        $client = wicket_api_client();

        try {
            $response = $client->get('/people/' . $personUuid);

            return $response;
        } catch (\Exception $e) {
            // Log error if needed
            return null;
        }
    }
}
