<?php

namespace WicketAcc\Blocks\OrgProfile;

use WicketAcc\Blocks;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Org Profile Block.
 **/
class init extends Blocks
{
    /**
     * @var string
     * @deprecated Superseded by the mdp_json_config ACF field; kept for legacy saved blocks.
     */
    protected array $mdp_json_fields = [];

    /**
     * Constructor.
     */
    public function __construct(
        protected array $block = [],
        protected bool $is_preview = false,
        protected int|string|bool|null $hide_additional_info = 0,
    ) {
        $this->block = $block;
        $this->is_preview = $is_preview;

        $this->hide_additional_info = get_field('hide_additional_info');
        $this->hide_alternate_name_field = get_field('hide_alternate_name_field');

        // Deprecated: mdp_json_fields is superseded by the mdp_json_config ACF
        // field (see init_block()); kept working for existing saved blocks.
        //
        // Note: the ACF group also still has an "MDP JSON Sections (Deprecated,
        // Inactive)" field (mdp_json_sections), but it is never read here — the
        // org profile widget component has no sections arg, unlike the individual
        // profile component, so this field never had any effect. It's kept
        // visible in the editor only so a value saved before this was noticed
        // isn't silently hidden from whoever configured the block.
        $json_fields = get_field('mdp_json_fields');
        $this->mdp_json_fields = json_decode($json_fields, true) ?? [];

        // Display the block
        $this->init_block();
    }

    /**
     * Init block.
     *
     * @return void
     */
    public function init_block($block = [])
    {
        global $wp;

        $lang = WACC()->Language()->getCurrentLanguage();

        /**------------------------------------------------------------------
         * Decide whether we are loading an ORG from the URL
         * or looking up all associated orgs to person
         * if there's more than 1, we list them for the user to choose
         * which org they want to see
        ------------------------------------------------------------------*/
        $org_id = isset($_GET['org_uuid']) ? sanitize_text_field($_GET['org_uuid']) : '';
        if (empty($org_id) && isset($_GET['org_id'])) {
            $org_id = sanitize_text_field($_GET['org_id']);
        }
        $child_org_id = isset($_GET['child_org_id']) ? sanitize_text_field($_GET['child_org_id']) : '';

        // Child organization compatibility
        if (!empty($child_org_id)) {
            $parent_org_id = $org_id;
            $org_id = $child_org_id;
        }

        $client = wicket_api_client();
        $person = wicket_current_person();

        if ($org_id) {

            $org = WACC()->Mdp()->Organization()->getOrganizationByUuid($org_id);

        } else {
            $org_ids = [];
            // figure out orgs I should see
            // this association to the org is set on each role. The actual role types we look at might change depending on the project
            foreach ($person->included() as $person_included) {
                if (isset($person_included['attributes']['name'])) {
                    if ($person_included['type'] == 'roles' && (stristr($person_included['attributes']['name'], 'org_editor'))) {
                        if (isset($person_included['relationships']['resource']['data']['id']) && $person_included['relationships']['resource']['data']['type'] == 'organizations') {
                            $org_ids[] = $person_included['relationships']['resource']['data']['id'];
                        }
                    }
                }
            }

            $org_ids = array_unique($org_ids);

            // if they only have 1 org, set org ID to the first value
            // else we build a list of their orgs below to choose from
            if (count($org_ids) == 1) {
                $org_id = $org_ids[0];

                $org = WACC()->Mdp()->Organization()->getOrganizationByUuid($org_id);

            }
        }

        if ($org_id) {
            $wicket_settings = get_wicket_settings();
            $access_token = wicket_get_access_token(wicket_current_person_uuid(), $org_id);

            $hidden_fields = [];
            if ($this->hide_alternate_name_field) {
                $hidden_fields[] = 'alternateName';
            }

            if (!component_exists('widget-profile-org')) {
                echo '<p>' . __('Widget-profile-org component is missing. Please update the Wicket Base Plugin.', 'wicket-acc') . '</p>';
            } else {
                $json_config = get_field('mdp_json_config');
                $widget_config = json_decode((string) $json_config, true) ?? [];

                if (is_array($widget_config) && $widget_config !== []) {
                    if (!empty($hidden_fields)) {
                        $widget_config['hiddenFields'] = $hidden_fields;
                    }
                    // The component has no dedicated 'lang' arg — it falls back to a
                    // bare ICL_LANGUAGE_CODE check with no Polylang/WP-locale support.
                    // $lang (WACC()->Language()->getCurrentLanguage(), resolved above)
                    // is the correct value the pre-refactor inline call used; route it
                    // through widget_config so it overrides the component's fallback.
                    $widget_config['lang'] = $lang;
                    get_component('widget-profile-org', [
                        'org_id'        => $org_id,
                        'widget_config' => $widget_config,
                    ]);
                } else {
                    // Deprecated fallback: mdp_json_fields only renders when the
                    // mdp_json_config ACF field is empty/invalid.
                    $component_widget_config = ['lang' => $lang];
                    if (!empty($hidden_fields)) {
                        $component_widget_config['hiddenFields'] = $hidden_fields;
                    }
                    get_component('widget-profile-org', array_filter([
                        'org_id'        => $org_id,
                        'fields'        => $this->mdp_json_fields,
                        'widget_config' => $component_widget_config,
                    ], static fn ($value) => $value !== []));
                }
            }
            ?>

            <?php if ($this->hide_additional_info == 0) : ?>
                <div class="wicket-section" role="complementary">
                    <h2><?php _e('Additional Info', 'wicket-acc'); ?>
                    </h2>
                    <div id="additional_info"></div>
                </div>

                <script>
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
                                lang: "<?php echo $lang; ?>",
                                // schemas: [ // If schemas are not provided, the widget defaults to show all schemas.

                                // ]
                            }).then(function(widget) {
                                widget.listen(widget.eventTypes.SAVE_SUCCESS, function(payload) {

                                });
                            });
                        });
                    })()
                </script>
            <?php endif; ?>
<?php
        } else {
            echo '<!--' . __('You currently have no organizations to manage information for.', 'wicket-acc') . '-->';
        }
    }
}
?>
