<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Helpers file of Module.
 *
 * @version  1.0.0
 */
class Helpers extends WicketAcc
{
    /**
     * Get current person (MDP).
     *
     * Example:
     * $person = WACC()->getCurrentPerson();
     *
     * @return object $person
     */
    public function getCurrentPerson()
    {
        return wicket_current_person();
    }

    /**
     * Get ACC slug localization option.
     *
     * Option from Base Plugin Settings
     *
     * @return string
     */
    public function getAccSlug()
    {
        // Prefer the shared helper for current language resolution.
        if (function_exists('wicket_get_current_language')) {
            $current_language = wicket_get_current_language();
            if (isset($this->acc_index_slugs[$current_language])) {
                return $this->acc_index_slugs[$current_language];
            }
        }

        // Fallback to English if no match.
        return $this->acc_index_slugs['en'];
    }

    /**
     * Get ACC name localization option.
     *
     * Account Centre
     * Account Center
     *
     * Option from Base Plugin Settings
     *
     * @return string
     */
    public function getAccName()
    {
        // Use centralized option helper (CF preferred, ACF fallback)
        $locale = $this->getOption('ac_localization', '');

        if (empty($locale)) {
            return __('Account Centre', 'wicket-acc');
        }

        // Check if returned value is a valid and allowed slug
        if (!in_array($locale, ['account-centre', 'account-center'])) {
            return __('Account Centre', 'wicket-acc');
        }

        // Check if we have center in the slug
        if (str_contains($locale, 'center')) {
            return __('Account Center', 'wicket-acc');
        }

        return __('Account Centre', 'wicket-acc');
    }

    /**
     * Get a theme option value preferring Carbon Fields, fallback to ACF option.
     *
     * @param string $key Option key
     * @param mixed $default Default value if neither provider is available
     * @return mixed
     */
    public function getOption(string $key, $default = null)
    {
        if (function_exists('carbon_get_theme_option')) {
            return carbon_get_theme_option($key);
        }

        if (function_exists('get_field')) {
            return get_field($key, 'option');
        }

        return $default;
    }

    /**
     * Get a Page/Post ID stored as a relation option.
     * Handles Carbon Fields relation arrays and ACF numeric IDs.
     *
     * @param string $key Option key
     * @param int $default Default ID
     * @return int
     */
    public function getOptionPageId(string $key, int $default = 0): int
    {
        if (function_exists('carbon_get_theme_option')) {
            $val = carbon_get_theme_option($key);
            // CF relation may return array with first item ['id']
            if (is_array($val)) {
                return isset($val[0]['id']) ? (int) $val[0]['id'] : $default;
            }
            if (is_numeric($val)) {
                return (int) $val;
            }

            return $default;
        }

        if (function_exists('get_field')) {
            $acf = get_field($key, 'option');

            return is_numeric($acf) ? (int) $acf : $default;
        }

        return $default;
    }

    /**
     * Get an attachment URL from a theme option.
     * CF typically stores attachment ID; ACF may store URL or ID.
     *
     * @param string $key Option key
     * @param string $default Default URL if not resolvable
     * @return string
     */
    public function getAttachmentUrlFromOption(string $key, string $default = ''): string
    {
        if (function_exists('carbon_get_theme_option')) {
            $id = carbon_get_theme_option($key);
            if (is_numeric($id)) {
                $url = wp_get_attachment_url((int) $id);

                return $url ?: $default;
            }

            return $default;
        }

        if (function_exists('get_field')) {
            $val = get_field($key, 'option');
            if (is_numeric($val)) {
                $url = wp_get_attachment_url((int) $val);

                return $url ?: $default;
            }
            if (is_string($val)) {
                return $val;
            }
        }

        return $default;
    }

    /**
     * Get language
     * If WPML is not installed, return 'en'.
     *
     * @return string
     */
    public function getLanguage()
    {
        $lang = wicket_get_current_language();

        return $lang;
    }

    /**
     * Chekc if WPML (or Polylang) are installed and active
     * Prioritize WPML, then check for Polylang.
     */
    public function isMultiLangEnabled() {
        // WPML: check common indicators first
        if (defined('ICL_SITEPRESS_VERSION')) {
            return true;
        }

        // Polylang: fallback detection
        if (defined('POLYLANG_VERSION')) {
            return true;
        }

        return false;
    }

    /**
     * Get global banner page ID
     * from slug: acc_global-headerbanner
     * CPT: my-account.
     *
     * @return int
     */
    public function getGlobalHeaderBannerPageId()
    {
        $page = get_page_by_path('acc_global-headerbanner', OBJECT, 'my-account');
        $page_id = $page->ID;

        // Is WPML enabled?
        if (function_exists('icl_get_languages')) {
            $page_id = apply_filters('wpml_object_id', $page_id, 'my-account', true, $this->getLanguage());
        }

        return $page_id;
    }

