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
                    WACC()->Log()->error('Failed to initialize API client', ['source' => __CLASS__]);

                    return false;
                }

                $response = $client->get('json_schemas');
                $schemas = $response['data'] ?? false;

                if (empty($schemas)) {
                    WACC()->Log()->info('No JSON schemas found', ['source' => __CLASS__]);

                    return false;
                }

                // Successfully fetched JSON schemas; omit debug logging in production
            } catch (RequestException $e) {
                $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
                WACC()->Log()->error("Error fetching JSON schemas (HTTP {$statusCode}): " . $e->getMessage(), [
                    'source' => __CLASS__,
                    'statusCode' => $statusCode,
                ]);

                return false;
            } catch (\Exception $e) {
                WACC()->Log()->error('Unexpected error fetching JSON schemas: ' . $e->getMessage(), [
                    'source' => __CLASS__,
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
            WACC()->Log()->warning('getSchemaOptions called with invalid parameters.', [
                'source' => __CLASS__,
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
            WACC()->Log()->warning('No schemas found or schemas data is empty.', [
                'source' => __CLASS__,
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
            WACC()->Log()->info('Schema with specified parent field not found.', [
                'source'      => __CLASS__,
                'parentField' => $parentField,
            ]);

            return [];
        }

        return $this->getSchemaOptions($targetSchema, $field, $subField);
    }

    /**
     * Transient key for the cached profile-image MDP field list.
     */
    public const PROFILE_IMAGE_FIELDS_TRANSIENT = 'wicket_acc_mdp_profile_image_fields';

    /**
     * List every additional_info widget schema and its storable sub-fields,
     * grouped for a select dropdown.
     *
     * Source is getSchemas() (the tenant json_schemas endpoint). The list is
     * tenant-wide and does not depend on any one person record.
     *
     * Used by the Account Centre profile-picture setting so the Implementation
     * Specialist can pick which MDP additional_info field stores the uploaded
     * image URL.
     *
     * @return array<int, array{schema_slug: string, schema_label: string, fields: array<int, array{slug: string, label: string}>}>
     *     Empty array when no schemas or no storable fields are found.
     */
    public function getProfileImageFieldOptions(): array
    {
        $schemas = $this->getSchemas();
        if (!is_array($schemas) || empty($schemas)) {
            return [];
        }

        return self::buildProfileImageFieldOptions($schemas, $this->currentLanguage());
    }

    /**
     * Cached profile-image MDP field options, transient-backed.
     *
     * Wraps getProfileImageFieldOptions() with a WordPress transient so the
     * settings UI and refresh route do not hit MDP on every render.
     *
     * @param int $ttl Cache lifetime in seconds. Default 6 hours.
     *
     * @return array Grouped options (see getProfileImageFieldOptions()).
     */
    public function getCachedProfileImageFieldOptions(int $ttl = 21600): array
    {
        $cached = get_transient(self::PROFILE_IMAGE_FIELDS_TRANSIENT);
        if (is_array($cached)) {
            return $cached;
        }

        $options = $this->getProfileImageFieldOptions();
        set_transient(self::PROFILE_IMAGE_FIELDS_TRANSIENT, $options, $ttl);

        return $options;
    }

    /**
     * Drop the cached field list and refetch once. Returns the fresh list.
     *
     * Called by the Refresh button REST route. Re-seeds the transient.
     *
     * @return array
     */
    public function refreshProfileImageFieldOptions(): array
    {
        delete_transient(self::PROFILE_IMAGE_FIELDS_TRANSIENT);

        return $this->getCachedProfileImageFieldOptions();
    }

    /**
     * Pure extractor: turn raw json_schemas data into a grouped field list.
     *
     * Separated from getProfileImageFieldOptions() so it is unit-testable with a
     * fixture array and no MDP I/O. Walks both flat properties and oneOf
     * branches. Keeps only scalar fields that can store a single URL string.
     *
     * @param array  $schemas  Raw return of getSchemas() (array of schema resources, or a JSON:API {data:[...]} wrapper).
     * @param string $language ISO 639-1 code used to resolve ui_schema i18n labels (e.g. "en").
     *
     * @return array<int, array{schema_slug: string, schema_label: string, fields: array<int, array{slug: string, label: string}>}>
     */
    public static function buildProfileImageFieldOptions(array $schemas, string $language): array
    {
        $resources = self::normalizeSchemaResources($schemas);
        $grouped = [];

        foreach ($resources as $schema) {
            $attributes = $schema['attributes'] ?? [];
            if (!is_array($attributes)) {
                continue;
            }

            $schema_slug = isset($attributes['key']) && is_string($attributes['key']) ? $attributes['key'] : '';
            if ($schema_slug === '') {
                continue;
            }

            $json_schema = $attributes['schema'] ?? [];
            $ui_schema = isset($attributes['ui_schema']) && is_array($attributes['ui_schema']) ? $attributes['ui_schema'] : [];
            if (!is_array($json_schema)) {
                continue;
            }

            $fields = self::extractStorableStringFields($json_schema, $ui_schema, $language);
            if (empty($fields)) {
                continue;
            }

            $grouped[] = [
                'schema_slug'  => $schema_slug,
                'schema_label' => self::resolveSchemaLabel($attributes, $schema_slug),
                'fields'       => $fields,
            ];
        }

        return $grouped;
    }

    /**
     * Normalize raw getSchemas() output into a flat list of resource arrays.
     *
     * Accepts either a JSON:API collection wrapper ({data:[resource,...]}) or an
     * already-flat list of resources. Drops entries that are not resources.
     *
     * @param array $schemas
     *
     * @return array<int, array>
     */
    private static function normalizeSchemaResources(array $schemas): array
    {
        $list = isset($schemas['data']) && is_array($schemas['data']) ? $schemas['data'] : $schemas;

        $resources = [];
        foreach ($list as $item) {
            if (is_array($item) && isset($item['attributes'])) {
                $resources[] = $item;
            }
        }

        return $resources;
    }

    /**
     * Collect scalar, URL-capable sub-fields from a widget JSON schema.
     *
     * Merges properties from flat `properties` and from each `oneOf` branch
     * (polymorphic widgets). Excludes composite field shapes (repeaters,
     * objects) that cannot store a single URL string. De-duplicates by slug.
     *
     * @param array  $json_schema
     * @param array  $ui_schema
     * @param string $language
     *
     * @return array<int, array{slug: string, label: string}>
     */
    private static function extractStorableStringFields(array $json_schema, array $ui_schema, string $language): array
    {
        $property_sets = [];
        if (isset($json_schema['properties']) && is_array($json_schema['properties'])) {
            $property_sets[] = $json_schema['properties'];
        }
        if (isset($json_schema['oneOf']) && is_array($json_schema['oneOf'])) {
            foreach ($json_schema['oneOf'] as $branch) {
                if (is_array($branch) && isset($branch['properties']) && is_array($branch['properties'])) {
                    $property_sets[] = $branch['properties'];
                }
            }
        }

        $fields = [];
        $seen = [];
        foreach ($property_sets as $properties) {
            foreach ($properties as $slug => $definition) {
                if (!is_string($slug) || isset($seen[$slug])) {
                    continue;
                }
                if (!is_array($definition) || !self::isStorableScalarField($definition)) {
                    continue;
                }

                $seen[$slug] = true;
                $fields[] = [
                    'slug'  => $slug,
                    'label' => self::resolveFieldLabel($slug, $definition, $ui_schema, $language),
                ];
            }
        }

        usort($fields, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $fields;
    }

    /**
     * Decide whether a JSON schema property can store a single URL string.
     *
     * Excludes composite shapes (items, nested properties, oneOf/allOf) and
     * non-string scalar types. An untyped scalar with no composite keys is
     * treated as a candidate (MDP sometimes omits type on string fields).
     *
     * @param array $property
     *
     * @return bool
     */
    private static function isStorableScalarField(array $property): bool
    {
        if (isset($property['items']) || isset($property['properties']) || isset($property['oneOf']) || isset($property['allOf'])) {
            return false;
        }

        $type = $property['type'] ?? null;
        if ($type === 'string') {
            return true;
        }
        if (isset($property['format']) && is_string($property['format']) && strtolower($property['format']) === 'url') {
            return true;
        }

        return $type === null;
    }

    /**
     * Resolve a human label for a sub-field, preferring UI i18n, then the JSON
     * schema title, then the slug itself.
     *
     * @param string $slug
     * @param array  $property
     * @param array  $ui_schema
     * @param string $language
     *
     * @return string
     */
    private static function resolveFieldLabel(string $slug, array $property, array $ui_schema, string $language): string
    {
        $ui = $ui_schema[$slug] ?? null;
        if (is_array($ui)) {
            $i18n_title = $ui['ui:i18n']['title'][$language] ?? null;
            if (is_string($i18n_title) && trim($i18n_title) !== '') {
                return trim($i18n_title);
            }
            $ui_title = $ui['ui:title'] ?? null;
            if (is_string($ui_title) && trim($ui_title) !== '') {
                return trim($ui_title);
            }
        }

        $title = $property['title'] ?? null;
        if (is_string($title) && trim($title) !== '') {
            return trim($title);
        }

        return $slug;
    }

    /**
     * Resolve a human label for a widget schema, falling back to its slug.
     *
     * @param array  $attributes
     * @param string $fallback
     *
     * @return string
     */
    private static function resolveSchemaLabel(array $attributes, string $fallback): string
    {
        foreach (['name', 'title', 'label'] as $key) {
            $value = $attributes[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return $fallback;
    }

    /**
     * Current site language as an ISO 639-1 code (e.g. "en", "fr").
     *
     * @return string
     */
    private function currentLanguage(): string
    {
        $code = strtok((string) get_bloginfo('language'), '-');

        return is_string($code) && $code !== '' ? $code : 'en';
    }
}
