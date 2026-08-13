<?php

namespace WicketAcc\Blocks\TouchpointZoom;

// No direct access
defined('ABSPATH') || exit;

class ajax extends init
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Register Ajax actions — logged-in users only; endpoint serves personal Wicket data
        add_action('wp_ajax_wicket_ac_touchpoint_zoom_results', [$this, 'ajax_load_more_results']);
    }

    /**
     * Ajax load more results.
     *
     * @return void
     */
    public function ajax_load_more_results()
    {
        // If action != wicket_ac_touchpoint_zoom_results, return
        if (!isset($_POST['action']) || $_POST['action'] != 'wicket_ac_touchpoint_zoom_results') {
            return false;
        }

        // Verify nonce
        $nonce_validation = check_ajax_referer('wicket_ac_touchpoint_zoom_results', 'security');

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
        $touchpoint_data_input = isset($_POST['touchpoint_data']) ? wp_unslash($_POST['touchpoint_data']) : '';

        // Decode the touchpoint data. The producer (init.php) base64-encodes a
        // JSON string, so we JSON-decode it here. Never unserialize request input.
        $decoded = base64_decode($touchpoint_data_input, true);
        $touchpoint_data = $decoded === false ? [] : json_decode($decoded, true);
        if (!is_array($touchpoint_data)) {
            $touchpoint_data = [];
        }

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
