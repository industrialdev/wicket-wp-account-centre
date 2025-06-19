<?php

namespace WicketAcc\MdpApi;

use Exception; // Required for getCurrentPerson
use GuzzleHttp\Exception\RequestException;

// No direct access
defined('ABSPATH') || exit;

/**
 * Handle MDP Person endpoints.
 */
class Person extends Init
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get current person UUID from WordPress context.
     *
     * @return string|false The person's UUID (user_login) or false if not available.
     */
    public function getCurrentPersonUuid()
    {
        // Ensure WordPress user functions are available and user is logged in.
        if (function_exists('wp_get_current_user')) {
            $current_user = wp_get_current_user();
            if ($current_user instanceof \WP_User && $current_user->exists() && !empty($current_user->user_login)) {
                return $current_user->user_login;
            }
        }

        // If any of the above conditions are not met, or wp_get_current_user doesn't exist.
        return false;
    }

    /**
     * Get current person.
     * NB: Contains original logic from Init.php which appears to have a bug.
     * If !empty($person_id), it returns false, preventing data fetch.
     */
    /**
     * Get current person's complete data object from the Wicket API.
     *
     * @return object|false The Wicket SDK Person resource object on success, false otherwise.
     */
    public function getCurrentPerson(): object|false
    {
        $person_id = $this->getCurrentPersonUuid();

        if (empty($person_id)) {
            // getCurrentPersonUuid already logs if the user is not logged in or session is invalid.
            // We can add a specific log here if needed, but it might be redundant.
            // WACC()->Log->debug('No current person UUID found, cannot fetch person data.', ['source' => __METHOD__]);
            return false;
        }

        // Delegate to getPersonByUuid for fetching and error handling
        return $this->getPersonByUuid($person_id);
    }

    /**
     * Check if the current user has a valid UUID.
     *
     * @return bool True if the current user has a valid UUID as their user_login, false otherwise.
     */
    public function hasValidUuid()
    {
        $uuid = $this->getCurrentPersonUuid();

        // Check if uuid is a valid UUID string
        if ($uuid && isValidUuid($uuid)) {
            return true;
        }

        return false;
    }

    /**
     * Retrieve a person's details from Wicket by their UUID.
     *
     * @param string $uuid The UUID of the person to fetch.
     *                     Should be a non-empty string.
     *
     * @return object|false The person's Wicket API data object on success.
     *                      Returns false if:
     *                      - The provided UUID is empty.
     *                      - The Wicket API client fails to initialize.
     *                      - An API error occurs (e.g., person not found, network issue).
     */
    public function getPersonByUuid(?string $uuid = null): object|false
    {
        if (empty($uuid)) {
            WACC()->Log->warning('Provided UUID is empty.', ['source' => __METHOD__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            WACC()->Log->error('Failed to initialize Wicket API client.', ['source' => __METHOD__, 'uuid' => $uuid]);

            return false;
        }

        try {
            // The Wicket SDK's fetch method typically returns a resource object.
            return $client->people->fetch($uuid);
        } catch (RequestException $e) {
            $response_code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            WACC()->Log->error(
                'RequestException while fetching person by UUID.',
                [
                    'source' => __METHOD__,
                    'uuid' => $uuid,
                    'status_code' => $response_code,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return false;
        } catch (Exception $e) {
            WACC()->Log->error(
                'Generic Exception while fetching person by UUID.',
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
     * Retrieve a person's profile from Wicket by their UUID.
     *
     * If no UUID is provided, it attempts to use the UUID of the current logged-in WordPress user.
     *
     * @param string|null $person_uuid The UUID of the person. Defaults to null, in which case the current user's UUID is attempted.
     *
     * @return object|null The person's Wicket API data object on success.
     *                     Returns null if:
     *                     - No UUID is provided and the current user has no UUID.
     *                     - The Wicket API client fails to initialize.
     *                     - An API error occurs (e.g., person not found, network issue).
     */
    /**
     * Retrieve a person's profile from Wicket by their UUID, including related data.
     *
     * If no UUID is provided, it attempts to use the UUID of the current logged-in WordPress user.
     *
     * @param string|null $person_uuid The UUID of the person to fetch. Defaults to current user's UUID.
     * @return object|null The Wicket SDK Person resource object (which includes linked resources like addresses, etc.)
     *                     on success, or null if:
     *                     - No UUID is provided and the current user has no UUID.
     *                     - The Wicket API client fails to initialize.
     *                     - An API error occurs (e.g., person not found, network issue).
     */
    public function getPersonProfileByUuid(?string $person_uuid = null): ?object
    {
        if (empty($person_uuid)) {
            $person_uuid = $this->getCurrentPersonUuid();
        }

        if (empty($person_uuid)) {
            WACC()->Log->warning('No UUID provided and current user UUID not found.', ['source' => __METHOD__]);

            return null;
        }

        $client = $this->initClient();
        if (!$client) {
            WACC()->Log->error('Failed to initialize Wicket API client.', ['source' => __METHOD__, 'uuid' => $person_uuid]);

            return null;
        }

        try {
            // The Wicket SDK's fetch method with includes for profile data typically returns a resource object.
            // Assuming the SDK handles the 'include' parameters internally or this is a base fetch.
            return $client->people->fetch($person_uuid, ['include' => 'emails,phones,addresses,identities,memberships']);
        } catch (RequestException $e) {
            $response_code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            WACC()->Log->error(
                'RequestException while fetching person profile by UUID.',
                [
                    'source' => __METHOD__,
                    'uuid' => $person_uuid,
                    'status_code' => $response_code,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return null;
        } catch (Exception $e) {
            WACC()->Log->error(
                'Generic Exception while fetching person profile by UUID.',
                [
                    'source' => __METHOD__,
                    'uuid' => $person_uuid,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return null;
        }
    }

    /**
     * Accepts a Wicket person object (typically the result of `getPersonByUuid` or `getCurrentPerson`)
     * and returns a filtered array of a specified repeatable contact method type (e.g., addresses, emails).
     *
     * @param object $person_data         The Wicket person data object, expected to have an `included()` method
     *                                    that returns a collection convertible to an array.
     * @param string $contact_type        The type of contact information to extract (e.g., "addresses", "phones", "emails").
     * @param bool   $return_full_objects If true, returns the full contact item objects; otherwise, returns only their attributes.
     * @return array|false                An array of contact items (either full objects or attributes) if successful, false otherwise.
     */
    public function getPersonRepeatableContactInfo(object $person_data, string $contact_type, bool $return_full_objects = false): array|false
    {
        if (!method_exists($person_data, 'included')) {
            WACC()->Log->warning('Person data object does not have an included() method.', ['source' => __METHOD__, 'contact_type' => $contact_type]);

            return false;
        }

        $included_data = $person_data->included();

        if (!method_exists($included_data, 'toArray')) {
            WACC()->Log->warning('Included data does not have a toArray() method.', ['source' => __METHOD__, 'contact_type' => $contact_type]);

            return false;
        }

        $all_included_items = $included_data->toArray();
        $contact_items = [];

        foreach ($all_included_items as $item) {
            if (isset($item['type']) && $item['type'] === $contact_type) {
                $contact_items[] = $item;
            }
        }

        if (empty($contact_items)) {
            return false;
        }

        $to_return = [];
        foreach ($contact_items as $contact_item) {
            if ($return_full_objects) {
                $to_return[] = $contact_item;
            } elseif (isset($contact_item['attributes'])) {
                $to_return[] = $contact_item['attributes'];
            } else {
                // Log if attributes are missing but expected
                WACC()->Log->debug('Contact item missing attributes.', ['source' => __METHOD__, 'contact_item' => $contact_item]);
            }
        }

        return empty($to_return) ? false : $to_return;
    }

    /**
     * Gets all people from Wicket.
     *
     * @return array|false An array of person resource objects on success, false on failure.
     */
    public function getAllPeople(): array|false
    {
        $client = $this->initClient();
        if (!$client) {
            // initClient() logs the error.
            return false;
        }

        try {
            $response = $client->people->all();

            return $response['data'] ?? [];
        } catch (RequestException $e) {
            $response_code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            WACC()->Log->error(
                'RequestException while fetching all people.',
                [
                    'source' => __METHOD__,
                    'status_code' => $response_code,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return false;
        } catch (Exception $e) {
            WACC()->Log->error(
                'Generic Exception while fetching all people.',
                [
                    'source' => __METHOD__,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]
            );

            return false;
        }
    }

    /**
     * Find a person by their primary email address and return the full person object.
     *
     * @param string $email The primary email address to search for.
     *
     * @return object|false The Wicket SDK Person resource object on success, false otherwise.
     */
    public function getPersonByEmail(string $email): object|false
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            WACC()->Log->warning('Invalid or empty email address provided for person lookup.', ['source' => __METHOD__, 'email' => $email]);

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
                    'filter[emails.primary_eq]' => 'true',
                    'filter[emails.address_eq]' => $email,
                    'page[size]' => 1, // We only need one result
                ],
            ];

            $response = $client->get('people', $params);

            // If a person is found, their ID will be in the response.
            if (!empty($response['data'][0]['id'])) {
                // Now fetch the full person object using the existing method to ensure consistency.
                return $this->getPersonByUuid($response['data'][0]['id']);
            }

            WACC()->Log->info('No person found with the specified primary email.', ['source' => __METHOD__, 'email' => $email]);

            return false;
        } catch (RequestException $e) {
            $response_code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            WACC()->Log->error(
                'RequestException while fetching person by email.',
                [
                    'source' => __METHOD__,
                    'email' => $email,
                    'status_code' => $response_code,
                    'message' => $e->getMessage(),
                ]
            );

            return false;
        } catch (Exception $e) {
            WACC()->Log->error(
                'Generic Exception while fetching person by email.',
                [
                    'source' => __METHOD__,
                    'email' => $email,
                    'message' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * Check if the current logged-in person has the 'member' role.
     *
     * This method caches the result for the duration of the request to avoid redundant checks.
     *
     * @return bool True if the person is a member, false otherwise.
     */
    public function isCurrentPersonMember(): bool
    {
        static $has_membership = null;

        // Only compute if not already determined for this request
        if (is_null($has_membership)) {
            $person = $this->getCurrentPerson();

            // Check if person data is valid and contains the role_names attribute
            if (!$person || !isset($person->attributes->role_names) || !is_array($person->attributes->role_names)) {
                WACC()->Log->info(
                    'Could not determine membership status. Current person data is unavailable or roles are malformed.',
                    ['source' => __METHOD__]
                );
                $has_membership = false;
            } else {
                // Check if 'member' exists in the role_names array
                $has_membership = in_array('member', $person->attributes->role_names);
            }
        }

        return $has_membership;
    }

    /**
     * Get the full name (given name and family name) of the current logged-in person.
     *
     * @return string The full name, or an empty string if the name cannot be retrieved.
     */
    public function getCurrentPersonFullName(): string
    {
        $person = $this->getCurrentPerson();

        // Ensure we have a valid person object with the necessary attributes
        if ($person && isset($person->attributes->given_name, $person->attributes->family_name)) {
            return trim($person->attributes->given_name . ' ' . $person->attributes->family_name);
        }

        WACC()->Log->info(
            'Could not retrieve full name. Current person data is unavailable or name attributes are missing.',
            ['source' => __METHOD__]
        );

        return '';
    }

    /**
     * Search for people by a search term.
     *
     * This method uses the autocomplete endpoint to find people based on a query term.
     * It's designed for front-end search displays where a specific UUID is not known.
     *
     * @param string $searchTerm The query term (e.g., 'Rob Ferguson', 'rob@example.com').
     * @param array  $args       (Optional) Additional arguments. Currently supports 'limit'.
     *                           - 'limit' (int): Maximum number of results to return. Defaults to 100.
     *
     * @return array|false An array of person data on success, false on failure.
     *                     Each person array contains 'id', 'full_name', and 'primary_email_address'.
     */
    public function searchPeople(string $searchTerm, array $args = []): array|false
    {
        if (empty($searchTerm)) {
            WACC()->Log->warning('Search term is empty.', ['source' => __METHOD__]);

            return false;
        }

        $defaults = [
            'limit' => 100,
        ];
        $args = wp_parse_args($args, $defaults);

        $client = $this->initClient();
        if (!$client) {
            return false; // initClient() logs the error
        }

        $queryParams = [
            'query' => [
                'query' => $searchTerm,
                'fields' => [
                    'people' => 'full_name,primary_email_address',
                ],
                'filter' => [
                    'resource_type' => 'people',
                ],
                'page' => [
                    'size' => (int) $args['limit'],
                ],
            ],
        ];

        try {
            $response = $client->get('/search/autocomplete', $queryParams);

            if (empty($response['included'])) {
                WACC()->Log->info(
                    'Person search returned no results.',
                    ['source' => __METHOD__, 'searchTerm' => $searchTerm]
                );

                return []; // Return empty array for no results, which is not an error
            }

            $formattedResults = [];
            foreach ($response['included'] as $result) {
                if ($result['type'] === 'people') {
                    $formattedResults[] = [
                        'id' => $result['id'],
                        'full_name' => $result['attributes']['full_name'] ?? '',
                        'primary_email_address' => $result['attributes']['primary_email_address'] ?? '',
                    ];
                }
            }

            return $formattedResults;
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $errorMessageDetail = 'RequestException while searching people.';
            if ($e->hasResponse()) {
                try {
                    $errorBody = json_decode((string) $e->getResponse()->getBody(), true, 512, JSON_THROW_ON_ERROR);
                    $errorMessageDetail = $errorBody['errors'][0]['detail'] ?? $errorBody['errors'][0]['title'] ?? $e->getMessage();
                } catch (\JsonException $jsonEx) {
                    $errorMessageDetail = 'Could not decode API error response: ' . $jsonEx->getMessage();
                }
            }
            $logContext = [
                'source'          => __METHOD__,
                'searchTerm'      => $searchTerm,
                'args'            => $args,
                'status_code'     => $statusCode,
                'response_body'   => $e->hasResponse() ? (string) $e->getResponse()->getBody() : null,
                'exception_trace' => $e->getTraceAsString(),
            ];
            WACC()->Log->error($errorMessageDetail, $logContext);

            return false;
        } catch (Exception $e) {
            $logContext = [
                'source'          => __METHOD__,
                'searchTerm'      => $searchTerm,
                'args'            => $args,
                'exception_class' => get_class($e),
                'exception_trace' => $e->getTraceAsString(),
            ];
            WACC()->Log->error('Generic Exception while searching people: ' . $e->getMessage(), $logContext);

            return false;
        }
    }

    /**
     * Get all "connections" (relationships) of a Wicket person.
     *
     * @return array|false Array of person connections on success, false on failure.
     */
    public function getPersonConnections()
    {
        $personId = $this->getCurrentPersonUuid();
        if (!$personId) {
            WACC()->Log->warning('No person ID available to fetch connections', ['source' => __METHOD__]);

            return false;
        }

        static $connections = null;

        if (is_null($connections)) {
            try {
                $client = $this->initClient();
                if (!$client) {
                    return false;
                }

                $response = $client->get("people/{$personId}/connections", [
                    'query' => [
                        'filter' => [
                            'connection_type_eq' => 'all',
                        ],
                        'sort' => '-created_at',
                    ],
                ]);

                $connections = $response['data'] ?? false;

                if (empty($connections)) {
                    WACC()->Log->info('No connections found for person', [
                        'source' => __METHOD__,
                        'personId' => $personId,
                    ]);
                }
            } catch (RequestException $e) {
                $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
                WACC()->Log->error("Error fetching person connections (HTTP {$statusCode}): " . $e->getMessage(), [
                    'source' => __METHOD__,
                    'personId' => $personId,
                    'statusCode' => $statusCode,
                ]);

                return false;
            } catch (Exception $e) {
                WACC()->Log->error('Unexpected error fetching person connections: ' . $e->getMessage(), [
                    'source' => __METHOD__,
                    'personId' => $personId,
                    'exception' => get_class($e),
                ]);

                return false;
            }
        }
        if ($connections) {
            return $connections;
        }
    }

    /**
     * Get all "connections" (relationships) of a specific Wicket person by their UUID.
     *
     * @param string $personUuid The UUID of the person to fetch connections for.
     * @return array|false Array of person connections on success, false on failure.
     */
    public function getPersonConnectionsById(string $personUuid)
    {
        if (empty($personUuid)) {
            WACC()->Log->warning('No person UUID provided to fetch connections', ['source' => __METHOD__]);

            return false;
        }

        static $connectionsCache = [];

        if (!isset($connectionsCache[$personUuid])) {
            try {
                $client = $this->initClient();
                if (!$client) {
                    return false;
                }

                $response = $client->get("people/{$personUuid}/connections", [
                    'query' => [
                        'filter' => [
                            'connection_type_eq' => 'all',
                        ],
                        'sort' => '-created_at',
                    ],
                ]);

                $connections = $response['data'] ?? false;

                if (empty($connections)) {
                    WACC()->Log->info('No connections found for specified person', [
                        'source' => __METHOD__,
                        'personUuid' => $personUuid,
                    ]);
                    $connectionsCache[$personUuid] = false;
                } else {
                    $connectionsCache[$personUuid] = $connections;
                }
            } catch (RequestException $e) {
                $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
                WACC()->Log->error("Error fetching person connections (HTTP {$statusCode}): " . $e->getMessage(), [
                    'source' => __METHOD__,
                    'personUuid' => $personUuid,
                    'statusCode' => $statusCode,
                ]);

                return false;
            } catch (Exception $e) {
                WACC()->Log->error('Unexpected error fetching person connections: ' . $e->getMessage(), [
                    'source' => __METHOD__,
                    'personUuid' => $personUuid,
                    'exception' => get_class($e),
                ]);

                return false;
            }
        }

        return $connectionsCache[$personUuid];
    }

    /**
     * Create a basic person record in MDP.
     *
     * @param string $givenName The person's given name.
     * @param string $familyName The person's family name.
     * @param ?string $email Optional email address.
     * @param ?string $password Optional password. Must be provided with passwordConfirmation.
     * @param ?string $passwordConfirmation Optional password confirmation. Must match password.
     * @param ?string $jobTitle Optional job title.
     * @param ?string $gender Optional gender.
     * @param array $additionalDataFields Optional additional data fields (key-value pairs).
     * @return array|false The created person data array on success, false on failure.
     */
    public function createPerson(
        string $givenName,
        string $familyName,
        ?string $email = null,
        ?string $password = null,
        ?string $passwordConfirmation = null,
        ?string $jobTitle = null,
        ?string $gender = null,
        array $additionalDataFields = []
    ): array|false {
        $client = $this->initClient();
        if (!$client) {
            WACC()->Log->error('Failed to initialize API client.', ['source' => __METHOD__]);

            return false;
        }

        $attributes = [
            'given_name' => $givenName,
            'family_name' => $familyName,
        ];

        if (!empty($jobTitle)) {
            $attributes['job_title'] = $jobTitle;
        }

        if (!empty($gender)) {
            $attributes['gender'] = $gender;
        }

        if (!empty($password) && !empty($passwordConfirmation)) {
            if ($password === $passwordConfirmation) {
                $attributes['user']['password'] = $password;
                $attributes['user']['password_confirmation'] = $passwordConfirmation;
            } else {
                WACC()->Log->warning('Password and confirmation do not match during person creation.', [
                    'source' => __METHOD__,
                    'givenName' => $givenName, // Avoid logging sensitive data like full name if possible
                    'familyNameInitial' => !empty($familyName) ? substr($familyName, 0, 1) : '',
                ]);

                return false; // Passwords don't match, fail creation
            }
        }

        if (!empty($additionalDataFields)) {
            $attributes['data_fields'] = $additionalDataFields;
        }

        $payload = [
            'data' => [
                'type' => 'people',
                'attributes' => $attributes,
            ],
        ];

        if (!empty($email)) {
            // This structure for creating a related email with attributes within the relationship
            // is specific to how the Wicket API might handle it. Standard JSON:API is different.
            $payload['data']['relationships']['emails']['data'][] = [
                'type' => 'emails',
                'attributes' => [
                    'address' => $email,
                    'is_primary' => true, // Assume new email should be primary
                ],
            ];
        }

        try {
            $personResponse = $client->post('people', ['json' => $payload]);

            // Assuming the SDK client returns an array structure directly
            return $personResponse;
        } catch (RequestException $e) {
            $errorDetails = [
                'source' => __METHOD__,
                'message' => $e->getMessage(),
            ];
            if ($e->hasResponse()) {
                $errorDetails['statusCode'] = $e->getResponse()->getStatusCode();
                // getContents() can only be called once on a stream, be careful if needing to re-read
                $errorDetails['responseBody'] = $e->getResponse()->getBody()->getContents();
            }
            WACC()->Log->error('RequestException while creating person.', $errorDetails);

            return false;
        } catch (Exception $e) {
            WACC()->Log->error('Generic Exception while creating person.', [
                'source' => __METHOD__,
                'message' => $e->getMessage(),
                'exception_type' => get_class($e),
            ]);

            return false;
        }
    }

    /**
     * Update multiple profile attributes of a Wicket person.
     *
     * Example of $fieldsToUpdate array:
     * [
     *  'attributes' => ['family_name' => 'Smith', 'job_title' => 'Developer'],
     *  'addresses' => [ ['uuid' => 'addr_uuid_1', 'city' => 'New York'], ['type' => 'home', 'address1' => '123 Main St'] ],
     *  'phones' => [ ['uuid' => 'phone_uuid_1', 'number' => '+15551234567'], ['type' => 'mobile', 'number' => '+15557654321'] ],
     *  'emails' => [ ['uuid' => 'email_uuid_1', 'address' => 'new@example.com'], ['type' => 'work', 'address' => 'work@example.com'] ],
     *  'web_addresses' => [ ['uuid' => 'web_uuid_1', 'address' => 'https://new.example.com'] ]
     * ]
     *
     * @param string $personUuid The UUID of the person to update.
     * @param array $fieldsToUpdate An array containing the fields to update, categorized by type (attributes, addresses, etc.).
     * @return array ['success' => bool, 'error' => string|array, 'data' => array (person data on attribute success)]
     */
    public function updatePerson(string $personUuid, array $fieldsToUpdate): array
    {
        $client = $this->initClient();
        if (!$client) {
            return ['success' => false, 'error' => 'API client initialization failed.'];
        }

        // Fetch current person data. getPersonByUuid returns an array or false.
        $currentPersonData = $this->getPersonByUuid($personUuid);
        if (!$currentPersonData) {
            return ['success' => false, 'error' => sprintf(__('Wicket person with UUID %s not found.', 'wicket-acc'), $personUuid)];
        }

        $errors = [];
        $updatedPersonData = null;

        // Update direct person attributes
        if (isset($fieldsToUpdate['attributes']) && is_array($fieldsToUpdate['attributes'])) {
            $attributesPayload = [];
            $allowedDirectAttributes = [
                'additional_name',
                'family_name',
                'given_name',
                'honorific_prefix',
                'honorific_suffix',
                'job_function',
                'job_level',
                'job_title',
                'nickname',
                'status',
                'suffix',
                'data_fields',
            ];

            foreach ($fieldsToUpdate['attributes'] as $key => $value) {
                if (in_array($key, $allowedDirectAttributes)) {
                    $attributesPayload[$key] = $value;
                } else {
                    WACC()->Log->info(
                        sprintf("Attribute '%s' is not directly updatable on Person object and was ignored.", $key),
                        ['source' => __METHOD__, 'person_uuid' => $personUuid]
                    );
                }
            }

            if (!empty($attributesPayload)) {
                // Assuming wicket_filter_null_and_blank is a global helper. If not, this needs attention.
                if (function_exists('wicket_filter_null_and_blank')) {
                    $attributesPayload = wicket_filter_null_and_blank($attributesPayload);
                }

                if (!empty($attributesPayload)) { // Ensure there's something to send after filtering
                    $payload = [
                        'data' => [
                            'id' => $personUuid,
                            'type' => 'people',
                            'attributes' => $attributesPayload,
                        ],
                    ];

                    try {
                        $updatedPersonData = $client->patch("people/{$personUuid}", ['json' => $payload]);
                    } catch (RequestException $e) {
                        $errorMsg = 'Failed to update person attributes.';
                        $context = ['source' => __METHOD__, 'person_uuid' => $personUuid, 'payload' => $payload];
                        if ($e->hasResponse()) {
                            $context['statusCode'] = $e->getResponse()->getStatusCode();
                            $context['responseBody'] = $e->getResponse()->getBody()->getContents();
                        }
                        WACC()->Log->error($errorMsg, $context);
                        $errors[] = $errorMsg . ' ' . $e->getMessage();
                    } catch (Exception $e) {
                        WACC()->Log->error('Generic exception updating person attributes.', ['source' => __METHOD__, 'person_uuid' => $personUuid, 'message' => $e->getMessage()]);
                        $errors[] = 'An unexpected error occurred while updating attributes: ' . $e->getMessage();
                    }
                }
            }
        }

        // Update related entities (addresses, phones, emails, web_addresses)
        // These will call refactored methods like $this->updatePersonAddressesInternal, etc.
        // For now, assuming they exist or will be created. The original function names are used as placeholders for refactoring targets.

        // Call internal method to update addresses
        if (isset($fieldsToUpdate['addresses']) && is_array($fieldsToUpdate['addresses'])) {
            $addressesResult = $this->updatePersonAddressesInternal($personUuid, $fieldsToUpdate['addresses']);
            if (!$addressesResult['success']) {
                $errors = array_merge($errors, $addressesResult['error']);
            }
        }

        // Call internal method to update phones
        if (isset($fieldsToUpdate['phones']) && is_array($fieldsToUpdate['phones'])) {
            $phonesResult = $this->updatePersonPhonesInternal($personUuid, $fieldsToUpdate['phones']);
            if (!$phonesResult['success']) {
                $errors = array_merge($errors, $phonesResult['error']);
            }
        }

        // Call internal method to update emails
        if (isset($fieldsToUpdate['emails']) && is_array($fieldsToUpdate['emails'])) {
            $emailsResult = $this->updatePersonEmailsInternal($personUuid, $fieldsToUpdate['emails']);
            if (!$emailsResult['success']) {
                $errors = array_merge($errors, $emailsResult['error']);
            }
        }

        // Call internal method to update web addresses
        if (isset($fieldsToUpdate['web_addresses']) && is_array($fieldsToUpdate['web_addresses'])) {
            $webAddressesResult = $this->updatePersonWebAddressesInternal($personUuid, $fieldsToUpdate['web_addresses']);
            if (!$webAddressesResult['success']) {
                $errors = array_merge($errors, $webAddressesResult['error']);
            }
        }

        if (!empty($errors)) {
            $response['success'] = false;
            $response['error'] = implode('; ', $errors);
            $response['data'] = ['errors_detail' => $errors]; // Provide detailed errors if needed

            return $response;
        }

        if (empty($errors)) {
            return ['success' => true, 'data' => $updatedPersonData ?? $currentPersonData]; // Return updated or current person data
        }

        return ['success' => false, 'error' => $errors, 'data' => null];
    }

    /**
     * Update or create addresses for a person.
     *
     * @param string $personUuid The UUID of the person.
     * @param array $addressesInput Array of address data. Each item should be an associative array.
     *                            If 'uuid' is present, address is updated. Otherwise, it's created.
     *                            Example item: ['uuid' => 'xyz', 'type' => 'home', 'address1' => '123 Main St', ...]
     * @return array ['success' => bool, 'error' => array of error messages]
     */
    protected function updatePersonAddressesInternal(string $personUuid, array $addressesInput): array
    {
        $client = $this->initClient();
        if (!$client) {
            return ['success' => false, 'error' => ['API client initialization failed.']];
        }

        $errors = [];

        foreach ($addressesInput as $addressItem) {
            if (!is_array($addressItem)) {
                $errors[] = 'Invalid address item data provided; not an array.';
                continue;
            }

            $attributes = $addressItem;
            unset($attributes['uuid']); // Remove UUID from attributes payload

            // Sanitize attributes if the global helper exists
            if (function_exists('wicket_filter_null_and_blank')) {
                $attributes = wicket_filter_null_and_blank($attributes);
            }

            if (empty($attributes)) {
                // Nothing to update or create if all attributes are blank after filtering
                continue;
            }

            $addressUuid = $addressItem['uuid'] ?? null;

            try {
                if (!empty($addressUuid)) {
                    // Update existing address
                    $payload = [
                        'data' => [
                            'id' => $addressUuid,
                            'type' => 'addresses',
                            'attributes' => $attributes,
                        ],
                    ];
                    $client->patch("people/{$personUuid}/addresses/{$addressUuid}", ['json' => $payload]);
                } else {
                    // Create new address
                    $payload = [
                        'data' => [
                            'type' => 'addresses',
                            'attributes' => $attributes,
                        ],
                    ];
                    $client->post("people/{$personUuid}/addresses", ['json' => $payload]);
                }
            } catch (RequestException $e) {
                $action = empty($addressUuid) ? 'create' : 'update';
                $errorMsg = sprintf('Failed to %s address (UUID: %s).', $action, $addressUuid ?? 'N/A');
                $context = [
                    'source' => __METHOD__,
                    'person_uuid' => $personUuid,
                    'address_uuid' => $addressUuid,
                    'payload' => $payload,
                    'original_exception' => $e->getMessage(),
                ];
                if ($e->hasResponse()) {
                    $context['statusCode'] = $e->getResponse()->getStatusCode();
                    $context['responseBody'] = $e->getResponse()->getBody()->getContents();
                }
                WACC()->Log->error($errorMsg, $context);
                $errors[] = $errorMsg . ' API Error: ' . $e->getMessage();
            } catch (Exception $e) {
                $action = empty($addressUuid) ? 'create' : 'update';
                $errorMsg = sprintf('Generic exception during address %s (UUID: %s).', $action, $addressUuid ?? 'N/A');
                WACC()->Log->error($errorMsg, ['source' => __METHOD__, 'person_uuid' => $personUuid, 'address_uuid' => $addressUuid, 'message' => $e->getMessage()]);
                $errors[] = $errorMsg . ' ' . $e->getMessage();
            }
        }

        if (empty($errors)) {
            return ['success' => true, 'error' => []];
        }

        return ['success' => false, 'error' => $errors];
    }

    /**
     * Update or create phone numbers for a person.
     *
     * @param string $personUuid The UUID of the person.
     * @param array $phonesInput Array of phone data. Each item should be an associative array.
     *                           If 'uuid' is present, phone is updated. Otherwise, it's created.
     *                           Example item: ['uuid' => 'xyz', 'type' => 'work', 'number' => '+15551234567']
     * @return array ['success' => bool, 'error' => array of error messages]
     */
    protected function updatePersonPhonesInternal(string $personUuid, array $phonesInput): array
    {
        $client = $this->initClient();
        if (!$client) {
            return ['success' => false, 'error' => ['API client initialization failed.']];
        }

        $errors = [];
        $readOnlyAttributes = [
            'uuid', 'type_external_id', 'number_national_format', 'number_international_format',
            'extension', 'country_code_number', 'created_at', 'updated_at', 'deleted_at',
            'primary_sms', 'consent', 'consent_third_party', 'consent_directory',
        ];

        foreach ($phonesInput as $phoneItem) {
            if (!is_array($phoneItem)) {
                $errors[] = 'Invalid phone item data provided; not an array.';
                continue;
            }

            $attributes = $phoneItem;
            $phoneUuid = $phoneItem['uuid'] ?? null;

            // Remove read-only attributes for PATCH and UUID from attributes payload
            foreach ($readOnlyAttributes as $key) {
                unset($attributes[$key]);
            }
            if ($phoneUuid) { // If it's an update, uuid is in $phoneUuid, not needed in attributes
                unset($attributes['uuid']);
            }

            // Sanitize attributes if the global helper exists
            if (function_exists('wicket_filter_null_and_blank')) {
                $attributes = wicket_filter_null_and_blank($attributes);
            }

            if (empty($attributes)) {
                continue; // Nothing to update or create
            }

            try {
                if (!empty($phoneUuid)) {
                    // Update existing phone
                    $payload = [
                        'data' => [
                            'id' => $phoneUuid,
                            'type' => 'phones',
                            'attributes' => $attributes,
                        ],
                    ];
                    $client->patch("phones/{$phoneUuid}", ['json' => $payload]);
                } else {
                    // Create new phone
                    $payload = [
                        'data' => [
                            'type' => 'phones',
                            'attributes' => $attributes, // Ensure 'type', 'number', 'primary' are set as needed
                        ],
                    ];
                    $client->post("people/{$personUuid}/phones", ['json' => $payload]);
                }
            } catch (RequestException $e) {
                $action = empty($phoneUuid) ? 'create' : 'update';
                $errorMsg = sprintf('Failed to %s phone (UUID: %s).', $action, $phoneUuid ?? 'N/A');
                $context = [
                    'source' => __METHOD__,
                    'person_uuid' => $personUuid,
                    'phone_uuid' => $phoneUuid,
                    'payload' => $payload,
                    'original_exception' => $e->getMessage(),
                ];
                if ($e->hasResponse()) {
                    $context['statusCode'] = $e->getResponse()->getStatusCode();
                    $context['responseBody'] = $e->getResponse()->getBody()->getContents();
                }
                WACC()->Log->error($errorMsg, $context);
                $errors[] = $errorMsg . ' API Error: ' . $e->getMessage();
            } catch (Exception $e) {
                $action = empty($phoneUuid) ? 'create' : 'update';
                $errorMsg = sprintf('Generic exception during phone %s (UUID: %s).', $action, $phoneUuid ?? 'N/A');
                WACC()->Log->error($errorMsg, ['source' => __METHOD__, 'person_uuid' => $personUuid, 'phone_uuid' => $phoneUuid, 'message' => $e->getMessage()]);
                $errors[] = $errorMsg . ' ' . $e->getMessage();
            }
        }

        if (empty($errors)) {
            return ['success' => true, 'error' => []];
        }

        return ['success' => false, 'error' => $errors];
    }

    /**
     * Update or create email addresses for a person.
     *
     * @param string $personUuid The UUID of the person.
     * @param array $emailsInput Array of email data. Each item should be an associative array.
     *                           If 'uuid' is present, email is updated. Otherwise, it's created.
     *                           Example item: ['uuid' => 'xyz', 'type' => 'work', 'address' => 'test@example.com', 'primary' => true]
     * @return array ['success' => bool, 'error' => array of error messages]
     */
    protected function updatePersonEmailsInternal(string $personUuid, array $emailsInput): array
    {
        $client = $this->initClient();
        if (!$client) {
            return ['success' => false, 'error' => ['API client initialization failed.']];
        }

        $errors = [];
        $readOnlyAttributes = [
            'uuid', 'type_external_id', 'localpart', 'domain', 'email', 'unique',
            'created_at', 'updated_at', 'deleted_at', 'consent', 'consent_third_party', 'consent_directory',
        ];

        foreach ($emailsInput as $emailItem) {
            if (!is_array($emailItem)) {
                $errors[] = 'Invalid email item data provided; not an array.';
                continue;
            }

            $attributes = $emailItem;
            $emailUuid = $emailItem['uuid'] ?? null;

            // Remove read-only attributes for PATCH and UUID from attributes payload
            foreach ($readOnlyAttributes as $key) {
                unset($attributes[$key]);
            }
            if ($emailUuid) { // If it's an update, uuid is in $emailUuid, not needed in attributes
                unset($attributes['uuid']);
            }

            // Sanitize attributes if the global helper exists
            if (function_exists('wicket_filter_null_and_blank')) {
                $attributes = wicket_filter_null_and_blank($attributes);
            }

            if (empty($attributes)) {
                continue; // Nothing to update or create
            }

            try {
                if (!empty($emailUuid)) {
                    // Update existing email
                    $payload = [
                        'data' => [
                            'id' => $emailUuid,
                            'type' => 'emails',
                            'attributes' => $attributes,
                        ],
                    ];
                    $client->patch("emails/{$emailUuid}", ['json' => $payload]);
                } else {
                    // Create new email
                    $payload = [
                        'data' => [
                            'type' => 'emails',
                            'attributes' => $attributes, // Ensure 'address', 'type', 'primary', 'unique' are set as needed
                        ],
                    ];
                    $client->post("people/{$personUuid}/emails", ['json' => $payload]);
                }
            } catch (RequestException $e) {
                $action = empty($emailUuid) ? 'create' : 'update';
                $errorMsg = sprintf('Failed to %s email (UUID: %s).', $action, $emailUuid ?? 'N/A');
                $context = [
                    'source' => __METHOD__,
                    'person_uuid' => $personUuid,
                    'email_uuid' => $emailUuid,
                    'payload' => $payload,
                    'original_exception' => $e->getMessage(),
                ];
                if ($e->hasResponse()) {
                    $context['statusCode'] = $e->getResponse()->getStatusCode();
                    $context['responseBody'] = $e->getResponse()->getBody()->getContents();
                }
                WACC()->Log->error($errorMsg, $context);
                $errors[] = $errorMsg . ' API Error: ' . $e->getMessage();
            } catch (Exception $e) {
                $action = empty($emailUuid) ? 'create' : 'update';
                $errorMsg = sprintf('Generic exception during email %s (UUID: %s).', $action, $emailUuid ?? 'N/A');
                WACC()->Log->error($errorMsg, ['source' => __METHOD__, 'person_uuid' => $personUuid, 'email_uuid' => $emailUuid, 'message' => $e->getMessage()]);
                $errors[] = $errorMsg . ' ' . $e->getMessage();
            }
        }

        if (empty($errors)) {
            return ['success' => true, 'error' => []];
        }

        return ['success' => false, 'error' => $errors];
    }

    /**
     * Update or create web addresses for a person.
     *
     * @param string $personUuid The UUID of the person.
     * @param array $webAddressesInput Array of web address data. Each item should be an associative array.
     *                                 If 'uuid' is present, web address is updated. Otherwise, it's created.
     *                                 Example item: ['uuid' => 'xyz', 'type' => 'website', 'address' => 'https://example.com']
     * @return array ['success' => bool, 'error' => array of error messages]
     */
    protected function updatePersonWebAddressesInternal(string $personUuid, array $webAddressesInput): array
    {
        $client = $this->initClient();
        if (!$client) {
            return ['success' => false, 'error' => ['API client initialization failed.']];
        }

        $errors = [];
        $readOnlyAttributes = [
            'uuid', 'type_external_id', 'data', 'created_at', 'updated_at', 'deleted_at',
            'consent', 'consent_third_party', 'consent_directory',
        ];

        foreach ($webAddressesInput as $webAddressItem) {
            if (!is_array($webAddressItem)) {
                $errors[] = 'Invalid web address item data provided; not an array.';
                continue;
            }

            $attributes = $webAddressItem;
            $webAddressUuid = $webAddressItem['uuid'] ?? null;

            // Remove read-only attributes for PATCH and UUID from attributes payload
            foreach ($readOnlyAttributes as $key) {
                unset($attributes[$key]);
            }
            if ($webAddressUuid) { // If it's an update, uuid is in $webAddressUuid, not needed in attributes
                unset($attributes['uuid']);
            }

            // Sanitize attributes if the global helper exists
            if (function_exists('wicket_filter_null_and_blank')) {
                $attributes = wicket_filter_null_and_blank($attributes);
            }

            if (empty($attributes)) {
                continue; // Nothing to update or create
            }

            try {
                if (!empty($webAddressUuid)) {
                    // Update existing web address
                    $payload = [
                        'data' => [
                            'id' => $webAddressUuid,
                            'type' => 'web-addresses', // API uses 'web-addresses'
                            'attributes' => $attributes,
                        ],
                    ];
                    $client->patch("web-addresses/{$webAddressUuid}", ['json' => $payload]);
                } else {
                    // Create new web address
                    $payload = [
                        'data' => [
                            'type' => 'web-addresses', // API uses 'web-addresses'
                            'attributes' => $attributes, // Ensure 'address', 'type' are set as needed
                        ],
                    ];
                    $client->post("people/{$personUuid}/web-addresses", ['json' => $payload]);
                }
            } catch (RequestException $e) {
                $action = empty($webAddressUuid) ? 'create' : 'update';
                $errorMsg = sprintf('Failed to %s web address (UUID: %s).', $action, $webAddressUuid ?? 'N/A');
                $context = [
                    'source' => __METHOD__,
                    'person_uuid' => $personUuid,
                    'web_address_uuid' => $webAddressUuid,
                    'payload' => $payload,
                    'original_exception' => $e->getMessage(),
                ];
                if ($e->hasResponse()) {
                    $context['statusCode'] = $e->getResponse()->getStatusCode();
                    $context['responseBody'] = $e->getResponse()->getBody()->getContents();
                }
                WACC()->Log->error($errorMsg, $context);
                $errors[] = $errorMsg . ' API Error: ' . $e->getMessage();
            } catch (Exception $e) {
                $action = empty($webAddressUuid) ? 'create' : 'update';
                $errorMsg = sprintf('Generic exception during web address %s (UUID: %s).', $action, $webAddressUuid ?? 'N/A');
                WACC()->Log->error($errorMsg, ['source' => __METHOD__, 'person_uuid' => $personUuid, 'web_address_uuid' => $webAddressUuid, 'message' => $e->getMessage()]);
                $errors[] = $errorMsg . ' ' . $e->getMessage();
            }
        }

        if (empty($errors)) {
            return ['success' => true, 'error' => []];
        }

        return ['success' => false, 'error' => $errors];
    }
}
