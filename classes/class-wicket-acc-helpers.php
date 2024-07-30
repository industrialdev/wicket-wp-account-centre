<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Helpers file of Module
 *
 * @package  wicket-account-centre
 * @version  1.0.0
 */

class Helpers extends WicketAcc
{
	/**
	 * Get current person (MDP)
	 *
	 * Example:
	 * $person = WACC()->get_current_person();
	 *
	 * @return object $person
	 */
	public function get_current_person()
	{
		return wicket_current_person();
	}
}
