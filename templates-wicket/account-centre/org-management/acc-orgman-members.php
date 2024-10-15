<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Template Name: ACC Org-Management Members
 * Template Post: my-account
 */

global $wp;

$OrgManagement = new OrgManagement();

$OrgManagement->page_role_check(
    [
        'administrator',
        'membership_manager',
    ]
);

/**
 * Get initial data
 */
$client            = wicket_api_client();
$org_id            = $_POST['org_id'] ?? $_GET['org_id'];
$membership_id     = $_REQUEST['membership_id'] ?? '';
$included_id       = $_REQUEST['included_id'] ?? '';
$action            = $_REQUEST['action'] ?? '';
$team_owner_id     = '';
$current_page      = get_post();
$parent_page_slug  = get_post($current_page->post_parent)->post_name;
$lang              = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';

if ($org_id || $membership_id) {
    $person_membership_uuid   = $OrgManagement->get_current_person_memberships_by_organization($org_id);
    $membership_data          = $OrgManagement->get_org_membership_data($membership_id);
    $active_assignments       = $membership_data['attributes']['active_assignments_count'];
    $max_assignments          = $membership_data['attributes']['max_assignments'];
    $team_members             = $OrgManagement->get_org_membership_members($membership_id);
    $roster_csv_url           = get_stylesheet_directory_uri() . '/downloads/roster_template.csv';
    $site_domain              = parse_url(get_site_url(), PHP_URL_HOST);
}

// Dropdown values
$dropdown_roles = [
    'member'             => 'Member',
    'membership_manager' => 'Membership Manager',
    'org_editor'         => 'Organization Editor',
];

$dropdown_relationship = [
    'association_staff'   => 'Association Staff',
    'civilian_director'   => 'Civilian Director',
    'past_president'      => 'Past President',
    'president'           => 'President',
    'secretary'           => 'Secretary',
    'treasurer'           => 'Treasurer',
    'uniform_director'    => 'Uniform Director',
    'vice_president'      => 'Vice President',
];

// Add or update member
if (isset($_POST['action']) && ($_POST['action'] == 'add_member_to_roster')) {
    $args = [
        'action'               => $_POST['action'],
        'person_email'         => $_POST['address'],
        'person_role'          => $_POST['role'],
        'person_relationship'  => $_POST['relationship'],
        'org_id'               => $_POST['org_id'],
        'membership_id'        => $_POST['membership_id'],
        'included_id'          => $_POST['included_id'],
        'first_name'           => $_POST['given_name'],
        'last_name'            => $_POST['family_name'],
        'person_current_roles' => $_POST['person_current_roles'],
        'update_role_user_id'  => $_POST['update_role_user_id'],
    ];

    $response = $OrgManagement->add_or_update_member($args);

    if (isset($response['error']) && $response['error'] === true) {
        $message = $response['message'];
        wp_die($message);
    }
}

// Remove member
if (isset($_REQUEST['unassign_person_uuid'])) {
    $args = [
        'unassign_person_uuid'   => $_REQUEST['unassign_person_uuid'],
        'person_uuid'            => $_REQUEST['unassign_person_uuid'],
        'person_relationship'    => $_REQUEST['person_relationship'],
        'person_connection_id'   => $_REQUEST['person_connection_id'],
        'email'                  => $_REQUEST['email'],
        'org_id'                 => $_REQUEST['org_id'],
        'membership_id'          => $_REQUEST['membership_id'],
        'included_id'            => $_REQUEST['included_id'],
        'allowed_roles'          => $dropdown_roles,
        'allowed_relationships'  => $dropdown_relationship,
        'person_membership_uuid' => $_REQUEST['person_membership_uuid'],
    ];

    $response = $OrgManagement->unassign_person_uuid($args);

    if (isset($response['error']) && $response['error'] === true) {
        $message = $response['message'];
        wp_die($message);
    }
}

// Update person roles
if (isset($_REQUEST['update_role_user_id'])) {
    $args = [
        'person_uuid'             => $_REQUEST['update_role_user_id'],
        'person_current_roles'    => $_REQUEST['person_current_roles'],
        'update_role_person_uuid' => $_REQUEST['update_role_person_uuid'],
        'role'                    => $_REQUEST['role'],
        'org_id'                  => $_REQUEST['org_id'],
        'membership_id'           => $_REQUEST['membership_id'],
        'included_id'             => $_REQUEST['included_id'],
        'allowed_roles'           => $dropdown_roles,
        'allowed_relationships'   => $dropdown_relationship,
    ];

    $response = $OrgManagement->update_role($args);

    if (isset($response['error']) && $response['error'] === true) {
        $message = $response['message'];
        wp_die($message);
    }
}

get_header();

