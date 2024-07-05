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
	$woo_memberships	= woo_get_active_memberships();

	switch($block_logic){

		case 'become_member': 
			$show_block = (!$memberships && !$woo_memberships) ? true : false;
			break;

		case 'renewal': 
      if( ! class_exists( '\Wicket_Memberships\Wicket_Memberships' ) ) {
        $membership_to_renew = is_renewal_period( $memberships, $renewal_period );
        $membership_to_renew = (!$membership_to_renew) ? is_renewal_period( $woo_memberships, $renewal_period ) : $membership_to_renew;
        $show_block = ($membership_to_renew) ? true : false;  
      } else {
        $membership_renewals = (new \Wicket_Memberships\Membership_Controller)->get_membership_callouts();
        #echo '<pre>'; var_dump( $membership_renewals );exit;
        echo '<p>For testing callouts add <pre>?wicket_wp_membership_debug_days=123</pre> to see what callouts would appear in 123 days.</p><br><br>';
        foreach( $membership_renewals as $renewal_type => $renewal_data ) {
          foreach( $renewal_data as $membership ) {
            if( !empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_MODE'] ) && $renewal_type == 'debug' ) {
              echo '<pre style="font-size:10px;">';
              echo 'DEBUG:<br>';
              echo "Renewal Type: {$renewal_type}<br>";
              echo "Membership ID: {$membership['membership']['ID']}<br>";
              echo "Membership Tier: {$membership['membership']['meta']['membership_tier_name']}<br>";
              echo "Sta {$membership['membership']['meta']['membership_starts_at']}<br>";
              echo "Early {$membership['membership']['meta']['membership_early_renew_at']}<br>";
              echo "End {$membership['membership']['meta']['membership_ends_at']}<br>";
              echo "Exp {$membership['membership']['meta']['membership_expires_at']}<br>";
              echo "End in {$membership['membership']['ends_in_days']} Days <br>";
              echo '</pre>';
              continue;
            }
            unset($links);
            #echo '<pre>'; var_dump( $membership ); echo '</pre>';
            if( !empty( $membership['membership']['meta']['membership_status'] == 'pending' )) {
              $link['link'] = [
                'title' => $membership['callout']['button_label'],
                'url' => 'mailto: '.$membership['callout']['email']
              ];    
              $links[] = $link;
            } else if( !empty( $membership['membership']['next_tier'] ) ) {
              #echo '<pre>'; var_dump( $membership['membership']['next_tier'] ); echo '</pre>';
              $links = wicket_ac_memberships_get_product_link_data( $membership );
            } else if ( !empty( $membership['membership']['form_page'] ) ) {
              #echo '<pre>'; var_dump( $membership['membership']['form_page'] ); echo '</pre>';
              $links = wicket_ac_memberships_get_page_link_data( $membership );
            }
            $title = $membership['callout']['header'];
            $description = $membership['callout']['content'];
            $attrs = get_block_wrapper_attributes(array('class' => 'callout-' . $block_logic . ' callout-' . $renewal_type ));
            echo '<div ' . $attrs . '>';
            if( !empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_MODE'] ) ) {
              echo '<pre style="font-size:10px;">';
              echo 'DEBUG:<br>';
              echo "Renewal Type: {$renewal_type}<br>";
              echo "Membership ID: {$membership['membership']['ID']}<br>";
              echo "Membership Tier: {$membership['membership']['meta']['membership_tier_name']}<br>";
              echo "Sta {$membership['membership']['meta']['membership_starts_at']}<br>";
              echo "Early {$membership['membership']['meta']['membership_early_renew_at']}<br>";
              echo "End {$membership['membership']['meta']['membership_ends_at']}<br>";
              echo "Exp {$membership['membership']['meta']['membership_expires_at']}<br>";
              echo "End in {$membership['membership']['ends_in_days']} Days <br>";
              echo '</pre>';
            }
            get_component( 'card-call-out', [ 
              'title'       => $title,
              'description' => $description,
              'links'       => $links,
              'style'       => '',
            ] );
            echo '</div>';          
          }
        }
        return;
      }
			break;
		
		case 'profile': 
			$show_block = wicket_profile_widget_validation( $mandatory_fields );
			$show_block = ($show_block && ($memberships || $woo_memberships)) ? true : false;
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