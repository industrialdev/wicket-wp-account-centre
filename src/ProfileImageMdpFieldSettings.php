<?php

declare(strict_types=1);

namespace WicketAcc;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// No direct access
defined('ABSPATH') || exit;

/**
 * Profile image -> MDP additional-info field picker.
 *
 * Owns the settings concern for choosing which MDP additional_info field stores
 * the uploaded profile image URL. Hosts:
 *   - the option key and the composite ref format ("{schema_slug}|{field_slug}");
 *   - the refresh REST route (POST /wicket-acc/v1/profile-image-mdp-fields/refresh);
 *   - shared ref parse/format helpers used by Profile.php, the settings UI, and
 *     the one-time migration.
 *
 * The field enumerator itself lives on WicketAcc\Mdp\Schema::getProfileImageFieldOptions().
 * This class is instantiated unconditionally so its rest_api_init hook fires
 * during REST requests (which are not is_admin).
 */
class ProfileImageMdpFieldSettings extends WicketAcc
{
    /** Option key storing the composite ref, or empty for "No syncing". */
    public const OPTION_KEY = 'acc_profile_picture_mdp_field_ref';

    /** One-time migration guard flag. */
    public const MIGRATION_FLAG = 'wicket_acc_mdp_field_ref_migrated';

    public const REST_NAMESPACE = 'wicket-acc/v1';

    public const REST_ROUTE = '/profile-image-mdp-fields/refresh';

    /** Sentinel value meaning "do not sync" (empty string). */
    public const NO_SYNCING = '';

