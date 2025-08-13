<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Router Class
 * Get ACC pages, IDs, slugs, and data needed to jump between them.
 *
 * If you need to translate my-account CPT slug for other languages, use WPML directly:
 * https://wpml.org/documentation/getting-started-guide/translating-page-slugs/
 */
class Router extends WicketAcc
{
    private $acc_page_id_cache = null;
    private $acc_slug_cache = null;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        if (apply_filters('wicket/acc/router/disable_router', false)) {
            return;
        }

        // DEBUG ONLY, check environment
        if (defined('WP_ENV') && WP_ENV === 'development') {
            flush_rewrite_rules();
        }

        add_action('admin_init', [$this, 'initAllPages']);
        add_action('init', [$this, 'accPagesTemplate']);
        add_filter('archive_template', [$this, 'customArchiveTemplate']);
        add_action('init', [$this, 'accRedirects']);
    }

    /**
     * Get ACC page ID.
     *
     * @return int
     */
    public function getAccPageId()
    {
        if ($this->acc_page_id_cache === null) {
            $this->acc_page_id_cache = WACC()->getOptionPageId('acc_page_dashboard', 0);
        }

        // Still null or empty?
        if (empty($this->acc_page_id_cache)) {
            // Check if we have a page with slug 'dashboard'
            $this->acc_page_id_cache = $this->getPageIdBySlug('dashboard');
        }

        return $this->acc_page_id_cache;
    }

    /**
     * Get ACC page Slug.
     *
     * @return string
     */
    public function getAccSlug()
    {
        if ($this->acc_slug_cache === null) {
            $acc_page_id = $this->getAccPageId();
            $this->acc_slug_cache = get_post($acc_page_id)->post_name;
        }

        return $this->acc_slug_cache;
    }

    /**
     * Create page for ACC
     * Index, Edit Profile, Events, Event Single, etc.
     *
     * @param string $slug
     * @param string $name
     *
     * @return mixed    ID of created page or false
     */
    public function createPage($slug, $name)
    {
        // Let's ensure our setting option doesn't have a page defined yet
        $page_id = WACC()->getOptionPageId('acc_page_' . $slug, 0);

        if ($page_id) {
            return $page_id;
        }

        $page_id = $this->getPageIdBySlug($slug);

        if ($page_id) {
            update_field('acc_page_' . $slug, $page_id, 'option');

            return $page_id;
        }

        // Create requested page as a child of ACC index page
        $page_id = wp_insert_post(
            [
                'post_type'      => $this->acc_post_type,
                'post_author'    => wp_get_current_user()->ID,
                'post_title'     => $name,
                'post_name'      => $slug,
                'post_status'    => 'publish',
                'comment_status' => 'closed',
                'post_content'   => '',
            ]
        );

        if ($page_id) {
            // Save ACF option field
            update_field('acc_page_' . $slug, $page_id, 'option');

            return $page_id;
        }

        return false;
    }

    /**
     * Get ACC page ID by slug.
     *
     * @param string $slug
     *
     * @return int|bool Page ID or false if not found
     */
    public function getPageIdBySlug($slug)
    {
        global $wpdb;

        $page_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND post_status = 'publish'",
                $slug,
                $this->acc_post_type
            )
        );

        // If page not found or is not a number, return false
        if (empty($page_id) || !is_numeric($page_id)) {
            return false;
        }

        return $page_id;
    }

    /**
     * Create all ACC pages
     * Run only once.
     *
     * @return void
     */
    public function initAllPages()
    {
        // Check if we've already created the main page
        if (get_option('wicket_acc_created_dashboard_page')) {
            return;
        }

        // Filter acc_pages_map against acc_pages_map_auto_create. Remove any page that doesn't exist in acc_pages_map_auto_create
        $pagesToCreate = array_intersect_key($this->acc_pages_map, array_flip($this->acc_pages_map_auto_create));

        // Create all other pages
        foreach ($pagesToCreate as $slug => $name) {
            $this->createPage($slug, $name);
        }

        $this->maybeCreateAccDashboardPage();
    }

    /**
     * Maybe create ACC page.
     *
     * return void
     */
    public function maybeCreateAccDashboardPage()
    {
        // Check if we've already created the main page
        if (get_option('wicket_acc_created_dashboard_page')) {
            return;
        }

        $set_slug = 'dashboard';
        $set_name = WACC()->getAccName();

        // Empty?
        if (empty($set_slug) || empty($set_name)) {
            return;
        }

        // Create page
        $this->createPage($set_slug, $set_name);

        // Save option to track that we've created the page
        update_option('wicket_acc_created_dashboard_page', true);
    }

    /**
     * Get Wicket ACC template.
     *
     * @param int $post_id Post ID
     *
     * @return string|false
     */
    private function getWicketAccTemplate($post_id = null)
    {
        $user_template = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/page-acc.php';
        $plugin_template = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/page-acc.php';

        if ($post_id && $this->isOrgmanagementPage($post_id)) {
            $acc_orgman_page = $this->orgmanPageRequested($post_id);

            $user_template = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/org-management/acc-orgman-' . $acc_orgman_page . '.php';
        }

        if (file_exists($user_template)) {
            return $user_template;
        } elseif (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return false;
    }

    /**
     * Checks if we are on the Organization Management page.
     *
     * Note: This function checks the existence of the custom fields for Organization Management
     *       pages, which are set in the Wicket ACC settings.
     *
     * @param int $post_id Post ID
     *
     * @return string|false
     */
    private function isOrgmanagementPage($post_id)
    {
        $orgManagementIndex = WACC()->getOptionPageId('acc_page_orgman-index', 0);
        $orgManagementProfile = WACC()->getOptionPageId('acc_page_orgman-profile', 0);
        $orgManagementMembers = WACC()->getOptionPageId('acc_page_orgman-members', 0);
        $orgManagementRoster = WACC()->getOptionPageId('acc_page_orgman-roster', 0);

        switch ($post_id) {
            case $orgManagementIndex:
                return 'index';
            case $orgManagementProfile:
                return 'profile';
            case $orgManagementRoster:
                return 'roster';
            case $orgManagementMembers:
                return 'members';
        }

        // Filterable flag to allow devs to override the template. Default to false.
        $is_acc_template = apply_filters('wicket/acc/orgman/is_orgmanagement_page', false, $post_id);

        if ($is_acc_template) {
            return $is_acc_template;
        }

        return false;
    }

    /**
     * Returns the "slug" of the Organization Management page requested, by is_orgmanagement_page() method.
     *
     * @param int $post_id Post ID
     *
     * @return bool
     */
    private function orgmanPageRequested($post_id)
    {
        if (!$post_id) {
            return false;
        }

        return $this->isOrgmanagementPage($post_id);
    }

    /**
     * Make all ACC CPT my-account pages, always load the template from get_wicket_acc_template() method.
     *
     * @return void
     */
    public function accPagesTemplate()
    {
        if (is_admin()) {
            return;
        }

        add_filter('single_template', function ($single_template) {
            global $post;

            if (is_admin()) {
                return $single_template;
            }

            if ($post->post_type == $this->acc_post_type) {
                // Check if user selected a custom template
                $custom_template = get_page_template_slug($post->ID);

                if (!$custom_template) {
                    // Only override default template if no custom template was selected
                    $template = $this->getWicketAccTemplate($post->ID);

                    if ($template) {
                        return $template;
                    }
                }
            }

            return $single_template;
        });
    }

    /**
     * Custom archive template for my-account CPT.
     *
     * NOTE: we used to redirect users earlier, at template_redirect. But, WP complains on some server configurations, that you can't access is_post_type_archive function before the main query is run. So, to avoid issues with those sites, we're using a custom archive template and redirecting users over there.
     *
     * @param string $template
     *
     * @return string
     */
    public function customArchiveTemplate($template)
    {
        if (is_admin()) {
            return $template;
        }

        if (is_post_type_archive($this->acc_post_type)) {
            $fixed_template = WICKET_ACC_PATH . 'includes/archive-acc.php';

            if (file_exists($fixed_template)) {
                return $fixed_template;
            }
        }

        return $template;
    }

    /**
     * Perform redirect with fallback for when headers are already sent.
     *
     * @param string $url Target URL
     * @return void
     */
    private function performRedirect(string $url): void
    {
        if (headers_sent()) {
            echo '<meta http-equiv="refresh" content="0;url=' . esc_url($url) . '" />';
            echo '<script>window.location.href="' . esc_url($url) . '";</script>';
        } else {
            wp_safe_redirect($url);
        }
        exit;
    }

    /**
     * Redirects for ACC
     * 1. /wc-account/ index only to /my-account/dashboard/
     * 2. Old acc slugs (account-centre) to new slugs (my-account)
     * 3. WooCommerce critical endpoints out from ACC.
     */
    public function accRedirects()
    {
        if (is_admin()) {
            return;
        }

        $current_lang = WACC()->Language->getCurrentLanguage();
        $acc_dashboard_id = (int) $this->getAccPageId();
        $acc_dashboard_url = get_permalink($acc_dashboard_id);
        $acc_dashboard_slug = get_post($acc_dashboard_id)->post_name;
        $acc_myaccount_slug = get_query_var('term');

        // WPML compatibility
        if (defined('ICL_SITEPRESS_VERSION')) {
            $acc_dashboard_id_translation = apply_filters('wpml_object_id', $acc_dashboard_id, 'post', true, $current_lang);
            $acc_dashboard_url_translation = get_permalink($acc_dashboard_id_translation);
            $acc_myaccount_slug_translation = apply_filters('wpml_get_translated_slug', $this->acc_post_type, $this->acc_post_type, $current_lang, 'post');
        }

        if (WACC()->isWooCommerceActive()) {
            $wc_page_id = wc_get_page_id('myaccount');
            $wc_page_slug = get_post($wc_page_id)->post_name;

            if ($current_lang !== 'en') {
                $wc_page_slug = $this->acc_wc_index_slugs[$current_lang];
            }

            if (!empty($acc_dashboard_slug)) {
                $acc_dashboard_slug = $wc_page_slug;
            }
        }

        $server_request_uri = $_SERVER['REQUEST_URI'];

        // Combined redirect logic for ACC index pages
        if ($server_request_uri === '/' . $acc_dashboard_slug . '/') {
            $this->performRedirect($acc_dashboard_url);
        } elseif (defined('ICL_SITEPRESS_VERSION') &&
                  $current_lang !== 'en' &&
                  isset($acc_myaccount_slug_translation) &&
                  $server_request_uri === '/' . $current_lang . '/' . $acc_myaccount_slug_translation . '/') {
            $this->performRedirect($acc_dashboard_url_translation);
        }

        // Redirect old ACC slugs
        $acc_old_slugs = [
            '/account-centre',
            '/account-center',
        ];

        if (is_array($acc_old_slugs) && !empty($acc_old_slugs)) {
            foreach ($acc_old_slugs as $old_slug) {
                if (str_contains($server_request_uri, $old_slug)) {
                    $this->performRedirect($acc_dashboard_url);
                }
            }
        }

        // Redirect (some) WC endpoints (my-account endpoints are for us)
        if (is_array($this->acc_prefer_wc_endpoints) && !empty($this->acc_prefer_wc_endpoints)) {
            $current_url = home_url(add_query_arg(null, null));
            $current_url = rtrim($current_url, '/');
            $wc_endpoint = basename($current_url);

            if ($current_lang !== 'en') {
                foreach ($this->acc_wc_endpoints as $endpoint_key => $translations) {
                    if (isset($translations[$current_lang]) && $translations[$current_lang] === $wc_endpoint) {
                        $wc_endpoint = $translations['en'];
                        break;
                    }
                }
            }

            if (in_array($wc_endpoint, $this->acc_prefer_wc_endpoints)) {
                if (!str_contains($server_request_uri, $wc_page_slug)) {
                    $wc_endpoint_url = home_url(wc_get_endpoint_url($wc_endpoint, '', $wc_page_slug));
                    $this->performRedirect($wc_endpoint_url);
                }
            }
        }
    }
}
