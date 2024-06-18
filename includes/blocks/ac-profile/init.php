<?php
/**
 * Wicket Individual Profile Block
 *
 **/

namespace Wicket_AC\Blocks\AC_Individual_Profile_Block;

function init( $block = [] ) { 
	get_component('widget-profile-individual', []);
}