<?php

namespace WicketAcc;

use Exception;

// No direct access
defined('ABSPATH') || exit;

/**
 * Profile for Wicket Account Centre
 *
 * Manage all actions of user's profile on WordPress.
 */

class OrgManagement extends WicketAcc
{
    private $org_uuid        = '';
    private $org_parent_uuid = '';
    private $org_pages_slugs = [
        'index',
        'members',
        'profile',
        'roster',
    ];

    /**
     * Constructor.
     */
    public function __construct() {}

    /**
     * Get an Org Management page URL, by slug
     *
     * @param string $slug The page slug
     *
     * @return string The URL
     */
    public function get_orgman_page_url($slug = '')
    {
        if (empty($slug)) {
            return home_url();
        }

        // Valid slug?
        if (!in_array($slug, $this->org_pages_slugs)) {
            return home_url();
        }

        $page_id = get_field('acc_page_orgman-' . sanitize_text_field($slug), 'option');

        if (empty($page_id)) {
            return home_url();
        }

        return get_permalink($page_id);
    }

    /**
     * Add or update a person to the membership
     *
     * @param array $args The arguments
     *
     * @return array|void The response: success (bool), error (bool), message (string). Void: url redirect
     */
    public function add_or_update_member($args)
    {
        if (empty($args)) {
            return false;
        }

        global $wp;

        $client               = wicket_api_client();
        $action               = isset($args['action']) ? sanitize_text_field($args['action']) : '';
        $lang                 = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';
        $person_email         = isset($args['person_email']) ? sanitize_email($args['person_email']) : '';
        $person_role          = $args['person_role'] ?? '';
        $person_relationship  = isset($args['person_relationship']) ? sanitize_text_field($args['person_relationship']) : '';
        $org_id               = isset($args['org_id']) ? sanitize_text_field($args['org_id']) : '';
        $organization_obj     = wicket_get_organization($org_id);
        $organization_name    = $organization_obj['data']['attributes']['legal_name_' . $lang];
        $membership_id        = isset($args['membership_id']) ? sanitize_text_field($args['membership_id']) : '';
        $org_membership       = $client->get("organization_memberships/$membership_id?include=membership");
        $included_id          = isset($args['included_id']) ? sanitize_text_field($args['included_id']) : '';
        $person_first_name    = isset($args['first_name']) ? sanitize_text_field($args['first_name']) : '';
        $person_last_name     = isset($args['last_name']) ? sanitize_text_field($args['last_name']) : '';
        $person_current_roles = isset($args['person_current_roles']) ? sanitize_text_field($args['person_current_roles']) : '';
        $update_role_user_id  = isset($args['update_role_user_id']) ? sanitize_text_field($args['update_role_user_id']) : '';

        // Check if the user already exists in Wicket, by email
        $person = $this->get_person_by_email($person_email);

        // Adding member and no relationship?
        if ($action == 'add_member_to_roster' && empty($person_relationship)) {
            return [
                'success' => false,
                'error'   => true,
                'message' => __('You must select a relationship type', 'wicket'),
            ];
        }

        // Person does not exist on MDP, create it
        if (!isset($person['id']) || empty($person['id'])) {
            $person = $this->create_person(trim($_POST['given_name']), trim($_POST['family_name']), $person_email);

            // Get person ID
            $person_uuid = $person['data']['id'];
        } else {
            // Get person ID
            $person_uuid = $person['id'];
        }

        // This is the only data we need from org on this call. Date format: ISO8604
        $start_at = $organization_obj['data']['attributes']['starts_at'] = (new \DateTime(date('c'), wp_timezone()))->format('c');
        //$ends_at  = $organization_obj['data']['attributes']['ends_at']   = (new \DateTime(date('c'), wp_timezone()))->modify('+1 year')->format('c');
        // PAO: no end date
        $ends_at = null;

        // PAO: no end date
        $org_membership['data']['attributes']['ends_at'] = null;

        // Assign this person to Org Membership
        $mdp_response = wicket_assign_person_to_org_membership(
            $person_uuid,
            $included_id,
            $membership_id,
            $org_membership
        );

        // If empty or null response, show error message
        if (empty($mdp_response) || is_null($mdp_response)) {
            $payload_log = [$person_uuid, $included_id, $membership_id, $org_membership];

            wc_get_logger()->debug('add_or_update_member > wicket_assign_person_to_org_membership: ' . print_r($mdp_response, true) . '<br/>With payload: ' . print_r($payload_log, true), ['source' => 'wicket_org_management']);

            return [
                'success' => false,
                'error'  => true,
                'message' => __("Error assigning person to membership", 'wicket'),
            ];
        }

        // Create or get the user on WP
        $user_wp = $this->create_wp_user($person_uuid, $person_first_name, $person_last_name, $person_email);

        if ($user_wp === false) {
            return [
                'success' => false,
                'error'  => true,
                'message' => __("Error creating user on WP", 'wicket'),
            ];
        }

        // Other roles
        if ($action == 'add_member_to_roster') {
            if (!is_array($person_role)) {
                $person_role = [$person_role];
            }

            foreach ($person_role as $role) {
                wicket_assign_role($person_uuid, $role, $org_id);
            }

            $this->assign_wp_roles($person_uuid, $person_role);
        } else {
            if (!is_array($person_current_roles)) {
                $person_current_roles = [$person_current_roles];
            }
            // action == update_member_on_roster
            wicket_remove_role($update_role_user_id, $person_current_roles);
            wicket_assign_role($update_role_user_id, $person_role, $org_id);
            $this->assign_wp_roles($update_role_user_id, $person_role);
        }

        // Deal with the relationship on MDP
        $payload = [
            'data' => [
                'attributes' => [
                    'connection_type'   => 'person_to_organization',
                    'custom_data_field' => null,
                    'description'       => null,
                    'ends_at'           => $ends_at,
                    'starts_at'         => $start_at,
                    'tags'              => [],
                    'type'              => $person_relationship,
                ],
                'id' => null,
                'relationships' => [
                    'from' => [
                        'data' => [
                            'id'   => $person_uuid,
                            'type' => 'people',
                        ],
                    ],
                    'to' => [
                        'data' => [
                            'id' => $org_id,
                            'meta' => [
                                'ancestry_depth' => 0,
                                'can_manage'     => true,
                                'can_update'     => true,
                            ],
                            'type' => 'organizations',
                        ],
                    ],
                ],
                'type' => 'connections',
            ],
        ];

        // Create the connection
        $response_relationship = wicket_create_connection($payload);

        // If empty or null response, show error message
        if (empty($response_relationship) || is_null($response_relationship)) {
            wc_get_logger()->debug('add_or_update_member > wicket_create_connection: ' . print_r($response_relationship, true) . '<br/>With payload: ' . print_r($payload, true), ['source' => 'wicket_org_management']);

            return [
                'success' => false,
                'error'  => true,
                'message' => __("Error assigning person to membership", 'wicket'),
            ];
        }

        // Touchpoints
        if ($action == 'update_member_on_roster') {
            $params = [
                'person_id' => $person_uuid,
                'action'    => 'Organization membership updated',
                'details'   => "Person's role was set to '$_POST[role]' on " . date('c', time()),
                'data'      => ['org_id' => $org_id],
            ];

            $service_id = get_create_touchpoint_service_id('Roster Management', 'Updated member role');

            write_touchpoint($params, $service_id);
        }

        if ($action == 'add_member_to_roster') {
            $service_id        = get_create_touchpoint_service_id('Roster Management', 'Added member from Roster Management front.');

            $params = [
                'person_id' => $person_uuid,
                'action'    => 'Organization membership assigned',
                'details'   => "Person was assigned to a membership under '$organization_name' on " . date('c', time()),
                'data'      => ['org_id' => $org_id],
            ];

            write_touchpoint($params, $service_id);
        }

        // We need $user->data->user_login = $person_uuid for the helper to work (even outside WP context)
        $user = (object) [
            'data' => (object) [
                'user_login' => $person_uuid,
            ],
        ];

        // Send email to user letting them know of the assignment
        send_person_to_team_assignment_email($user, $org_id);

        // Redirect user
        $url_redirect = home_url(add_query_arg([], $wp->request));

        header("Location: $url_redirect?org_id=$org_id&membership_id=$membership_id&included_id=$included_id&success");

        die();
    }

