<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Template Name: ACC Org-Management Index
 * Template Post: my-account
 */

global $wp;

$OrgManagement = new OrgManagement();

$client = wicket_api_client();
$person = $OrgManagement->get_current_person();
$lang   = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';

// Get parent page slug
$current_page     = get_post();
$parent_page_slug = get_post($current_page->post_parent)->post_name;

/**------------------------------------------------------------------
 * Decide whether we are loading an ORG from the URL
 * or looking up all associated orgs to person
 * if there's more than 1, we list them for the user to choose
 * which org they want to see
------------------------------------------------------------------*/
$org_id = $_GET['org_id'] ?? '';

if ($org_id) {
    $org = wicket_get_organization($org_id);
} else {
    $org_ids = [];

    if (is_null($person)) {
        wp_die(__('Person not found. Contact your administrator', 'wicket'));
    }

    // Figure out orgs I should see. This association to the org is set on each role. The actual role types we look at might change depending on the project
    foreach ($person->included() as $person_included) {
        // Warning fix
        if (!isset($person_included['attributes']['name'])) {
            $person_included['attributes']['name'] = '';
        }

        // Assigned roles
        $roles = $person_included['attributes']['assignable_role_names'] ?? [];

        if (
            $person_included['type'] == 'roles' && stristr($person_included['attributes']['name'], 'owner')
            || stristr($person_included['attributes']['name'], 'membership_manager')
            || stristr($person_included['attributes']['name'], 'org_editor')
            || isset(
                $person_included['attributes']['assignable_role_names']
            ) && (
                in_array('membership_manager', $roles)
                || in_array('org_editor', $roles)
            )
        ) {

            if (isset($person_included['relationships']['resource']['data']['id']) && $person_included['relationships']['resource']['data']['type'] == 'organizations') {
                $org_ids[] = $person_included['relationships']['resource']['data']['id'];
            }
        }
    }

    $org_ids = array_unique($org_ids);

    // If they only have 1 org, redirect back to this page with the org ID in the URL to show info for that org. Ese we build a list of their orgs below to choose from
    if (count($org_ids) == 1) {
        $url = strtok($_SERVER["REQUEST_URI"], '?');
        header('Location: ' . $url . '?org_id=' . $org_ids[0]);
        die;
    }
}

get_header();
?>

<?php
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

                        <?php
                    if ($org_id) :
                        $org_info = $OrgManagement->get_organization_info_extended($org_id, $lang);

                        if (!$org_info) {
                            wp_die(__('Organization info not found', 'wicket'));
                        }
                        ?>
                            <div class="wicket-welcome-block bg-light-010 rounded-100 p-4 mb-4">
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
                                        <?php echo $org_info['org_meta']['main_phone']['number_international_format']; ?>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <hr aria-hidden="true">


                            <div class="table-responsive">
                                <?php
                                // get the teams in Woo assigned to this org
                                $org_memberships = wicket_get_org_memberships($org_id);

                        echo "<table class='org_management_table mb-5'>";
                        echo "<thead>";
                        echo "<th>&nbsp;</th>";
                        echo "<th>" . __('Number of Assigned People', 'wicket') . "</th>";
                        echo "<th></th>";
                        echo "</thead>";
                        foreach ($org_memberships as $org_mship) {
                            //var_dump($org_mship);
                            //die();

                            $org_mship_uuid     = $org_mship['membership']['id'];
                            $membership_uuid    = $org_mship['membership']['id'];
                            $included_uuid      = $org_mship['included']['id'];

                            $active_assignments = $org_mship['membership']['attributes']['active_assignments_count'];
                            $max_assignments    = $org_mship['membership']['attributes']['max_assignments'];
                            $max_assignments ??= __('Unlimited', 'wicket');
                            $starts_at          = $org_mship['membership']['attributes']['starts_at'];
                            $ends_at            = $org_mship['membership']['attributes']['ends_at'];

                            echo "<tr>";
                            echo "<td>";
                            echo "<strong>" . $org_mship['included']['attributes']['name_' . $lang] . "</strong>";
                            $date = date('F j, Y', strtotime($ends_at));
                            $expiry = $ends_at != '' ? __('Expires') . ' ' . $date : '';
                            echo "<br>" . $expiry;
                            echo "</td>";
                            echo "<td class='fw-bold'>";
                            echo $active_assignments . ' / ' . $max_assignments . " " . __('Seats', 'wicket');
                            echo "</td>";
                            echo "<td>";

                            // Proper get link: organization-members
                            $org_members_url = untrailingslashit(get_permalink(get_page_by_path($parent_page_slug . '/organization-members')));

                            // Link: organization-roster
                            $org_roster_url = untrailingslashit(get_permalink(get_page_by_path($parent_page_slug . '/organization-roster')));

                            // Link: organization-editor
                            $org_editor_url = untrailingslashit(get_permalink(get_page_by_path($parent_page_slug . '/organization-editor')));

                            if ($OrgManagement->role_check(['administrator', 'membership_manager'])) {
                                echo "<a class='primary_link_color underline_link' href='$org_members_url/?org_id=$org_id&membership_id=$membership_uuid&included_id=$included_uuid'>" . __('Manage Members', 'wicket') . "</a>";
                            }

                            /*if ($OrgManagement->role_check(['administrator', 'membership_owner', 'membership_manager'])) {
                    echo '<br/>';
                    echo "<a class='primary_link_color underline_link' href='$org_roster_url/?org_id=$org_id'>" . __('Manage Employees', 'wicket') . "</a>";
                }*/

                            if ($OrgManagement->role_check(['administrator', 'org_editor'])) {
                                echo '<br/>';
                                echo "<a class='primary_link_color underline_link' href='$org_editor_url/?org_id=$org_id'>" . __('Edit Organization', 'wicket') . "</a>";
                            }

                            echo "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                        ?>
                            </div>
                        <?php else : ?>

                            <?php
                            if ($org_ids) {
                                echo "<h2 class='primary_link_color'>" . __('Choose an Organization:', 'wicket') . "</h2>";
                                echo "<ul>";
                                // lookup org details based on UUID found on the role
                                foreach ($org_ids as $org_uuid) {
                                    $organization = $client->get("organizations/$org_uuid");
                                    echo "<li>";
                                    echo "<a class='primary_link_color' href='" . home_url(add_query_arg([], $wp->request)) . "?org_id=$org_uuid'>";
                                    echo $organization['data']['attributes']['legal_name_' . $lang];
                                    echo "</a>";
                                    echo "</li>";
                                }
                                echo '</ul>';
                            } else {
                                ?>
                                <p><?php echo esc_html__('You currently have no organizations to manage members for.', 'wicket'); ?>
                                </p>
                        <?php
                            }

                        endif;
    ?>
                    </div>
                </div>

                <?php WACC()->renderAccSidebar(); ?>
            </section>
        </main>
<?php endwhile;
endif; ?>

<?php get_footer(); ?>
