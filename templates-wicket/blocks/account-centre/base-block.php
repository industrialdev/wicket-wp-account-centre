<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * deliver - Deliver description
 * a_list - A_list description
 * of - Of description
 * available - Available description
 * variables - Variables description
 */
?>
<section class="wicket-acc-base-block <?php echo $args['block_slug']; ?>">
	<div class="wicket-acc-base-block__content">
		<h2><?php esc_html_e('Title', 'wicket-acc'); ?></h2>
		<p>
			<?php esc_html_e('This are the available variables:', 'wicket-acc'); ?>
		</p>
		<p>
			<?php echo '<pre>' . var_dump($args) . '</pre>'; ?>
		</p>
	</div>
</section>
