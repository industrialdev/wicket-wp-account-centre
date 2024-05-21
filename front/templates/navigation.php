<?php
/**
 * My Account navigation
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/navigation.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp;

$child_endpoints        = array();
$default_endpoints      = array();
$account_menu_item_slug = array();
$nav_heading 			= get_option( 'wicket_acc_nav_heading' );
$nav_layout				= get_option( 'wicket_acc_set_ep_as_fld' );

foreach ( wc_get_account_menu_items() as $endpoint => $label ) {
	$account_menu_item_slug[] = $endpoint;
}

$args         = array(
	'numberposts' => -1,
	'post_type'   => 'wicket_acc',
	'post_status' => 'publish',
);
$wicket_acc_eps = get_posts( $args );

do_action( 'woocommerce_before_account_navigation' );
?>

<nav class="myaccount-nav">
	<?php do_action( 'woocommerce_before_account_navigation_ul' ); ?>

	<?php if ($nav_heading && ($nav_layout !== 'tab') ) :
		$myaccount_page = get_option( 'woocommerce_myaccount_page_id' );
	?>
		<h2 class="myaccount-nav-heading"><a href="<?php echo get_permalink($myaccount_page); ?>"><?php echo $nav_heading; ?></a></h2>
	<?php endif; ?>

	<?php
	if (has_nav_menu('wicket-acc-nav')) : ?>
		<?php
			wp_nav_menu(array(
				'container' => false,
				'theme_location' => 'wicket-acc-nav',
				'depth' => 3,
				'menu_id' => 'wicket-acc-menu',
				'menu_class' => 'wicket-acc-menu',
				));
		?>
	<?php endif; ?>

</nav>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
