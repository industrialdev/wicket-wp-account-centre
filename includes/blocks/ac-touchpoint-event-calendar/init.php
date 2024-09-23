<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Touchpoint Event Calendar Block
 **/
class Block_TouchpointEventCalendar extends WicketAcc
{
	/**
	 * Constructor
	 */
	public function __construct(
		protected array $block     = [],
		protected bool $is_preview = false,
		protected ?Blocks $blocks = null,
	) {
		$this->block      = $block;
		$this->is_preview = $is_preview;
		$this->blocks     = $blocks ?? new Blocks();

		// Display the block
		$this->init_block();
	}

	/**
	 * Init block
	 *
	 * @return void
	 */
	protected function init_block()
	{
		$close = 0;
		$attrs = get_block_wrapper_attributes(
			[
				'class' => 'wicket-acc-block wicket-acc-block-touchpoints-tec flex flex-col gap-8'
			]
		);

		if ($this->is_preview) {
			$args = [
				'block_name'          => 'Touchpoints TEC',
				'block_description'   => 'This block displays registered data for The Events Calendar on the front-end.',
				'block_slug'          => 'wicket-ac-touchpoint-tec',
			];

			$this->blocks->render_template('preview', $args);

			return;
		}

		echo '<section ' . $attrs . '>';

		$title                          = get_field('title');
		$display                        = get_field('default_display');
		$registered_action              = get_field('registered_action');
		$num_results                    = get_field('page_results');
		$override_past_events_link      = get_field('override_past_events_link');
		$override_past_events_link_text = get_field('override_past_events_link_text');
		$show_view_more_events          = get_field('show_view_more_events');
		$use_x_columns                  = get_field('use_x_columns');

		$total_results     = 0;
		$counter           = 0;
		$display_type      = 'upcoming';

		$touchpoints_results = $this->get_touchpoints_results('Events Calendar');

		if (empty($registered_action)) {
			$registered_action = [
				"rsvp_to_event",
				"registered_for_an_event",
				"attended_an_event"
			];
		}

		// Get query vars
		$display     = isset($_REQUEST['show']) ? sanitize_text_field($_REQUEST['show']) : 'upcoming';
		$num_results = isset($_REQUEST['num_results']) ? absint($_REQUEST['num_results']) : $num_results;

		if (empty($display)) {
			$display = 'upcoming';
		}

		// Allowed query vars for display
		$valid_display = [
			'upcoming',
			'past',
			'all'
		];

		if (!in_array($display, $valid_display)) {
			$display = 'upcoming';
		}

		// Switch link
		$display_other = $display == 'upcoming' ? 'past' : 'upcoming';

		$switch_link   = add_query_arg(
			[
				'show'        => $display_other,
				'num_results' => $num_results
			],
			remove_query_arg('show')
		);

		$switch_link = esc_url($switch_link);

		$args = [
			'block_name'                     => 'Touchpoint TEC',
			'block_description'              => 'This block displays registered data for TEC (The Events Calendar) on the front-end.',
			'block_slug'                     => 'wicket-ac-touchpoint-tec',
			'attrs'                          => $attrs,
			'title'                          => $title,
			'display'                        => $display,
			'num_results'                    => $num_results,
			'total_results'                  => $total_results,
			'counter'                        => $counter,
			'close'                          => $close,
			'display_type'                   => $display_type,
			'touchpoints_results'            => $touchpoints_results,
			'switch_link'                    => $switch_link,
			'override_past_events_link'      => $override_past_events_link,
			'override_past_events_link_text' => $override_past_events_link_text,
			'show_view_more_events'          => $show_view_more_events,
			'use_x_columns'                  => $use_x_columns,
			'is_preview'                     => $this->is_preview
		];

		// Render block
		WACC()->Blocks->render_template('touchpoint-tec', $args);

		return;
	}

	/**
	 * Get touchpoints results
	 *
	 * $service_id - Touchpoint service id
	 *
	 * @return mixed Array of touchpoints or false on error
	 */
	protected function get_touchpoints_results($service_id = '')
	{
		if (empty($service_id)) {
			return false;
		}

		// Debug with person: 9e0093fb-6df8-4da3-bf62-e6c135c1e4b0
		$touchpoint_service = WACC()->MdpApi->create_touchpoint_service_id($service_id);
		$touchpoints        = WACC()->MdpApi->get_current_user_touchpoints($touchpoint_service);

		return $touchpoints;
	}

