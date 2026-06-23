<?php

/**
 * Organization Model for handling organization data.
 */

namespace WicketORM\Services;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles data operations for organizations.
 */
class OrganizationService
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var CacheService|null
     */
    private ?CacheService $cacheService = null;

    public function __construct()
    {
        $this->config = ConfigService::getConfig();
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
     * Resolve organization display name from available attributes.
     *
     * @param array $attributes
     * @return string
     */
    private function resolveOrgNameFromAttributes(array $attributes): string
    {
        $candidates = [
            $attributes['legal_name'] ?? null,
            $attributes['legal_name_en'] ?? null,
            $attributes['legal_name_fr'] ?? null,
            $attributes['name'] ?? null,
            $attributes['name_en'] ?? null,
            $attributes['name_fr'] ?? null,
            $attributes['alternate_name'] ?? null,
            $attributes['alternate_name_en'] ?? null,
            $attributes['alternate_name_fr'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return 'Unknown Organization';
    }

    /**
     * Normalize role values for comparisons.
     *
     * @param string $role Role name.
     * @return string
     */
    private function normalizeRoleName(string $role): string
    {
        return strtolower(str_replace(' ', '_', trim($role)));
    }

    /**
     * Get role-only management access configuration.
     *
     * @return array
     */
    private function getRoleOnlyAccessConfig(): array
    {
        $permissions = $this->config['access']['permissions'] ?? [];
        $role_only = $permissions['role_only_management_access'] ?? [];

        return is_array($role_only) ? $role_only : [];
    }

    /**
     * Determine if role-only org management access is enabled.
     *
     * @return bool
     */
    private function isRoleOnlyAccessEnabled(): bool
    {
        $role_only = $this->getRoleOnlyAccessConfig();

        return !empty($role_only['enabled']);
    }

    /**
     * Get normalized allow-list roles for role-only access.
     *
     * @return array
     */
    private function getRoleOnlyAccessAllowedRoles(): array
    {
        $role_only = $this->getRoleOnlyAccessConfig();
        $allowed_roles = $role_only['allowed_roles'] ?? [];

        if (!is_array($allowed_roles)) {
            return [];
        }

        $normalized_roles = [];
        foreach ($allowed_roles as $role) {
            $normalized = $this->normalizeRoleName((string) $role);
            if ($normalized === '') {
                continue;
            }
            $normalized_roles[$normalized] = true;
        }

        return array_keys($normalized_roles);
    }

    /**
     * Resolve organization rows from org-scoped roles when role-only access is enabled.
     *
     * @param string $person_uuid Person UUID.
     * @return array
     */
    private function getUserOrganizationsFromRoles(string $person_uuid): array
    {
        if (!$this->isRoleOnlyAccessEnabled() || empty($person_uuid) || !function_exists('wicket_api_client')) {
            return [];
        }

        $allowed_roles = $this->getRoleOnlyAccessAllowedRoles();
        if (empty($allowed_roles)) {
            return [];
        }

        $allowed_lookup = array_fill_keys($allowed_roles, true);
        $client = wicket_api_client();
        $organizations = [];
        $page_number = 1;
        $total_pages = 1;

        try {
            do {
                $response = $client->get('/people/' . rawurlencode($person_uuid) . '/roles', [
                    'page' => ['number' => $page_number, 'size' => 100],
                    'include' => 'resource',
                    'sort' => '-global,name',
                ]);

                $response_data = is_array($response['data'] ?? null) ? $response['data'] : [];
                $included_data = is_array($response['included'] ?? null) ? $response['included'] : [];
                $org_name_by_id = [];

                foreach ($included_data as $included) {
                    if (($included['type'] ?? '') !== 'organizations') {
                        continue;
                    }
                    $org_id = (string) ($included['id'] ?? '');
                    if ($org_id === '') {
                        continue;
                    }
                    $org_attrs = (array) ($included['attributes'] ?? []);
                    $org_name_by_id[$org_id] = $this->resolveOrgNameFromAttributes($org_attrs);
                }

                foreach ($response_data as $role) {
                    $role_name = $this->normalizeRoleName((string) ($role['attributes']['name'] ?? ''));
                    if ($role_name === '' || !isset($allowed_lookup[$role_name])) {
                        continue;
                    }

                    $resource = $role['relationships']['resource']['data']
                        ?? $role['relationships']['organization']['data']
                        ?? null;
                    $resource_type = strtolower((string) ($resource['type'] ?? ''));
                    $is_global_role = !empty($role['attributes']['global']);
                    if (!is_array($resource) || $is_global_role) {
                        continue;
                    }
                    if ($resource_type !== '' && !in_array($resource_type, ['organizations', 'organization'], true)) {
                        continue;
                    }

                    $org_id = (string) ($resource['id'] ?? '');
                    if ($org_id === '') {
                        continue;
                    }

                    if (!isset($organizations[$org_id])) {
                        $organizations[$org_id] = [
                            'id' => $org_id,
                            'org_name' => $org_name_by_id[$org_id] ?? 'Unknown Organization',
                            'user_role' => '',
                            'roles' => [],
                        ];
                    }

                    $organizations[$org_id]['roles'][$role_name] = true;

                    if (empty($organizations[$org_id]['org_name']) && isset($org_name_by_id[$org_id])) {
                        $organizations[$org_id]['org_name'] = $org_name_by_id[$org_id];
                    }
                }

                $page_meta = $response['meta']['page'] ?? [];
                $total_pages = max(1, (int) ($page_meta['total_pages'] ?? 1));
                $page_number++;
            } while ($page_number <= $total_pages);
        } catch (\Throwable $e) {
            \Wicket()->log()->info('Failed resolving organizations from role-only access: ' . $e->getMessage(), [
                'source' => 'wicket-orgman',
                'person_uuid' => $person_uuid,
            ]);

            return [];
        }

        foreach ($organizations as $org_id => $org_data) {
            $role_slugs = array_keys((array) ($org_data['roles'] ?? []));
            $role_labels = array_map(static function (string $role): string {
                return ucwords(str_replace('_', ' ', $role));
            }, $role_slugs);
            $organizations[$org_id]['roles'] = $role_slugs;
            $organizations[$org_id]['user_role'] = implode(', ', $role_labels);
        }

        return array_values($organizations);
    }

    /**
     * Resolve organization rows where the user is the owner of organization memberships.
     *
     * This discovers organizations where the user owns the membership itself (via the
     * owner relationship on organization_memberships), even if they have no personal
     * membership entry or org-scoped roles.
     *
     * @param string $person_uuid Person UUID.
     * @return array
     */
    private function getUserOrganizationsFromOwnership(string $person_uuid): array
    {
        if (empty($person_uuid) || !function_exists('wicket_api_client')) {
            return [];
        }

        $client = wicket_api_client();
        $organizations = [];

        try {
            // Fetch all organization memberships where user is the owner
            // Use filter on owner relationship via the people endpoint
            $page_number = 1;
            $total_pages = 1;

            do {
                // Query organization_memberships filtered by owner
                $response = $client->get('/organization_memberships', [
                    'query' => [
                        'page' => ['number' => $page_number, 'size' => 100],
                        'filter' => [
                            'owner_uuid_eq' => $person_uuid,
                        ],
                    ],
                    'include' => 'organization,owner',
                ]);

                $response_data = is_array($response['data'] ?? null) ? $response['data'] : [];
                $included_data = is_array($response['included'] ?? null) ? $response['included'] : [];

                // Build org name lookup from included data
                $org_name_by_id = [];
                foreach ($included_data as $included) {
                    if (($included['type'] ?? '') !== 'organizations') {
                        continue;
                    }
                    $org_id = (string) ($included['id'] ?? '');
                    if ($org_id === '') {
                        continue;
                    }
                    $org_attrs = (array) ($included['attributes'] ?? []);
                    $org_name_by_id[$org_id] = $this->resolveOrgNameFromAttributes($org_attrs);
                }

                // Process organization_memberships where user is the owner
                $sample_owners = [];
                foreach ($response_data as $membership) {
                    // Verify the owner relationship matches
                    $owner_id = $membership['relationships']['owner']['data']['id'] ?? '';

                    // Collect sample owner IDs for debugging (first 3)
                    if (count($sample_owners) < 3 && $owner_id !== '') {
                        $sample_owners[] = $owner_id;
                    }

                    if ($owner_id !== $person_uuid) {
                        continue;
                    }

                    // Get the organization ID
                    $org_id = $membership['relationships']['organization']['data']['id'] ?? '';
                    if ($org_id === '') {
                        continue;
                    }

                    if (!isset($organizations[$org_id])) {
                        $organizations[$org_id] = [
                            'id' => $org_id,
                            'org_name' => $org_name_by_id[$org_id] ?? 'Unknown Organization',
                            'user_role' => 'Membership Owner',
                            'roles' => [],
                        ];
                    }
                }

                $page_meta = $response['meta']['page'] ?? [];
                $total_pages = max(1, (int) ($page_meta['total_pages'] ?? 1));

                $page_number++;
            } while ($page_number <= $total_pages);
        } catch (\Throwable $e) {
            \Wicket()->log()->info('Failed resolving organizations from ownership: ' . $e->getMessage(), [
                'source' => 'wicket-orgman',
                'person_uuid' => $person_uuid,
            ]);

            return [];
        }

        return array_values($organizations);
    }

    /**
     * Get all organizations a user is associated with.
     *
     * @param string $person_uuid The UUID of the person.
     * @return array An array of organization data.
     */
    public function getUserOrganizations($person_uuid)
    {
        $user_uuid = function_exists('wicket_current_person_uuid') ? wicket_current_person_uuid() : '';
        if ('' === $user_uuid) {
            return [];
        }

        $cache_key = 'orgman_user_orgs_' . md5($user_uuid . '_' . $person_uuid);
        $cached_data = $this->cacheService()->get($cache_key);

        if (false !== $cached_data) {
            return $cached_data;
        }

        $logger = \Wicket()->log();
        $person = wicket_get_person_by_id($person_uuid);

        if (!$person) {
            $logger->error('Person not found for UUID: ' . $person_uuid, ['source' => 'wicket-orgman']);
            $error_data = ['error' => 'person_not_found'];
            $this->cacheService()->set($cache_key, $error_data);

            return $error_data;
        }

        $organizations = [];
        $membership_error = null;
        $client = wicket_api_client();
        $user_uuid = function_exists('wicket_current_person_uuid') ? wicket_current_person_uuid() : '';
        if ('' === $user_uuid) {
            return [];
        }

        $user_memberships_endpoint = "/people/{$user_uuid}/membership_entries?page[number]=1&page[size]=12&sort=-active,membership_category_weight,-ends_at&include=membership,organization_membership.organization,fusebill_subscription";

        try {
            $membership_response = $client->get($user_memberships_endpoint);

            // Extract organizations from the included data where membership type = "organization"
            $org_membership_ids = [];

            // First, collect all organization membership IDs from user's entries
            foreach (($membership_response['data'] ?? []) as $entry) {
                if (isset($entry['relationships']['organization_membership']['data']['id'])) {
                    $org_membership_ids[] = $entry['relationships']['organization_membership']['data']['id'];
                }
            }

            // Process included data to find organizations
            if (isset($membership_response['included']) && is_array($membership_response['included'])) {
                foreach ($membership_response['included'] as $included_item) {
                    // Find organization memberships that match the user's entries
                    if ($included_item['type'] === 'organization_memberships'
                        && in_array($included_item['id'], $org_membership_ids)) {

                        // Find the organization for this membership
                        if (isset($included_item['relationships']['organization']['data']['id'])) {
                            $org_id = $included_item['relationships']['organization']['data']['id'];

                            // Find the full organization data in included items
                            foreach ($membership_response['included'] as $org_item) {
                                if ($org_item['type'] === 'organizations' && $org_item['id'] === $org_id) {
                                    $org_name = $this->resolveOrgNameFromAttributes((array) ($org_item['attributes'] ?? []));

                                    $organizations[] = [
                                        'id' => $org_id,
                                        'org_name' => $org_name,
                                        'user_role' => 'Member',
                                    ];

                                    break;
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $logger->error('Error fetching membership entries: ' . $e->getMessage(), ['source' => 'wicket-orgman']);
            $membership_error = ['error' => 'api_error', 'message' => 'Unable to fetch memberships. Please try again later.'];
        }

        // Get organizations where the user is the owner of the organization membership
        $ownership_organizations = $this->getUserOrganizationsFromOwnership((string) $person_uuid);
        if (!empty($ownership_organizations)) {
            $organizations = array_merge($organizations, $ownership_organizations);
        }

        $role_organizations = $this->getUserOrganizationsFromRoles((string) $person_uuid);
        if (!empty($role_organizations)) {
            $organizations = array_merge($organizations, $role_organizations);
        }

        $organizations_by_id = [];
        foreach ($organizations as $organization) {
            $org_id = (string) ($organization['id'] ?? '');
            if ($org_id === '') {
                continue;
            }

            if (!isset($organizations_by_id[$org_id])) {
                $organizations_by_id[$org_id] = [
                    'id' => $org_id,
                    'org_name' => (string) ($organization['org_name'] ?? 'Unknown Organization'),
                    'user_role' => (string) ($organization['user_role'] ?? ''),
                    'roles' => [],
                ];
            }

            if ($organizations_by_id[$org_id]['org_name'] === 'Unknown Organization' && !empty($organization['org_name'])) {
                $organizations_by_id[$org_id]['org_name'] = (string) $organization['org_name'];
            }
            if ($organizations_by_id[$org_id]['user_role'] === '' && !empty($organization['user_role'])) {
                $organizations_by_id[$org_id]['user_role'] = (string) $organization['user_role'];
            }
            foreach ((array) ($organization['roles'] ?? []) as $role_slug) {
                $normalized_role = $this->normalizeRoleName((string) $role_slug);
                if ($normalized_role === '') {
                    continue;
                }
                $organizations_by_id[$org_id]['roles'][$normalized_role] = true;
            }
        }

        foreach ($organizations_by_id as $org_id => $organization) {
            $roles = array_keys((array) ($organization['roles'] ?? []));
            $organizations_by_id[$org_id]['roles'] = $roles;
            if ($organizations_by_id[$org_id]['user_role'] === '' && !empty($roles)) {
                $organizations_by_id[$org_id]['user_role'] = implode(', ', array_map(static function (string $role): string {
                    return ucwords(str_replace('_', ' ', $role));
                }, $roles));
            }
            if ($organizations_by_id[$org_id]['org_name'] === 'Unknown Organization' && function_exists('wicket_get_organization')) {
                $org_detail = wicket_get_organization($org_id);
                $org_attrs = is_array($org_detail['attributes'] ?? null)
                    ? $org_detail['attributes']
                    : (is_array($org_detail['data']['attributes'] ?? null) ? $org_detail['data']['attributes'] : []);
                if (!empty($org_attrs)) {
                    $organizations_by_id[$org_id]['org_name'] = $this->resolveOrgNameFromAttributes($org_attrs);
                }
            }
        }

        $organizations = array_values($organizations_by_id);
        if ($membership_error !== null && empty($organizations)) {
            $this->cacheService()->set($cache_key, $membership_error);

            return $membership_error;
        }

        $this->cacheService()->set($cache_key, $organizations);

        return $organizations;
    }

    /**
     * Filter organizations to only show those with active memberships OR active roles.
     *
     * @param array $organizations List of organizations to filter
     * @param string $user_uuid Current user identifier
     * @return array Filtered organizations with active memberships or roles
     */
    public function filterActiveOrganizations($organizations, $user_uuid)
    {
        return $organizations;
    }

    /**
     * Resolve the active membership UUID for a given organization.
     *
     * @param string $organizationUuid Organization identifier.
     * @return string|null
     */
    public function getMembershipUuid(string $organizationUuid): ?string
    {
        if (empty($organizationUuid)) {
            return null;
        }

        try {
            $membershipService = new MembershipService();

            return $membershipService->getMembershipForOrganization($organizationUuid);
        } catch (\Throwable $e) {
            \Wicket()->log()->info(
                'Failed resolving membership uuid for organization: ' . $e->getMessage(),
                [
                    'source'   => 'wicket-orgman',
                    'org_uuid' => $organizationUuid,
                ]
            );
        }

        return null;
    }

    /**
     * Get the organization owner (Primary Member).
     *
     * @param string $org_id The organization ID.
     * @return object|WP_Error The person object or WP_Error on failure.
     */
    public function getOrganizationOwner($org_id)
    {
        if (empty($org_id)) {
            return new \WP_Error('invalid_params', 'Organization ID is required.');
        }

        if (!function_exists('wicket_api_client') || !function_exists('wicket_get_person_by_id')) {
            return new \WP_Error('missing_dependency', 'Required Wicket functions are not available.');
        }

        try {
            $client = wicket_api_client();
            $org_owner_id = null;

            // Get organization memberships with owner data included
            $response = $client->get("/organizations/{$org_id}/membership_entries?sort=-ends_at&include=membership%2Cfusebill_subscription%2Cowner");

            if (!isset($response['data']) || empty($response['data'])) {
                return new \WP_Error('no_memberships', 'No memberships found for this organization.');
            }

            // Find the active membership owner
            foreach ($response['data'] as $membership) {
                if (
                    isset($membership['attributes']['active'])
                    && $membership['attributes']['active'] === true
                    && isset($membership['relationships']['owner']['data']['id'])
                ) {
                    $org_owner_id = $membership['relationships']['owner']['data']['id'];
                    break;
                }
            }

            // If no active membership found, use the most recent membership's owner
            if (!$org_owner_id && isset($response['data'][0]['relationships']['owner']['data']['id'])) {
                $org_owner_id = $response['data'][0]['relationships']['owner']['data']['id'];
            }

            if (!$org_owner_id) {
                return new \WP_Error('no_owner', 'No owner found for this organization.');
            }

            // Get the person object
            $person = wicket_get_person_by_id($org_owner_id);
            if (!$person) {
                return new \WP_Error('person_not_found', 'Owner person not found.');
            }

            return $person;

        } catch (\Exception $e) {
            \Wicket()->log()->error('OrganizationService::getOrganizationOwner() - Exception: ' . $e->getMessage(), ['source' => 'wicket-orgman']);

            return new \WP_Error('get_owner_exception', $e->getMessage());
        }
    }
}
