<?php

namespace WicketAcc;

use Carbon_Fields\Datastore\Datastore;
use Carbon_Fields\Field\Field;

/**
 * Custom datastore for Wicket settings.
 *
 * Stores all fields in a single serialized array in the 'wicket_settings' option.
 */
class CFWicketSettingsDatastore extends Datastore
{
    /**
     * The option name where settings are stored.
     */
    public const OPTION_NAME = 'wicket_settings';

    /**
     * Load the field value.
     *
     * @param Field $field The field to load.
     * @return mixed The field value.
     */
    public function load(Field $field)
    {
        $settings = get_option(self::OPTION_NAME, []);

        return $settings[$field->get_base_name()] ?? null;
    }

    /**
     * Save the field value.
     *
     * @param Field $field The field to save.
     */
    public function save(Field $field)
    {
        // Only save root-level fields to avoid saving complex field internals individually
        if (!empty($field->get_hierarchy())) {
            return;
        }

        $settings = get_option(self::OPTION_NAME, []);
        $settings[$field->get_base_name()] = $field->get_value();
        update_option(self::OPTION_NAME, $settings);
    }

    /**
     * Delete the field value.
     *
     * @param Field $field The field to delete.
     */
    public function delete(Field $field)
    {
        $settings = get_option(self::OPTION_NAME, []);
        if (isset($settings[$field->get_base_name()])) {
            unset($settings[$field->get_base_name()]);
            update_option(self::OPTION_NAME, $settings);
        }
    }

    /**
     * Not used for this datastore.
     * Needed for Carbon Fields compatibility.
     */
    public function init() {}
}
