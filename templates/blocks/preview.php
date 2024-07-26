<?php
// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * block_name - Block name
 * block_description - Block description
 * attrs - Attributes for the block container.
 * display - Display type: upcoming, past, all
 * num_results - Number of results to display
 * total_results - Total results
 * counter - Counter
 * display_type - Touchpoint display type: upcoming, past, all
 * touchpoints - Touchpoints results
 * switch_link - Switch link
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
