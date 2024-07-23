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

			// Display the block
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
				<style>
					.wicket-ac-block-preview {
						border: 2px dotted var(--tec-color-border-tertiary);
						padding: 1rem;
					}
				</style>
			<?php
				return;
			}

			$num_results       = 2;
			$display           = get_field('default_display');
			$registered_action = get_field('registered_action');

			$touchpoint_service = get_create_touchpoint_service_id('MicroSpec');
			$touchpoints        = wicket_get_current_user_touchpoints($touchpoint_service);

			if (empty($registered_action)) {
				$registered_action = [
					"rsvp_to_event",
					"registered_for_an_event",
					"attended_an_event"
				];
			}

			if (!empty($_REQUEST['display'])) {
				$default_display = sanitize_text_field($_REQUEST['display']);

				if (in_array($default_display, ['upcoming', 'past', 'all'])) {
					$display = $default_display; // Sanitized
				}
			}

			if (empty($display)) {
				$display = 'upcoming';
			}

			if (isset($_REQUEST['num_results']) && !empty($_REQUEST['num_results'])) {
				$num_results = absint($_REQUEST['num_results']);
			}

			?>
			<section <?php echo $attrs; ?>>
				<div class="container">
					<div class="header flex justify-between items-center mb-6">
						<?php
						if ($display == 'upcoming') {
						?>
							<h2 class="text-2xl font-bold"><?php esc_html_e('Past Registered Events', 'wicket-acc'); ?></h2>
							<a href="#" class="upcoming-link font-bold"><?php esc_html_e('See Upcoming Registered Events →', 'wicket-acc'); ?></a>
						<?php
						}

						if ($display == 'past') {
						?>
							<h2 class="text-2xl font-bold"><?php esc_html_e('Upcoming Registered Events', 'wicket-acc'); ?></h2>
							<a href="#" class="past-link font-bold"><?php esc_html_e('See Past Registered Events →', 'wicket-acc'); ?></a>
						<?php
						}
						?>
					</div>

					<div class="events-list grid gap-6">
						<?php
						if ($display == 'upcoming' || $display == 'all') {
							$this->display_touchpoints($touchpoints['data'], 'upcoming', $num_results);
							$close++;
						}

						if ($display == 'past' || $display == 'all') {
							$this->display_touchpoints($touchpoints['data'], 'past', $num_results);
							$close++;
						}
						?>
					</div>
				</div>
			</section>
		<?php
		}

		/**
		 * Display the touchpoints
		 *
		 * @param array $touchpoint_data
		 * @param string $display
		 * @param int $num_results
		 * @return void
		 */
		protected function display_touchpoints($touchpoint_data, $display_type, $num_results)
		{
			// No data
			if (empty($touchpoint_data)) {
				echo '<p class="no-data">';
				_e('You do not have any ' . $display_type . ' data at this time.', 'wicket-acc');
				echo '</p>';
				return;
			}

		?>
			<h2>
				<?php echo ucfirst($display_type) . " Data: " . count($touchpoint_data); ?>
			</h2>
			<?php

			$counter = 0;

			foreach ($touchpoint_data as $tp) :

				if ($tp['attributes']['code'] == 'cancelled_registration_for_an_event') :
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
				endif;
			endforeach;
			?>

			<?php if ($counter == $num_results) : ?>
				<p>
					<a href='#' class='touchpoint_show_more_cta' id='touchpoint_show_more_cta' @click.prevent="show = !show" x-show="!show"><?php esc_html_e('Show More', 'wicket-acc'); ?></a>
				</p>
				<div x-show="show" x-transition class='touchpoint_show_more'>
				<?php endif; ?>

				<?php if ($counter == count($touchpoint_data) && count($touchpoint_data) > $num_results) : ?>
				</div>
<?php
				endif;
			}
		}
	}

	/**
	 * Initialize the block
	 *
	 * @param array $block
	 */
	function init($block = [], $is_preview)
	{
		// Is ACF enabled?
		if (function_exists('acf_get_field')) {
			new Wicket_Acc_Touchpoint_Microspec($is_preview);
		}
	}
