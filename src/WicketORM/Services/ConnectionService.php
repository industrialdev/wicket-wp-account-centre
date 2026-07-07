<?php

/**
 * Connection Service for Org Management.
 */

declare(strict_types=1);

namespace WicketORM\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles membership and connection helpers against the Wicket API.
 */
class ConnectionService
{
    /**
     * @var array
     */
    private array $config;

    public function __construct()
    {
        $this->config = ConfigService::getConfig();
    }

    /**
     * Determine if a person belongs to a given organization membership.
     *
     * @param string $personUuid
     * @param string $membershipUuid
     * @return bool|WP_Error
     */
    public function personHasMembership(string $personUuid, string $membershipUuid)
    {
        return wicket_person_has_membership($personUuid, $membershipUuid);
    }

    /**
     * Ensure a person-to-organization connection exists.
     *
     * @param string $personUuid
     * @param string $orgUuid
     * @param array  $overrides Optional overrides including 'type' for relationship type
     * @return true|WP_Error
     */
    public function ensurePersonConnection(string $personUuid, string $orgUuid, array $overrides = [])
    {
        $relationshipType = $overrides['type'] ?? \WicketORM\Helpers\RelationshipHelper::get_default_relationship_type();
        unset($overrides['type']);

        $atts = array_merge(['connection_type' => 'person_to_organization'], $overrides);

        return wicket_ensure_person_org_connection($personUuid, $orgUuid, $relationshipType, $atts);
    }

    /**
     * Current point-in-time timestamp in UTC.
     *
     * @return string
     */
    private function currentStartDate(): string
    {
        return wicket_time_get_current_iso8601_utc();
    }

    /**
     * Current UTC day-start timestamp.
     *
     * @return string
     */
    private function currentDayStartDate(): string
    {
        return wicket_time_get_mdp_day_start_iso8601_utc();
    }

    /**
     * Resolve relationship-removal anchor.
     *
     * @return string
     */
    private function getRemovalAnchor(): string
    {
        $relationship_anchor = $this->config['relationships']['removal']['end_date_anchor'] ?? null;
        if (is_string($relationship_anchor) && trim($relationship_anchor) !== '') {
            return sanitize_key($relationship_anchor);
        }

        return sanitize_key((string) ($this->config['removal']['end_date_anchor'] ?? 'action_time'));
    }

    /**
     * Get person connections by UUID.
     *
     * @param string $person_uuid The person UUID
     * @return array|false The person connections or false if not found
     */
    public function getPersonConnectionsById($person_uuid)
    {
        if (empty($person_uuid) || !function_exists('wicket_api_client')) {
            return false;
        }

        try {
            $client = wicket_api_client();
            $response = $client->get('people/' . rawurlencode($person_uuid) . '/connections?filter%5Bconnection_type_eq%5D=all&sort=-created_at');

            if (!isset($response['data']) || !is_array($response['data'])) {
                return false;
            }

            return $response;
        } catch (\Throwable $e) {
            \Wicket()->log()->error('Failed to get person connections: ' . $e->getMessage(), ['source' => 'wicket-orgman']);

            return false;
        }
    }

