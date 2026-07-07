<?php

/**
 * Membership Service for Org Management.
 */

namespace WicketORM\Services;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class MembershipService
{
    /**
     * @var ConnectionService|null
     */
    private $connectionService = null;

    /**
     * @var array
     */
    private $config;

    public function __construct()
    {
        $this->config = ConfigService::getConfig();
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
     * Resolve person-membership removal anchor.
     *
     * @return string
     */
    private function getRemovalAnchor(): string
    {
        $cycle_anchor = $this->config['membership']['cycle']['removal']['end_date_anchor'] ?? null;
        if (is_string($cycle_anchor) && trim($cycle_anchor) !== '') {
            return sanitize_key($cycle_anchor);
        }

        return sanitize_key((string) ($this->config['removal']['end_date_anchor'] ?? 'action_time'));
    }

    /**
     * Retrieve all organization memberships for an organization.
     *
     * @param string $organizationUuid Organization identifier.
     * @return array<int, array>
     */
    public function getOrganizationMemberships(string $organizationUuid): array
    {
        if (empty($organizationUuid) || !function_exists('wicket_get_org_memberships')) {
            return [];
        }

        try {
            $memberships = wicket_get_org_memberships($organizationUuid);
        } catch (\Throwable $e) {
            \Wicket()->log()->error(
                'Failed fetching organization memberships: ' . $e->getMessage(),
                ['source' => 'wicket-orgman', 'org_uuid' => $organizationUuid]
            );

            return [];
        }

        return is_array($memberships) ? $memberships : [];
    }

    /**
     * Resolve the active (or fallback) organization membership UUID for an organization.
     *
     * @param string $organizationUuid Organization identifier.
     * @return string|null
     */
    public function getOrganizationMembershipUuid(string $organizationUuid): ?string
    {
        $memberships = $this->getOrganizationMemberships($organizationUuid);
        if (empty($memberships)) {
            return null;
        }

        $preferCurrentCycle = (bool) ($this->config['membership']['resolution']['prefer_current_cycle'] ?? false);
        if ($preferCurrentCycle) {
            $currentCycleUuid = $this->resolveCurrentCycleMembershipUuid($memberships);
            if ($currentCycleUuid !== null) {
                return $currentCycleUuid;
            }
        }

        $fallback = null;
        foreach ($memberships as $membership) {
            $uuid = $membership['membership']['attributes']['uuid']
                ?? $membership['membership']['id']
                ?? null;

            if (empty($uuid)) {
                continue;
            }

            $isActive = (bool) ($membership['membership']['attributes']['active'] ?? false);
            $inGrace = (bool) ($membership['membership']['attributes']['in_grace'] ?? false);

            if ($isActive || $inGrace) {
                return $uuid;
            }

            if (null === $fallback) {
                $fallback = $uuid;
            }
        }

        return $fallback;
    }

    /**
     * Resolve organization membership UUID for the current cycle when feature-flagged.
     *
     * @param array<int,array> $memberships
     * @return string|null
     */
    private function resolveCurrentCycleMembershipUuid(array $memberships): ?string
    {
        $now = new \DateTimeImmutable('now', wp_timezone());
        $activeCurrent = [];
        $activeNonCurrent = [];
        $fallback = [];

        foreach ($memberships as $membership) {
            $attrs = (array) ($membership['membership']['attributes'] ?? []);
            $uuid = $attrs['uuid'] ?? ($membership['membership']['id'] ?? null);
            if (!is_string($uuid) || trim($uuid) === '') {
                continue;
            }

            $row = [
                'uuid' => trim($uuid),
                'starts_at' => (string) ($attrs['starts_at'] ?? ''),
                'ends_at' => (string) ($attrs['ends_at'] ?? ''),
            ];

            $isActive = (bool) ($attrs['active'] ?? false);
            $inGrace = (bool) ($attrs['in_grace'] ?? false);
            $inCurrentCycle = $this->isMembershipInCurrentCycle($row['starts_at'], $row['ends_at'], $now);

            if ($isActive || $inGrace) {
                if ($inCurrentCycle) {
                    $activeCurrent[] = $row;
                } else {
                    $activeNonCurrent[] = $row;
                }
            } else {
                $fallback[] = $row;
            }
        }

        $sortByStartDesc = static function (array $a, array $b): int {
            $aTs = strtotime($a['starts_at'] ?? '') ?: PHP_INT_MIN;
            $bTs = strtotime($b['starts_at'] ?? '') ?: PHP_INT_MIN;
            if ($aTs === $bTs) {
                return 0;
            }

            return ($aTs > $bTs) ? -1 : 1;
        };

        if (!empty($activeCurrent)) {
            usort($activeCurrent, $sortByStartDesc);

            return (string) $activeCurrent[0]['uuid'];
        }

        if (!empty($activeNonCurrent)) {
            usort($activeNonCurrent, $sortByStartDesc);

            return (string) $activeNonCurrent[0]['uuid'];
        }

        if (!empty($fallback)) {
            usort($fallback, $sortByStartDesc);

            return (string) $fallback[0]['uuid'];
        }

        return null;
    }

    /**
     * Check if a membership date window includes the current date.
     *
     * @param string $startsAt
     * @param string $endsAt
     * @param \DateTimeImmutable $now
     * @return bool
     */
    private function isMembershipInCurrentCycle(string $startsAt, string $endsAt, \DateTimeImmutable $now): bool
    {
        $start = null;
        $end = null;

        if ($startsAt !== '') {
            try {
                $start = new \DateTimeImmutable($startsAt);
            } catch (\Throwable $e) {
                $start = null;
            }
        }

        if ($endsAt !== '') {
            try {
                $end = new \DateTimeImmutable($endsAt);
            } catch (\Throwable $e) {
                $end = null;
            }
        }

        if ($start !== null && $start > $now) {
            return false;
        }

        if ($end !== null && $end < $now) {
            return false;
        }

        return true;
    }

    /**
     * Get the organization_membership UUID for the current user and an organization.
     * Prefers organization memberships when available; falls back to user-bound memberships.
     *
     * @param string $organizationUuid
     * @return string|null
     */
    public function getMembershipForOrganization(string $organizationUuid): ?string
    {
        $current_user_uuid = function_exists('wicket_current_person_uuid') ? wicket_current_person_uuid() : '';
        if ('' === $current_user_uuid) {
            return null;
        }

        $cache = new CacheService();
        $cache_key = 'orgman_membership_' . md5($current_user_uuid . '_' . $organizationUuid);
        $cached_data = $cache->get($cache_key);

        if (false !== $cached_data) {
            return $cached_data;
        }

        $resolved = $this->getOrganizationMembershipUuid($organizationUuid);
        if ($resolved) {
            $cache->set($cache_key, $resolved);

            return $resolved;
        }

        if (empty($organizationUuid) || !function_exists('wicket_get_current_person_memberships')) {
            $cache->set($cache_key, null);

            return null;
        }

        $memberships = wicket_get_current_person_memberships();
        if (empty($memberships['included']) || !is_array($memberships['included'])) {
            $cache->set($cache_key, null);

            return null;
        }

        $fallback = null;
        foreach ($memberships['included'] as $included) {
            if (
                isset($included['type']) && $included['type'] === 'organization_memberships'
                && isset($included['relationships']['organization']['data']['id'])
                && $included['relationships']['organization']['data']['id'] === $organizationUuid
            ) {
                $isActive = $included['attributes']['active'] ?? null;
                if ($isActive) {
                    $cache->set($cache_key, $included['id']);

                    return $included['id'];
                }
                if (!$fallback) {
                    $fallback = $included['id'];
                }
            }
        }

        $cache->set($cache_key, $fallback);

        return $fallback;
    }

    /**
     * Fetch organization membership details including the membership entity.
     *
     * @param string $membershipUuid
     * @return array|null
     */
    public function getOrgMembershipData(string $membershipUuid): ?array
    {
        if (empty($membershipUuid)) {
            return null;
        }

        $cache = new CacheService();
        $cache_key = 'orgman_membership_data_' . md5($membershipUuid);
        $cached_data = $cache->get($cache_key);

        if (false !== $cached_data) {
            return $cached_data;
        }

        if (!function_exists('wicket_api_client')) {
            $cache->set($cache_key, null);

            return null;
        }

        try {
            $client = wicket_api_client();
            $endpoint = '/organization_memberships/' . rawurlencode($membershipUuid) . '?page[number]=1&sort=&include=membership%2Cowner';
            $response = $client->get($endpoint);
            $data = isset($response['data']) ? $response : null;

            $cache->set($cache_key, $data);

            return $data;
        } catch (\Throwable $e) {
            $cache->set($cache_key, null);

            return null;
        }
    }

    /**
     * Resolve effective max seat assignments for an organization membership.
     * Uses configured tier mapping when available, otherwise falls back to API max_assignments.
     *
     * @param array|null $membershipData Organization membership payload.
     * @return int|null
     */
    public function getEffectiveMaxAssignments(?array $membershipData): ?int
    {
        if (!is_array($membershipData)) {
            return null;
        }

        $mapped = $this->getTierMappedMaxAssignments($membershipData);
        if (null !== $mapped) {
            return $mapped;
        }

        if (!isset($membershipData['data']['attributes']['max_assignments'])) {
            return null;
        }

        return (int) $membershipData['data']['attributes']['max_assignments'];
    }

    /**
     * Resolve membership tier label from org membership payload.
     *
     * @param array|null $membershipData Organization membership payload.
     * @return string
     */
    public function getMembershipTierName(?array $membershipData): string
    {
        if (!is_array($membershipData)) {
            return '';
        }

        $attributeCandidates = [
            $membershipData['data']['attributes']['membership_tier'] ?? null,
            $membershipData['data']['attributes']['membership_tier_name'] ?? null,
            $membershipData['data']['attributes']['membership_name'] ?? null,
            $membershipData['data']['attributes']['name'] ?? null,
        ];

        foreach ($attributeCandidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        $membershipRelationshipId = $membershipData['data']['relationships']['membership']['data']['id'] ?? null;
        $included = $membershipData['included'] ?? null;
        if (!is_array($included)) {
            return '';
        }

        $firstMembershipName = '';
        foreach ($included as $item) {
            if (($item['type'] ?? '') !== 'memberships') {
                continue;
            }

            $name = $item['attributes']['name'] ?? $item['attributes']['name_en'] ?? '';
            if (!is_string($name) || trim($name) === '') {
                continue;
            }

            $trimmedName = trim($name);
            if ($firstMembershipName === '') {
                $firstMembershipName = $trimmedName;
            }

            if ($membershipRelationshipId !== null && (string) ($item['id'] ?? '') === (string) $membershipRelationshipId) {
                return $trimmedName;
            }
        }

        return $firstMembershipName;
    }

    /**
     * Resolve the membership tier slug for an organization membership.
     *
     * The tier slug is the slug of the related 'memberships' (tier) resource, retrieved via the
     * organization_memberships include=membership payload. Used by the multi-tier additional
     * seats flow to drive form conditional logic and per-tier WooCommerce product resolution.
     *
     * Resolution order:
     *   1. 'included' membership whose id matches data.relationships.membership.data.id
     *      (type 'memberships') -> attributes.slug.
     *   2. First 'memberships' resource in 'included' -> attributes.slug (when the relationship
     *      pointer is absent but a tier resource is present).
     *   3. Direct attributes on the org membership (membership_tier_slug / slug) as a defensive
     *      fallback if the API ever exposes the slug inline.
     *
     * Results are memoized per (org_uuid, membership_id) for the request.
     *
     * @param string $organization_uuid Organization UUID (contextual; used for cache keying).
     * @param string $membership_id     Organization membership UUID.
     * @return string Tier slug, or '' when it cannot be resolved.
     */
    public function getMembershipTierSlug(string $organization_uuid, string $membership_id): string
    {
        $membership_id = trim($membership_id);
        if ($membership_id === '') {
            return '';
        }

        static $cache = [];
        $cache_key = $organization_uuid . '|' . $membership_id;
        if (array_key_exists($cache_key, $cache)) {
            return $cache[$cache_key];
        }

        $cache[$cache_key] = '';

        $membership_data = $this->getOrgMembershipData($membership_id);
        if (!is_array($membership_data)) {
            return '';
        }

        $cache[$cache_key] = $this->extractTierSlugFromOrgMembership($membership_data);

        return $cache[$cache_key];
    }

    /**
     * Extract the tier slug from a single organization_memberships payload (with included).
     *
     * @param array $membership_data Organization membership payload.
     * @return string
     */
    public function extractTierSlugFromOrgMembership(array $membership_data): string
    {
        $attrs = (array) ($membership_data['data']['attributes'] ?? []);

        // Defensive inline fallbacks.
        foreach (['membership_tier_slug', 'tier_slug', 'membership_slug'] as $key) {
            $candidate = $attrs[$key] ?? null;
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        $included = $membership_data['included'] ?? null;
        if (!is_array($included)) {
            return '';
        }

        $relationship_membership_id = $membership_data['data']['relationships']['membership']['data']['id'] ?? null;
        $first_slug = '';

        foreach ($included as $item) {
            if (($item['type'] ?? '') !== 'memberships') {
                continue;
            }

            $slug = $item['attributes']['slug'] ?? null;
            if (!is_string($slug) || trim($slug) === '') {
                continue;
            }

            $trimmed = trim($slug);
            if ($first_slug === '') {
                $first_slug = $trimmed;
            }

            if (
                $relationship_membership_id !== null
                && (string) ($item['id'] ?? '') === (string) $relationship_membership_id
            ) {
                return $trimmed;
            }
        }

        return $first_slug;
    }

    /**
     * Resolve tier-mapped seat limit from config.
     *
     * @param array $membershipData Organization membership payload.
     * @return int|null
     */
    private function getTierMappedMaxAssignments(array $membershipData): ?int
    {
        $tierMap = $this->config['membership']['seat_limits']['tier_max_assignments'] ?? [];
        if (!is_array($tierMap) || empty($tierMap)) {
            return null;
        }

        $tierName = $this->getMembershipTierName($membershipData);
        if ($tierName === '') {
            return null;
        }

        $caseSensitive = (bool) ($this->config['membership']['seat_limits']['tier_name_case_sensitive'] ?? false);
        if ($caseSensitive) {
            if (!array_key_exists($tierName, $tierMap)) {
                return null;
            }

            return is_numeric($tierMap[$tierName]) ? (int) $tierMap[$tierName] : null;
        }

        $normalizedTier = strtolower(trim($tierName));
        foreach ($tierMap as $mapTier => $seatLimit) {
            if (!is_string($mapTier)) {
                continue;
            }

            if (strtolower(trim($mapTier)) !== $normalizedTier) {
                continue;
            }

            return is_numeric($seatLimit) ? (int) $seatLimit : null;
        }

        return null;
    }

    /**
     * Get current person's membership UUID for a specific organization.
     *
     * This method provides backward compatibility with the legacy function signature.
     *
     * @param string $organization_uuid The organization UUID.
     * @return string|WP_Error The membership UUID or WP_Error on failure.
     */
    public function getCurrentPersonMembershipsByOrganization($organization_uuid)
    {
        if (empty($organization_uuid)) {
            return new \WP_Error('invalid_params', 'Organization UUID is required.');
        }

        try {
            $membership_uuid = $this->getMembershipForOrganization($organization_uuid);

            if (!$membership_uuid) {
                return new \WP_Error('no_membership', 'No membership found for this organization.');
            }

            return $membership_uuid;

        } catch (\Exception $e) {
            \Wicket()->log()->error('MembershipService::get_current_person_memberships_by_organization() - Exception: ' . $e->getMessage(), ['source' => 'wicket-orgman']);

            return new \WP_Error('get_membership_exception', $e->getMessage());
        }
    }

    /**
     * Search members for a specific membership.
     *
     * This method provides backward compatibility with the legacy function signature.
     * Searches for person memberships within a specific organization membership using query filters.
     *
     * @param string $membership_uuid The UUID of the membership to search within.
     * @param array $args Optional arguments containing page and size for pagination, and query for search.
     * @return array|WP_Error The search results or WP_Error on failure.
     */
    public function membershipSearchMembers($membership_uuid = '', $args = [])
    {
        if (empty($membership_uuid) || empty($args)) {
            return new \WP_Error('invalid_params', 'Membership UUID and arguments are required.');
        }

        if (!function_exists('wicket_api_client')) {
            return new \WP_Error('missing_dependency', 'Wicket API client is unavailable.');
        }

        try {
            // Defaults
            $page = absint($args['page'] ?? 1);
            $size = absint($args['size'] ?? 15);
            $query = isset($args['query']) ? sanitize_text_field($args['query']) : '';

            if (empty($query)) {
                return new \WP_Error('invalid_query', 'Search query is required.');
            }

            $client = wicket_api_client();

            // Build search filter parameters
            $filter_data = [
                'filter' => [
                    'organization_membership_uuid_in' => [$membership_uuid],
                    'person_full_name_or_person_emails_address_cont' => $query,
                    'active_at' => 'now',
                ],
            ];

            // Use POST request with proper filtering
            $response = $client->post('/person_memberships/query?' . http_build_query([
                'page[number]' => $page,
                'page[size]'   => $size,
                'include'      => 'emails,phones,addresses',
            ]), ['json' => $filter_data]);

            return isset($response['data']) ? $response : new \WP_Error('no_results', 'No search results found.');

        } catch (\Exception $e) {
            \Wicket()->log()->error('MembershipService::membership_search_members() - Exception: ' . $e->getMessage(), ['source' => 'wicket-orgman']);

            return new \WP_Error('search_failed', $e->getMessage());
        }
    }

    /**
     * Get organization membership members.
     *
     * This method provides backward compatibility with the legacy function signature.
     * Retrieves person memberships for a specific organization membership with pagination support.
     *
     * @param string $membership_uuid The UUID of the organization membership.
     * @param array $args Optional arguments containing page and size for pagination.
     * @return array|WP_Error The membership members or WP_Error on failure.
     */
    public function getOrgMembershipMembers($membership_uuid = '', $args = [])
    {
        if (empty($membership_uuid)) {
            return new \WP_Error('invalid_params', 'Membership UUID is required.');
        }

        if (!function_exists('wicket_api_client')) {
            return new \WP_Error('missing_dependency', 'Wicket API client is unavailable.');
        }

        try {
            // Defaults
            $page = absint($args['page'] ?? 1);
            $size = absint($args['size'] ?? 15);

            $client = wicket_api_client();
            $response = $client->get('/organization_memberships/' . rawurlencode($membership_uuid) . '/person_memberships?page[number]=' . $page . '&page[size]=' . $size . '&filter[active_at]=now');

            return isset($response['data']) ? $response : new \WP_Error('no_results', 'No membership members found.');

        } catch (\Exception $e) {
            \Wicket()->log()->error('MembershipService::get_org_membership_members() - Exception: ' . $e->getMessage(), ['source' => 'wicket-orgman']);

            return new \WP_Error('get_members_failed', $e->getMessage());
        }
    }

    /**
     * End a person membership with today's date.
     *
     * @param string $person_membership_id The ID of the person membership to end-date.
     * @return array|WP_Error The updated person membership data or WP_Error on failure.
     */
    public function endPersonMembershipToday($person_membership_id)
    {
        if (empty($person_membership_id)) {
            return new \WP_Error('invalid_params', 'Person membership ID is required.');
        }

        if (!function_exists('wicket_api_client')) {
            return new \WP_Error('missing_dependency', 'Wicket API client is unavailable.');
        }

        try {
            $client = wicket_api_client();

            // Get the current person membership
            $person_membership = $client->get('person_memberships/' . rawurlencode($person_membership_id));
            if (!$person_membership || empty($person_membership['data'])) {
                return new \WP_Error('person_membership_not_found', 'Person membership not found.');
            }

            // Prepare the update payload with end date set to today
            $person_membership_data = $person_membership['data'];
            $attributes = $person_membership_data['attributes'];

            $ends_at = $this->getRemovalAnchor() === 'day_start_utc'
                ? $this->currentDayStartTimestamp()
                : $this->currentTimestamp();

            $update_payload = [
                'data' => [
                    'type'       => $person_membership_data['type'],
                    'id'         => $person_membership_id,
                    'attributes' => [
                        'ends_at' => $ends_at,
                    ],
                ],
            ];

            // Update the person membership
            $response = $client->patch("person_memberships/{$person_membership_id}", ['json' => $update_payload]);

            if (!empty($response['errors'])) {
                \Wicket()->log()->error('MembershipService::endPersonMembershipToday() - API error: ' . json_encode($response['errors']), ['source' => 'wicket-orgman']);

                return new \WP_Error('api_error', 'Failed to end-date person membership: ' . ($response['errors'][0]['detail'] ?? 'Unknown error'));
            }

            \Wicket()->log()->info('[OrgMan] endPersonMembershipToday success', [
                'source' => 'wicket-orgman',
                'person_membership_id' => $person_membership_id,
                'ends_at' => $ends_at,
                'anchor' => $this->getRemovalAnchor(),
            ]);

            return $response;

        } catch (\Exception $e) {
            \Wicket()->log()->error('MembershipService::endPersonMembershipToday() - Exception: ' . $e->getMessage(), ['source' => 'wicket-orgman']);

            return new \WP_Error('end_person_membership_exception', $e->getMessage());
        }
    }

    /**
     * End every active person_membership for a person within one org_membership.
     *
     * Queries the MDP for all person_memberships the person holds under the
     * given organization membership and end-dates each active one via
     * endPersonMembershipToday(). Continue-on-error: every record is attempted
     * and per-record failures are collected, not fatal. Mirrors the authoritative
     * loop that lived inline in the remove-member modal.
     *
     * @param string $person_uuid         The UUID of the person.
     * @param string $org_membership_uuid The organization membership UUID.
     * @return array{ended: list<string>, errors: array<string,string>}
     *   ended:  person_membership IDs that were successfully end-dated
     *   errors: map of person_membership_id => error message (non-fatal)
     */
    public function endAllActivePersonMembershipsForOrg($person_uuid, $org_membership_uuid)
    {
        $ended = [];
        $errors = [];

        if (empty($person_uuid) || empty($org_membership_uuid)) {
            return ['ended' => $ended, 'errors' => $errors];
        }

        if (!function_exists('wicket_api_client')) {
            \Wicket()->log()->error('MembershipService::endAllActivePersonMembershipsForOrg() - API client unavailable', ['source' => 'wicket-orgman']);

            return ['ended' => $ended, 'errors' => $errors];
        }

        try {
            $client = wicket_api_client();
            $filter_data = [
                'filter' => [
                    'organization_membership_uuid_in' => [$org_membership_uuid],
                    'person_id_eq' => $person_uuid,
                ],
            ];

            $response = $client->post('/person_memberships/query', ['json' => $filter_data]);

            if (is_wp_error($response)) {
                \Wicket()->log()->error('MembershipService::endAllActivePersonMembershipsForOrg() - query failed', [
                    'source' => 'wicket-orgman',
                    'person_uuid' => $person_uuid,
                    'org_membership_uuid' => $org_membership_uuid,
                    'error' => $response->get_error_message(),
                ]);

                return ['ended' => $ended, 'errors' => $errors];
            }

            if (empty($response['data']) || !is_array($response['data'])) {
                return ['ended' => $ended, 'errors' => $errors];
            }

            foreach ($response['data'] as $p_membership) {
                $p_membership_id = $p_membership['id'] ?? null;
                $p_membership_active = $p_membership['attributes']['active'] ?? false;

                if (!$p_membership_id || !$p_membership_active) {
                    continue;
                }

                $result = $this->endPersonMembershipToday($p_membership_id);
                if (is_wp_error($result)) {
                    $errors[(string) $p_membership_id] = $result->get_error_message();
                    \Wicket()->log()->error('MembershipService::endAllActivePersonMembershipsForOrg() - failed to end membership', [
                        'source' => 'wicket-orgman',
                        'id' => $p_membership_id,
                        'error' => $result->get_error_message(),
                    ]);

                    continue;
                }

                $ended[] = (string) $p_membership_id;
            }
        } catch (\Exception $e) {
            \Wicket()->log()->error('MembershipService::endAllActivePersonMembershipsForOrg() - Exception: ' . $e->getMessage(), [
                'source' => 'wicket-orgman',
                'person_uuid' => $person_uuid,
                'org_membership_uuid' => $org_membership_uuid,
            ]);
        }

        \Wicket()->log()->info('[OrgMan] endAllActivePersonMembershipsForOrg result', [
            'source' => 'wicket-orgman',
            'person_uuid' => $person_uuid,
            'org_membership_uuid' => $org_membership_uuid,
            'ended_count' => count($ended),
            'ended_ids' => $ended,
            'error_count' => count($errors),
            'errors' => $errors,
        ]);

        return ['ended' => $ended, 'errors' => $errors];
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
}
