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
	public function init_client()
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
	public function get_option($key, $default = null)
	{
		$options = get_option('wicket_settings', []);

		return $options[$key] ?? $default;
	}

	/**
	 * Get Wicket MDP settings
	 */
	public function mdp_get_settings($environment = null)
	{
		$settings    = [];
		$environment = $this->get_option('wicket_admin_settings_environment');

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
	public function get_current_person_uuid()
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
	 * @param string $person_id Optional. If not provided, the current person ID will be used. Use this to debug with a specific person.
	 *
	 * @return array
	 */
	public function get_current_user_touchpoints($service_id, $person_id = null)
	{
		$client    = $this->init_client();
		$person_id = $person_id ?? $this->get_current_person_uuid();

		try {
			$touchpoints = $client->get("people/$person_id/touchpoints?page[size]=100&filter[service_id]=$service_id", ['json']);

			return $touchpoints;
		} catch (Exception $e) {
			$errors = json_decode($e->getResponse()->getBody())->errors;
		}

		return false;
	}

	/**
	 * Get or create a touchpoint service ID.
	 *
	 * This function retrieves an existing service ID by the given service name.
	 * If the service does not exist, it creates a new service with the specified
	 * name and description and returns the newly created service ID.
	 *
	 * Example usage:
	 * ```php
	 * $service_id = get_create_touchpoint_service_id('Events Calendar', 'Events from the website');
	 * write_touchpoint($params, $service_id);
	 * ```
	 *
	 * @param string $service_name        The name of the service.
	 * @param string $service_description The description of the service. Default is 'Custom from WP'.
	 * @return string|false               The service ID if successful, or false on failure.
	 */
	public function create_touchpoint_service_id($service_name, $service_description = 'Custom from WP')
	{
		$client = $this->init_client();

		// check for existing service, return service ID
		$existing_services = $client->get("services?filter[name_eq]=$service_name");
		$existing_service = isset($existing_services['data']) && !empty($existing_services['data']) ? $existing_services['data'][0]['id'] : '';

		if ($existing_service) {
			return $existing_service;
		}

		// if no existing service, create one and return service ID
		$payload['data']['attributes'] = [
			'name' => $service_name,
			'description' => $service_description,
			'status' => 'active',
			'integration_type' => "custom",
		];

		try {
			$service = $client->post("/services", ['json' => $payload]);

			return $service['data']['id'];
		} catch (Exception $e) {
			$errors = json_decode($e->getResponse()->getBody())->errors;
		}

		return false;
	}
}
