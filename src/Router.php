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

        add_action('init', [$this, 'accPagesTemplate']);
        add_filter('archive_template', [$this, 'customArchiveTemplate']);
        add_action('init', [$this, 'accRedirects']);
    }

    /**
     * Get ACC page ID.
     *
     * @return int
     */

    /**
     * Get Wicket ACC template.
     *
     * @param int $post_id Post ID
     *
     * @return string|false
     */
    private function getWicketAccTemplate($post_id = null)
    {
        // Check if this is a WooCommerce endpoint
        if (WACC()->isWooCommerceActive()) {
            // Get the current endpoint
            $current_endpoint = $this->getCurrentWooCommerceEndpoint();

            if ($current_endpoint) {
                // Use the WooCommerce template for WooCommerce endpoints
                $user_template = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/page-wc.php';
                $plugin_template = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/page-wc.php';

                if (file_exists($user_template)) {
                    return $user_template;
                } elseif (file_exists($plugin_template)) {
                    return $plugin_template;
                }
            }
        }

        // Default to the regular ACC template
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
        $orgManagementIndex = $this->getPageIdBySlug('org-management');
        $orgManagementProfile = $this->getPageIdBySlug('org-management-profile');
        $orgManagementMembers = $this->getPageIdBySlug('org-management-members');
        $orgManagementRoster = $this->getPageIdBySlug('org-management-roster');

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

            if ($post && $post->post_type == $this->acc_post_type) {
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

        // Also hook into template_include to handle WooCommerce endpoints
        add_filter('template_include', function ($template) {
            // Only for ACC WC context (e.g. /my-account/* URLs)
            if (!WACC()->isWooCommerceActive() || !WACC()->WooCommerce()->isWooCommerceEndpoint()) {
                return $template;
            }

            // Use the same template selection logic as for CPT posts
            $acc_template = $this->getWicketAccTemplate();
            if ($acc_template) {
                return $acc_template;
            }

            return $template;
        }, 100);
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

        $dashboard_url = $this->get_account_page_url('dashboard');
        $server_request_uri = $_SERVER['REQUEST_URI'];

        // Redirect old ACC slugs to the main dashboard URL
        $acc_old_slugs = [
            '/account-centre',
            '/account-center',
            '/wc-account',
            '/wc-compte',
            '/wc-cuenta',
        ];

        foreach ($acc_old_slugs as $old_slug) {
            if (str_starts_with($server_request_uri, $old_slug)) {
                $this->performRedirect($dashboard_url);

                return; // Exit after first redirect
            }
        }

        // Redirect archive page of 'my-account' CPT to the dashboard
        if (is_post_type_archive($this->acc_post_type)) {
            $this->performRedirect($dashboard_url);

            return;
        }
    }

    /**
     * Get the current WooCommerce endpoint if we're on a WooCommerce endpoint page.
     *
     * @return string|false The endpoint key or false if not on a WooCommerce endpoint
     */
    private function getCurrentWooCommerceEndpoint()
    {
        // Use the WooCommerce class method to get the current endpoint key
        $endpoint_key = WACC()->WooCommerce()->getCurrentEndpointKey();

        // Return the endpoint key if found, false otherwise
        return $endpoint_key ?: false;
    }
}
