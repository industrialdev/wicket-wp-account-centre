<?php

/**
 * Config Service for handling configuration.
 */

namespace WicketORM\Services;

// Exit if accessed directly.
if (!defined('ABSPATH') && !defined('WICKET_DOING_TESTS')) {
    exit;
}

/**
 * Handles configuration for the application.
 */
class ConfigService
{
    /**
     * @var array|null Static config cache shared across all instances.
     * Used by getConfig() for services that don't have ConfigService injected.
     */
    private static ?array $staticConfig = null;

    /**
     * Get the full config array via static accessor.
     *
     * Use this in services that don't have ConfigService injected.
     * Caches the result across all calls within a request.
     *
     * @return array
     */
    public static function getConfig(): array
    {
        if (self::$staticConfig === null) {
            self::$staticConfig = \WicketORM\Config\OrgManConfig::get();
        }

        return self::$staticConfig;
    }

    /**
     * Clear the static config cache. For test isolation only.
     *
     * @return void
     */
    public static function resetCache(): void
    {
        self::$staticConfig = null;
    }

    /**
     * Get the full config array, cached per instance.
     *
     * Delegates to the static cache so all access paths share one result.
     *
     * @return array
     */
    public function getFullConfig(): array
    {
        return self::getConfig();
    }

    /**
     * Retrieve a configuration value using dot notation.
     *
     * Does NOT apply per-field WordPress filters. Use the typed getter methods
     * (e.g. isAdditionalSeatsEnabled) for paths that have filter hooks.
     *
     * @param string $key     Dot-notation path (e.g. 'membership.strategy').
     * @param mixed  $default Default fallback value.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $config = $this->getFullConfig();
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Get the current roster management mode.
     *
     * @return string The current roster mode.
     */
    public function getRosterMode()
    {
        $config = $this->getFullConfig();
        $default_strategy = $config['membership']['strategy'] ?? 'cascade';

        return $default_strategy;
    }

    /**
     * Check if additional seats functionality is enabled.
     *
     * @return bool True if additional seats functionality is enabled.
     */
    public function isAdditionalSeatsEnabled()
    {
        $config = $this->getFullConfig();
        $default_enabled = $config['integrations']['additional_seats']['enabled'] ?? false;

        return apply_filters('wicket/org-roster/additional_seats_enabled', $default_enabled);
    }

    /**
     * Get the SKU for additional seats product.
     *
     * @return string The SKU for the additional seats product.
     */
    public function getAdditionalSeatsSku()
    {
        $config = $this->getFullConfig();
        $default_sku = $config['integrations']['additional_seats']['sku'] ?? 'additional-seats';

        return apply_filters('wicket/org-roster/additional_seats_sku', $default_sku);
    }

    /**
     * Get the SKU for additional seats discount product.
     *
     * @return string The SKU for the additional seats discount product.
     */
    public function getAdditionalSeatsDiscountSku()
    {
        $config = $this->getFullConfig();
        $default_sku = $config['integrations']['additional_seats']['discount_sku'] ?? 'corporate-seat-discount';

        return apply_filters('wicket/org-roster/additional_seats_discount_sku', $default_sku);
    }

    /**
     * Get the Gravity Form ID for additional seats purchase.
     *
     * @return int The Gravity Form ID.
     */
    public function getAdditionalSeatsFormId()
    {
        $config = $this->getFullConfig();
        $default_form_id = $config['integrations']['additional_seats']['form_id'] ?? 0;

        if ((int) $default_form_id === 0 && function_exists('wicket_gf_get_form_id_by_slug')) {
            $slug = $config['integrations']['additional_seats']['form_slug'] ?? 'additional-seats';
            $slug = is_string($slug) ? trim($slug) : '';
            if ($slug !== '') {
                $detected_form_id = wicket_gf_get_form_id_by_slug($slug);
                $default_form_id = $detected_form_id ? (int) $detected_form_id : 0;
            }
        }

        return apply_filters('wicket/org-roster/additional_seats_form_id', (int) $default_form_id);
    }

    /**
     * Get a localized Gravity Form ID for the current language.
     *
     * @param int $form_id The base Gravity Form ID.
     * @return int The localized form ID.
     */
    public function getLocalizedFormId(int $form_id): int
    {
        $form_id = absint($form_id);
        if ($form_id === 0) {
            return 0;
        }

        if (function_exists('wicket_is_multilang_active') && wicket_is_multilang_active()) {
            $current_lang = function_exists('wicket_get_current_language') ? wicket_get_current_language() : null;
            if ($current_lang && function_exists('apply_filters')) {
                $element_type = defined('ICL_GRAVITY_FORM_ELEMENT_TYPE') ? ICL_GRAVITY_FORM_ELEMENT_TYPE : 'gravity_form';
                $translated_id = apply_filters('wpml_object_id', $form_id, $element_type, false, $current_lang);
                if (empty($translated_id) && $element_type !== 'gf_form') {
                    $translated_id = apply_filters('wpml_object_id', $form_id, 'gf_form', false, $current_lang);
                }
                if (!empty($translated_id)) {
                    $form_id = (int) $translated_id;
                }
            }
        }

        return (int) $form_id;
    }

