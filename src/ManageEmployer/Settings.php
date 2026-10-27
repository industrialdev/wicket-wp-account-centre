<?php

declare(strict_types=1);

namespace WicketAcc\ManageEmployer;

// No direct access
defined('ABSPATH') || exit;

/**
 * Canonical field schema and typed settings for the wicket-acc/manage-employer block.
 *
 * Single source of truth for the settings inventory: the block definition builds
 * its HyperFields controls from schema(), the REST layer resolves client payloads
 * through resolve(), and the Warden contract test pins the registered schema to
 * this list. Do not maintain a second field list anywhere else.
 *
 * @see atlas://plans/feature-acc-manage-employer-block.md
 */
final class Settings
{
    public const TAB_GENERAL = 'general';
    public const TAB_FRONT_END = 'front_end';

    public const MODE_ALL = 'all';
    public const MODE_ACTIVE = 'active';
    public const MODE_INACTIVE = 'inactive';

    private const HMAC_CONTEXT = 'wicket-acc/manage-employer/settings';

    private const TOKEN_VERSION = 'me1';

    /**
     * Authoritative settings inventory.
     *
     * Each entry: label, type, tab, section, default, and for selects the closed
     * option list. dynamic_options marks the two fields whose option lists come
     * from live MDP providers at registration time (transient-cached; the block
     * class owns those providers).
     *
     * Conditional rules implement story 1.3 and win over the settings-map draft.
     *
     * @return array<string, array{label: string, type: string, tab: string, section: string, default: mixed, options?: array<int|string, string>, dynamic_options?: bool, conditional_logic?: array}>
     */
    public static function schema(): array
    {
        // Story 1.3: inactive (read-only) mode hides the end action, the
        // removal alert, and the primary controls.
        $active_mode_rule = self::condition('display_membership_status_filter', '!=', self::MODE_INACTIVE);

        return [
            'org_types' => [
                'label' => __('Organization Types', 'wicket-acc'),
                'type' => 'multiselect',
                'tab' => self::TAB_GENERAL,
                'section' => 'organization_display',
                'default' => [],
                'dynamic_options' => true,
            ],
            'relationship_types_on_create' => [
                'label' => __('Relationship Type(s) on Organization Creation/Selection', 'wicket-acc'),
                'type' => 'multiselect',
                'tab' => self::TAB_GENERAL,
                'section' => 'organization_display',
                'default' => ['employee'],
                'dynamic_options' => true,
            ],
            'display_membership_status_filter' => [
                'label' => __('Display Organizations with Membership Status', 'wicket-acc'),
                'type' => 'select',
                'tab' => self::TAB_GENERAL,
                'section' => 'organization_display',
                'default' => self::MODE_ALL,
                'options' => [
                    self::MODE_ALL => __('All', 'wicket-acc'),
                    self::MODE_ACTIVE => __('Active only', 'wicket-acc'),
                    self::MODE_INACTIVE => __('Inactive (read-only)', 'wicket-acc'),
                ],
            ],
            'relationship_status_filter' => [
                'label' => __('Display Relationship Statuses', 'wicket-acc'),
                'type' => 'select',
                'tab' => self::TAB_GENERAL,
                'section' => 'organization_display',
                'default' => self::MODE_ALL,
                'options' => [
                    self::MODE_ALL => __('All', 'wicket-acc'),
                    self::MODE_ACTIVE => __('Active only', 'wicket-acc'),
                    self::MODE_INACTIVE => __('Inactive only', 'wicket-acc'),
                ],
            ],
            'org_name_singular' => [
                'label' => __('Organization Name (Singular)', 'wicket-acc'),
                'type' => 'text',
                'tab' => self::TAB_GENERAL,
                'section' => 'labeling',
                // Text defaults stay literal English: they ride inside the
                // signed token, so a locale switch between render and REST
                // must not change verification.
                'default' => 'Employer',
            ],
            'org_name_plural' => [
                'label' => __('Organization Name (Plural)', 'wicket-acc'),
                'type' => 'text',
                'tab' => self::TAB_GENERAL,
                'section' => 'labeling',
                'default' => 'Employers',
            ],
            'disable_create' => [
                'label' => __('Disable Creating New Organizations', 'wicket-acc'),
                'type' => 'checkbox',
                'tab' => self::TAB_GENERAL,
                'section' => 'creation',
                'default' => false,
            ],
            'create_org_types' => [
                'label' => __('Organization Type When Creating a New Organization', 'wicket-acc'),
                'type' => 'multiselect',
                'tab' => self::TAB_GENERAL,
                'section' => 'creation',
                'default' => [],
                'dynamic_options' => true,
                'conditional_logic' => self::condition('disable_create', '!=', true),
            ],
            'display_org_type' => [
                'label' => __('Display Organization Type', 'wicket-acc'),
                'type' => 'checkbox',
                'tab' => self::TAB_FRONT_END,
                'section' => 'card_content',
                'default' => false,
            ],
            'display_org_address' => [
                'label' => __('Display Organization Address', 'wicket-acc'),
                'type' => 'checkbox',
                'tab' => self::TAB_FRONT_END,
                'section' => 'card_content',
                'default' => false,
            ],
            'org_address_format' => [
                'label' => __('Address Format', 'wicket-acc'),
                'type' => 'select',
                'tab' => self::TAB_FRONT_END,
                'section' => 'card_content',
                'default' => 'full',
                'options' => [
                    'full' => __('Full address', 'wicket-acc'),
                    'city_province_country' => __('City, Province, Country', 'wicket-acc'),
                    'city_province' => __('City, Province', 'wicket-acc'),
                ],
                'conditional_logic' => self::condition('display_org_address', '=', true),
            ],
            'display_membership_status' => [
                'label' => __('Display Membership Status', 'wicket-acc'),
                'type' => 'checkbox',
                'tab' => self::TAB_FRONT_END,
                'section' => 'card_content',
                'default' => true,
            ],
            'display_relationship_dates' => [
                'label' => __('Display Relationship Start/End Dates', 'wicket-acc'),
                'type' => 'checkbox',
                'tab' => self::TAB_FRONT_END,
                'section' => 'card_content',
                'default' => true,
            ],
            'orgs_per_page' => [
                'label' => __('Organizations per Page', 'wicket-acc'),
                'type' => 'number',
                'tab' => self::TAB_FRONT_END,
                'section' => 'pagination',
                'default' => 5,
            ],
            'hide_end_button' => [
                'label' => __('Hide End Button', 'wicket-acc'),
                'type' => 'checkbox',
                'tab' => self::TAB_FRONT_END,
                'section' => 'actions',
                'default' => false,
                'conditional_logic' => $active_mode_rule,
            ],
            'removal_alert_enabled' => [
                'label' => __('Display Removal Alert Message', 'wicket-acc'),
                'type' => 'checkbox',
                'tab' => self::TAB_FRONT_END,
                'section' => 'actions',
                'default' => true,
                'conditional_logic' => $active_mode_rule,
            ],
            'removal_alert_title' => [
                'label' => __('Removal Alert Title', 'wicket-acc'),
                'type' => 'text',
                'tab' => self::TAB_FRONT_END,
                'section' => 'actions',
                'default' => 'Remove this employer?',
                'conditional_logic' => self::condition('removal_alert_enabled', '=', true),
            ],
            'removal_alert_description' => [
                'label' => __('Removal Alert Description', 'wicket-acc'),
                'type' => 'textarea',
                'tab' => self::TAB_FRONT_END,
                'section' => 'actions',
                'default' => 'This will end your relationship with this organization.',
                'conditional_logic' => self::condition('removal_alert_enabled', '=', true),
            ],
            'enable_primary' => [
                'label' => __('Enable Primary Organization', 'wicket-acc'),
                'type' => 'checkbox',
                'tab' => self::TAB_FRONT_END,
                'section' => 'primary_organization',
                'default' => true,
                'conditional_logic' => $active_mode_rule,
            ],
            'hide_primary_tag' => [
                'label' => __('Hide Primary Organization Tag', 'wicket-acc'),
                'type' => 'checkbox',
                'tab' => self::TAB_FRONT_END,
                'section' => 'primary_organization',
                'default' => false,
                'conditional_logic' => self::condition('enable_primary', '=', true),
            ],
            'hide_set_primary_button' => [
                'label' => __('Hide "Set as Primary" Button', 'wicket-acc'),
                'type' => 'checkbox',
                'tab' => self::TAB_FRONT_END,
                'section' => 'primary_organization',
                'default' => false,
                'conditional_logic' => self::condition('enable_primary', '=', true),
            ],
            'no_results_message' => [
                'label' => __('No Results Found Message', 'wicket-acc'),
                'type' => 'text',
                'tab' => self::TAB_FRONT_END,
                'section' => 'empty_state',
                'default' => 'No employers found.',
            ],
        ];
    }

