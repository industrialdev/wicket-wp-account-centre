<?php

declare(strict_types=1);

namespace WicketAcc\Mdp;

use Exception;
use GuzzleHttp\Exception\RequestException;

// No direct access
defined('ABSPATH') || exit;

/**
 * Handles various MDP helper/utility API endpoints.
 */
class Helper extends Init
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Retrieve a single interval resource by its ID.
     *
     * @param int|string $uuid The UUID of the interval to fetch.
     * @return object|false The Wicket SDK Interval resource object on success, false on failure.
     */
    public function getIntervalById(int|string $uuid): object|false
    {
        if (empty($uuid)) {
            WACC()->Log->warning('Interval ID cannot be empty.', ['source' => __METHOD__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            // initClient() already logs the error.
            return false;
        }

        try {
            return $client->intervals->fetch($uuid);
        } catch (RequestException $e) {
            $response_code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            WACC()->Log->error(
                'RequestException while fetching interval by ID.',
                [
                    'source' => __METHOD__,
                    'uuid' => $uuid,
                    'status_code' => $response_code,
                    'message' => $e->getMessage(),
                ]
            );

            return false;
        } catch (Exception $e) {
            WACC()->Log->error(
                'Generic Exception while fetching interval by ID.',
                [
                    'source' => __METHOD__,
                    'uuid' => $uuid,
                    'message' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * Gets the name of a resource type by its slug.
     *
     * Fetches all resource types from the API and searches for a match.
     * Consider caching the resource types for better performance if called frequently.
     *
     * @param string $slug The slug of the resource type (e.g., 'organization_type', 'person_gender').
     * @return string|false The name of the resource type if found, otherwise false.
     */
    public function getResourceTypeNameBySlug(string $slug): string|false
    {
        if (empty($slug)) {
            WACC()->Log->warning('Slug cannot be empty.', ['source' => __METHOD__]);

            return false;
        }

        $client = WACC()->Mdp->initClient();
        if (!$client) {
            WACC()->Log->error('Failed to initialize API client.', ['source' => __METHOD__]);

            return false;
        }

        try {
            $response = $client->get('resource_types');
        } catch (RequestException $e) {
            WACC()->Log->error(
                'API request to /resource_types failed.',
                [
                    'source' => __METHOD__,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]
            );

            return false;
        }

        if (empty($response['data']) || !is_array($response['data'])) {
            WACC()->Log->warning(
                'No data or invalid data format in /resource_types response.',
                ['source' => __METHOD__, 'response' => $response]
            );

            return false;
        }

        foreach ($response['data'] as $resourceType) {
            if (
                isset($resourceType['attributes']['slug'])
                && $resourceType['attributes']['slug'] === $slug
                && isset($resourceType['attributes']['name'])
            ) {
                return $resourceType['attributes']['name'];
            }
        }

        WACC()->Log->info(
            'Resource type name not found for slug.',
            ['source' => __METHOD__, 'slug' => $slug]
        );

        return false;
    }

    /**
     * Build the data_fields array for an API request from form submission data.
     *
     * This method processes a single field from the submitted form data (e.g., $_POST),
     * handles type casting, validation, and formats it correctly for the Wicket API.
     * The resulting structured data is added to the $dataFields array, which is passed by reference.
     *
     * @param array   &$dataFields The array to build, passed by reference.
     * @param string  $field       The name of the field to process.
     * @param string  $schema      The schema ID for the field.
     * @param string  $type        The expected data type ('string', 'array', 'int', 'boolean', 'object', 'readonly', 'array_oneof').
     * @param array   $postData    The submitted form data (e.g., $_POST).
     * @param ?object $entity      The pre-existing entity (person/org) for readonly fields. Defaults to null.
     */
    public function addDataField(
        array &$dataFields,
        string $field,
        string $schema,
        string $type,
        array $postData,
        ?object $entity = null
    ): void {
        $value = null;

        if (isset($postData[$field])) {
            $value = $postData[$field];

            // --- Handle specific types from POST data ---
            switch ($type) {
                case 'array':
                    if (empty(array_filter($value))) {
                        WACC()->Log->info('Skipped processing data field.', ['source' => __METHOD__, 'field' => $field, 'schema' => $schema, 'reason' => 'Empty array value submitted']);

                        return;
                    }
                    break;
                case 'string':
                    if ($value === '') {
                        WACC()->Log->info('Skipped processing data field.', ['source' => __METHOD__, 'field' => $field, 'schema' => $schema, 'reason' => 'Empty string value submitted']);

                        return;
                    }
                    break;
                case 'boolean':
                    if ($value === '1') {
                        $value = true;
                    } elseif ($value === '0') {
                        $value = false;
                    } else {
                        WACC()->Log->info('Skipped processing data field.', ['source' => __METHOD__, 'field' => $field, 'schema' => $schema, 'reason' => 'Empty boolean value submitted']);

                        return;
                    }
                    break;
                case 'int':
                    if ($value) {
                        $value = (int) $value;
                    } else {
                        WACC()->Log->info('Skipped processing data field.', ['source' => __METHOD__, 'field' => $field, 'schema' => $schema, 'reason' => 'Empty integer value submitted']);

                        return;
                    }
                    break;
                case 'object':
                    if ($value) {
                        foreach ($value as &$index) {
                            $index = (array) json_decode(stripslashes($index), true);
                        }
                    }
                    break;
            }
        } else {
            // --- Handle fields NOT in POST data (e.g., clearing values or readonly) ---
            if ($type === 'array' || $type === 'object') {
                $value = [];
            } elseif ($type === 'readonly' && $entity) {
                $value = $this->getReadOnlyValue($field, $schema, $entity);
                if ($value === null) {
                    WACC()->Log->info('Skipped processing data field.', ['source' => __METHOD__, 'field' => $field, 'schema' => $schema, 'reason' => 'Readonly field with no existing value']);

                    return;
                }
            } else {
                WACC()->Log->info('Skipped processing data field.', ['source' => __METHOD__, 'field' => $field, 'schema' => $schema, 'reason' => 'Field not present in submission']);

                return;
            }
        }

        // Add the processed value to the dataFields array
        $dataFields[$schema]['value'][$field] = $value;
        $dataFields[$schema]['$schema'] = $schema;
    }

    /**
     * Retrieve the value for a readonly field from an entity's existing data_fields.
     *
     * @param string $field The field name.
     * @param string $schema The schema ID.
     * @param object $entity The entity object.
     * @return mixed|null The existing value or null if not found.
     */
    private function getReadOnlyValue(string $field, string $schema, object $entity): mixed
    {
        if (empty($entity->data_fields)) {
            return null;
        }

        foreach ((array) $entity->data_fields as $df) {
            $df = (array) $df; // Ensure it's an array for consistent access
            if (($df['$schema'] ?? '') === $schema) {
                return $df['value'][$field] ?? null;
            }
        }

        return null;
    }

    /**
     * Converts an object to a clean array and sanitizes property keys.
     *
     * This function is helpful for converting SDK objects (e.g., Person object)
     * into arrays with accessible keys, removing null-byte prefixes from
     * protected/private property names that occur during serialization.
     *
     * @param object $dataObject The object to convert.
     * @return array The cleaned array representation of the object.
     */
    public function convertObjectToArray(object $dataObject): array
    {
        // Serialize and unserialize to force object into an array, exposing all properties.
        // ['allowed_classes' => false] prevents instantiation of objects during unserialization.
        $array = (array) unserialize(serialize($dataObject), ['allowed_classes' => false]);

        $cleanArray = [];
        foreach ($array as $key => $value) {
            // Remove null-byte prefixes from keys (e.g., "\0ClassName\0propertyName" or "\0*\0propertyName")
            // These prefixes are added by PHP when serializing protected or private properties.
            $cleanKey = preg_replace('/^\x00(?:\*|[^\x00]+)\x00/', '', $key);
            $cleanArray[$cleanKey] = $value;
        }

        return $cleanArray;
    }

    /**
     * Little helper function that acts as a version of array_filter()
     * that *doesn't strip out 0 values, which we might actually want for purposes of sending
     * data back to the MDP.
     *
     * @param array $array
     *
     * @return array that has had it's null and blank string values removed.
     */
    public function filterNullAndBlank($array)
    {
        return array_filter($array, static function ($var) {
            return $var !== null && $var !== '';
        });
    }
}
