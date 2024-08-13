<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Modal: Touchpoint TEC
 *
 * Available data:
 *
 * $args - Touchpoint data
 **/
$tp = $args;
?>

<div class="wicketAcc-modal relative z-10 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true" data-uuid="<?php echo $tp['id']; ?>">
	<div class="backdrop fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

	<div class="modalMain fixed inset-0 z-11 w-screen overflow-y-auto">
		<div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
			<div class="modalContent relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
				<div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
					<div class="sm:flex sm:items-start">
						<div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-end rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
							<i class="fa-solid fa-xmark fa-2x close-modal cursor-pointer" aria-label="Close modal" role="button" tabindex="0"></i>
						</div>
						<div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
							<h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">
								<?php esc_html_e('Full events details', 'wicket-acc'); ?>
							</h3>
							<div class="mt-2">
								<dl class="flex flex-wrap">
									<div class="flex w-full sm:w-1/2">
										<dt class="font-medium text-gray-900"><?php esc_html_e('Event:', 'wicket-acc'); ?></dt>
										<dd class="ml-2 text-gray-700"><?php echo esc_html($tp['attributes']['data']['event_title']); ?></dd>
									</div>
									<div class="flex w-full sm:w-1/2">
										<dt class="font-medium text-gray-900"><?php esc_html_e('Badge Type:', 'wicket-acc'); ?></dt>
										<dd class="ml-2 text-gray-700"><?php //echo esc_html($tp['attributes']['data']['BadgeType']);
																										?></dd>
									</div>
									<div class="flex w-full sm:w-1/2">
										<dt class="font-medium text-gray-900"><?php esc_html_e('Start Date:', 'wicket-acc'); ?></dt>
										<dd class="ml-2 text-gray-700"><?php echo date('M j, Y', strtotime($tp['attributes']['data']['start_date'])); ?></dd>
									</div>
									<div class="flex w-full sm:w-1/2">
										<dt class="font-medium text-gray-900"><?php esc_html_e('End Date:', 'wicket-acc'); ?></dt>
										<dd class="ml-2 text-gray-700"><?php echo date('M j, Y', strtotime($tp['attributes']['data']['end_date'])); ?></dd>
									</div>
								</dl>
							</div>
						</div>
					</div>
				</div>
				<div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
					<button type="button" class="close-modal mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">
						<?php esc_html_e('Ok', 'wicket-acc'); ?>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>
