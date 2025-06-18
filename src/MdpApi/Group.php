<?php

namespace WicketAcc\MdpApi;

// No direct access
defined('ABSPATH') || exit;

use GuzzleHttp\Exception\RequestException;

/**
 * Handle MDP Group endpoints.
 */
class Group extends Init
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Retrieves all groups from the Wicket API.
     *
     * @return array|false An array of group data on success (can be empty if no groups exist).
     *                     Returns false if:
     *                     - The Wicket API client fails to initialize.
     *                     - An API error occurs.
     */
    public function getGroups(): array|false
    {
        $client = $this->initClient();
        if (!$client) {
            WACC()->Log->error('Failed to initialize Wicket API client.', ['source' => 'MdpApi-Group']);

            return false;
        }

        try {
            $groupsData = $client->get('groups');

            // Assuming $groupsData is an array as per Wicket SDK behavior for list endpoints.
            return $groupsData;
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            WACC()->Log->error(
                'RequestException while fetching groups. Status: ' . $statusCode . '. Message: ' . $e->getMessage(),
                ['source' => 'MdpApi-Group']
            );

            return false;
        } catch (\Exception $e) {
            WACC()->Log->error(
                'Generic Exception while fetching groups: ' . $e->getMessage(),
                ['source' => 'MdpApi-Group']
            );

            return false;
        }
    }

    /**
     * Get all groups that a person UUID is part of.
     *
     * @param ?string $person_uuid (Optional) The person UUID to search for. If missing, uses current person.
     * @param array   $args (Optional) Array of arguments to pass to the API:
     *                      'org_id'       => (string|null) The organization UUID to search for. Default null (search all groups).
     *                      'search_query' => (string|null) The search query to find groups by their names (case insensitive). Default null.
     *                      'per_page'     => (int) The number of groups to return per page. Default 20.
     *                      'page'         => (int) The page number to return. Default 1.
     *
     * @return array|false Array of group memberships with included group data on success, false on failure or if no groups found.
     */
    public function getPersonGroups(?string $person_uuid = null, array $args = []): array|false
    {
        // Default args
        $defaults = [
            'org_id'       => null,
            'search_query' => null,
            'per_page'     => 20,
            'page'         => 1,
        ];
        $args = wp_parse_args($args, $defaults);

        if (is_null($person_uuid)) {
            // Assumes $this->Person->getCurrentPersonUuid() exists and returns ?string
            $person_uuid = $this->Person->getCurrentPersonUuid();
        }

        if (empty($person_uuid)) {
            WACC()->Log->warning('Person UUID is empty, cannot fetch groups.', ['source' => 'MdpApi-Group']);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            // initClient() already logs an error, so we can just return here.
            return false;
        }

        try {
            // Payload
            $query_params = [
                'page' => [
                    'number' => (int) $args['page'],
                    'size'   => (int) $args['per_page'],
                ],
                'filter' => [
                    'person_uuid_eq' => $person_uuid,
                ],
                'include' => 'group',
            ];

            // Arg: org_id
            if (!empty($args['org_id'])) {
                $query_params['filter']['group_organization_uuid_eq'] = $args['org_id'];
            }

            // Arg: search_query
            if (!empty($args['search_query'])) {
                $query_params['filter']['group_name_en_i_cont'] = $args['search_query'];
            }

            // Query the MDP
            $response = $client->get('/group_members', [
                'query' => $query_params,
            ]);

            if (!isset($response['data']) || empty($response['data'])) {
                WACC()->Log->info(
                    'No group data found for person.',
                    [
                        'source' => 'MdpApi-Group',
                        'person_uuid' => $person_uuid,
                        'args' => $args,
                        'response_keys' => isset($response) ? array_keys($response) : 'null_response',
                    ]
                );

                return false; // No groups found or API did not return data as expected.
            }

            return $response;
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $log_context = [
                'source' => 'MdpApi-Group',
                'person_uuid' => $person_uuid,
                'args' => $args,
                'status_code' => $statusCode,
            ];
            $log_context['exception_trace'] = $e->getTraceAsString();
            WACC()->Log->error(
                'RequestException while fetching person groups: ' . $e->getMessage(),
                $log_context
            );

            return false;
        } catch (\Exception $e) {
            $log_context = [
                'source' => 'MdpApi-Group',
                'person_uuid' => $person_uuid,
                'args' => $args,
            ];
            $log_context['exception_class'] = get_class($e);
            $log_context['exception_trace'] = $e->getTraceAsString();
            WACC()->Log->error(
                'Generic Exception while fetching person groups: ' . $e->getMessage(),
                $log_context
            );

            return false;
        }
    }

    /**
     * Get all groups associated with a specific Organization UUID.
     *
     * @param string $org_uuid The organization UUID to search for.
     * @param array  $args (Optional) Array of arguments to pass to the API:
     *                     'search_query' => (string|null) The search query to find groups by their names (case insensitive). Default null.
     *                     'per_page'     => (int) The number of groups to return per page. Default 20.
     *                     'page'         => (int) The page number to return. Default 1.
     *
     * @return array|false Array of groups on success, false on failure or if no groups found.
     */
    public function getOrganizationGroups(string $org_uuid, array $args = []): array|false
    {
        // Default args
        $defaults = [
            'search_query' => null,
            'per_page'     => 20,
            'page'         => 1,
        ];
        $args = wp_parse_args($args, $defaults);

        if (empty($org_uuid)) {
            WACC()->Log->warning('Organization UUID is empty, cannot fetch groups.', ['source' => 'MdpApi-Group']);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            // initClient() already logs an error.
            return false;
        }

        try {
            // Payload
            $query_params = [
                'page' => [
                    'number' => (int) $args['page'],
                    'size'   => (int) $args['per_page'],
                ],
                'filter' => [
                    'organization_uuid_eq' => $org_uuid,
                ],
            ];

            // Arg: search_query
            if (!empty($args['search_query'])) {
                $query_params['filter']['name_en_i_cont'] = $args['search_query'];
            }

            // Query the MDP
            $response = $client->get('/groups', [
                'query' => $query_params,
            ]);

            if (!isset($response['data']) || empty($response['data'])) {
                WACC()->Log->info(
                    'No groups found for organization.',
                    [
                        'source'   => 'MdpApi-Group',
                        'org_uuid' => $org_uuid,
                        'args'     => $args,
                    ]
                );

                return false;
            }

            return $response;
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $log_context = [
                'source'      => 'MdpApi-Group',
                'org_uuid'    => $org_uuid,
                'args'        => $args,
                'status_code' => $statusCode,
                'exception_trace' => $e->getTraceAsString(),
            ];
            WACC()->Log->error(
                'RequestException while fetching organization groups: ' . $e->getMessage(),
                $log_context
            );

            return false;
        } catch (\Exception $e) {
            $log_context = [
                'source'   => 'MdpApi-Group',
                'org_uuid' => $org_uuid,
                'args'     => $args,
                'exception_class' => get_class($e),
                'exception_trace' => $e->getTraceAsString(),
            ];
            WACC()->Log->error(
                'Generic Exception while fetching organization groups: ' . $e->getMessage(),
                $log_context
            );

            return false;
        }
    }

    /**
     * Add a member to a group with the specified role.
     *
     * @param string $person_id The UUID of the person to add.
     * @param string $group_uuid The UUID of the group to add the member to.
     * @param string $group_role_slug The role to assign to the person (e.g., 'member', 'admin').
     * @param array  $args { (Optional) Array of arguments.
     *     @type ?string     The start date for the membership (YYYY-MM-DD). Defaults to today.
     *     @type ?string       The end date for the membership (YYYY-MM-DD). Default null.
     *     @type bool    Whether to skip if the user is already a member with the same role. Default true.
     * }
     *
     * @return array|false The API response array for the new or existing membership, or false on failure.
     */
    public function addGroupMember(string $person_id, string $group_uuid, string $group_role_slug, array $args = []): array|false
    {
        // Validate required parameters
        if (empty($person_id) || empty($group_uuid) || empty($group_role_slug)) {
            WACC()->Log->warning(
                'Missing required parameter(s) for addGroupMember.',
                ['source' => 'MdpApi-Group', 'person_id' => $person_id, 'group_uuid' => $group_uuid, 'role' => $group_role_slug]
            );

            return false;
        }

        // Default args
        $defaults = [
            'start_date'     => null,
            'end_date'       => null,
            'skip_if_exists' => true,
        ];
        $args = wp_parse_args($args, $defaults);

        // If skip_if_exists is true, check for an existing membership
        if ($args['skip_if_exists']) {
            $existing_memberships = $this->getPersonGroups($person_id);
            if (!empty($existing_memberships['data'])) {
                foreach ($existing_memberships['data'] as $membership) {
                    if (
                        ($membership['relationships']['group']['data']['id'] ?? '') === $group_uuid
                        && ($membership['attributes']['type'] ?? '') === $group_role_slug
                    ) {
                        WACC()->Log->info(
                            'User is already a member of the group with the same role. Skipping.',
                            ['source' => 'MdpApi-Group', 'person_id' => $person_id, 'group_uuid' => $group_uuid]
                        );

                        return $membership; // Return existing membership data
                    }
                }
            }
        }

        // Set start date to today if not provided, formatted correctly with timezone
        $start_date = $args['start_date'] ?? (new \DateTime('today', wp_timezone()))->format('Y-m-d\T00:00:00P');

        $client = $this->initClient();
        if (!$client) {
            return false; // initClient() logs the error
        }

        // Construct the JSON:API compliant payload
        $payload = [
            'data' => [
                'type'       => 'group_members',
                'attributes' => [
                    'type'       => $group_role_slug,
                    'start_date' => $start_date,
                    'end_date'   => $args['end_date'],
                ],
                'relationships' => [
                    'person' => [
                        'data' => ['type' => 'people', 'id' => $person_id],
                    ],
                    'group' => [
                        'data' => ['type' => 'groups', 'id' => $group_uuid],
                    ],
                ],
            ],
        ];

        try {
            return $client->post('group_members', ['json' => $payload]);
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $log_context = [
                'source'      => 'MdpApi-Group',
                'person_id'   => $person_id,
                'group_uuid'  => $group_uuid,
                'args'        => $args,
                'status_code' => $statusCode,
                'payload'     => $payload, // Log the payload for debugging
                'exception_trace' => $e->getTraceAsString(),
            ];
            WACC()->Log->error('RequestException while adding group member: ' . $e->getMessage(), $log_context);

            return false;
        } catch (\Exception $e) {
            $log_context = [
                'source'   => 'MdpApi-Group',
                'person_id'   => $person_id,
                'group_uuid'  => $group_uuid,
                'args'     => $args,
                'payload'     => $payload,
                'exception_class' => get_class($e),
                'exception_trace' => $e->getTraceAsString(),
            ];
            WACC()->Log->error('Generic Exception while adding group member: ' . $e->getMessage(), $log_context);

            return false;
        }
    }

    /**
     * Removes a member from a group using the group membership UUID.
     *
     * @param string $groupMembershipId The UUID of the group membership record (the 'group_members' resource ID).
     *
     * @return bool True on successful deletion, false on failure.
     */
    public function removeGroupMember(string $groupMembershipId): bool
    {
        if (empty($groupMembershipId)) {
            WACC()->Log->warning('Group membership ID is empty, cannot remove member.', ['source' => 'MdpApi-Group']);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            // initClient() already logs an error.
            return false;
        }

        try {
            $client->delete("/group_members/{$groupMembershipId}");
            WACC()->Log->info(
                'Successfully removed group member.',
                ['source' => 'MdpApi-Group', 'group_membership_id' => $groupMembershipId]
            );

            return true;
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $log_context = [
                'source'              => 'MdpApi-Group',
                'group_membership_id' => $groupMembershipId,
                'status_code'         => $statusCode,
                'exception_trace'     => $e->getTraceAsString(),
            ];
            WACC()->Log->error(
                'RequestException while removing group member: ' . $e->getMessage(),
                $log_context
            );

            return false;
        } catch (\Exception $e) {
            $log_context = [
                'source'              => 'MdpApi-Group',
                'group_membership_id' => $groupMembershipId,
                'exception_class'     => get_class($e),
                'exception_trace'     => $e->getTraceAsString(),
            ];
            WACC()->Log->error(
                'Generic Exception while removing group member: ' . $e->getMessage(),
                $log_context
            );

            return false;
        }
    }

    /**
     * Get a specific group by its UUID.
     *
     * @param string $uuid The UUID of the group to retrieve.
     *
     * @return array|false The group data array on success, false on failure or if not found.
     */
    public function getGroup(string $uuid): array|false
    {
        if (empty($uuid)) {
            WACC()->Log->warning('Group UUID is empty, cannot fetch group.', ['source' => 'MdpApi-Group']);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            // initClient() already logs an error.
            return false;
        }

        try {
            $response = $client->get("groups/{$uuid}");

            if (empty($response['data'])) {
                WACC()->Log->info(
                    'Group not found or no data returned.',
                    ['source' => 'MdpApi-Group', 'group_uuid' => $uuid]
                );

                return false;
            }

            return $response; // Assuming the response structure includes the group data directly or under a 'data' key
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $log_context = [
                'source'      => 'MdpApi-Group',
                'group_uuid'  => $uuid,
                'status_code' => $statusCode,
                'exception_trace' => $e->getTraceAsString(),
            ];
            // Specifically log 404 as info, others as error
            if ($statusCode === 404) {
                WACC()->Log->info('Group not found (404).', $log_context);
            } else {
                WACC()->Log->error('RequestException while fetching group: ' . $e->getMessage(), $log_context);
            }

            return false;
        } catch (\Exception $e) {
            $log_context = [
                'source'   => 'MdpApi-Group',
                'group_uuid' => $uuid,
                'exception_class' => get_class($e),
                'exception_trace' => $e->getTraceAsString(),
            ];
            WACC()->Log->error('Generic Exception while fetching group: ' . $e->getMessage(), $log_context);

            return false;
        }
    }

    /**
     * Get all members (people) of a specific group, with optional filtering.
     *
     * @param string $group_uuid The UUID of the group to get members from.
     * @param array  $args (Optional) Array of arguments for filtering and pagination:
     *                     'per_page' => (int) Number of members per page. Default 50.
     *                     'page'     => (int) Page number. Default 1.
     *                     'active'   => (bool) Filter by active status. Default true.
     *                     'role'     => (string|null) Filter by group role slug (e.g., 'member') or comma-separated roles (e.g., 'member,observer'). Default null.
     *
     * @return array|false Array of group members (people data) on success, false on failure.
     */
    public function getGroupMembers(string $group_uuid, array $args = []): array|false
    {
        if (empty($group_uuid)) {
            WACC()->Log->warning('Group UUID is empty, cannot fetch group members.', ['source' => 'MdpApi-Group']);

            return false;
        }

        // Default args
        $defaults = [
            'per_page' => 50,
            'page'     => 1,
            'active'   => true,
            'role'     => null,
        ];
        $args = wp_parse_args($args, $defaults);

        $client = $this->initClient();
        if (!$client) {
            return false; // initClient() logs the error
        }

        $endpoint = "/groups/{$group_uuid}/people";
        $query_params = [
            'page' => [
                'number' => (int) $args['page'],
                'size'   => (int) $args['per_page'],
            ],
            'filter' => [
                'active_eq' => $args['active'] ? 'true' : 'false', // Ensure boolean is string 'true'/'false' for API query
            ],
            'include' => 'person',
        ];

        // Handle role filtering
        if (!empty($args['role'])) {
            $trimmed_role = trim($args['role']);
            if (str_contains($trimmed_role, ',')) {
                $roles_array = array_map('trim', explode(',', $trimmed_role));
                $query_params['filter']['resource_type_slug_in'] = $roles_array;
            } else {
                $query_params['filter']['resource_type_slug_eq'] = $trimmed_role;
            }
        }

        try {
            $response = $client->get($endpoint, ['query' => $query_params]);

            if (empty($response['data']) && empty($response['included'])) {
                WACC()->Log->info(
                    'No members found for group or no data returned.',
                    ['source' => 'MdpApi-Group', 'group_uuid' => $group_uuid, 'args' => $args]
                );

                // Return empty array if no data, as it's a valid state (group with no members)
                // but ensure the structure matches a successful call with data for consistency if possible.
                // For now, returning the raw empty response is acceptable if API guarantees 'data' key.
                // If API might omit 'data' key on empty results, adjust to return ['data' => [], 'included' => []].
                return $response;
            }

            return $response;
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $error_message_detail = 'RequestException while fetching group members.';
            if ($e->hasResponse()) {
                try {
                    $error_body = json_decode((string) $e->getResponse()->getBody(), true, 512, JSON_THROW_ON_ERROR);
                    $error_message_detail = $error_body['errors'][0]['detail'] ?? $error_body['errors'][0]['title'] ?? $e->getMessage();
                } catch (\JsonException $jsonEx) {
                    $error_message_detail = 'Could not decode API error response: ' . $jsonEx->getMessage();
                }
            }
            $log_context = [
                'source'      => 'MdpApi-Group',
                'group_uuid'  => $group_uuid,
                'args'        => $args,
                'status_code' => $statusCode,
                'response_body' => $e->hasResponse() ? (string) $e->getResponse()->getBody() : null,
                'exception_trace' => $e->getTraceAsString(),
            ];
            WACC()->Log->error($error_message_detail, $log_context);

            return false;
        } catch (\Exception $e) {
            $log_context = [
                'source'   => 'MdpApi-Group',
                'group_uuid' => $group_uuid,
                'args'     => $args,
                'exception_class' => get_class($e),
                'exception_trace' => $e->getTraceAsString(),
            ];
            WACC()->Log->error('Generic Exception while fetching group members: ' . $e->getMessage(), $log_context);

            return false;
        }
    }

    /**
     * Search for members within a specific group.
     *
     * @param string $group_uuid The UUID of the group to search in.
     * @param string $search_query The search query (person's first/last name, email).
     * @param array  $args (Optional) Array of arguments for filtering and pagination:
     *                     'per_page' => (int) Number of members per page. Default 20.
     *                     'page'     => (int) Page number. Default 1.
     *                     'active'   => (bool) Filter by active status. Default true.
     *                     'role'     => (string|null) Filter by group role slug(s), comma-separated for multiple.
     *
     * @return array|false The API response array on success, false on failure.
     */
    public function searchGroupMembers(string $group_uuid, string $search_query, array $args = []): array|false
    {
        if (empty($group_uuid) || empty($search_query)) {
            WACC()->Log->warning(
                'Group UUID or search query is empty.',
                ['source' => 'MdpApi-Group', 'group_uuid' => $group_uuid, 'search_query' => $search_query]
            );

            return false;
        }

        $defaults = [
            'per_page' => 20,
            'page'     => 1,
            'active'   => true,
            'role'     => null,
        ];
        $args = wp_parse_args($args, $defaults);

        $client = $this->initClient();
        if (!$client) {
            return false; // initClient() logs the error
        }

        $endpoint = "/groups/{$group_uuid}/people";
        $query_params = [
            'page' => [
                'number' => (int) $args['page'],
                'size'   => (int) $args['per_page'],
            ],
            'filter' => [
                'active_eq' => $args['active'] ? 'true' : 'false',
                'person_search_query' => [
                    'keywords' => [
                        'term'   => $search_query,
                        'fields' => 'full_name,given_name,family_name,primary_email',
                    ],
                ],
            ],
            'include' => 'person',
        ];

        if (!empty($args['role'])) {
            $trimmed_role = trim($args['role']);
            if (str_contains($trimmed_role, ',')) {
                $roles_array = array_map('trim', explode(',', $trimmed_role));
                $query_params['filter']['resource_type_slug_in'] = $roles_array;
            } else {
                $query_params['filter']['resource_type_slug_eq'] = $trimmed_role;
            }
        }

        try {
            $response = $client->get($endpoint, ['query' => $query_params]);
            if (empty($response['data']) && empty($response['included'])) {
                WACC()->Log->info(
                    'Search returned no members for group.',
                    ['source' => 'MdpApi-Group', 'group_uuid' => $group_uuid, 'search_query' => $search_query, 'args' => $args]
                );
            }

            return $response;
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $error_message_detail = 'RequestException while searching group members.';
            if ($e->hasResponse()) {
                try {
                    $error_body = json_decode((string) $e->getResponse()->getBody(), true, 512, JSON_THROW_ON_ERROR);
                    $error_message_detail = $error_body['errors'][0]['detail'] ?? $error_body['errors'][0]['title'] ?? $e->getMessage();
                } catch (\JsonException $jsonEx) {
                    $error_message_detail = 'Could not decode API error response: ' . $jsonEx->getMessage();
                }
            }
            $log_context = [
                'source'       => 'MdpApi-Group',
                'group_uuid'   => $group_uuid,
                'search_query' => $search_query,
                'args'         => $args,
                'status_code'  => $statusCode,
                'response_body' => $e->hasResponse() ? (string) $e->getResponse()->getBody() : null,
                'exception_trace' => $e->getTraceAsString(),
            ];
            WACC()->Log->error($error_message_detail, $log_context);

            return false;
        } catch (\Exception $e) {
            $log_context = [
                'source'       => 'MdpApi-Group',
                'group_uuid'   => $group_uuid,
                'search_query' => $search_query,
                'args'         => $args,
                'exception_class' => get_class($e),
                'exception_trace' => $e->getTraceAsString(),
            ];
            WACC()->Log->error('Generic Exception while searching group members: ' . $e->getMessage(), $log_context);

            return false;
        }
    }

    /**
     * Formats raw group data from the API for display purposes (e.g., selectors).
     *
     * @param array $groups The raw API response array containing group data.
     * @return array|false An array of formatted group data, or false if the input is empty or invalid.
     */
    public function formatGroupsForSelector(array $groups = []): array|false
    {
        if (empty($groups['data'])) {
            WACC()->Log->info('Input for group formatting is empty or invalid.', ['source' => 'MdpApi-Group', 'groups_data' => $groups]);

            return false;
        }

        $formatted_groups = [];
        $lang = WACC()->Language->getCurrentLanguage();

        foreach ($groups['data'] as $group) {
            if (($group['type'] ?? '') !== 'groups') {
                continue;
            }

            $attributes = $group['attributes'] ?? [];
            $formatted_groups[] = [
                'id'           => $group['id'] ?? null,
                'name'         => $attributes["name_{$lang}"] ?? $attributes['name'] ?? '',
                'type'         => isset($attributes['type']) ? ucwords(str_replace('_', ' ', $attributes['type'])) : '',
                'description'  => $attributes["description_{$lang}"] ?? $attributes['description'] ?? '',
                'is_active'    => $attributes['active'] ?? false,
                'member_count' => $attributes['active_member_count'] ?? 0,
                'start_date'   => $attributes['start_date'] ?? null,
                'end_date'     => $attributes['end_date'] ?? null,
                'slug'         => $attributes['slug'] ?? '',
            ];
        }

        return $formatted_groups;
    }

    /**
     * Formats the response from getPersonGroups for display purposes.
     *
     * This method processes an API response containing both group memberships and full group data
     * to produce a flattened, easy-to-use array.
     *
     * @param array $response The raw API response from getPersonGroups.
     * @return array|false An array of formatted group data, or false if the input is empty or invalid.
     */
    public function formatPersonGroupsForSelector(array $response = []): array|false
    {
        if (empty($response['data']) || empty($response['included'])) {
            WACC()->Log->info('Input for person group formatting is empty or invalid.', ['source' => 'MdpApi-Group', 'response_data' => $response]);

            return false;
        }

        // Create a lookup map for included groups for efficient access.
        $group_map = [];
        foreach ($response['included'] as $included_item) {
            if (($included_item['type'] ?? null) === 'groups') {
                $group_map[$included_item['id']] = $included_item;
            }
        }

        if (empty($group_map)) {
            WACC()->Log->info('No group data found in the included section of the response.', ['source' => 'MdpApi-Group']);

            return false;
        }

        $formatted_groups = [];
        $lang = WACC()->Language->getCurrentLanguage();

        foreach ($response['data'] as $group_member) {
            $group_id = $group_member['relationships']['group']['data']['id'] ?? null;
            if (!$group_id || !isset($group_map[$group_id])) {
                continue; // Skip if the related group is not in our map
            }

            $group = $group_map[$group_id];
            $group_attributes = $group['attributes'] ?? [];
            $member_attributes = $group_member['attributes'] ?? [];

            $formatted_groups[] = [
                'id'          => $group['id'] ?? null,
                'name'        => $group_attributes["name_{$lang}"] ?? $group_attributes['name'] ?? '',
                'type'        => isset($group_attributes['type']) ? ucwords(str_replace('_', ' ', $group_attributes['type'])) : '',
                'description' => $group_attributes["description_{$lang}"] ?? $group_attributes['description'] ?? '',
                'is_active'   => $member_attributes['active'] ?? false,
                'member_role' => isset($member_attributes['type']) ? ucwords(str_replace('_', ' ', $member_attributes['type'])) : '',
                'is_admin'    => ($member_attributes['type'] ?? '') === 'administrator',
                'start_date'  => $member_attributes['start_date'] ?? null,
                'end_date'    => $member_attributes['end_date'] ?? null,
                'slug'        => $group_attributes['slug'] ?? '',
            ];
        }

        return $formatted_groups;
    }
}
