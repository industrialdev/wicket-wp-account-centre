<?php

/**
 * Permission Model for handling role data.
 */

namespace WicketORM\Services;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles data operations for member permissions.
 */
class PermissionService
{
    /**
     * Get the available roles that can be assigned.
     *
     * @return array
     */
    public function getAvailableRoles()
    {
        $roles = [];

        if (function_exists('wicket_orgman_get_available_roles')) {
            $legacy_roles = wicket_orgman_get_available_roles();
            if (is_array($legacy_roles)) {
                $roles = $legacy_roles;
            }
        }

        if (empty($roles)) {
            $roles = $this->getAvailableRolesNative();
        }

        return array_map(static function ($label) {
            return esc_html__($label, 'wicket-acc');
        }, $roles);
    }

    /**
     * Native implementation of available roles.
     * These roles are used to define permissions within the organization structure.
     *
     * @return array Associative array of role slugs and their translated labels.
     */
    private function getAvailableRolesNative(): array
    {
        $config = ConfigService::getConfig();

        return $config['access']['roles']['labels'] ?? [];
    }

    /**
     * Get person's current roles for a specific organization.
     *
     * @param string $person_uuid The UUID of the person
     * @param string $org_id The ID of the organization
     * @return array An array of current role slugs
     */
    public function getPersonCurrentRolesByOrgId($person_uuid, $org_id): array
    {
        if (empty($person_uuid) || empty($org_id) || !function_exists('wicket_api_client')) {
            return [];
        }

        try {
            $client = wicket_api_client();
            $params = [
                'page' => ['number' => 1, 'size' => 100],
                'include' => 'resource',
                'sort' => '-global,name',
            ];
            $response = $client->get('/people/' . rawurlencode($person_uuid) . '/roles', $params);

            if (!isset($response['data']) || !is_array($response['data'])) {
                return [];
            }

            $roles = [];
            foreach ($response['data'] as $role) {
                $resource = $role['relationships']['resource']['data']
                    ?? $role['relationships']['organization']['data']
                    ?? null;
                $resource_id = is_array($resource) ? (string) ($resource['id'] ?? '') : '';
                $resource_type = strtolower((string) ($resource['type'] ?? ''));
                if ($resource_id === '' || $resource_id !== $org_id) {
                    continue;
                }
                if ($resource_type !== '' && !in_array($resource_type, ['organizations', 'organization'], true)) {
                    continue;
                }
                if (!empty($role['attributes']['global'])) {
                    continue;
                }

                if ($resource_id === $org_id) {
                    $roles[] = $role['attributes']['name'] ?? '';
                }
            }

            return array_values(array_filter($roles));
        } catch (\Throwable $e) {
            \Wicket()->log()->error('Failed to get person roles: ' . $e->getMessage(), ['source' => 'wicket-orgman']);

            return [];
        }
    }

    /**
     * Remove a single role from a person within an organization.
     *
     * @param string $person_uuid The UUID of the person
     * @param string $role_name The name of the role
     * @param string $org_id The ID of the organization
     * @return bool True if successful, false otherwise
     */
    private function removePersonSingleRoleFromOrg($person_uuid, $role_name, $org_id): bool
    {
        if (empty($person_uuid) || empty($role_name) || empty($org_id)) {
            return false;
        }

        if (function_exists('wicket_remove_role')) {
            return (bool) wicket_remove_role($person_uuid, $role_name, $org_id);
        }

        if (!function_exists('wicket_api_client') || !function_exists('wicket_get_person_by_id')) {
            return false;
        }

        try {
            $client = wicket_api_client();
            $person = wicket_get_person_by_id($person_uuid);

            if (!$person) {
                return false;
            }

            $role_id = '';

            // Find the role ID from the person's included roles
            if (is_array($person) && isset($person['included']) && is_array($person['included'])) {
                foreach ($person['included'] as $included) {
                    if (isset($included['type']) && $included['type'] === 'roles'
                        && isset($included['attributes']['name']) && $included['attributes']['name'] === $role_name) {
                        $role_id = (string) $included['id'];
                        break;
                    }
                }
            } elseif (is_object($person) && method_exists($person, 'included')) {
                $included_data = $person->included();
                if (is_array($included_data)) {
                    foreach ($included_data as $included) {
                        if (isset($included['type']) && $included['type'] === 'roles'
                            && isset($included['attributes']['name']) && $included['attributes']['name'] === $role_name) {
                            $role_id = (string) $included['id'];
                            break;
                        }
                    }
                }
            }

            if (empty($role_id)) {
                return false;
            }

            // Build role payload
            $payload = [
                'data' => [
                    [
                        'type' => 'roles',
                        'id' => $role_id,
                    ],
                ],
            ];

            $client->delete("people/$person_uuid/relationships/roles", ['json' => $payload]);

            return true;
        } catch (\Throwable $e) {
            \Wicket()->log()->error('Failed to remove single role: ' . $e->getMessage(), ['source' => 'wicket-orgman']);

            return false;
        }
    }

