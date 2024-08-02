<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Language file for Wicket Account Centre Plugins
 *
 * @package  Wicket\Admin
 * @version  1.0.0
 */
class Language extends WicketAcc
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		//add_action('plugins_loaded', [$this, 'load_textdomain']);
	}

	/**
	 * Load text domain
	 */
	public function load_textdomain()
	{
		if (function_exists('load_plugin_textdomain')) {
			load_plugin_textdomain('wicket-acc', false, dirname(plugin_basename(__FILE__)) . '/languages/');
		}
	}
}
