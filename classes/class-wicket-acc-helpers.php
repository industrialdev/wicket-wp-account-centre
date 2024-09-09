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

	/**
	 * Get global banner page ID
	 * from slug: acc_global-headerbanner
	 * CPT: my-account
	 *
	 * @return int
	 */
	public function get_global_headerbanner_page_id()
	{
		$page    = get_page_by_path('acc_global-headerbanner', OBJECT, 'my-account');
		$page_id = $page->ID;

		// Is WPML enabled?
		if (function_exists('icl_get_languages')) {
			$page_id = apply_filters('wpml_object_id', $page_id, 'my-account', true, $this->get_language());
		}

		return $page_id;
	}

	/**
	 * Get Wicket ACC sidebar template
	 *
	 * @return string
	 */
	public function render_acc_sidebar()
	{
		$user_template    = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/sidebar.php';
		$plugin_template  = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/sidebar.php';
		$sidebar_template = false;

		if (file_exists($user_template)) {
			$sidebar_template = $user_template;
		} elseif (file_exists($plugin_template)) {
			$sidebar_template = $plugin_template;
		}

		if ($sidebar_template) {
			include_once $sidebar_template;
		}
	}
}
