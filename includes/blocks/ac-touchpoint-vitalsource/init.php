<?php

namespace WicketAcc\Blocks\TouchpointVitalSource;

use WicketAcc\Blocks;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Touchpoint VitalSource Block.
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
     * Get the service ID.
     *
     * Note: For staging we will be using the Service 'Aptify Conversion' in place of 'VitalSource'. This was how historical touchpoints were imported. The structure is the same as VitalSource.
     * @return string
     */
    public function get_service_id()
    {
        return 'Aptify Conversion';
    }

    /**
     * Display the block.
     *
     * @return void
     */
    protected function init_block()
    {
        $close = 0;
        $attrs = $this->is_preview ? ' ' : get_block_wrapper_attributes(
            [
                'class' => 'wicket-acc-block wicket-acc-block-touchpoints-vitalsource wicket-ac-touchpoint-vitalsource max-w-5xl mx-auto my-8',
            ]
        );

        if ($this->is_preview) {
            $args = [
                'block_name'        => 'Touchpoint VitalSource',
                'block_description' => 'This block displays registered data for VitalSource on the front-end.',
                'block_slug'        => 'wicket-ac-touchpoint-vitalsource',
            ];

            $this->blocks->render_template('preview', $args);

            return;
        }

        $title = get_field('title');
        $num_results = get_field('page_results');
        $show_view_more = get_field('show_view_more');

        $total_results = 0;
        $counter = 0;

        $touchpoints_results = $this->get_touchpoints_results($this->get_service_id());

        // Get query vars
        $num_results = isset($_REQUEST['num_results']) ? absint($_REQUEST['num_results']) : $num_results;

        $switch_link = add_query_arg(
            [
                'num_results' => $num_results,
            ],
            remove_query_arg('show')
        );

        $switch_link = esc_url($switch_link);

        $args = [
            'block_name'          => 'Touchpoint VitalSource',
            'block_description'   => 'This block displays registered data for VitalSource on the front-end.',
            'block_slug'          => 'wicket-ac-touchpoint-vitalsource',
            'attrs'               => $attrs,
            'title'               => $title,
            'num_results'         => $num_results,
            'total_results'       => $total_results,
            'counter'             => $counter,
            'close'               => $close,
            'touchpoints_results' => $touchpoints_results,
            'switch_link'         => $switch_link,
            'show_view_more'      => $show_view_more,
            'is_ajax_request'     => false,
            'is_preview'          => $this->is_preview,
        ];

        // Render block
        WACC()->Blocks->render_template('touchpoint-vitalsource', $args);
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

        // Debug with person: 6d199632-1bb8-4558-9a7e-b00c824590de
        $touchpoint_service = WACC()->MdpApi->create_touchpoint_service_id($service_id);
        $touchpoints = WACC()->MdpApi->get_current_user_touchpoints($touchpoint_service);

        // Filter touchpoints by action
        $fitered_touchpoints = self::filter_touchpoints_by_action($touchpoints, 'eBook Fulfillment');

        return $fitered_touchpoints;
    }

    /**
     * Display the touchpoints.
     *
     * @param array $touchpoint_data Touchpoint data
     * @param string $display Touchpoint display type: upcoming, past, all
     * @param int $num_results Number of results to display
     * @param bool $ajax Is ajax request?
     * @param array $config show_view_more(bool), use_x_columns(int)
     *
     * @return void
     */
    public static function display_touchpoints($touchpoint_data = [], $num_results = 5, $ajax = false, $config = [])
    {
        // Config defaults
        if (empty($config)) {
            $config['show_view_more'] = true;
        }

        // No data
        if (empty($touchpoint_data)) {
            echo '<p class="no-data">';
            _e('You do not have any ebooks.', 'wicket-acc');
            echo '</p>';

            return;
        }

        // Total results
        $total_results = count($touchpoint_data);

        // Get the offset from POST or default to 0
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;

        // Slice the array to get only the needed items
        $display_data = array_slice($touchpoint_data, $offset, $num_results);

        foreach ($display_data as $tp) :
            if (isset($tp['attributes']['data']['product_name'])) {
                $args['tp'] = $tp;
                WACC()->Blocks->render_template('touchpoint-vitalsource-card', $args);
            }
        endforeach;

        // Show more button only if not an AJAX request and there are more results
        if ($ajax === false && $config['show_view_more'] && ($offset + $num_results) < $total_results) {
            self::load_more_results($touchpoint_data, $num_results, $total_results, $offset);
        }
    }

    /**
     * Load more results.
     *
     * @param array $touchpoint_data Touchpoint data
     * @param int $num_results Number of results to display
     * @param int $total_results Total results
     * @param int $offset Offset of displayed results
     * @param string $display_type Touchpoint display type: upcoming, past, all
     *
     * @return void
     */
    public static function load_more_results($touchpoint_data = [], $num_results = 5, $total_results = 0, $offset = 0)
    {
        ?>
		<div x-data="ajaxFormHandler()">
			<div class="wicket-ac-touchpoint__vitalsource-results container" x-html="responseMessage">
			</div>

			<div class="flex load-more-container">
				<form id="form" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="post"
					@submit.prevent="submitForm">
					<input type="hidden" name="action" value="wicket_ac_touchpoint_vitalsource_results">
					<input type="hidden" name="num_results" value="<?php echo $num_results; ?>">
					<input type="hidden" name="total_results" value="<?php echo $total_results; ?>">
					<input type="hidden" name="offset" value="<?php echo $offset + $num_results; ?>">
					<?php wp_nonce_field('wicket_ac_touchpoint_vitalsource_results'); ?>

					<div x-show="loading" class="wicket-ac-touchpoint__loader flex justify-center items-center self-center">
						<i class="fas fa-spinner fa-spin"></i>
					</div>

					<button type="submit"
						class="button button--secondary show-more flex items-center font-bold text-color-dark-100 my-4"
						x-show="!loading">
						<span class="text"><?php esc_html_e('Show More', 'wicket-acc'); ?></span>
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

    /**
     * Filter touchpoints
     * Get touchpoints that has eBook Fullfillment as an action attribute.
     *
     * @param array $touchpoints
     *
     * @return array
     */
    public static function filter_touchpoints_by_action($touchpoints = [], $action = '')
    {
        if (empty($touchpoints)) {
            return $touchpoints;
        }

        $touchpoints = $touchpoints['data'];

        $filtered_touchpoints = array_filter($touchpoints, function ($touchpoint) use ($action) {
            if (isset($touchpoint['attributes']['action'])) {
                $tp_action = $touchpoint['attributes']['action'];

                return $tp_action == $action;
            }

            return false;
        });

        return $filtered_touchpoints;
    }
}
