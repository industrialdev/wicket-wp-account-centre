<?php

declare(strict_types=1);

namespace WicketAcc\Mdp;

use Exception;
use GuzzleHttp\Exception\RequestException;

// No direct access
defined('ABSPATH') || exit;

/**
 * Handles MDP Touchpoint related API endpoints.
 */
class Touchpoint extends Init
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get a user's touchpoints for a given service ID.
     *
     * @param string $service_id The service ID to filter touchpoints by.
     * @param array  $options    Optional. Array of options. Supported keys: {
     *                           - 'person_uuid': The person's UUID. If not provided, the current user's UUID is used.
     *                           - 'mode': 'upcoming' (default) or 'past'.
     * }
     * @return array|false An array of touchpoints or false on failure.
     */
    public function getCurrentUserTouchpoints(string $serviceId, array $options = []): array|false
    {
        if (empty($serviceId)) {
            WACC()->Log()->warning('Service ID cannot be empty.', ['source' => __CLASS__]);

            return false;
        }

        $pId = $options['person_uuid'] ?? $this->Person->getCurrentPersonUuid();

        if (empty($pId)) {
            WACC()->Log()->warning('Person UUID could not be determined.', ['source' => __CLASS__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            // initClient already logs the error, so we just return.
            return false;
        }

        try {
            $params = [
                'page[size]' => 20,
                'filter[service_id]' => $serviceId,
            ];

            // Date-based filtering
            $today = gmdate('Y-m-d');
            $mode = $options['mode'] ?? 'upcoming';
            if ($mode === 'past') {
                $params['filter[end_date]'] = $today;
            } else {
                $params['filter[start_date]'] = $today;
            }
            $response = $client->get("people/{$pId}/touchpoints", ['query' => $params]);

            return $response['data'] ?? [];
        } catch (RequestException $e) {
            $responseCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            WACC()->Log()->error(
                'Touchpoint API request failed.',
                [
                    'source' => __CLASS__,
                    'person_uuid' => $pId,
                    'service_id' => $serviceId,
                    'status' => $responseCode,
                    'error' => $e->getMessage(),
                ]
            );

            return false;
        } catch (Exception $e) {
            WACC()->Log()->error(
                'An unexpected error occurred while fetching touchpoints.',
                [
                    'source' => __CLASS__,
                    'person_uuid' => $pId,
                    'service_id' => $serviceId,
                    'exception_class' => get_class($e),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return false;
        }
    }

    /**
     * Write a Touchpoint.
     *
     * This function sends a touchpoint to the Wicket API based on the provided parameters and service ID.
     *
     * USAGE:
     * ```php
     * $params = [
     *   'person_id' => '[uuid from wicket]',
     *   'action' => 'test action',
     *   'details' => 'these are some details',
     *   'data' => ['test' => 'thing'],
     *   'external_event_id' => 'some unique value used when you dont want duplicate touchpoints but cant control how they are triggered'
     * ];
     * WACC()->Mdp()->Touchpoint()->writeTouchpoint($params, WACC()->Mdp()->Touchpoint()->getOrCreateServiceId('[service name]', '[service description]'));
     * ```
     *
     * @param array  $params            The parameters for the touchpoint, including:
     *                                  - 'person_id' (string): The UUID of the person from Wicket.
     *                                  - 'action' (string): The action of the touchpoint.
     *                                  - 'details' (string): Details about the touchpoint.
     *                                  - 'data' (array): Additional data for the touchpoint.
     *                                  - 'external_event_id' (string): A unique value to prevent duplicate touchpoints.
     * @param string $wicketServiceId The ID of the Wicket service.
     * @return bool                     True if the touchpoint was successfully written, false otherwise.
     */
    public function writeTouchpoint(array $params, string $wicketServiceId): bool
    {
        $client = $this->initClient();
        if (!$client) {
            return false;
        }

        $payload = $this->buildTouchpointPayload($params, $wicketServiceId);

        if (empty($payload)) {
            WACC()->Log()->warning('Failed to build touchpoint payload or payload is empty.', ['source' => __CLASS__, 'params' => $params, 'service_id' => $wicketServiceId]);

            return false;
        }

        try {
            $response = $client->post('touchpoints', ['json' => $payload]);

            // Assuming a successful POST returns a 2xx status code and the client handles it by returning data or null.
            // If $response is not empty or specifically if it contains an ID, consider it successful.
            return !empty($response['data']['id']);
        } catch (RequestException $e) {
            $response_code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            WACC()->Log()->error(
                'Touchpoint API POST request failed.',
                [
                    'source' => __CLASS__,
                    'service_id' => $wicketServiceId,
                    'status' => $response_code,
                    'error' => $e->getMessage(),
                    'payload' => $payload, // Log payload for debugging
                ]
            );

            return false;
        } catch (Exception $e) {
            WACC()->Log()->error(
                'An unexpected error occurred while writing touchpoint.',
                [
                    'source' => __CLASS__,
                    'service_id' => $wicketServiceId,
                    'exception_class' => get_class($e),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'payload' => $payload, // Log payload for debugging
                ]
            );

            return false;
        }
    }

    /**
     * Build the payload for a touchpoint.
     *
     * @param array  $params            Parameters for the touchpoint.
     * @param string $wicketServiceId The Wicket service ID.
     * @return array|null The payload array or null if required parameters are missing.
     */
    private function buildTouchpointPayload(array $params, string $wicketServiceId): ?array
    {
        if (empty($params['personId']) || empty($params['action']) || empty($wicketServiceId)) {
            WACC()->Log()->warning(
                'Missing required parameters for building touchpoint payload.',
                ['source' => __CLASS__, 'personIdPresent' => !empty($params['personId']), 'actionPresent' => !empty($params['action']), 'serviceIdPresent' => !empty($wicketServiceId)]
            );

            return null;
        }

        $payload = [
            'data' => [
                'type' => 'touchpoints',
                'attributes' => [
                    'action' => $params['action'],
                    'details' => $params['details'] ?? '',
                    'data' => $params['data'] ?? [],
                    'external_event_id' => $params['external_event_id'] ?? null,
                ],
                'relationships' => [
                    'person' => [
                        'data' => [
                            'type' => 'people',
                            'id' => $params['person_id'],
                        ],
                    ],
                    'service' => [
                        'data' => [
                            'type' => 'services',
                            'id' => $wicketServiceId,
                        ],
                    ],
                ],
            ],
        ];

        // Remove null values from external_event_id if not provided
        if (is_null($payload['data']['attributes']['external_event_id'])) {
            unset($payload['data']['attributes']['external_event_id']);
        }

        return $payload;
    }

    /**
     * Get or create a touchpoint service and return its ID.
     *
     * This function retrieves an existing service by name. If it doesn't exist,
     * it creates a new one.
     *
     * @param string $service_name        The name of the service.
     * @param string $service_description The description for a new service.
     * @return string|null The service ID on success, or null on failure.
     */
    public function getOrCreateServiceId(string $serviceName, string $serviceDescription = 'Custom from WP'): ?string
    {
        if (empty($serviceName)) {
            WACC()->Log()->warning('Service name cannot be empty.', ['source' => __CLASS__]);

            return null;
        }

        $client = $this->initClient();
        if (!$client) {
            return null;
        }

        try {
            // 1. Check for existing service
            $params = ['filter[name_eq]' => $serviceName];
            $existing_services = $client->get('services', ['query' => $params]);

            if (!empty($existing_services['data'][0]['id'])) {
                return $existing_services['data'][0]['id'];
            }

            // 2. If no existing service, create one
            $payload = [
                'data' => [
                    'type' => 'services',
                    'attributes' => [
                        'name'             => $serviceName,
                        'description'      => $serviceDescription,
                        'status'           => 'active',
                        'integration_type' => 'custom',
                    ],
                ],
            ];

            $new_service = $client->post('services', ['json' => $payload]);
            $new_service_id = $new_service['data']['id'] ?? null;

            if ($new_service_id) {
                return $new_service_id;
            }

            WACC()->Log()->error(
                'Failed to create service or extract ID from response.',
                ['source' => __CLASS__, 'service_name' => $serviceName, 'response' => $new_service]
            );

            return null;
        } catch (RequestException $e) {
            $responseCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            WACC()->Log()->error(
                'Touchpoint Service API request failed.',
                [
                    'source' => __CLASS__,
                    'service_name' => $serviceName,
                    'status' => $responseCode,
                    'error' => $e->getMessage(),
                ]
            );

            return null;
        } catch (Exception $e) {
            WACC()->Log()->error(
                'An unexpected error occurred while getting or creating a service.',
                [
                    'source' => __CLASS__,
                    'service_name' => $serviceName,
                    'exception_class' => get_class($e),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return null;
        }
    }
}
