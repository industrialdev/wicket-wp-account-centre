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
            return $client->people->fetch($person_uuid, ['include' => 'emails,phones,addresses,identities']);
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
}
