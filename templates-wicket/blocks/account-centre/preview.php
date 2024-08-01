<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * block_name - Block name
 * block_description - Block description
 * block_slug - Block slug
 */
?>
<style type="text/css">
	.wicket-ac-block-preview {
		border: 2px dotted var(--tec-color-border-tertiary);
		padding: 1rem;
	}
</style>
<div class="wicket-ac-touchpoints__preview wicket-ac-block-preview <?php echo $args['block_slug']; ?>">
	<div class="wicket-ac-touchpoints__preview__title"><?php esc_html_e($args['block_name'], 'wicket-acc'); ?></div>
	<div class="wicket-ac-touchpoints__preview__content"><?php esc_html_e($args['block_description'], 'wicket-acc'); ?></div>
</div>
