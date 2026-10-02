<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/*
 * Available $args[] variables:
 *
 * block_name - Block name
 * block_description - Block description
 * block_slug - Block slug
 */
?>
<style type="text/css">
    .wicket-ac-block-preview {
        border: 2px dotted var(--wp--preset--color--tertiary);
        padding: 1rem;
    }
</style>
<div class="wicket-ac-touchpoints__preview wicket-ac-block-preview <?php echo $args['block_slug']; ?>">
    <div class="wicket-ac-touchpoints__preview__title"><?php echo esc_html($args['block_name']); ?></div>
    <div class="wicket-ac-touchpoints__preview__content"><?php echo esc_html($args['block_description']); ?></div>
</div>
