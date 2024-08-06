<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

class BlockAjax_BaseBlock extends Block_BaseBlock
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Register Ajax actions
		add_action('wp_ajax_wicket_acc_base_block_results', [$this, 'ajax_load_more_results']);
		add_action('wp_ajax_nopriv_wicket_acc_base_block_results', [$this, 'ajax_load_more_results']);
	}

	/**
	 * Ajax load more results
	 *
	 * @return void
	 */
	public function ajax_load_more_results()
	{
		// If action != wicket_acc_base_block_results, return
		if (!isset($_POST['action']) || $_POST['action'] != 'wicket_acc_base_block_results') {
			return false;
		}

		// Verify nonce
		$nonce_validation = check_ajax_referer('wicket_acc_base_block_results', 'security');

		if (!$nonce_validation) {
			echo '<p class="error no-data">';
			_e('Security validation failed.', 'wicket-acc');
			echo '</p>';
			die();
		}

		// Retrieve form data
		$variable     = absint($_POST['variable']);
		$other_field  = sanitize_text_field($_POST['other_field']);

		// We will get $this->display_touchpoints results and return it as html
		// Ideally, we should return the results as json, but... i don't know if Wicket has any standar way to render json results on the front-end
		// Even more ideally, we should be using HTMX ;)

		// Process some data and retrieve results in HTML
		// Don't need to use ob_start, if your methods already return HTML
		ob_start();

		$results = 'Some response';

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

new BlockAjax_TouchpointMicroSpec();
