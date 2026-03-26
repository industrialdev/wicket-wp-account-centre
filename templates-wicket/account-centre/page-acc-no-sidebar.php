<?php
// No direct access
defined('ABSPATH') || exit;

/*
 * Template Name: ACC Page (No Sidebar)
 * Template Post Type: my-account
 *
 * ACC pages template — no sidebar variant.
 *
 * This is a sidebar-free template for pages created inside Wicket Account Centre CPT.
 * Only the_content() is rendered, wrapped between the theme header and footer.
 *
 * This template can be overridden (at theme level) by copying it to yourtheme/templates-wicket/account-centre/page-acc-no-sidebar.php.
 */

get_header();

$wrapper_classes = [
    'wicket-acc',
    'wicket-acc-page',
    'wicket-acc-postid-' . get_the_ID(),
    'wicket-acc-container',
    'woocommerce-wicket--container',
    'wicket-acc--no-sidebar',
];

if (defined('WICKET_WP_THEME_V2')) {
    $wrapper_classes[] = 'wicket-acc--v2';
}

$dev_wrapper_classes = get_field('page_wrapper_class');
if (!empty($dev_wrapper_classes)) {
    $wrapper_classes[] = $dev_wrapper_classes;
}

$acc_global_headerbanner_page_id = WACC()->getGlobalHeaderBannerPageId();
$acc_global_headerbanner_status  = WACC()->getOption('acc_global-headerbanner', false);

// Check if we have a global banner page
if ($acc_global_headerbanner_page_id && $acc_global_headerbanner_status) {
    $global_banner_page = get_post($acc_global_headerbanner_page_id);
    if ($global_banner_page) {
        echo '<div class="wicket-acc alignfull wp-block-wicket-banner block">';
        echo apply_filters('the_content', $global_banner_page->post_content);
        echo '</div>';
    }
}
?>

<div class="<?php echo implode(' ', $wrapper_classes); ?>">
    <div class="woocommerce-wicket--account-centre wicket-acc-page wicket-acc-page-acc-no-sidebar">
        <?php
        if (have_posts()) {
            while (have_posts()) :
                the_post();
                the_content();
            endwhile;
        }
        ?>
    </div>
</div>

<?php
get_footer();
