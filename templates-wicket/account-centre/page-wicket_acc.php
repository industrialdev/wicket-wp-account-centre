<?php
// No direct access
defined('ABSPATH') || exit;

if (!defined('WICKET_ACC_PATH') || empty($acc_sidebar_location)) {
	wp_die('Please activate and configure the Wicket Account Centre plugin to use this template.');
}

/**
 * Template Name: ACC Generic Template
 */

get_header();

$wrapper_classes     = [];
$dev_wrapper_classes = get_field('page_wrapper_class');
if (!empty($dev_wrapper_classes)) {
	$wrapper_classes[] = $dev_wrapper_classes;
}

// Class for Roster Managment styling
$wrapper_classes[] = 'roster-management';
$wrapper_classes[] = 'acc-organization-management';

// ACC Options
$acc_post_index_id    = get_field('acc_page_account-centre', 'option');
$acc_sidebar_location = get_field('acc_sidebar_location', 'option');

if (empty($acc_sidebar_location)) {
	$acc_sidebar_location = 'right';
}

$display_breadcrumb   = get_field('display_breadcrumb');
$display_publish_date = get_field('display_publish_date');

if ($display_breadcrumb) {
	echo '<div class="wp-block-breadcrumbs">'; // Having the `wp-block-` prefix will help align it with the other Blocks
	get_component('breadcrumbs', []);
	echo '</div>';
}
if ($display_publish_date) {
	echo '<div class="wp-block-published-date">';
	echo "<p class='mt-3 mb-4'><strong>" . __('Published:', 'wicket') . ' ' . get_the_date('d-m-Y') . "</strong></p>";
	echo '</div>';
}
?>

<div class="woocommerce-wicket--container <?php echo implode(' ', $wrapper_classes) ?>">
	<?php
	if ('left' === $acc_sidebar_location) {
		do_action('woocommerce_account_navigation');
	}
	?>

	<div class="woocommerce-wicket--account-centre">
		<?php
		the_content();
		?>
	</div>

	<?php
	if ('right' === $acc_sidebar_location) {
		do_action('woocommerce_account_navigation');
	}
	?>
</div>
