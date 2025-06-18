<?php

declare(strict_types=1);

namespace WicketAcc\MdpApi;

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
}
