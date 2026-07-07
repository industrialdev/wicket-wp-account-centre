<?php

declare(strict_types=1);

namespace WicketAcc\Admin;

// No direct access
defined('ABSPATH') || exit;

/**
 * Migrate ACC options from Carbon Fields storage to HyperFields storage.
 *
 * Background: Carbon Fields stored each theme-option field as its own
 * wp_options row (<key> holds the raw value, _<key> holds CF's type metadata).
 * HyperFields stores all page fields in a single array option. This migrator
 * copies the raw CF values into the HF option so settings survive the
 * library swap without manual re-entry.
 *
 * Production safety:
 *   - Reads only against the old CF rows. Old data persists inert after the
 *     copy; nothing destructive happens to the source.
 *   - Idempotent: gated by a flag, safe to re-run.
 *   - Don't-overwrite-if-populated: if the HF option already has a value for
 *     a given key (e.g. a partial earlier run, or a value set directly via
 *     the new UI), the CF value is NOT clobbered.
 *   - Runs on admin_init (early, before any HF option read) and on WP_CLI
 *     init so the migration lands before the plugin reads its own settings.
 *
 * Scope: only the ACC main options (the ones registered in InitOptions).
 * Environment fields (wicket_admin_settings_*) live in the
 * shared wicket_settings array, which is already array-shaped, and are not
 * touched here.
 */
class HFMigration
{
    /**
     * Option name where HyperFields stores the ACC main options as one array.
     */
    public const HF_OPTION_NAME = 'wicket_acc_options';

    /**
     * Gate flag. Mirrors the existing wicket_acc_cf_migration_complete pattern.
     */
    public const MIGRATION_FLAG = 'wicket_acc_hf_migration_complete';

    /**
     * Gate flag for the checkbox-value normalization pass.
     *
     * Carbon Fields stores checked checkboxes as 'yes'; HyperFields expects
     * a boolean. Installs that ran the main migration before checkbox
     * normalization existed carry 'yes' values that the HF checkbox template
     * renders unchecked (checked() === '1') while the plugin's truthy guards
     * treat them as enabled. This flag tracks a one-shot fix-up that coerces
     * any stray truthy-string checkbox values to a proper boolean.
     */
    public const MIGRATION_NORMALIZE_FLAG = 'wicket_acc_hf_checkbox_normalized';

    /**
     * The checkbox-typed keys among the main options.
     *
     * Carbon stored these as 'yes'/'no' (or ''/1) strings; HyperFields stores
     * a boolean. Used by both the copy path and the normalization pass.
     */
    private const CHECKBOX_KEYS = [
        'acc_global-headerbanner',
    ];

    /**
     * The ACC main-option keys previously registered with Carbon Fields.
     *
     * Kept in sync with the fields registered in InitOptions::registerMainOptionsPage.
     * Order matches the registration order for traceability.
     */
    private const CF_MAIN_OPTION_KEYS = [
        'ac_localization',
        'acc_sidebar_location',
        'acc_profile_picture_size',
        'acc_profile_picture_default',
        'acc_profile_picture_mdp_schema',
        'acc_profile_picture_mdp_field',
        'acc_global-headerbanner',
    ];

    /**
     * Register the migration hook.
     */
    public function __construct()
    {
        // admin_init fires early on admin loads, before ACC reads its options
        // (Helpers reads on demand during request handling). Priority 5 keeps
        // us ahead of other admin_init listeners that might read settings.
        add_action('admin_init', [$this, 'run'], 5);

        // WP_CLI context has no admin_init; run on the cli init hook so a
        // first CLI request after the upgrade also migrates before reads.
        if (defined('\WP_CLI') && \WP_CLI) {
            add_action('cli_init', [$this, 'run'], 5);
        }
    }

    /**
     * Run the migration once.
     *
     * @return void
     */
    public function run(): void
    {
        // The checkbox-value normalization pass is independent of the main
        // copy migration. It must run on installs where the main flag was set
        // before normalization existed (stray 'yes' values from Carbon).
        if (!get_option(self::MIGRATION_NORMALIZE_FLAG)) {
            $this->normalizeCheckboxValues();
        }

        if (get_option(self::MIGRATION_FLAG)) {
            return;
        }

        $migrated = $this->migrateMainOptions();

        // Mark complete regardless of whether any values were copied: an empty
        // source (fresh install, never had Carbon) is still a valid terminal
        // state. Re-running would be a no-op anyway, but the flag prevents
        // every future admin_init from re-scanning wp_options.
        update_option(self::MIGRATION_FLAG, true);

        if ($migrated !== []) {
            /*
             * Fires after the migration actually copies values.
             *
             * Only fires when at least one key was copied, so listeners can
             * rely on a non-empty payload.
             *
             * @param array $migrated Map of [key => value] for keys copied.
             */
            do_action('wicket_acc_hf_migration_complete', $migrated);
        }
    }

