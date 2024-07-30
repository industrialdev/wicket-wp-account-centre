<?php
// No direct access
defined('ABSPATH') || exit;

get_header();
?>

<div class="wicket-account-centre container">
	<div class="woocommerce">
		<?php echo do_shortcode('[woocommerce_my_account]'); ?>
	</div>
</div>

<?php
get_footer();
