<?php

declare(strict_types=1);

namespace WicketAcc\Admin;

// No direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * General admin tweaks from Wicket Account Centre.
 */
class Tweaks
{
    public function __construct()
    {
        // Add CPTs to the 'Front page' dropdown in Reading Settings
        add_filter('wp_dropdown_pages', [$this, 'addCptToFrontPageDropdown'], 10, 1);
        // Ensure CPT posts can be set as front page
        add_action('pre_get_posts', [$this, 'forceCptOnFrontPageQuery']);
    }

    /**
     * Add 'my-account' CPT posts to the 'Front page' dropdown.
     *
     * @param string $output
     * @return string
     */
    public function addCptToFrontPageDropdown($output): string
    {
        global $pagenow;
        if ($pagenow !== 'options-reading.php') {
            return $output;
        }
        // Only modify the dropdown for 'page_on_front'
        if (false === strpos($output, 'page_on_front')) {
            return $output;
        }
        $current = (int) get_option('page_on_front', 0);
        $post = get_post($current);
        if ($post && is_object($post) && $post->post_type !== 'page') {
            // Already a CPT, ensure it's selectable
            $output = str_replace(
                '</select>',
                '<option value="' . esc_attr($current) . '" selected>' . esc_html($post->post_title) . ' (' . esc_html($post->post_type) . ')</option></select>',
                $output
            );
        }
        // Add all my-account CPTs
        $cpt_posts = get_posts([
            'post_type' => 'my-account',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        if (!$cpt_posts) {
            return $output;
        }
        $output = str_replace('</select>', '', $output);
        $output .= '<optgroup label="My Account (CPT)">';
        foreach ($cpt_posts as $cpt) {
            $output .= '<option value="' . esc_attr($cpt->ID) . '"' . selected($current, $cpt->ID, false) . '>' . esc_html($cpt->post_title) . '</option>';
        }
        $output .= '</optgroup></select>';
        return $output;
    }

    /**
     * Force front page query to use CPT if set.
     *
     * @param \WP_Query $query
     */
    public function forceCptOnFrontPageQuery($query): void
    {
        if (!is_admin() && $query->is_main_query() && $query->is_home() && $query->is_front_page()) {
            $front_id = (int) get_option('page_on_front');
            if ($front_id) {
                $post = get_post($front_id);
                if ($post && $post->post_type === 'my-account') {
                    $query->set('post_type', 'my-account');
                    $query->set('page_id', $front_id);
                }
            }
        }
    }
}

// Bootstrap the tweaks
add_action('plugins_loaded', function () {
    if (is_admin() || defined('REST_REQUEST')) {
        new Tweaks();
    }
});
