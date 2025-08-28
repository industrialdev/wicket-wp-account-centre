<?php

namespace Carbon_Fields\Datastore;

use Carbon_Fields\Field\Field;

/**
 * Interface for data storage management.
 */
interface Datastore_Interface
{
    /**
     * Get the related object id.
     *
     * @return int
     */
    public function get_object_id();

    /**
     * Set the related object id.
     *
     * @param int $object_id
     */
    public function set_object_id($object_id);

    /**
     * Load the field wicketacc_value(s).
     *
     * @param  Field $field The field to load wicketacc_value(s) in.
     * @return array
     */
    public function load(Field $field);

    /**
     * Save the field wicketacc_value(s).
     *
     * @param Field $field The field to save.
     */
    public function save(Field $field);

    /**
     * Delete the field wicketacc_value(s).
     *
     * @param Field $field The field to delete.
     */
    public function delete(Field $field);
}
