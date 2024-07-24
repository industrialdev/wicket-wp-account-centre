<?php

/**
 * Wicket Touchpoint Microspec Block
 **/

namespace Wicket_AC\Blocks\AC_Touchpoint_Microspec;

if (!class_exists('Wicket_Acc_Touchpoint_Microspec')) {
	class Wicket_Acc_Touchpoint_Microspec
	{
		/**
		 * Constructor
		 */
		public function __construct(
			protected bool $is_preview = false,
		) {
			$this->is_preview = $is_preview;
			$this->display_block();
		}

		/**
		 * Display the block
		 *
		 * @return void
		 */
		protected function display_block()
		{
			$is_preview = $this->is_preview;

			$close = 0;
			$attrs = $is_preview ? ' ' : get_block_wrapper_attributes(
				[
					'class' => 'wicket-ac-touchpoint-microspec max-w-5xl mx-auto my-8 p-6'
				]
			);

			if ($is_preview) {
?>
				<div class="wicket-ac-touchpoints__preview wicket-ac-touchpoint-microspec wicket-ac-block-preview">
					<div class="wicket-ac-touchpoints__preview__title">
						<?php esc_html_e('Block: Touchpoint for MicroSpec', 'wicket-acc'); ?>
					</div>
					<div class="wicket-ac-touchpoints__preview__content">
						<?php esc_html_e('This block displays registered data for MicroSpec on the front-end.', 'wicket-acc'); ?>
					</div>
				</div>
			<?php
				return;
			}

			$display           = get_field('default_display');
			$registered_action = get_field('registered_action');
			$num_results       = get_field('num_results');

			$touchpoints_results = $this->get_touchpoints_results();

			if (empty($registered_action)) {
				$registered_action = [
					"rsvp_to_event",
					"registered_for_an_event",
					"attended_an_event"
				];
			}

			// Get query vars
			$display     = isset($_REQUEST['show']) ? sanitize_text_field($_REQUEST['show']) : 'upcoming';
			$num_results = isset($_REQUEST['num_results']) ? absint($_REQUEST['num_results']) : 5;

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

			// Locate template if exists
			$template = WICKET_ACC_PLUGIN_DIR . 'templates/blocks-account-centre/block_touchpoint_microspec.php';

			if (file_exists($template)) {
				include_once $template;
			}
		}

		/**
		 * Get touchpoints results
		 *
		 * @return array
		 */
		protected function get_touchpoints_results()
		{
			$touchpoint_service = get_create_touchpoint_service_id('MicroSpec');
			$touchpoints        = wicket_get_current_user_touchpoints($touchpoint_service);

			return $touchpoints;
		}

		/**
		 * Display the touchpoints
		 *
		 * @param array $touchpoint_data Touchpoint data
		 * @param string $display Touchpoint display type: upcoming, past, all
		 * @param int $num_results Number of results to display
		 * @param bool $ajax Is ajax request?
		 *
		 * @return void
		 */
		protected function display_touchpoints($touchpoint_data = [], $display_type = 'upcoming', $num_results = 5, $ajax = false)
		{
			// No data
			if (empty($touchpoint_data)) {
				echo '<p class="no-data">';
				_e('You do not have any ' . $display_type . ' data at this time.', 'wicket-acc');
				echo '</p>';
				return;
			}

			// Filter data by type
			$touchpoint_data = $this->filter_touchpoint_data($touchpoint_data, $display_type);

			// Total results
			$total_results = count($touchpoint_data);

			// Ajax request?
			if ($ajax === false) {
			?>
				<p class="text-base">
					<?php echo ucfirst($display_type) . " Data: " . $total_results; ?>
				</p>
				<?php
			}

			$counter = 0;

			foreach ($touchpoint_data as $tp) :
				//if ($tp['attributes']['code'] == 'cancelled_registration_for_an_event') :
				$counter++;

				if (isset($tp['attributes']['data']['StartDate'])) :
				?>
					<div class="event-card my-4 p-4 border border-gray-200 rounded-md shadow-md">
						<p class="text-sm font-bold mb-2 event-type">
							<?php echo $tp['attributes']['data']['BadgeType']; ?>
						</p>
						<h3 class="text-lg font-bold mb-2 event-name">
							<?php echo $tp['attributes']['data']['EventName']; ?>
						</h3>
						<p class="text-sm mb-2 event-date">
							<?php echo date('M', strtotime($tp['attributes']['data']['StartDate'])) . '-' . date('j', strtotime($tp['attributes']['data']['StartDate'])) . '-' . date('Y', strtotime($tp['attributes']['data']['StartDate'])) . ' | ' . date('g:i a', strtotime($tp['attributes']['data']['StartDate'])) . ' - ' . date('g:i a', strtotime($tp['attributes']['data']['EndDate'])); ?>
						</p>
						<p class="text-sm event-location hidden">
							<strong><?php esc_attr_e('Location:', 'wicket-acc'); ?></strong>
						</p>
					</div>
			<?php
				endif;
			//endif;
			endforeach;

			// Show more like pagination, to load more data in the same page (if there are more than $num_results)
			if ($counter == $num_results && $ajax === false) {
				$this->load_more_results($touchpoint_data, $num_results, $total_results, $counter, $display_type);
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
		protected function filter_touchpoint_data($touchpoint_data = [], $display_type = 'upcoming')
		{
			if (empty($touchpoint_data)) {
				return $touchpoint_data;
			}

			// Get current date as: 2024-09-19T14:00:00.000Z
			$current_date = date('Y-m-d\TH:i:s.000Z');

			// Check inside every touchpoint for attributes->data->StartDate, and compare with current date. If display_type = upcoming, return an array of touchpoints that are greater than current date. If display_type = past, return an array of touchpoints that are less than current date.
			$filtered_touchpoint_data = array_filter($touchpoint_data, function ($touchpoint) use ($current_date, $display_type) {
				if (isset($touchpoint['attributes']['data']['StartDate'])) {
					$start_date = $touchpoint['attributes']['data']['StartDate'];

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
		 * @param int $counter Counter
		 * @param string $display_type Touchpoint display type: upcoming, past, all
		 *
		 * @return void
		 */
		protected function load_more_results($touchpoint_data = [], $num_results = 5, $total_results = 0, $counter = 0, $display_type = 'upcoming')
		{
			// Sanitize
			$num_results   = absint($num_results);
			$total_results = absint($total_results);
			$counter       = absint($counter);
			?>

			<div x-data="ajaxFormHandler()">
				<div class="wicket-ac-touchpoint__microspec-results container">
					<div class="events-list grid gap-6" x-html="responseMessage"></div>
				</div>

				<div class="flex justify-center items-center">
					<form action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="post" @submit.prevent="submitForm">
						<input type="hidden" name="action" value="wicket_ac_touchpoint_microspec_results">
						<input type="hidden" name="num_results" value="<?php echo $num_results; ?>">
						<input type="hidden" name="total_results" value="<?php echo $total_results; ?>">
						<input type="hidden" name="type" value="<?php echo $display_type; ?>">
						<input type="hidden" name="counter" value="<?php echo $counter; ?>">
						<?php wp_nonce_field('wicket_ac_touchpoint_microspec_results'); ?>

						<div x-show="loading" class="wicket-ac-touchpoint__loader flex justify-center items-center self-center">
							<i class="fas fa-spinner fa-spin"></i>
						</div>

						<button type="submit" class="show-more flex items-center font-bold text-color-dark-100 my-4" x-show="!loading">
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
}

/**
 * Initialize the block
 *
 * @param array $block
 * @param bool $is_preview
 *
 * @return void
 */
function init($block = [], $is_preview)
{
	// Is ACF enabled?
	if (function_exists('acf_get_field')) {
		new Wicket_Acc_Touchpoint_Microspec($is_preview);
	}
}