$wrapper_classes     = [];
$dev_wrapper_classes = get_field('page_wrapper_class');
if (!empty($dev_wrapper_classes)) {
    $wrapper_classes[] = $dev_wrapper_classes;
}

// CSS classes for Roster Managment styling
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

                        <h1 class="manage-members-title has-heading-3-xl-font-size mb-6">
                            <?php _e('Manage Members', 'wicket'); ?>
                        </h1>

                        <p class="body-lg mb-6">
                            <?php _e('Manage your association\'s executive members and staff through the Manage Members page. Administrators can easily add or remove individuals, ensuring your association\'s roster is accurate and up-to-date. This should be done, for example, following an election and turnover in executive positions. New members will receive an automatic email from the system and be added to all PAO communications distribution lists.', 'wicket'); ?>
                        </p>

                        <?php if ($org_id) : ?>

                            <?php
                        $org_info = $OrgManagement->get_organization_info_extended($org_id, $lang);

                            if (!$org_info) {
                                wp_die(__('Organization info not found', 'wicket'));
                            }
                            ?>
                            <div class="wicket-welcome-block bg-light-010 rounded-100 p-4 mb-6">
                                <h2 class='organization_name heading-lg font-weight:400 dark-100'>
                                    <?php echo $org_info['org_name'] ?>
                                </h2>

                                <?php if (!empty($org_info['org_meta']['main_address'])) : ?>
                                    <p class='formatted_address_label mb-4'>
                                        <?php echo $org_info['org_meta']['main_address']['formatted_address_label']; ?>
                                    </p>

                                    <?php if (isset($org_info['org_meta']['main_email']['address'])) : ?>
                                        <p class="email_address mb-4">
                                        <h5 class="font-bold">
                                            <?php _e('Email Address', 'wicket') ?>
                                        </h5>
                                        <?php echo $org_info['org_meta']['main_email']['address'] ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (isset($org_info['org_meta']['main_phone']['number_international_format'])) : ?>
                                        <p class="phone_number mb-4">
                                        <h5 class="font-bold">
                                            <?php _e('Phone Number', 'wicket') ?>
                                        </h5>
                                        <?php echo $org_info['org_meta']['main_phone']['number_international_format'] ?>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="assigned-people wicket-welcome-block py-2 px-4 hidden">
                                <div class="font-bold">
                                    <?php _e('Number of Assigned People', 'wicket') ?>
                                </div>
                                <?php
                                $max_assignments ??= __('Unlimited', 'wicket');
                            echo $active_assignments . ' / ' . $max_assignments;
                            ?>
                            </div>

                            <div class="assigned-people wicket-welcome-block py-2 px-4">
                                <div class="font-bold">
                                    <?php _e('Number of Assigned People', 'wicket') ?>
                                </div>
                                <?php
                            $max_assignments ??= __('Unlimited', 'wicket');
                            echo $active_assignments . '/' . $max_assignments;
                            ?>
                            </div>

                            <?php if (isset($message)) : ?>
                                <div class='alert alert-danger' role='alert'>
                                    <p><strong><?php echo $message ?></strong></p>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($_GET['success'])) : ?>
                                <div class='alert alert-success' role='alert'>
                                    <p><strong><?php _e("Successfully assigned person to membership", 'wicket'); ?></strong>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if ($team_members) : ?>
                                <div class='table-responsive mb-6'>
                                    <div class="alert alert-info text-right py-2" role="info" style="text-align: right;">
                                        <p class="text-xs" style="font-size: 0.8rem;"><i aria-hidden="true"
                                                class="fa-regular fa-circle-info"></i>
                                            <?php _e('Hold CTRL/CMD key and click to select multiple permissions', 'wicket') ?>
                                        </p>
                                    </div>

                                    <table class='team_assignment_table'>
                                        <thead>
                                            <tr>
                                                <th><?php _e('Name', 'wicket') ?>
                                                </th>
                                                <th><?php _e('Role', 'wicket') ?>
                                                </th>
                                                <th><?php _e('Permissions', 'wicket') ?>
                                                </th>
                                                <th aria-hidden="true"><span class='webaim-hidden'>&nbsp;</span></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                        foreach ($team_members as $team_member) :
                                            $person_uuid            = $team_member['relationships']['person']['data']['id'];
                                            $person                 = $OrgManagement->get_person_by_id($person_uuid);
                                            $users_roles            = $OrgManagement->get_org_roles_person($person_uuid, $org_id);
                                            $person_membership_uuid = $team_member['id'];

                                            // Current roles
                                            $person_current_roles = $person->role_names;
                                            // Critical: remove 'user' role from results
                                            $person_current_roles = array_diff($person_current_roles, ['user']);

                                            // Convert variable to something we can use in the form as hidden input
                                            $person_current_roles = implode(',', $person_current_roles);

                                            // Let's deal with the relationship
                                            $person_relationship = $OrgManagement->get_person_connections_by_id($person_uuid);

                                            /*
                              $person_relationship['data] is an array with unknow  number of elements, [0], [1], etc. See response example above.

                              We need to find the one that has relationships>organization>data>id == $org_id
                              */
                                            $filtered_data = array_filter($person_relationship['data'], function ($element) use ($org_id) {
                                                return isset($element['relationships']['organization']['data']['id']) &&
                                                    $element['relationships']['organization']['data']['id'] === $org_id;
                                            });

                                            // array_filter preserves the keys, reset the keys
                                            $filtered_data = array_values($filtered_data);

                                            // Assign our data
                                            if (!empty($filtered_data)) {
                                                $person_relationship_slug = $filtered_data[0]['attributes']['type'] ?? null;
                                                $person_connection_id     = $filtered_data[0]['attributes']['uuid'] ?? null;
                                                $person_relationship_name = ucwords(str_replace('_', ' ', $person_relationship_slug));
                                            } else {
                                                // Handle the case where no matching organization ID was found
                                                $person_relationship_slug = 'not_set';
                                                $person_connection_id     = null;
                                                $person_relationship_name = __('Not set', 'wicket');
                                            }

                                            $person_membership_end_date = $team_member['attributes']['ends_at'];
                                            $flag_membership_expired    = false;
                                            $today_date                 = date('Y-m-d');

                                            if ($person_membership_end_date) {
                                                $person_membership_end_date = date('Y-m-d', strtotime($person_membership_end_date));

                                                // Check if membership has expired
                                                if ($person_membership_end_date <= $today_date) {
                                                    $flag_membership_expired = true;
                                                }
                                            } else {
                                                $person_membership_end_date = '';
                                            }

                                            if ($flag_membership_expired === true) {
                                                continue;
                                            }
                                            ?>
                                                <tr>
                                                    <td style="vertical-align: top;" class="orgman_member_name">
                                                        <?php echo $person->given_name ?>
                                                        <?php echo $person->family_name ?>
                                                        <br />
                                                        <a href="mailto:<?php echo $person->primary_email_address ?>"
                                                            class="email_address"><?php echo $person->primary_email_address ?></a>
                                                    </td>
                                                    <td class='role' style="vertical-align: top;">
                                                        <?php echo $person_relationship_name; ?>
                                                    </td>
                                                    <td colspan="2" class='rol_col'>
                                                        <form
                                                            action="<?php echo home_url(add_query_arg([], $wp->request)); ?>"
                                                            method="get" class="save_permissions_form">
                                                            <input type="hidden" name='org_id'
                                                                value='<?php echo $org_id; ?>'>
                                                            <input type="hidden" name="membership_id"
                                                                value="<?php echo $membership_id; ?>">
                                                            <input type="hidden" name="person_current_roles"
                                                                value="<?php echo $person_current_roles; ?>">
                                                            <input type="hidden" name="person_relationship"
                                                                value="<?php echo $person_relationship_slug; ?>">
                                                            <input type="hidden" name='update_role_user_id'
                                                                value='<?php echo $person_uuid; ?>'>
                                                            <input type="hidden" name="included_id"
                                                                value="<?php echo $included_id; ?>">
                                                            <input type="hidden" name='update_role_person_uuid'
                                                                value='<?php echo $person_uuid; ?>'>
                                                            <input type="hidden" name='action' value='update_member_on_roster'>

                                                            <?php
                                                            if ($flag_membership_expired === false) :
                                                                ?>
                                                                <select name="role[]" aria-label='users role' multiple="multiple" size="2">
                                                                    <?php
                                                                        if (isset($dropdown_roles) && is_array($dropdown_roles)) {
                                                                            foreach ($dropdown_roles as $dropdown_key => $dropdown_label) {
                                                                                // Skip 'member' role
                                                                                if ($dropdown_key == 'member') {
                                                                                    continue;
                                                                                }
                                                                                ?>
                                                                            <option
                                                                                value="<?php echo $dropdown_key ?>"
                                                                                <?php
                                                                                            // We have to loop through the roles and see if the current role is in the array
                                                                                            foreach ($person->role_names as $role) {
                                                                                                if ($role == $dropdown_key) {
                                                                                                    echo 'selected';
                                                                                                }
                                                                                            }
                                                                                ?>><?php echo $dropdown_label ?>
                                                                            </option>
                                                                    <?php
                                                                            }
                                                                        } ?>
                                                                </select>
                                                            <?php else: ?>
                                                                <p class="text-xs text-info">
                                                                    <?php _e('This person membership has expired', 'wicket') ?>
                                                                </p>
                                                            <?php endif; ?>

                                                            <br />
                                                            <?php
                                                            // Proper $unassign_url
                                                            // Add a forward slash before query args. Also org_id and membership_id to the URL

                                                            $person_relationship_slug_encoded = base64_encode(urlencode($person_relationship_slug));

                                            $email_base64 = base64_encode(urlencode($person->primary_email_address));

                                            $unassign_url = home_url(add_query_arg([], $wp->request)) . '/?org_id=' . $org_id . '&membership_id=' . $membership_id . '&included_id=' . $included_id . '&person_membership_uuid=' . $person_membership_uuid . '&unassign_person_uuid=' . $person_uuid . '&person_relationship=' . $person_relationship_slug_encoded . '&person_connection_id=' . $person_connection_id . '&email=' . $email_base64;
                                            ?>

                                                            <button
                                                                class='primary_link_color underline_link action_save_permissions clear-both'><?php _e('Save Permissions', 'wicket') ?></button>

                                                            <?php
                                            // If user has membership_owner role, don't show the remove member link
                                            if (!in_array('membership_owner', $person->role_names)) :
                                                ?>
                                                                <br />
                                                                <a class='primary_link_color underline_link action_remove_member clear-both'
                                                                    href='<?php echo $unassign_url ?>'><?php _e('Remove Member', 'wicket') ?></a>
                                                            <?php endif;
                                            ?>

                                                            <div class="clear-both"></div>
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
                                    <?php _e('Need to bulk add your members to enjoy the many benefits of your membership?', 'wicket') ?>
                                </h3>
                                <p>
                                    <?php _e('Please download then fill out our Member Benefits Roster Template and send it back to: ', 'wicket') ?>
                                    memberservices@<?php echo $site_domain ?>
                                    <br />
                                    <?php _e('This will create user accounts for all your members who will be able to access your membership benefits that include special discounts on several nationally recognized producets and services, special member pricing for events, access to news and information only available to your members, and more.', 'wicket') ?>
                                </p>
                            </div>
                            <p class=" bulk_upload_cta hidden">
                                <i aria-hidden='true' class="far fa-file-excel"></i>
                                <?php _e('Member Roster Upload Sheet', 'wicket') ?>

                                <a href="<?php echo $roster_csv_url ?>"><?php _e('Download', 'wicket') ?>
                                    <i aria-hidden='true' class="fa-regular fa-arrow-down-to-line"></i></a>
                            </p>

                            <?php
                            // only show the bulk action to admins
                            if (in_array('administrator', wp_get_current_user()->roles)) : ?>
                                <!-- <a class='button' href="<?php echo "/roster-bulk-upload?org_id=$org_id&membership_id=$membership_id" ?>"><?php _e('Bulk add members', 'wicket') ?>
				<i aria-hidden='true' class="far fa-file-excel"></i></a> -->
                                <!-- <br> -->
                            <?php endif; ?>

                            <br />

                            <div class="mt-5">
                                <div class="col-lg-4">
                                    <h2 class='black_header'>
                                        <?php _e('Add a Member', 'wicket') ?>
                                    </h2>
                                </div>
                            </div>

                            <?php if ($max_assignments == 'Unlimited' || $active_assignments != $max_assignments) : ?>

                                <form
                                    action="<?php echo home_url(add_query_arg([], $wp->request)); ?>"
                                    method="post" class="add_new_person_membership_form">
                                    <input type="hidden" name="org_id"
                                        value="<?php echo $org_id ?>">
                                    <input type="hidden" name="membership_id"
                                        value="<?php echo $membership_id ?>">
                                    <input type="hidden" name="included_id"
                                        value="<?php echo $included_id ?>">
                                    <input type="hidden" name="role[]" value="member">
                                    <input type="hidden" name="action" value="add_member_to_roster">

                                    <p class="hidden">
                                        <?php _e("You can add members one at a time here, if you do not have a full member roster available.", 'wicket') ?>
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
                                        <input type="email" class="form__input" name="address" id="address" required
                                            value="<?php echo $_POST['address'] ?? '' ?>">
                                        <small>example format: mail@mail.com</small>
                                    </div>
                                    <div class="form__group">
                                        <label for="relationship"
                                            class="form__label fw-bold"><?php _e('Relationship Type:', 'wicket') ?>
                                            *</label>
                                        <select class="form__input" name="relationship" aria-label='users relationship' required>
                                            <option value=""></option>
                                            <?php
                                            if (isset($dropdown_relationship) && is_array($dropdown_relationship)) {
                                                foreach ($dropdown_relationship as $dropdown_value => $dropdown_label) { ?>
                                                    <option
                                                        value="<?php echo $dropdown_value ?>">
                                                        <?php echo $dropdown_label ?>
                                                    </option>
                                            <?php
                                                }
                                            } ?>
                                        </select>
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
