<?php
/**
 * Business information partial.
 */
if (!isset($org_id)) {
    $org_id = '';
}

$header ??= ['name' => '', 'address' => '', 'email' => '', 'phone' => ''];
$sections ??= [];
$state ??= [];
$notice ??= null;
?>
<div class="business-info">
	<?php if ($notice) : ?>
		<div class="notifications-wt_inline-container">
			<div class="notification notification-<?php echo esc_attr($notice['type']); ?> notification-wt_inline">
				<div class="notification-icon">
					<?php
                    $icon = match ($notice['type']) {
                        'success' => '✓',
                        'error' => '✕',
                        'warning' => '!',
                        default => 'i'
                    };
	    echo esc_html($icon);
	    ?>
				</div>
				<div class="notification-content">
					<div class="notification-message"><?php echo wp_kses_post($notice['message']); ?></div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php if (!empty($header['name'])) : ?>
		<section class="business-info__header">
			<h2 class="wp-block-heading has-heading-sm-font-size wt_text-xl wt_font-bold wt_mb-2"><?php echo esc_html($header['name']); ?></h2>
			<?php if (!empty($header['address'])) : ?>
				<p class="wt_text-sm wt_text-gray-600"><?php echo esc_html($header['address']); ?></p>
			<?php endif; ?>
			<?php if (!empty($header['email'])) : ?>
				<p class="wt_text-sm wt_text-gray-600"><?php echo esc_html($header['email']); ?></p>
			<?php endif; ?>
			<?php if (!empty($header['phone'])) : ?>
				<p class="wt_text-sm wt_text-gray-600"><?php echo esc_html($header['phone']); ?></p>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php
    // Check for seat limits or other informational banners using config
    $configService = new WicketORM\Services\ConfigService();
$seat_limit_info = $configService->getBusinessInfoSeatLimitInfo();
if ($seat_limit_info) : ?>
		<div class="seat-limit-notice">
			<?php echo wp_kses_post($seat_limit_info); ?>
		</div>
	<?php endif; ?>

	<?php if (empty($sections)) : ?>
		<p><?php esc_html_e('Business information is not available for this organization.', 'wicket-acc'); ?></p>
	<?php else : ?>
	<form class="business-info__form"
	      ds-post="<?php echo esc_url(rest_url('org-management/v1/business/info')); ?>"
	      ds-target="#business-info-container"
	      ds-swap="innerHTML">
		<input type="hidden" name="org_id" value="<?php echo esc_attr($org_id); ?>">
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('org_management_business_info_' . $org_id)); ?>">

		<?php foreach ($sections as $section_key => $section) :
		    $section_state = $state[$section_key] ?? ['values' => [], 'other' => ''];
		    $selected = $section_state['values'] ?? [];
		    $other_value = $section_state['other'] ?? '';
		    ?>
			<fieldset class="business-info__section wt_mb-6">
				<legend class="wt_text-lg wt_font-semibold wt_mb-3"><?php echo esc_html($section['label']); ?></legend>
				<div class="wt_flex wt_flex-col wt_gap-3">
					<?php foreach ($section['options'] as $option) :
					    $input_id = sprintf('%s_%s', $section_key, $option['slug']);
					    $is_checked = in_array($option['value'], $selected, true);
					    ?>
						<div class="business-info__option wt_flex wt_items-center wt_gap-2">
							<input
								id="<?php echo esc_attr($input_id); ?>"
								type="checkbox"
								name="<?php echo esc_attr($section_key); ?>[]"
								value="<?php echo esc_attr($option['value']); ?>"
								<?php checked($is_checked); ?>
							>
							<label for="<?php echo esc_attr($input_id); ?>" class="wt_cursor-pointer">
								<?php echo esc_html($option['label']); ?>
							</label>
						</div>
						<?php if ($option['is_other']) : ?>
							<div class="wt_ml-6">
								<label class="wt_block wt_text-sm wt_text-gray-600" for="<?php echo esc_attr($input_id . '_other'); ?>">
									<?php esc_html_e('Please specify', 'wicket-acc'); ?>
								</label>
								<input
									id="<?php echo esc_attr($input_id . '_other'); ?>"
									type="text"
									name="<?php echo esc_attr($section_key . '_other'); ?>"
									value="<?php echo esc_attr($other_value); ?>"
									class="wt_border wt_border-gray-300 wt_p-2 wt_rounded-sm wt_w-full"
								>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endforeach; ?>

		<div class="wt_flex wt_justify-end">
			<button type="submit" class="button button--primary component-button"><?php esc_html_e('Save Changes', 'wicket-acc'); ?></button>
		</div>
	</form>
	<?php endif; ?>
</div>
