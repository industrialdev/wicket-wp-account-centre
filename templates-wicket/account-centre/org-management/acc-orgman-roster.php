<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Template Name: ACC Org-Management Roster
 * Template Post: my-account
 */

global $wp;

$OrgManagement = new OrgManagement();

$OrgManagement->page_role_check(
    [
        'administrator',
        'membership_manager',
        'org_manager',
    ]
);

$client        = wicket_api_client();
$org_id        = $_POST['org_id'] ?? $_GET['org_id'];
$lang          = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';
$team_owner_id = '';

// Get parent page slug
$current_page     = get_post();
$parent_page_slug = get_post($current_page->post_parent)->post_name;

/**
 * Get initial data
 */
if ($org_id) {
    $organization_uuid      = $org_id;
    $organization_obj       = wicket_get_organization($org_id);
    $roster_members         = $OrgManagement->get_org_relationships_person($org_id);
    $roster_csv_url         = get_stylesheet_directory_uri() . '/downloads/roster_template.csv';
    $site_domain            = parse_url(get_site_url(), PHP_URL_HOST);
    $total_relationships    = count($roster_members);
    $active_assignments     = $total_relationships;
    $max_assignments        = 'Unlimited';
}

/**
 * Received POST org_id, form processing
 */
if (isset($_POST['org_id'])) {
    // We are processing a form submission, so we need to make sure we have the org_id
    if (!isset($org_id) || empty($org_id)) {
        // Error message
        $message = __("Organization ID is missing", 'wicket');
        wp_die($message);
    }
}

/**------------------------------------------------------------------
 * Create/update new person and add_to_roster
------------------------------------------------------------------*/
if (isset($_POST['address']) && isset($_POST['action']) && $_POST['action'] == 'add_to_roster') {
    // Sanitize email
    $person_email = filter_var(trim($_POST['address']), FILTER_SANITIZE_EMAIL);

    // Check if the user already exists in Wicket, by email
    $person = wicket_get_person_by_email($person_email);

    // Person does not exist on MDP, create it
    if (!isset($person['id'])) {
        $person = wicket_create_person(trim($_POST['given_name']), trim($_POST['family_name']), $person_email);

        // Get person ID
        $person_uuid = $person['data']['id'];
    } else {
        // Get person ID
        $person_uuid = $person['id'];
    }

    // This is the only data we need from org on this call. Date format: ISO8604
    $start_at = $organization_obj['data']['attributes']['starts_at'] = (new \DateTime(date('c'), wp_timezone()))->format('c');
    $ends_at  = $organization_obj['data']['attributes']['ends_at']   = (new \DateTime(date('c'), wp_timezone()))->modify('+1 year')->format('c');

    // Assign this person to Org Membership
    $mdp_response = wicket_create_connection(
        [
            'data' => [
                'type' => 'connections',
                'attributes' => [
                    'connection_type'   => 'person_to_organization',
                    'type'              => 'member',
                    'starts_at'         => $start_at,
                    'ends_at'           => $ends_at,
                    'description'       => null,
                    'tags'              => [],
                ],
                'relationships' => [
                    'from' => [
                        'data' => [
                            'type' => 'people',
                            'id'   => $person_uuid,
                            'meta' => [
                                'can_manage' => false,
                                'can_update' => false,
                            ],
                        ],
                    ],
                    'to' => [
                        'data' => [
                            'type' => 'organizations',
                            'id'   => $org_id,
                        ],
                    ],
                ],
            ],
        ]
    );

    // If empty or null response, show error message
    if (empty($mdp_response) || is_null($mdp_response)) {
        $message = __("Error assigning person to membership", 'wicket');
        wp_die($message);
    }

    // We need $user->data->user_login = $person_uuid for the helper to work (even outside WP context)
    $user = (object) [
        'data' => (object) [
            'user_login' => $person_uuid,
        ],
    ];

    // Send email to user letting them know of the assignment
    //send_person_to_team_assignment_email($user, $org_id);

    // Write a touchpoint saying they were assigned an org membership
    $organization_name = $organization_obj['data']['attributes']['legal_name_' . $lang];
    $service_id        = get_create_touchpoint_service_id('Roster Manage', 'Added member from Rooster Management front.');

    $params = [
        'person_id' => $user->user_login,
        'action'    => 'Organization membership assigned',
        'details'   => "Person was assigned to a membership under '$organization_name' on " . date('c', time()),
        'data'      => ['org_id' => $org_id],
    ];

    write_touchpoint($params, $service_id);

    if (!isset($message)) {
        $url = home_url(add_query_arg([], $wp->request));
        header("Location: $url?org_id=$org_id&success");
        die;
    }
}

