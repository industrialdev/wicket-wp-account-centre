<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * block_name - Block name
 * block_description - Block description
 * attrs - Attributes for the block container.
 * is_preview - Is preview?
 */
?>
<div class="wicket-ac-touchpoints__previewc wicket-ac-block-preview <?php echo $args['block_slug']; ?>">
	<div class="wicket-ac-touchpoints__preview__title">
		<?php esc_html_e($args['block_name'], 'wicket-acc'); ?>
	</div>
	<div class="wicket-ac-touchpoints__preview__content">
		<?php esc_html_e($args['block_description'], 'wicket-acc'); ?>
	</div>
</div>
