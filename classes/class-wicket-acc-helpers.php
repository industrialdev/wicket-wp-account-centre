<?php

namespace Wicket_Acc;

/**
 * Helpers file of Module
 *
 * @package  wicket-account-centre
 * @version  1.0.0
 */

class Helpers extends \Wicket_Acc
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

if (function_exists('acf_get_field')) {
	new Helpers();
}
