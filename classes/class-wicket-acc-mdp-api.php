<?php

namespace WicketAcc;

use Exception;
use Wicket\Client;

// No direct access
defined('ABSPATH') || exit;

/**
 * Class MdpApi
 * To retrieve data from the MDP API
 *
 * @package WicketAcc
 */
class MdpApi
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Initialize the API client
	 */
	protected function init_client()
	{
		try {
			if (!class_exists('\Wicket\Client')) {
				return FALSE;
			}

			$wicket_settings = $this->mdp_get_settings();

			$client = new Client($app_key = '', $wicket_settings['jwt'], $wicket_settings['api_endpoint']);

			$client->authorize($wicket_settings['person_id']);
		} catch (Exception $e) {
			return false;
		}

		return $client;
	}

	/**
	 * Get option
	 */
	protected function wt_get_option($key, $default = null)
	{
		$options = get_option('wicket_settings', []);

		return $options[$key] ?? $default;
	}

	/**
	 * Get Wicket MDP settings
	 */
	protected function mdp_get_settings($environment = null)
	{
		$settings    = [];
		$environment = $this->wt_get_option('wicket_admin_settings_environment');

		switch ($environment) {
			case 'prod':
				$settings['api_endpoint'] = wicket_get_option('wicket_admin_settings_prod_api_endpoint');
				$settings['jwt'] = wicket_get_option('wicket_admin_settings_prod_secret_key');
				$settings['person_id'] = wicket_get_option('wicket_admin_settings_prod_person_id');
				$settings['parent_org'] = wicket_get_option('wicket_admin_settings_prod_parent_org');
				$settings['wicket_admin'] = wicket_get_option('wicket_admin_settings_prod_wicket_admin');
				break;
			case 'stage':
				$settings['api_endpoint'] = wicket_get_option('wicket_admin_settings_stage_api_endpoint');
				$settings['jwt'] = wicket_get_option('wicket_admin_settings_stage_secret_key');
				$settings['person_id'] = wicket_get_option('wicket_admin_settings_stage_person_id');
				$settings['parent_org'] = wicket_get_option('wicket_admin_settings_stage_parent_org');
				$settings['wicket_admin'] = wicket_get_option('wicket_admin_settings_stage_wicket_admin');
				break;
		}

		return $settings;
	}

	/**
	 * Get current person UUID
	 */
	protected function mdp_get_current_person_uuid()
	{
		// Get the SDK client from the wicket module.
		if (function_exists('wicket_api_client')) {
			$person_id = wp_get_current_user()->user_login;

			return $person_id;
		}

		return false;
	}

	/**
	 * Get current user's touchpoints
	 *
	 * @param string $service_id
	 *
	 * @return array
	 */
	protected function mdp_get_current_user_touchpoints($service_id)
	{
		$client    = $this->init_client();
		$person_id = $this->mdp_get_current_person_uuid();

		try {
			$touchpoints = $client->get("people/$person_id/touchpoints?page[size]=100&filter[service_id]=$service_id", ['json']);

			return $touchpoints;
		} catch (Exception $e) {
			$errors = json_decode($e->getResponse()->getBody())->errors;
		}

		return false;
	}
}
