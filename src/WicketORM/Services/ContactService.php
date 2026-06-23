<?php

/**
 * Contact Service for the ESCRCS Contacts Roster.
 *
 * Manages relationship-based contacts (not membership-based members).
 * New service. Does not modify any existing service.
 */

declare(strict_types=1);

namespace WicketORM\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class ContactService
{
    private array $config;

    private ConnectionService $connectionService;

    private PermissionService $permissionService;

    public function __construct()
    {
        $this->config = ConfigService::getConfig();
        $this->connectionService = new ConnectionService();
        $this->permissionService = new PermissionService();
    }

    /**
     * Get contacts for an org (people with matching relationship types).
     *
     * Returns paginated results with relationship types grouped per person.
     *
     * @param string $org_uuid Organization UUID.
     * @param array  $params   Optional: ['page' => 1, 'size' => 10, 'query' => ''].
     * @return array ['contacts' => [...], 'pagination' => [...]]
     */
    public function getContacts(string $org_uuid, array $params = []): array
    {
        $contacts_config = $this->config['contacts'] ?? [];
        $roster_types = $contacts_config['relationship_types']['roster'] ?? [];
        $page_size = (int) ($contacts_config['presentation']['page_size'] ?? 10);

        if (empty($roster_types)) {
            return ['contacts' => [], 'pagination' => $this->emptyPagination()];
        }

        $page = max(1, (int) ($params['page'] ?? 1));
        $size = max(1, (int) ($params['size'] ?? $page_size));
        $query = sanitize_text_field((string) ($params['query'] ?? ''));

        // Check cache
        $cache_enabled = (bool) ($this->config['platform']['cache']['enabled'] ?? true);
        $cache_duration = (int) ($this->config['platform']['cache']['duration'] ?? 300);
        $cache_key = 'orgman_contacts_' . md5($org_uuid . '_' . $page_size);

        if ($cache_enabled && $query === '' && $page === 1) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = $this->connectionService->getOrgConnections($org_uuid, [
            'resource_type_slugs' => $roster_types,
            'active'              => true,
        ], [
            'page' => $page,
            'size' => $size,
        ]);

        if (is_wp_error($result)) {
            return ['contacts' => [], 'pagination' => $this->emptyPagination()];
        }

        $connections = $result['data'] ?? [];
        $included_raw = $result['included'] ?? [];
        $meta = $result['meta'] ?? [];

        // Build person lookup from included resources
        $person_lookup = [];
        foreach ($included_raw as $inc) {
            $inc_type = $inc['type'] ?? '';
            $inc_id = $inc['id'] ?? '';
            if ($inc_type === 'people' && $inc_id !== '') {
                $person_lookup[$inc_id] = $inc['attributes'] ?? [];
            }
        }

        // Group connections by person
        $grouped = [];
        foreach ($connections as $conn) {
            $person_id = $conn['relationships']['person']['data']['id'] ?? '';
            if ($person_id === '') {
                continue;
            }

            $type_slug = $conn['attributes']['resource_type_slug']
                ?? $conn['attributes']['type']
                ?? '';

            // Extract person data from included lookup
            $person_attrs = $person_lookup[$person_id] ?? [];
            $person_name = trim(
                ($person_attrs['given_name'] ?? $person_attrs['first_name'] ?? '') . ' '
                . ($person_attrs['family_name'] ?? $person_attrs['last_name'] ?? '')
            );
            $person_email = (string) (
                $person_attrs['primary_email_address']
                ?? $person_attrs['email']
                ?? ''
            );

            // Apply search filter if query present
            if ($query !== '') {
                $search_haystack = strtolower(trim($person_name . ' ' . $person_email));
                if (strpos($search_haystack, strtolower($query)) === false) {
                    continue;
                }
            }

            if (!isset($grouped[$person_id])) {
                $grouped[$person_id] = [
                    'person_uuid'          => $person_id,
                    'full_name'            => $person_name,
                    'email'                => $person_email,
                    'relationship_types'   => [],
                    'connection_ids'       => [],
                ];
            }

            if ($type_slug !== '' && !in_array($type_slug, $grouped[$person_id]['relationship_types'], true)) {
                $grouped[$person_id]['relationship_types'][] = $type_slug;
            }

            $conn_id = $conn['id'] ?? '';
            if ($conn_id !== '') {
                $grouped[$person_id]['connection_ids'][] = $conn_id;
            }
        }

        // Get labels for relationship types
        $type_labels = $contacts_config['form']['relationship_type'] ?? [];
        foreach ($grouped as &$contact) {
            $contact['relationship_type_names'] = array_map(
                static fn (string $slug) => $type_labels[$slug] ?? ucwords(str_replace('_', ' ', $slug)),
                $contact['relationship_types']
            );
            $contact['relationship_type_names_csv'] = implode(', ', $contact['relationship_type_names']);
            $contact['connection_ids_csv'] = implode(',', $contact['connection_ids']);
        }
        unset($contact);

        $contacts = array_values($grouped);
        $pagination = [
            'currentPage' => (int) ($meta['page'] ?? $page),
            'totalPages'  => (int) ($meta['total_pages'] ?? 1),
            'pageSize'    => (int) ($meta['size'] ?? $size),
            'totalItems'  => (int) ($meta['total_items'] ?? 0),
        ];

        $response = ['contacts' => $contacts, 'pagination' => $pagination];

        // Cache first page without query
        if ($cache_enabled && $query === '' && $page === 1) {
            set_transient($cache_key, $response, $cache_duration);
        }

        return $response;
    }

