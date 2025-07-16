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
     * Constructor.
     */
    public function __construct(
        protected array $block = [],
        protected bool $is_preview = false,
        protected int|string|null|bool $hide_additional_info = 0,
    ) {
        $this->block = $block;
        $this->is_preview = $is_preview;

        $this->hide_additional_info = get_field('hide_additional_info');
        $this->hide_alternate_name_field = get_field('hide_alternate_name_field');

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

        $lang = WACC()->Language->getCurrentLanguage();

        /**------------------------------------------------------------------
         * Decide whether we are loading an ORG from the URL
         * or looking up all associated orgs to person
         * if there's more than 1, we list them for the user to choose
         * which org they want to see
        ------------------------------------------------------------------*/
        $org_id = (isset($_GET['org_id'])) ? $_GET['org_id'] : '';
        $child_org_id = (isset($_GET['child_org_id'])) ? $_GET['child_org_id'] : '';

        // Child organization compatibility
        if (!empty($child_org_id)) {
            $parent_org_id = $org_id;
            $org_id = $child_org_id;
        }

        $client = wicket_api_client();
        $person = wicket_current_person();

        if ($org_id) {
            $org = WACC()->Mdp->Organization->getOrganizationByUuid($org_id);
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
                $org = WACC()->Mdp->Organization->getOrganizationByUuid($org_id);
            }
        }

        if ($org_id) {
            $wicket_settings = get_wicket_settings();
            $access_token = wicket_get_access_token(wicket_current_person_uuid(), $org_id);
            ?>

			<div class="wicket-section" role="complementary">
				<h2><?php _e('Profile', 'wicket-acc'); ?>
				</h2>
				<div id="profile"></div>
			</div>

			<script>
				window.Wicket = function (doc, tag, id, script) {
					var w = window.Wicket || {};
					if (doc.getElementById(id)) return w;
					var ref = doc.getElementsByTagName(tag)[0];
					var js = doc.createElement(tag);
					js.id = id;
					js.src = script;
					ref.parentNode.insertBefore(js, ref);
					w._q = [];
					w.ready = function (f) {
						w._q.push(f)
					};
					return w
				}(document, "script", "wicket-widgets",
					"<?php echo $wicket_settings['wicket_admin'] ?>/dist/widgets.js"
				);
			</script>

			<script>

				<?php
                $hidden_fields = [];
            if ($this->hide_alternate_name_field) {
                $hidden_fields[] = 'alternateName';
            }
            ?>

					(function () {
						Wicket.ready(function () {
							var widgetRoot = document.getElementById('profile');

							Wicket.widgets.editOrganizationProfile({
								rootEl: widgetRoot,
								apiRoot: '<?php echo $wicket_settings['api_endpoint'] ?>',
								accessToken: '<?php echo $access_token ?>',
								orgId: '<?php echo $org_id ?>',
								lang: "<?php echo $lang; ?>",
								hiddenFields: ['<?php echo implode("', '", $hidden_fields) ?>']
							}).then(function (widget) {
								widget.listen(widget.eventTypes.SAVE_SUCCESS, function (payload) {

								});
							});
						});
					})()
			</script>

			<?php if ($this->hide_additional_info == 0) : ?>
				<div class="wicket-section" role="complementary">
					<h2><?php _e('Additional Info', 'wicket-acc'); ?>
					</h2>
					<div id="additional_info"></div>
				</div>

				<script>
						(function () {
							Wicket.ready(function () {
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
								}).then(function (widget) {
									widget.listen(widget.eventTypes.SAVE_SUCCESS, function (payload) {

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