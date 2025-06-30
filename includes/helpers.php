<?php

// No direct access

use WicketAcc\WicketAcc;

defined('ABSPATH') || exit;

/*
 *
 * DO NOT ADD MORE HELPERS HERE.
 * This file should only contain the WACC() helper function.
 *
 * Please, add new helper methods to the ./src/Helpers.php file.
 *
 * The WACC() function returns the main plugin instance, which provides
 * access to all plugin components and helper methods.
 *
 * ---- USAGE ----
 *
 * To call a helper method (from the Helpers class):
 * WACC()->my_helper_method();
 *
 * To access a plugin component (e.g., Profile, Mdp):
 * WACC()->className->method_name();
 *
 */

/**
 * Magic wrapper Class for WACC() helpers.
 *
 * @return WicketAcc
 */
function WACC()
{
    return WicketAcc::get_instance();
}

/*
 *
 * DO NOT ADD MORE HELPERS HERE.
 * This file should only contain the WACC() helper function.
 *
 * Please, add new helper methods to the ./src/Helpers.php file.
 *
 * The WACC() function returns the main plugin instance, which provides
 * access to all plugin components and helper methods.
 *
 * ---- USAGE ----
 *
 * To call a helper method (from the Helpers class):
 * WACC()->my_helper_method();
 *
 * To access a plugin component (e.g., Profile, Mdp):
 * WACC()->className->method_name();
 *
 */
