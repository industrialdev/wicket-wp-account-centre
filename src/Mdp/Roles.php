<?php

declare(strict_types=1);

namespace WicketAcc\Mdp;

use Exception;
use GuzzleHttp\Exception\RequestException;

// No direct access
defined('ABSPATH') || exit;

/**
 * Handles MDP Role assignments for a Person.
 */
class Roles extends Init
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Assign a role to a person.
     *
     * The lookup for role_name is case sensitive.
     * Will create the role with matching name if it doesn't exist yet.
     *
     * @param string $personUuid The UUID of the person.
     * @param string $roleName The text name of the role (e.g., "Board Member").
     * @param string $orgUuid Optional. The UUID of an organization to associate with this role assignment.
     * @return bool True on success, false on failure.
     */
    public function assignRole(string $personUuid, string $roleName, string $orgUuid = ''): bool
    {
        if (empty($personUuid) || empty($roleName)) {
            WACC()->Log()->warning('Person UUID and Role Name cannot be empty.', ['source' => __METHOD__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            // initClient() already logs the error.
            return false;
        }

        $payload = [
            'data' => [
                'type' => 'roles',
                'attributes' => [
                    'name' => $roleName,
                ],
            ],
        ];

        if (!empty($orgUuid)) {
            $payload['data']['relationships']['resource']['data']['id'] = $orgUuid;
            $payload['data']['relationships']['resource']['data']['type'] = 'organizations';
        }

        try {
            $client->post("people/{$personUuid}/roles", ['json' => $payload]);

            return true;
        } catch (RequestException $e) {
            $response_code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            $response_body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';
            WACC()->Log()->error(
                'RequestException while assigning role to person.',
                [
                    'source' => __METHOD__,
                    'person_uuid' => $personUuid,
                    'role_name' => $roleName,
                    'org_uuid' => $orgUuid,
                    'status_code' => $response_code,
                    'response_body' => $response_body,
                    'message' => $e->getMessage(),
                ]
            );
        } catch (Exception $e) {
            WACC()->Log()->error(
                'Generic Exception while assigning role to person.',
                [
                    'source' => __METHOD__,
                    'person_uuid' => $personUuid,
                    'role_name' => $roleName,
                    'org_uuid' => $orgUuid,
                    'message' => $e->getMessage(),
                ]
            );
        }

        return false;
    }

    /**
     * Remove a role from a person.
     *
     * The lookup for role_name is case sensitive.
     *
     * @param string $personUuid The UUID of the person.
     * @param string $roleName The text name of the role to remove.
     * @return bool True on success, false on failure.
     */
    public function removeRole(string $personUuid, string $roleName): bool
    {
        if (empty($personUuid) || empty($roleName)) {
            WACC()->Log()->warning('Person UUID and Role Name cannot be empty.', ['source' => __METHOD__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            return false;
        }

        // Fetch person data to find the role ID

        $person = WACC()->Mdp()->Person()->getPersonByUuid($personUuid);

        if (!$person || !isset($person->data->id)) { // Ensure person data is valid
            WACC()->Log()->warning(
                'Failed to retrieve person or person data is invalid for role removal.',
                [
                    'source' => __METHOD__,
                    'person_uuid' => $personUuid,
                    'role_name' => $roleName,
                ]
            );

            return false;
        }

        $roleId = '';
        // The SDK's fetch method might return included data differently.
        // Assuming $person->included() is the correct way based on original code.
        // If $person is the direct resource object, roles might be in $person->relationships->roles->data
        // or require a separate call if not included. This part might need adjustment based on actual SDK response.
        $includedData = $person->included();
        if (is_array($includedData)) {
            foreach ($includedData as $included) {
                if (isset($included['type'], $included['attributes']['name'], $included['id']) &&
                    $included['type'] === 'roles' && $included['attributes']['name'] === $roleName
                ) {
                    $roleId = $included['id'];
                    break;
                }
            }
        }

        if (empty($roleId)) {
            WACC()->Log()->info(
                'Role not found on person or could not be identified for removal.',
                [
                    'source' => __METHOD__,
                    'person_uuid' => $personUuid,
                    'role_name' => $roleName,
                ]
            );

            return false; // Role not found or person has no included data to check
        }

        $payload = [
            'data' => [
                [
                    'type' => 'roles',
                    'id' => $roleId,
                ],
            ],
        ];

        try {
            $client->delete("people/{$personUuid}/relationships/roles", ['json' => $payload]);

            return true;
        } catch (RequestException $e) {
            $response_code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            $response_body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';
            WACC()->Log()->error(
                'RequestException while removing role from person.',
                [
                    'source' => __METHOD__,
                    'person_uuid' => $personUuid,
                    'role_name' => $roleName,
                    'role_id' => $roleId,
                    'status_code' => $response_code,
                    'response_body' => $response_body,
                    'message' => $e->getMessage(),
                ]
            );
        } catch (Exception $e) {
            WACC()->Log()->error(
                'Generic Exception while removing role from person.',
                [
                    'source' => __METHOD__,
                    'person_uuid' => $personUuid,
                    'role_name' => $roleName,
                    'role_id' => $roleId,
                    'message' => $e->getMessage(),
                ]
            );
        }

        return false;
    }

    /**
     * Update person roles.
     *
     * @param array $args The arguments:
     *                    - 'person_current_roles': (string|array) The current roles of the person.
     *                    - 'roles': (string|array) The new role(s) to assign.
     *                    - 'org_id': (string) The organization ID.
     *                    - 'update_role_person_uuid': (string) The person UUID to update roles for.
     *                    - 'person_uuid': (string) Alternative key for 'update_role_person_uuid'.
     *                    - 'prevent_redirect': (bool) Optional. If true, prevents redirect on success. Defaults to false.
     *
     * @return array|void The response array: ['success' => bool, 'error' => bool, 'message' => string].
     *                    Void on successful redirect.
     */
    public function updateRoles(array $args)
    {
        $preventRedirect = isset($args['prevent_redirect']) ? (bool) $args['prevent_redirect'] : false;

        if (empty($args)) {
            return [
                'success' => false,
                'error'   => true,
                'message' => __('No arguments provided', 'wicket-wp-account-centre'),
            ];
        }

        // TODO: Refactor to remove dependency on global $wp.
        global $wp;

        $personCurrentRoles = $args['person_current_roles'] ?? '';
        $newRoles = $args['roles'] ?? '';
        $orgId = isset($args['org_id']) ? sanitize_text_field($args['org_id']) : '';
        $updateRolePersonUuid = isset($args['update_role_person_uuid']) ? sanitize_text_field($args['update_role_person_uuid']) : '';
        $personUuid = isset($args['person_uuid']) ? sanitize_text_field($args['person_uuid']) : '';

        if (!empty($personUuid)) {
            $updateRolePersonUuid = $personUuid;
        }

        if (is_string($personCurrentRoles) && str_contains($personCurrentRoles, ',')) {
            $personCurrentRoles = explode(',', $personCurrentRoles);
        } elseif (is_string($personCurrentRoles) && !empty($personCurrentRoles)) {
            $personCurrentRoles = [$personCurrentRoles];
        } elseif (!is_array($personCurrentRoles)) {
            $personCurrentRoles = [];
        }

        if (is_string($newRoles) && str_contains($newRoles, ',')) {
            $newRoles = explode(',', $newRoles);
        } elseif (is_string($newRoles) && !empty($newRoles)) {
            $newRoles = [$newRoles];
        } elseif (!is_array($newRoles)) {
            $newRoles = [];
        }

        // Ensure 'member' role is not removed and is always present in new roles.
        $rolesToRemove = array_diff($personCurrentRoles, ['member']);
        $newRoles = array_unique(array_merge($newRoles, ['member']));

        // Remove roles from MDP.
        foreach ($rolesToRemove as $roleToRemove) {
            $this->removeRole($updateRolePersonUuid, $roleToRemove);
        }

        // Remove roles from WordPress.

        WACC()->User()->removeWpRoles($updateRolePersonUuid, $rolesToRemove);

        // Add new roles to MDP.
        foreach ($newRoles as $roleToAdd) {
            $this->assignRole($updateRolePersonUuid, $roleToAdd, $orgId);
        }

        // Add new roles to WordPress.

        WACC()->User()->assignWpRoles($updateRolePersonUuid, $newRoles);

        // Create Touchpoint.
        $touchpointParams = [
            'person_id' => $updateRolePersonUuid,
            'action'    => 'Organization member updated',
            'details'   => "Person's role was updated from '" . esc_html(json_encode($personCurrentRoles)) . "' to '" . esc_html(json_encode($newRoles)) . "' on " . date('c', time()),
            'data'      => ['org_id' => $orgId],
        ];

        $serviceId = WACC()->Mdp()->Touchpoint()->getOrCreateServiceId('Roster Manage', 'Updated member role');

        if ($serviceId) {

            WACC()->Mdp()->Touchpoint()->writeTouchpoint($touchpointParams, $serviceId);

        }

        $response = [
            'success' => true,
            'error'   => false,
            'message' => __('Role updated successfully', 'wicket-wp-account-centre'),
        ];

        if ($response['error'] === false && !$preventRedirect) {
            // Ensure $wp is an object and request property exists before using it.
            $redirectUrl = home_url(is_object($wp) && isset($wp->request) ? add_query_arg([], $wp->request) : '');
            wp_safe_redirect($redirectUrl);
            exit;
        }

        return $response;
    }
}