    /**
     * Add a contact: create/find person, create relationship, assign roles.
     *
     * @param string $org_uuid     Organization UUID.
     * @param array  $contact_data ['first_name', 'last_name', 'email', 'relationship_type', 'roles' => []].
     * @param array  $context      Additional context.
     * @return array|WP_Error Result array on success, WP_Error on failure.
     */
    public function addContact(string $org_uuid, array $contact_data, array $context = []): array|WP_Error
    {
        $contacts_config = $this->config['contacts'] ?? [];
        $assign_roles = $contacts_config['on_add']['assign_roles'] ?? [];

        // Filter submitted roles against allowed
        $roles = $context['roles'] ?? [];
        $roles = \WicketORM\Helpers\PermissionHelper::filter_role_submission(
            $roles,
            $assign_roles,
            []
        );

        $relationship_type = sanitize_key((string) ($contact_data['relationship_type'] ?? ''));
        if ($relationship_type === '') {
            return new WP_Error('missing_type', 'Relationship type is required.');
        }

        // Create or find person
        $first_name = sanitize_text_field((string) ($contact_data['first_name'] ?? ''));
        $last_name = sanitize_text_field((string) ($contact_data['last_name'] ?? ''));
        $email = sanitize_email((string) ($contact_data['email'] ?? ''));

        if ($first_name === '' || $last_name === '' || $email === '') {
            return new WP_Error('missing_data', 'First name, last name, and email are required.');
        }

        if (!function_exists('wicket_create_or_get_person')) {
            return new WP_Error('missing_dependency', 'Person creation helper not available.');
        }

        $person_uuid = wicket_create_or_get_person($first_name, $last_name, $email);
        if (is_wp_error($person_uuid)) {
            return $person_uuid;
        }

        // Determine contact state
        $state = $this->getPersonContactState($person_uuid, $org_uuid, $relationship_type);
        $warnings = [];

        switch ($state) {
            case 'PERSON_LINKED_SAME_TYPE':
                // Already has this relationship type. Skip creation, still ensure roles.
                $warnings[] = __('This person already has this relationship type.', 'wicket-acc');
                break;

            case 'PERSON_FOUND_NO_LINK':
            case 'PERSON_LINKED_DIFF_TYPE':
            default:
                // Create relationship via ConnectionService
                $conn_result = $this->connectionService->ensurePersonConnection($person_uuid, $org_uuid, [
                    'type' => $relationship_type,
                ]);

                if (is_wp_error($conn_result)) {
                    return $conn_result;
                }
                break;
        }

        // Assign roles (non-fatal on failure)
        if (!empty($roles)) {
            $role_result = $this->permissionService->assignRoles($person_uuid, $roles, $org_uuid);
            if (is_wp_error($role_result)) {
                \Wicket()->log()->warning('Contact role assignment failed: ' . $role_result->get_error_message(), [
                    'source'      => 'wicket-orgman',
                    'person_uuid' => $person_uuid,
                    'org_uuid'    => $org_uuid,
                ]);
                $warnings[] = __('Contact added but role assignment failed. Please set roles manually.', 'wicket-acc');
            }
        }

        // Clear cache
        $this->clearContactsCache($org_uuid);

        return [
            'person_uuid' => $person_uuid,
            'state'       => $state,
            'warnings'    => $warnings,
        ];
    }

