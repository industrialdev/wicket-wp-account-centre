<?php

declare(strict_types=1);

namespace WicketAcc\Mdp;

use GuzzleHttp\Exception\RequestException;

// No direct access
defined('ABSPATH') || exit;

/**
 * Handles MDP Schema API endpoints.
 */
class Schema extends Init
{
    /**
     * Get all JSON Schemas from Wicket.
     *
     * @return array|false Array of JSON schemas on success, false on failure.
     */
    public function getSchemas()
    {
        static $schemas = null;

        if (is_null($schemas)) {
            try {
                $client = $this->initClient();
                if (!$client) {
                    WACC()->Log->error('Failed to initialize API client', ['source' => __METHOD__]);

                    return false;
                }

                $response = $client->get('json_schemas');
                $schemas = $response['data'] ?? false;

                if (empty($schemas)) {
                    WACC()->Log->info('No JSON schemas found', ['source' => __METHOD__]);

                    return false;
                }

                WACC()->Log->debug('Successfully fetched JSON schemas', [
                    'source' => __METHOD__,
                    'schema_count' => is_countable($schemas) ? count($schemas) : 0,
                ]);
            } catch (RequestException $e) {
                $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
                WACC()->Log->error("Error fetching JSON schemas (HTTP {$statusCode}): " . $e->getMessage(), [
                    'source' => __METHOD__,
                    'statusCode' => $statusCode,
                ]);

                return false;
            } catch (\Exception $e) {
                WACC()->Log->error('Unexpected error fetching JSON schemas: ' . $e->getMessage(), [
                    'source' => __METHOD__,
                    'exception' => get_class($e),
                ]);

                return false;
            }
        }

        return $schemas;
    }

    /**
     * Load options from a schema based on a schema entry.
     *
     * This method refactors the legacy `wicket_get_schemas_options` function,
     * addressing several structural issues and potential bugs.
     *
     * @param array       $schema   The schema entry from Wicket.
     * @param string      $field    The primary field to extract options from.
     * @param string|null $subField An optional sub-field for nested structures.
     *
     * @return array An array of options, each with a 'key' and 'value'.
     */
    public function getSchemaOptions(array $schema, string $field, ?string $subField = null): array
    {
        if (empty($schema) || empty($field)) {
            WACC()->Log->warning('getSchemaOptions called with invalid parameters.', [
                'source' => __METHOD__,
                'field' => $field,
                'subField' => $subField,
            ]);

            return [];
        }

        $language = strtok(get_bloginfo('language'), '-');
        $attributes = $schema['attributes'] ?? [];
        $schemaData = $attributes['schema'] ?? [];
        $uiSchema = $attributes['ui_schema'] ?? [];

        $enumValues = [];
        $enumLabels = [];

        // --- Extract Values (Enums) ---
        // The logic is structured to find the first available enum list, avoiding conflicts.
        if (isset($schemaData['properties'][$field]['enum'])) {
            $enumValues = $schemaData['properties'][$field]['enum']; // Single value
        } elseif (isset($schemaData['properties'][$field]['items']['enum'])) {
            $enumValues = $schemaData['properties'][$field]['items']['enum']; // Multi-value
        } elseif (isset($schemaData['oneOf'][0]['properties'][$field]['items']['enum'])) {
            $enumValues = $schemaData['oneOf'][0]['properties'][$field]['items']['enum']; // UI schema oneOf
        } elseif (!empty($subField)) {
            if (isset($schemaData['properties'][$field]['items']['properties'][$subField]['enum'])) {
                $enumValues = $schemaData['properties'][$field]['items']['properties'][$subField]['enum']; // Repeater
            } elseif (isset($schemaData['properties'][$field]['items']['properties'][$subField]['items']['enum'])) {
                $enumValues = $schemaData['properties'][$field]['items']['properties'][$subField]['items']['enum']; // Nested repeater
            } elseif (isset($schemaData['properties'][$field]['oneOf'])) {
                // Object type field
                foreach ($schemaData['properties'][$field]['oneOf'] as $item) {
                    if (isset($item['properties'][$subField]['enum'][0])) {
                        $enumValues[] = $item['properties'][$subField]['enum'][0];
                    }
                }
            } elseif (isset($schemaData['properties'][$field]['items']['oneOf'])) {
                // Dependent object type field
                foreach ($schemaData['properties'][$field]['items']['oneOf'] as $item) {
                    if (isset($item['properties'][$subField]['items']['enum'])) {
                        $enumValues = array_merge($enumValues, $item['properties'][$subField]['items']['enum']);
                    }
                }
            }
        }

        // --- Extract Labels ---
        // The logic is structured to find the first available label list.
        if (isset($uiSchema[$field]['ui:i1e8n']['enumNames'][$language])) {
            $enumLabels = $uiSchema[$field]['ui:i18n']['enumNames'][$language];
        } elseif (!empty($subField) && isset($uiSchema[$field]['items'][$subField]['ui:i18n']['enumNames'][$language])) {
            $enumLabels = $uiSchema[$field]['items'][$subField]['ui:i18n']['enumNames'][$language];
        } elseif (!empty($subField) && isset($schemaData['properties'][$field]['items']['properties'][$subField]['enumNames'])) {
            // This is an exception where labels are in the main schema, not ui_schema.
            $enumLabels = $schemaData['properties'][$field]['items']['properties'][$subField]['enumNames'];
        }

        if (empty($enumValues)) {
            return [];
        }

        // --- Combine values and labels ---
        $options = [];
        foreach ($enumValues as $index => $value) {
            $options[] = [
                'key'   => $value,
                'value' => $enumLabels[$index] ?? $value, // Use label if available, otherwise fallback to the value itself
            ];
        }

        return $options;
    }

    /**
     * Gets all the options for a field within a specific JSON schema.
     *
     * This method finds a specific schema by its key (parent field) and then
     * extracts the options for a given field within that schema.
     *
     * @param string      $parentField The key of the parent schema to search for.
     * @param string      $field       The field within the schema to get options from.
     * @param string|null $subField    Optional. The sub-field for nested objects or repeaters.
     *
     * @return array An array of options, or an empty array on failure.
     */
    public function getSchemaFieldValues(string $parentField, string $field, ?string $subField = null): array
    {
        $schemas = $this->getSchemas();

        if (empty($schemas['data'])) {
            WACC()->Log->warning('No schemas found or schemas data is empty.', [
                'source' => __METHOD__,
            ]);

            return [];
        }

        $targetSchema = null;
        foreach ($schemas['data'] as $schema) {
            if (($schema['attributes']['key'] ?? null) === $parentField) {
                $targetSchema = $schema;
                break;
            }
        }

        if (!$targetSchema) {
            WACC()->Log->info('Schema with specified parent field not found.', [
                'source'      => __METHOD__,
                'parentField' => $parentField,
            ]);

            return [];
        }

        return $this->getSchemaOptions($targetSchema, $field, $subField);
    }
}