    /**
     * End a person's relationship with an organization today.
     *
     * @param string $person_uuid The UUID of the person.
     * @param string $relationship_id The ID of the relationship to end.
     * @param string $org_id The ID of the organization.
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public function endRelationshipToday($person_uuid, $relationship_id, $org_id)
    {
        if (empty($person_uuid) || empty($relationship_id) || empty($org_id)) {
            return new WP_Error('invalid_params', 'Person UUID, relationship ID, and organization ID are required.');
        }

        $ends_at = $this->getRemovalAnchor() === 'day_start_utc'
            ? $this->currentDayStartDate()
            : $this->currentStartDate();

        return wicket_end_person_org_connection($person_uuid, $relationship_id, $org_id, ['ends_at' => $ends_at]);
    }

    /**
     * End a person's relationship with an organization at the current action time.
     *
     * @param string $person_uuid The UUID of the person.
     * @param string $relationship_id The ID of the relationship to end.
     * @param string $org_id The ID of the organization.
     * @return array|WP_Error The updated connection data or WP_Error on failure.
     */
    public function endRelationshipAtActionTime($person_uuid, $relationship_id, $org_id)
    {
        if (empty($person_uuid) || empty($relationship_id) || empty($org_id)) {
            return new WP_Error('invalid_params', 'Person UUID, relationship ID, and organization ID are required.');
        }

        try {
            $client = wicket_api_client();

            $connection = wicket_get_connection_by_id($relationship_id);
            if (!$connection || empty($connection['data'])) {
                return new WP_Error('connection_not_found', 'Connection not found.');
            }

            $connection_data = $connection['data'];
            $attributes = $connection_data['attributes'];
            $ends_at = $this->currentStartDate();

            $attributes['tags'] = !empty($attributes['tags']) ? $attributes['tags'] : [];
            if ($attributes['tags'] === null) {
                $attributes['tags'] = [];
            }

            $attributes['description'] = !empty($attributes['description']) ? $attributes['description'] : null;
            $attributes['custom_data_field'] = !empty($attributes['custom_data_field']) ? $attributes['custom_data_field'] : null;

            $update_payload = [
                'data' => [
                    'type'          => $connection_data['type'],
                    'id'            => $relationship_id,
                    'attributes'    => [
                        'type'              => $attributes['type'],
                        'starts_at'         => $attributes['starts_at'],
                        'ends_at'           => $ends_at,
                        'description'       => $attributes['description'],
                        'tags'              => $attributes['tags'],
                        'custom_data_field' => $attributes['custom_data_field'],
                    ],
                    'relationships' => [
                        'from' => [
                            'data' => [
                                'type' => $connection_data['relationships']['from']['data']['type'],
                                'id'   => $connection_data['relationships']['from']['data']['id'],
                                'meta' => [
                                    'can_manage' => true,
                                    'can_update' => true,
                                ],
                            ],
                        ],
                        'to'   => [
                            'data' => [
                                'type' => $connection_data['relationships']['to']['data']['type'],
                                'id'   => $connection_data['relationships']['to']['data']['id'],
                            ],
                        ],
                    ],
                ],
            ];

            return $client->patch("connections/{$relationship_id}", ['json' => $update_payload]);
        } catch (\Throwable $e) {
            return new WP_Error('update_connection_exception', $e->getMessage());
        }
    }

    /**
     * Builds a payload for creating a new connection between a person and an organization.
     *
     * This method provides backward compatibility with the legacy function signature.
     *
     * @param string $person_id The UUID of the person to connect to the organization.
     * @param string $org_id The UUID of the organization to connect the person to.
     * @param string $connection_type The type of connection to create (e.g., 'person_to_organization').
     * @param string $type The specific type of relationship (e.g., 'Position').
     * @param string|null $description Optional relationship description.
     * @return array|WP_Error The connection payload or WP_Error on failure.
     */
    public function buildConnectionPayload($person_id = null, $org_id = null, $connection_type = null, $type = null, $description = null)
    {
        if (empty($person_id) || empty($org_id) || empty($connection_type) || empty($type)) {
            return new WP_Error('invalid_params', 'Person ID, organization ID, connection type, and type are required.');
        }

        try {
            $now_date = $this->currentStartDate();

            $description = is_string($description) ? sanitize_textarea_field($description) : '';
            $payload = [
                'data' => [
                    'type'          => 'connections',
                    'attributes'    => [
                        'connection_type' => $connection_type,
                        'type'            => $type,
                        'starts_at'       => $now_date,
                    ],
                    'relationships' => [
                        'organization' => [
                            'data' => [
                                'id'   => $org_id,
                                'type' => 'organizations',
                            ],
                        ],
                        'person'       => [
                            'data' => [
                                'id'   => $person_id,
                                'type' => 'people',
                            ],
                        ],
                        'from'         => [
                            'data' => [
                                'id'   => $person_id,
                                'type' => 'people',
                            ],
                        ],
                        'to'           => [
                            'data' => [
                                'id'   => $org_id,
                                'type' => 'organizations',
                            ],
                        ],
                    ],
                ],
            ];

            if ($description !== '') {
                $payload['data']['attributes']['description'] = $description;
            }

            return $payload;

        } catch (\Exception $e) {
            \Wicket()->log()->error('ConnectionService::buildConnectionPayload() - Exception: ' . $e->getMessage(), ['source' => 'wicket-orgman']);

            return new WP_Error('build_payload_exception', $e->getMessage());
        }
    }

