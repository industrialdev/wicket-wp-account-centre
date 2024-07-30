<?php

/**
 * Template Name: Account Center - Tpl
 */

get_header();

$acc_post_index_id    = get_field('account_centre_index', 'option');
$acc_sidebar_position = get_field('sidebar_position', 'option');

if (!defined('WICKET_ACC_PATH') || empty($acc_sidebar_position)) {
	wp_die('Please activate and configure the Wicket Account Center plugin to use this template.');
}

if (have_posts()) :
	while (have_posts()) :
		the_post();
		the_content();
?>
		<div class="woocommerce-wicket--container">
			<?php
			if ('left-sidebar' == $acc_sidebar_position) {
				// Check if file exists on child theme first
				if (file_exists(
					WICKET_ACC_TEMPLATE_PATH . 'front/navigation.php'
				)) {
					include_once WICKET_ACC_TEMPLATE_PATH . 'front/navigation.php';
				} else {
					include_once WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'front/navigation.php';
				}
			}
			?>
			<div class="woocommerce-wicket--account-centre">
				<h2 class="wicket-h2">This is a custom Account Center Template account-center-tpl.php"</h2>
				<p>
					Copy and customise this template file and assign to a WP Page.<br>
					Add the Wicket <b>'Banner'</b> block with added class: <b>alignfull</b> to span screen width.<br>
					To add content on the front-end create the corresponding Account Center Page and note the Post_ID number.<br>
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
			if ('right-sidebar' == $acc_sidebar_position) {
				// Check if file exists on child theme first
				if (file_exists(
					WICKET_ACC_TEMPLATE_PATH . 'front/navigation.php'
				)) {
					include_once WICKET_ACC_TEMPLATE_PATH . 'front/navigation.php';
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
