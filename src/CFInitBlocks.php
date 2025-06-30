<?php

namespace WicketAcc;

use WicketAcc\Blocks\ChangePassword;

// No direct access
defined('ABSPATH') || exit;

/**
 * Carbon Fields Blocks Init Class.
 */
class CFInitBlocks extends WicketAcc
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