    /**
     * Constructor. Hooks the refresh REST route on every request context.
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        // Run after HFMigration (admin_init priority 5) so legacy keys are in the
        // HyperFields option array before we read them.
        add_action('admin_init', [$this, 'maybeMigrateLegacyRef'], 10);
        if (defined('\WP_CLI') && \WP_CLI) {
            // CLI has no admin_init; run on init so a first CLI request migrates.
            add_action('init', [$this, 'maybeMigrateLegacyRef'], 20);
        }
    }

    /**
     * Register REST routes.
     */
    public function registerRoutes(): void
    {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleRefresh'],
                'permission_callback' => static fn (): bool => current_user_can('manage_options'),
            ],
        ]);
    }

    /**
     * Refresh the cached MDP field list and return the fresh grouped options.
     *
     * Called by the settings page Refresh button. Clears the transient, refetches
     * once from the tenant json_schemas endpoint, and re-seeds the cache.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function handleRefresh(WP_REST_Request $request): WP_REST_Response
    {
        $options = WACC()->Mdp()->Schema()->refreshProfileImageFieldOptions();

        return new WP_REST_Response([
            'success' => true,
            'fields'  => $options,
        ], 200);
    }

    /**
     * Parse a stored composite ref into [schema_slug, field_slug].
     *
     * @param string|null $ref Raw option value.
     *
     * @return array{0: string, 1: string}|null Null when empty (No syncing) or malformed.
     */
    public static function parseFieldRef(?string $ref): ?array
    {
        if ($ref === null) {
            return null;
        }

        $ref = trim($ref);
        if ($ref === '' || !str_contains($ref, '|')) {
            return null;
        }

        [$schema, $field] = explode('|', $ref, 2);
        $schema = trim($schema);
        $field = trim($field);
        if ($schema === '' || $field === '') {
            return null;
        }

        return [$schema, $field];
    }

    /**
     * Build a composite ref from a schema and field slug.
     *
     * @param string $schema_slug
     * @param string $field_slug
     *
     * @return string
     */
    public static function formatFieldRef(string $schema_slug, string $field_slug): string
    {
        return $schema_slug . '|' . $field_slug;
    }

    /**
     * One-time migration of the legacy free-text slug pair into the composite ref.
     *
     * Reads the old acc_profile_picture_mdp_schema / acc_profile_picture_mdp_field
     * options and writes acc_profile_picture_mdp_field_ref. Guarded by a flag so
     * it runs once. Runs on admin_init (after the Carbon->HyperFields migration)
     * and on the WP_CLI init hook. An empty legacy pair is a valid terminal state
     * (No syncing default) and still sets the flag.
     */
    public function maybeMigrateLegacyRef(): void
    {
        if (get_option(self::MIGRATION_FLAG)) {
            return;
        }

        $existing = WACC()->getOption(self::OPTION_KEY, self::NO_SYNCING);
        if (is_string($existing) && $existing !== self::NO_SYNCING) {
            // Already configured manually (or migrated); just mark done.
            update_option(self::MIGRATION_FLAG, true);

            return;
        }

        $legacy_schema = $this->readLegacyOption('acc_profile_picture_mdp_schema');
        $legacy_field = $this->readLegacyOption('acc_profile_picture_mdp_field');

        if ($legacy_schema !== '' && $legacy_field !== '') {
            $this->writeMainOption(self::OPTION_KEY, self::formatFieldRef($legacy_schema, $legacy_field));
            WACC()->Log()->info('Migrated legacy profile image MDP slug pair into composite ref.', [
                'source'      => __CLASS__,
                'schema_slug' => $legacy_schema,
                'field_slug'  => $legacy_field,
            ]);
        }

        update_option(self::MIGRATION_FLAG, true);
    }

    /**
     * Read a legacy option from the HyperFields array, with a standalone
     * get_option() fallback for pre-HFMigration storage.
     */
    private function readLegacyOption(string $key): string
    {
        $value = WACC()->getOption($key, '');
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $fallback = get_option($key, '');

        return is_string($fallback) ? trim($fallback) : '';
    }

    /**
     * Write a single key into the wicket_acc_options array (HyperFields storage).
     */
    private function writeMainOption(string $key, string $value): void
    {
        $options = get_option(InitOptions::MAIN_OPTION_NAME, []);
        if (!is_array($options)) {
            $options = [];
        }

        $options[$key] = $value;
        update_option(InitOptions::MAIN_OPTION_NAME, $options);
    }

    /**
     * Render the profile-image MDP field picker (HyperFields CustomField).
     *
     * Outputs a grouped <select> (No syncing + one optgroup per schema slug)
     * plus a Refresh button. The Refresh button calls the refresh REST route
     * via the bundled admin JS, which rebuilds the select from the response.
     *
     * @param array  $field_data HyperFields field data (name, name_attr, label, ...).
     * @param mixed  $value      Currently stored composite ref.
     */
    public static function renderField(array $field_data, $value): void
    {
        $field_id = isset($field_data['name']) && is_string($field_data['name']) ? $field_data['name'] : self::OPTION_KEY;
        $name_attr = isset($field_data['name_attr']) && is_string($field_data['name_attr']) ? $field_data['name_attr'] : self::OPTION_KEY;
        $label = isset($field_data['label']) && is_string($field_data['label']) ? $field_data['label'] : __('Profile image MDP field', 'wicket-acc');
        $value = is_string($value) ? $value : '';

        $options = WACC()->Mdp()->Schema()->getCachedProfileImageFieldOptions();

        $rest_url = rest_url(self::REST_NAMESPACE . self::REST_ROUTE);
        $nonce = wp_create_nonce('wp_rest');

        $no_syncing_label = __('No syncing (keep image local)', 'wicket-acc');
        $refresh_label = __('Refresh fields', 'wicket-acc');
        $refreshing_label = __('Refreshing...', 'wicket-acc');
        $success_label = __('Field list refreshed.', 'wicket-acc');
        $error_label = __('Could not refresh the field list. Check the MDP connection and try again.', 'wicket-acc');
        $empty_label = __('No MDP fields are available for this tenant.', 'wicket-acc');
        $help = __('Choose which MDP additional-info field stores the uploaded profile image URL. "No syncing" keeps the image in WordPress only.', 'wicket-acc');

        // Drift flag: the stored ref is no longer in a populated (trustworthy)
        // cached field list. An empty list gives no signal (MDP may just be
        // unreachable), so we do not cry wolf in that case.
        $drifted = self::storedRefHasDrifted($value, $options);
        ?>
        <div class="hyperpress-field-wrapper">
            <div class="hyperpress-field-row">
                <div class="hyperpress-field-label">
                    <label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($label); ?></label>
                </div>
                <div class="hyperpress-field-input-wrapper" data-wicket-acc-mdp-fields>
                    <select id="<?php echo esc_attr($field_id); ?>"
                            name="<?php echo esc_attr($name_attr); ?>"
                            class="regular-text"
                            data-wicket-acc-mdp-fields-select
                            data-wicket-no-syncing-label="<?php echo esc_attr($no_syncing_label); ?>">
                        <option value="" <?php selected($value, ''); ?>><?php echo esc_html($no_syncing_label); ?></option>
                        <?php foreach ($options as $group) :
                            $schema_slug = isset($group['schema_slug']) && is_string($group['schema_slug']) ? $group['schema_slug'] : '';
                            $schema_label = isset($group['schema_label']) && is_string($group['schema_label']) ? $group['schema_label'] : $schema_slug;
                            $fields = isset($group['fields']) && is_array($group['fields']) ? $group['fields'] : [];
                            if ($schema_slug === '' || empty($fields)) {
                                continue;
                            }
                            ?>
                            <optgroup label="<?php echo esc_attr($schema_label); ?>">
                                <?php foreach ($fields as $field) :
                                    $field_slug = isset($field['slug']) && is_string($field['slug']) ? $field['slug'] : '';
                                    $field_label = isset($field['label']) && is_string($field['label']) ? $field['label'] : $field_slug;
                                    if ($field_slug === '') {
                                        continue;
                                    }
                                    $ref = self::formatFieldRef($schema_slug, $field_slug);
                                    ?>
                                    <option value="<?php echo esc_attr($ref); ?>" <?php selected($value, $ref); ?>>
                                        <?php echo esc_html($field_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>

                    <button type="button"
                            class="button-link"
                            style="display:inline-block; width:auto;"
                            data-wicket-acc-mdp-fields-refresh-button
                            data-rest-url="<?php echo esc_url($rest_url); ?>"
                            data-nonce="<?php echo esc_attr($nonce); ?>"
                            data-refreshing-label="<?php echo esc_attr($refreshing_label); ?>"
                            data-success-label="<?php echo esc_attr($success_label); ?>"
                            data-error-label="<?php echo esc_attr($error_label); ?>"
                            data-empty-label="<?php echo esc_attr($empty_label); ?>">
                        <?php echo esc_html($refresh_label); ?>
                    </button>
                    <span class="spinner" style="float:none; display:none;" data-wicket-acc-mdp-fields-spinner></span>
                    <span class="description" data-wicket-acc-mdp-fields-status style="display:none;"></span>

                    <?php if ($drifted) : ?>
                        <p class="description" style="color:#d63638;">
                            <?php echo esc_html(__('The selected field no longer exists in the MDP schema. Pick a valid field, or set "No syncing". Until then, uploads stay local and are not sent to the MDP.', 'wicket-acc')); ?>
                        </p>
                    <?php endif; ?>

                    <p class="description"><?php echo esc_html($help); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Sanitize/validate the composite ref at settings-save time.
     *
     * Empty is valid (No syncing). A non-empty ref must parse and must exist in
     * the live field list. On drift (field removed from MDP), keep the
     * previously stored value rather than wipe it. This runs only on save, never
     * on the upload path.
     *
     * @param mixed $value Submitted value.
     *
     * @return string Cleaned composite ref, or empty for No syncing.
     */
    public static function sanitizeField($value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return self::NO_SYNCING;
        }

        $parsed = self::parseFieldRef($value);
        if ($parsed === null) {
            return self::currentStoredRef();
        }

        [$schema_slug, $field_slug] = $parsed;
        if (self::refExistsInLiveList($schema_slug, $field_slug)) {
            return self::formatFieldRef($schema_slug, $field_slug);
        }

        WACC()->Log()->warning('Profile image MDP field ref not found in live list; keeping previous value.', [
            'source'   => __CLASS__,
            'submitted' => $value,
        ]);

        return self::currentStoredRef();
    }

    /**
     * Whether a composite ref exists in the current (cached) field list.
     */
    private static function refExistsInLiveList(string $schema_slug, string $field_slug): bool
    {
        return self::refInGroupedList(
            $schema_slug,
            $field_slug,
            WACC()->Mdp()->Schema()->getCachedProfileImageFieldOptions()
        );
    }

    /**
     * Pure membership check: is a schema|field pair present in a grouped list?
     *
     * Shared by the save-time check (refExistsInLiveList), the settings UI
     * drift notice (storedRefHasDrifted), and the upload-path guard
     * (configuredRefHasDrifted) so all three agree on what "in the list" means.
     *
     * @param string $schema_slug
     * @param string $field_slug
     * @param array  $list        Grouped options (schema_slug/schema_label/fields).
     */
    private static function refInGroupedList(string $schema_slug, string $field_slug, array $list): bool
    {
        foreach ($list as $group) {
            if (($group['schema_slug'] ?? '') !== $schema_slug) {
                continue;
            }
            foreach ($group['fields'] ?? [] as $field) {
                if (($field['slug'] ?? '') === $field_slug) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a given ref value has drifted out of a supplied (populated) list.
     *
     * Used by the settings UI. An empty list gives no signal (the MDP may be
     * unreachable), so it returns false to avoid a false alarm.
     *
     * @param string $value   Stored composite ref (raw option value).
     * @param array  $options Grouped field list already fetched for rendering.
     */
    private static function storedRefHasDrifted(string $value, array $options): bool
    {
        if ($value === '' || $options === []) {
            return false;
        }

        $parsed = self::parseFieldRef($value);
        if ($parsed === null) {
            return true;
        }

        return !self::refInGroupedList($parsed[0], $parsed[1], $options);
    }

    /**
     * Whether the stored ref has drifted out of a populated (trustworthy) cache.
     *
     * Upload-path guard. Reads the raw transient only: an empty/absent cache
     * gives no drift signal, so this check never triggers an MDP schemas call
     * and the upload path stays MDP-free in the worst case. When the cache is
     * populated and the stored ref is absent, the configured field has been
     * removed from the MDP and the caller should skip the write (degrade to
     * No syncing) instead of failing on every upload.
     */
    public static function configuredRefHasDrifted(): bool
    {
        $cached = get_transient(Mdp\Schema::PROFILE_IMAGE_FIELDS_TRANSIENT);
        if (!is_array($cached) || $cached === []) {
            return false;
        }

        return self::storedRefHasDrifted(self::currentStoredRef(), $cached);
    }

    /**
     * Read the currently stored composite ref from the options array.
     */
    private static function currentStoredRef(): string
    {
        $stored = WACC()->getOption(self::OPTION_KEY, self::NO_SYNCING);

        return is_string($stored) ? $stored : self::NO_SYNCING;
    }
}
