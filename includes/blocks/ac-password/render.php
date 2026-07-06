<?php

namespace WicketAcc\Blocks\Password;

/*
 * Legacy ACC Password block wrapper.
 *
 * Existing posts may still embed the legacy ACF `wicket-ac/ac-password` block.
 * Rather than duplicate the form markup, delegate to the current
 * `wicket-acc/change-password` HyperBlocks block by rendering its block
 * comment through do_blocks(). That runs the registered render_callback
 * (which executes the .hb.php template).
 *
 * Note: the legacy block is deprecated, so custom editor-set attributes
 * (form_title/form_instructions) are not passed through — the new block
 * renders with its registered defaults, matching the behavior of the old
 * renderBlock([], [], '') call which also ignored attributes.
 */

wp_enqueue_style('wicket-acc-password-block');

// do_blocks parses the comment, resolves the registered block, and invokes
// its render_callback. The callback reads form-submission state from a static
// on the ChangePassword class, so this path preserves that behavior.
echo do_blocks('<!-- wp:wicket-acc/change-password /-->');
