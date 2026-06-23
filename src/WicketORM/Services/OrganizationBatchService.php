<?php

/**
 * Organization Batch Service for optimized organization fetching.
 */

namespace WicketORM\Services;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles batch organization data operations using proven API patterns.
 */
class OrganizationBatchService
{
    /**
     * @var array
     */
    private $config;

    public function __construct()
    {
        $this->config = ConfigService::getConfig();
    }

    /**
     * Get multiple organizations by UUIDs using batch API call.
     * Uses the same API client and patterns as wicket_get_organization().
     *
     * @param array $uuids Array of organization UUIDs.
     * @return array Array of organization data in the same format as wicket_get_organization().
     */
    public function getOrganizationsBatch($uuids)
    {
        if (empty($uuids)) {
            return [];
        }

        $logger = \Wicket()->log();

        // Get the same API client that wicket_get_organization uses
        $client = wicket_api_client();
        if (!$client) {
            $logger->error('Failed to get Wicket API client', ['source' => 'wicket-orgman']);

            return [];
        }

        try {
            // Use the proven batch method from our tests: POST /organizations/query with uuid_in filter
            $json_args = [
                'filter' => [
                    'uuid_in' => $uuids,
                ],
            ];

            $response = $client->post('organizations/query', ['json' => $json_args]);

            if (!$response || !isset($response['data'])) {
                $logger->warning('Batch call returned no data or invalid response', ['source' => 'wicket-orgman']);

                return [];
            }

            $organizations = [];
            foreach ($response['data'] as $org_data) {
                if (!empty($org_data['id']) && !empty($org_data['attributes'])) {
                    // Format the data exactly like wicket_get_organization() does
                    $organizations[] = [
                        'data' => [
                            'id' => $org_data['id'],
                            'attributes' => $org_data['attributes'],
                            'type' => $org_data['type'] ?? 'organizations',
                        ],
                    ];
                }
            }

            return $organizations;

        } catch (\Exception $e) {
            $logger->error('Batch API call failed: ' . $e->getMessage(), ['source' => 'wicket-orgman']);

            return [];
        }
    }

    /**
     * Get individual organization by UUID (wrapper around wicket_get_organization).
     * Used as fallback when batch fails.
     *
     * @param string $uuid Organization UUID.
     * @return array|false Organization data or false if not found.
     */
    public function getOrganizationIndividual($uuid)
    {
        if (!function_exists('wicket_get_organization')) {
            return false;
        }

        try {
            return wicket_get_organization($uuid);
        } catch (\Exception $e) {
            $logger = \Wicket()->log();
            $logger->error('Individual call failed for ' . $uuid . ': ' . $e->getMessage(), ['source' => 'wicket-orgman']);

            return false;
        }
    }
}
