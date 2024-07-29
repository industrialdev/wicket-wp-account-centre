<?php
// No direct access
defined('ABSPATH') || exit;

/**
 * Global WACC() function
 * Our entry point for this plugin functionality. All future helpers should live as Class methods accessible through this function.
 *
 * @return object $wac
 */
function WACC()
{
	return Wicket_Acc::instance();
}
