<?php
/*
* Template Name: Account Center - Tpl
*/ 
get_header();
$ac_post_id = 12051; //the Account Center Custom Post ID for managing content on front-end
$wicket_acc_ep_as = get_option( 'wicket_acc_set_ep_as_fld' );

if( !defined('WICKET_ACC_PLUGIN_DIR') || empty( $wicket_acc_ep_as )) {
  ?>
    <div class="woocommerce-wicket--container" style="color:red; font-weight:bold;text-decoration:underline;">
      Please activate and configure the Wicket Account Center plugin to use this template. 
    </div>
  <?php
  get_footer();
  exit;
}

if ( have_posts() ) :
while ( have_posts() ) :
  the_post(); 
  the_content();
  ?>
  <div class="woocommerce-wicket--container">
<?php
  
if ( 'left-sidebar' == $wicket_acc_ep_as ) {
  include_once WICKET_ACC_PLUGIN_DIR . 'front/templates/navigation.php';
}
?>
  <?php 
  ?>
<div class="woocommerce-wicket--account-centre">
  <h2 class="wicket-h2">--> This is a custom Account Center Template "/account-center-tpl.php" <--</h2>
  <p>
    Copy and customise this template file and assign to a WP Page.<br>
    Add the Wicket <b>'Banner'</b> block with added class: <b>alignfull</b> to span screen width.<br>
    To add content on the front-end create the corresponding Account Center Page and note the Post_ID number.<br>
    Set the Post ID at the top of your template to inject the content: <b>&lt;?php $ac_post_id = 12051; ?&gt;</b>
  </p>
  </h2>
  <?php 
    $the_post = get_post( $ac_post_id ); 
    if( get_post_field( 'post_status', $ac_post_id ) == 'publish' ) {
      echo apply_filters('the_content', get_post_field('post_content', $ac_post_id));
    }
  ?>
</div>

<?php
    if ( 'right-sidebar' == $wicket_acc_ep_as ) {
			include_once WICKET_ACC_PLUGIN_DIR . 'front/templates/navigation.php';
    }    
    endwhile;
endif;
?>
</div>
<?php
get_footer();