	/**
	 * Display the touchpoints
	 *
	 * @param array $touchpoint_data Touchpoint data
	 * @param string $display Touchpoint display type: upcoming, past, all
	 * @param int $num_results Number of results to display
	 * @param bool $ajax Is ajax request?
	 * @param array $config show_view_more_events(bool), use_x_columns(int)
	 *
	 * @return void
	 */
	public static function display_touchpoints($touchpoint_data = [], $display_type = 'upcoming', $num_results = 5, $ajax = false, $config = [])
	{
		// No data
		if (empty($touchpoint_data)) {
			echo '<p class="no-data">';
			_e('You do not have any ' . $display_type . ' data at this time.', 'wicket-acc');
			echo '</p>';
			return;
		}

		// Config defaults
		if (empty($config)) {
			$config['show_view_more_events'] = true;
			$config['use_x_columns']         = 1;
		}

		// Filter data by type
		$touchpoint_data = self::filter_touchpoint_data($touchpoint_data, $display_type);

		// Total results
		$total_results = count($touchpoint_data);

		$counter = 0;

		foreach ($touchpoint_data as $tp) :
			//if ($tp['attributes']['code'] == 'cancelled_registration_for_an_event') :
			$counter++;

			if (isset($tp['attributes']['data']['start_date'])) {
				$args['tp'] = $tp;

				WACC()->Blocks->render_template('touchpoint-tec-card', $args);
			}
			//endif;

			if ($counter == $num_results) {
				break;
			}
		endforeach;

		// Show more like pagination, to load more data in the same page (if there are more than $num_results)
		if ($counter == $num_results && $ajax === false && $config['show_view_more_events']) {
			self::load_more_results($touchpoint_data, $num_results, $total_results, $counter, $display_type);
		}
	}

	/**
	 * Filter touchpoint data
	 *
	 * @param array $touchpoint_data Touchpoint data
	 * @param string $display_type Touchpoint display type: upcoming, past, all
	 *
	 * @return array
	 */
	public static function filter_touchpoint_data($touchpoint_data = [], $display_type = 'upcoming')
	{
		if (empty($touchpoint_data)) {
			return $touchpoint_data;
		}

		// Get current date as: 2024-09-19T14:00:00.000Z
		$current_date = date('Y-m-d\TH:i:s.000Z');

		// Check inside every touchpoint for attributes->data->start_date, and compare with current date. If display_type = upcoming, return an array of touchpoints that are greater than current date. If display_type = past, return an array of touchpoints that are less than current date.
		$filtered_touchpoint_data = array_filter($touchpoint_data, function ($touchpoint) use ($current_date, $display_type) {
			if (isset($touchpoint['attributes']['data']['start_date'])) {
				$start_date = $touchpoint['attributes']['data']['start_date'];

				// Check if start date is greater than current date
				if (strtotime($start_date) > strtotime($current_date)) {
					return $display_type == 'upcoming';
				}

				// Check if start date is less than current date
				if (strtotime($start_date) < strtotime($current_date)) {
					return $display_type == 'past';
				}
			}
			return false;
		});

		return $filtered_touchpoint_data;
	}

	/**
	 * Load more results
	 *
	 * @param array $touchpoint_data Touchpoint data
	 * @param int $num_results Number of results to display
	 * @param int $total_results Total results
	 * @param int $counter Counter of displayed results
	 * @param string $display_type Touchpoint display type: upcoming, past, all
	 *
	 * @return void
	 */
	public static function load_more_results($touchpoint_data = [], $num_results = 5, $total_results = 0, $counter = 0, $display_type = 'upcoming')
	{
		// Sanitize
		$num_results   = absint($num_results);
		$total_results = absint($total_results);
		$counter       = absint($counter);
		?>

		<div x-data="ajaxFormHandler()">
			<div class="wicket-ac-touchpoint__tec-results container">
				<div class="events-list grid gap-6" x-html="responseMessage"></div>
			</div>

			<div class="flex justify-center items-center">
				<form action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="post" @submit.prevent="submitForm">
					<input type="hidden" name="action" value="wicket_ac_touchpoint_tec_results">
					<input type="hidden" name="num_results" value="<?php echo $num_results; ?>">
					<input type="hidden" name="total_results" value="<?php echo $total_results; ?>">
					<input type="hidden" name="type" value="<?php echo $display_type; ?>">
					<input type="hidden" name="counter" value="<?php echo $counter; ?>">
					<?php wp_nonce_field('wicket_ac_touchpoint_tec_results'); ?>

					<div x-show="loading" class="wicket-ac-touchpoint__loader flex justify-center items-center self-center">
						<i class="fas fa-spinner fa-spin"></i>
					</div>

					<button type="submit" class="button button-primary show-more flex items-center font-bold text-color-dark-100 my-4" x-show="!loading">
						<span class="arrow mr-2">&#9660;</span>
						<span class="text"><?php esc_html_e('Show More', 'wicket-acc'); ?></span>
					</button>
				</form>
			</div>

			<script>
				function ajaxFormHandler() {
					return {
						loading: false,
						responseMessage: '',
						submitForm(event) {
							this.loading = true;
							const formData = new FormData(event.target);

							console.log(formData);
							console.log(woocommerce_params.ajax_url);

							fetch(woocommerce_params.ajax_url, {
									method: 'POST',
									body: formData
								})
								.then(response => response.text())
								.then(data => {
									this.loading = false;
									if (data) {
										this.responseMessage = data;
									} else {
										this.responseMessage = '<?php esc_html_e('An error occurred. No data.', 'wicket-acc'); ?>';
									}
								})
								.catch(error => {
									this.loading = false;
									this.responseMessage = '<?php esc_html_e('An error occurred. Failed.', 'wicket-acc'); ?>';
								});
						}
					};
				}
			</script>
		</div>
<?php
	}
}
