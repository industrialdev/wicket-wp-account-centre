<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * ACF exported settings
 */

add_action('acf/include_fields', function () {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}

	acf_add_local_field_group(array(
		'key' => 'group_66a932a6f0713',
		'title' => 'ACC Options',
		'fields' => array(
			array(
				'key' => 'field_66a932a81d0e4',
				'label' => 'Sidebar position',
				'name' => 'sidebar_position',
				'aria-label' => '',
				'type' => 'radio',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'wpml_cf_preferences' => 1,
				'choices' => array(
					'left' => 'Left',
					'right' => 'Right',
				),
				'default_value' => '',
				'return_format' => 'value',
				'allow_null' => 0,
				'other_choice' => 0,
				'layout' => 'vertical',
				'save_other_choice' => 0,
			),
			array(
				'key' => 'field_66a933f5c9010',
				'label' => 'Account Centre Index',
				'name' => 'account_centre_index',
				'aria-label' => '',
				'type' => 'post_object',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'wpml_cf_preferences' => 1,
				'post_type' => array(
					0 => 'page',
					1 => 'wicket_acc',
				),
				'post_status' => array(
					0 => 'publish',
				),
				'taxonomy' => '',
				'return_format' => 'id',
				'multiple' => 0,
				'allow_null' => 0,
				'bidirectional' => 0,
				'ui' => 1,
				'bidirectional_target' => array(),
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'options_page',
					'operator' => '==',
					'value' => 'wicket_acc_options',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
		'show_in_rest' => 0,
		'acfml_field_group_mode' => 'translation',
	));
});
