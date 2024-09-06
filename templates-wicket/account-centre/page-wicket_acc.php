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
$wrapper_classes[]   = 'wicket-acc';
$wrapper_classes[]   = 'wicket-acc-page wicket-acc-postid-' . get_the_ID();
$wrapper_classes[]   = 'wicket-acc-container';
$wrapper_classes[]   = 'woocommerce-wicket--container';

$dev_wrapper_classes = get_field('page_wrapper_class');
if (!empty($dev_wrapper_classes)) {
	$wrapper_classes[] = $dev_wrapper_classes;
}

// ACC Options
$acc_index_id         = get_field('acc_page_account-centre', 'option');
$acc_sidebar_location = get_field('acc_sidebar_location', 'option');
$acc_spelling         = get_field('acc_localization', 'option');
$display_breadcrumb   = false;
$display_publish_date = false;

if (empty($acc_sidebar_location)) {
	$acc_sidebar_location = 'right';
}

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

if (!empty($acc_spelling)) {
	$acc_spelling = $acc_spelling['label'];
} else {
	$acc_spelling = __('Account Centre', 'wicket-acc');
}
?>

<div class="alignfull wp-block-wicket-banner">
	<?php

	get_component('banner', [
		'title'            => sprintf(__('%s', 'wicket-acc'), $acc_spelling),
		'intro'            => sprintf(__('Welcome to the %s. Here you can manage your account, view your membership details and more.', 'wicket-acc'), $acc_spelling),
		'show_date'        => false,
		'text_alignment'   => 'left',
		'reversed'         => false,
		'background_style' => 'reversed',
		'classes'          => ['py-8', 'px-4', 'relative', 'bg-dark-100', 'text-white', 'bg-mode-reversed'],
	]); ?>
</div>

<div class="<?php echo implode(' ', $wrapper_classes) ?>">
	<?php
	if ('left' === $acc_sidebar_location) {
		do_action('woocommerce_account_navigation');
	}
	?>

	<div class="woocommerce-wicket--account-centre wicket-acc-page-acc">
		<?php
		if (!is_wc_endpoint_url()) {
			// ACC page
			if (have_posts()) {
				while (have_posts()) :
					the_post();
					the_content();
				endwhile;
			} else {
				echo '<p>' . __('Sorry, no ACC page found.', 'wicket-acc') . '</p>';
			}
		} else {
			// WooCommerce endpoint
			$endpoint = WC()->query->get_current_endpoint();
			if ($endpoint) {
				// Get a wicket_acc page from ACC Option
				$acc_page_id     = get_field('acc_page_' . $endpoint, 'option');
				if ($acc_page_id) {
					// Do we have a translated page for this ID?
					$translated_page_id = apply_filters('wpml_object_id', $acc_page_id, 'wicket_acc', false, 'en');
					if ($translated_page_id) {
						$acc_page_id = $translated_page_id;
					}

					$wicket_acc_page = get_post($acc_page_id);

					if ($wicket_acc_page) {
						echo apply_filters('the_content', $wicket_acc_page->post_content);
					}
				}

				do_action("woocommerce_account_{$endpoint}_endpoint");
			}
		}
		?>
	</div>

	<?php
	if ('right' === $acc_sidebar_location) {
		do_action('woocommerce_account_navigation');
	}
	?>
</div>

<?php
get_footer();
