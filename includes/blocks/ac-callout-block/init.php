<?php
/**
 * Wicket Callout Block
 *
 **/

namespace Wicket_AC\Blocks\AC_Callout_Block;

function init( $block = [] ) { 
	$block_logic 			= get_field('block_logic');
	$renewal_period 	= get_field('renewal_period');
	$mandatory_fields = get_field('select_profile_mandatory_fields');
	$title       			= get_field( 'ac_callout_title' );
	$description 			= get_field( 'ac_callout_description' );
	$links       			= get_field( 'ac_callout_links' );
	$memberships 			= wicket_get_active_memberships();

	switch($block_logic){

		case 'become_member': 
			$show_block = (!$memberships) ? true : false;
			break;

		case 'renewal': 
			$membership_to_renew = is_renewal_period( $memberships, $renewal_period );
			$show_block = ($membership_to_renew) ? true : false;
			break;
		
		case 'profile': 
			$show_block = wicket_profile_widget_validation( $mandatory_fields );
			break;
		
	}

	$attrs = get_block_wrapper_attributes(array('class' => 'callout-' . $block_logic));

	// Show the block if conditional logic is true OR if viewing in the block editor
	if($show_block || is_admin()): 

	echo '<div ' . $attrs . '>';
	
	get_component( 'card-call-out', [ 
		'title'       => $title,
		'description' => $description,
		'links'       => $links,
		'style'       => '',
	] );
	echo '</div>';

	endif; 
}