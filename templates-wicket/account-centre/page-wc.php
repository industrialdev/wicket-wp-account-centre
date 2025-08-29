<?php

/**
 * WooCommerce My Account page.
 */
defined('ABSPATH') || exit;

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
$acc_global_headerbanner_page_id = WACC()->getGlobalHeaderBannerPageId();
$acc_global_headerbanner_status = WACC()->getOption('acc_global-headerbanner', false);
$current_page_id = get_the_ID();
$default_language = wicket_get_current_language();

// We're already on a WooCommerce endpoint (Router selected this template)
// Set the necessary variables directly
$wc_wrapper_class = 'woocommerce';
$is_wc_endpoint = true;

// Get the current endpoint key
$wc_endpoint = WACC()->WooCommerce()->getCurrentEndpointKey();

// Get the endpoint argument if it exists
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', trim($path, '/'))));

// Guard: only set query var when we have a valid endpoint key
if ($wc_endpoint && count($segments) >= 3) {
    $endpoint_arg = $segments[count($segments) - 1];

    // Populate WP query var so Woo endpoint handlers receive the argument
    if ($endpoint_arg !== null && $endpoint_arg !== '') {
        // Cast numeric args (like order ID) to int for safety
        $value = is_numeric($endpoint_arg) ? absint($endpoint_arg) : sanitize_text_field(wp_unslash($endpoint_arg));
        set_query_var($wc_endpoint, $value);
    }
}

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
        <!--<div class="woocommerce-MyAccount-content">-->
        <?php
        // Render the WooCommerce account content (includes notices and endpoint content)
        do_action('woocommerce_account_content');
?>
        <!--</div>-->
    </div>

    <?php
    if ('right' === $acc_sidebar_location) {
        WACC()->renderAccSidebar();
    }
?>
</div>

<?php get_footer(); ?>