    /**
     * Update the relationship description for a person-to-organization connection.
     *
     * @param string $person_uuid The UUID of the person.
     * @param string $org_id The UUID of the organization.
     * @param string $description The new relationship description.
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public function updateConnectionDescription($person_uuid, $org_id, $description)
    {
        if (empty($person_uuid) || empty($org_id)) {
            return new WP_Error('invalid_params', 'Person UUID and organization ID are required.');
        }

        if (!function_exists('wicket_api_client')) {
            return new WP_Error('missing_dependency', 'Wicket API client is unavailable.');
        }

        try {
            $connections = $this->getPersonConnectionsById($person_uuid);

            if (empty($connections['data'])) {
                return new WP_Error('no_connection', 'No connection found for this person and organization.');
            }

            $connection_ids = [];
            foreach ($connections['data'] as $connection) {
                if (
                    isset($connection['relationships']['organization']['data']['id'])
                    && $connection['relationships']['organization']['data']['id'] === $org_id
                    && isset($connection['attributes']['connection_type'])
                    && $connection['attributes']['connection_type'] === 'person_to_organization'
                ) {
                    $connection_ids[] = $connection['id'];
                }
            }

            if (empty($connection_ids)) {
                return new WP_Error('no_connection', 'No active person-to-organization connection found.');
            }

            $client = wicket_api_client();
            $description = is_string($description) ? sanitize_textarea_field($description) : '';
            $description = $description !== '' ? $description : null;

            foreach ($connection_ids as $connection_id) {
                $connection = wicket_get_connection_by_id($connection_id);

                if (!$connection || empty($connection['data'])) {
                    continue;
                }

                $connection_data = $connection['data'];
                $attributes = $connection_data['attributes'];

                if (empty($attributes['resource_type'])) {
                    if (!empty($connection_data['relationships']['organization']['data']['type'])) {
                        $attributes['resource_type'] = $connection_data['relationships']['organization']['data']['type'];
                    } elseif (!empty($connection_data['relationships']['to']['data']['type'])) {
                        $attributes['resource_type'] = $connection_data['relationships']['to']['data']['type'];
                    } elseif (($attributes['connection_type'] ?? '') === 'person_to_organization') {
                        $attributes['resource_type'] = 'organizations';
                    }
                }
                if (empty($attributes['resource_type'])) {
                    $attributes['resource_type'] = 'organizations';
                }

                $attributes['tags'] = !empty($attributes['tags']) ? $attributes['tags'] : [];
                if ($attributes['tags'] === null) {
                    $attributes['tags'] = [];
                }

                $attributes['custom_data_field'] = !empty($attributes['custom_data_field']) ? $attributes['custom_data_field'] : null;

                $relationships = [
                    'from' => [
                        'data' => [
                            'type' => $connection_data['relationships']['from']['data']['type'],
                            'id'   => $connection_data['relationships']['from']['data']['id'],
                            'meta' => [
                                'can_manage' => true,
                                'can_update' => true,
                            ],
                        ],
                    ],
                    'to'   => [
                        'data' => [
                            'type' => $connection_data['relationships']['to']['data']['type'],
                            'id'   => $connection_data['relationships']['to']['data']['id'],
                        ],
                    ],
                ];
                if (!empty($connection_data['relationships']['organization']['data'])) {
                    $relationships['organization'] = [
                        'data' => [
                            'type' => $connection_data['relationships']['organization']['data']['type'],
                            'id'   => $connection_data['relationships']['organization']['data']['id'],
                        ],
                    ];
                }
                if (!empty($connection_data['relationships']['person']['data'])) {
                    $relationships['person'] = [
                        'data' => [
                            'type' => $connection_data['relationships']['person']['data']['type'],
                            'id'   => $connection_data['relationships']['person']['data']['id'],
                        ],
                    ];
                }

                $update_payload = [
                    'data' => [
                        'type'          => $connection_data['type'],
                        'id'            => $connection_id,
                        'attributes'    => [
                            'connection_type'   => $attributes['connection_type'] ?? 'person_to_organization',
                            'resource_type'     => $attributes['resource_type'] ?? null,
                            'type'              => $attributes['type'],
                            'starts_at'         => $attributes['starts_at'],
                            'ends_at'           => $attributes['ends_at'] ?? null,
                            'description'       => $description,
                            'tags'              => $attributes['tags'],
                            'custom_data_field' => $attributes['custom_data_field'],
                        ],
                        'relationships' => $relationships,
                    ],
                ];

                $response = $client->patch("connections/{$connection_id}", ['json' => $update_payload]);

                if (!empty($response['errors'])) {
                    \Wicket()->log()->error('ConnectionService::updateConnectionDescription() - API error: ' . json_encode($response['errors']), ['source' => 'wicket-orgman']);

                    return new WP_Error('api_error', 'Failed to update connection description: ' . ($response['errors'][0]['detail'] ?? 'Unknown error'));
                }
            }

            return true;

        } catch (\Exception $e) {
            \Wicket()->log()->error('ConnectionService::updateConnectionDescription() - Exception: ' . $e->getMessage(), ['source' => 'wicket-orgman']);

            return new WP_Error('update_connection_exception', $e->getMessage());
        }
    }

    /**
     * Creates a new connection in the API.
     *
     * This method provides backward compatibility with the legacy function signature.
     *
     * @param array $payload The connection payload to send to the API.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function createConnection($payload)
    {
        if (empty($payload) || !is_array($payload)) {
            return new WP_Error('invalid_params', 'Valid payload array is required.');
        }

        if (!function_exists('wicket_api_client')) {
            return new WP_Error('missing_dependency', 'Wicket API client is unavailable.');
        }

        try {
            $client = wicket_api_client();
            $response = $client->post('connections', ['json' => $payload]);

            // If we get here without an exception, the connection was created successfully
            return true;

        } catch (\Exception $e) {
            $error_message = $e->getMessage();

            // Try to extract more detailed error information from the response
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                try {
                    $error_body = json_decode($e->getResponse()->getBody(), true);
                    if (isset($error_body['errors']) && !empty($error_body['errors'])) {
                        $error_message = $error_body['errors'][0]['detail'] ?? $error_message;
                    }
                } catch (\Exception $json_error) {
                    // If we can't parse the JSON, just use the original error message
                    \Wicket()->log()->error('ConnectionService::createConnection() - JSON parse error: ' . $json_error->getMessage(), ['source' => 'wicket-orgman']);
                }
            }

            \Wicket()->log()->error('ConnectionService::createConnection() - Exception: ' . $error_message, ['source' => 'wicket-orgman']);

            return new WP_Error('connection_creation_failed', $error_message);
        }
    }

    /**
     * Check if a person has a relationship with an organization.
     *
     * This method provides backward compatibility with the legacy function signature.
     *
     * @param string $person_uuid The UUID of the person.
     * @param string $org_id The UUID of the organization.
     * @return bool|WP_Error True if person has relationship, false if not, WP_Error on failure.
     */
    public function personHasRelationship($person_uuid, $org_id)
    {
        if (empty($person_uuid) || empty($org_id)) {
            return new WP_Error('invalid_params', 'Person UUID and organization ID are required.');
        }

        if (!function_exists('wicket_api_client')) {
            return new WP_Error('missing_dependency', 'Wicket API client is unavailable.');
        }

        try {
            $client = wicket_api_client();
            $response = $client->get("people/{$person_uuid}/connections?page[number]=1&page[size]=30&filter[connection_type_eq]=all&filter[active_true]=true&sort=");

            if (isset($response['data']) && !empty($response['data'])) {
                foreach ($response['data'] as $connection) {
                    // Check type, connection type, and the organization ID within the relationships object
                    if (
                        $connection['type'] == 'connections'
                        && isset($connection['attributes']['connection_type'])
                        && $connection['attributes']['connection_type'] == 'person_to_organization'
                        && isset($connection['relationships']['organization']['data']['id'])
                        && $connection['relationships']['organization']['data']['id'] == $org_id
                    ) {
                        return true;
                    }
                }
            }

            return false;

        } catch (\Exception $e) {
            \Wicket()->log()->error('ConnectionService::personHasRelationship() - Exception: ' . $e->getMessage(), ['source' => 'wicket-orgman']);

            return new WP_Error('relationship_check_failed', $e->getMessage());
        }
    }