    /**
     * Get the Gravity Form ID for additional seats, localized to current language.
     *
     * @return int The localized Gravity Form ID.
     */
    public function getAdditionalSeatsFormIdForCurrentLanguage(): int
    {
        return $this->getLocalizedFormId($this->getAdditionalSeatsFormId());
    }

    /**
     * Get additional seats minimum quantity.
     *
     * @return int The minimum quantity.
     */
    public function getAdditionalSeatsMinQuantity()
    {
        $config = $this->getFullConfig();
        $default_min_quantity = $config['integrations']['additional_seats']['min_quantity'] ?? 1;

        return apply_filters('wicket/org-roster/additional_seats_min_quantity', $default_min_quantity);
    }

    /**
     * Get additional seats maximum quantity.
     *
     * @return int The maximum quantity.
     */
    public function getAdditionalSeatsMaxQuantity()
    {
        $config = $this->getFullConfig();
        $default_max_quantity = $config['integrations']['additional_seats']['max_quantity'] ?? 100;

        return apply_filters('wicket/org-roster/additional_seats_max_quantity', $default_max_quantity);
    }

    /**
     * Whether the multi-tier additional-seats flow is active.
     *
     * Tier mode is opt-in. It activates when the 'tier_mode' flag is explicitly true OR when
     * 'tier_skus' contains at least one tier-to-SKU mapping. When active the flow resolves one
     * WooCommerce product per membership tier slug; when inactive the legacy single-SKU path is
     * used (preserving behaviour for ASAE/CITT/NJBIA/OSPE and similar sites).
     *
     * @return bool
     */
    public function isAdditionalSeatsTierMode()
    {
        $config = $this->getFullConfig();
        $tier_mode = (bool) ($config['integrations']['additional_seats']['tier_mode'] ?? false);
        $tier_skus = $this->getAdditionalSeatsTierSkus();
        $default_active = $tier_mode || !empty($tier_skus);

        return (bool) apply_filters('wicket/org-roster/additional_seats_tier_mode', $default_active);
    }

    /**
     * Get the tier-to-SKU map for multi-tier additional seats.
     *
     * Keys are membership tier slugs (the 'memberships' resource slug), values are WooCommerce
     * product SKUs. When empty the flow falls back to deriving a SKU per tier as
     * '{additional_seats_sku}-{tier-slug}' via getAdditionalSeatsTierSkuForSlug().
     *
     * @return array<string,string> Map of tier slug => product SKU.
     */
    public function getAdditionalSeatsTierSkus()
    {
        $config = $this->getFullConfig();
        $tier_skus = $config['integrations']['additional_seats']['tier_skus'] ?? [];
        $tier_skus = is_array($tier_skus) ? $tier_skus : [];

        // Normalize keys/values to trimmed strings.
        $normalized = [];
        foreach ($tier_skus as $tier_slug => $sku) {
            $tier_slug = is_string($tier_slug) ? trim($tier_slug) : '';
            $sku = is_string($sku) ? trim($sku) : '';
            if ($tier_slug !== '' && $sku !== '') {
                $normalized[$tier_slug] = $sku;
            }
        }

        return apply_filters('wicket/org-roster/additional_seats_tier_skus', $normalized);
    }

    /**
     * Get the query parameter / GF hidden field name used to carry the tier slug.
     *
     * Default 'tier-slug'.
     *
     * @return string
     */
    public function getAdditionalSeatsTierSlugField()
    {
        $config = $this->getFullConfig();
        $field = $config['integrations']['additional_seats']['tier_slug_field'] ?? 'tier-slug';
        $field = is_string($field) ? trim($field) : '';
        if ($field === '') {
            $field = 'tier-slug';
        }

        return apply_filters('wicket/org-roster/additional_seats_tier_slug_field', $field);
    }

