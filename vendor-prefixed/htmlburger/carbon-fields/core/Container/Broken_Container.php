<?php

namespace Carbon_Fields\Container;

/**
 * Broken container class.
 * Used when a container gets misconfigured.
 */
class Broken_Container extends Container
{
    public function add_fields($fields) {}

    public function init() {}

    protected function is_valid_save()
    {
        return false;
    }

    protected function get_environment_for_request()
    {
        return [];
    }

    public function is_valid_attach_for_request()
    {
        return false;
    }

    protected function get_environment_for_object($object_id)
    {
        return [];
    }

    public function is_valid_attach_for_object($object_id = null)
    {
        return false;
    }
}