    /**
     * Default value per field name.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return array_map(static fn (array $meta): mixed => $meta['default'], self::schema());
    }

    /**
     * Project template scope onto the settings keys. The hyperblocks renderer
     * extracts each attribute as its own variable, so the template hands
     * get_defined_vars() here instead of maintaining a second field list.
     *
     * @param array<string, mixed> $vars
     * @return array<string, mixed>
     */
    public static function pick(array $vars): array
    {
        return array_intersect_key($vars, self::schema());
    }

    /**
     * Resolve a raw attribute payload into typed settings.
     *
     * Rules from the plan: blank numbers fall back to the default; number fields
     * arrive as floats and are cast; an unknown select value silently coerces to
     * the first option; blank multi-selects mean all-selections downstream.
     * Gutenberg does not serialize default-equal values, so absent keys resolve
     * to their defaults.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function resolve(array $raw): array
    {
        $resolved = [];

        foreach (self::schema() as $name => $meta) {
            $value = array_key_exists($name, $raw) ? $raw[$name] : null;

            $resolved[$name] = match ($meta['type']) {
                'number' => self::resolveNumber($value, (int) $meta['default']),
                'checkbox' => self::resolveCheckbox($value, (bool) $meta['default']),
                'select' => self::resolveSelect($value, $meta),
                'multiselect' => self::resolveMultiselect($value),
                default => self::resolveText($value, (string) $meta['default']),
            };
        }

        return $resolved;
    }

    /**
     * HMAC-sign the resolved settings for embedding in block markup. The
     * context and version prefix domain-separate the wp_salt key so tokens
     * from other surfaces never verify here.
     *
     * @param array<string, mixed> $resolved
     */
    public static function sign(array $resolved): string
    {
        return self::TOKEN_VERSION . '.' . hash_hmac('sha256', self::HMAC_CONTEXT . "\0" . self::canonicalPayload($resolved), wp_salt('auth'));
    }