    /**
     * Unassign person from an org membership/team
     *
     * @param array $args The arguments
     *
     * @return array|void The response: success (bool), error (bool), message (string). Void: url redirect
     */
    public function unassign_person_uuid($args = [])
    {
        /*
    Data from URL:

    $unassign_url = home_url(add_query_arg(array(), $wp->request)) . '/?org_id=' . $org_id . '&membership_id=' . $membership_id . '&person_membership_uuid=' . $person_membership_uuid . '&unassign_person_uuid=' . $person_uuid . '&person_relationship=' . $person_relationship_slug . '&person_connection_id=' . $person_connection_id;
    */

        global $wp;

        $lang                   = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';
        $unassign_person_uuid   = isset($args['unassign_person_uuid']) ? sanitize_text_field($args['unassign_person_uuid']) : '';
        $person_uuid            = isset($args['person_uuid']) ? sanitize_text_field($args['person_uuid']) : '';
        $person_email           = base64_decode(urldecode($_GET['email']));
        $person_relationship    = isset($args['person_relationship']) ? sanitize_text_field($args['person_relationship']) : '';
        $person_connection_id   = isset($args['person_connection_id']) ? sanitize_text_field($args['person_connection_id']) : '';
        $org_id                 = isset($args['org_id']) ? sanitize_text_field($args['org_id']) : '';
        $membership_id          = isset($args['membership_id']) ? sanitize_text_field($args['membership_id']) : '';
        $included_id            = isset($args['included_id']) ? sanitize_text_field($args['included_id']) : '';
        $allowed_roles          = $args['allowed_roles'] ?? [];
        $allowed_relationships  = $args['allowed_relationships'] ?? [];
        $person_membership_uuid = isset($args['person_membership_uuid']) ? sanitize_text_field($args['person_membership_uuid']) : '';

        // Decode base64 params
        $person_relationship = base64_decode(urldecode($person_relationship));

        $missing_args = [];
        if (!$person_email) {
            $missing_args[] = 'person_email';
        }
        if (!$person_uuid) {
            $missing_args[] = 'person_uuid';
        }
        if (!$person_relationship) {
            $missing_args[] = 'person_relationship';
        }
        if (!$person_connection_id) {
            $missing_args[] = 'person_connection_id';
        }
        if (!$org_id) {
            $missing_args[] = 'org_id';
        }
        if (!$membership_id) {
            $missing_args[] = 'membership_id';
        }
        if (!$included_id) {
            $missing_args[] = 'included_id';
        }
        if (!$unassign_person_uuid) {
            $missing_args[] = 'unassign_person_uuid';
        }

        if (!empty($missing_args)) {
            return [
                'success' => false,
                'error'  => true,
                'message' => sprintf(__('Missing arguments: %s', 'wicket'), implode(', ', $missing_args)),
            ];
        }

        $organization_obj       = wicket_get_organization($org_id);
        $organization_name      = $organization_obj['data']['attributes']['legal_name_' . $lang];
        $person                 = wicket_get_person_by_id($person_uuid);

        // Don't allow removing membership_owner
        if (in_array('membership_owner', $person->role_names)) {
            return [
                'success' => false,
                'error'  => true,
                'message' => __('Error: You cannot remove a membership owner from the organization', 'wicket'),
            ];
        }

        // Has current relationship = president
        if (isset($person_relationship) && $person_relationship == 'president') {
            return [
                'success' => false,
                'error'  => true,
                'message' => __('Error: You cannot remove a president from the organization', 'wicket'),
            ];
        }

        // Remove all roles
        foreach ($allowed_roles as $dropdown_key => $dropdown_label) {
            wicket_remove_role($unassign_person_uuid, $dropdown_key);
        }

        // From WP too
        $this->remove_wp_roles($unassign_person_uuid, $allowed_roles);

        // Remove membership
        // wicket_unassign_person_from_org_membership($_GET['person_membership_uuid']);
        // PAO: set membership pend date to today
        $end_date = date('Y-m-d');
        $response_membership = $this->update_person_membership_date($person_membership_uuid, [
            'ends_at' => $end_date,
        ]);

        // Remove connection (relationship)
        //$response_connection = wicket_remove_connection($person_connection_id);
        // PAO: We won't remove the connection. We will set the end_date to today's date
        $response_relationship = wicket_set_connection_start_end_dates($person_connection_id, $end_date);

        if (is_wp_error($response_membership) || is_wp_error($response_relationship)) {
            return [
                'success' => false,
                'error'  => true,
                'message' => __('Error updating membership or connection end date', 'wicket'),
            ];
        }

        // Touchpoint
        $params = [
            'person_id' => $unassign_person_uuid,
            'action'    => 'Organization membership removed',
            'details'   => "Persons membership was removed from '$organization_name' on " . date('c', time()),
            'data'      => ['org_id' => $org_id],
        ];

        $service_id = get_create_touchpoint_service_id('Roster Manage', 'Removed member from organization');

        write_touchpoint($params, $service_id);

        // Redirect user
        $url_redirect = home_url(add_query_arg([], $wp->request));

        header("Location: $url_redirect?org_id=$org_id&membership_id=$membership_id&included_id=$included_id");

        die();
    }

