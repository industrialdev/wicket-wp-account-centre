<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Settings Class
 * Handles plugin configuration and settings.
 */
class Settings extends WicketAcc
{
    /**
     * Wicket theme setting.
     *
     * @var string
     */
    private $wicketTheme;

    /**
     * Wicket prefer color scheme setting.
     *
     * @var string
     */
    private $wicketPreferColorScheme;

    /**
     * Settings constructor.
     *
     * Initializes settings with default values or filtered values.
     */
    public function __construct()
    {
        $this->wicketTheme = apply_filters('wicket/acc/settings/wicket_theme', 'light');
        $this->wicketPreferColorScheme = apply_filters('wicket/acc/settings/wicket_prefer_color_scheme', 'light');
    }

    /**
     * Get the wicket theme setting.
     *
     * @return string
     */
    public function getWicketTheme(): string
    {
        return $this->wicketTheme;
    }

    /**
     * Get the wicket prefer color scheme setting.
     *
     * @return string
     */
    public function getWicketPreferColorScheme(): string
    {
        return $this->wicketPreferColorScheme;
    }
}
