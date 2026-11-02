<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * WP Job Manager integration class.
 *
 * WPJM gates its front-end action handlers on is_page(), which only passes for
 * the `page` post type. ACC serves member pages as the `my-account` CPT, so
 * pages configured as WPJM pages (Job Dashboard, Submit Job Form) live there
 * and those gates never pass: dashboard action links (Continue Submission,
 * delete, duplicate) decline with the misleading "page is not set as the job
 * dashboard" notice. The gate accepts the configured dashboard page, or any
 * my-account post whose content carries [job_dashboard] (upstream's own
 * fallback; translated dashboards are distinct posts). Dashboards composed
 * from patterns/blocks without the literal shortcode still decline, unless
 * they sit on the configured Job Dashboard page.
 *
 * Not covered: WP_Job_Manager_Shortcodes::handle_redirects() has the same
 * is_page() assumption, so the submission-limit redirect never runs on the
 * submit page. The publish-time re-check only backstops preview listings
 * under an active listing limit; needs an upstream fix (no hook available).
 */
class JobManager extends WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_filter('job_manager_should_run_shortcode_action_handler', [$this, 'allow_acc_job_dashboard_actions'], 10, 1);
    }

    /**
     * Let WPJM's dashboard action handler run on the ACC job dashboard.
     *
     * @param bool $should
     * @return bool
     */
    public function allow_acc_job_dashboard_actions($should)
    {
        if ($should) {
            return true;
        }

        if (!is_singular('my-account') || !class_exists('WP_Job_Manager')) {
            return false;
        }

        $dashboard_id = (int) job_manager_get_page_id('job_dashboard');

        if ($dashboard_id > 0 && get_queried_object_id() === $dashboard_id) {
            return true;
        }

        $queried = get_queried_object();

        return $queried instanceof \WP_Post && has_shortcode($queried->post_content, 'job_dashboard');
    }
}
