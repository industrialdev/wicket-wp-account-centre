<?php

use Wicket_Acc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Global WACC() function
 * Wicket Account Centre Helpers
 *
 * @return object $wac
 */
function WACC()
{
	return new Wicket_Acc\MethodRouter();
}

/**
 * DO NOT ADD MORE HELPERS HERE
 *
 * Please, use class-wicket-acc-helpers.php file instead
 */
