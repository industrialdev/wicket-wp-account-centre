<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * WooCommerce Integration Class
 * Handles WooCommerce functionality when available.
 *
 * Special URLs to take care of:
 * https://localhost/my-account/orders/
 * https://localhost/fr/mon-compte/orders/
 * https://localhost/es/mi-cuenta/orders/
 *
 * https://localhost/my-account/view-order/40141/
 * https://localhost/fr/mon-compte/view-order/40141/
 * https://localhost/es/mi-cuenta/view-order/40141/
 *
 * https://localhost/my-account/subscriptions/
 * https://localhost/fr/mon-compte/subscriptions/
 * https://localhost/es/mi-cuenta/subscriptions/
 *
 * https://localhost/my-account/view-subscription/40142/
 * https://localhost/fr/mon-compte/view-subscription/40142/
 * https://localhost/es/mi-cuenta/view-subscription/40142/
 *
 * https://localhost/my-account/payment-methods/
 * https://localhost/fr/mon-compte/payment-methods/
 * https://localhost/es/mi-cuenta/payment-methods/
 *
 * https://localhost/my-account/add-payment-method/
 * https://localhost/fr/mon-compte/add-payment-method/
 * https://localhost/es/mi-cuenta/add-payment-method/
 *
 * https://localhost/my-account/delete-payment-method/56/?_wpnonce=bf3ee98a23
 * https://localhost/fr/mon-compte/delete-payment-method/56/?_wpnonce=bf3ee98a23
 * https://localhost/es/mi-cuenta/delete-payment-method/56/?_wpnonce=bf3ee98a23
 */
