<?php
// No direct access
defined('ABSPATH') || exit;

/**
 * Template Name: ACC Default Template
 *
 * This is the default template for the Wicket Account Centre pages.
 *
 * This template can be overridden by copying it to yourtheme/templates-wicket/account-centre/page-wicket_acc.php.
 */

get_header();

$wrapper_classes = [
	'wicket-acc',
	'wicket-acc-page',
	'wicket-acc-postid-' . get_the_ID(),
	'wicket-acc-container',
	'woocommerce-wicket--container',
];

$dev_wrapper_classes = get_field('page_wrapper_class');
if (!empty($dev_wrapper_classes)) {
	$wrapper_classes[] = $dev_wrapper_classes;
}

// ACC Options
$acc_dashboard_id          = get_field('acc_page_dashboard', 'option');
$acc_sidebar_location      = get_field('acc_sidebar_location', 'option');
$acc_spelling              = get_field('acc_localization', 'option');
$acc_display_breadcrumb    = false;
$acc_display_publish_date  = false;
$is_wc_endpoint            = false;
$acc_global_headerbanner_page_id = WACC()->get_global_headerbanner_page_id();
$acc_global_headerbanner_status  = get_field('acc_global-headerbanner', 'option');

// WooCommerce endpoints
$wc_endpoints = WC()->query->get_query_vars();
$current_url  = $_SERVER['REQUEST_URI'];
$wc_endpoint  = basename(rtrim($current_url, '/'));

if (in_array($wc_endpoint, $wc_endpoints)) {
	$is_wc_endpoint = true;
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
	echo "<p class='mt-3 mb-4'><strong>" . __('Published:', 'wicket') . ' ' . get_the_date('d-m-Y') . "</strong></p>";
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
		WACC()->render_acc_sidebar();
	}
	?>

	<div class="woocommerce-wicket--account-centre wicket-acc-page wicket-acc-page-acc">
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
			do_action("woocommerce_account_{$wc_endpoint}_endpoint");
		}
		?>
	</div>

	<?php
	if ('right' === $acc_sidebar_location) {
		// Get the Wicket ACC sidebar template
		WACC()->render_acc_sidebar();
	}
	?>
</div>

<?php
get_footer();
