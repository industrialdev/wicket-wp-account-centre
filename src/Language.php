<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Language file for Wicket Account Centre Plugins.
 *
 * @version  1.0.0
 */
class Language extends WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('init', [$this, 'load_textdomain']);
    }

    /**
     * Load text domain.
     */
    public function load_textdomain()
    {
        if (!is_admin() && function_exists('load_plugin_textdomain')) {
            $plugin_rel_path = dirname(plugin_basename(__FILE__)) . '/languages/';
            load_plugin_textdomain('wicket-acc', false, $plugin_rel_path);
        }
    }

    /**
     * Get current language ISO code (two-letter code).
     *
     * This method is compatible with WPML, Polylang, and the default WordPress user locale,
     * providing a reliable way to determine the active language.
     *
     * @return string The two-letter ISO language code (e.g., 'en', 'fr'). Defaults to 'en'.
     */
    public function getCurrentLanguage(): string
    {
        // 1. Check WPML
        if (defined('ICL_SITEPRESS_VERSION')) {
            // Attempt 1.1: via filter
            $lang = apply_filters('wpml_current_language', null);
            if (is_string($lang) && strlen($lang) === 2) {
                return $lang;
            }

            // Attempt 1.2: via global $sitepress object
            global $sitepress;
            if (isset($sitepress) && is_object($sitepress) && method_exists($sitepress, 'get_current_language')) {
                $lang = $sitepress->get_current_language();
                if (is_string($lang) && strlen($lang) === 2) {
                    return $lang;
                }
            }

            // Attempt 1.3: via ICL_LANGUAGE_CODE constant
            if (defined('ICL_LANGUAGE_CODE')) {
                $lang = (string) ICL_LANGUAGE_CODE;
                if (strlen($lang) === 2) {
                    return $lang;
                }
            }
        }

        // 2. Check Polylang
        if (function_exists('pll_current_language')) {
            /** @disregard P1010 Undefined function 'WicketAcc\pll_current_language' */
            $lang = pll_current_language('slug'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
            if (is_string($lang)) {
                return substr($lang, 0, 2);
            }
        }

        // 3. WP user locale or default WP locale
        $locale = get_user_locale();
        if (empty($locale)) {
            $locale = get_locale();
        }

        if (is_string($locale)) {
            return substr($locale, 0, 2);
        }

        // 4. Default fallback
        return 'en';
    }
}
