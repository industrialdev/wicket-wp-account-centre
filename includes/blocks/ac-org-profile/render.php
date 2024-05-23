<?php
/**
 * Wicket Org Profile Block
 *
 **/

use Wicket_AC\Blocks\AC_Org_Profile_Block;

if(is_admin()){
  echo '[Org Profile Block]';
} else {
  echo AC_Org_Profile_Block\init( $block );
}