    /**
     * Get active person-to-organization connections for a person and organization.
     *
     * @param string $person_uuid
     * @param string $org_id
     * @return array|WP_Error
     */
    public function getActivePersonOrganizationConnections($person_uuid, $org_id)
    {
        if (empty($person_uuid) || empty($org_id)) {
            return new WP_Error('invalid_params', 'Person UUID and organization ID are required.');
        }

        $result = wicket_get_active_person_org_connections($person_uuid, $org_id);

        $logger = \Wicket()->log();
        if ($logger && is_array($result)) {
            $logger->debug('[OrgMan] Active person-org connections resolved', [
                'source'                 => 'wicket-orgman',
                'person_uuid'            => $person_uuid,
                'org_id'                 => $org_id,
                'active_connection_count' => count($result),
            ]);
        }

        return $result;
    }

    /**
     * End-date all active person-to-organization connections for a person and organization.
     *
     * @param string   $person_uuid
     * @param string   $org_id
     * @param string[] $skip_types  Relationship type slugs to leave untouched (e.g. ['company_admin']).
     * @return array|WP_Error
     */
    public function endActivePersonOrganizationConnections($person_uuid, $org_id, array $skip_types = [])
    {
        $logger = \Wicket()->log();
        $log_context = [
            'source' => 'wicket-orgman',
            'service' => 'connection',
            'action' => 'end_active_person_org_connections',
            'person_uuid' => $person_uuid,
            'org_id' => $org_id,
        ];

        if ($logger) {
            $logger->info('[OrgMan] End active connections started', $log_context + [
                'skip_types_raw' => array_values($skip_types),
            ]);
        }

        $connections = $this->getActivePersonOrganizationConnections($person_uuid, $org_id);
        if (is_wp_error($connections)) {
            if ($logger) {
                $logger->error('[OrgMan] End active connections aborted: active connection lookup error', $log_context + [
                    'error_code' => $connections->get_error_code(),
                    'error_message' => $connections->get_error_message(),
                ]);
            }

            return $connections;
        }

        $normalized_skip_types = $this->normalizeSkipTypes($skip_types);

        $ended_ids = [];
        $skipped_ids = [];
        $evaluated = [];
        foreach ($connections as $connection) {
            $connection_id = (string) ($connection['id'] ?? '');
            if ($connection_id === '') {
                continue;
            }

            $raw_relationship_type = (string) ($connection['attributes']['type'] ?? '');
            $normalized_relationship_type = sanitize_key(str_replace(['-', ' '], '_', strtolower(trim($raw_relationship_type))));

            // Leave protected relationship types intact so they are not destroyed during repair.
            // Match by exact raw value OR normalized slug to handle API formatting variants.
            if (!empty($skip_types)) {
                if (
                    in_array($raw_relationship_type, $skip_types, true)
                    || in_array($normalized_relationship_type, $normalized_skip_types, true)
                ) {
                    $skipped_ids[] = $connection_id;
                    $evaluated[] = [
                        'connection_id' => $connection_id,
                        'type_raw' => $raw_relationship_type,
                        'type_normalized' => $normalized_relationship_type,
                        'decision' => 'skipped_protected_type',
                    ];
                    continue;
                }
            }

            $result = $this->endRelationshipToday($person_uuid, $connection_id, $org_id);
            if (is_wp_error($result)) {
                if ($logger) {
                    $logger->error('[OrgMan] End active connections failed while ending connection', $log_context + [
                        'connection_id' => $connection_id,
                        'type_raw' => $raw_relationship_type,
                        'type_normalized' => $normalized_relationship_type,
                        'error_code' => $result->get_error_code(),
                        'error_message' => $result->get_error_message(),
                    ]);
                }

                return $result;
            }

            $ended_ids[] = $connection_id;
            $evaluated[] = [
                'connection_id' => $connection_id,
                'type_raw' => $raw_relationship_type,
                'type_normalized' => $normalized_relationship_type,
                'decision' => 'ended',
            ];
        }

        if ($logger) {
            $logger->info('[OrgMan] End active connections completed', $log_context + [
                'skip_types_raw' => array_values($skip_types),
                'skip_types_normalized' => $normalized_skip_types,
                'evaluated_count' => count($evaluated),
                'ended_count' => count($ended_ids),
                'skipped_count' => count($skipped_ids),
                'ended_connection_ids' => $ended_ids,
                'skipped_connection_ids' => $skipped_ids,
                'evaluated_connections' => $evaluated,
            ]);
        }

        return [
            'count' => count($ended_ids),
            'connection_ids' => $ended_ids,
        ];
    }