    /**
     * Copy each CF main-option value into the HF array option.
     *
     * @return array Map of [key => value] for keys actually copied (skipped
     *               keys — already populated in HF, or absent in CF — are omitted).
     */
    private function migrateMainOptions(): array
    {
        $hf_options = get_option(self::HF_OPTION_NAME, []);
        if (!is_array($hf_options)) {
            $hf_options = [];
        }

        $copied = [];

        foreach (self::CF_MAIN_OPTION_KEYS as $key) {
            // Don't overwrite if HF already has a non-null value for this key.
            // Handles partial earlier runs and values set directly via the new
            // UI before this migrator first ran. A null entry is treated as
            // unset so a cleared field still receives the CF value.
            if (array_key_exists($key, $hf_options) && $hf_options[$key] !== null) {
                continue;
            }

            $cf_value = $this->readCarbonValue($key);

            // Skip absent CF values — nothing to migrate, leave the HF key
            // unset. NOTE: this does NOT mean InitOptions' field default will
            // apply on read. Helpers::getOption returns the CALLER-SUPPLIED
            // default when a key is absent, never the registered field default.
            // Callers are responsible for passing their own fallback.
            if ($cf_value === null) {
                continue;
            }

            // Normalize checkbox values: Carbon stored 'yes'/'1'/etc.; HF
            // expects a boolean so the template's checked() and the plugin's
            // truthy guards agree.
            if (in_array($key, self::CHECKBOX_KEYS, true)) {
                $cf_value = $this->normalizeCheckboxValue($cf_value);
            }

            $hf_options[$key] = $cf_value;
            $copied[$key] = $cf_value;
        }

        // Only write if we actually copied something. A no-op write would
        // needlessly dirty the options table and bump the option's autoload
        // revision (irrelevant here, but cheap to avoid).
        //
        // Race note: this read-modify-write of wicket_acc_options is unlocked.
        // Two near-simultaneous first-touch requests (e.g. admin page load
        // racing wp-cron right after deploy) can both build from the same
        // pre-migration snapshot and one can clobber a human edit saved in
        // the narrow window between. Window is milliseconds and exists once
        // (first run only), so the trade-off is accepted over adding a lock.
        if ($copied === []) {
            return [];
        }

        update_option(self::HF_OPTION_NAME, $hf_options);

        return $copied;
    }

    /**
     * Read a Carbon Fields theme-option value from its raw wp_options row.
     *
     * Carbon's Theme_Options_Datastore stores each simple-root field at the
     * underscore-prefixed key (_<field_name>) — confirmed against the live
     * CF source (Key_Toolset::KEY_PREFIX = '_') and the production DB. The
     * bare <key> is read as a defensive fallback for any non-standard
     * storage variant but is not where CF normally puts the value.
     *
     * @param string $key Field base name (no underscore prefix).
     * @return mixed|null The raw CF value, or null if neither row exists.
     */
    private function readCarbonValue(string $key): mixed
    {
        // Primary: Carbon's standard theme-options storage location.
        $raw = get_option('_' . $key, null);

        if ($raw === null) {
            // Defensive fallback: some installs or field types may have
            // written to the bare key. Harmless if absent.
            $raw = get_option($key, null);
        }

        return $raw;
    }

    /**
     * One-shot fix-up: coerce stray Carbon-format checkbox values in the HF
     * option array to proper booleans.
     *
     * Runs on installs where the main migration copied 'yes' (Carbon's
     * checked sentinel) before this normalization existed. The checkbox
     * template renders checked() === '1', so 'yes' shows unchecked in the UI
     * while truthy guards treat it as enabled, producing the visual/logic
     * mismatch (e.g. the Global Header submenu showing with the box off).
     *
     * Idempotent and gated by MIGRATION_NORMALIZE_FLAG so it runs exactly
     * once. Only touches the checkbox-typed keys; leaves everything else.
     *
     * @return void
     */
    private function normalizeCheckboxValues(): void
    {
        update_option(self::MIGRATION_NORMALIZE_FLAG, true);

        $hf_options = get_option(self::HF_OPTION_NAME, []);
        if (!is_array($hf_options)) {
            return;
        }

        $changed = false;

        foreach (self::CHECKBOX_KEYS as $key) {
            if (!array_key_exists($key, $hf_options)) {
                continue;
            }

            $normalized = $this->normalizeCheckboxValue($hf_options[$key]);

            if ($hf_options[$key] !== $normalized) {
                $hf_options[$key] = $normalized;
                $changed = true;
            }
        }

        if ($changed) {
            update_option(self::HF_OPTION_NAME, $hf_options);
        }
    }

    /**
     * Coerce a Carbon-format checkbox value to a HyperFields boolean.
     *
     * Carbon Fields stored checked as 'yes' (and sometimes '1' or true);
     * unchecked as '' or 'no'. HyperFields stores a plain boolean. Map all
     * common truthy representations to true and everything else to false so
     * the checkbox template's checked($value, '1')-equivalent and the
     * plugin's truthy guards agree.
     *
     * @param mixed $value Raw value from Carbon or the HF array.
     * @return bool
     */
    private function normalizeCheckboxValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        // Carbon's checked sentinel is 'yes'. Also accept the usual WP/HTML
        // truthy strings so this is robust against any storage variant.
        return in_array(
            strtolower((string) $value),
            ['yes', '1', 'true', 'on'],
            true,
        );
    }
}
