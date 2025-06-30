<?php

declare(strict_types=1);

namespace WicketAcc\Mdp;

use Exception;
use GuzzleHttp\Exception\RequestException;

// No direct access
defined('ABSPATH') || exit;

/**
 * Handles MDP Organization related API endpoints.
 */
class Organization extends Init
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get organization data by UUID from MDP.
     *
     * @param string $uuid The organization UUID.
     * @return array|false The organization data array or false on failure.
     */
    public function getOrganizationByUuid(string $uuid, ?string $include = null): array|false
    {
        if (empty($uuid)) {
            WACC()->Log->warning('UUID cannot be empty.', ['source' => __METHOD__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            WACC()->Log->error('Failed to initialize API client.', ['source' => __METHOD__, 'uuid' => $uuid]);

            return false;
        }

        try {
            $params = [];
            if (!empty($include)) {
                $params['query'] = ['include' => $include];
            }

            // The Wicket SDK client returns the full response array.
            return $client->get("organizations/{$uuid}", $params);
        } catch (RequestException $e) {
            WACC()->Log->error(
                'RequestException while fetching organization by UUID.',
                [
                    'source' => __METHOD__,
                    'uuid' => $uuid,
                    'include' => $include,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return false;
        } catch (Exception $e) {
            WACC()->Log->error(
                'Generic Exception while fetching organization by UUID.',
                [
                    'source' => __METHOD__,
                    'uuid' => $uuid,
                    'include' => $include,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return false;
        }
    }

    /**
     * Get comprehensive organization information, including parent, address, phone, email.
     *
     * @param string $org_uuid Organization UUID.
     * @param string $lang Language code for localized fields (e.g., 'en', 'fr'). Defaults to 'en'.
     * @return array|false An array with organization details or false on failure.
     */
    public function getOrganizationInfo(string $org_uuid, string $lang = 'en'): array|false
    {
        if (empty($org_uuid)) {
            WACC()->Log->warning('Organization UUID cannot be empty.', ['source' => __METHOD__]);

            return false;
        }

        $organization_data = $this->getOrganizationByUuid($org_uuid);
        if (empty($organization_data['data'])) {
            WACC()->Log->warning('Failed to retrieve base data for organization.', ['source' => __METHOD__, 'org_uuid' => $org_uuid]);

            return false;
        }

        $attributes = $organization_data['data']['attributes'] ?? [];
        $relationships = $organization_data['data']['relationships'] ?? [];

        $org_parent_uuid = $relationships['parent_organization']['data']['id'] ?? '';
        $org_parent_name = '';
        if (!empty($org_parent_uuid)) {
            $org_parent_info = $this->getOrganizationByUuid($org_parent_uuid);
            if (!empty($org_parent_info['data']['attributes'])) {
                $parent_attrs = $org_parent_info['data']['attributes'];
                $org_parent_name = $parent_attrs["legal_name_{$lang}"] ?? $parent_attrs['legal_name'] ?? '';
            }
        }

        $org_name = $attributes["legal_name_{$lang}"] ?? $attributes['legal_name'] ?? '';
        $org_description = $attributes["description_{$lang}"] ?? $attributes['description'] ?? '';
        $org_type = $attributes['type'] ?? '';
        $org_type_nice_name = !empty($org_type) ? ucwords(str_replace('_', ' ', $org_type)) : '';
        $org_status = $attributes['status'] ?? '';

        $org_address_attrs = [];
        $org_phone_attrs = [];
        $org_email_attrs = [];

        $client = $this->initClient();
        if ($client) {
            $contact_types = ['addresses', 'phones', 'emails'];
            foreach ($contact_types as $type) {
                try {
                    $response = $client->get("organizations/{$org_uuid}/{$type}");
                    if (!empty($response['data'][0]['attributes'])) {
                        switch ($type) {
                            case 'addresses':
                                $org_address_attrs = $response['data'][0]['attributes'];
                                break;
                            case 'phones':
                                $org_phone_attrs = $response['data'][0]['attributes'];
                                break;
                            case 'emails':
                                $org_email_attrs = $response['data'][0]['attributes'];
                                break;
                        }
                    }
                } catch (Exception $e) {
                    WACC()->Log->error(
                        "API Exception fetching {$type} for organization.",
                        [
                            'source' => __METHOD__,
                            'org_uuid' => $org_uuid,
                            'contact_type' => $type,
                            'message' => $e->getMessage(),
                            'exception' => $e,
                        ]
                    );
                }
            }
        } else {
            WACC()->Log->error('Failed to initialize client for fetching contact details.', ['source' => __METHOD__, 'org_uuid' => $org_uuid]);
        }

        return [
            'org_uuid'           => $org_uuid,
            'org_name'           => $org_name,
            'org_description'    => $org_description,
            'org_parent_uuid'    => $org_parent_uuid,
            'org_parent_name'    => $org_parent_name,
            'org_type'           => $org_type,
            'org_type_nice_name' => $org_type_nice_name,
            'org_status'         => $org_status,
            'org_address'        => $org_address_attrs,
            'org_phone'          => $org_phone_attrs,
            'org_email'          => $org_email_attrs,
        ];
    }

    /**
     * Get all organizations from Wicket.
     *
     * This method caches the result for the duration of the request to avoid redundant API calls.
     *
     * @return array|false An array of organization data on success, false on failure.
     */
    public function getAllOrganizations(): array|false
    {
        static $organizations = null;

        // Only fetch if not already determined for this request
        if (is_null($organizations)) {
            $client = $this->initClient();
            if (!$client) {
                $organizations = false;

                return false;
            }

            try {
                // The Wicket SDK client returns the full response array.
                $organizations = $client->get('organizations');
            } catch (RequestException $e) {
                $response_code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
                WACC()->Log->error(
                    'RequestException while fetching all organizations.',
                    [
                        'source' => __METHOD__,
                        'status_code' => $response_code,
                        'message' => $e->getMessage(),
                    ]
                );
                $organizations = false;
            } catch (Exception $e) {
                WACC()->Log->error(
                    'Generic Exception while fetching all organizations.',
                    [
                        'source' => __METHOD__,
                        'message' => $e->getMessage(),
                    ]
                );
                $organizations = false;
            }
        }

        return $organizations;
    }

    /**
     * Get organization data or UUID by its slug.
     *
     * @param string $slug The organization slug to search for.
     * @param bool $returnUuidOnly If true, returns only the UUID string. Otherwise, returns the full organization data object.
     *
     * @return array|string|false The organization data array, the UUID string, or false if not found or on error.
     */
    public function getOrganizationBySlug(string $slug, bool $returnUuidOnly = false): array|string|false
    {
        if (empty($slug)) {
            WACC()->Log->warning('Organization slug cannot be empty.', ['source' => __METHOD__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            // initClient() already logs the error.
            return false;
        }

        try {
            $params = [
                'query' => [
                    'filter[slug_eq]' => $slug,
                    'page[size]' => 1,
                ],
            ];

            // If we only need the UUID, we can optimize the query to only return the 'id' field.
            if ($returnUuidOnly) {
                $params['query']['fields[organizations]'] = 'id';
            }

            $response = $client->get('organizations', $params);

            if (!empty($response['data'][0]['id'])) {
                $orgUuid = $response['data'][0]['id'];

                if ($returnUuidOnly) {
                    return $orgUuid;
                }

                // To ensure the returned object is consistent with other methods,
                // we fetch the full resource by its UUID.
                return $this->getOrganizationByUuid($orgUuid);
            }

            WACC()->Log->info('No organization found with the specified slug.', ['source' => __METHOD__, 'slug' => $slug]);

            return false;

        } catch (RequestException $e) {
            WACC()->Log->error(
                'RequestException while fetching organization by slug.',
                [
                    'source' => __METHOD__,
                    'slug' => $slug,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return false;
        } catch (Exception $e) {
            WACC()->Log->error(
                'Generic Exception while fetching organization by slug.',
                [
                    'source' => __METHOD__,
                    'slug' => $slug,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return false;
        }
    }

    /**
     * Get basic organization information by UUID, including alternate name and parent details.
     *
     * Fetches localized legal name and description, as well as the parent organization's UUID and name.
     *
     * @param string $uuid The organization UUID.
     * @param string $lang Language code for localization (e.g., 'en', 'fr'). Defaults to 'en'.
     * @return array|false An array with basic organization details, or false on failure.
     */
    public function getOrganizationBasicInfo(string $uuid, string $lang = 'en'): array|false
    {
        if (empty($uuid)) {
            WACC()->Log->warning('Organization UUID cannot be empty.', ['source' => __METHOD__]);

            return false;
        }

        $orgData = $this->getOrganizationByUuid($uuid);
        if (empty($orgData['data'])) {
            WACC()->Log->warning('Organization not found for provided UUID.', ['source' => __METHOD__, 'uuid' => $uuid]);

            return false;
        }

        $attributes = $orgData['data']['attributes'] ?? [];
        $relationships = $orgData['data']['relationships'] ?? [];

        $orgName = $attributes["legal_name_{$lang}"] ?? $attributes['legal_name'] ?? '';
        $orgAlternateName = $attributes["alternate_name_{$lang}"] ?? $attributes['alternate_name'] ?? '';
        $orgDescription = $attributes["description_{$lang}"] ?? $attributes['description'] ?? '';
        $orgType = $attributes['type'] ?? '';
        $orgStatus = $attributes['status'] ?? '';

        // Derive pretty type name
        $orgTypePretty = '';
        if (!empty($orgType)) {
            $orgTypePretty = ucwords(str_replace(['-', '_'], ' ', $orgType));
        }
        // Use the Helper class to get the resource type name by slug.
        $orgTypeName = '';
        if (!empty($orgType)) {
            $orgTypeName = WACC()->Mdp->Helper->getResourceTypeNameBySlug($orgType);
            // If the helper didn't find a specific name, fall back to the pretty version.
            if (empty($orgTypeName)) {
                $orgTypeName = $orgTypePretty;
            }
        }

        $parentUuid = $relationships['parent_organization']['data']['id'] ?? '';
        $parentName = '';
        if (!empty($parentUuid)) {
            $parentOrgData = $this->getOrganizationByUuid($parentUuid);
            if (!empty($parentOrgData['data']['attributes'])) {
                $parentAttributes = $parentOrgData['data']['attributes'];
                $parentName = $parentAttributes["legal_name_{$lang}"] ?? $parentAttributes['legal_name'] ?? '';
            }
        }

        return [
            'org_uuid'        => $uuid,
            'org_name'        => $orgName,
            'org_name_alt'    => $orgAlternateName,
            'org_description' => $orgDescription,
            'org_type'        => $orgType, // This is the slug
            'org_type_pretty' => $orgTypePretty,
            'org_type_slug'   => $orgType, // Explicitly the slug
            'org_type_name'   => $orgTypeName,
            'org_status'      => $orgStatus,
            'org_parent_id'   => $parentUuid,
            'org_parent_name' => $parentName,
        ];
    }

    /**
     * Get all "connections" (relationships) of a specific Wicket organization by its UUID.
     *
     * @param string $orgUuid The UUID of the organization to fetch connections for.
     * @return array|false Array of organization connections on success, false on failure.
     */
    public function getOrgConnectionsById(string $orgUuid)
    {
        if (empty($orgUuid)) {
            WACC()->Log->warning('No organization UUID provided to fetch connections', ['source' => __METHOD__]);

            return false;
        }

        static $connectionsCache = [];

        if (!isset($connectionsCache[$orgUuid])) {
            try {
                $client = $this->initClient();
                if (!$client) {
                    return false;
                }

                $response = $client->get("organizations/{$orgUuid}/connections", [
                    'query' => [
                        'filter' => [
                            'connection_type_eq' => 'all',
                        ],
                        'sort' => '-created_at',
                    ],
                ]);

                $connections = $response['data'] ?? false;

                if (empty($connections)) {
                    WACC()->Log->info('No connections found for specified organization', [
                        'source' => __METHOD__,
                        'orgUuid' => $orgUuid,
                    ]);
                    $connectionsCache[$orgUuid] = false;
                } else {
                    $connectionsCache[$orgUuid] = $connections;
                }
            } catch (RequestException $e) {
                $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
                WACC()->Log->error("Error fetching organization connections (HTTP {$statusCode}): " . $e->getMessage(), [
                    'source' => __METHOD__,
                    'orgUuid' => $orgUuid,
                    'statusCode' => $statusCode,
                ]);

                return false;
            } catch (Exception $e) {
                WACC()->Log->error('Unexpected error fetching organization connections: ' . $e->getMessage(), [
                    'source' => __METHOD__,
                    'orgUuid' => $orgUuid,
                    'exception' => get_class($e),
                ]);

                return false;
            }
        }

        return $connectionsCache[$orgUuid];
    }

    /**
     * Get person-to-organization relationships for a specific organization.
     *
     * @param string $orgUuid The organization UUID.
     * @return array|false An array of relationship data or false on failure.
     */
    public function getOrganizationPersonRelationships(string $orgUuid): array|false
    {
        if (empty($orgUuid)) {
            WACC()->Log->warning('Organization UUID cannot be empty.', ['source' => __METHOD__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            WACC()->Log->error('Failed to initialize API client.', ['source' => __METHOD__, 'orgUuid' => $orgUuid]);

            return false;
        }

        try {
            $response = $client->get("organizations/{$orgUuid}/connections", [
                'query' => [
                    'filter' => [
                        'connection_type_eq' => 'person_to_organization',
                    ],
                ],
            ]);

            return $response['data'] ?? false;
        } catch (RequestException $e) {
            WACC()->Log->error(
                'RequestException while fetching organization person relationships.',
                [
                    'source' => __METHOD__,
                    'orgUuid' => $orgUuid,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return false;
        } catch (Exception $e) {
            WACC()->Log->error(
                'Generic Exception while fetching organization person relationships.',
                [
                    'source' => __METHOD__,
                    'orgUuid' => $orgUuid,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return false;
        }
    }

    /**
     * Returns an array of all the user's roles for a specific organization.
     *
     * @param string $personUuid The person's UUID.
     * @param string $orgUuid    The organization's UUID.
     * @return array|false An array of role names or false on failure/no roles found.
     */
    public function getOrganizationPersonRoles(string $personUuid, string $orgUuid): array|false
    {
        if (empty($personUuid) || empty($orgUuid)) {
            WACC()->Log->error('Person UUID and Organization UUID are required.', ['source' => __METHOD__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            return false;
        }

        try {
            $params = [
                'query' => [
                    'page[number]' => 1,
                    'page[size]'   => 100,
                    'include'      => 'resource',
                    'sort'         => '-global,name',
                ],
            ];

            $response = $client->get("people/{$personUuid}/roles", $params);

            $usersRoles = [];
            if (!empty($response['data'])) {
                foreach ($response['data'] as $role) {
                    if (
                        isset($role['relationships']['resource']['data']['id']) &&
                        $role['relationships']['resource']['data']['id'] === $orgUuid
                    ) {
                        $usersRoles[] = $role['attributes']['name'];
                    }
                }
            }

            return !empty($usersRoles) ? $usersRoles : false;
        } catch (RequestException $e) {
            $errorMsg = 'Failed to get person roles for organization.';
            $context = [
                'source'      => __METHOD__,
                'person_uuid' => $personUuid,
                'org_uuid'    => $orgUuid,
                'original_exception' => $e->getMessage(),
            ];
            if ($e->hasResponse()) {
                $context['statusCode'] = $e->getResponse()->getStatusCode();
                $context['responseBody'] = $e->getResponse()->getBody()->getContents();
            }
            WACC()->Log->error($errorMsg, $context);
        } catch (Exception $e) {
            WACC()->Log->error('Generic exception while getting person roles for organization.', [
                'source'      => __METHOD__,
                'person_uuid' => $personUuid,
                'org_uuid'    => $orgUuid,
                'message'     => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Get extended organization info, including main address, phone, and email.
     *
     * This method retrieves organization data and its primary contact details,
     * utilizing caching to improve performance.
     *
     * @param string $orgUuid The organization UUID.
     * @param string $lang    The language code (currently used for cache key).
     * @return array|false The organization info or false on failure.
     */
    public function getOrganizationInfoExtended(string $orgUuid, string $lang = 'en'): array|false
    {
        if (empty($orgUuid) || empty($lang)) {
            WACC()->Log->error('Organization UUID and language are required.', ['source' => __METHOD__]);

            return false;
        }

        $orgInfoKey = sprintf(
            'wicket_orgman_org_info_extended_%s_%s',
            sanitize_key($orgUuid),
            sanitize_key($lang)
        );
        $orgInfo = get_transient($orgInfoKey);

        if (false !== $orgInfo) {
            return $orgInfo;
        }

        $client = $this->initClient();
        if (!$client) {
            return false;
        }

        try {
            // Fetch organization info with included contact details in one call
            $response = $client->get("organizations/{$orgUuid}", [
                'query' => [
                    'include' => 'addresses,phones,emails',
                ],
            ]);

            if (empty($response['data'])) {
                return false;
            }

            $orgInfo = $response['data']['attributes'];
            $orgInfo['id'] = $response['data']['id'];
            $orgInfo['org_meta'] = [
                'main_address' => [],
                'main_phone' => [],
                'main_email' => [],
                'billing_email' => '',
            ];

            if (!empty($response['included'])) {
                foreach ($response['included'] as $item) {
                    switch ($item['type']) {
                        case 'addresses':
                            // Assuming the first address is the main one as per original logic
                            if (empty($orgInfo['org_meta']['main_address'])) {
                                $orgInfo['org_meta']['main_address'] = $item['attributes'];
                            }
                            break;
                        case 'phones':
                            // Assuming the first phone is the main one
                            if (empty($orgInfo['org_meta']['main_phone'])) {
                                $orgInfo['org_meta']['main_phone'] = $item['attributes'];
                            }
                            break;
                        case 'emails':
                            // Assuming the first email is the main one
                            if (empty($orgInfo['org_meta']['main_email'])) {
                                $orgInfo['org_meta']['main_email'] = $item['attributes'];
                            }
                            // Check for billing email
                            if (isset($item['attributes']['type']) && $item['attributes']['type'] === 'billing') {
                                $orgInfo['org_meta']['billing_email'] = $item['attributes']['address'];
                            }
                            break;
                    }
                }
            }

            set_transient($orgInfoKey, $orgInfo, 30); // Cache for 30 seconds

            return $orgInfo;
        } catch (RequestException $e) {
            $errorMsg = 'Failed to get extended organization info.';
            $context = [
                'source'      => __METHOD__,
                'org_uuid'    => $orgUuid,
                'original_exception' => $e->getMessage(),
            ];
            if ($e->hasResponse()) {
                $context['statusCode'] = $e->getResponse()->getStatusCode();
                $context['responseBody'] = $e->getResponse()->getBody()->getContents();
            }
            WACC()->Log->error($errorMsg, $context);
        } catch (Exception $e) {
            WACC()->Log->error('Generic exception while getting extended organization info.', [
                'source'      => __METHOD__,
                'org_uuid'    => $orgUuid,
                'message'     => $e->getMessage(),
            ]);
        }

        return false;
    }
}
