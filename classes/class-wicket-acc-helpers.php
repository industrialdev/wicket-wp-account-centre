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

		if (!isset($locale) || empty($locale)) {
			return WICKET_ACC_SLUG;
		}

		// WPML enabled?
		if (function_exists('icl_get_languages')) {
			global $sitepress;
			$current_language = $sitepress->get_current_language();

			if (isset($this->acc_slugs[$current_language])) {
				return $this->acc_slugs[$current_language];
			}
		}

		return $locale['value'];
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

	/**
	 * Get language
	 * If WPML is not installed, return 'en'
	 *
	 * @return string
	 */
	public function get_language()
	{
		global $sitepress;

		if (!isset($sitepress)) {
			return 'en';
		}

		$lang = $sitepress->get_current_language();

		return $lang;
	}
}
