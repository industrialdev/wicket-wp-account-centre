<?php

namespace WicketAcc\Blocks\TouchpointZoom;

use WicketAcc\Blocks;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Touchpoint Zoom Block.
 **/
class init extends Blocks
{
    /**
     * Constructor.
     */
    public function __construct(
        protected array $block = [],
        protected bool $is_preview = false,
        protected ?Blocks $blocks = null,
    ) {
        $this->block = $block;
        $this->is_preview = $is_preview;
        $this->blocks = $blocks ?? new Blocks();

        // Display the block
        $this->init_block();
    }

    /**
     * Init block.
     *
     * @return void
     */
    protected function init_block()
    {
        $close = 0;
        $attrs = get_block_wrapper_attributes(
            [
                'class' => 'wicket-acc-block wicket-acc-block-touchpoints-zoom flex flex-col gap-8',
            ]
        );

        if ($this->is_preview) {
            $args = [
                'block_name'        => 'Touchpoints Zoom',
                'block_description' => 'This block displays registered data for Zoom Webinars on the front-end.',
                'block_slug'        => 'wicket-ac-touchpoint-zoom',
            ];

            $this->blocks->render_template('preview', $args);

            return;
        }

        $title = get_field('title');
        $past_events_title = get_field('past_events_title');
        $display = get_field('default_display');
        $registered_action = get_field('registered_action');
        $num_results = get_field('page_results');
        $show_switch_view_link = get_field('show_switch_view_link');
        $override_past_events_link = get_field('override_past_events_link');
        $override_past_events_link_text = get_field('override_past_events_link_text');
        $show_view_more_events = get_field('show_view_more_events');
        $use_x_columns = get_field('use_x_columns');

        $total_results = 0;
        $counter = 0;
        $display_type = 'upcoming';

        $touchpoints_results = $this->get_touchpoints_results('Zoom Webinars (1)');

        if (empty($registered_action)) {
            $registered_action = [
                'rsvp_to_event',
                'registered_for_an_event',
                'attended_an_event',
            ];
        }

        // Get query vars
        $block_id = $this->block['id'] ?? 'unknown';
        $show_param = "show-{$block_id}";
        $num_param = "num-{$block_id}";

        // Check for block-specific parameter first, fallback to default display
        $display = isset($_REQUEST[$show_param]) ? sanitize_text_field($_REQUEST[$show_param]) : $display;
        $num_results = isset($_REQUEST[$num_param]) ? absint($_REQUEST[$num_param]) : $num_results;

        if (empty($display)) {
            $display = 'upcoming';
        }

        // Allowed query vars for display
        $valid_display = [
            'upcoming',
            'past',
            'all',
        ];

        if (!in_array($display, $valid_display)) {
            $display = 'upcoming';
        }

        // Switch link
        $display_other = $display == 'upcoming' ? 'past' : 'upcoming';

        $switch_link = add_query_arg(
            [
                $show_param => $display_other,
                $num_param  => $num_results,
            ],
            remove_query_arg([$show_param, $num_param])
        );

        $switch_link = esc_url($switch_link);

        $args = [
            'block_id'                       => $block_id,
            'block_name'                     => 'Touchpoint Zoom',
            'block_description'              => 'This block displays registered data for Zoom Webinars on the front-end.',
            'block_slug'                     => 'wicket-ac-touchpoint-zoom',
            'attrs'                          => $attrs,
            'title'                          => $title,
            'past_events_title'              => $past_events_title,
            'display'                        => $display,
            'num_results'                    => $num_results,
            'total_results'                  => $total_results,
            'counter'                        => $counter,
            'close'                          => $close,
            'display_type'                   => $display_type,
            'touchpoints_results'            => $touchpoints_results,
            'switch_link'                    => $switch_link,
            'show_switch_view_link'          => $show_switch_view_link,
            'override_past_events_link'      => $override_past_events_link,
            'override_past_events_link_text' => $override_past_events_link_text,
            'show_view_more_events'          => $show_view_more_events,
            'use_x_columns'                  => $use_x_columns,
            'is_ajax_request'                => false,
            'is_preview'                     => $this->is_preview,
            'registered_action'              => $registered_action,
        ];

        // Render block
        WACC()->Blocks->render_template('touchpoint-zoom', $args);
    }