    /**
     * Remove a contact: end relationship(s), strip roles (with dual-roster guard).
     *
     * @param string $org_uuid    Organization UUID.
     * @param string $person_uuid Person UUID.
     * @param array  $context     ['connection_ids' => [...]].
     * @return array|WP_Error
     */
    public function removeContact(string $org_uuid, string $person_uuid, array $context = []): array|WP_Error
    {
        $contacts_config = $this->config['contacts'] ?? [];
        $strip_roles = $contacts_config['on_removal']['strip_roles'] ?? [];
        $skip_if_membership = (bool) ($contacts_config['on_removal']['skip_strip_if_has_membership'] ?? true);
        $roster_types = $contacts_config['relationship_types']['roster'] ?? [];

        // Get contact relationships for this person on this org
        $contact_relationships = $this->getPersonContactRelationships($person_uuid, $org_uuid);
        $connection_ids = $context['connection_ids'] ?? [];

        // If no specific connection IDs provided, end all contact-type relationships
        if (empty($connection_ids)) {
            foreach ($contact_relationships as $rel) {
                $conn_id = $rel['id'] ?? '';
                if ($conn_id !== '') {
                    $connection_ids[] = $conn_id;
                }
            }
        }

        // End each relationship
        $ended_any = false;
        foreach ($connection_ids as $conn_id) {
            $result = $this->connectionService->endRelationshipToday($person_uuid, $conn_id, $org_uuid);
            if (!is_wp_error($result)) {
                $ended_any = true;
            } else {
                \Wicket()->log()->error('Failed to end contact relationship: ' . $result->get_error_message(), [
                    'source'        => 'wicket-orgman',
                    'person_uuid'   => $person_uuid,
                    'connection_id' => $conn_id,
                ]);
            }
        }

        if (!$ended_any) {
            return new WP_Error('removal_failed', 'Failed to end contact relationships.');
        }

        // Dual-roster guard: check if the person being removed has active membership
        $roles_stripped = false;
        $membership_preserved = false;

        if ($skip_if_membership && $this->personHasActiveMembership($person_uuid, $org_uuid)) {
            // Person has active membership. Skip role stripping.
            $membership_preserved = true;
            \Wicket()->log()->info('Preserved roles due to active membership (dual-roster guard)', [
                'source'      => 'wicket-orgman',
                'person_uuid' => $person_uuid,
                'org_uuid'    => $org_uuid,
            ]);
        } elseif (!empty($strip_roles)) {
            $role_result = $this->permissionService->removePersonRolesFromOrg($person_uuid, $strip_roles, $org_uuid);
            $roles_stripped = !is_wp_error($role_result);

            if (is_wp_error($role_result)) {
                \Wicket()->log()->error('Failed to strip contact roles: ' . $role_result->get_error_message(), [
                    'source'      => 'wicket-orgman',
                    'person_uuid' => $person_uuid,
                    'org_uuid'    => $org_uuid,
                ]);
            }
        }

        // Clear cache
        $this->clearContactsCache($org_uuid);

        return [
            'ended'               => $ended_any,
            'roles_stripped'      => $roles_stripped,
            'membership_preserved' => $membership_preserved,
        ];
    }

