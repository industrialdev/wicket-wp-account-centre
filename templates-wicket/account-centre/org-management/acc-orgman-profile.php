<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Template Name: ACC Org-Management Profile
 * Template Post: my-account
 */

global $wp;

$OrgManagement = new OrgManagement();

$OrgManagement->page_role_check(
    [
        'administrator',
        'org_editor',
    ]
);

$client = wicket_api_client();
$person = $OrgManagement->get_current_person();
$lang   = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';

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
$wicket_user_id = wicket_current_person_uuid();
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

                        <?php if ($org_id) : ?>

                            <?php
                            $wicket_settings = get_wicket_settings();
                            $access_token = wicket_get_access_token(wicket_current_person_uuid(), $org_id);
                            ?>

                            <script type="text/javascript">
                                window.Wicket = function(doc, tag, id, script) {
                                    var w = window.Wicket || {};
                                    if (doc.getElementById(id)) return w;
                                    var ref = doc.getElementsByTagName(tag)[0];
                                    var js = doc.createElement(tag);
                                    js.id = id;
                                    js.src = script;
                                    ref.parentNode.insertBefore(js, ref);
                                    w._q = [];
                                    w.ready = function(f) {
                                        w._q.push(f)
                                    };
                                    return w
                                }(document, "script", "wicket-widgets",
                                    "<?php echo $wicket_settings['wicket_admin'] ?>/dist/widgets.js"
                                );
                            </script>

                            <div id="profile"></div>

                            <script type="text/javascript">
                                (function() {
                                    Wicket.ready(function() {
                                        var widgetRoot = document.getElementById('profile');

                                        Wicket.widgets.editOrganizationProfile({
                                            rootEl: widgetRoot,
                                            apiRoot: '<?php echo $wicket_settings['api_endpoint'] ?>',
                                            accessToken: '<?php echo $access_token ?>',
                                            orgId: '<?php echo $org_id ?>',
                                            lang: "<?php echo defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en' ?>"
                                        }).then(function(widget) {
                                            widget.listen(widget.eventTypes.SAVE_SUCCESS, function(payload) {

                                            });
                                        });
                                    });
                                })()
                            </script>

                            <br>
                            <br>

                            <?php
                            $wicker_user_id = $wicket_settings['person_id'];

                            if (isset($wicker_user_id)) : ?>

                                <div class="wicket-section additional_info-container" role="form">
                                    <div class="">
                                        <div class="col-lg-8">
                                            <h2 class="wicket-section-title primary_link_color">
                                                <?php _e('Additional Info', 'industrial'); ?>
                                            </h2>
                                        </div>
                                        <div class="col-lg-4 text-lg-end">
                                            <p><span class="mandatory">*</span>
                                                <?php _e('indicates required field', 'industrial'); ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div id="additional_info"></div>
                                </div>


                                <?php
                                $environment = get_option('wicket_admin_settings_environment');
                                $full_time_employees_schema = $environment[0] == 'prod' ? 'e981bd15-8ce1-402f-b3a8-8ebec40ce66d' : '3f986a58-0a25-4e7a-83aa-0ad8736da541';
                                $organization_stats_schema = $environment[0] == 'prod' ? '8870bae7-6ac6-4993-9031-fb90d4d772a5' : '79a7a472-a843-4605-ae6e-b5eb440cfde5';
                                $location_data_schema = $environment[0] == 'prod' ? '1766eaa6-e3d5-408c-a7cd-e221c496069b' : '746dee68-91ad-419d-9e67-6be64c63a98e';
                                $business_type_schema = $environment[0] == 'prod' ? '3bc5ec95-63f7-4e0f-89d3-2dc8acf2c241' : '29d74c5e-4620-409c-a521-b30a03c365ef';
                                ?>

                                <script type="text/javascript">
                                    (function() {
                                        Wicket.ready(function() {
                                            var widgetRoot = document.getElementById('additional_info');

                                            Wicket.widgets.editAdditionalInfo({
                                                loadIcons: true,
                                                rootEl: widgetRoot,
                                                apiRoot: '<?php echo $wicket_settings['api_endpoint'] ?>',
                                                accessToken: '<?php echo $access_token ?>',
                                                resource: {
                                                    type: "organizations",
                                                    id: '<?php echo $org_id ?>'
                                                },
                                                lang: "<?php echo defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en' ?>",
                                                schemas: [ // If schemas are not provided, the widget defaults to show all schemas.
                                                    {
                                                        id: '<?php echo $full_time_employees_schema ?>',
                                                        showAsRequired: true
                                                    },
                                                    {
                                                        id: '<?php echo $business_type_schema ?>',
                                                        showAsRequired: true
                                                    },
                                                    {
                                                        id: '<?php echo $organization_stats_schema ?>',
                                                        showAsRequired: true
                                                    },
                                                    {
                                                        id: '<?php echo $location_data_schema ?>',
                                                        showAsRequired: true
                                                    }
                                                ]
                                            }).then(function(widget) {
                                                widget.listen(widget.eventTypes.SAVE_SUCCESS, function(payload) {

                                                });
                                            });
                                        });
                                    })()
                                </script>

                            <?php endif; ?>


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
                                echo "<p>" . __('You currently have no organizations to manage information for.', 'wicket') . "</p>";
                            }
                            ?>

                        <?php endif; ?>

                        <br>
                        <br>


                    </div>
                </div>

                <?php WACC()->renderAccSidebar(); ?>
            </section>
        </main>
<?php endwhile;
endif; ?>

<?php get_footer(); ?>
