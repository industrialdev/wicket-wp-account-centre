<?php

namespace WicketAcc\Blocks\TouchpointVitalSource;

// No direct access
defined('ABSPATH') || exit;

class ajax extends init
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Register Ajax actions
        add_action('wp_ajax_wicket_ac_touchpoint_vitalsource_results', [$this, 'ajax_load_more_results']);
        add_action('wp_ajax_nopriv_wicket_ac_touchpoint_vitalsource_results', [$this, 'ajax_load_more_results']);
    }

    /**
     * Ajax load more results.
     *
     * @return void
     */
    public function ajax_load_more_results()
    {
        // If action != wicket_ac_touchpoint_vitalsource_results, return
        if (!isset($_POST['action']) || $_POST['action'] != 'wicket_ac_touchpoint_vitalsource_results') {
            return false;
        }

        // Verify nonce
        $nonce_validation = check_ajax_referer('wicket_ac_touchpoint_vitalsource_results', 'security');

        if (!$nonce_validation) {
            echo '<p class="error no-data">';
            _e('Security validation failed.', 'wicket-acc');
            echo '</p>';
            die();
        }

        // Retrieve form data
        $num_results = absint($_POST['num_results']);

        $vitalsource_results = $this->get_touchpoints_results($this->get_service_id());

        ob_start();
        $this->display_touchpoints(
            $vitalsource_results,
            $num_results,
            true,
            ['show_view_more' => false] // Don't show the "Show More" button in AJAX responses
        );
        $results = ob_get_clean();

        // If empty, return error
        if (empty($results)) {
            echo '<p class="error no-data">';
            _e('No data found.', 'wicket-acc');
            echo '</p>';
            die();
        }

        // Send the HTML
        echo $results;
        die();
    }
}

new ajax();