    /**
     * Get touchpoints results.
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

        $touchpoint_service = WACC()->MdpApi->create_touchpoint_service_id($service_id);
        $touchpoints = WACC()->MdpApi->get_current_user_touchpoints($touchpoint_service);

        return $touchpoints;
    }

    /**
     * Display the touchpoints.
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
            $config['use_x_columns'] = 1;
            $config['counter'] = 0;
            $config['block_id'] = 'unknown';
        }

        $block_id = $config['block_id'];

        $registered_action = get_field('registered_action');

        // Filter data by type. Only on initial call. We don't want ajax calls to be filtered again
        if ($ajax === false) {
            $touchpoint_data = self::filter_touchpoint_data($touchpoint_data, $display_type, $registered_action);
        }

        // Total results
        $total_results = count($touchpoint_data);

        if ($total_results <= 0) {
            get_component('card-call-out', [
                'title' => __('You have no upcoming webinars', 'wicket-acc'),
                'style' => 'secondary',
            ]);
        }

        $counter = 0;

        foreach ($touchpoint_data as $key => $tp) :
            $counter++;

            if (isset($tp['attributes']['data']['start_date'])) {
                $args['tp'] = $tp;

                WACC()->Blocks->render_template('touchpoint-zoom-card', $args);
            }

            // Remove current loop element from array
            unset($touchpoint_data[$key]);

            if ($counter == $num_results) {
                break;
            }
        endforeach;

        // Load more results
        if ($total_results > 1 && $config['show_view_more_events'] && $ajax === false) {
            self::load_more_results($touchpoint_data, $num_results, $total_results, $counter, $display_type, $ajax, $block_id);
        }
    }

    /**
     * Filter touchpoint data.
     *
     * @param array $touchpoint_data Touchpoint data
     * @param string $display_type Touchpoint display type: upcoming, past, all
     *
     * @return array
     */
    public static function filter_touchpoint_data($touchpoint_data = [], $display_type = 'upcoming', $registered_action = [])
    {
        if (empty($touchpoint_data)) {
            return [];
        }

        if (empty($registered_action)) {
            $registered_action = [
                'rsvp_to_event',
                'event_registered',
                'attended_an_event',
            ];
        }

        $filtered_data = [];

        foreach ($touchpoint_data as $tp) {
            // Is this action in the allowed list?
            if (!in_array($tp['attributes']['code'], $registered_action)) {
                continue;
            }

            // Get the start date for comparison
            $start_date = $tp['attributes']['data']['start_date'] ?? '';

            if (empty($start_date)) {
                continue;
            }

            $start_timestamp = strtotime($start_date);
            $current_timestamp = current_time('timestamp');

            if ($display_type === 'upcoming' && $start_timestamp >= $current_timestamp) {
                $filtered_data[] = $tp;
            } elseif ($display_type === 'past' && $start_timestamp < $current_timestamp) {
                $filtered_data[] = $tp;
            } elseif ($display_type === 'all') {
                $filtered_data[] = $tp;
            }
        }

        return $filtered_data;
    }

    /**
     * Load more results.
     *
     * @param array $touchpoint_data Touchpoint data
     * @param int $num_results Number of results to display
     * @param int $total_results Total results
     * @param int $counter Counter
     * @param string $display_type Touchpoint display type: upcoming, past, all
     * @param bool $ajax Is ajax request?
     * @param string $block_id Block ID
     *
     * @return void
     */
    public static function load_more_results($touchpoint_data = [], $num_results = 5, $total_results = 0, $counter = 0, $display_type = 'upcoming', $ajax = false, $block_id = 0)
    {
        // Encode touchpoint data for ajax
        $touchpoint_data_encoded = base64_encode(serialize($touchpoint_data));

        // Calculate remaining results
        $remaining_results = $total_results - $counter;

        if ($remaining_results <= 0) {
            return;
        }

        $nonce = wp_create_nonce('wicket_ac_touchpoint_zoom_results');
        $num_param = "num-{$block_id}";

        ?>
		<div class="load-more-container text-center mt-8" id="load-more-container-<?php echo $block_id; ?>">
			<button class="load-more-btn btn btn-primary" data-block-id="<?php echo $block_id; ?>"
				data-total-results="<?php echo $total_results; ?>" data-counter="<?php echo $counter; ?>"
				data-type="<?php echo $display_type; ?>" data-<?php echo $num_param; ?>="<?php echo $num_results; ?>"
				data-touchpoint-data="<?php echo $touchpoint_data_encoded; ?>" data-nonce="<?php echo $nonce; ?>"
				onclick="loadMoreZoomResults(this)">
				<?php _e('Load More', 'wicket-acc'); ?> (<?php echo $remaining_results; ?>
				<?php _e('remaining', 'wicket-acc'); ?>)
			</button>
		</div>

		<script>
			function loadMoreZoomResults(button) {
				const blockId = button.getAttribute('data-block-id');
				const totalResults = button.getAttribute('data-total-results');
				const counter = button.getAttribute('data-counter');
				const type = button.getAttribute('data-type');
				const numParam = 'num-' + blockId;
				const numResults = button.getAttribute('data-' + numParam);
				const touchpointData = button.getAttribute('data-touchpoint-data');
				const nonce = button.getAttribute('data-nonce');

				// Disable button
				button.disabled = true;
				button.innerHTML = '<?php _e('Loading...', 'wicket-acc'); ?>';

				// AJAX request
				const xhr = new XMLHttpRequest();
				xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
				xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

				xhr.onreadystatechange = function () {
					if (xhr.readyState === XMLHttpRequest.DONE) {
						if (xhr.status === 200) {
							// Insert results before the load more button
							const container = document.getElementById('load-more-container-' + blockId);
							const eventsGrid = container.parentNode.querySelector('.events-list');

							// Create temporary container to parse HTML
							const tempDiv = document.createElement('div');
							tempDiv.innerHTML = xhr.responseText;

							// Append new cards to events grid
							while (tempDiv.firstChild) {
								eventsGrid.appendChild(tempDiv.firstChild);
							}

							// Remove load more container
							container.remove();
						} else {
							// Re-enable button on error
							button.disabled = false;
							button.innerHTML = '<?php _e('Load More', 'wicket-acc'); ?>';
							console.error('Error loading more results');
						}
					}
				};

				// Send request
				const params = new URLSearchParams();
				params.append('action', 'wicket_ac_touchpoint_zoom_results');
				params.append('security', nonce);
				params.append('block_id', blockId);
				params.append('total_results', totalResults);
				params.append('counter', counter);
				params.append('type', type);
				params.append(numParam, numResults);
				params.append('touchpoint_data', touchpointData);

				xhr.send(params.toString());
			}
		</script>
		<?php
    }
}