    /**
     * Get the relationship state for a person on an org.
     *
     * @param string $person_uuid    Person UUID.
     * @param string $org_uuid       Organization UUID.
     * @param string $requested_type The relationship type being requested.
     * @return string PERSON_NOT_FOUND|PERSON_FOUND_NO_LINK|PERSON_LINKED_SAME_TYPE|PERSON_LINKED_DIFF_TYPE
     */
    public function getPersonContactState(string $person_uuid, string $org_uuid, string $requested_type): string
    {
        $contact_rels = $this->getPersonContactRelationships($person_uuid, $org_uuid);

        if (empty($contact_rels)) {
            return 'PERSON_FOUND_NO_LINK';
        }

        foreach ($contact_rels as $rel) {
            $type_slug = $rel['attributes']['resource_type_slug']
                ?? $rel['attributes']['type']
                ?? '';
            if ($type_slug === $requested_type) {
                return 'PERSON_LINKED_SAME_TYPE';
            }
        }

        return 'PERSON_LINKED_DIFF_TYPE';
    }

    /**
     * Get active contact-type relationships for a person on an org.
     *
     * @param string $person_uuid Person UUID.
     * @param string $org_uuid    Organization UUID.
     * @return array Connection records matching contact roster types.
     */
    public function getPersonContactRelationships(string $person_uuid, string $org_uuid): array
    {
        $contacts_config = $this->config['contacts'] ?? [];
        $roster_types = $contacts_config['relationship_types']['roster'] ?? [];

        $connections = $this->connectionService->getActivePersonOrganizationConnections($person_uuid, $org_uuid);

        if (is_wp_error($connections) || !is_array($connections)) {
            return [];
        }

        // Filter to only contact-type relationships
        $contact_connections = [];
        $data = is_array($connections['data'] ?? null) ? $connections['data'] : $connections;

        foreach ((array) $data as $conn) {
            $type_slug = $conn['attributes']['resource_type_slug']
                ?? $conn['attributes']['type']
                ?? '';
            $is_active = (bool) ($conn['attributes']['active'] ?? false);

            if ($is_active && in_array($type_slug, $roster_types, true)) {
                $contact_connections[] = $conn;
            }
        }

        return $contact_connections;
    }

    /**
     * Check if a specific person has active membership for an org.
     *
     * Unlike PermissionHelper::has_active_membership() which checks the CURRENT user,
     * this method checks an arbitrary person by UUID.
     *
     * @param string $person_uuid Person UUID to check.
     * @param string $org_uuid    Organization UUID.
     * @return bool
     */
    private function personHasActiveMembership(string $person_uuid, string $org_uuid): bool
    {
        if ($person_uuid === '' || $org_uuid === '') {
            return false;
        }

        if (!function_exists('wicket_get_person_active_memberships')) {
            return false;
        }

        try {
            $memberships = wicket_get_person_active_memberships($person_uuid);

            $included = $memberships['included'] ?? [];
            if (!is_array($included)) {
                return false;
            }

            foreach ($included as $entry) {
                $entry_org_id = $entry['relationships']['organization']['data']['id'] ?? '';
                if ($entry_org_id === $org_uuid) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            \Wicket()->log()->error('ContactService::personHasActiveMembership failed: ' . $e->getMessage(), [
                'source'      => 'wicket-orgman',
                'person_uuid' => $person_uuid,
                'org_uuid'    => $org_uuid,
            ]);
        }

        return false;
    }

    /**
     * Clear contacts cache for an organization.
     *
     * @param string $org_uuid Organization UUID.
     */
    public function clearContactsCache(string $org_uuid): void
    {
        $cache_key = 'orgman_contacts_' . md5($org_uuid);
        delete_transient($cache_key);
    }

    /**
     * Empty pagination skeleton.
     */
    private function emptyPagination(): array
    {
        return [
            'currentPage' => 1,
            'totalPages'  => 1,
            'pageSize'    => 10,
            'totalItems'  => 0,
        ];
    }
}
