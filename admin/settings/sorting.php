<?php
/**
 * Sorting
 *
 * Registers Settings for sorting.
 *
 * @package  wicket-account-centre
 */

add_settings_section(
	'wicket_sorting_sec', // ID used to identify this section and with which to register options.
	__( 'Sort the Endpoints of My Account page.', 'wicket-acc' ),  // Title to be displayed on the administration page.
	'', // Callback used to render the description of the section.
	'wicket_acc_pro_sort_enpoints_section'      // Page on which to add this section of options.
);

add_settings_field(
	'wicket_acc_sorted_endponts', // ID used to identify the field throughout the theme.
	'', // The label to the left of the option interface element.
	'wicket_acc_sorting_callback',   // The name of the function responsible for rendering the option interface.
	'wicket_acc_pro_sort_enpoints_section', // The page on which this option will be displayed.
	'wicket_sorting_sec' // The name of the section to which this field belongs.
);

register_setting(
	'wicket_acc_pro_sort_enpoints',
	'wicket_acc_sorted_endponts'
);

add_settings_field(
	'wicket_acc_sorted_child_endpoints', // ID used to identify the field throughout the theme.
	'', // The label to the left of the option interface element.
	'wicket_acc_child_sorting_callback',   // The name of the function responsible for rendering the option interface.
	'wicket_acc_pro_sort_enpoints_section', // The page on which this option will be displayed.
	'wicket_acc_sorting_sec' // The name of the section to which this field belongs.
);

register_setting(
	'wicket_acc_pro_sort_enpoints',
	'wicket_acc_sorted_child_endpoints'
);

/**
 * Callback.
 */
function wicket_acc_sorting_callback() {

}

/**
 * Callback.
 */
function wicket_acc_child_sorting_callback() {

}
