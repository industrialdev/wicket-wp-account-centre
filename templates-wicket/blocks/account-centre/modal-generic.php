<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Generic modal template for blocks
 *
 * Available data:
 *
 * $args - Passed data
 **/
?>

<div class="wicketAcc-modal relative z-10 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
	<div class="backdrop fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

	<div class="modalMain fixed inset-0 z-11 w-screen overflow-y-auto">
		<div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
			<div class="modalContent relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
				<div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
					<div class="sm:flex sm:items-start">
						<div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-end rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
							<i class="fa-solid fa-xmark fa-2x close-modal cursor-pointer" aria-hidden="true"></i>
						</div>
						<div class=" mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
							<h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">
								<?php esc_html_e('Modal title', 'wicket-acc'); ?>
							</h3>
							<div class="mt-2">
								<p class="text-sm text-gray-500">
									Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
								</p>
							</div>
						</div>
					</div>
				</div>
				<div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
					<button type="button" class="close-modal inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto">
						<?php esc_html_e('Deactivate', 'wicket-acc'); ?>
					</button>
					<button type="button" class="close-modal mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">
						<?php esc_html_e('Cancel', 'wicket-acc'); ?>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>
