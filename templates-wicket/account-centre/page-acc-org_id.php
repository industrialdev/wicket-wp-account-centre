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

// ACC Options (CF first, ACF fallback via helper)
$acc_dashboard_id = WACC()->getAccPageId();
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
    $org_id = isset($_REQUEST['org_uuid']) ? sanitize_text_field($_REQUEST['org_uuid']) : null;
if (empty($org_id) && isset($_REQUEST['org_id'])) {
    $org_id = sanitize_text_field($_REQUEST['org_id']);
}

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
