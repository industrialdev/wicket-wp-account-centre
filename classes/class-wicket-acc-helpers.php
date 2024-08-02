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

	/**
	 * Get ACC slug localization option
	 *
	 * account-centre
	 * account-center
	 *
	 * Option from Base Plugin Settings
	 *
	 * @return string
	 */
	public function get_slug()
	{
		$locale = get_field('ac_localization', 'option');

		if (empty($locale)) {
			return WICKET_ACC_SLUG;
		}

		// Check if returned value is a valid and allowed slug
		if (!in_array($locale, [WICKET_ACC_SLUG, 'account-centre', 'account-center'])) {
			return WICKET_ACC_SLUG;
		}

		return $locale;
	}

	/**
	 * Get ACC name localization option
	 *
	 * Account Centre
	 * Account Center
	 *
	 * Option from Base Plugin Settings
	 *
	 * @return string
	 */
	public function get_name()
	{
		$locale = get_field('ac_localization', 'option');

		if (empty($locale)) {
			return 'Account Centre';
		}

		// Check if returned value is a valid and allowed slug
		if (!in_array($locale, ['account-centre', 'account-center'])) {
			return 'Account Centre';
		}

		// Check if we have center in the slug
		if (str_contains($locale, 'center')) {
			return 'Account Center';
		}

		return 'Account Centre';
	}
}