class WooCommerce extends WicketAcc
{
    /**
     * Storage for temporarily hidden WC query vars.
     */
    private $stored_wc_query_vars = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Register early, independent of WooCommerce, so redirects and query vars are handled before canonical
        add_filter('redirect_canonical', [$this, 'bypass_canonical_for_acc_endpoints'], 999, 2);
        add_filter('wp_redirect', [$this, 'prevent_redirect_for_acc_endpoints'], 999, 2);
        add_action('template_redirect', [$this, 'disable_canonical_for_acc_endpoints'], 1);
        add_action('parse_request', [$this, 'inject_acc_endpoint_query_vars']);
        add_action('init', [$this, 'register_acc_wc_endpoints']);
        add_action('template_redirect', [$this, 'maybe_clear_404_for_acc_endpoints']);
        // Defer WC-specific integrations until WooCommerce initializes
        add_action('woocommerce_init', [$this, 'wooInitialize']);
    }

    /**
     * Normalize Woo endpoint URLs by collapsing duplicated adjacent segments for
     * known bases and endpoint slugs (handles cases like /my-account/my-account/orders/2/).
     *
     * @param string $url
     * @param string $endpoint
     * @param mixed  $value
     * @param string $permalink
     * @return string
     */
    public function normalize_endpoint_url($url, $endpoint, $value, $permalink)
    {
        $parsed = wp_parse_url($url);
        $path = $parsed['path'] ?? '';
        if ($path === '') {
            return $url;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if (empty($segments)) {
            return $url;
        }

        // Build allowlist of segments we can safely de-duplicate
        $allowed = [];
        // Bases (localized)
        foreach ((array) $this->acc_wc_index_slugs as $b) {
            $allowed[$b] = true;
        }
        // Endpoint slugs (all localized variants)
        foreach ((array) $this->acc_wc_endpoints as $slugset) {
            foreach ((array) $slugset as $slug) {
                $allowed[$slug] = true;
            }
        }

        $normalized = [];
        $prev = null;
        foreach ($segments as $seg) {
            if ($prev !== null && $seg === $prev && isset($allowed[$seg])) {
                // skip duplicate allowed segment
                continue;
            }
            $normalized[] = $seg;
            $prev = $seg;
        }

        $new_path = '/' . implode('/', $normalized) . '/';
        $new_url = home_url($new_path);

        if (!empty($parsed['query'])) {
            $new_url .= '?' . $parsed['query'];
        }
        if (!empty($parsed['fragment'])) {
            $new_url .= '#' . $parsed['fragment'];
        }

        return $new_url;
    }

    /**
     * Build a localized endpoint URL based on current request language directory usage.
     * Emits a clean path like /<lang?>/<base>/<endpoint>/<value?>.
     *
     * @param string $endpoint Canonical endpoint key (e.g., 'payment-methods').
     * @param mixed  $value    Optional value.
     * @return string          Fully qualified localized URL.
     */
    private function build_localized_endpoint_url(string $endpoint, $value = ''): string
    {
        // Determine if current request uses a language directory prefix and capture it
        $current_path = isset($_SERVER['REQUEST_URI']) ? (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
        $request_uses_lang_dir = false;
        $lang_from_path = '';
        if ($current_path !== '') {
            $current_parts = array_values(array_filter(explode('/', trim($current_path, '/'))));
            $first_seg = $current_parts[0] ?? '';
            if ($first_seg !== '' && preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $first_seg)) {
                $request_uses_lang_dir = true;
                $lang_from_path = $first_seg;
            }
        }

        // Prefer language from path when present; fallback to app language
        $lang = $lang_from_path !== '' ? $lang_from_path : WACC()->getLanguage();

        // Choose base slug by that language; fallback to EN
        $base = $this->acc_wc_index_slugs[$lang] ?? ($this->acc_wc_index_slugs['en'] ?? 'my-account');

        // For payment-methods, enforce canonical slug across languages
        $localized_endpoint = ($endpoint === 'payment-methods')
            ? 'payment-methods'
            : ($this->acc_wc_endpoints[$endpoint][$lang] ?? $endpoint);

        $segments = [];
        if ($request_uses_lang_dir && $lang !== '') {
            $segments[] = $lang;
        }
        $segments[] = $base;
        $segments[] = $localized_endpoint;
        if ((string) $value !== '') {
            $segments[] = rawurlencode((string) $value);
        }

        $path = '/' . implode('/', $segments) . '/';

        return home_url($path);
    }

    /**
     * Localize WooCommerce endpoint URLs to current language and translated base/slug.
     * Ensures buttons like "View" on orders/subscriptions use /fr/mon-compte/voir-... when multilingual is enabled.
     *
     * @param string $url       The generated endpoint URL.
     * @param string $endpoint  The endpoint key (canonical), e.g. 'view-order'.
     * @param mixed  $value     Optional value (e.g., order ID).
     * @param string $permalink Base permalink passed in by Woo.
     * @return string           Localized URL when applicable.
     */
    public function localize_endpoint_url($url, $endpoint, $value, $permalink)
    {
        // Only adjust when multilingual is active and when we know about this endpoint
        if (!WACC()->isMultiLangEnabled() || !isset($this->acc_wc_endpoints[$endpoint])) {
            return $url;
        }

        $lang = WACC()->getLanguage();

        // Determine localized base and endpoint slug
        $base = $this->acc_wc_index_slugs[$lang] ?? ($this->acc_wc_index_slugs['en'] ?? 'my-account');
        $localized_endpoint = $this->acc_wc_endpoints[$endpoint][$lang] ?? $endpoint;

        // Respect current request's language directory usage (default language may not use /en/)
        $current_path = isset($_SERVER['REQUEST_URI']) ? (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
        $request_uses_lang_dir = false;
        if ($current_path !== '') {
            $current_parts = array_values(array_filter(explode('/', trim($current_path, '/'))));
            $first_seg = $current_parts[0] ?? '';
            $request_uses_lang_dir = ($first_seg !== '' && preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $first_seg));
        }

        /* Build a clean, non-nested endpoint path: /<lang?>/<base>/<endpoint>/<value?> */
        $new_segments = [];
        if ($request_uses_lang_dir) {
            $new_segments[] = $lang;
        }
        $new_segments[] = $base;
        $new_segments[] = $localized_endpoint;
        if ((string) $value !== '') {
            $new_segments[] = rawurlencode((string) $value);
        }

        $new_path = '/' . implode('/', $new_segments) . '/';
        // Preserve query and fragment from original URL
        $parsed = wp_parse_url($url);
        $new_url = home_url($new_path);
        if (!empty($parsed['query'])) {
            $new_url .= '?' . $parsed['query'];
        }
        if (!empty($parsed['fragment'])) {
            $new_url .= '#' . $parsed['fragment'];
        }

        return $new_url;
    }

    /**
     * Split current request path into segments and drop an initial language segment if present (e.g., fr or fr-CA).
     *
     * @return array
     */
    private function get_language_aware_segments(): array
    {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($request_uri, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', trim((string) $path, '/'))));
        // Only strip language directory when multilingual plugins are active
        if (WACC()->isMultiLangEnabled() && !empty($segments) && preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $segments[0])) {
            array_shift($segments);
        }

        return $segments;
    }

    /**
     * Given a localized endpoint slug, return its canonical endpoint key (e.g., 'voir-commande' -> 'view-order').
     * Returns empty string if not found.
     */
    private function endpoint_key_from_localized_slug(string $slug): string
    {
        foreach ($this->acc_wc_endpoints as $endpoint_key => $localizedSlugs) {
            if (in_array($slug, (array) $localizedSlugs, true)) {
                return $endpoint_key;
            }
        }

        return '';
    }

    /**
     * Failsafe: prevent any redirect that would strip ACC endpoint arguments.
     *
     * @param string $location
     * @param int    $status
     * @return string|false
     */
    public function prevent_redirect_for_acc_endpoints($location, $status)
    {
        $segments = $this->get_language_aware_segments();

        // Need /base/endpoint/arg
        if (count($segments) < 3) {
            return $location;
        }

        $bases = array_values($this->acc_wc_index_slugs);
        $base = $segments[0] ?? '';
        if (!in_array($base, $bases, true)) {
            return $location;
        }

        $second_last = $segments[count($segments) - 2] ?? '';
        $last = end($segments) ?: '';

        $endpoint_key = $this->endpoint_key_from_localized_slug($second_last) ?: $second_last;
        if ($second_last && array_key_exists($endpoint_key, $this->acc_wc_endpoints) && $last !== '') {
            return false;
        }

        return $location;
    }

    /**
     * Remove WordPress core canonical redirect for ACC endpoint URLs with args
     * Runs very early on template_redirect before core canonical executes (priority 10).
     */
    public function disable_canonical_for_acc_endpoints(): void
    {
        $segments = $this->get_language_aware_segments();

        if (count($segments) < 3) {
            return;
        }

        $bases = array_values($this->acc_wc_index_slugs);
        $base = $segments[0] ?? '';
        if (!in_array($base, $bases, true)) {
            return;
        }

        $second_last = $segments[count($segments) - 2] ?? '';
        $last = end($segments) ?: '';

        $endpoint_key = $this->endpoint_key_from_localized_slug($second_last) ?: $second_last;
        if ($second_last && array_key_exists($endpoint_key, $this->acc_wc_endpoints) && $last !== '') {
            // Stop core canonical from running at all
            remove_action('template_redirect', 'redirect_canonical');
        }
    }

    /**
     * Initialize hooks and filters for WooCommerce integration.
     */
    public function wooInitialize()
    {
        if (is_admin()) {
            return;
        }

        // Override templates
        add_filter('woocommerce_locate_template', [$this, 'override_woocommerce_template'], 10, 3);

        // Remove order again button
        remove_action('woocommerce_order_details_after_order_table', 'woocommerce_order_again_button');

        // Add global header banner as ACC pages
        //add_action('wicket_header_end', [$this, 'wc_add_acc_banner'], PHP_INT_MAX);

        // Remove tax totals
        add_filter('woocommerce_cart_tax_totals', [$this, 'remove_cart_tax_totals'], 10, 2);
        add_filter('woocommerce_calculated_total', [$this, 'exclude_tax_cart_total'], 10, 2);
        add_filter('woocommerce_subscriptions_calculated_total', [$this, 'exclude_tax_cart_total']);

        // Localize endpoint URLs (base + slug) for WPML languages
        add_filter('woocommerce_get_endpoint_url', [$this, 'localize_endpoint_url'], 9, 4);

        // Normalize duplicated segments like /my-account/my-account/ or /orders/orders/
        add_filter('woocommerce_get_endpoint_url', [$this, 'normalize_endpoint_url'], 10, 4);

        // Keep orders pagination fix after normalization
        add_filter('woocommerce_get_endpoint_url', [$this, 'fix_orders_pagination_url'], 11, 4);

        // Ensure redirects/links to payment-methods land on ACC base in ACC context
        add_filter('woocommerce_get_endpoint_url', [$this, 'fix_payment_methods_endpoint_base'], 12, 4);

        // Add WooCommerce endpoints to account pages
        add_filter('wicket_acc_menu_items', [$this, 'add_wc_menu_items']);

        // Make WooCommerce think ACC CPT endpoints are inside Woo.
        add_filter('woocommerce_is_account_page', [$this, 'enable_wc_account_page_detection'], 10, 1);
        add_filter('wc_get_page_id', [$this, 'enable_wc_page_detection'], 10, 2);
        add_filter('woocommerce_is_endpoint_url', [$this, 'enable_wc_endpoint_url_detection'], 10, 2);
        // Also ensure endpoint query vars are present when applicable
        add_action('wp', [$this, 'maybe_flag_wc_endpoint_query']);

        // Handle payment method actions early to ensure reliable redirects
        add_action('template_redirect', [$this, 'handle_payment_method_actions_early'], 1);
        // Also try earlier hooks in case template_redirect isn't firing
        add_action('wp', [$this, 'handle_payment_method_actions_early'], 1);
        add_action('parse_request', [$this, 'handle_payment_method_actions_early'], 1);

        // Minimal shim: some gateways rely on this conditional specifically
        add_filter('woocommerce_is_add_payment_method_page', [$this, 'maybe_force_is_add_payment_method_page']);

        // Trick Woo conditionals that rely on is_page( wc_get_page_id('myaccount') )
        // by mapping the myaccount page id to the current ACC page id in ACC context.
        add_filter('pre_option_woocommerce_myaccount_page_id', [$this, 'map_myaccount_page_id_to_acc']);

        // TEMP DEBUG: Log whether Stripe UPE scripts are enqueued on add-payment-method pages
        add_action('wp_enqueue_scripts', [$this, 'debug_stripe_enqueue_state'], 99);

        // Force any late redirects after payment method actions to localized payment methods
        add_filter('wp_redirect', [$this, 'force_payment_methods_redirect_in_acc'], 999, 2);
    }

    /**
     * Early handler for payment method actions to ensure deletion/default-set works
     * and always redirects back to payment methods, even if headers were already sent.
     *
     * Runs before WooCommerce's own handler to avoid blank page if redirection fails.
     *
     * @return void
     */
    public function handle_payment_method_actions_early(): void
    {
        if (!$this->is_acc_wc_context()) {
            return;
        }

        global $wp;

        $delete_token_id = isset($wp->query_vars['delete-payment-method']) ? absint($wp->query_vars['delete-payment-method']) : 0;
        $set_default_id = isset($wp->query_vars['set-default-payment-method']) ? absint($wp->query_vars['set-default-payment-method']) : 0;

        if (!$delete_token_id && !$set_default_id) {
            return;
        }

        if (!function_exists('wc_add_notice')) {
            return;
        }

        // Process delete action
        if ($delete_token_id) {
            $token = \WC_Payment_Tokens::get($delete_token_id);
            $nonce_ok = isset($_REQUEST['_wpnonce']) && wp_verify_nonce(wp_unslash($_REQUEST['_wpnonce']), 'delete-payment-method-' . $delete_token_id);

            if (is_null($token) || get_current_user_id() !== $token->get_user_id() || !$nonce_ok) {
                wc_add_notice(__('Invalid payment method.', 'woocommerce'), 'error');
            } else {
                \WC_Payment_Tokens::delete($delete_token_id);
                wc_add_notice(__('Payment method deleted.', 'woocommerce'));
            }

            $this->safe_redirect_to_payment_methods();
        }

        // Process set-default action
        if ($set_default_id) {
            $token = \WC_Payment_Tokens::get($set_default_id);
            $nonce_ok = isset($_REQUEST['_wpnonce']) && wp_verify_nonce(wp_unslash($_REQUEST['_wpnonce']), 'set-default-payment-method-' . $set_default_id);

            if (is_null($token) || get_current_user_id() !== $token->get_user_id() || !$nonce_ok) {
                wc_add_notice(__('Invalid payment method.', 'woocommerce'), 'error');
            } else {
                \WC_Payment_Tokens::set_users_default($token->get_user_id(), intval($set_default_id));
                wc_add_notice(__('This payment method was successfully set as your default.', 'woocommerce'));
            }

            $this->safe_redirect_to_payment_methods();
        }
    }

    /**
     * Redirect to the account payment methods endpoint with a fallback when headers are already sent.
     *
     * @return void
     */
    private function safe_redirect_to_payment_methods(): void
    {
        // Use localized builder to ensure redirect matches current language (e.g., FR)
        $url = $this->build_localized_endpoint_url('payment-methods', '');
        if (!$url) {
            $url = wc_get_account_endpoint_url('payment-methods');
        }
        wc_nocache_headers();
        if (!headers_sent()) {
            // Send header redirect but also print HTML fallback in case the client blocks it
            wp_safe_redirect($url);
        }
        // Always print an HTML/JS fallback to guarantee navigation
        echo '<!doctype html><html><head><meta charset="utf-8">';
        echo '<meta http-equiv="refresh" content="0;url=' . esc_url($url) . '">';
        echo '</head><body>';
        echo '<script>window.location.replace(' . wp_json_encode($url) . ');</script>';
        echo '</body></html>';
        exit;
    }

    /**
     * As a safety net, if anything else attempts a redirect after payment method actions,
     * force it to the localized payment methods URL while in ACC WC context.
     *
     * @param string $location
     * @param int    $status
     * @return string
     */
    public function force_payment_methods_redirect_in_acc($location, $status)
    {
        if (is_admin()) {
            return;
        }

        if (!$this->is_acc_wc_context()) {
            return $location;
        }

        // Detect current request pertains to payment method actions
        $segments = $this->get_language_aware_segments();
        $count = count($segments);
        if ($count < 2) {
            return $location;
        }

        $last = $segments[$count - 1] ?? '';
        $second_last = $segments[$count - 2] ?? '';
        $last_key = $this->endpoint_key_from_localized_slug($last) ?: $last;
        $second_key = $this->endpoint_key_from_localized_slug($second_last) ?: $second_last;

        $action_endpoints = ['delete-payment-method', 'set-default-payment-method'];
        $is_action = in_array($last_key, $action_endpoints, true) || in_array($second_key, $action_endpoints, true);
        if (!$is_action) {
            return $location;
        }

        // Override to localized payment methods URL
        return $this->build_localized_endpoint_url('payment-methods');
    }

    /**
     * Override WooCommerce templates.
     *
     * @param string $template
     * @param string $template_name
     * @param string $template_path
     *
     * @return string
     */
    public function override_woocommerce_template($template, $template_name, $template_path)
    {

        if (is_admin()) {
            return $template;
        }

        // WC myaccount to ACC dashboard
        if ($template_name === 'myaccount/my-account.php') {
            $plugin_template = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/page-wc.php';
            $user_template = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/page-wc.php';

            if (file_exists($user_template)) {
                return $user_template;
            }

            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        // Determine the endpoint name from $template_name (ex: myaccount/payment-methods.php = payment-methods)
        $wc_endpoint = explode('/', $template_name);
        $wc_endpoint = end($wc_endpoint);
        $wc_endpoint = explode('.', $wc_endpoint);
        // grab the first element of the array
        $wc_endpoint = array_shift($wc_endpoint);

        // If we are seeing a WC endpoint from $acc_pages_map
        if (in_array($wc_endpoint, $this->acc_pages_map)) {
            // Only on WooCommerce pages
            if (!WACC()->is_account_page()) {
                return $template;
            }

            // Handle parameterized endpoints that require WooCommerce native rendering
            // Use all endpoint keys from the centralized definition
            $parameterized_endpoints = array_keys($this->acc_wc_endpoints);
            if (in_array($wc_endpoint, $parameterized_endpoints)) {
                // Get the endpoint value from query vars
                global $wp;
                $endpoint_value = '';
                if (isset($wp->query_vars[$wc_endpoint])) {
                    $endpoint_value = $wp->query_vars[$wc_endpoint];
                }

                // Call WooCommerce endpoint action hook with the endpoint value
                do_action("woocommerce_account_{$wc_endpoint}_endpoint", $endpoint_value);

                // Return empty template to prevent double rendering
                return WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/empty.php';
            }

            // For other endpoints, load the content of the post with slug $wc_endpoint from CPT my-account
            $acc_post_id = WACC()->getOptionPageId('acc_page_' . $wc_endpoint, 0);

            if ($acc_post_id) {
                // Get post content and display it
                $acc_post_content = get_post($acc_post_id)->post_content;
                echo $acc_post_content;
            }
        }

        return $template;
    }

    /**
     * Adds the global header banner to the WooCommerce my account navigation.
     *
     * Will only add the banner if the 'acc_global-headerbanner' option is enabled.
     *
     * @return void
     */
    public function wc_add_acc_banner()
    {
        // Use centralized helper (Carbon Fields preferred, ACF fallback)
        $acc_banner_enabled = WACC()->getOption('acc_global-headerbanner', false);

        if (!$acc_banner_enabled) {
            return;
        }

        // Only on WooCommerce myaccount pages
        if (!WACC()->is_account_page()) {
            return;
        }

        $acc_global_headerbanner_page_id = WACC()->getGlobalHeaderBannerPageId();
        $acc_global_banner_page = get_post($acc_global_headerbanner_page_id);

        // What happened here?
        if (empty($acc_global_banner_page)) {
            return;
        }

        // Banner content
        $acc_global_banner_content = '<div class="wicket-acc alignfull wp-block-wicket-banner">';
        $acc_global_banner_content .= apply_filters('the_content', $acc_global_banner_page->post_content);
        $acc_global_banner_content .= '</div>';

        echo $acc_global_banner_content;
    }

    /**
     * Removes the tax totals from the cart page.
     *
     * @param array $tax_totals An associative array of tax rates to totals.
     * @param object $instance The WC_Tax object.
     *
     * @return array Empty array.
     */
    public function remove_cart_tax_totals($tax_totals, $instance = null)
    {
        if (!is_cart()) {
            return $tax_totals;
        }

        $tax_totals = [];

        return $tax_totals;
    }

    /**
     * Removes the tax totals from the cart totals.
     *
     * @param int $total The total.
     * @param object $instance The WC_Tax object.
     *
     * @return int The total without tax.
     */
    public function exclude_tax_cart_total($total, $instance = null)
    {
        if (!is_cart()) {
            return $total;
        }

        $total = round(WC()->cart->cart_contents_total + WC()->cart->shipping_total + WC()->cart->fee_total, WC()->cart->dp);

        return $total;
    }

    /**
     * Fix pagination URLs for orders endpoint.
     *
     * @param string $url The URL.
     * @param string $endpoint The endpoint.
     * @param int $value The value.
     * @param string $permalink The permalink.
     *
     * @return string The fixed URL.
     */
    public function fix_orders_pagination_url($url, $endpoint, $value, $permalink)
    {
        // Only modify URLs for the orders endpoint with pagination
        if ($endpoint === 'orders' && is_numeric($value)) {
            // Check if the URL has the duplicate "orders" pattern
            if (strpos($url, '/orders/orders/') !== false) {
                // Fix the URL by replacing the duplicate pattern
                $url = str_replace('/orders/orders/', '/orders/', $url);
            }
        }

        return $url;
    }

    /**
     * Ensure wc_get_endpoint_url('payment-methods') uses the ACC base when in ACC context.
     */
    public function fix_payment_methods_endpoint_base($url, $endpoint, $value, $permalink)
    {
        if ($endpoint !== 'payment-methods' || !$this->is_acc_wc_context()) {
            return $url;
        }

        $segments = $this->get_language_aware_segments();
        $bases = array_values($this->acc_wc_index_slugs);
        $base = $bases[0] ?? 'my-account';
        if (!empty($segments) && in_array($segments[0], $bases, true)) {
            $base = $segments[0];
        }

        // Use canonical slug for payment methods across languages
        $path = '/' . $base . '/payment-methods/';

        return home_url($path);
    }

    /**
     * Add WooCommerce menu items to account menu.
     *
     * @param array $items Current menu items.
     * @return array Modified menu items.
     */
    public function add_wc_menu_items($items)
    {
        if (!WACC()->isWooCommerceActive()) {
            return $items;
        }

        // Add WooCommerce menu items
        $wc_items = [
            'orders' => [
                'title' => __('Orders', 'wicket-acc'),
                'url' => wc_get_account_endpoint_url('orders'),
            ],
            'downloads' => [
                'title' => __('Downloads', 'wicket-acc'),
                'url' => wc_get_account_endpoint_url('downloads'),
            ],
            'payment-methods' => [
                'title' => __('Payment Methods', 'wicket-acc'),
                'url' => wc_get_account_endpoint_url('payment-methods'),
            ],
        ];

        // Add subscriptions if WooCommerce Subscriptions is active
        if (class_exists('WC_Subscriptions')) {
            $wc_items['subscriptions'] = [
                'title' => __('Subscriptions', 'wicket-acc'),
                'url' => wc_get_account_endpoint_url('subscriptions'),
            ];
        }

        return array_merge($items, $wc_items);
    }

    /**
     * Prevent canonical redirect that strips endpoint args from ACC URLs, e.g.,
     * /my-account/view-order/40121/ -> /my-account/view-order/.
     *
     * @param string|false $redirect_url
     * @param string       $requested_url
     * @return string|false
     */
    public function bypass_canonical_for_acc_endpoints($redirect_url, $requested_url)
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return $redirect_url;
        }

        $raw_url = $requested_url ?: $_SERVER['REQUEST_URI'];
        $path = parse_url($raw_url, PHP_URL_PATH);
        if (!$path) {
            return $redirect_url;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if (count($segments) < 3) {
            return $redirect_url; // need at least base/endpoint/arg
        }

        // First segment must be one of our ACC WC index slugs (localized)
        $bases = array_values($this->acc_wc_index_slugs);
        $base = $segments[0] ?? '';
        if (!in_array($base, $bases, true)) {
            return $redirect_url;
        }

        $endpoint_keys = array_keys($this->acc_wc_endpoints);
        $second_last = $segments[count($segments) - 2];

        if (in_array($second_last, $endpoint_keys, true)) {
            // This is an ACC endpoint with an arg; do not canonicalize away the arg
            return false;
        }

        return $redirect_url;
    }

    /**
     * On parse_request, extract endpoint argument from URL segments and inject into WP query vars
     * so WooCommerce endpoint handlers receive the argument properly.
     *
     * @param WP $wp
     * @return void
     */
    public function inject_acc_endpoint_query_vars($wp): void
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $path_segments = $this->get_language_aware_segments();

        // Must start with our ACC WC base
        if (count($path_segments) < 2) {
            return;
        }
        $acc_base = $path_segments[0] ?? '';
        $bases = array_values($this->acc_wc_index_slugs);
        if (!in_array($acc_base, $bases, true)) {
            return;
        }

        // Handle nested endpoints like /my-account/payment-methods/delete-payment-method/58/
        // Scan through segments to find any endpoint with its argument
        for ($i = 1; $i < count($path_segments) - 1; $i++) {
            $maybe_endpoint = $path_segments[$i] ?? '';
            $next_segment = $path_segments[$i + 1] ?? '';

            $endpoint_key = $this->endpoint_key_from_localized_slug($maybe_endpoint) ?: $maybe_endpoint;

            if (array_key_exists($endpoint_key, $this->acc_wc_endpoints) && $next_segment !== '') {
                // This is an endpoint with an argument
                $value = is_numeric($next_segment) ? absint($next_segment) : sanitize_text_field(wp_unslash($next_segment));
                $wp->set_query_var($endpoint_key, $value);
                $wp->query_vars[$endpoint_key] = $value;
                break; // Found and set the endpoint, no need to continue
            }
        }

        // Fallback: handle simple endpoints without arguments (e.g., /my-account/add-payment-method/)
        if (count($path_segments) === 2) {
            $maybe_endpoint = $path_segments[1] ?? '';
            $endpoint_key = $this->endpoint_key_from_localized_slug($maybe_endpoint) ?: $maybe_endpoint;
            if ($maybe_endpoint && array_key_exists($endpoint_key, $this->acc_wc_endpoints)) {
                $wp->set_query_var($endpoint_key, true);
                $wp->query_vars[$endpoint_key] = true;
            }
        }

        // Also handle nested endpoints without arguments (e.g., /my-account/payment-methods/add-payment-method/)
        if (count($path_segments) >= 3) {
            $last_seg = $path_segments[count($path_segments) - 1] ?? '';
            $last_key = $this->endpoint_key_from_localized_slug($last_seg) ?: $last_seg;
            if ($last_seg && array_key_exists($last_key, $this->acc_wc_endpoints)) {
                $wp->set_query_var($last_key, true);
                $wp->query_vars[$last_key] = true;
            }
        }
    }

    /**
     * Register rewrite endpoints so URLs like /my-account/view-order/40121/ don't 404 on CPT permalinks.
     * We add all localized endpoint slugs.
     *
     * @return void
     */
    public function register_acc_wc_endpoints(): void
    {
        if (is_admin()) {
            return;
        }

        // Use the same endpoint mask as WooCommerce
        $mask = EP_PAGES;

        foreach ($this->acc_wc_endpoints as $endpoint_key => $localizedSlugs) {
            foreach ((array) $localizedSlugs as $slug) {
                add_rewrite_endpoint($slug, $mask);
            }
        }

        // Add custom rewrite rules for our CPT with endpoints
        // Add rules for all endpoints that need arguments

        // Define all endpoint keys that need arguments
        $bases = array_values($this->acc_wc_index_slugs);
        $langDir = '(?:[a-z]{2}(?:-[A-Z]{2})?/)?';
        foreach ($bases as $base) {
            foreach ($this->acc_wc_endpoints as $endpoint_key => $localizedSlugs) {
                foreach ((array) $localizedSlugs as $slug) {
                    // Some action endpoints should render the payment methods page
                    $target_page_slug = in_array($endpoint_key, ['delete-payment-method', 'set-default-payment-method'], true)
                        ? 'payment-methods'
                        : $endpoint_key;

                    // Direct: /base/slug/ID
                    add_rewrite_rule(
                        '^' . $langDir . preg_quote($base, '/') . '/' . preg_quote($slug, '/') . '/([^/]+)/?$',
                        'index.php?my-account=' . $target_page_slug . '&' . $endpoint_key . '=$matches[1]',
                        'top'
                    );

                    // Nested: /base/child/slug/ID
                    add_rewrite_rule(
                        '^' . $langDir . preg_quote($base, '/') . '/([^/]+)/' . preg_quote($slug, '/') . '/([^/]+)/?$',
                        'index.php?my-account=$matches[1]&' . $endpoint_key . '=$matches[2]',
                        'top'
                    );
                }
            }
        }
    }

    /**
     * If WP resolved to 404 for an ACC endpoint with an argument, clear 404 so our template renders.
     * This guards against stale rewrites until permalinks are flushed.
     */
    public function maybe_clear_404_for_acc_endpoints(): void
    {
        if (!is_404()) {
            return;
        }

        $segments = $this->get_language_aware_segments();

        if (count($segments) < 3) {
            return;
        }
        $bases = array_values($this->acc_wc_index_slugs);
        $base = $segments[0] ?? '';
        if (!in_array($base, $bases, true)) {
            return;
        }
        $second_last = $segments[count($segments) - 2] ?? '';
        $last = end($segments) ?: '';

        $endpoint_key = $this->endpoint_key_from_localized_slug($second_last) ?: $second_last;
        if ($second_last && array_key_exists($endpoint_key, $this->acc_wc_endpoints) && $last !== '') {
            global $wp_query;
            if ($wp_query) {
                $wp_query->is_404 = false;
            }
        }
    }

    /**
     * Disable WooCommerce account page detection for ACC pages.
     *
     * @param bool $is_account_page
     * @return bool
     */
    public function enable_wc_account_page_detection($is_account_page)
    {
        if ($this->is_acc_wc_context()) {
            return true;
        }

        return $is_account_page;
    }

    /**
     * Disable WooCommerce page detection for ACC pages.
     *
     * @param int $page_id
     * @param string $page
     * @return int
     */
    public function enable_wc_page_detection($page_id, $page)
    {
        if ($this->is_acc_wc_context() && $page === 'myaccount') {
            // Return the actual WooCommerce my account page ID so plugins relying on it behave
            $wc_myaccount_page_id = (int) get_option('woocommerce_myaccount_page_id');

            return $wc_myaccount_page_id ?: $page_id;
        }

        return $page_id;
    }

    /**
     * Disable WooCommerce endpoint URL detection for ACC pages.
     *
     * @param bool $is_endpoint
     * @param string $endpoint
     * @return bool
     */
    public function enable_wc_endpoint_url_detection($is_endpoint, $endpoint = '')
    {
        if (!$this->is_acc_wc_context()) {
            return $is_endpoint;
        }

        // If a specific endpoint key is being checked, validate against our known endpoints
        if (is_string($endpoint) && $endpoint !== '') {
            $endpoint_key = $this->getCurrentEndpointKey();
            if ($endpoint_key !== '' && $endpoint_key === $endpoint) {
                return true;
            }

            return $is_endpoint;
        }

        // Generic check: any known endpoint present on current ACC URL
        return $this->getCurrentEndpointKey() !== '';
    }

    /**
     * If in ACC WC context and on a known endpoint, mark WP/WC query accordingly.
     */
    public function maybe_flag_wc_endpoint_query()
    {
        if (!$this->is_acc_wc_context()) {
            return;
        }

        $endpoint_key = $this->getCurrentEndpointKey();
        if ($endpoint_key === '') {
            return;
        }

        // Ensure the query var is set for the endpoint value
        global $wp, $wp_query;
        if (isset($wp->query_vars[$endpoint_key])) {
            $value = $wp->query_vars[$endpoint_key];
        } elseif (isset($wp_query->query_vars[$endpoint_key])) {
            $value = $wp_query->query_vars[$endpoint_key];
        } else {
            $value = true; // presence without specific value
            $wp->query_vars[$endpoint_key] = $value;
            $wp_query->query_vars[$endpoint_key] = $value;
            set_query_var($endpoint_key, $value);
        }

        // Nothing else needed; WC reads from query vars.
        // Make is_page( wc_get_page_id('myaccount') ) truthy for ACC so core
        // is_add_payment_method_page() and similar checks pass.
        $wc_myaccount_page_id = (int) get_option('woocommerce_myaccount_page_id');
        if ($wc_myaccount_page_id > 0) {
            if (isset($wp_query)) {
                $wp_query->is_page = true;
                $wp_query->is_singular = true;
                $wp_query->queried_object_id = $wc_myaccount_page_id;
                $wp_query->query_vars['page_id'] = $wc_myaccount_page_id;
            }
            if (isset($wp)) {
                $wp->query_vars['page_id'] = $wc_myaccount_page_id;
            }
        }
    }

    /**
     * Return true for ACC add-payment-method endpoint when WooCommerce checks this conditional.
     */
    public function maybe_force_is_add_payment_method_page($is)
    {
        if ($is) {
            return $is;
        }

        if (!$this->is_acc_wc_context()) {
            return $is;
        }

        return $this->getCurrentEndpointKey() === 'add-payment-method';
    }

    // Removed forced enqueues and is_add_payment_method_page shim (not needed).

    /**
     * Determine if current request is within ACC WooCommerce context (ACC base URL).
     */
    private function is_acc_wc_context(): bool
    {
        // Check by path base against translated ACC WC index slugs
        $segments = $this->get_language_aware_segments();

        if (!empty($segments)) {
            $bases = array_values($this->acc_wc_index_slugs);

            if (in_array($segments[0], $bases, true)) {

                return true;
            }
        }

        // Fallback: CPT post type check
        global $post;
        $post_check = ($post && $post->post_type === 'my-account');

        return $post_check;
    }

    /**
     * Get the current WooCommerce endpoint key from the URL.
     *
     * @return string The endpoint key or empty string if not found
     */
    public function getCurrentEndpointKey(): string
    {
        $segments = $this->get_language_aware_segments();
        if (count($segments) < 2) {
            return '';
        }
        // Try last segment first (handles nested endpoint without arg, e.g., /payment-methods/add-payment-method/)
        $last = $segments[count($segments) - 1] ?? '';
        $last_key = $this->endpoint_key_from_localized_slug($last) ?: $last;
        if ($last && array_key_exists($last_key, $this->acc_wc_endpoints)) {
            return $last_key;
        }

        // Fallback: prefer second-last when there is a value (e.g., /view-order/123/)
        $second_last = $segments[count($segments) - 2] ?? '';
        $second_key = $this->endpoint_key_from_localized_slug($second_last) ?: $second_last;

        return array_key_exists($second_key, $this->acc_wc_endpoints) ? $second_key : '';
    }

    /**
     * Check if the current request is a WooCommerce endpoint.
     *
     * @return bool True if the current request is a WooCommerce endpoint, false otherwise
     */
    public function isWooCommerceEndpoint(): bool
    {
        return $this->getCurrentEndpointKey() !== '';
    }

    // Removed body_class additions (not needed).

    /**
     * Map WooCommerce myaccount page id to current ACC page id so is_page( wc_get_page_id('myaccount') ) is satisfied.
     */
    public function map_myaccount_page_id_to_acc($value)
    {
        if (!$this->is_acc_wc_context()) {
            return $value;
        }

        global $post;
        if ($post && $post->post_type === 'my-account') {
            return (int) $post->ID;
        }

        return $value;
    }

    /**
     * TEMP DEBUG: Log enqueue state for Stripe UPE on add-payment-method pages.
     */
    public function debug_stripe_enqueue_state(): void
    {
        if (!$this->is_acc_wc_context()) {
            return;
        }

        $endpoint = $this->getCurrentEndpointKey();
        if ($endpoint !== 'add-payment-method') {
            return;
        }

        if (!function_exists('wc_get_logger')) {
            return;
        }

        $logger = wc_get_logger();
        $lang = WACC()->getLanguage();
        $is_add_page = function_exists('is_add_payment_method_page') ? is_add_payment_method_page() : false;
        $upe_enqueued = wp_script_is('wc-stripe-upe-classic', 'enqueued') ? 'yes' : 'no';
        $upe_registered = wp_script_is('wc-stripe-upe-classic', 'registered') ? 'yes' : 'no';

        $logger->debug(
            'ACC DEBUG Stripe UPE enqueue: lang=' . $lang . ' endpoint=' . $endpoint . ' is_add_payment_method_page=' . ($is_add_page ? 'yes' : 'no') . ' script_registered=' . $upe_registered . ' script_enqueued=' . $upe_enqueued,
            ['source' => 'wicket-acc']
        );
    }

    /**
     * Get the WooCommerce endpoints.
     *
     * @return array
     */
    public function getEndpoints()
    {
        return $this->acc_wc_endpoints;
    }
}
