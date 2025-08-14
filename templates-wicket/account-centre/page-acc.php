<?php
// No direct access
defined('ABSPATH') || exit;

/*
 * Template Name: ACC Page
 *
 * @package WicketAcc
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

//

/*
 * ACC pages template.
 *
 * This is the default template used for pages created inside Wicket Account Centre CPT.
 *
 * This template can be overridden (at theme level) by copying it to yourtheme/templates-wicket/account-centre/page-acc.php.
 */

get_header();

$wrapper_classes = [
    'wicket-acc',
    'wicket-acc-page',
    'wicket-acc-postid-' . get_the_ID(),
    'wicket-acc-container',
    'woocommerce-wicket--container',
];

if (defined('WICKET_WP_THEME_V2')) {
    $wrapper_classes[] = 'wicket-acc--v2';
}

$dev_wrapper_classes = get_field('page_wrapper_class');
if (!empty($dev_wrapper_classes)) {
    $wrapper_classes[] = $dev_wrapper_classes;
}

// ACC Options (CF first, ACF fallback via helper)
$acc_dashboard_id = WACC()->getOptionPageId('acc_page_dashboard', 0);
$acc_sidebar_location = WACC()->getOption('acc_sidebar_location', '');
$acc_spelling = WACC()->getOption('acc_localization', '');
$acc_display_breadcrumb = false;
$acc_display_publish_date = false;
$is_wc_endpoint = false;
$acc_global_headerbanner_page_id = WACC()->getGlobalHeaderBannerPageId();
$acc_global_headerbanner_status = WACC()->getOption('acc_global-headerbanner', false);
$current_page_id = get_the_ID();
$default_language = 'en';
if (function_exists('wpml_get_default_language')) {
    $default_language = wpml_get_default_language();
}

// WooCommerce endpoints
$wc_wrapper_class = '';
if (WACC()->isWooCommerceActive()) {
    $wc_endpoints = WC()->query->get_query_vars();
    $wc_endpoint = null;

    // Detect endpoint from URL segments so CPT slug routing keeps working
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = array_values(array_filter(explode('/', trim($path, '/'))));
    $last = end($segments) ?: '';
    $second_last = count($segments) > 1 ? $segments[count($segments) - 2] : '';
    $endpoint_arg = null;

    // If last segment is not an endpoint (e.g., numeric arg like order id), use previous segment
    if (in_array($last, array_keys($wc_endpoints), true)) {
        $wc_endpoint = $last;
    } elseif ($second_last && in_array($second_last, array_keys($wc_endpoints), true)) {
        $wc_endpoint = $second_last;
    } else {
        // Fallback: try Woo's endpoint checks (covers edge cases)
        foreach (array_keys($wc_endpoints) as $endpoint_key) {
            $query_var_value = get_query_var($endpoint_key);
            $is_wc_endpoint_check = function_exists('is_wc_endpoint') ? is_wc_endpoint($endpoint_key) : false;

            if ($is_wc_endpoint_check || $query_var_value) {
                $wc_endpoint = $endpoint_key;
                break;
            }
        }
    }

    // Try to capture the endpoint argument from URL
    if ($wc_endpoint) {
        // If the endpoint is the second-to-last segment, use the last as arg
        if ($second_last === $wc_endpoint && $last !== '') {
            $endpoint_arg = $last;
        }

        // Populate WP query var so Woo endpoint handlers receive the argument
        if ($endpoint_arg !== null) {
            // Woo query vars typically equal the endpoint key
            // Cast numeric args (like order ID) to int for safety
            $value = is_numeric($endpoint_arg) ? absint($endpoint_arg) : sanitize_text_field(wp_unslash($endpoint_arg));
            set_query_var($wc_endpoint, $value);
        }

        $is_wc_endpoint = true;
        $wc_wrapper_class = 'woocommerce';
    }
}

// WPML: Endpoint detection relies on query vars, not slugs, so no special handling needed here.

if (empty($acc_sidebar_location)) {
    $acc_sidebar_location = 'right';
}

if ($acc_display_breadcrumb) {
    echo '<div class="wp-block-breadcrumbs">'; // Having the `wp-block-` prefix will help align it with the other Blocks
    get_component('breadcrumbs', []);
    echo '</div>';
}

if ($acc_display_publish_date) {
    echo '<div class="wp-block-published-date">'; // Having the `wp-block-` prefix will help align it with the other Blocks
    echo "<p class='mt-3 mb-4'><strong>" . __('Published:', 'wicket-acc') . ' ' . get_the_date('d-m-Y') . '</strong></p>';
    echo '</div>';
}
?>

<?php
// Check if we have a global banner page
if ($acc_global_headerbanner_page_id && $acc_global_headerbanner_status) {
    $global_banner_page = get_post($acc_global_headerbanner_page_id);
    if ($global_banner_page) {
        echo '<div class="wicket-acc alignfull wp-block-wicket-banner">';
        echo apply_filters('the_content', $global_banner_page->post_content);
        echo '</div>';
    }
}
?>

<div class="<?php echo implode(' ', $wrapper_classes) ?>">
    <?php
    if ('left' === $acc_sidebar_location) {
        WACC()->renderAccSidebar();
    }
?>

    <div class="woocommerce-wicket--account-centre wicket-acc-page wicket-acc-page-acc <?php echo $wc_wrapper_class; ?>">
        <?php
    // ACC page
    if (have_posts()) {
        while (have_posts()) :
            the_post();
            the_content();
        endwhile;
    }

if ($is_wc_endpoint) {
    // Run the WooCommerce endpoint action

    // Check if the order exists and user can view it
    if ($wc_endpoint === 'view-order') {
        $order_id = get_query_var('view-order');
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                // Test the exact same validation that WooCommerce does
                $wc_validation_passes = ($order && current_user_can('view_order', $order_id));
            } else {
            }
        }
    }

    // Pass the endpoint argument as a parameter to the WooCommerce action
    $endpoint_value = get_query_var($wc_endpoint);

    if ($endpoint_value) {
        do_action("woocommerce_account_{$wc_endpoint}_endpoint", $endpoint_value);
    } else {
        do_action("woocommerce_account_{$wc_endpoint}_endpoint");
    }
} else {
}
?>
    </div>

    <?php
    if ('right' === $acc_sidebar_location) {
        // Get the Wicket ACC sidebar template
        WACC()->renderAccSidebar();
    }
?>
</div>

<?php
get_footer();
