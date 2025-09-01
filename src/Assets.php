<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Assets Class
 * Handles enqueuing and printing assets.
 */
class Assets extends WicketAcc
{
    private $wicketTheme;
    private $wicketPreferColorScheme;

    /**
     * Assets constructor.
     *
     * Adds actions to enqueue admin and frontend assets.
     */
    public function __construct()
    {

        $this->wicketTheme = WACC()->Settings()->getWicketCssTheme();
        $this->wicketPreferColorScheme = WACC()->Settings()->getWicketCssPreferColorScheme();

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    /**
     * Get the latest modification time from an array of files.
     *
     * @param array $files Array of file paths.
     * @return int|string The latest modification time or WICKET_ACC_VERSION as a fallback.
     */
    private function get_latest_modification_time(array $files, string $transient_key)
    {
        // In development environments, bypass the cache to ensure changes are reflected immediately.
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $timestamps = [];
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $timestamps[] = filemtime($file);
                }
            }

            return !empty($timestamps) ? max($timestamps) : WICKET_ACC_VERSION;
        }

        // Try to get the cached version from the transient.
        $cached_version = get_transient($transient_key);
        if (false !== $cached_version) {
            return $cached_version;
        }

        // If the transient is not set, calculate the latest modification time.
        $timestamps = [];
        foreach ($files as $file) {
            if (file_exists($file)) {
                $timestamps[] = filemtime($file);
            }
        }

        $latest_version = !empty($timestamps) ? max($timestamps) : WICKET_ACC_VERSION;

        // Cache the new version in a transient for 1 hour.
        set_transient($transient_key, $latest_version, HOUR_IN_SECONDS);

        return $latest_version;
    }

    /**
     * Enqueue admin assets (CSS & JS).
     *
     * @return void
     */
    public function enqueue_admin_assets()
    {
        $admin_css_files = [
            WICKET_ACC_PATH . 'assets/css/wicket-acc-admin-main.css',
        ];
        $admin_js_path = WICKET_ACC_PATH . 'assets/js/wicket-acc-admin-main.js';

        wp_enqueue_style('wicket-acc-admin-styles', WICKET_ACC_URL . 'assets/css/wicket-acc-admin-main.css', [], $this->get_latest_modification_time($admin_css_files, 'wicket_acc_admin_css_version'));
        wp_enqueue_script('wicket-acc-admin-scripts', WICKET_ACC_URL . 'assets/js/wicket-acc-admin-main.js', [], file_exists($admin_js_path) ? filemtime($admin_js_path) : WICKET_ACC_VERSION, true);

        // Localize script with settings
        $localized_settings = [
            'wicket_theme' => $this->wicketTheme,
            'wicket_prefer_color_scheme' => $this->wicketPreferColorScheme,
        ];
        wp_localize_script('wicket-acc-admin-scripts', 'wicketAccSettings', $localized_settings);
    }

    /**
     * Enqueue frontend assets (CSS & JS).
     *
     * Enqueues main frontend CSS & JS files, legacy JS file, and WooCommerce assets.
     *
     * @return void
     */
    public function enqueue_frontend_assets()
    {
        global $post;

        // Empty $post?
        if (empty($post)) {
            // Get post data
            $post = get_queried_object();
        }

        // Check if the current theme name starts with 'wicket'
        // If so, we don't include picocss to avoid conflicts
        $theme = wp_get_theme();
        $theme_name = $theme->get('Name');
        $is_wicket_theme = str_starts_with(strtolower($theme_name), 'wicket');
        $should_enqueue_wicket_styles = apply_filters('wicket/acc/should_enqueue_wicket_styles', true);

        if (!$is_wicket_theme && $should_enqueue_wicket_styles) {
            wp_enqueue_style('wicket-pico-fluid', WICKET_ACC_URL . 'assets/css/vanilla/_wicket-pico-fluid.classless.light.zinc.css', [], WICKET_ACC_VERSION);
            wp_enqueue_style('wicket-vanilla', WICKET_ACC_URL . 'assets/css/vanilla/wicket-vanilla.css', ['wicket-pico-fluid'], WICKET_ACC_VERSION);
        }

        // Determine if assets should be enqueued based on context.
        // Assets are enqueued if:
        // 1. The current post type IS 'my-account', OR
        // 2. The current page IS one of 'my-account', 'mon-compte', 'mi-cuenta', OR
        // 3. WooCommerce IS active AND it's a relevant WooCommerce page (shop, account, endpoint).
        // If NONE of these conditions are met, we return early.

        $current_post_type = get_post_type(); // Relies on global $post. Returns false if no global $post.

        $is_relevant_page_slug = is_page([
            'my-account',
            'mon-compte',
            'mi-cuenta',
        ]);

        $is_relevant_woocommerce_context = WACC()->isWooCommerceActive() && (
            is_woocommerce() ||
            is_account_page() ||
            is_wc_endpoint_url()
        );

        // If it's NOT the 'my-account' post type, AND
        // it's NOT one of the specified page slugs, AND
        // it's NOT a relevant WooCommerce context,
        // THEN return early and do not enqueue assets.
        if (
            ('my-account' !== $current_post_type) &&
            (!$is_relevant_page_slug) &&
            (!$is_relevant_woocommerce_context)
        ) {
            return;
        }

        $frontend_css_files = [
            WICKET_ACC_PATH . 'assets/css/wicket-acc-main.css',
            WICKET_ACC_PATH . 'assets/css/_wicket-acc-variables.css',
            WICKET_ACC_PATH . 'assets/css/_wicket-acc-global.css',
            WICKET_ACC_PATH . 'assets/css/_wicket-acc-animations.css',
            WICKET_ACC_PATH . 'assets/css/_wicket-acc-blocks-global.css',
            WICKET_ACC_PATH . 'assets/css/_wicket-acc-legacy.css',
            WICKET_ACC_PATH . 'assets/css/_wicket-acc-pages.css',
            WICKET_ACC_PATH . 'assets/css/_wicket-acc-woocommerce.css',
            WICKET_ACC_PATH . 'assets/css/_wicket-acc-navigation.css',
            WICKET_ACC_PATH . 'assets/css/_wicket-acc-org-management.css',
        ];

        $frontend_js_path = WICKET_ACC_PATH . 'assets/js/wicket-acc-main.js';
        $legacy_js_path = WICKET_ACC_PATH . 'assets/js/wicket-acc-legacy.js';

        wp_enqueue_style('wicket-acc-frontend-styles', WICKET_ACC_URL . 'assets/css/wicket-acc-main.css', [], $this->get_latest_modification_time($frontend_css_files, 'wicket_acc_frontend_css_version'));
        wp_enqueue_script('wicket-acc-frontend-scripts', WICKET_ACC_URL . 'assets/js/wicket-acc-main.js', [], file_exists($frontend_js_path) ? filemtime($frontend_js_path) : WICKET_ACC_VERSION, true);
        wp_enqueue_script('wicket-acc-frontend-legacy-scripts', WICKET_ACC_URL . 'assets/js/wicket-acc-legacy.js', [], file_exists($legacy_js_path) ? filemtime($legacy_js_path) : WICKET_ACC_VERSION, true);

        // Localize script with settings
        $localized_settings = [
            'wicket_theme' => $this->wicketTheme,
            'wicket_prefer_color_scheme' => $this->wicketPreferColorScheme,
        ];
        wp_localize_script('wicket-acc-frontend-scripts', 'wicketAccSettings', $localized_settings);
    }
}
