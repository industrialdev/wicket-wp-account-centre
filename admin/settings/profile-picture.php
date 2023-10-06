<?php
/**
 * Front file of Module
 *
 * Manage all actions of front.
 *
 * @package  wicket-account-centre
 * @version  1.0.0
 */

add_settings_section(
	'wicket_acc_pro_pic_settings_sec', // ID used to identify this section and with which to register options.
	__( 'Profile Picture Settings', 'wicket-acc' ),  // Title to be displayed on the administration page.
	'wicket_acc_pro_pic_settings_sec_cb', // Callback used to render the description of the section.
	'wicket_acc_pro_pic_settings_page'      // Page on which to add this section of options.
);

add_settings_field(
	'wicket_acc_set_pro_pic_fld', // ID used to identify the field throughout the theme.
	esc_html__( 'Upload Profile Picture:', 'wicket-acc' ), // The label to the left of the option interface element.
	'wicket_acc_set_pro_pic_fld_callback',   // The name of the function responsible for rendering the option interface.
	'wicket_acc_pro_pic_settings_page', // The page on which this option will be displayed.
	'wicket_acc_pro_pic_settings_sec' // The name of the section to which this field belongs.
);

add_settings_field(
	'wicket_acc_set_pro_pic_success_message', // ID used to identify the field throughout the theme.
	esc_html__( 'Upload Profile Picture Message:', 'wicket-acc' ), // The label to the left of the option interface element.
	'wicket_acc_set_pro_pic_success_message_callback',   // The name of the function responsible for rendering the option interface.
	'wicket_acc_pro_pic_settings_page', // The page on which this option will be displayed.
	'wicket_acc_pro_pic_settings_sec' // The name of the section to which this field belongs.
);

add_settings_field(
	'wicket_acc_set_pro_pic_remove_message', // ID used to identify the field throughout the theme.
	esc_html__( 'Profile Picture Removed Message:', 'wicket-acc' ), // The label to the left of the option interface element.
	'wicket_acc_set_pro_pic_remove_message',   // The name of the function responsible for rendering the option interface.
	'wicket_acc_pro_pic_settings_page', // The page on which this option will be displayed.
	'wicket_acc_pro_pic_settings_sec' // The name of the section to which this field belongs.
);

register_setting(
	'wicket_acc_pro_pic_settings',
	'wicket_acc_set_pro_pic_fld'
);
register_setting(
	'wicket_acc_pro_pic_settings',
	'wicket_acc_set_pro_pic_success_message'
);
register_setting(
	'wicket_acc_pro_pic_settings',
	'wicket_acc_set_pro_pic_remove_message'
);

/**
 * Section callback.
 */
function wicket_acc_pro_pic_settings_sec_cb() {

}

/**
 * Field callback.
 */
function wicket_acc_set_pro_pic_fld_callback() {

	$wicket_acc_pic = get_option( 'wicket_acc_set_pro_pic_fld' );

	?>

		<input type="checkbox" name="wicket_acc_set_pro_pic_fld" value="yes" 
		<?php
		if ( 'yes' === esc_attr( $wicket_acc_pic ) ) {
			echo 'checked';}
		?>
		>
		<p class="description"><?php esc_html_e( 'Enable it to allow the customer to upload the profile picture.', 'wicket-acc' ); ?></p>

	<?php

}

/**
 * Field callback.
 */
function wicket_acc_set_pro_pic_success_message_callback() {

	$wicket_acc_val = get_option( 'wicket_acc_set_pro_pic_success_message' );

	?>

		<input type="text" name="wicket_acc_set_pro_pic_success_message" class="width-60" value="<?php echo wp_kses_post( $wicket_acc_val ); ?>">

		<p class="description"><?php esc_html_e( 'Add a message to display when profile picture is uploaded successfully.', 'wicket-acc' ); ?></p>

	<?php

}

/**
 * Field callback.
 */
function wicket_acc_set_pro_pic_remove_message() {

	$wicket_acc_val = get_option( 'wicket_acc_set_pro_pic_remove_message' );

	?>

		<input type="text" class="width-60" name="wicket_acc_set_pro_pic_remove_message" value="<?php echo wp_kses_post( $wicket_acc_val ); ?>" >

		<p class="description"><?php esc_html_e( 'Add a message to display when profile picture is removed successfully.', 'wicket-acc' ); ?></p>

	<?php

}