    /**
     * Continue-on-error counterpart to endActivePersonOrganizationConnections().
     *
     * Ends every active person-to-organization connection for a person, honoring
     * the same protected-type skip list, but attempts every record regardless of
     * per-record failure. Used by removal flows (modal/strategies) that must keep
     * going when one connection cannot be ended, matching the modal's parity.
     *
     * The existing endActivePersonOrganizationConnections() stays fail-fast because
     * the add-path stale-relationship repair depends on early abort semantics.
     *
     * @param string $person_uuid The UUID of the person.
     * @param string $org_id      The organization ID.
     * @param array  $skip_types  Relationship types to leave intact.
     * @return array{ended: list<string>, errors: array<string,string>}
     *   ended:  connection IDs that were successfully end-dated
     *   errors: map of connection_id => error message (non-fatal)
     */
    public function endAllActivePersonOrganizationConnections($person_uuid, $org_id, array $skip_types = [])
    {
        $ended = [];
        $errors = [];

        $connections = $this->getActivePersonOrganizationConnections($person_uuid, $org_id);
        if (is_wp_error($connections)) {
            \Wicket()->log()->error('[OrgMan] endAllActivePersonOrganizationConnections aborted: active connection lookup error', [
                'source' => 'wicket-orgman',
                'person_uuid' => $person_uuid,
                'org_id' => $org_id,
                'error_code' => $connections->get_error_code(),
                'error_message' => $connections->get_error_message(),
            ]);

            return ['ended' => $ended, 'errors' => $errors];
        }

        $normalized_skip_types = $this->normalizeSkipTypes($skip_types);

        foreach ($connections as $connection) {
            $connection_id = (string) ($connection['id'] ?? '');
            if ($connection_id === '') {
                continue;
            }

            $raw_relationship_type = (string) ($connection['attributes']['type'] ?? '');
            $normalized_relationship_type = sanitize_key(str_replace(['-', ' '], '_', strtolower(trim($raw_relationship_type))));

            // Leave protected relationship types intact.
            if (!empty($skip_types)) {
                if (
                    in_array($raw_relationship_type, $skip_types, true)
                    || in_array($normalized_relationship_type, $normalized_skip_types, true)
                ) {
                    continue;
                }
            }

            $result = $this->endRelationshipToday($person_uuid, $connection_id, $org_id);
            if (is_wp_error($result)) {
                $errors[$connection_id] = $result->get_error_message();
                \Wicket()->log()->error('[OrgMan] endAllActivePersonOrganizationConnections failed to end connection', [
                    'source' => 'wicket-orgman',
                    'connection_id' => $connection_id,
                    'error_code' => $result->get_error_code(),
                    'error_message' => $result->get_error_message(),
                ]);

                continue;
            }

            $ended[] = $connection_id;
        }

        \Wicket()->log()->info('[OrgMan] endAllActivePersonOrganizationConnections result', [
            'source' => 'wicket-orgman',
            'person_uuid' => $person_uuid,
            'org_id' => $org_id,
            'skip_types' => array_values($skip_types),
            'ended_count' => count($ended),
            'ended_ids' => $ended,
            'error_count' => count($errors),
            'errors' => $errors,
        ]);

        return ['ended' => $ended, 'errors' => $errors];
    }

