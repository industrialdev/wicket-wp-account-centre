<?php

/**
 * Relationship Helper for Org Management.
 */

namespace WicketORM\Helpers;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Relationship Helper class extending the base Helper.
 */
class RelationshipHelper extends Helper
{
    /**
     * Get the default person-organization relationship type for ORM member additions.
     *
     * This function retrieves the configured default relationship type from the
     * WicketORM configuration, with a fallback to 'employee' for backwards compatibility.
     *
     * @return string The default relationship type.
     */
    public static function get_default_relationship_type(): string
    {
        $config = self::get_config();

        return $config['relationships']['defaults']['type'] ?? 'employee_staff';
    }

    /**
     * Get all available relationship types.
     *
     * @return array Available relationship types
     */
    public static function get_available_relationship_types(): array
    {
        return wicket_get_person_org_relationship_types();
    }

    /**
     * Validate relationship type.
     *
     * @param string $type The relationship type to validate
     * @return bool True if valid, false otherwise
     */
    public static function is_valid_relationship_type(string $type): bool
    {
        return wicket_is_valid_person_org_relationship_type($type);
    }
}
