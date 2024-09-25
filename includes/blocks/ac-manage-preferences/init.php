<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Manage Preferences Block
 *
 **/
class Block_ManagePreferences extends WicketAcc
{
    /**
     * Constructor
     */
    public function __construct(
        protected array $block     = [],
        protected bool $is_preview = false,
    ) {
        $this->block      = $block;
        $this->is_preview = $is_preview;

        // Display the block
        $this->init_block();
    }
    /**
     * Init block
     *
     * @return void
     */
    protected function init_block()
    {
        $wicket_settings = get_wicket_settings(); ?>

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
			}(document, "script", "wicket-widgets", "<?php echo $wicket_settings['wicket_admin'] ?>/dist/widgets.js");
		</script>

		<div id="preferences"></div>

		<script type="text/javascript">
			(function() {
				Wicket.ready(function() {
					var widgetRoot = document.getElementById('preferences');

					Wicket.widgets.editPersonPreferences({
						rootEl: widgetRoot,
						apiRoot: '<?php echo $wicket_settings['api_endpoint'] ?>',
						accessToken: '<?php echo wicket_access_token_for_person(wicket_current_person_uuid()) ?>',
						personId: '<?php echo wicket_current_person_uuid(); ?>',
						lang: "<?php echo defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en' ?>"
					}).then(function(widget) {
						widget.listen(widget.eventTypes.SAVE_SUCCESS, function(payload) {
							console.log(payload);
							// console.log(`Schema ID ${payload.resource.id} updated with values ${payload.updatedDataField.value}`);
						});
					});
				});
			})()
		</script>
<?php
    }
}
