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
     *                           - 'source': Optional source identifier for custom logic. Default to empty string.
     *                           - 'mode': 'upcoming', 'past', or 'all'. Defaults to 'all'.
     *                           - 'event_start_date_field': The field key for event start date in API response.
     *                           - 'event_end_date_field': The field key for event end date in API response.
     * }
     * @return array|false An array of touchpoints or false on failure.
     */
    public function getCurrentUserTouchpoints(string $serviceId, array $options = []): array|false
    {
        if (empty($serviceId)) {
            WACC()->Log()->warning('Service ID cannot be empty.', ['source' => __CLASS__]);

            return false;
        }

        // Set the default for optional params
        $options = wp_parse_args($options, [
            'person_uuid'            => $this->Person->getCurrentPersonUuid(),
            'source'                 => '', // Optional source identifier for custom logic
            'mode'                   => 'all', // 'upcoming', 'past', or 'all'
            'event_start_date_field' => 'event_start', // Default field key for event start date
            'event_end_date_field'   => 'event_end',   // Default field key for event end date
        ]);

        $personUuid = $options['person_uuid'];

        if (empty($personUuid)) {
            WACC()->Log()->warning('Person UUID could not be determined.', ['source' => __CLASS__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            // initClient already logs the error, so we just return.
            return false;
        }

        try {
            // Get all touchpoints without date filtering - let the API return everything
            $params = [
                'page[size]' => 100, // Increased to get more results for local filtering
                'filter[service_id]' => $serviceId,
            ];

            $response = $client->get("people/{$personUuid}/touchpoints", ['query' => $params]);
            $touchpoints = $response['data'] ?? [];

            // If no mode specified, return all results
            if ($options['mode'] === 'all' || empty($touchpoints)) {
                return $touchpoints;
            }

            // Filter touchpoints based on mode and date fields
            $mappedOptions = $this->mapSourceFieldNames($options);
            $filteredTouchpoints = $this->filterTouchpointsByDate($touchpoints, $options['mode'], $mappedOptions);

            // No debug logging: return filtered touchpoints

            return $filteredTouchpoints;

        } catch (RequestException $e) {
            $responseCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            WACC()->Log()->error(
                'Touchpoint API request failed.',
                [
                    'source' => __CLASS__,
                    'person_uuid' => $personUuid,
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
                    'person_uuid' => $personUuid,
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
     * Filter touchpoints by date based on mode and dynamic date field keys.
     *
     * @param array  $touchpoints The array of touchpoints to filter.
     * @param string $mode        The filtering mode: 'upcoming' or 'past'.
     * @param array  $options     Options containing date field keys and other settings.
     * @return array Filtered touchpoints array.
     */
    private function filterTouchpointsByDate(array $touchpoints, string $mode, array $options): array
    {
        $options = wp_parse_args($options, [
            'event_start_date_field' => 'event_start',
            'event_end_date_field'   => 'event_end',
            'source'                 => '',
            'mode'                   => 'all',
        ]);

        $eventStartField = $options['event_start_date_field'] ?? 'event_start';
        $eventEndField = $options['event_end_date_field'] ?? 'event_end';
        $currentTimestamp = time();

        return array_filter($touchpoints, function ($touchpoint) use ($mode, $eventStartField, $eventEndField, $currentTimestamp) {
            // Look for date in touchpoint attributes data
            $data = $touchpoint['attributes']['data'] ?? [];

            // Get start and end dates from the touchpoint data
            $startDate = $data[$eventStartField] ?? null;
            $endDate = $data[$eventEndField] ?? null;

            // If no start date is available, skip this touchpoint
            if (empty($startDate)) {
                return false;
            }

            // Parse the date and convert to timestamp
            $startTimestamp = $this->parseEventDate($startDate);
            if ($startTimestamp === false) {
                // If we can't parse the date, skip this touchpoint
                return false;
            }

            // For events with end dates, use end date for comparison if available
            $comparisonTimestamp = $startTimestamp;
            if (!empty($endDate)) {
                $endTimestamp = $this->parseEventDate($endDate);
                if ($endTimestamp !== false) {
                    // Use end date for comparison to determine if event is truly past
                    $comparisonTimestamp = $endTimestamp;
                }
            }

            // Filter based on mode
            if ($mode === 'upcoming') {
                return $comparisonTimestamp > $currentTimestamp;
            } elseif ($mode === 'past') {
                return $comparisonTimestamp < $currentTimestamp;
            }

            // Default: return all (shouldn't reach here)
            return true;
        });
    }

    /**
     * Parse event date from various formats into a Unix timestamp.
     *
     * Handles different date formats from various services:
     * - Pheedloop: "2025-10-10T15:00:00"
     * - Events Calendar: "2025-01-21 9:00 AM EST"
     * - Cvent: "2024-12-16T18:00:00.000Z"
     *
     * @param string $dateString The date string to parse.
     * @return int|false Unix timestamp or false if parsing fails.
     */
    private function parseEventDate(string $dateString): int|false
    {
        if (empty($dateString)) {
            return false;
        }

        // Try to parse using strtotime first (handles most formats)
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            return $timestamp;
        }

        // If strtotime fails, try DateTime for more complex formats
        try {
            $dateTime = new \DateTime($dateString);

            return $dateTime->getTimestamp();
        } catch (Exception $e) {
            WACC()->Log()->warning(
                'Failed to parse event date.',
                [
                    'source' => __CLASS__,
                    'date_string' => $dateString,
                    'error' => $e->getMessage(),
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

    /**
     * Map source-specific field names to standard field names for filtering.
     *
     * This method provides backwards compatibility by dynamically setting the correct
     * field names based on the source, without modifying existing filtering logic.
     *
     * @param array $options The original options array.
     * @return array Modified options array with mapped field names.
     */
    private function mapSourceFieldNames(array $options): array
    {
        $source = $options['source'] ?? '';

        // If no source specified, return options unchanged (use explicit fields or defaults)
        if (empty($source)) {
            return $options;
        }

        // Map field names based on source
        switch (strtolower($source)) {
            case 'vital':
            case 'vitalsource':
                // VitalSource uses 'end_date' or 'ebook_end' for end dates
                // Use 'created_at' as start date since eBooks don't have a real start date
                $mappedOptions = $options;
                $mappedOptions['event_start_date_field'] = 'created_at';
                $mappedOptions['event_end_date_field'] = 'end_date'; // Fallback to 'end_date'

                // mapping applied for vitalsource

                return $mappedOptions;

            case 'eventcalendar':
            case 'eventscalendar':
            case 'event_calendar':
            case 'events_calendar':
                // Event Calendar uses 'start_date' and 'end_date'
                $mappedOptions = $options;
                $mappedOptions['event_start_date_field'] = 'start_date';
                $mappedOptions['event_end_date_field'] = 'end_date';

                // mapping applied for eventcalendar

                return $mappedOptions;

            case 'pheedloop':
                // Pheedloop uses 'event_start' and 'event_end' (already defaults)
                // pheedloop uses defaults
                return $options;

            case 'cvent':
                // Cvent uses 'event_start' and 'event_end' (already defaults)
                // cvent uses defaults
                return $options;

            default:
                // Keep defaults for unknown sources
                // unknown source: keep defaults
                return $options;
        }
    }
}
