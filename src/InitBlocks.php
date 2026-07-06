<?php

namespace WicketAcc;

use WicketAcc\Blocks\ChangePassword;

// No direct access
defined('ABSPATH') || exit;

/**
 * HyperBlocks Blocks Init Class.
 */
class InitBlocks extends WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Initialize blocks
        new ChangePassword();
    }
}
