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
        // WPML enabled?
        if (function_exists('icl_get_languages')) {
            global $sitepress;
            $current_language = $sitepress->get_current_language();

            if (isset($this->acc_index_slugs[$current_language])) {
                return $this->acc_index_slugs[$current_language];
            }
        }

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
        $locale = get_field('ac_localization', 'option');

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
     * Get language
     * If WPML is not installed, return 'en'.
     *
     * @return string
     */
    public function getLanguage()
    {
        global $sitepress;

        if (!isset($sitepress)) {
            return 'en';
        }

        $lang = $sitepress->get_current_language();

        return $lang;
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
        $acc_global_headerbanner_status = get_field('acc_global-headerbanner', 'option');

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