/**
 * Remove connection (wicket_remove_connection())
 * action = remove_from_roster
 */
if (isset($_GET['action']) && $_GET['action'] == 'remove_from_roster') {
    // Remove current role, wicket_remove_role(). Get current role.
    wicket_remove_role($_GET['person_uuid'], 'member');

    // Remove connection
    wicket_remove_connection($_GET['connection_id']);

    // Touchpoint
    $params = [
        'person_id' => $_GET['person_uuid'],
        'action'    => 'Organization membership removed',
        'details'   => "Persons membership was removed from '$organization_name' on " . date('c', time()),
        'data'      => ['org_id' => $org_id],
    ];

    $service_id = get_create_touchpoint_service_id('Roster Manage', 'Removed member from membership');

    write_touchpoint($params, $service_id);

    $url = home_url(add_query_arg([], $wp->request));
    header("Location: $url?org_id=$org_id");
    die;
}

get_header();

$wrapper_classes     = [];
$dev_wrapper_classes = get_field('page_wrapper_class');
if (!empty($dev_wrapper_classes)) {
    $wrapper_classes[] = $dev_wrapper_classes;
}

// Class for Roster Managment styling
$wrapper_classes[] = 'woocommerce';
$wrapper_classes[] = 'roster-management';
$wrapper_classes[] = 'acc-organization-management';
$wrapper_classes[] = 'wicket-acc-container';

$display_breadcrumb   = get_field('display_breadcrumb');
$display_publish_date = get_field('display_publish_date');
?>

