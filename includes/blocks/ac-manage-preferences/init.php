<?php

namespace WicketAcc\Blocks\ManagePreferences;

use WicketAcc\Blocks;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Manage Preferences Block.
 *
 * Renders the MDP editPersonPreferences widget from the mdp_json_config ACF
 * field, through the base plugin's widget-prefs-person component.
 *
 * @see atlas://plans/feature-widget-prefs-json-config.md
 **/
class init extends Blocks
{
    /**
     * ACF key of this block's mdp_json_config field. Hooks target the key, not
     * the name, because the profile blocks also have an mdp_json_config field.
     */
    public const CONFIG_FIELD_KEY = 'field_6ae203d487fd5';

    /**
     * Base plugin capability: widget-prefs-person accepts widget_config.
     */
    public const BASE_CAPABILITY = 'base-plugin.components.widget_prefs_person_config';

    /**
     * Constructor.
     *
     * @param array $block      ACF block settings.
     * @param bool  $is_preview Whether the block renders in the editor preview.
     */
    public function __construct(
        protected array $block = [],
        protected bool $is_preview = false,
    ) {
        $this->block = $block;
        $this->is_preview = $is_preview;

        // Display the block
        $this->init_block();
    }

    /**
     * Init block.
     *
     * @return void
     */
    protected function init_block(): void
    {
        $widget_config = self::decode_config(get_field('mdp_json_config'));
        if ($widget_config === []) {
            $widget_config = self::legacy_to_config(get_field('hide_language_preference'));
        }

        if (!self::base_supports_config()) {
            $this->render_legacy_widget(!empty($widget_config['hideLanguagePreferences']));

            return;
        }

        get_component('widget-prefs-person', [
            'person_id'       => wicket_current_person_uuid(),
            'widget_config'   => $widget_config,
            'show_title'      => false,
            'root_id'         => 'preferences',
            'dispatch_events' => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // Field hooks
    // -------------------------------------------------------------------------

    /**
     * Register the ACF field filters for this block.
     *
     * @return void
     */
    public static function register_hooks(): void
    {
        add_filter('acf/validate_value/key=' . self::CONFIG_FIELD_KEY, [self::class, 'validate_config'], 10, 2);
        add_filter('acf/prepare_field/key=' . self::CONFIG_FIELD_KEY, [self::class, 'add_base_plugin_notice']);

        // Legacy support, see below.
        add_filter('acf/load_value/key=' . self::CONFIG_FIELD_KEY, [self::class, 'prefill_config'], 10, 3);
        add_filter('acf/prepare_field/name=hide_language_preference', '__return_false');
    }

    /**
     * Reject invalid or list-shaped JSON. An empty value is valid.
     *
     * @param bool|string $valid Current validation state.
     * @param mixed       $value Submitted value.
     * @return bool|string
     */
    public static function validate_config(bool|string $valid, mixed $value): bool|string
    {
        if ($valid !== true || !is_string($value) || trim($value) === '') {
            return $valid;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return __('Invalid JSON. Correct the syntax before saving.', 'wicket-acc');
        }

        if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            return __('The widget config must be a JSON object, for example {"hidePrimaryLanguage": true}.', 'wicket-acc');
        }

        return $valid;
    }

    /**
     * Tell the editor when the base plugin cannot apply the JSON config yet.
     *
     * @param array|false $field ACF field array.
     * @return array|false
     */
    public static function add_base_plugin_notice(array|false $field): array|false
    {
        if ($field === false || self::base_supports_config()) {
            return $field;
        }

        $field['instructions'] = ($field['instructions'] ?? '') . '<p><strong>'
            . esc_html__('Update the Wicket Base Plugin to use this setting. Until then, only "hideLanguagePreferences" applies.', 'wicket-acc')
            . '</strong></p>';

        return $field;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Decode a saved JSON config. Empty, invalid or list-shaped JSON returns [].
     *
     * @param mixed $raw Saved field value.
     * @return array<string, mixed>
     */
    public static function decode_config(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) && $decoded !== [] && !array_is_list($decoded) ? $decoded : [];
    }

    /**
     * True when the base widget-prefs-person component accepts widget_config.
     *
     * @return bool
     */
    private static function base_supports_config(): bool
    {
        return function_exists('wicket_supports') && wicket_supports(self::BASE_CAPABILITY);
    }

    // -------------------------------------------------------------------------
    // Legacy support
    //
    // Blocks saved before mdp_json_config have a hide_language_preference
    // toggle. The toggle is hidden in the editor; its value still renders and
    // pre-fills the JSON until the block is next edited, which drops it.
    // render_legacy_widget() serves sites whose base plugin predates
    // widget_config; remove it once every site runs that base release.
    // -------------------------------------------------------------------------

    /**
     * Map the legacy hide_language_preference value to widget options.
     *
     * @param mixed $hide_language_preference Saved toggle value.
     * @return array<string, bool>
     */
    public static function legacy_to_config(mixed $hide_language_preference): array
    {
        return $hide_language_preference ? ['hideLanguagePreferences' => true] : [];
    }

    /**
     * Pre-fill an empty JSON config from the legacy toggle.
     *
     * Runs for the editor form and for front-end get_field() calls, so an
     * unedited block renders the same options it did before.
     *
     * @param mixed      $value   Saved config value.
     * @param int|string $post_id ACF post id; the block id inside a block.
     * @param array      $field   ACF field array.
     * @return mixed
     */
    public static function prefill_config(mixed $value, int|string $post_id, array $field): mixed
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        $legacy = self::legacy_to_config(get_field('hide_language_preference', $post_id));

        return $legacy === [] ? $value : wp_json_encode($legacy, JSON_PRETTY_PRINT);
    }

    /**
     * Previous inline widget render, for a base plugin without widget_config.
     *
     * @param bool $hide_language_preference Hide the language section.
     * @return void
     */
    protected function render_legacy_widget(bool $hide_language_preference): void
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
                        lang: "<?php echo WACC()->Language()->getCurrentLanguage(); ?>",
                        hideLanguagePreferences: <?php echo $hide_language_preference ? 'true' : 'false' ?>,
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

init::register_hooks();
