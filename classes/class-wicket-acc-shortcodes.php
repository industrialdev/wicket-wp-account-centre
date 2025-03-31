<?php

namespace WicketAcc\Shortcodes;

// No direct access
defined('ABSPATH') || exit;

use WicketAcc\WicketAcc;

/**
 * Shortcodes for Wicket Account Centre.
 */
class Shortcodes extends WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // [org-selector]
        add_shortcode('org-selector', [$this, 'orgSelectorCallback']);
    }

    /**
     * Callback function for the [org-selector] shortcode.
     *
     * @return string The shortcode output.
     */
    public function orgSelectorCallback()
    {
        $org_uuid = $_GET['org_id'] ?? '';
        $lang = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';
        $org_uuids_list = [];

        if (!empty($org_uuid)) {
            return '';
        }

        if (empty($org_uuid)) {
            $org_uuids_list = $this->orgSelectorGetOrgsList();
        }

        if (empty($org_uuids_list)) {
            return __('No organizations found for your account.', 'wicket-acc');
        }

        // If user only has one organization, redirect to that organization with url parameters
        if (!empty($org_uuids_list) && count($org_uuids_list) === 1 && empty($org_uuid)) {
            $url = strtok($_SERVER['REQUEST_URI'], '?');
            $redirect_url = add_query_arg('org_id', $org_uuids_list[0], $url);

            // Use wp_safe_redirect if possible (check headers_sent)
            if (!headers_sent()) {
                wp_safe_redirect($redirect_url);
                exit;
            } else {
                // Fallback meta refresh
                return '<meta http-equiv="refresh" content="0; url=' . esc_url($redirect_url) . '">';
            }
        }

        ob_start();

        if (!empty($org_uuids_list) && is_array($org_uuids_list) && count($org_uuids_list) > 1 && empty($org_uuid)) {
            // Build a list of orgs to choose from
            $linked = true;
            $has_active_memberships = false;
            $client = wicket_api_client();
            ?>
            <div class="wicket-acc-org-selector wp-block-paragraph">
                <h2 class='mb-5 primary_link_color'>
                    <?php _e('Choose an Organization:', 'wicket-acc'); ?>
                </h2>
                <ul class="wicket-organization-selector mb-10">
                    <?php
                                global $wp;

            foreach ($org_uuids_list as $i_org_id) :
                // Check if org has at least one membership
                $org_memberships = wicket_get_org_memberships($i_org_id);

                // Check if we have any active memberships
                if (!empty($org_memberships)) {
                    foreach ($org_memberships as $membership) {
                        if (isset($membership['membership']['attributes']['active']) && $membership['membership']['attributes']['active'] === true) {
                            $has_active_memberships = true;
                            break;
                        }
                    }

                    $linked = $has_active_memberships;
                }

                $organization = $client->get("organizations/$i_org_id");
                $org_name = $organization['data']['attributes']['legal_name_' . $lang] ?? 'N/A';
                ?>
                        <li class='flex items-center gap-3 py-1 leading-[2rem]'>
                            <?php if ($linked) { ?>
                                <i class="fa-solid fa-building w-[20px] h-[20px] text-[var(--color-primary)] shrink-0"></i><a
                                    class='primary_link_color'
                                    href='<?php echo home_url(add_query_arg([], $wp->request)) . "/?org_id=$i_org_id"; ?>'>
                                <?php } else { ?>
                                    <i class="fa-solid fa-ban w-[20px] h-[20px] text-[var(--color-primary)] shrink-0"></i>
                                <?php } ?>
                                <?php echo $org_name; ?>
                                <?php if ($linked) { ?>
                                </a>
                            <?php } ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
<?php
        }

        return ob_get_clean();
    }

    /**
     * Get the list of organizations a user is a part of.
     *
     * @return array The list of organizations
     */
    private function orgSelectorGetOrgsList()
    {
        $person = wicket_current_person();

        if (is_null($person)) {
            return [];
        }

        $org_uuids_list = [];

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
                || stristr($person_included['attributes']['name'], 'member')
                || stristr($person_included['attributes']['name'], 'org_editor')
                || isset(
                    $person_included['attributes']['assignable_role_names']
                ) && (
                    in_array('member', $roles)
                    || in_array('org_editor', $roles)
                )
            ) {

                if (isset($person_included['relationships']['resource']['data']['id']) && $person_included['relationships']['resource']['data']['type'] == 'organizations') {
                    $org_uuids_list[] = $person_included['relationships']['resource']['data']['id'];
                }
            }
        }

        $org_uuids_list = array_unique($org_uuids_list);

        return $org_uuids_list;
    }
}
