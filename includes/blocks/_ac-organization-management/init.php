<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Block: Organization Management
 *
 **/
class Block_OrganizationManagement extends WicketAcc
{
	/**
	 * Constructor
	 */
	public function __construct(
		protected array $block            = [],
		protected bool $is_preview        = false,
		protected ?Blocks $blocks         = null,
		protected string $block_slug      = 'wicket-acc-organization-management',
		protected string $organization_id = '',
	) {
		$this->block        = $block;
		$this->is_preview   = $is_preview;
		$this->blocks       = $blocks ?? new Blocks();
		$this->organization_id = $_REQUEST['organization_id'] ?? '';

		// Display the block
		$this->display_block();
	}

	/**
	 * Display the block
	 *
	 * @return void
	 */
	protected function display_block()
	{
		// Process the form
		$process_form_one = $this->process_form_one();

		if ($process_form_one === false) {
		}

		if ($process_form_one === true) {
		}

		// If no GET or POST (REQUEST in general) data, render the landing
		if (!isset($_REQUEST['action'])) {
			$org_uuid     = $this->get_organization_uuid_on_init();
			$current_lang = WACC()->get_language();

			$args = [
				'organization_id'   => $org_uuid,
				'organization_info' => $this->get_organization_info($org_uuid, $current_lang),
			];

			if (empty($args['organization_info'])) {
				$args = [
					'block_name'   => __('Organization Management', 'wicket-acc'),
					'block_slug'   => $this->block_slug,
					'block_error'  => __('Organization info not found', 'wicket-acc'),
				];

				$this->blocks->render_template('error', $args);
				die();
			}

			$args['organization_memberships'] = WACC()->MdpApi()->get_organization_memberships($org_uuid);

			$this->blocks->render_template('organization-management', $args);
			die();
		}
	}

	/**
	 * Empty organization id on init
	 *
	 * @return void|string
	 */
	public function get_organization_uuid_on_init()
	{
		if (!empty($this->organization_id)) {
			return $this->organization_id;
		}

		$current_person = WACC()->MdpApi()->get_current_person();
		$orgs_ids       = [];

		foreach ($current_person->included() as $included) {
			// Warning fix
			if (!isset($included['attributes']['name'])) {
				$included['attributes']['name'] = '';
			}

			// Roles
			$roles = $included['attributes']['assignable_role_names'] ?? [];

			if (
				$included['type'] == 'roles' && stristr($included['attributes']['name'], 'owner')
				|| stristr($included['attributes']['name'], 'roster')
				|| stristr($included['attributes']['name'], 'membership_manager')
				|| isset(
					$included['attributes']['assignable_role_names']
				) && in_array('membership_manager', $roles)
			) {
				if (isset($included['relationships']['resource']['data']['id']) && $included['relationships']['resource']['data']['type'] == 'organizations') {
					$orgs_ids[] = $included['relationships']['resource']['data']['id'];
				}
			}
		}

		// Remove duplicates
		$orgs_ids = array_unique($orgs_ids);

		// Only one organization?
		if (count($orgs_ids) == 1) {
			$this->organization_id = $orgs_ids[0];

			return $orgs_ids[0];
		}

		return $orgs_ids;
	}

	/**
	 * Get the organization info
	 *
	 * @param string $org_uuid Organization UUID
	 * @param string $lang Language
	 *
	 * @return array|bool
	 */
	public function get_organization_info(string $org_uuid, string $lang)
	{
		// Empty?
		if (empty($org_uuid) || empty($lang)) {
			return false;
		}

		$org_info = WACC()->MdpApi()->get_organization_info($org_uuid, $lang);

		return $org_info;
	}

	/**
	 * Process the form and save the profile picture
	 *
	 * @return bool
	 */
	public function process_form_one()
	{
		if (is_admin()) {
			return;
		}

		// No data? no action?
		if (!isset($_POST['action']) || $_POST['action'] !== 'wicket-acc-roster-management') {
			return;
		}

		$form = $_POST;

		// Check nonce
		if (!wp_verify_nonce(sanitize_text_field(wp_unslash($form['nonce'])), 'wicket-acc-roster-management')) {
			return false;
		}
	}
}
