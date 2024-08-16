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

$wrapper_classes     = [];
$wrapper_classes[]   = 'wicket-acc-page wicket-acc-postid-' . get_the_ID();

$dev_wrapper_classes = get_field('page_wrapper_class');
if (!empty($dev_wrapper_classes)) {
	$wrapper_classes[] = $dev_wrapper_classes;
}

// ACC Options
$acc_index_id        = get_field('acc_page_account-centre', 'option');
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
	echo '<div class="wp-block-published-date">'; // Having the `wp-block-` prefix will help align it with the other Blocks
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
		<?php the_content(); ?>
	</div>

	<?php
	if ('right' === $acc_sidebar_location) {
		do_action('woocommerce_account_navigation');
	}
	?>
</div>

<?php
get_footer();
