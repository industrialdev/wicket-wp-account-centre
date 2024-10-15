<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * block_name - Block name
 * block_slug - Block slug
 * block_error - Error message
 */

// Not set?
if (!isset($args['block_name']) || !isset($args['block_slug']) || !isset($args['block_error'])) {
    $args = [
        'block_name'   => __('No name', 'wicket-acc'),
        'block_slug'   => 'wicket-acc-error-block',
        'block_error'  => __('Forgot something?:', 'wicket-acc'),
    ];
}
?>
<style type="text/css">
	.wicket-acc-block-error {
		border: 2px dotted var(--tec-color-border-primary);
		padding: 1rem;
	}
</style>
<section class="wicket-acc-block-error <?php echo $args['block_slug']; ?> <?php echo $args['block_slug']; ?>__error">
	<div class="wicket-acc-block-error__content">
		<h2><?php esc_html_e('Error', 'wicket-acc'); ?></h2>
		<p><?php esc_html_e('There was an error on block:', 'wicket-acc'); ?> <?php echo $args['block_name']; ?></p>
		<p><?php echo $args['block_error']; ?></p>
	</div>
</section>
