<?php

namespace WicketAcc\Blocks\TouchpointMoodle;

use WicketAcc\Blocks;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Touchpoint Moodle Block.
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
                'class' => 'wicket-acc-block wicket-acc-block-touchpoints-moodle flex flex-col gap-8',
            ]
        );

        if ($this->is_preview) {
            $args = [
                'block_name'        => 'Touchpoints Moodle',
                'block_description' => 'This block displays registered data from Moodle on the front-end.',
                'block_slug'        => 'wicket-ac-touchpoint-moodle',
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

        $touchpoints_results = $this->get_touchpoints_results('Moodle');

        // echo '<pre>';
        // print_r($touchpoints_results);
        // echo '</pre>';
        // die;

        if (empty($registered_action)) {
            $registered_action = [
                'enrolled_in_a_course',
                'completed_a_course',
                'created_account',
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
                $show_param   => $display_other,
                $num_param => $num_results,
            ],
            remove_query_arg([$show_param, $num_param])
        );

        $switch_link = esc_url($switch_link);

        $args = [
            'block_id'                       => $block_id,
            'block_name'                     => 'Touchpoint Moodle',
            'block_description'              => 'This block displays registered data from Moodle on the front-end.',
            'block_slug'                     => 'wicket-ac-touchpoint-moodle',
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
        WACC()->Blocks->render_template('touchpoint-moodle', $args);
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
            _e('You do not have any data at this time.', 'wicket-acc');
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
                'title' => __('You have no upcoming events', 'wicket-acc'),
                'style' => 'secondary',
            ]);
        }

        $counter = 0;

        foreach ($touchpoint_data as $key => $tp) :
            $counter++;

            $args['tp'] = $tp;
            WACC()->Blocks->render_template('touchpoint-moodle-card', $args);

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
            return $touchpoint_data;
        }

        // Ensure $display_type is valid: upcoming, past, all
        $display_type = sanitize_text_field($display_type);
        $display_type = in_array($display_type, ['upcoming', 'past', 'all'], true) ? $display_type : 'upcoming';

        // Get current timestamp
        $current_timestamp = current_datetime()->getTimestamp();

        // Now, filter by registered_action
        $filtered_touchpoint_data = array_filter($touchpoint_data, function ($touchpoint) use ($registered_action) {
            return is_array($registered_action) && in_array($touchpoint['attributes']['code'], $registered_action, true);
        });

        return $filtered_touchpoint_data;
    }

    /**
     * Load more results.
     *
     * @param array $touchpoint_data Touchpoint data
     * @param int $num_results Number of results to display
     * @param int $total_results Total results
     * @param int $counter Counter of displayed results
     * @param string $display_type Touchpoint display type: upcoming, past, all
     * @param bool $ajax Whether the results are being loaded via AJAX
     *
     * @return void
     */
    public static function load_more_results($touchpoint_data = [], $num_results = 5, $total_results = 0, $counter = 0, $display_type = 'upcoming', $ajax = false, $block_id = 0)
    {
        // Sanitize
        $num_results = absint($num_results);
        $total_results = absint($total_results);
        $counter = absint($counter);
        $touchpoint_data_input = base64_encode(maybe_serialize($touchpoint_data));
        $received_results_count = count($touchpoint_data);
        ?>

        <div x-data="ajaxFormHandler_<?php echo esc_attr($block_id); ?>()">
            <div class="wicket-ac-touchpoint__moodle-results container">
                <div class="events-list grid gap-6"
                    x-html="responseMessage_<?php echo esc_attr($block_id); ?>">
                </div>
            </div>

            <div class="flex justify-center items-center">
                <form
                    action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                    method="post" @submit.prevent="submitForm">
                    <input type="hidden" name="action" value="wicket_ac_touchpoint_moodle_results">
                    <input type="hidden" name="num_results"
                        value="<?php echo esc_attr($num_results); ?>">
                    <input type="hidden" name="total_results"
                        value="<?php echo esc_attr($total_results); ?>">
                    <input type="hidden" name="type"
                        value="<?php echo esc_attr($display_type); ?>">
                    <input type="hidden" name="counter"
                        value="<?php echo esc_attr($counter); ?>">
                    <input type="hidden" name="touchpoint_data"
                        value="<?php echo esc_html($touchpoint_data_input); ?>">
                    <?php wp_nonce_field('wicket_ac_touchpoint_moodle_results'); ?>

                    <div x-show="loading" class="wicket-ac-touchpoint__loader flex justify-center items-center self-center">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>

                    <?php $show_more_classes = $received_results_count < 1 ? 'hidden' : ''; ?>

                    <?php
                            get_component('button', [
                                'variant' => 'secondary',
                                'type'    => 'submit',
                                'classes' => ['touchpoint-show-more', 'my-4', $show_more_classes],
                                'label'   => __('Show More', 'wicket-acc'),
                                'prefix_icon' => 'fa-solid fa-caret-down',
                                'atts'   => [
                                    'x-show="!loading && !buttonClicked"',
                                ],
                            ]);
        ?>
                </form>
            </div>
        </div>

        <script>
            function ajaxFormHandler_<?php echo esc_attr($block_id); ?>() {
                return {
                    loading: false,
                    <?php if ($received_results_count < 1) : ?>
                        buttonClicked: true,
                    <?php else : ?>
                        buttonClicked: false,
                    <?php endif; ?>
                    responseMessage_<?php echo esc_attr($block_id); ?>: '',
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
                                    this.responseMessage_<?php echo esc_attr($block_id); ?> =
                                        data;
                                    this.buttonClicked = true;
                                } else {
                                    this.responseMessage_<?php echo esc_attr($block_id); ?> =
                                        '<?php esc_html_e('An error occurred. No data.', 'wicket-acc'); ?>';
                                }
                            })
                            .catch(error => {
                                this.loading = false;
                                this.responseMessage_<?php echo esc_attr($block_id); ?> =
                                    '<?php esc_html_e('An error occurred. Failed.', 'wicket-acc'); ?>';
                            });
                    }
                };
            }
        </script>
<?php
    }
}
