<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Account Centre
 * Post Types.
 */

/**
 * Registers Class.
 */
class Registers extends WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_nav_menu']); // In case we need to check for logged in user/role. See https://wordpress.stackexchange.com/questions/217351/on-which-hook-should-i-be-calling-register-nav-menus
        add_filter('theme_page_templates', [$this, 'register_acc_page_template'], 10, 3);
        add_filter('template_include', [$this, 'load_acc_page_template'], 10, 1);
    }

    /**
     * Register Post Type.
     */
    public function register_post_type()
    {
        // Set UI labels for custom post type
        $labels = [
            'name'               => esc_html__('Account Centre', 'wicket-acc'),
            'singular_name'      => esc_html__('Page', 'wicket-acc'),
            'add_new_item'       => esc_html__('Add New Page', 'wicket-acc'),
            'add_new'            => esc_html__('Add New Page', 'wicket-acc'),
            'edit_item'          => esc_html__('Edit Page', 'wicket-acc'),
            'view_item'          => esc_html__('View Page', 'wicket-acc'),
            'update_item'        => esc_html__('Update Page', 'wicket-acc'),
            'search_items'       => esc_html__('Search Page', 'wicket-acc'),
            'not_found'          => esc_html__('Not Found', 'wicket-acc'),
            'not_found_in_trash' => esc_html__('Not found in Trash', 'wicket-acc'),
            'menu_name'          => esc_html__('Account Centre', 'wicket-acc'),
            'parent_item_colon'  => '',
            'all_items'          => esc_html__('All Pages', 'wicket-acc'),
            'attributes'         => __('Pages Sorting Order'),
        ];

        // Set other options for custom post type
        $args = [
            'labels'              => $labels,
            'menu_icon'           => '',
            'public'              => true,
            'publicly_queryable'  => true,
            'exclude_from_search' => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => true,
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => true,
            'menu_position'       => 30,
            'menu_icon'           => WICKET_ACC_URL . '/assets/images/wicket-logo-20-white.svg',
            'rewrite'             => true,
            'supports'            => [
                'title',
                'page-attributes',
                'editor',
                'custom-fields',
                'revisions',
                'thumbnail',
            ],
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
        ];

        // Register ACC CPT
        register_post_type('my-account', $args);
    }

    /**
     * Register Custom Navigation Menu.
     */
    public function register_nav_menu()
    {
        // This theme uses wp_nav_menu() in one location.
        register_nav_menus([
            'wicket-acc-nav' => esc_html__('Account Centre Menu', 'wicket-acc'),
        ]);

        // This theme offers a secondary wp_nav_menu()
        register_nav_menus([
            'wicket-acc-nav-secondary' => esc_html__('Account Centre Secondary Menu', 'wicket-acc'),
        ]);
    }

    /**
     * Register ACC page template
     * Shows ACC page as selectable page template in the page editor.
     *
     * @param array $page_templates
     * @param string $theme
     * @param WP_Post $post
     *
     * @return array
     */
    public function register_acc_page_template($page_templates, $theme, $post)
    {
        $templates = [
            'account-centre/page-acc.php' => __('ACC Page', 'wicket-acc'),
            'account-centre/page-acc-org_id.php' => __('ACC Page with Org ID', 'wicket-acc'),
        ];

        foreach ($templates as $template_file => $template_name) {
            $template_path = WICKET_ACC_PLUGIN_TEMPLATE_PATH . $template_file;

            if (file_exists($template_path)) {
                $page_templates['plugins/wicket-wp-account-centre/templates-wicket/' . $template_file] = $template_name;
            }
        }

        return $page_templates;
    }

    /**
     * Load ACC page template
     * Loads the ACC page template.
     *
     * @param string $template
     *
     * @return string
     */
    public function load_acc_page_template($template)
    {
        $requested_slug = get_page_template_slug();
        $requested_basename = basename($requested_slug);

        $template_basename = basename($template);

        if ($requested_basename === 'page-acc.php' && $template_basename !== 'search.php') {
            $template = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/page-acc.php';

            if (file_exists($template)) {
                return $template;
            }
        }

        return $template;
    }
}