<?php if (have_posts()) : while (have_posts()) : the_post(); ?>

        <?php
        if ($display_breadcrumb) {
            echo '<div class="wp-block-breadcrumbs">'; // Having the `wp-block-` prefix will help align it with the other Blocks
            get_component('breadcrumbs', []);
            echo '</div>';
        }
        if ($display_publish_date) {
            echo '<div class="wp-block-published-date">';
            echo "<p class='mt-3 mb-4'><strong>" . __('Published:', 'wicket') . ' ' . get_the_date('d-m-Y') . "</strong></p>";
            echo '</div>';
        }
        ?>

        <main
            class="<?php echo implode(' ', $wrapper_classes) ?> container mb-8"
            id="main-content">
            <?php //include(locate_template('template-parts/header/account-centre-banner.php', false, false));
            ?>

            <section id="content" class="woocommerce-wicket--container section page-default">

                <div class="woocommerce-wicket--account-centre row">
                    <div class="columns large-8">

                        <?php if ($org_id) : ?>

                            <?php
                            $org_info = $OrgManagement->get_organization_info_extended($org_id, $lang);

                            if (!$org_info) {
                                wp_die(__('Organization info not found', 'wicket'));
                            }
                            ?>
                            <div class="wicket-welcome-block bg-light-010 rounded-100 p-4 mb-6">
                                <h2 class='font-bold text-lg black_header'>
                                    <?php echo $org_info['org_name'] ?>
                                </h2>

                                <?php if (!empty($org_info['org_meta']['main_address'])) : ?>
                                    <p class='formatted_address_label mb-4'>
                                        <?php echo $org_info['org_meta']['main_address']['formatted_address_label']; ?>
                                    </p>

                                    <p class="email_address mb-4">
                                    <h5 class="font-bold">
                                        <?php _e('Email Address', 'wicket') ?>
                                    </h5>
                                    <?php echo $org_info['org_meta']['main_email']['address'] ?>
                                    </p>

                                    <p class="phone_number mb-4">
                                    <h5 class="font-bold">
                                        <?php _e('Phone Number', 'wicket') ?>
                                    </h5>
                                    <?php echo $org_info['org_meta']['main_phone']['number_international_format'] ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="assigned-people wicket-welcome-block py-2 px-4">
                                <span class="font-bold">
                                    <?php _e('Number of Employees:', 'wicket') ?>
                                </span>
                                <?php echo $total_relationships; ?>
                            </div>

                            <?php if (isset($message)) : ?>
                                <div class='alert alert-danger' role='alert'>
                                    <p><strong><?php echo $message ?></strong></p>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($_GET['success'])) : ?>
                                <div class='alert alert-success' role='alert'>
                                    <p><strong><?php _e("Successfully added person to roster", 'wicket'); ?></strong>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if ($roster_members) : ?>
                                <div class='mb-6 table-responsive'>
                                    <table class='roster_management_table'>
                                        <thead>
                                            <tr>
                                                <th><?php _e('First Name', 'wicket') ?>
                                                </th>
                                                <th><?php _e('Last Name', 'wicket') ?>
                                                </th>
                                                <th><?php _e('Email Address', 'wicket') ?>
                                                </th>
                                                <th><?php _e('Actions', 'wicket') ?>
                                                </th>
                                                <th><span class='webaim-hidden'>empty table header</span></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            foreach ($roster_members as $roster_member) :
                                                $person_uuid         = $roster_member['relationships']['person']['data']['id'];
                                                $person              = wicket_get_person_by_id($person_uuid);
                                                $person_membership_uuid = $roster_member['id'];
                                            ?>
                                                <tr>
                                                    <td><?php echo $person->given_name ?>
                                                    </td>
                                                    <td><?php echo $person->family_name ?>
                                                    </td>
                                                    <td class='email_address'>
                                                        <?php echo $person->primary_email_address ?>
                                                    </td>
                                                    <td colspan="2" class='rol_col'>
                                                        <form
                                                            action="<?php echo home_url(add_query_arg([], $wp->request)); ?>"
                                                            method="get">
                                                            <input type="hidden" name='org_id'
                                                                value='<?php echo $org_id; ?>'>
                                                            <input type="hidden" name='person_uuid'
                                                                value='<?php echo $person_uuid; ?>'>
                                                            <input type="hidden" name='connection_id'
                                                                value='<?php echo $roster_member['id']; ?>'>
                                                            <input type="hidden" name='action' value='remove_from_roster'>

                                                            <?php
                                                            // Unassign url
                                                            $unassign_url = home_url(add_query_arg([], $wp->request)) . '/?org_id=' . $org_id . '&person_uuid=' . $person_uuid . '&connection_id=' . $roster_member['id'] . '&action=remove_from_roster';
                                                            ?>

                                                            <a class='primary_link_color underline_link'
                                                                href='<?php echo $unassign_url ?>'><?php _e('Remove Employee', 'wicket') ?></a>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>

                                </div>
                            <?php else : ?>
                                <p><?php _e('There are currently no members assigned to this membership. Use the form below to add some.', 'wicket') ?>
                                </p>
                            <?php endif; ?>

                            <div class='bulk_upload_callout hidden'>
                                <h3 class='black_header font-bold'>
                                    <?php _e('Need to bulk add your employees to enjoy the many benefits of your membership?', 'wicket') ?>
                                </h3>
                                <p>
                                    <?php _e('Please download then fill out our Member Benefits Roster Template and send it back to: ', 'wicket') ?>
                                    memberservices@<?php echo $site_domain ?>
                                    <br />
                                    <?php _e('This will create user accounts for all your employees who will be able to access your membership benefits that include special discounts on several nationally recognized producets and services, special member pricing for events, access to news and information only available to your members, and more.', 'wicket') ?>
                                </p>
                            </div>
                            <p class="bulk_upload_cta hidden">
                                <i aria-hidden='true' class="far fa-file-excel"></i>
                                <?php _e('Employee Roster Upload Sheet', 'wicket') ?>

                                <a href="<?php echo $roster_csv_url ?>"><?php _e('Download', 'wicket') ?>
                                    <i aria-hidden='true' class="fa-regular fa-arrow-down-to-line"></i></a>
                            </p>

                            <?php
                            // only show the bulk action to admins
                            if (in_array('administrator', wp_get_current_user()->roles)) : ?>
                                <!-- <a class='button' href="<?php echo "/roster-bulk-upload?org_id=$org_id" ?>"><?php _e('Bulk add employees', 'wicket') ?>
				<i aria-hidden='true' class="far fa-file-excel"></i></a> -->
                                <!-- <br> -->
                            <?php endif; ?>

                            <br>
                            <div class="mt-5">
                                <div class="col-lg-4">
                                    <h2 class='black_header'>
                                        <?php _e('Add an Employee', 'wicket') ?>
                                    </h2>
                                </div>
                                <?php
                                // only show the link to add more members if the current user is the owner of the team
                                // this is because we want the person who originally paid for the team to see any additions to it on renewal
                                //if ($team_post->get_owner_id() == wp_get_current_user()->ID) :
                                if ($team_owner_id == wp_get_current_user()->ID) :
                                ?>
                                    <!-- <div class="col-lg-6 mb-3">
              <a href="<?php echo $lang == 'fr' ? '/fr' : '' ?>/account-centre/organization/purchase-membership-seats"
					class='fw-bold'><?php _e('Purchase more membership seats', 'wicket') ?></a>
					<i aria-hidden='true' class="tags--blue fa-solid fa-arrow-right"></i>
				</div> -->
                                <?php endif; ?>
                            </div>

                            <?php if ($max_assignments == 'Unlimited' || $active_assignments != $max_assignments) : ?>

                                <form
                                    action="<?php echo home_url(add_query_arg([], $wp->request)); ?>"
                                    method="post" class="add_new_person_membership_form">
                                    <input type="hidden" name="org_id"
                                        value="<?php echo $org_id ?>">
                                    <input type="hidden" name="person_uuid"
                                        value="<?php echo $person_uuid ?>">
                                    <input type="hidden" name="action" value="add_to_roster">

                                    <p class="hidden">
                                        <?php _e("You can add employees one at a time here, if you do not have a full employee roster available.", 'wicket') ?>
                                    </p>
                                    <p><?php _e('* indicates required field', 'wicket') ?>
                                    </p>

                                    <div class="form__group">
                                        <label for="given_name"
                                            class="form__label fw-bold"><?php _e('First Name:', 'wicket') ?>
                                            *</label>
                                        <input type="text" class="form__input" name="given_name" id="given_name" required
                                            value="<?php echo $_POST['given_name'] ?? '' ?>">
                                    </div>
                                    <div class="form__group">
                                        <label for="family_name"
                                            class="form__label fw-bold"><?php _e('Last Name:', 'wicket') ?>
                                            *</label>
                                        <input type="text" class="form__input" name="family_name" id="family_name" required
                                            value="<?php echo $_POST['family_name'] ?? '' ?>">
                                    </div>
                                    <div class="form__group">
                                        <label for="address"
                                            class="form__label fw-bold"><?php _e('Email Address:', 'wicket') ?>
                                            *</label>
                                        <input type="email" class="form__input" name="address" id="address"
                                            pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,5}$" required
                                            value="<?php echo $_POST['address'] ?? '' ?>">
                                        <small>example format: mail@mail.com</small>
                                    </div>
                                    <input class="button button--primary" type="submit" name="submit"
                                        value="<?php _e('Add User', 'wicket') ?>">
                                    <br>
                                    <br>
                                </form>
                            <?php else : ?>
                                <p class="alert alert-danger">
                                    <?php _e('You have reached the maximum number of assignable people', 'wicket') ?>
                                </p>
                            <?php endif; ?>

                        <?php endif; ?>
                    </div>
                </div>

                <?php WACC()->renderAccSidebar(); ?>
            </section>
        </main>
<?php endwhile;
endif; ?>

<?php get_footer(); ?>
