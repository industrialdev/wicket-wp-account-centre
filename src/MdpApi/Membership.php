<?php

declare(strict_types=1);

namespace WicketAcc\MdpApi;

use Exception;

// No direct access
defined('ABSPATH') || exit;

/**
 * Handles MDP Membership related API endpoints.
 */
class Membership extends Init
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get a single organization membership by its UUID.
     *
     * @param string $uuid The membership UUID.
     * @return array|false The membership data array or false on failure.
     */
    /**
     * Get a single organization membership by its UUID.
     *
     * @param string $uuid The membership UUID.
     * @return array|false The membership data array or false on failure.
     */
    public function getOrganizationMembershipByUuid(string $uuid): array|false
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
            // Assuming the Wicket SDK client's 'get' method returns an array or throws an exception.
            return $client->get("organization_memberships/{$uuid}");
        } catch (Exception $e) {
            WACC()->Log->error(
                'API Exception while fetching organization membership by UUID.',
                [
                    'source' => __METHOD__,
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
            WACC()->Log->warning('Organization UUID cannot be empty.', ['source' => __METHOD__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            WACC()->Log->error('Failed to initialize API client.', ['source' => __METHOD__, 'org_uuid' => $org_uuid]);

            return false;
        }

        try {
            $org_memberships_response = $client->get("/organizations/{$org_uuid}/membership_entries?sort=-ends_at&include=membership");

            if (!isset($org_memberships_response['data'])) {
                WACC()->Log->warning(
                    'API response for organization memberships did not contain a data key.',
                    ['source' => __METHOD__, 'org_uuid' => $org_uuid, 'response' => $org_memberships_response]
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
                    'source' => __METHOD__,
                    'org_uuid' => $org_uuid,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return false;
        }
    }
}
