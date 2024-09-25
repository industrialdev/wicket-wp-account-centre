<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Language file for Wicket Account Centre Plugins
 *
 * @package  Wicket\Admin
 * @version  1.0.0
 */
class Language extends WicketAcc
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
    }

    /**
     * Load text domain
     */
    public function load_textdomain()
    {
        if (!is_admin() && function_exists('load_plugin_textdomain')) {
            $plugin_rel_path = dirname(plugin_basename(__FILE__)) . '/languages/';
            load_plugin_textdomain('wicket-acc', false, $plugin_rel_path);
        }
    }
}