    /**
     * Verify an embedded settings token against the resolved settings.
     *
     * @param array<string, mixed> $resolved
     */
    public static function verify(string $token, array $resolved): bool
    {
        if ($token === '') {
            return false;
        }

        return hash_equals(self::sign($resolved), $token);
    }

    /**
     * Deterministic serialization for HMAC: key order must not change the token.
     *
     * @param array<string, mixed> $resolved
     */
    private static function canonicalPayload(array $resolved): string
    {
        $copy = $resolved;
        self::sortRecursive($copy);

        $encoded = wp_json_encode($copy);

        return $encoded === false ? '' : (string) $encoded;
    }

    private static function sortRecursive(array &$array): void
    {
        ksort($array);

        foreach ($array as &$value) {
            if (is_array($value)) {
                self::sortRecursive($value);
            }
        }
        unset($value);

        // Scalar lists sort too: the token must not depend on the order the
        // caller happened to build the array in.
        if (array_is_list($array) && ! in_array(false, array_map('is_scalar', $array), true)) {
            sort($array);
        }
    }

    /**
     * @param array{options?: array<int|string, string>} $meta
     */
    private static function resolveSelect(mixed $value, array $meta): string
    {
        $options = array_keys($meta['options'] ?? []);

        if ($options === []) {
            return '';
        }

        $candidate = is_string($value) ? $value : '';

        return in_array($candidate, $options, true) ? $candidate : (string) $options[0];
    }

    private static function resolveMultiselect(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn ($slug): bool => is_string($slug) && $slug !== ''));
    }

    private static function resolveNumber(mixed $value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return (int) round((float) $value);
    }

    private static function resolveCheckbox(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private static function resolveText(mixed $value, string $default): string
    {
        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        return $value;
    }

    /**
     * One-condition visibility rule in the HyperFields canonical shape.
     *
     * HyperFields logic is show-when: a field with rules is visible only while
     * a group matches, so "hidden in mode X" must be written as "shown when
     * field != X".
     */
    private static function condition(string $field, string $operator, bool|string $value): array
    {
        return [
            'relation' => 'AND',
            'conditions' => [
                [
                    'field' => $field,
                    'operator' => $operator,
                    'value' => $value,
                ],
            ],
        ];
    }
}