    /**
     * Resolve the WooCommerce product SKU for a given membership tier slug.
     *
     * Uses an explicit tier_skus mapping when present; otherwise derives the SKU as
     * '{additional_seats_sku}-{tier-slug}' so a site only needs to create WooCommerce products
     * following that convention.
     *
     * @param string $tier_slug Membership tier slug.
     * @return string|null Resolved SKU, or null when the tier slug is empty.
     */
    public function getAdditionalSeatsTierSkuForSlug($tier_slug)
    {
        $tier_slug = is_string($tier_slug) ? trim($tier_slug) : '';
        if ($tier_slug === '') {
            return null;
        }

        $tier_skus = $this->getAdditionalSeatsTierSkus();
        if (isset($tier_skus[$tier_slug]) && $tier_skus[$tier_slug] !== '') {
            return $tier_skus[$tier_slug];
        }

        $prefix = $this->getAdditionalSeatsSku();
        $prefix = is_string($prefix) ? trim($prefix) : '';
        if ($prefix === '') {
            $prefix = 'additional-seats';
        }

        return $prefix . '-' . $tier_slug;
    }

    /**
     * Reverse-lookup: resolve the membership tier slug for a given WooCommerce product SKU.
     *
     * Used on order processing to classify a line item as a tier-specific seat product. Returns
     * null when the SKU is not recognised as a tier seat product (either tier mode is off or the
     * SKU does not map to a configured/derived tier).
     *
     * @param string $sku WooCommerce product SKU.
     * @return string|null Tier slug, or null.
     */
    public function getAdditionalSeatsTierSlugForSku($sku)
    {
        $sku = is_string($sku) ? trim($sku) : '';
        if ($sku === '') {
            return null;
        }

        $tier_skus = $this->getAdditionalSeatsTierSkus();
        foreach ($tier_skus as $tier_slug => $tier_sku) {
            if ($tier_sku === $sku) {
                return $tier_slug;
            }
        }

        // Derived-SKU fallback ONLY when no explicit tier_skus map is configured. With an
        // explicit map present (S1), any product whose SKU merely starts with the prefix would
        // otherwise false-positive as a tier seat (e.g. a future 'additional-seats-bundle').
        // Flag-only tier mode (empty map) is the only case that relies on derived SKUs.
        if (!empty($tier_skus)) {
            return null;
        }

        $prefix = $this->getAdditionalSeatsSku();
        $prefix = is_string($prefix) ? trim($prefix) : '';
        if ($prefix === '') {
            $prefix = 'additional-seats';
        }
        $prefix_dash = $prefix . '-';
        if (str_starts_with($sku, $prefix_dash)) {
            $candidate = substr($sku, strlen($prefix_dash));
            $candidate = is_string($candidate) ? trim($candidate) : '';
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Get allowed document types.
     *
     * @return array Array of allowed document file types.
     */
    public function getAllowedDocumentTypes()
    {
        $config = $this->getFullConfig();
        $default_types = $config['integrations']['documents']['allowed_types'] ?? [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif',
        ];

        return apply_filters('wicket/org-roster/allowed_document_types', $default_types);
    }

    /**
     * Get maximum document size.
     *
     * @return int Maximum document size in bytes.
     */
    public function getMaxDocumentSize()
    {
        $config = $this->getFullConfig();
        $default_size = $config['integrations']['documents']['max_size'] ?? (10 * 1024 * 1024); // 10MB default

        return apply_filters('wicket/org-roster/max_document_size', $default_size);
    }

    /**
     * Get business info seat limit information.
     *
     * @return string|null Custom seat limit information or null.
     */
    public function getBusinessInfoSeatLimitInfo()
    {
        $config = $this->getFullConfig();
        $default_info = $config['integrations']['business_info']['seat_limit_info'] ?? null;

        return apply_filters('wicket/org-roster/business_info_seat_limit', $default_info);
    }

    /**
     * Get the supplemental members page URL.
     *
     * @param string $org_uuid The organization UUID.
     * @return string The URL for the supplemental members page.
     */
    public function getSupplementalMembersUrl($org_uuid = '')
    {
        // Find the my-account CPT page with slug 'supplemental-members'
        $args = [
            'post_type' => 'my-account',
            'name' => 'supplemental-members',
            'numberposts' => 1,
        ];

        $posts = get_posts($args);

        if (!empty($posts)) {
            $post_id = $posts[0]->ID;

            // Only attempt multilingual translation if multilingual plugin is active
            if (function_exists('wicket_is_multilang_active') && wicket_is_multilang_active()) {
                // Get current language
                $current_lang = function_exists('wicket_get_current_language') ? wicket_get_current_language() : null;

                // Get translated post ID if language is available
                if ($current_lang && function_exists('apply_filters')) {
                    $translated_post_id = apply_filters('wpml_object_id', $post_id, 'my-account', false, $current_lang);
                    if ($translated_post_id) {
                        $post_id = $translated_post_id;
                    }
                }
            }

            $base_url = get_permalink($post_id);
        } else {
            return '';
        }

        if (!empty($org_uuid)) {
            return add_query_arg('org_uuid', $org_uuid, $base_url);
        }

        return $base_url;
    }
}
