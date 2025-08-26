<?php

declare(strict_types=1);

namespace WicketAcc\Mdp;

use Exception;
use GuzzleHttp\Exception\RequestException;

// No direct access
defined('ABSPATH') || exit;

/**
 * Handles MDP Membership related API endpoints.
 */
class Membership extends Init
{
    protected Person $person;
    protected Organization $organization;
    protected array $cache = [];

    /**
     * Constructor.
     */
    public function __construct(Person $person, Organization $organization)
    {
        parent::__construct();
        $this->person = $person;
        $this->organization = $organization;
    }

    /**
     * Get a single organization membership by its UUID.
     *
     * @param string $uuid The membership UUID.
     * @return array|false The membership data array or false on failure.
     */
    public function getOrganizationMembershipByUuid(?string $uuid): array|false
    {
        if (empty($uuid)) {
            WACC()->Log->warning('UUID cannot be empty.', ['source' => __CLASS__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            WACC()->Log->error('Failed to initialize API client.', ['source' => __CLASS__, 'uuid' => $uuid]);

            return false;
        }

        try {
            // Assuming the Wicket SDK client's 'get' method returns an array or throws an exception.
            return $client->get("organization_memberships/{$uuid}");
        } catch (Exception $e) {
            WACC()->Log->error(
                'API Exception while fetching organization membership by UUID.',
                [
                    'source' => __CLASS__,
                    'uuid' => $uuid,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return false;
        }
    }

    /**
     * Get all organization memberships for a given organization UUID.
     *
     * @param string $org_uuid Organization UUID.
     * @return array|false An array of memberships with included data, or false on failure.
     */
    /**
     * Get all organization memberships for a given organization UUID.
     *
     * @param string $org_uuid Organization UUID.
     * @return array|false An array of memberships with included data, or false on failure.
     *                     Returns an empty array if no memberships are found but the call was successful.
     */
    public function getOrganizationMemberships(string $org_uuid): array|false
    {
        if (empty($org_uuid)) {
            WACC()->Log->warning('Organization UUID cannot be empty.', ['source' => __CLASS__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            WACC()->Log->error('Failed to initialize API client.', ['source' => __CLASS__, 'org_uuid' => $org_uuid]);

            return false;
        }

        try {
            $org_memberships_response = $client->get("/organizations/{$org_uuid}/membership_entries?sort=-ends_at&include=membership");

            if (!isset($org_memberships_response['data'])) {
                WACC()->Log->warning(
                    'API response for organization memberships did not contain a data key.',
                    ['source' => __CLASS__, 'org_uuid' => $org_uuid, 'response' => $org_memberships_response]
                );

                return false; // Or an empty array if that's preferred for malformed responses
            }

            if (empty($org_memberships_response['data'])) {
                return []; // Valid for an organization to have no memberships
            }

            $memberships = [];
            $included_data_map = [];

            if (!empty($org_memberships_response['included'])) {
                foreach ($org_memberships_response['included'] as $included_item) {
                    if (isset($included_item['type'], $included_item['id'])) {
                        $included_data_map[$included_item['type']][$included_item['id']] = $included_item;
                    }
                }
            }

            foreach ($org_memberships_response['data'] as $org_membership_entry) {
                $membership_id = $org_membership_entry['relationships']['membership']['data']['id'] ?? null;
                $current_entry = ['entry' => $org_membership_entry];

                if ($membership_id && isset($included_data_map['memberships'][$membership_id])) {
                    $current_entry['membership_details'] = $included_data_map['memberships'][$membership_id];
                }
                $memberships[] = $current_entry;
            }

            return $memberships;
        } catch (Exception $e) {
            WACC()->Log->error(
                'API Exception while fetching organization memberships.',
                [
                    'source' => __CLASS__,
                    'org_uuid' => $org_uuid,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return false;
        }
    }

    /**
     * Assigns a person to an organization membership.
     *
     * @param string $personId The UUID of the person.
     * @param string $membershipId The UUID of the membership definition.
     * @param string $orgMembershipId The UUID of the specific organization membership instance.
     * @param array  $orgMembership The organization membership data, used to extract start and end dates.
     * @return bool True on success, false on failure.
     */
    public function assignPersonToOrgMembership(string $personId, string $membershipId, string $orgMembershipId, array $orgMembership): bool
    {
        $client = $this->initClient();
        if (!$client) {
            return false;
        }

        $payload = [
            'data' => [
                'type' => 'person_memberships',
                'attributes' => [
                    'starts_at' => $orgMembership['data']['attributes']['starts_at'] ?? null,
                    'ends_at' => $orgMembership['data']['attributes']['ends_at'] ?? null,
                    'status' => 'Active',
                ],
                'relationships' => [
                    'person' => [
                        'data' => ['id' => $personId, 'type' => 'people'],
                    ],
                    'membership' => [
                        'data' => ['id' => $membershipId, 'type' => 'memberships'],
                    ],
                    'organization_membership' => [
                        'data' => ['id' => $orgMembershipId, 'type' => 'organization_memberships'],
                    ],
                ],
            ],
        ];

        try {
            $client->post('person_memberships', ['json' => $payload]);

            return true;
        } catch (RequestException $e) {
            WACC()->Log->error(
                'API Exception while assigning person to organization membership.',
                [
                    'source' => __CLASS__,
                    'personId' => $personId,
                    'membershipId' => $membershipId,
                    'orgMembershipId' => $orgMembershipId,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return false;
        }
    }

    /**
     * Unassigns a person from a membership on an organization.
     *
     * @param string $personMembershipUuid The UUID of the person_membership record to delete.
     * @return bool True on success, false on failure.
     */
    public function unassignPersonFromOrgMembership(string $personMembershipUuid): bool
    {
        $client = $this->initClient();
        if (!$client) {
            return false;
        }

        try {
            $client->delete("person_memberships/{$personMembershipUuid}");

            return true;
        } catch (RequestException $e) {
            WACC()->Log->error(
                'API Exception while unassigning person from organization membership.',
                [
                    'source' => __CLASS__,
                    'personMembershipUuid' => $personMembershipUuid,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return false;
        }
    }

    /**
     * Returns active memberships for the current person from Wicket.
     * This method replicates and replaces the functionality of the legacy
     * `wicket_get_active_memberships` function using the new Mdp structure.
     *
     * @param string $iso_code (Optional) ISO code for the language: en, fr, es, etc. Defaults to 'en'.
     * @return array An array of active membership summaries.
     */
    public function getCurrentPersonActiveMemberships(string $iso_code = 'en'): array
    {
        $membership_summary = [];

        // Use the same approach as the original working function
        $wicket_memberships = $this->getCurrentPersonMemberships();

        if ($wicket_memberships) {
            $helper = new \Wicket\ResponseHelper($wicket_memberships);

            foreach ($helper->data as $entry) {
                $membership_tier = $helper->getIncludedRelationship($entry, 'membership');
                if (!$membership_tier) {
                    continue;
                }
                if ($entry['attributes']['status'] != 'Active') {
                    continue;
                }
                $entry_summary = [
                    'membership_category' => $entry['attributes']['membership_category'],
                    'starts_at'           => $entry['attributes']['starts_at'],
                    'ends_at'             => $entry['attributes']['ends_at'],
                    'name'                => $membership_tier['attributes']['name_' . $iso_code] ?? $membership_tier['attributes']['name'] ?? 'N/A',
                    'type'                => $membership_tier['attributes']['type'],
                ];

                if (isset($entry['relationships']['organization_membership']['data']['id'])) {
                    $entry_summary['organization_membership_id'] = $entry['relationships']['organization_membership']['data']['id'];
                }

                $membership_summary[] = $entry_summary;
            }
        }

        return $membership_summary;
    }

    /**
     * Returns active memberships relationship from wicket API.
     *
     * @param string|null $org_uuid The organization UUID
     * @return array|false $memberships relationship, false if UUID is null
     */
    public function getActiveMembershipRelationship(?string $org_uuid): array|false
    {
        if ($org_uuid === null) {
            return false;
        }

        $person_type = '';
        $wicket_memberships = $this->getCurrentPersonMemberships();
        $person_uuid = $this->person->getCurrentPersonUuid();
        $org_info = [];

        if ($wicket_memberships && isset($wicket_memberships['included'])) {
            foreach ($wicket_memberships['included'] as $included) {
                if ($included['type'] !== 'organizations') {
                    continue;
                }

                $included_org_uuid = $included['id'] ?? '';

                if ($org_uuid !== $included_org_uuid) {
                    continue;
                }

                $org_connections = $this->organization->getOrgConnectionsById($included_org_uuid);
                $org_info['name'] = $included['attributes']['legal_name'] ?? '';

                if ($org_connections) {
                    foreach ($org_connections as $org_included) {
                        $person_to_org_uuid = $org_included['relationships']['person']['data']['id'] ?? '';

                        if ($person_to_org_uuid == $person_uuid) {
                            $person_type = $org_included['attributes']['type'] ?? '';
                            break; // Found the match, no need to continue
                        }
                    }
                }
            }
        }

        $person_type = str_replace(['-', '_'], ' ', $person_type);
        $org_info['relationship'] = ucwords($person_type);

        return $org_info;
    }

    public function getCurrentUserWooActiveMemberships(): array
    {
        $membership_summary = [];

        if (!WACC()->isWooCommerceActive()) {
            return [];
        }

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return [];
        }

        $args = [
            'status' => ['active', 'complimentary'],
        ];

        if (function_exists('wc_memberships_get_user_memberships')) {
            /** @disregard P1010 Undefined function */
            $wc_user_memberships = \wc_memberships_get_user_memberships($current_user_id, $args);
        }

        if (empty($wc_user_memberships)) {
            return [];
        }

        foreach ($wc_user_memberships as $membership) {
            $entry_summary = [
                'starts_at' => $membership->get_start_date(),
                'ends_at'   => $membership->get_end_date(),
                'name'      => $membership->plan->name,
            ];

            $membership_summary[] = $entry_summary;
        }

        return $membership_summary;
    }

    /**
     * Get organization membership data by its UUID, including membership and owner details.
     *
     * @param string $membershipUuid The UUID of the organization membership.
     *
     * @return array|false The API response array on success, false otherwise.
     */
    public function getOrganizationMembershipData(string $membershipUuid): array|false
    {
        if (empty($membershipUuid)) {
            WACC()->Log->warning('Membership UUID cannot be empty.', ['source' => __CLASS__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            // initClient() already logs the error.
            return false;
        }

        try {
            $endpoint = 'organization_memberships/' . sanitize_text_field($membershipUuid);
            $params = [
                'query' => [
                    'include' => 'membership,owner',
                ],
            ];

            $response = $client->get($endpoint, $params);

            if (empty($response['data'])) {
                WACC()->Log->info('No data found for the given organization membership UUID.', [
                    'source' => __CLASS__,
                    'membership_uuid' => $membershipUuid,
                ]);

                return false;
            }

            return $response;
        } catch (RequestException $e) {
            $response_code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            WACC()->Log->error(
                'RequestException while fetching organization membership data.',
                [
                    'source' => __CLASS__,
                    'membership_uuid' => $membershipUuid,
                    'status_code' => $response_code,
                    'message' => $e->getMessage(),
                ]
            );

            return false;
        } catch (Exception $e) {
            WACC()->Log->error(
                'Generic Exception while fetching organization membership data.',
                [
                    'source' => __CLASS__,
                    'membership_uuid' => $membershipUuid,
                    'message' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * Get all person memberships for a given organization membership.
     *
     * @param string $membershipUuid The UUID of the organization membership.
     * @param array<string, int> $args The arguments for pagination.
     *        - 'page': The page number.
     *        - 'size': The page size.
     *
     * @return array|false The API response array on success, false otherwise.
     */
    public function getOrganizationMembershipMembers(string $membershipUuid, array $args = []): array|false
    {
        if (empty($membershipUuid)) {
            WACC()->Log->warning('Membership UUID cannot be empty.', ['source' => __CLASS__]);

            return false;
        }

        // Set pagination defaults
        $page = isset($args['page']) ? absint($args['page']) : 1;
        $size = isset($args['size']) ? absint($args['size']) : 20;

        $client = $this->initClient();
        if (!$client) {
            // initClient() already logs the error.
            return false;
        }

        try {
            $endpoint = 'organization_memberships/' . sanitize_text_field($membershipUuid) . '/person_memberships';
            $params = [
                'query' => [
                    'page' => [
                        'number' => $page,
                        'size'   => $size,
                    ],
                ],
            ];

            $response = $client->get($endpoint, $params);

            if (!isset($response['data'])) {
                WACC()->Log->info('No data found for the given organization membership members.', [
                    'source' => __CLASS__,
                    'membership_uuid' => $membershipUuid,
                ]);

                return false;
            }

            return $response;
        } catch (RequestException $e) {
            $response_code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            WACC()->Log->error(
                'RequestException while fetching organization membership members.',
                [
                    'source' => __CLASS__,
                    'membership_uuid' => $membershipUuid,
                    'status_code' => $response_code,
                    'message' => $e->getMessage(),
                ]
            );

            return false;
        } catch (Exception $e) {
            WACC()->Log->error(
                'Generic Exception while fetching organization membership members.',
                [
                    'source' => __CLASS__,
                    'membership_uuid' => $membershipUuid,
                    'message' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * Gets the person memberships for a specified UUID using the person membership entries endpoint.
     *
     * @param array $args (Optional) Array of arguments to pass to the API
     *              person_uuid (Optional) The person UUID to search for. If missing, uses current person.
     *              include (Optional) The include parameter to pass to the API. Default: 'membership,organization_membership.organization,fusebill_subscription'.
     *              filter (Optional) The filter parameter to pass to the API. Default: ['active_at' => 'now'].
     *
     * @return array|false Array of memberships on ['data'] or false on failure
     */
    public function getCurrentPersonMemberships(array $args = []): array|false
    {
        $defaults = [
            'person_uuid' => WACC()->Mdp()->Person()->getCurrentPersonUuid(),
            'include' => 'membership,organization_membership.organization,fusebill_subscription',
            'filter' => [
                'active_at' => 'now',
            ],
        ];

        $args = wp_parse_args($args, $defaults);
        $uuid = $args['person_uuid'];

        if (empty($uuid)) {
            WACC()->Log->warning('Person UUID cannot be empty for membership entries.', ['source' => __CLASS__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            WACC()->Log->error('Failed to initialize API client.', ['source' => __CLASS__, 'person_uuid' => $uuid]);

            return false;
        }

        // Use class-level caching with person UUID as key
        $cache_key = 'membership_entries_' . $uuid;
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        try {
            $endpoint = 'people/' . $uuid . '/membership_entries';
            $query_params = [];

            if (!empty($args['include'])) {
                $query_params['include'] = $args['include'];
            }

            if (!empty($args['filter'])) {
                $query_params['filter'] = $args['filter'];
            }

            if (!empty($query_params)) {
                $endpoint .= '?' . http_build_query($query_params);
            }

            $memberships = $client->get($endpoint);

            if ($memberships) {
                // Cache the result
                $this->cache[$cache_key] = $memberships;

                return $memberships;
            }

            WACC()->Log->info('No membership entries found for person.', [
                'source' => __CLASS__,
                'person_uuid' => $uuid,
            ]);

            return false;
        } catch (RequestException $e) {
            $response_code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'unknown';

            WACC()->Log->error(
                'RequestException while fetching person membership entries.',
                [
                    'source' => __CLASS__,
                    'person_uuid' => $uuid,
                    'status_code' => $response_code,
                    'message' => $e->getMessage(),
                ]
            );

            return false;
        } catch (Exception $e) {
            WACC()->Log->error(
                'Generic Exception while fetching person membership entries.',
                [
                    'source' => __CLASS__,
                    'person_uuid' => $uuid,
                    'message' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * Gets the max end date for a person's memberships using the person_member_histories endpoint.
     *
     * @param array $args Optional arguments.
     *              person_uuid (string|null) The person UUID to search for. If null/missing, uses current person.
     *              rollup_type (string) The rollup type to filter by. Default: 'category'.
     *              category (string) The category to filter by. Default: 'Membership'.
     * @return string|false The max end date on success, false on failure.
     */
    public function getPersonMaxEndDate(array $args = []): string|false
    {
        $defaults = [
            'person_uuid' => null,
            'rollup_type' => 'category',
            'category' => 'Membership',
        ];

        $args = wp_parse_args($args, $defaults);
        extract($args);

        if (empty($person_uuid)) {
            $person_uuid = WACC()->Mdp()->Person()->getCurrentPersonUuid();
        }

        if (empty($person_uuid)) {
            WACC()->Log->warning('Person UUID cannot be empty for max end date.', ['source' => __CLASS__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            WACC()->Log->error('Failed to initialize API client.', ['source' => __CLASS__, 'person_uuid' => $person_uuid]);

            return false;
        }

        // Use class-level caching with person UUID as key
        $cache_key = 'max_end_date_' . $person_uuid . '_' . $rollup_type . '_' . $category;
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        try {
            $endpoint = 'person_member_histories';
            $query_params = [
                'filter[rollup_type_eq]' => $rollup_type,
                'filter[person_uuid_eq]' => $person_uuid,
            ];

            // Add category filter if rollup_type is category
            if ($rollup_type === 'category') {
                $query_params['filter[category_eq]'] = $category;
            }

            $endpoint .= '?' . http_build_query($query_params);

            $response = $client->get($endpoint);

            if (!empty($response['data']) && is_array($response['data'])) {
                // Get the max_ends_at from the first entry (should only be one with category rollup)
                foreach ($response['data'] as $entry) {
                    if (isset($entry['attributes']['max_ends_at'])) {
                        $max_end_date = $entry['attributes']['max_ends_at'];
                        // Cache the result
                        $this->cache[$cache_key] = $max_end_date;

                        return $max_end_date;
                    }
                }
            }

            WACC()->Log->info('No max end date found for person.', [
                'source' => __CLASS__,
                'person_uuid' => $person_uuid,
            ]);

            return false;
        } catch (RequestException $e) {
            $response_code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'unknown';

            WACC()->Log->error(
                'RequestException while fetching person max end date.',
                [
                    'source' => __CLASS__,
                    'person_uuid' => $person_uuid,
                    'status_code' => $response_code,
                    'message' => $e->getMessage(),
                ]
            );

            return false;
        } catch (Exception $e) {
            WACC()->Log->error(
                'Generic Exception while fetching person max end date.',
                [
                    'source' => __CLASS__,
                    'person_uuid' => $person_uuid,
                    'message' => $e->getMessage(),
                ]
            );

            return false;
        }
    }
}
