<?php

namespace WicketAcc;

/**
 * Account class.
 */
class Account
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('init', [$this, 'register_meta']);
        add_action('init', [$this, 'register_blocks']);
        add_action('init', [$this, 'register_shortcodes']);
        add_action('init', [$this, 'register_assets']);
        add_action('init', [$this, 'register_menus']);
        add_action('init', [$this, 'register_sidebars']);
        add_action('init', [$this, 'register_widgets']);
        add_action('init', [$this, 'register_rest_routes']);
        add_action('init', [$this, 'register_rewrite_rules']);
        add_action('init', [$this, 'register_query_vars']);
        add_action('init', [$this, 'register_post_statuses']);
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('init', [$this, 'register_meta']);
        add_action('init', [$this, 'register_blocks']);
        add_action('init', [$this, 'register_shortcodes']);
        add_action('init', [$this, 'register_assets']);
        add_action('init', [$this, 'register_menus']);
        add_action('init', [$this, 'register_sidebars']);
        add_action('init', [$this, 'register_widgets']);
        add_action('init', [$this, 'register_rest_routes']);
        add_action('init', [$this, 'register_rewrite_rules']);
        add_action('init', [$this, 'register_query_vars']);
        add_action('init', [$this, 'register_post_statuses']);
    }

    /**
     * Register post types.
     */
    public function register_post_types()
    {
        // Register post types
    }

    /**
     * Register taxonomies.
     */
    public function register_taxonomies()
    {
        // Register taxonomies
    }

    /**
     * Register meta.
     */
    public function register_meta()
    {
        // Register meta
    }

    /**
     * Register blocks.
     */
    public function register_blocks()
    {
        // Register blocks
    }

    /**
     * Register shortcodes.
     */
    public function register_shortcodes()
    {
        // Register shortcodes
    }

    /**
     * Register assets.
     */
    public function register_assets()
    {
        // Register assets
    }

    /**
     * Register menus.
     */
    public function register_menus()
    {
        // Register menus
    }

    /**
     * Register sidebars.
     */
    public function register_sidebars()
    {
        // Register sidebars
    }

    /**
     * Register widgets.
     */
    public function register_widgets()
    {
        // Register widgets
    }

    /**
     * Register REST routes.
     */
    public function register_rest_routes()
    {
        // Register REST routes
    }

    /**
     * Register rewrite rules.
     */
    public function register_rewrite_rules()
    {
        // Register rewrite rules
    }

    /**
     * Register query vars.
     */
    public function register_query_vars()
    {
        // Register query vars
    }

    /**
     * Register post statuses.
     */
    public function register_post_statuses()
    {
        // Register post statuses
    }
}
