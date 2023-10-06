<?php
/**
 * General Settings
 *
 * Register settings and fields for general settings
 *
 * @package  wicket-account-centre
 */

add_settings_section(
	'wicket_acc_settings_sec', // ID used to identify this section and with which to register options.
	__( 'General Settings', 'wicket-acc' ), // Title to be displayed on the administration page.
	'wicket_acc_settings_sec_cb', // Callback used to render the description of the section.
	'wicket_acc_settings_page' // Page on which to add this section of options.
);

add_settings_field(
	'wicket_acc_set_ep_as_fld', // ID used to identify the field throughout the theme.
	esc_html__( 'Navigation Layout:', 'wicket-acc' ), // The label to the left of the option interface element.
	'wicket_acc_set_ep_as_fld_callback', // The name of the function responsible for rendering the option interface.
	'wicket_acc_settings_page', // The page on which this option will be displayed.
	'wicket_acc_settings_sec', // The name of the section to which this field belongs.
	array(
		esc_html__( 'Select style for endpoint navigation.', 'wicket-acc' ),
	)
);

register_setting(
	'wicket_acc_settings',
	'wicket_acc_set_ep_as_fld'
);

add_settings_field(
	'wicket_acc_nav_heading', // ID used to identify the field throughout the theme.
	esc_html__( 'Navigation Heading:', 'wicket-acc' ), // The label to the left of the option interface element.
	'wicket_acc_nav_heading_callback', // The name of the function responsible for rendering the option interface.
	'wicket_acc_settings_page', // The page on which this option will be displayed.
	'wicket_acc_settings_sec' // The name of the section to which this field belongs.
);

register_setting(
	'wicket_acc_settings',
	'wicket_acc_nav_heading'
);

add_settings_field(
	'wicket_acc_set_ep_hide_fld', // ID used to identify the field throughout the theme.
	esc_html__( 'Hide Endpoints:', 'wicket-acc' ), // The label to the left of the option interface element.
	'wicket_acc_set_ep_hide_fld_callback', // The name of the function responsible for rendering the option interface.
	'wicket_acc_settings_page', // The page on which this option will be displayed.
	'wicket_acc_settings_sec' // The name of the section to which this field belongs.
);

register_setting(
	'wicket_acc_settings',
	'wicket_acc_set_ep_hide_fld'
);

add_settings_field(
	'wicket_acc_set_ep_custom_dashboard', // ID used to identify the field throughout the theme.
	esc_html__( 'Custom Dashboard Page:', 'wicket-acc' ), // The label to the left of the option interface element.
	'wicket_acc_set_ep_custom_dashboard_callback', // The name of the function responsible for rendering the option interface.
	'wicket_acc_settings_page', // The page on which this option will be displayed.
	'wicket_acc_settings_sec' // The name of the section to which this field belongs.
);

register_setting(
	'wicket_acc_settings',
	'wicket_acc_set_ep_custom_dashboard'
);

/**
 * Section callback.
 */
function wicket_acc_settings_sec_cb() {

}

/**
 * Section callback.
 *
 * @param array $args Arguments.
 */
function wicket_acc_set_ep_as_fld_callback( $args ) {

	$value = get_option( 'wicket_acc_set_ep_as_fld' );
	$value = empty( $value ) ? 'theme' : $value;
	?>
	<input type="radio" name="wicket_acc_set_ep_as_fld" id="" value="left-sidebar" <?php echo checked( 'left-sidebar', esc_attr( $value ) ); ?> /> 
	<?php echo esc_html__( 'Left Sidebar (Replaces menu with plugin custom style)', 'wicket-acc' ); ?>
	<br/>
	<input type="radio" name="wicket_acc_set_ep_as_fld" id="" value="right-sidebar" <?php echo checked( 'right-sidebar', esc_attr( $value ) ); ?> />
	<?php echo esc_html__( 'Right Sidebar (Replaces menu with plugin custom style)', 'wicket-acc' ); ?>
	<br/>
	<input type="radio" name="wicket_acc_set_ep_as_fld" id="" value="tab" <?php echo checked( 'tab', esc_attr( $value ) ); ?> />
	<?php echo esc_html__( 'Tabs  (Replaces menu with plugin custom style)', 'wicket-acc' ); ?>
	<p class="description afreg_additional_fields_section_title"> <?php echo wp_kses_post( $args[0] ); ?> </p>
<?php

}

/**
 * Section callback.
 *
 * @param array $args Arguments.
 */
function wicket_acc_nav_heading_callback( $args ) {

	$value = get_option( 'wicket_acc_nav_heading' );
	$value = empty( $value ) ? null : $value;
	?>
	<input type="text" name="wicket_acc_nav_heading" class="width-60" value="<?php echo wp_kses_post( $value ); ?>">

	<p class="description"><?php esc_html_e( 'Optional. Displays for Left & Right layouts.', 'wicket-acc' ); ?></p>
<?php

}

/**
 * Section callback.
 */
function wicket_acc_set_ep_hide_fld_callback() {

	$wicket_acc_set_ep_hide_fld = (array) get_option( 'wicket_acc_set_ep_hide_fld' );

	$items = array(
		'dashboard'       => __( 'Dashboard', 'woocommerce' ),
		'orders'          => __( 'Orders', 'woocommerce' ),
		'downloads'       => __( 'Downloads', 'woocommerce' ),
		'edit-address'    => __( 'Addresses', 'woocommerce' ),
		'payment-methods' => __( 'Payment methods', 'woocommerce' ),
		'edit-account'    => __( 'Account details', 'woocommerce' ),
		'customer-logout' => __( 'Logout', 'woocommerce' ),
	);
	if(in_array('woocommerce-subscriptions/woocommerce-subscriptions.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	   $items['subscriptions'] = __( 'Subscriptions', 'woocommerce' );
	}
	if(in_array('woocommerce-memberships/woocommerce-memberships.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	   $items['members-area'] = __( 'Memberships', 'woocommerce' );
	}

	foreach ( $items as $key => $value ) {
		?>
		<input type="checkbox" name="wicket_acc_set_ep_hide_fld[]" value="<?php echo esc_attr( $key ); ?>" 
			<?php
			if ( in_array( esc_attr( $key ), $wicket_acc_set_ep_hide_fld, true ) ) {
				echo 'checked'; }
			?>
		><?php echo esc_attr( $value ); ?> 
		<br>

		<?php
	}
}

/**
 * Section callback.
 */
function wicket_acc_set_ep_custom_dashboard_callback() {
	?>
	<select class="wicket_acc_custom_dashboard width-60" name="wicket_acc_set_ep_custom_dashboard" id="wicket_acc_set_ep_custom_dashboard">
		<?php
		$args           = array(
			'numberposts' => -1,
			'post_type'   => 'wicket_acc',
		);
		$all_end_points = get_posts( $args );
		$selected_dashboard = get_option( 'wicket_acc_set_ep_custom_dashboard' );

		foreach ( $all_end_points as $endpoint ) {
			$endpoint_id = $endpoint->ID;

			$end_point_type = get_post_meta( $endpoint_id, 'wicket_acc_endpType_fld', true );

			if ( 'cendpoint' === $end_point_type ) {
			?>
				<option name="wicket_acc_set_ep_custom_dashboard" value="<?php echo esc_attr( $endpoint_id ); ?>" <?php echo selected( $endpoint_id, esc_attr( $selected_dashboard ) ); ?>>
					<?php echo esc_attr( $endpoint->post_title ); ?>
				</option>
			<?php }
		}
		?>
	</select>
<?php }
