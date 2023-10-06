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

$child_endpoints        = array();
$default_endpoints      = array();
$account_menu_item_slug = array();

foreach ( wc_get_account_menu_items() as $endpoint => $label ) {
	$account_menu_item_slug[] = $endpoint;
}

$args = array(
	'numberposts' => -1,
	'post_type'   => 'wicket_acc',
	'post_status' => 'publish',
);
$wicket_acc_eps = get_posts( $args );

?>

<nav class="woocommerce-MyAccount-navigation">	
	<ul>
		<?php
		foreach ( wc_get_account_menu_items() as $endpoint => $label ) :

			$group_endpoint   = false;
			$child_endpoint   = false;
			$custom_endpoint  = false;
			$endpoint_post_id = 0;

			foreach ( (array) $wicket_acc_eps as $endpoint_post ) {

				$slug = get_post_meta( $endpoint_post->ID, 'wicket_acc_slug_fld', true );

				if ( $slug === $endpoint ) {
					$endpoint_post_id = $endpoint_post->ID;
					$custom_endpoint  = true;
				}
			}

			if ( $custom_endpoint ) {

				$post_object    = get_post( $endpoint_post_id );
				$end_point_type = get_post_meta( $post_object->ID, 'wicket_acc_endpType_fld', true );

				if ( 'group_endpoint' === $end_point_type ) {

					$child_endpoints = (array) json_decode( get_post_meta( $post_object->ID, 'wicket_acc_group_child', true ) );

					$default_endpoints = (array) json_decode( get_post_meta( $post_object->ID, 'wicket_acc_group_default_filed', true ) );
					$group_endpoint    = true;

				} else {

					if ( is_array( $wicket_acc_eps ) ) {

						foreach ( $wicket_acc_eps as $endpoint_post ) {

							$_type = get_post_meta( $endpoint_post->ID, 'wicket_acc_endpType_fld', true );

							if ( 'group_endpoint' === $_type ) {

								$_child = (array) json_decode( get_post_meta( $endpoint_post->ID, 'wicket_acc_group_child', true ) );

								$slug = get_post_meta( $endpoint_post->ID, 'wicket_acc_slug_fld', true );

								if ( ! in_array( $slug, $account_menu_item_slug, true ) ) {
									continue;
								}

								if ( in_array( (string) $endpoint_post_id, $_child, true ) ) {
									$child_endpoint = true;
									break;
								}
							}
						}
					}

					if ( $child_endpoint ) {
						continue;
					}
				}
			} else {

				$args = array(
					'numberposts' => -1,
					'post_type'   => 'wicket_acc',
					'post_status' => 'publish',
				);

					$wicket_acc_eps = get_posts( $args );

				if ( is_array( $wicket_acc_eps ) ) {
					foreach ( $wicket_acc_eps as $endpoint_post ) {

						$_type = get_post_meta( $endpoint_post->ID, 'wicket_acc_endpType_fld', true );

						if ( 'group_endpoint' === $_type ) {

							$_child = (array) json_decode( get_post_meta( $endpoint_post->ID, 'wicket_acc_group_default_filed', true ) );
							$slug   = get_post_meta( $endpoint_post->ID, 'wicket_acc_slug_fld', true );

							if ( ! in_array( $slug, $account_menu_item_slug, true ) ) {
								continue;
							}

							if ( is_array( $_child ) && in_array( $endpoint, $_child, true ) ) {
								$child_endpoint = true;
								break;
							}
						}
					}
					if ( $child_endpoint ) {
						continue;
					}
				}
			}
			if ( ! $group_endpoint ) {
				?>
						<li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $endpoint ) ); ?>">
							<span class="ui-icon ui-icon-arrowthick-2-n-s"></span>
							<input type="hidden" name="wicket_acc_sorted_endponts[]" value="<?php echo esc_attr( $endpoint ); ?>">
							<?php echo esc_html( $label ); ?>
						</li>
					<?php
			} else {
				?>
					<li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $endpoint ) ); ?> group_endpoint">
						<span class="ui-icon ui-icon-arrowthick-2-n-s"></span>
						<input type="hidden" name="wicket_acc_sorted_endponts[]" value="<?php echo esc_attr( $endpoint ); ?>">
						<span class="group-endpoint-title"><?php echo esc_html( $label ); ?></span>
						<ul class="wicket_acc_sub_list">
						<?php
						$parent_endpoint       = $endpoint;
						$group_child_endpoints = array();
						foreach ( $default_endpoints as $endpoint ) {
							$items = array(
								'dashboard'       => __( 'Dashboard', 'wicket-acc' ),
								'orders'          => __( 'Orders', 'wicket-acc' ),
								'downloads'       => __( 'Downloads', 'wicket-acc' ),
								'edit-address'    => __( 'Addresses', 'wicket-acc' ),
								'payment-methods' => __( 'Payment methods', 'wicket-acc' ),
								'edit-account'    => __( 'Account details', 'wicket-acc' ),
								'customer-logout' => __( 'Logout', 'wicket-acc' ),
							);
							$label = $items[ $endpoint ];
							if ( ! in_array( $endpoint, $account_menu_item_slug, true ) ) {
								continue;
							}

							$group_child_endpoints[ $endpoint ] = $items[ $endpoint ];
						}

						foreach ( $child_endpoints as $endpoint_id ) {
							$post_object = get_post( $endpoint_id );
							$endpoint    = get_post_meta( $endpoint_id, 'wicket_acc_slug_fld', true );
							if ( ! in_array( $endpoint, $account_menu_item_slug, true ) ) {
								continue;
							}
							$label = get_post_meta( $endpoint_id, 'wicket_acc_menu_title', true );
							if ( empty( $label ) ) {
								$label = $post_object->post_title;
							}

							$group_child_endpoints[ $endpoint ] = $label;

						}

						$child_sorting = get_option( 'wicket_acc_sorted_child_endpoints' );

						$sorted_child_endpoint = array();

						if ( isset( $child_sorting[ $parent_endpoint ] ) ) {

							foreach ( (array) $child_sorting[ $parent_endpoint ] as $value ) {

								if ( isset( $group_child_endpoints[ $value ] ) ) {

									$sorted_child_endpoint[ $value ] = $group_child_endpoints[ $value ];

								}
							}
						}

						if ( empty( $sorted_child_endpoint ) ) {

							$sorted_child_endpoint = $group_child_endpoints;
						}

						$sorted_child_endpoint = array_unique( array_merge( $sorted_child_endpoint, $group_child_endpoints ) );

						foreach ( $sorted_child_endpoint as $endpoint => $label ) {
							?>
								<li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $endpoint ) ); ?>">
									<span class="ui-icon ui-icon-arrowthick-2-n-s"></span>
									<input type="hidden" name="wicket_acc_sorted_child_endpoints[<?php echo esc_attr( $parent_endpoint ); ?>][]" value="<?php echo esc_attr( $endpoint ); ?>">
									<?php echo esc_html( $label ); ?>
								</li>
							<?php
						}
						?>
						</ul>
					</li>
				<?php
			}

		endforeach;
		?>
	</ul>
</nav>

<script>
	jQuery(document).ready(function($) {
		$('table.form-table').hide();
	});
</script>
