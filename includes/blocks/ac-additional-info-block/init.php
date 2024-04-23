<?php
/**
 * Wicket Additional Info Block
 *
 **/

namespace Wicket_AC\Blocks\AC_Additional_Info_Block;

function init( $block = [] ) { 
	$wicket_settings = get_wicket_settings();
	$person_id = wicket_current_person_uuid();
	$environment = wicket_get_option('wicket_admin_settings_environment');
	$ai_schema = get_field('additional_info_schema');
		

	if ( $person_id ) { ?>

		<script type="text/javascript">
			window.Wicket=function(doc,tag,id,script){
				var w=window.Wicket||{};if(doc.getElementById(id))return w;var ref=doc.getElementsByTagName(tag)[0];var js=doc.createElement(tag);js.id=id;js.src=script;ref.parentNode.insertBefore(js,ref);w._q=[];w.ready=function(f){w._q.push(f)};return w
			}(document,"script","wicket-widgets","<?php echo $wicket_settings['wicket_admin'] ?>/dist/widgets.js");
		</script>

		<div class="wicket-section" role="complementary">
			<h2><?php _e('Additional Info', 'wicket'); ?></h2>
			<!-- additional information -->
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
				accessToken: '<?php echo wicket_access_token_for_person($person_id) ?>',
				resource: {
					type: "people",
					id: '<?php echo $person_id; ?>'
				},
				lang: "<?php echo defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en' ?>",
				schemas: [ // If schemas are not provided, the widget defaults to show all schemas.
					<?php 
					if($ai_schema):
						foreach($ai_schema as $schema): ?>
					{ id: '<?php echo ($environment == 'prod') ? $schema['schema_id_prod'] : $schema['schema_id_stage']; ?>', <?php if($schema['schema_read_only']){ ?> resourceId: '<?php echo ($environment == 'prod') ? $schema['read_only_schema_id_prod'] : $schema['read_only_schema_id_stage']; ?>' <?php } ?> }, 
					<?php endforeach;
					endif; ?>
				]
				}).then(function(widget) {
					widget.listen(widget.eventTypes.SAVE_SUCCESS, function(payload) {
						
					});
				});
			});
			})();
		</script>

	
	<?php
	}
}