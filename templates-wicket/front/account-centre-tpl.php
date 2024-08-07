<?php

/**
 * Template Name: Account Centre - Tpl
 */

get_header();

$acc_post_index_id    = get_field('acc_page_account-centre', 'option');
$acc_sidebar_location = get_field('acc_sidebar_location', 'option');

if (empty($acc_sidebar_location)) {
	$acc_sidebar_location = 'right';
}

if (!defined('WICKET_ACC_PATH') || empty($acc_sidebar_location)) {
	wp_die('Please activate and configure the Wicket Account Centre plugin to use this template.');
}

if (have_posts()) :
	while (have_posts()) :
		the_post();
		the_content();
?>
		<div class="woocommerce-wicket--container">
			<?php
			if ('left' == $acc_sidebar_location) {
				// Check if file exists on child theme first
				if (file_exists(
					WICKET_ACC_USER_TEMPLATE_PATH . 'front/navigation.php'
				)) {
					include_once WICKET_ACC_USER_TEMPLATE_PATH . 'front/navigation.php';
				} else {
					include_once WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'front/navigation.php';
				}
			}
			?>
			<div class="woocommerce-wicket--account-centre">
				<h2 class="wicket-h2">This is a custom Account Centre Template account-centre-tpl.php"</h2>
				<p>
					Copy and customise this template file and assign to a WP Page.<br>
					Add the Wicket <b>'Banner'</b> block with added class: <b>alignfull</b> to span screen width.<br>
					To add content on the front-end create the corresponding Account Centre Page and note the Post_ID number.<br>
					Set the Post ID at the top of your template to inject the content: <b>&lt;?php $acc_post_index_id = 12051; ?&gt;</b>
				</p>
				</h2>
				<?php
				$the_post = get_post($acc_post_index_id);

				if (get_post_field('post_status', $acc_post_index_id) == 'publish') {
					echo apply_filters('the_content', get_post_field('post_content', $acc_post_index_id));
				}
				?>
			</div>
			<?php
			if ('right' == $acc_sidebar_location) {
				// Check if file exists on child theme first
				if (file_exists(
					WICKET_ACC_USER_TEMPLATE_PATH . 'front/navigation.php'
				)) {
					include_once WICKET_ACC_USER_TEMPLATE_PATH . 'front/navigation.php';
				} else {
					include_once WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'front/navigation.php';
				}
			}
			?>
		</div>
<?php
	endwhile;
endif;
get_footer();