    /**
     * Remove roles from a person within an organization.
     *
     * @param string       $person_uuid The UUID of the person.
     * @param array|string $roles The roles to remove. Can be a string or an array.
     * @param string       $org_id The organization ID.
     * @return true|\WP_Error True if successful, WP_Error if failed.
     */
    public function removePersonRolesFromOrg($person_uuid, $roles, $org_id)
    {
        if (empty($person_uuid) || empty($roles) || empty($org_id)) {
            return new \WP_Error('invalid_params', 'Person UUID, roles, and organization ID are required.');
        }

        // Normalize roles to array
        if (!is_array($roles)) {
            if (str_contains($roles, ',')) {
                $roles = explode(',', $roles);
            } else {
                $roles = [$roles];
            }
        }

        // Sanitize roles
        $roles = array_map('sanitize_key', array_filter($roles));

        if (empty($roles)) {
            return new \WP_Error('invalid_roles', 'No valid roles provided.');
        }

        // Protected roles that should never be removed
        $protected_roles = ['super_admin', 'administrator', 'user'];

        try {
            foreach ($roles as $role) {
                // Skip protected roles
                if (in_array($role, $protected_roles, true)) {
                    continue;
                }

                $result = $this->removePersonSingleRoleFromOrg($person_uuid, $role, $org_id);
                if (!$result) {
                    return new \WP_Error('role_removal_failed', sprintf('Failed removing role %s.', $role));
                }
            }

            return true;
        } catch (\Throwable $e) {
            return new \WP_Error('role_removal_exception', $e->getMessage());
        }
    }

    /**
     * Assign roles to a person within an organization.
     *
     * @param string       $person_uuid The UUID of the person.
     * @param array|string $roles The roles to assign. Can be a string or an array of roles.
     * @param string       $org_id The organization ID.
     * @return true|\WP_Error True if successful, WP_Error if failed.
     */
    public function assignRoles($person_uuid, $roles, $org_id)
    {
        if (empty($person_uuid) || empty($roles) || empty($org_id)) {
            return new \WP_Error('invalid_params', 'Person UUID, roles, and organization ID are required.');
        }

        if (!function_exists('wicket_assign_role')) {
            return new \WP_Error('missing_dependency', 'Role assignment helper is unavailable.');
        }

        // Normalize roles to array
        if (!is_array($roles)) {
            if (str_contains($roles, ',')) {
                $roles = explode(',', $roles);
            } else {
                $roles = [$roles];
            }
        }

        // Sanitize roles
        $roles = array_map('sanitize_key', array_filter($roles));

        if (empty($roles)) {
            return new \WP_Error('invalid_roles', 'No valid roles provided.');
        }

        try {
            foreach ($roles as $role) {
                $result = wicket_assign_role($person_uuid, $role, $org_id);
                if (false === $result) {
                    return new \WP_Error('role_assignment_failed', sprintf('Failed assigning role %s.', $role));
                }
            }

            return true;
        } catch (\Throwable $e) {
            return new \WP_Error('role_assignment_exception', $e->getMessage());
        }
    }

    /**
     * Update the roles for a member within an organization.
     *
     * @param string $person_uuid The UUID of the person.
     * @param string $org_id The organization ID.
     * @param array  $roles The array of role slugs to assign.
     * @return array|\WP_Error
     */
    public function updateMemberRoles($person_uuid, $org_id, $roles)
    {
        // Get current roles
        $current_roles = $this->getPersonCurrentRolesByOrgId($person_uuid, $org_id);

        // Roles to add
        $roles_to_add = array_diff($roles, $current_roles);

        // Roles to remove
        $roles_to_remove = array_diff($current_roles, $roles);

        // Assign new roles
        if (!empty($roles_to_add)) {
            $result = $this->assignRoles($person_uuid, $roles_to_add, $org_id);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        // Remove old roles
        if (!empty($roles_to_remove)) {
            $result = $this->removePersonRolesFromOrg($person_uuid, $roles_to_remove, $org_id);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        return ['status' => 'success', 'message' => 'Permissions updated successfully.'];
    }

    /**
     * Get user's role slugs for a specific organization.
     *
     * @param string $personUuid
     * @param string $orgId
     * @return array
     */
    public function getOrgRolesForPerson(string $personUuid, string $orgId): array
    {
        if (empty($personUuid) || empty($orgId) || !function_exists('wicket_api_client')) {
            return [];
        }

        try {
            $client = wicket_api_client();
            $params = [
                'page' => ['number' => 1, 'size' => 100],
                'include' => 'resource',
                'sort' => '-global,name',
            ];
            $response = $client->get('/people/' . rawurlencode($personUuid) . '/roles', $params);

            $roles = [];
            if (isset($response['data']) && is_array($response['data'])) {
                foreach ($response['data'] as $role) {
                    $resource = $role['relationships']['resource']['data']
                        ?? $role['relationships']['organization']['data']
                        ?? null;
                    $resource_id = is_array($resource) ? (string) ($resource['id'] ?? '') : '';
                    $resource_type = strtolower((string) ($resource['type'] ?? ''));

                    $role_name = $role['attributes']['name'] ?? '';
                    $is_global = !empty($role['attributes']['global']);

                    if ($resource_id === '' || $resource_id !== $orgId) {
                        continue;
                    }
                    if ($resource_type !== '' && !in_array($resource_type, ['organizations', 'organization'], true)) {
                        continue;
                    }
                    if (!empty($role['attributes']['global'])) {
                        continue;
                    }

                    if ($resource_id === $orgId) {
                        $roles[] = $role['attributes']['name'] ?? '';
                    }
                }
            }

            return array_values(array_filter($roles));
        } catch (\Throwable $e) {
            \Wicket()->log()->error('getOrgRolesForPerson: exception', [
                'source' => 'wicket-orgman',
                'personUuid' => $personUuid,
                'orgId' => $orgId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
