<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Assets Class
 * Handles enqueuing and printing assets
 */
class Assets extends WicketAcc
{
	public function __construct()
	{
		// Admin Scripts & Styles
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

		// Frontend Scripts & Styles
		add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
	}

	public function enqueue_admin_assets()
	{
		wp_enqueue_style('wicket-acc-admin-styles', WICKET_ACC_URL . 'assets/css/wicket-acc-admin-styles.css', [], WICKET_ACC_VERSION);
		wp_enqueue_script('wicket-acc-admin-scripts', WICKET_ACC_URL . 'assets/js/wicket-acc-admin-scripts.js', [], WICKET_ACC_VERSION, true);
	}

	public function enqueue_frontend_assets()
	{
		wp_enqueue_style('wicket-acc-frontend-styles', WICKET_ACC_URL . 'assets/css/wicket-acc-styles.css', [], WICKET_ACC_VERSION);
		wp_enqueue_script('wicket-acc-frontend-scripts', WICKET_ACC_URL . 'assets/js/wicket-acc-scripts.js', [], WICKET_ACC_VERSION, true);
		wp_enqueue_script('wicket-acc-frontend-legacy-scripts', WICKET_ACC_URL . 'assets/js/wicket-acc-legacy.js', [], WICKET_ACC_VERSION, true);
	}
}
