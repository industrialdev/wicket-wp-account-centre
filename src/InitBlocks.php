<?php

namespace WicketAcc;

use WicketAcc\Blocks\ChangePassword;
use WicketAcc\Blocks\ManageEmployer;

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
        new ManageEmployer();
    }
}
