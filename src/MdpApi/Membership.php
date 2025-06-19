<?php

declare(strict_types=1);

namespace WicketAcc\MdpApi;

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
                    'source' => __METHOD__,
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
                    'source' => __METHOD__,
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
     * `wicket_get_active_memberships` function using the new MdpApi structure.
     *
     * @param string $iso_code (Optional) ISO code for the language: en, fr, es, etc. Defaults to 'en'.
     * @return array An array of active membership summaries.
     */
    public function getCurrentPersonActiveMemberships(string $iso_code = 'en'): array
    {
        $membership_summary = [];
        $person_uuid = WACC()->MdpApi->Person->getCurrentPersonUuid();

        if (empty($person_uuid)) {
            WACC()->Log->debug('No current person UUID found, cannot fetch active memberships.', ['source' => __METHOD__]);

            return [];
        }

        $person_profile = WACC()->MdpApi->Person->getPersonProfileByUuid($person_uuid);

        if (!$person_profile || !isset($person_profile->included) || !is_array($person_profile->included)) {
            WACC()->Log->debug(
                'Person profile or included data not found/valid for active memberships.',
                ['source' => __METHOD__, 'person_uuid' => $person_uuid, 'profile_exists' => !empty($person_profile)]
            );

            return [];
        }

        $person_membership_entries = [];
        $membership_tiers_map = [];

        foreach ($person_profile->included as $included_item) {
            if (!isset($included_item->type, $included_item->id)) {
                continue;
            }
            // The SDK might use 'person_memberships' or 'person-memberships' as type
            if (strtolower($included_item->type) === 'person_memberships' || strtolower($included_item->type) === 'person-memberships') {
                // Ensure this person_membership entry actually belongs to the current person
                if (
                    isset($included_item->relationships->person->data->id) &&
                    $included_item->relationships->person->data->id === $person_uuid
                ) {
                    $person_membership_entries[] = $included_item;
                }
            } elseif (strtolower($included_item->type) === 'memberships') { // These are the membership tiers/plans
                $membership_tiers_map[$included_item->id] = $included_item;
            }
        }

        if (empty($person_membership_entries)) {
            WACC()->Log->debug('No relevant person_membership entries found in included data.', [
                'source' => __METHOD__,
                'person_uuid' => $person_uuid,
                'included_count' => count($person_profile->included),
            ]);

            return [];
        }

        foreach ($person_membership_entries as $entry) {
            if (!isset($entry->attributes->status) || strtolower($entry->attributes->status) !== 'active') {
                continue;
            }

            $membership_tier_id = $entry->relationships->membership->data->id ?? null;
            $membership_tier = $membership_tier_id ? ($membership_tiers_map[$membership_tier_id] ?? null) : null;

            if (!$membership_tier) {
                WACC()->Log->warning('Membership tier/plan details not found for active person_membership.', [
                    'source' => __METHOD__,
                    'person_uuid' => $person_uuid,
                    'person_membership_id' => $entry->id,
                    'membership_tier_id' => $membership_tier_id,
                ]);
                continue;
            }

            $name_property = 'name_' . strtolower($iso_code);
            $default_name_property = 'name_en'; // Fallback to English name

            $entry_summary = [
                'membership_category' => $entry->attributes->membership_category ?? null,
                'starts_at'           => $entry->attributes->starts_at ?? null,
                'ends_at'             => $entry->attributes->ends_at ?? null,
                'name'                => $membership_tier->attributes->$name_property ?? $membership_tier->attributes->$default_name_property ?? $membership_tier->attributes->name ?? 'N/A',
                'type'                => $membership_tier->attributes->type ?? null,
            ];

            if (isset($entry->relationships->organization_membership->data->id)) {
                $entry_summary['organization_membership_id'] = $entry->relationships->organization_membership->data->id;
            }

            $membership_summary[] = $entry_summary;
        }

        return $membership_summary;
    }

    /**
     * Returns active memberships for the current user from WooCommerce Memberships.
     *
     * @return array An array of active WooCommerce membership summaries, or an empty array if none are found
     *               or WooCommerce Memberships is not active/user not logged in.
     *               Each summary contains 'starts_at', 'ends_at', and 'name'.
     */
    /**
     * Returns active memberships relationship from wicket API.
     *
     * @param string $org_uuid The organization UUID
     * @return array $memberships relationship
     */
    public function getActiveMembershipRelationship(string $org_uuid): array
    {
        $person_type = '';
        $wicket_memberships = $this->getCurrentPersonMemberships();
        $person_uuid = $this->person->getPersonUuid();
        $org_info = [];

        if (!empty($wicket_memberships['included'])) {
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

                if (!empty($org_connections)) {
                    foreach ($org_connections as $org_connection) {
                        $person_to_org_uuid = $org_connection['relationships']['person']['data']['id'] ?? '';
                        if ($person_to_org_uuid == $person_uuid) {
                            $person_type = $org_connection['attributes']['type'] ?? '';
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

        if (!function_exists('WACC') || !WACC()->isWooCommerceActive() || !function_exists('wc_memberships_get_user_memberships')) {
            if (function_exists('WACC') && WACC()->Log) {
                WACC()->Log->debug('WooCommerce or WC Memberships not active, cannot fetch Woo memberships.', ['source' => __METHOD__]);
            }

            return [];
        }

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            if (function_exists('WACC') && WACC()->Log) {
                WACC()->Log->debug('No current user ID, cannot fetch Woo memberships.', ['source' => __METHOD__]);
            }

            return [];
        }

        $args = [
            'status' => ['active', 'complimentary'],
        ];

        /** @disregard P1010 Undefined function */
        $wc_user_memberships = \wc_memberships_get_user_memberships($current_user_id, $args);

        if (empty($wc_user_memberships)) {
            return [];
        }

        foreach ($wc_user_memberships as $membership_obj) {
            if (!is_object($membership_obj) || !method_exists($membership_obj, 'get_plan') || !is_object($membership_obj->get_plan()) || !method_exists($membership_obj->get_plan(), 'get_name')) {
                if (function_exists('WACC') && WACC()->Log) {
                    WACC()->Log->warning('Invalid WooCommerce membership object encountered.', ['source' => __METHOD__, 'membership_object_type' => gettype($membership_obj)]);
                }
                continue;
            }
            $membership_summary[] = [
                'starts_at' => $membership_obj->get_start_date(),
                'ends_at'   => $membership_obj->get_end_date(),
                'name'      => $membership_obj->get_plan()->get_name(),
            ];
        }

        return $membership_summary;
    }
}
