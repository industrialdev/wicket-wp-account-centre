<?php

namespace WicketAcc\Blocks\TouchpointPheedloop;

// No direct access
defined('ABSPATH') || exit;

class ajax extends init
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Register Ajax actions
        add_action('wp_ajax_wicket_ac_touchpoint_pheedloop_results', [$this, 'ajax_load_more_results']);
        add_action('wp_ajax_nopriv_wicket_ac_touchpoint_pheedloop_results', [$this, 'ajax_load_more_results']);
    }

    /**
     * Ajax load more results
     *
     * @return void
     */
    public function ajax_load_more_results()
    {
        // If action != wicket_ac_touchpoint_pheedloop_results, return
        if (!isset($_POST['action']) || $_POST['action'] != 'wicket_ac_touchpoint_pheedloop_results') {
            return false;
        }

        // Verify nonce
        $nonce_validation = check_ajax_referer('wicket_ac_touchpoint_pheedloop_results', 'security');

        if (!$nonce_validation) {
            echo '<p class="error no-data">';
            _e('Security validation failed.', 'wicket-acc');
            echo '</p>';
            die();
        }

        // Retrieve form data
        $num_results   = absint($_POST['num_results']);
        $total_results = absint($_POST['total_results']);
        $counter       = absint($_POST['counter']);
        $display_type  = sanitize_text_field($_POST['type']);

        $pheedloop_results = $this->get_touchpoints_results();

        // We will get $this->display_pheedloop results and return it as html
        // Ideally, we should return the results as json, but... i don't know if Wicket has any standard way to render json results on the front-end
        // Even more ideally, we should be using HTMX ;)
        ob_start();
        $this->display_touchpoints($pheedloop_results['data'], $display_type, $num_results, true);
        $results = ob_get_clean();

        // If empty, results json error
        if (empty($results)) {
            echo '<p class="error no-data">';
            _e('No data found.', 'wicket-acc');
            echo '</p>';
            die();
        }

        // Get the results and clean the buffer
        $results = ob_get_clean();

        // Send the HTML
        echo $results;

        die();
    }
}

new ajax();