    /**
     * Update person roles
     *
     * @param array $args The arguments
     *
     * @return array|void The response: success (bool), error (bool), message (string). Void: url redirect
     */
    public function update_role($args)
    {
        if (empty($args)) {
            return [
                'success' => false,
                'error'  => true,
                'message' => __('Missing arguments', 'wicket'),
            ];
        }

        global $wp;

        $person_current_roles    = isset($args['person_current_roles']) ? sanitize_text_field($args['person_current_roles']) : '';
        $new_role                = $args['role'] ?? '';
        $org_id                  = isset($args['org_id']) ? sanitize_text_field($args['org_id']) : '';
        $update_role_person_uuid = isset($args['update_role_person_uuid']) ? sanitize_text_field($args['update_role_person_uuid']) : '';
        $membership_id           = isset($args['membership_id']) ? sanitize_text_field($args['membership_id']) : '';
        $included_id             = isset($args['included_id']) ? sanitize_text_field($args['included_id']) : '';

        // Remove current role(s), wicket_remove_role(). Get current role.
        if (str_contains($person_current_roles, ',')) {
            $person_current_roles = explode(',', $person_current_roles);
        } else {
            $person_current_roles = [$person_current_roles];
        }

        if (!is_array($new_role)) {
            $new_role = [$new_role];
        }

        // PAO: We should never remove the user role 'member'
        $roles_to_remove = array_diff($person_current_roles, ['member']);

        // PAO: new role should always be 'member'
        $new_role = array_merge($new_role, ['member']);

        // Remove roles
        foreach ($roles_to_remove as $role_remove) {
            wicket_remove_role($update_role_person_uuid, $role_remove);
        }

        // From WP too
        $this->remove_wp_roles($update_role_person_uuid, $roles_to_remove);

        // Add new roles
        foreach ($new_role as $role_add) {
            wicket_assign_role($update_role_person_uuid, $role_add, $org_id);
        }

        // From WP too
        $this->assign_wp_roles($update_role_person_uuid, $new_role);

        // Touchpoint
        $params = [
            'person_id' => $_GET['update_role_person_uuid'],
            'action'    => 'Organization member updated',
            'details'   => "Person's role was updated from '$person_current_roles' to '$new_role' on " . date('c', time()),
            'data'      => ['org_id' => $org_id],
        ];

        $service_id = get_create_touchpoint_service_id('Roster Manage', 'Updated member role');

        write_touchpoint($params, $service_id);

        // Redirect user
        $url_redirect = home_url(add_query_arg([], $wp->request));

        header("Location: $url_redirect?org_id=$org_id&membership_id=$membership_id&included_id=$included_id");

        die();
    }

