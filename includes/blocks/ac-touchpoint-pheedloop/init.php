<?php

namespace WicketAcc\Blocks\TouchpointPheedloop;

use WicketAcc\Blocks;

// No direct access
defined( 'ABSPATH' ) || exit;

/**
 * Wicket Touchpoint Pheedloop Block
 **/
class init extends Blocks {
	/**
	 * Constructor
	 */
	public function __construct(
		protected array $block = [],
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
	 * Display the block
	 *
	 * @return void
	 */
	protected function init_block() {
		$close = 0;
		$attrs = $this->is_preview ? ' ' : get_block_wrapper_attributes(
			[
				'class' => 'wicket-acc-block wicket-acc-block-touchpoints-pheedloop wicket-ac-touchpoint-pheedloop max-w-5xl mx-auto my-8 p-6',
			]
		);

		if ( $this->is_preview ) {
			$args = [
				'block_name'        => 'Touchpoint Pheedloop',
				'block_description' => 'This block displays registered data for Pheedloop on the front-end.',
				'block_slug'        => 'wicket-ac-touchpoint-pheedloop',
			];

			$this->blocks->render_template( 'preview', $args );

			return;
		}

		$title                          = get_field( 'title' );
		$display                        = get_field( 'default_display' );
		$num_results                    = get_field( 'page_results' );
		$override_past_events_link      = get_field( 'override_past_events_link' );
		$override_past_events_link_text = get_field( 'override_past_events_link_text' );
		$show_view_more_events          = get_field( 'show_view_more_events' );
		$use_x_columns                  = get_field( 'use_x_columns' );

		$total_results = 0;
		$counter       = 0;
		$display_type  = 'upcoming';

		$touchpoints_results = $this->get_touchpoints_results( 'Aptify Conversion' );

		// Get query vars
		$display     = isset( $_REQUEST['show'] ) ? sanitize_text_field( $_REQUEST['show'] ) : $display;
		$num_results = isset( $_REQUEST['num_results'] ) ? absint( $_REQUEST['num_results'] ) : $num_results;

		if ( empty( $display ) ) {
			$display = 'upcoming';
		}

		// Allowed query vars for display
		$valid_display = [
			'upcoming',
			'past',
			'all',
		];

		if ( ! in_array( $display, $valid_display ) ) {
			$display = 'upcoming';
		}

		// Switch link
		$display_other = $display == 'upcoming' ? 'past' : 'upcoming';

		$switch_link = add_query_arg(
			[
				'show'        => $display_other,
				'num_results' => $num_results,
			],
			remove_query_arg( 'show' )
		);

		$switch_link = esc_url( $switch_link );

		$args = [
			'block_name'                     => 'Touchpoint Pheedloop',
			'block_description'              => 'This block displays registered data for Pheedloop on the front-end.',
			'block_slug'                     => 'wicket-ac-touchpoint-pheedloop',
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
			'is_ajax_request'                => false,
			'is_preview'                     => $this->is_preview,
		];

		// Render block
		WACC()->Blocks->render_template( 'touchpoint-pheedloop', $args );

		return;
	}

	/**
	 * Get touchpoints results
	 *
	 * $service_id - Touchpoint service id
	 *
	 * @return mixed Array of touchpoints or false on error
	 */
	protected function get_touchpoints_results( $service_id = '' ) {
		if ( empty( $service_id ) ) {
			return false;
		}

		// Debug with person: 9e0093fb-6df8-4da3-bf62-e6c135c1e4b0
		$touchpoint_service = WACC()->MdpApi->create_touchpoint_service_id( $service_id );
		$touchpoints        = WACC()->MdpApi->get_current_user_touchpoints( $touchpoint_service, '6d199632-1bb8-4558-9a7e-b00c824590de' );
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
	public static function display_touchpoints( $touchpoint_data = [], $display_type = 'upcoming', $num_results = 5, $ajax = false, $config = [] ) {

		// Config defaults
		if ( empty( $config ) ) {
			$config['show_view_more_events'] = true;
			$config['use_x_columns']         = 1;
		}

		// Filter data by type
		$touchpoint_data = self::filter_touchpoint_data( $touchpoint_data, $display_type );

		// No data
		if ( empty( $touchpoint_data ) ) {
			echo '<p class="no-data">';
			_e( 'You do not have any ' . $display_type . ' events at this time.', 'wicket-acc' );
			echo '</p>';
			return;
		}

		// Total results
		$total_results = count( $touchpoint_data );

		// Get the offset from POST or default to 0
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

		// Slice the array to get only the needed items
		$display_data = array_slice( $touchpoint_data, $offset, $num_results );

		foreach ( $display_data as $tp ) :
			if ( isset( $tp['attributes']['data']['event_start'] ) ) {
				$args['tp'] = $tp;
				WACC()->Blocks->render_template( 'touchpoint-pheedloop-card', $args );
			}
		endforeach;

		// Show more button only if not an AJAX request and there are more results
		if ( $ajax === false && $config['show_view_more_events'] && ($offset + $num_results) < $total_results ) {
			self::load_more_results( $touchpoint_data, $num_results, $total_results, $offset, $display_type );
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
	public static function filter_touchpoint_data( $touchpoint_data = [], $display_type = 'upcoming' ) {
		if ( empty( $touchpoint_data ) ) {
			return $touchpoint_data;
		}

		// Get current date as: 2024-09-19T14:00:00.000Z
		$current_date = date( 'Y-m-d\TH:i:s.000Z' );

		// Check inside every touchpoint for attributes->data->StartDate, and compare with current date. If display_type = upcoming, return an array of touchpoints that are greater than current date. If display_type = past, return an array of touchpoints that are less than current date.
		$filtered_touchpoint_data = array_filter( $touchpoint_data, function ($touchpoint) use ($current_date, $display_type) {
			if ( isset( $touchpoint['attributes']['data']['event_start'] ) ) {
				$start_date = $touchpoint['attributes']['data']['event_start'];

				// Check if start date is greater than current date
				if ( strtotime( $start_date ) > strtotime( $current_date ) ) {
					return $display_type == 'upcoming';
				}

				// Check if start date is less than current date
				if ( strtotime( $start_date ) < strtotime( $current_date ) ) {
					return $display_type == 'past';
				}
			}
			return false;
		} );

		return $filtered_touchpoint_data;
	}

	/**
	 * Load more results
	 *
	 * @param array $touchpoint_data Touchpoint data
	 * @param int $num_results Number of results to display
	 * @param int $total_results Total results
	 * @param int $offset Offset of displayed results
	 * @param string $display_type Touchpoint display type: upcoming, past, all
	 *
	 * @return void
	 */
	public static function load_more_results( $touchpoint_data = [], $num_results = 5, $total_results = 0, $offset = 0, $display_type = 'upcoming' ) {
		?>
		<div x-data="ajaxFormHandler()">
            <div class="wicket-ac-touchpoint__pheedloop-results container">
				<div class="events-list grid gap-6" x-html="responseMessage"></div>
			</div>

			<div class="flex justify-center items-center load-more-container">
				<form id="form" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" method="post"
					@submit.prevent="submitForm">
					<input type="hidden" name="action" value="wicket_ac_touchpoint_pheedloop_results">
					<input type="hidden" name="num_results" value="<?php echo $num_results; ?>">
					<input type="hidden" name="total_results" value="<?php echo $total_results; ?>">
					<input type="hidden" name="type" value="<?php echo $display_type; ?>">
					<input type="hidden" name="offset" value="<?php echo $offset + $num_results; ?>">
					<?php wp_nonce_field( 'wicket_ac_touchpoint_pheedloop_results' ); ?>

					<div x-show="loading" class="wicket-ac-touchpoint__loader flex justify-center items-center self-center">
						<i class="fas fa-spinner fa-spin"></i>
					</div>

					<button type="submit"
						class="button button-primary show-more flex items-center font-bold text-color-dark-100 my-4"
						x-show="!loading">
						<span class="arrow mr-2">&#9660;</span>
						<span class="text"><?php esc_html_e( 'Show More', 'wicket-acc' ); ?></span>
					</button>
				</form>
			</div>
		</div>

		<script>
			function ajaxFormHandler() {
				return {
					loading: false,
					responseMessage: '',
					submitForm(event) {
						this.loading = true;
						const form = document.getElementById('form');
						const formData = new FormData(form);

						fetch(woocommerce_params.ajax_url, {
							method: 'POST',
							body: formData,
						})
						.then(response => response.text())
						.then(data => {
							this.loading = false;
							if (data) {
                                this.responseMessage += data;
								// Find the events list and load more container
								const loadMoreContainer = document.querySelector('.load-more-container');


								// Update the offset for the next request
								const offset = parseInt(form.querySelector('[name="offset"]').value);
								const numResults = parseInt(form.querySelector('[name="num_results"]').value);
								const totalResults = parseInt(form.querySelector('[name="total_results"]').value);

								// Update offset for next request
								form.querySelector('[name="offset"]').value = offset + numResults;

								// Hide "Show More" if we've loaded all results
								if (offset + numResults >= totalResults) {
									loadMoreContainer.style.display = 'none';
								}
							}
						})
						.catch(error => {
							this.loading = false;
							console.error('Error:', error);
						});
					}
				};
			}
		</script>
		<?php
	}
}
