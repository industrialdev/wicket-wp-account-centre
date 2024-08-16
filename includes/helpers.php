<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Magic wrapper Class for WACC() helpers
 *
 * @return object
 */
function WACC()
{
	static $instance = null;

	if ($instance === null) {
		$instance = new MethodRouter();
	}

	return $instance;
}

/**
 *
 * DO NOT ADD MORE HELPERS HERE
 *
 * Please, use class-wicket-acc-helpers.php file instead.
 *
 * class-wicket-acc-helpers.php can contain several methods as helpers.
 *
 * Usage of methods inside the class, from outsite this plugin:
 * WACC()->method_name();
 *
 * Also, class-acc-helpers-router.php can mount an already registered class, so WACC() can use them and their methods directly as helpers. Without the need to write a wrapper helper method for each method of the exposed class.
 *
 * Usage of any method from outside this plugin:
 * WACC()->className->method_name();
 *
 */