    /**
     * Normalize a list of relationship-type skip entries into unique sanitized slugs.
     * Shared by both end-active-connections methods (fail-fast and continue-on-error).
     *
     * @param array $skip_types
     * @return list<string>
     */
    private function normalizeSkipTypes(array $skip_types): array
    {
        return array_values(array_unique(array_filter(array_map(static function ($type): string {
            $normalized = strtolower(trim((string) $type));
            $normalized = str_replace(['-', ' '], '_', $normalized);

            return sanitize_key($normalized);
        }, $skip_types))));
    }

    /**
     * Update the relationship type for a person-to-organization connection.
     *
     * @param string $person_uuid The UUID of the person.
     * @param string $org_id The UUID of the organization.
     * @param string $new_type The new relationship type.
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public function updateConnectionType($person_uuid, $org_id, $new_type)
    {
        if (empty($person_uuid) || empty($org_id) || empty($new_type)) {
            return new WP_Error('invalid_params', 'Person UUID, organization ID, and new type are required.');
        }

        if (!function_exists('wicket_api_client')) {
            return new WP_Error('missing_dependency', 'Wicket API client is unavailable.');
        }

        try {
            // Get the person's active connections to this organization
            $connections = $this->getPersonConnectionsById($person_uuid);

            if (empty($connections['data'])) {
                return new WP_Error('no_connection', 'No connection found for this person and organization.');
            }

            // Find the matching connection(s)
            $connection_ids = [];
            foreach ($connections['data'] as $connection) {
                if (
                    isset($connection['relationships']['organization']['data']['id'])
                    && $connection['relationships']['organization']['data']['id'] === $org_id
                    && isset($connection['attributes']['connection_type'])
                    && $connection['attributes']['connection_type'] === 'person_to_organization'
                ) {
                    $connection_ids[] = $connection['id'];
                }
            }

            if (empty($connection_ids)) {
                return new WP_Error('no_connection', 'No active person-to-organization connection found.');
            }

            $client = wicket_api_client();

            // Update each connection (usually just one, but handle multiple)
            foreach ($connection_ids as $connection_id) {
                // Get the full connection details
                $connection = wicket_get_connection_by_id($connection_id);

                if (!$connection || empty($connection['data'])) {
                    continue;
                }

                $connection_data = $connection['data'];
                $attributes = $connection_data['attributes'];

                // Update the type
                $attributes['type'] = $new_type;
                if (empty($attributes['resource_type'])) {
                    if (!empty($connection_data['relationships']['organization']['data']['type'])) {
                        $attributes['resource_type'] = $connection_data['relationships']['organization']['data']['type'];
                    } elseif (!empty($connection_data['relationships']['to']['data']['type'])) {
                        $attributes['resource_type'] = $connection_data['relationships']['to']['data']['type'];
                    } elseif (($attributes['connection_type'] ?? '') === 'person_to_organization') {
                        $attributes['resource_type'] = 'organizations';
                    }
                }
                if (empty($attributes['resource_type'])) {
                    $attributes['resource_type'] = 'organizations';
                }

                // Fix tags, if empty or null, make it an empty array
                $attributes['tags'] = !empty($attributes['tags']) ? $attributes['tags'] : [];
                if ($attributes['tags'] === null) {
                    $attributes['tags'] = [];
                }

                // Ensure empty fields stay null
                $attributes['description'] = !empty($attributes['description']) ? $attributes['description'] : null;
                $attributes['custom_data_field'] = !empty($attributes['custom_data_field']) ? $attributes['custom_data_field'] : null;

                $relationships = [
                    'from' => [
                        'data' => [
                            'type' => $connection_data['relationships']['from']['data']['type'],
                            'id'   => $connection_data['relationships']['from']['data']['id'],
                            'meta' => [
                                'can_manage' => true,
                                'can_update' => true,
                            ],
                        ],
                    ],
                    'to'   => [
                        'data' => [
                            'type' => $connection_data['relationships']['to']['data']['type'],
                            'id'   => $connection_data['relationships']['to']['data']['id'],
                        ],
                    ],
                ];
                if (!empty($connection_data['relationships']['organization']['data'])) {
                    $relationships['organization'] = [
                        'data' => [
                            'type' => $connection_data['relationships']['organization']['data']['type'],
                            'id'   => $connection_data['relationships']['organization']['data']['id'],
                        ],
                    ];
                }
                if (!empty($connection_data['relationships']['person']['data'])) {
                    $relationships['person'] = [
                        'data' => [
                            'type' => $connection_data['relationships']['person']['data']['type'],
                            'id'   => $connection_data['relationships']['person']['data']['id'],
                        ],
                    ];
                }

                $update_payload = [
                    'data' => [
                        'type'          => $connection_data['type'],
                        'id'            => $connection_id,
                        'attributes'    => [
                            'connection_type'   => $attributes['connection_type'] ?? 'person_to_organization',
                            'resource_type'     => $attributes['resource_type'] ?? null,
                            'type'              => $new_type,
                            'starts_at'         => $attributes['starts_at'],
                            'ends_at'           => $attributes['ends_at'] ?? null,
                            'description'       => $attributes['description'],
                            'tags'              => $attributes['tags'],
                            'custom_data_field' => $attributes['custom_data_field'],
                        ],
                        'relationships' => $relationships,
                    ],
                ];

                // Update the connection
                $response = $client->patch("connections/{$connection_id}", ['json' => $update_payload]);

                if (!empty($response['errors'])) {
                    \Wicket()->log()->error('ConnectionService::updateConnectionType() - API error: ' . json_encode($response['errors']), ['source' => 'wicket-orgman']);

                    return new WP_Error('api_error', 'Failed to update connection type: ' . ($response['errors'][0]['detail'] ?? 'Unknown error'));
                }
            }

            return true;

        } catch (\Exception $e) {
            \Wicket()->log()->error('ConnectionService::updateConnectionType() - Exception: ' . $e->getMessage(), ['source' => 'wicket-orgman']);

            return new WP_Error('update_connection_exception', $e->getMessage());
        }
    }

    /**
     * Get active connections for an organization, optionally filtered by relationship types.
     *
     * Uses API-side filtering via filter[resource_type_slug_in] for performance.
     * New method for contacts roster. Does not modify any existing method.
     *
     * @param string $org_uuid   Organization UUID.
     * @param array  $filters    Optional: ['resource_type_slugs' => ['president', ...], 'active' => true].
     * @param array  $pagination Optional: ['page' => 1, 'size' => 10].
     * @return array ['data' => [...], 'meta' => [...]]|WP_Error
     */
    public function getOrgConnections(string $org_uuid, array $filters = [], array $pagination = []): array|WP_Error
    {
        if (empty($org_uuid) || !function_exists('wicket_api_client')) {
            return new WP_Error('invalid_params', 'Organization UUID and Wicket API client are required.');
        }

        $page = max(1, (int) ($pagination['page'] ?? 1));
        $size = max(1, (int) ($pagination['size'] ?? 10));

        // Build query string manually: Ransack's _in predicate requires repeated
        // filter[..._in][]=value params (array notation), not a comma-separated string.
        // http_build_query adds numeric indices ([0], [1]) which Rails parses as a hash,
        // so we build the [] form by hand.
        $query_parts = [
            'page[number]=' . $page,
            'page[size]=' . $size,
            'include=person',
        ];

        // Active filter: use the ransackable 'active_at' scope (not 'active_true',
        // which is not a valid predicate and is silently ignored by Ransack).
        $active = $filters['active'] ?? true;
        if ($active) {
            $query_parts[] = 'filter[active_at]=' . rawurlencode(gmdate('Y-m-d\TH:i:s\Z'));
        }

        // Server-side type filtering via resource_type_slug_in (array notation)
        $type_slugs = $filters['resource_type_slugs'] ?? [];
        if (!empty($type_slugs) && is_array($type_slugs)) {
            foreach (array_map('sanitize_key', $type_slugs) as $slug) {
                if ($slug !== '') {
                    $query_parts[] = 'filter[resource_type_slug_in][]=' . rawurlencode($slug);
                }
            }
        }

        try {
            $client = wicket_api_client();
            $endpoint = '/organizations/' . rawurlencode($org_uuid) . '/connections?' . implode('&', $query_parts);
            $response = $client->get($endpoint);

            if (is_wp_error($response)) {
                return $response;
            }

            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            $included = is_array($response['included'] ?? null) ? $response['included'] : [];
            $page_meta = is_array($response['meta']['page'] ?? null) ? $response['meta']['page'] : [];

            return [
                'data'     => $data,
                'included' => $included,
                'meta'     => [
                    'page'        => $page,
                    'size'        => $size,
                    'total_items' => (int) ($page_meta['total_items'] ?? count($data)),
                    'total_pages' => (int) ($page_meta['total_pages'] ?? 1),
                ],
            ];
        } catch (\Throwable $e) {
            \Wicket()->log()->error('ConnectionService::getOrgConnections() failed: ' . $e->getMessage(), [
                'source'   => 'wicket-orgman',
                'org_uuid' => $org_uuid,
            ]);

            return new WP_Error('api_error', 'Failed to fetch organization connections.');
        }
    }
}
