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
	 * Get current person
	 */
	public function get_current_person()
	{
		$person_id = $this->get_current_person_uuid();

		if (!empty($person_id)) {
			return false;
		}

		$client = $this->init_client();

		try {
			$person = $client->people->fetch($person_id);
		} catch (Exception $e) {
			$errors = json_decode($e->getResponse()->getBody())->errors;

			return false;
		}

		return $person;
	}

	/**
	 * Get organization info
	 *
	 * @param string $org_uuid Organization UUID
	 * @param string $lang Optional. Default is 'en'. Can be: en, fr, es.
	 *
	 * @return array|false
	 */
	public function get_organization_info($org_uuid, $lang = 'en')
	{
		// Empty?
		if (empty($org_uuid) || empty($lang)) {
			return false;
		}

		// Empty defaults
		$org_parent_name    = '';
		$org_parent_uuid    = '';
		$org_type           = '';
		$org_type_nice_name = '';
		$org_address        = [];
		$org_phone          = [];
		$org_email          = [];

		// Get the organization
		$organization    = WACC()->MdpApi()->get_organization_by_uuid($org_uuid);
		$org_parent_uuid = $org_info['data']['relationships']['parent_organization']['data']['id'] ?? '';

		if (!empty($org_parent_uuid)) {
			$org_parent_info = WACC()->MdpApi()->get_organization_by_uuid($org_parent_uuid);
		}

		// Organization name
		$org_name = $organization['data']['attributes']['legal_name_' . $lang] ?? $organization['data']['attributes']['legal_name'];

		$org_description = $organization['data']['attributes']['description_' . $lang] ?? $organization['data']['attributes']['description'];

		// Organization parent name
		if (!empty($org_parent_info)) {
			$org_parent_name = $org_parent_info['data']['attributes']['legal_name_' . $lang] ?? $org_parent_info['data']['attributes']['legal_name'];
		}

		// Organization type
		if (!empty($organization['data']['attributes']['type'])) {
			$org_type = $organization['data']['attributes']['type'];

			$org_type_nice_name = ucwords(str_replace('_', ' ', $org_type));
		}

		// Organization status
		$org_status = $organization['data']['attributes']['status'] ?? '';

		// Organization address
		$client   = $this->init_client();
		$response = $client->get("organizations/$org_uuid/addresses");

		if (isset($response['data']) && !empty($response['data'])) {
			$org_address = $response['data'][0]['attributes'];
		}

		// Organization phone number
		$response = $client->get("organizations/$org_uuid/phones");

		if (isset($response['data']) && !empty($response['data'])) {
			$org_phone = $response['data'][0]['attributes'];
		}

		// Organization email
		$response = $client->get("organizations/$org_uuid/emails");

		if (isset($response['data']) && !empty($response['data'])) {
			$org_email = $response['data'][0]['attributes'];
		}

		$organization_info = [
			'org_uuid'           => $org_uuid,
			'org_name'           => $org_name,
			'org_description'    => $org_description,
			'org_parent_uuid'    => $org_parent_uuid,
			'org_parent_name'    => $org_parent_name,
			'org_type'           => $org_type,
			'org_type_nice_name' => $org_type_nice_name,
			'org_status'         => $org_status,
			'org_address'        => $org_address,
			'org_phone'          => $org_phone,
			'org_email'          => $org_email,
		];

		return $organization_info;
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

	/**
	 * Get organization by UUID
	 *
	 * @param string $uuid
	 *
	 * @return array|false
	 */
	public function get_organization_by_uuid($uuid = '')
	{
		if (empty($uuid)) {
			return false;
		}

		$client = $this->init_client();

		try {
			$organization = $client->get("organizations/$uuid");
		} catch (Exception $e) {
			$errors = json_decode($e->getResponse()->getBody())->errors;

			return false;
		}

		return $organization;
	}

	/**
	 * Get single organization membership by UUID
	 *
	 * @param string $uuid
	 *
	 * @return array|false
	 */
	public function get_organization_membership_by_uuid($uuid = '')
	{
		if ( empty( $uuid ) ) {
			return false;
		}

		$client = $this->init_client();

		try {
			$organization_membership = $client->get("organization_memberships/$uuid");
		} catch (Exception $e) {
			$errors = json_decode($e->getResponse()->getBody())->errors;

			return false;
		}

		return $organization_membership;
	}

	/**
	 * Get organization memberships
	 *
	 * @param string $org_uuid Organization UUID
	 *
	 * @return array|bool
	 */
	public function get_organization_memberships(string $org_uuid)
	{
		// Empty?
		if (empty($org_uuid)) {
			return false;
		}

		$client = $this->init_client();

		try {
			$org_memberships = $client->get("/organizations/$org_uuid/membership_entries?sort=-ends_at&include=membership");
		} catch (Exception $e) {
			$errors = json_decode($e->getResponse()->getBody())->errors;

			return false;
		}

		if (empty($org_memberships['data'])) {
			return false;
		}

		$memberships = [];

		foreach ($org_memberships['data'] as $org_membership) {
			$memberships[$org_membership['id']]['membership'] = $org_membership;

			foreach ($org_memberships['included'] as $included) {
				if ($included['id'] == $org_membership['relationships']['membership']['data']['id']) {
					$memberships[$org_membership['id']]['included'] = $included;
				}
			}
		}

		return $memberships;
	}
}
