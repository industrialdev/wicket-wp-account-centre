<?php

namespace WicketAcc\Blocks\TouchpointEventCalendar;

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
        add_action('wp_ajax_wicket_ac_touchpoint_tec_results', [$this, 'ajax_load_more_results']);
        add_action('wp_ajax_nopriv_wicket_ac_touchpoint_tec_results', [$this, 'ajax_load_more_results']);
    }

    /**
     * Ajax load more results.
     *
     * @return void
     */
    public function ajax_load_more_results()
    {
        // If action != wicket_ac_touchpoint_tec_results, return
        if (!isset($_POST['action']) || $_POST['action'] != 'wicket_ac_touchpoint_tec_results') {
            return false;
        }

        // Verify nonce
        $nonce_validation = check_ajax_referer('wicket_ac_touchpoint_tec_results', 'security');

        if (!$nonce_validation) {
            echo '<p class="error no-data">';
            _e('Security validation failed.', 'wicket-acc');
            echo '</p>';
            die();
        }

        // Get POST data
        $block_id = isset($_POST['block_id']) ? absint($_POST['block_id']) : 0;
        $num_param = "num-{$block_id}";
        $num_results = isset($_POST[$num_param]) ? absint($_POST[$num_param]) : 5;
        $total_results = isset($_POST['total_results']) ? absint($_POST['total_results']) : 0;
        $counter = isset($_POST['counter']) ? absint($_POST['counter']) : 0;
        $display_type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'upcoming';
        $touchpoint_data_input = $_POST['touchpoint_data'] ?? '';

        // Get touchpoints data
        $touchpoint_data = maybe_unserialize(base64_decode($touchpoint_data_input));

        // We will get $this->display_touchpoints results and return it as html
        // Ideally, we should return the results as json, but... i don't know if Wicket has any standar way to render json results on the front-end
        // Even more ideally, we should be using HTMX :blink: :blink: :blink: :blinkitty: :blink: :blink: :blink:
        ob_start();
        $this->display_touchpoints($touchpoint_data, $display_type, $num_results, true, ['counter' => $counter]);
        $results = ob_get_clean();

        // If empty, results json error
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