    /**
     * Returns the Org membership UUID for the given organization UUID
     *
     * @param array $data The JSON API response data
     */
    public function get_membership_uuid_by_organization_uuid($org_id = '')
    {
        if (!function_exists('wicket_get_current_person_memberships')) {
            return null;
        }

        if (empty($org_id)) {
            return null;
        }

        $memberships = wicket_get_current_person_memberships();

        // Iterate over the included array, and find relationships>organization>data>id that matches the given org_id
        foreach ($memberships['included'] as $included) {
            if ($included['type'] === 'organization_memberships') {
                if (
                    isset($included['relationships']['organization']['data']['id']) &&
                    $included['relationships']['organization']['data']['id'] === $org_id
                ) {
                    // Get relationships>membership>data>id
                    if (isset($included['relationships']['membership']['data']['id'])) {
                        return $included['relationships']['membership']['data']['id'];
                    } else {
                        return null;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Return the membership ID for this person and organization
     *
     * @param string $organization_uuid The organization UUID
     *
     * @return string|bool The membership ID or false if not found
     */
    public function get_current_person_memberships_by_organization($organization_uuid = '')
    {
        if (!$organization_uuid) {
            return false;
        }

        $memberships = wicket_get_current_person_memberships();


        if (empty($memberships['included']) || !is_array($memberships['included'])) {
            return false;
        }

        // Iterate over the included array to find the organization_membership related to the given org UUID
        foreach ($memberships['included'] as $included) {
            if ($included['type'] === 'organization_memberships') {
                // Check if the organization UUID matches
                if (
                    isset($included['relationships']['organization']['data']['id']) &&
                    $included['relationships']['organization']['data']['id'] === $organization_uuid
                ) {
                    // Return the UUID of the organization_membership itself
                    return $included['id'];
                }
            }
        }

        return false;
    }

    /**
     * Return a person object by email. If the email is not found, return false.
     *
     * @param string $email Email address of the person
     *
     * @return object|bool Person object or false if not found
     */
    public function get_person_by_email($email = '')
    {
        if (!$email) {
            return false;
        }

        $client = WACC()->MdpApi->init_client();
        $person = $client->get('/people?filter[emails_primary_eq]=true&filter[emails_address_eq]=' . urlencode($email));

        // Return the first person if found
        if (isset($person['data'][0])) {
            return $person['data'][0];
        }

        return false;
    }

    /**
     * Return Org membership_uuid information
     */
    public function get_org_membership_data($membership_uuid = '')
    {
        if (!$membership_uuid) {
            return false;
        }

        $client   = wicket_api_client();
        $response = $client->get('/organization_memberships/' . $membership_uuid);

        // Return the count of assigned people if found
        if (isset($response['data'])) {
            return $response['data'];
        }

        return false;
    }

    /**
     * Returns an array of all the organization memberships person
     *
     * @param string $membership_uuid The membership UUID
     *
     * @return array|bool The organization memberships or false if not found
     */
    public function get_org_membership_members($membership_uuid = '')
    {
        if (!$membership_uuid) {
            return false;
        }

        $client = wicket_api_client();
        $response = $client->get('/organization_memberships/' . $membership_uuid . '/person_memberships');

        // Return the count of max_assignments if found
        if (isset($response['data'])) {
            return $response['data'];
        }

        return false;
    }

    /**
     * Returns an array of all the organization relationships (person only)
     *
     * Different from the base plugin helper:
     *  - It returns the relationships of the organization, not the person
     *
     * @param string $org_uuid The organization UUID
     *
     * @return array|bool The organization relationships or false if not found
     */
    public function get_org_relationships_person($org_uuid = '')
    {
        if (!$org_uuid) {
            return false;
        }

        $client   = wicket_api_client();
        $response = $client->get('/organizations/' . $org_uuid . '/connections?filter%5Bconnection_type_eq%5D=person_to_organization');

        // Return the count of max_assignments if found
        if (isset($response['data'])) {
            return $response['data'];
        }

        return false;
    }

    /**
     * Returns an array of all the users roles for an organization
     *
     * Different from the base plugin helper:
     *  - It returns the roles of the organization, not the person
     *
     * @param string $person_uuid The organization UUID

     * @param string $org_uuid The organization UUID
     *
     * @return array|bool The users org roles or false if not found
     */
    public function get_org_roles_person($person_uuid = '', $org_uuid = '')
    {
        if (!$person_uuid || !$org_uuid) {
            return false;
        }

        $client   = wicket_api_client();
        $response = $client->get('/people/' . $person_uuid . '/roles');

        // Return the count of max_assignments if found
        if (isset($response['data'])) {
            foreach ($response['data'] as $role) {
                if (!empty($role['relationships']['resource']['data']['id']) && $role['relationships']['resource']['data']['id'] == $org_uuid) {
                    $users_roles[] = $role['attributes']['name'];
                }
            }

            return $users_roles ?? [];
        }

        return false;
    }

    /**
     * Get organization info, extended
     *
     * Different from the base plugin helper:
     *  - It returns the main address, phone, and email
     *
     * @param string $org_uuid The organization UUID
     * @param string $lang The language code
     *
     * @return array|bool The organization basic info or false if not found
     */
    public function get_organization_info_extended($org_uuid = '', $lang = '')
    {
        if (!$org_uuid || !$lang) {
            return false;
        }

        $org_info = wicket_get_organization_basic_info($org_uuid);

        // Get Org main address
        $client = wicket_api_client();
        $response = $client->get('/organizations/' . $org_uuid . '/addresses');

        // Add the main address to the org_info
        if (isset($response['data']) && !empty($response['data'])) {
            $org_info['org_meta']['main_address'] = $response['data'][0]['attributes'];
        } else {
            $org_info['org_meta']['main_address'] = [];
        }

        // Get Org telephone number
        $client = wicket_api_client();
        $response = $client->get('/organizations/' . $org_uuid . '/phones');

        // Add the main address to the org_info
        if (isset($response['data']) && !empty($response['data'])) {
            $org_info['org_meta']['main_phone'] = $response['data'][0]['attributes'];
        } else {
            $org_info['org_meta']['main_phone'] = [];
        }

        // Get Org email address
        $client = wicket_api_client();
        $response = $client->get('/organizations/' . $org_uuid . '/emails');

        // Add the main address to the org_info
        if (isset($response['data']) && !empty($response['data'])) {
            $org_info['org_meta']['main_email'] = $response['data'][0]['attributes'];
        } else {
            $org_info['org_meta']['main_email'] = [];
        }

        return $org_info;
    }

    /**
     * Sends an email to the user with instructions on how to access their team profile.
     *
     * @param WP_User $user The user object to send the email to.
     * @param int $org_id The organization ID to send the email with the correct branding.
     *
     * @return void
     */
    public function send_person_to_team_assignment_email($user, $org_id)
    {
        $org       = wicket_get_organization($org_id);
        $lang      = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';
        $person    = wicket_get_person_by_id($user->data->user_login);
        $home_url  = get_home_url();
        $site_name = get_bloginfo('name');
        $site_url  = get_site_url();
        $base_domain = parse_url($site_url, PHP_URL_HOST);

        if ($org) {
            $organization_name = $org['data']['attributes']['legal_name_' . $lang];
        } else {
            $organization_name = $site_name;
        }

        $to         = $person->primary_email_address;
        $first_name = $person->given_name;
        $last_name  = $person->family_name;
        $subject    = "Welcome to " . $organization_name;

        $body = "Hi $first_name, <br><br>
	You have been assigned a membership as part of $organization_name.
	<br>
	<br>
	Visit <a href='$home_url'>$site_name</a> and login to complete your profile and access your resources.
	<br>
	<br>
	Thank you,
	<br>
	<br>
	$organization_name";

        $headers   = ['Content-Type: text/html; charset=UTF-8'];
        $headers[] = 'From: ' . $organization_name . ' <no-reply@' . $base_domain . '>';

        wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Get person connections by ID
     *
     * @param string $uuid The person UUID
     *
     * @return array|bool The person connections or false if not found
     */
    public function get_person_connections_by_id($uuid)
    {
        $client = wicket_api_client();

        $connections = $client->get('people/' . $uuid . '/connections?filter%5Bconnection_type_eq%5D=all&sort=-created_at');

        return $connections;
    }

    /**
     * Create a new user on WordPress
     *
     * @param string $uuid The UUID of the user, to be used as username
     * @param string $first_name The first name of the user
     * @param string $last_name The last name of the user
     * @param string $email The email address of the user
     *
     * @return array|bool The user object or false if not found
     */
    public function create_wp_user($uuid, $first_name, $last_name, $email)
    {
        if (empty($uuid) || empty($first_name) || empty($last_name) || empty($email)) {
            return false;
        }

        $user = get_user_by('login', $uuid);

        if ($user) {
            return $user;
        }

        $user = get_user_by('email', $email);

        if ($user) {
            return $user;
        }

        // Create the user
        $username = sanitize_user($uuid);
        $password = wp_generate_password(12, false);
        $user_id  = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return false;
        }

        return true;
    }

    /**
     * Assign WP roles to a user
     *
     * @param string $user The person UUID (login) or email address
     * @param array $roles The roles to assign. Can be an array or a string
     *
     * @return bool True if successful, false if not
     */
    public function assign_wp_roles($person_uuid, $roles)
    {
        if (empty($person_uuid) || empty($roles)) {
            return false;
        }

        $user = get_user_by('login', $person_uuid) ?? get_user_by('email', $person_uuid);

        if (!$user || !is_object($user)) {
            return false;
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                $user->add_role($role);
            }
        } else {
            $user->add_role($roles);
        }

        return true;
    }

    /**
     * Remove WP roles from a user
     *
     * @param string $user The person UUID (login) or email address
     * @param array $roles The roles to remove. Can be an array or a string
     *
     * @return bool True if successful, false if not
     */
    public function remove_wp_roles($person_uuid, $roles)
    {
        if (empty($person_uuid) || empty($roles)) {
            return false;
        }

        $user = get_user_by('login', $person_uuid) ?? get_user_by('email', $person_uuid);

        if (!$user || !is_object($user)) {
            return false;
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                $user->remove_role($role);
            }
        } else {
            $user->remove_role($roles);
        }

        return true;
    }

    /**
     * Role checker
     *
     * @param array|string $roles array or string of roles to check
     * @param bool $all_true Default: false. If true, all roles must be in the user's roles. If false, at least one role must be in the user's roles
     *
     * @return bool True if condition met, false if not
     */
    public function role_check($roles, $all_true = false)
    {
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        $user = wp_get_current_user();

        // Admins can do anything
        if (in_array('administrator', $user->roles)) {
            return true;
        }

        if ($all_true) {
            // Match all roles received
            $match_all = true;

            foreach ($roles as $role) {
                if (!in_array($role, $user->roles)) {
                    $match_all = false;
                    break;
                }
            }

            return $match_all;
        } else {
            // Match any role received, at least one of them
            foreach ($roles as $role) {
                if (in_array($role, $user->roles)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Role check for pages
     *
     * @param array|string $roles array or string of roles to check
     * @param bool $all_true If true, all roles must be in the user's roles. If false, at least one role must be in the user's roles
     *
     * @return void
     */
    public function page_role_check($roles, $all_true = false)
    {
        $response = $this->role_check($roles, $all_true);

        if (!$response) {
            wp_die(__('You do not have permission to access this page', 'wicket'));
        }
    }

    /**
     * Update person membership dates
     *
     * Different from the base plugin helper:
     *  - Dates are optional.
     *  - Start date isn't fixed to today.
     *
     * @param string $membership_uuid The membership UUID
     * @param array $args The arguments: starts_at, ends_at, grace_period_days
     *
     * @return array|bool The response or false if not successful
     */
    public function update_person_membership_date($membership_uuid, $args = [])
    {
        if (empty($membership_uuid) || empty($args)) {
            return false;
        }

        $client = wicket_api_client();

        $starts_at         = isset($args['starts_at']) ? sanitize_text_field($args['starts_at']) : '';
        $ends_at           = isset($args['ends_at']) ? sanitize_text_field($args['ends_at']) : '';
        $grace_period_days = isset($args['grace_period_days']) ? sanitize_text_field($args['grace_period_days']) : false;

        // Empty start and end? We need at least one of this
        if (empty($starts_at) && empty($ends_at)) {
            return false;
        }

        // Build membership payload
        $payload = [
            'data' => [
                'type' => 'person_memberships',
                'attributes' => [],
            ],
        ];

        // Only if dates are received
        if (!empty($starts_at)) {
            $payload['data']['attributes']['starts_at'] = $starts_at;
        }

        if (!empty($ends_at)) {
            $payload['data']['attributes']['ends_at'] = $ends_at;
        }

        // Grace period days
        if ($grace_period_days !== false) {
            $payload['data']['attributes']['grace_period_days'] = $grace_period_days;
        }

        try {
            $response = $client->patch("/person_memberships/$membership_uuid", ['json' => $payload]);
        } catch (Exception $e) {
            $response = new \WP_Error('wicket_api_error', $e->getMessage());
        }

        return $response;
    }

    /**
     * Retrieves a person by its ID.
     *
     * @param string $person_id The person ID.
     *
     * @return array|false The person data or false if not found.
     */
    public function get_person_by_id($person_id = '')
    {
        if (!$person_id) {
            return false;
        }

        $person = wicket_get_person_by_id($person_id);

        if (!$person) {
            return false;
        }

        return $person;
    }

    /**
     * Retrieves the current person (logged in user).
     *
     * @return array|false The person data or false if not found.
     */
    public function get_current_person()
    {
        $person = wicket_current_person();

        if (!$person) {
            return false;
        }

        return $person;
    }

    /**
     * Creates a new person using the given name, last name and email.
     * If the person already exists (by email), the existing ID is returned.
     *
     * @param string $first_name The person name.
     * @param string $last_name  The person last name.
     * @param string $email      The person email.
     * @param array  $extras     An array with extra data to be saved in the person.
     *
     * @return string|false Person ID, false if the creation fails.
     */
    public function create_person($first_name, $last_name, $email, $extras = [])
    {
        if (!$first_name || !$last_name || !$email) {
            return false;
        }

        // Valid email?
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Does the person already exist?
        $person = $this->get_person_by_email($email);

        if (!$person) {
            $person = wicket_create_person(trim($first_name), trim($last_name), trim($email));
        }

        if (!$person) {
            return false;
        }

        $person_id = $person['id'] ?? $person['data']['id'];

        // Add extra data
        if (!empty($extras)) {
            $update_response = $this->update_person_profile_data($person_id, $extras);
        }

        return $person_id;
    }

    /**
     * Updates a person's profile data.
     *
     * Given a person UUID and an array of data to be updated,
     * this function sends a PATCH request to the Wicket API
     * to update the person's profile data.
     *
     * @param string $person_id The person UUID.
     * @param array  $data      An array of data to be updated.
     *
     * @return array|false The response from the Wicket API, false if the update fails.
     */
    public function update_person_profile_data($person_id, $data = [])
    {
        if (empty($person_id) || empty($data)) {
            return false;
        }

        $client = WACC()->MdpApi->init_client();

        /**
         * Payload to mimic:
         *
         * {"data":{"type":"people","id":"0eaa4b70-9922-4195-a67f-cc16d8e86037","attributes":{"given_name":"First03","additional_name":null,"family_name":"Last03","suffix":null,"honorific_suffix":null,"preferred_pronoun":null,"job_title":"CEO","job_level":"director"}}}
         *
         * Send as PATCH to /people/{UUID}
         */

        $data = [
            'data' => [
                'type'       => 'people',
                'id'         => $person_id,
                'attributes' => $data,
            ],
        ];

        try {
            $response = $client->patch("/people/$person_id", ['json' => $data]);
        } catch (Exception $e) {
            $response = new \WP_Error('wicket_api_error', $e->getMessage());
        }

        return $response;
    }

    /**
     * Retrieves all job levels from MDP.
     *
     * @return array An associative array where the key is the job level slug and the value is an array with 'slug' and 'name' keys.
     */
    public function get_job_levels()
    {
        $client = WACC()->MdpApi->init_client();

        $job_levels_resource_types = $client->get('/resource_types?filter[entity_type_code_eq]=shared_job_level');
        $job_levels = [];

        foreach ($job_levels_resource_types['data'] as $resource_type) {
            $job_levels[$resource_type['attributes']['slug']] = [
                'slug' => $resource_type['attributes']['slug'],
                'name' => $resource_type['attributes']['name'],
            ];
        }

        return $job_levels;
    }

    /**
     * Create a new connection in the API.
     *
     * @param array $payload The new connection properties.
     *
     * @return bool|array true on success,
     */
    public function create_connection($payload)
    {
        $client = WACC()->MdpApi->init_client();

        try {
            $client->post('connections', ['json' => $payload]);

            return true;
        } catch (\Exception $e) {
            $errors = json_decode($e->getResponse()->getBody())->errors;
            echo "<pre>";
            print_r($e->getMessage());
            echo "</pre>";

            echo "<pre>";
            print_r($errors);
            echo "</pre>";

            $response = [
                'error'   => true,
                'message' => $errors[0]->detail,
            ];

            return $response;
        }

        return false;
    }

    /**
     * Builds a payload for creating a new connection of a given type between a person and an org.
     *
     * @param string $person_id The UUID of the person to connect to the org.
     * @param string $org_id The UUID of the org to connect the person to.
     * @param string $connection_type The type of connection to create. Ex: person_to_organization, organization_to_person, etc.
     *
     * @return array The payload for creating a new connection.
     */
    public function build_connection_payload($person_id = null, $org_id = null, $connection_type = null, $type = null)
    {
        $payload = [
            'data' => [
                'type'          => 'connections',
                'attributes'    => [
                    'connection_type' => $connection_type,
                    'type'            => $type,
                    'starts_at' => date("Y-m-d"),
                ],
                'relationships' => [
                    'organization' => [
                        'data' => [
                            'id'   => $org_id,
                            'type' => 'organizations',
                        ],
                    ],
                    'person'       => [
                        'data' => [
                            'id'   => $person_id,
                            'type' => 'people',
                        ],
                    ],
                    'from'         => [
                        'data' => [
                            'id'   => $person_id,
                            'type' => 'people',
                        ],
                    ],
                    'to'           => [
                        'data' => [
                            'id'   => $org_id,
                            'type' => 'organizations',
                        ],
                    ],
                ],
            ],
        ];

        return $payload;
    }

    /**
     * Returns a list of connection types for a person to an organization.
     *
     * @return array
     */
    public function get_person_to_organizations_connection_types_list()
    {
        $client = WACC()->MdpApi->init_client();

        $resource_types = $client->resource_types->all();
        $resource_types = collect($resource_types);
        $found          = $resource_types->filter(function ($item) {
            return $item->resource_type == 'connection_person_to_organizations';
        });

        return $found;
    }


    /**
     * Returns resource list of person types.
     *
     * @return array
     */
    public function get_person_types_list()
    {
        $client = WACC()->MdpApi->init_client();

        $resource_types = $client->resource_types->all();
        $resource_types = collect($resource_types);
        $found          = $resource_types->filter(function ($item) {
            return $item->resource_type == 'shared_person_type';
        });

        return $found;
    }

    /**
     * Redirects to the referrer page with the given data, or dies if data is empty or has an error.
     *
     * @param array $data The data to pass to the redirect page.
     */
    public function redirect_or_die($data = [])
    {
        if (empty($data)) {
            wp_die(__('No data provided', 'wicket-acc'));
        }

        if (isset($response['error']) && $response['error'] === true) {
            wp_die($response['message']);
        }

        // Redirect
        global $wp;

        $url_redirect  = home_url(add_query_arg([], $wp->request));
        $org_id        = isset($_REQUEST['org_id']) ? sanitize_text_field($_REQUEST['org_id']) : '';
        $membership_id = isset($_REQUEST['membership_id']) ? sanitize_text_field($_REQUEST['membership_id']) : '';
        $included_id   = isset($_REQUEST['included_id']) ? sanitize_text_field($_REQUEST['included_id']) : '';

        header("Location: $url_redirect?org_id=$org_id&membership_id=$membership_id&included_id=$included_id&success");

        die();
    }
}
