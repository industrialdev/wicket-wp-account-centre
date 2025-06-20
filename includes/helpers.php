<?php

// No direct access

use WicketAcc\MethodRouter;

defined('ABSPATH') || exit;

/*
 *
 * DO NOT ADD MORE HELPERS HERE
 * DO NOT ADD MORE HELPERS HERE
 * DO NOT ADD MORE HELPERS HERE
 *
 * Please, use ./src/Helpers.php file instead.
 *
 * ./src/Helpers.php can contain several methods as helpers.
 *
 * Usage of methods inside the class, from outsite this plugin:
 * WACC()->method_name();
 *
 * Also, ./src/MethodRouter.php can mount an already registered class, so WACC() can use them and their methods directly as helpers. Without the need to write a wrapper helper method for each method of the exposed class.
 *
 * Usage of any method from outside this plugin:
 * WACC()->className->method_name();
 *
 */

/**
 * Magic wrapper Class for WACC() helpers.
 *
 * @return object
 */
function WACC()
{
    static $instance = null;

    if ($instance === null) {
        $instance = new MethodRouter();
        $instance->init();
    }

    return $instance;
}

/*
 *
 * DO NOT ADD MORE HELPERS HERE
 * DO NOT ADD MORE HELPERS HERE
 * DO NOT ADD MORE HELPERS HERE
 *
 * Please, use ./src/Helpers.php file instead.
 *
 * ./src/Helpers.php can contain several methods as helpers.
 *
 * Usage of methods inside the class, from outsite this plugin:
 * WACC()->method_name();
 *
 * Also, ./src/MethodRouter.php can mount an already registered class, so WACC() can use them and their methods directly as helpers. Without the need to write a wrapper helper method for each method of the exposed class.
 *
 * Usage of any method from outside this plugin:
 * WACC()->className->method_name();
 *
 */
