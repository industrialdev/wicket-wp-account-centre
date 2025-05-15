<?php
// No direct access
defined('ABSPATH') || exit;

/*
 * Template Name: ACC page with Org Selector
 * Template Post Type: my-account
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

// ACC Options
$acc_dashboard_id = get_field('acc_page_dashboard', 'option');
$acc_sidebar_location = get_field('acc_sidebar_location', 'option');
$acc_spelling = get_field('acc_localization', 'option');
$acc_display_breadcrumb = false;
$acc_display_publish_date = false;
$is_wc_endpoint = false;
$acc_global_headerbanner_page_id = WACC()->getGlobalHeaderBannerPageId();
$acc_global_headerbanner_status = get_field('acc_global-headerbanner', 'option');
$current_page_id = get_the_ID();
$default_language = 'en';
if (function_exists('wpml_get_default_language')) {
    $default_language = wpml_get_default_language();
}

// WooCommerce endpoints
$wc_endpoints = WC()->query->get_query_vars();
$current_url = $_SERVER['REQUEST_URI'];
$wc_endpoint = basename(rtrim($current_url, '/'));
$wc_wrapper_class = '';

if (in_array($wc_endpoint, $wc_endpoints)) {
    $is_wc_endpoint = true;
    $wc_wrapper_class = 'woocommerce';
}

// WPML enabled?
if (defined('ICL_SITEPRESS_VERSION')) {
    // Not in default language
    if ($default_language !== ICL_LANGUAGE_CODE) {
        // We are in a translation, get the current page translation parent
        $original_page_id = apply_filters(
            'wpml_object_id',
            $current_page_id,
            'my-account',
            true,
            $default_language
        );

        // Get the correct WC endpoint slug
        $wc_endpoint = get_post($original_page_id)->post_name;

        if (in_array($wc_endpoint, $wc_endpoints)) {
            $is_wc_endpoint = true;
        }
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
    // Do we have an org_id in URL?
    $org_id = $_REQUEST['org_id'] ?? null;

if (empty($org_id) || $org_id === null) {
    echo do_shortcode('[org-selector]');
} else {
    if (have_posts()) {
        while (have_posts()) :
            the_post();
            the_content();
        endwhile;
    } else {
        echo '<p>' . __('No content found.', 'wicket-acc') . '</p>';
    }
}

if ($is_wc_endpoint) {
    // Run the WooCommerce endpoint action
    do_action("woocommerce_account_{$wc_endpoint}_endpoint");
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