    /**
     * Get Wicket ACC sidebar template.
     *
     * @return string
     */
    public function renderAccSidebar()
    {
        $user_template = WICKET_ACC_USER_TEMPLATE_PATH . 'account-centre/sidebar.php';
        $plugin_template = WICKET_ACC_PLUGIN_TEMPLATE_PATH . 'account-centre/sidebar.php';
        $sidebar_template = false;

        if (file_exists($user_template)) {
            $sidebar_template = $user_template;
        } elseif (file_exists($plugin_template)) {
            $sidebar_template = $plugin_template;
        }

        if ($sidebar_template) {
            include_once $sidebar_template;
        }
    }

    /**
     * Render the global sub header template.
     *
     * @return void
     */
    public function renderGlobalSubHeader()
    {
        $acc_global_headerbanner_page_id = WACC()->getGlobalHeaderBannerPageId();
        // Read option via helper (CF first, ACF fallback)
        $acc_global_headerbanner_status = $this->getOption('acc_global-headerbanner', false);

        if ($acc_global_headerbanner_page_id && $acc_global_headerbanner_status) {
            $global_banner_page = get_post($acc_global_headerbanner_page_id);
            if ($global_banner_page) {
                echo '<div class="wicket-acc wicker-acc-subheader alignfull wp-block-wicket-banner">';
                echo apply_filters('the_content', $global_banner_page->post_content);
                echo '</div>';
            }
        }
    }

    /**
     * Check if we're on an account page.
     *
     * @return bool True if we're on an account page, false otherwise.
     */
    public function is_account_page()
    {
        return is_singular('my-account') || is_post_type_archive('my-account');
    }

    /**
     * Get account page URL.
     *
     * @param string $endpoint Optional. Endpoint to append to the URL.
     * @return string
     */
    public function get_account_page_url($endpoint = '')
    {
        $account_page = get_page_by_path($this->getAccSlug());
        if (!$account_page) {
            return home_url();
        }

        $url = get_permalink($account_page);
        if ($endpoint) {
            $url = trailingslashit($url) . $endpoint;
        }

        return $url;
    }

    /**
     * Get account menu items.
     *
     * @return array
     */
    public function get_account_menu_items()
    {
        $items = [
            'dashboard' => [
                'title' => __('Dashboard', 'wicket-acc'),
                'url' => $this->get_account_page_url(),
            ],
            'edit-profile' => [
                'title' => __('Edit Profile', 'wicket-acc'),
                'url' => $this->get_account_page_url('edit-profile'),
            ],
            'change-password' => [
                'title' => __('Change Password', 'wicket-acc'),
                'url' => $this->get_account_page_url('change-password'),
            ],
            'organization-management' => [
                'title' => __('Organization Management', 'wicket-acc'),
                'url' => $this->get_account_page_url('organization-management'),
            ],
        ];

        return apply_filters('wicket_acc_menu_items', $items);
    }

    /**
     * Render account menu.
     *
     * @param string $menu_location Optional. Menu location to render.
     * @return void
     */
    public function render_account_menu($menu_location = 'wicket-acc-nav')
    {
        if (has_nav_menu($menu_location)) {
            wp_nav_menu([
                'container' => false,
                'theme_location' => $menu_location,
                'depth' => 3,
                'menu_id' => 'wicket-acc-menu',
                'menu_class' => 'wicket-acc-menu',
                'walker' => new \wicket_acc_menu_walker(),
            ]);
        } else {
            $items = $this->get_account_menu_items();
            if (!empty($items)) {
                echo '<ul class="wicket-acc-menu">';
                foreach ($items as $item) {
                    echo '<li class="wicket-acc-menu-item">';
                    echo '<a href="' . esc_url($item['url']) . '">' . esc_html($item['title']) . '</a>';
                    echo '</li>';
                }
                echo '</ul>';
            }
        }
    }

    /**
     * Checks if WooCommerce is active.
     *
     * @return bool
     */
    public function isWooCommerceActive()
    {
        return class_exists('WooCommerce');
    }

    /**
     * Get theme path. Prioritize child theme.
     *
     * @return string
     */
    public function getThemePath()
    {
        return trailingslashit(get_stylesheet_directory());
    }

    /**
     * Get theme URL. Prioritize child theme.
     *
     * @return string
     */
    public function getThemeURL()
    {
        return trailingslashit(get_stylesheet_directory_uri());
    }

    /**
     * Checks if a given string is a valid UUID (RFC 4122 compliant).
     *
     * @param string $uuid The string to check.
     *
     * @return bool True if the string is a valid UUID, false otherwise.
     */
    public function isValidUuid(string $uuid): bool
    {
        // A regular expression that matches the standard UUID format.
        // It checks for 32 hexadecimal characters, grouped by hyphens in a 8-4-4-4-12 pattern.
        // The 'i' flag makes the match case-insensitive for hexadecimal characters (a-f/A-F).
        // The third group's first character (version) is typically 1-5 for standard UUIDs.
        // The fourth group's first character (variant) is typically 8, 9, a, or b for standard UUIDs.
        $regex = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-5][0-9a-f]{3}-[089ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        return (bool) preg_match($regex, $uuid);
    }
}
