<?php

namespace WicketAcc\Blocks\Password;

use WicketAcc\Blocks\ChangePassword;

/*
 * Legacy ACC Password block wrapper.
 * Renders the new Carbon Fields ChangePassword block output to keep legacy block references working.
 */

static $changePasswordInstance;

if (!$changePasswordInstance instanceof ChangePassword) {
    $changePasswordInstance = new ChangePassword();
}

wp_enqueue_style('wicket-acc-password-block');

$changePasswordInstance->renderBlock([], [], '');
