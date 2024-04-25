<?php
/**
 * Front file of Module
 *
 * Manage all actions of front.
 *
 * @package  wicket-account-centre
 * @version  1.0.0
 */

if ( ! class_exists( 'Wicket_Acc_Front' ) ) {
	/**
	 * Front class of module.
	 */
	class Wicket_Acc_Front {
		/**
		 * Constructor.
		 */
		public function __construct() {

			add_action( 'wp_enqueue_scripts', array( $this, 'wicket_acc_front_scripts' ) );

			add_action( 'init', array( $this, 'wicket_acc_add_endpoints_and_content' ) );

			add_filter( 'woocommerce_get_query_vars', array( $this, 'wicket_acc_custom_query_vars' ), 1 );

			add_filter( 'woocommerce_account_menu_items', array( $this, 'wicket_acc_custom_my_account_menu_items' ), 1200 );

			$wicket_acc_allow_pp = get_option( 'wicket_acc_set_pro_pic_fld' );

			if ( 'yes' === esc_attr( $wicket_acc_allow_pp ) ) {

				add_action( 'woocommerce_before_account_navigation_ul', array( $this, 'wicket_acc_cus_pp_form' ), 10, 2 );

				add_filter( 'get_avatar', array( $this, 'wicket_acc_cus_change_avatar' ), 1, 5 );

			}

			add_action( 'init', array( $this, 'wicket_acc_override_templates' ) );

			add_filter( 'the_title', array($this, 'wicket_acc_custom_endpoint_titles'), 10, 2 );

			add_filter( 'the_title', array($this, 'wicket_acc_core_endpoint_titles'), 10, 2 );


		}

		/**
		 * Enqueue scripts for front.
		 */
		public function wicket_acc_front_scripts() {

			if ( is_admin() ) {
				return;
			}
			// Upload Font-Awesome 4.
			wp_enqueue_style( 'Font_Awesome', 'https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css', false, '1.0' );

			// Enqueue Front JS.
			wp_enqueue_script( 'wicket_acc_front', plugins_url( '../assets/js/wicket_acc_front.js', __FILE__ ), array( 'jquery' ), '1.0', true );
			// Enqueue Front CSS.
			wp_enqueue_style( 'wicket_acc_front', plugins_url( '../assets/css/wicket_acc_front.css', __FILE__ ), false, '1.0' );
			wp_enqueue_style( 'wicket_acc_grid', plugins_url( '../assets/css/wicket_acc_grid.css', __FILE__ ), false, '1.0' );

		}

		/**
		 * Get endpoints.
		 */
		public function wicket_acc_get_endpoints() {

			$wicket_acc_custom_dashboard_id = get_option( 'wicket_acc_set_ep_custom_dashboard' );

			$args = array(
				'numberposts' => -1,
				'post_type'   => 'wicket_acc',
				'post_status' => 'publish',
				'orderby'     => 'menu_order',
				'order'       => 'ASC',
				'post__not_in' => array($wicket_acc_custom_dashboard_id), /* exclude custom dashboard endpoint */
			);

			$wicket_acc_eps = get_posts( $args );

			return $wicket_acc_eps;

		}

		/*
		 * Change endpoint title for custom endpoints.
		 *
		 */
		public function wicket_acc_custom_endpoint_titles( $title, $id ) {

			$wicket_acc_endpoints = $this->wicket_acc_get_endpoints();
			if ( is_array( $wicket_acc_endpoints ) ) {
				foreach ( $wicket_acc_endpoints as $wicket_endpoint ) {	

					global $wp_query;
					$ep_id = $wicket_endpoint->ID;
					$wicket_slug = get_post_meta( intval( $ep_id ), 'wicket_acc_slug_fld', true );
					$wicket_acc_menu_title = get_post_meta( intval( $ep_id ), 'wicket_acc_menu_title', true );
					$is_endpoint = isset( $wp_query->query_vars[$wicket_slug] );

					if ( ($id == $wp_query->queried_object_id) && $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
						// New page title.
						$title = $wicket_acc_menu_title;
						remove_filter( 'the_title', 'wicket_acc_custom_endpoint_titles' );
					}

				}
			}

			return $title;

		}

		/*
		 * Change endpoint title for core WooCommerce endpoints.
		 *
		 * NEED TO ADD SETTINGS TO MANAGE THESE TITLES FROM ADMIN
		 *
		 */
		public function wicket_acc_core_endpoint_titles( $title, $id ) {
			
			global $wp_query;
			if ( ($id == $wp_query->queried_object_id) && in_the_loop() && ! is_admin() ) {

			    if ( is_wc_endpoint_url( 'downloads' ) ) { // add your endpoint urls
			        $title = "My Downloads"; // change your entry-title
			    }
			    elseif ( is_wc_endpoint_url( 'orders' ) ) {
			        $title = "My Orders";
			    }
			    elseif ( is_wc_endpoint_url( 'edit-account' ) ) {
			        $title = "Change My Details";
			    }
			    elseif ( is_wc_endpoint_url( 'edit-address' ) ) {
			        $title = "My Addresses";
			    }
			    elseif ( is_wc_endpoint_url( 'payment-methods' ) ) {
			        $title = "My Payment Methods";
			    }
			    elseif ( !is_wc_endpoint_url() && is_account_page() ) {
			        $title = "My Account Centre";
			    }

			}

		    return $title;

		}

		/**
		 * Endpoint contents.
		 */
		public function wicket_acc_add_endpoints_and_content() {

			if ( ! empty( get_option( 'wicket_acc_set_ep_as_fld' ) ) ) {
				remove_action( 'woocommerce_account_navigation', 'woocommerce_account_navigation', 10 );
				add_action( 'woocommerce_account_navigation', array( $this, 'wicket_acc_account_navigation' ), 10 );

			}

			$wicket_acc_endpoints = $this->wicket_acc_get_endpoints();

			if ( is_array( $wicket_acc_endpoints ) ) {

				foreach ( $wicket_acc_endpoints as $wicket_endpoint ) {

					$ep_id = $wicket_endpoint->ID;

					$wicket_slug = get_post_meta( intval( $ep_id ), 'wicket_acc_slug_fld', true );

					add_rewrite_endpoint( $wicket_slug, EP_ROOT | EP_PAGES, $wicket_slug );

					add_action(
						'woocommerce_account_' . $wicket_slug . '_endpoint',
						function() use ( $ep_id ) {
							$this->wicket_acc_get_custom_endpoint_content( $ep_id );
						}, 5
					);

				}

				flush_rewrite_rules();
			}
		}
	
		/**
		 * Replace navigation.
		 */
		public function wicket_acc_account_navigation() {

			include_once WICKET_ACC_PLUGIN_DIR . 'front/templates/navigation.php';
		}

		/**
		 * Register Query variables.
		 *
		 * @param array $vars Variables.
		 */
		public function wicket_acc_custom_query_vars( $vars ) {

			$wicket_acc_endpoints = $this->wicket_acc_get_endpoints();

			if ( is_array( $wicket_acc_endpoints ) ) {

				foreach ( $wicket_acc_endpoints as $wicket_endpoint ) {
					$ep_id              = $wicket_endpoint->ID;
					$wicket_slug          = get_post_meta( intval( $ep_id ), 'wicket_acc_slug_fld', true );
					$vars[ $wicket_slug ] = $wicket_slug;
				}
			}

			return $vars;

		}

		/**
		 * Add custom endpoints in nav menus.
		 *
		 * @param array $items Menu items.
		 */
		public function wicket_acc_custom_my_account_menu_items( $items ) {

			$wicket_acc_endpoints = $this->wicket_acc_get_endpoints();

			$hide_ep_list = get_option( 'wicket_acc_set_ep_hide_fld' );

			// get Current User Role.
			$curr_user = wp_get_current_user();

			$user_data = get_user_meta( $curr_user->ID );

			$curr_user_role = $curr_user->roles;

			// Remove the logout menu item.
			$logout = $items['customer-logout'];

			unset( $items['customer-logout'] );

			if ( is_array( $wicket_acc_endpoints ) ) {

				foreach ( $wicket_acc_endpoints as $wicket_endpoint ) {

					// Insert your custom endpoint.

					$ep_id = $wicket_endpoint->ID;

					$wicket_slug = get_post_meta( intval( $ep_id ), 'wicket_acc_slug_fld', true );

					$wicket_acc_icon = get_post_meta( intval( $ep_id ), 'wicket_acc_icon_fld', true );

					if ( ! empty( $wicket_acc_icon ) ) {
						$wicket_acc_icon = '\\' . $wicket_acc_icon;
					}

					$wicket_acc_ep_type = get_post_meta( intval( $ep_id ), 'wicket_acc_endpType_fld', true );

					$wicket_acc_user_role = get_post_meta( intval( $ep_id ), 'wicket_acc_user_role', true );

					$wicket_acc_menu_title = get_post_meta( intval( $ep_id ), 'wicket_acc_menu_title', true );

					if ( empty( $wicket_acc_menu_title ) ) {
						$wicket_acc_menu_title = $wicket_endpoint->post_title;
					}

					if ( is_user_logged_in() ) {

						if ( is_array( $wicket_acc_user_role ) || empty( $wicket_acc_user_role ) ) {

							if ( in_array( $curr_user_role, (array)$wicket_acc_user_role, true ) || empty( $wicket_acc_user_role ) || is_admin() ) {

								$items[ $wicket_slug ] = wp_kses_post( $wicket_acc_menu_title );

							}
						}
					}

					if ( ! is_user_logged_in() ) {

						if ( is_array( $wicket_acc_user_role ) && ! empty( $wicket_acc_user_role ) ) {

							if ( in_array( 'guest', $wicket_acc_user_role, true ) || is_admin() ) {

								$items[ $wicket_slug ] = wp_kses_post( $wicket_endpoint->post_title );

							}
						}
					}

					if ( ! empty( $wicket_acc_icon ) && ! is_admin() ) {

						if ( 'group_endpoint' === $wicket_acc_ep_type ) {
							?>
							<style type="text/css">

								.myaccount-nav ul li.nav-item--<?php echo esc_attr( $wicket_slug ); ?> a.group_endpoint_a::before {

									content: "<?php echo esc_attr( $wicket_acc_icon ); ?>";

								}

							</style>

							<?php

						} else {

							?>
							<style type="text/css">

								.myaccount-nav ul li.nav-item--<?php echo esc_attr( $wicket_slug ); ?> a::before {

									content: "<?php echo esc_attr( $wicket_acc_icon ); ?>";
								}

							</style>

							<?php
						}
					}
				}
			}

			// Insert back the logout item.
			$items['customer-logout'] = $logout;

			if ( ! empty( $hide_ep_list ) ) {
				foreach ( $hide_ep_list as $h_ep ) {

					unset( $items[ esc_attr( $h_ep ) ] );
				}
			}

			$sorted_endpoints = get_option( 'wicket_acc_sorted_endponts' );

			$child_sorting = get_option( 'wicket_acc_sorted_child_endpoints' );

			$sorted_items = array();
			foreach ( (array) $sorted_endpoints as $value ) {
				if ( isset( $items[ $value ] ) ) {
					$sorted_items[ $value ] = $items[ $value ];
				}

				if ( isset( $child_sorting[ $value ] ) ) {
					foreach ( (array) $child_sorting[ $value ] as $value1 ) {
						if ( isset( $items[ $value1 ] ) ) {
							$sorted_items[ $value1 ] = $items[ $value1 ];
						}
					}
				}
			}

			$sorted_items = array_unique( array_merge( $sorted_items, $items ) );

			return $sorted_items;
		}

		/**
		 * Get custom content.
		 *
		 * @param int $id ID of menu item.
		 */
		public function wicket_acc_get_custom_endpoint_content( $id = '' ) {

			$ep_id = $id;

			$wicket_acc_ep_type = get_post_meta( intval( $ep_id ), 'wicket_acc_endpType_fld', true );

			$wicket_acc_page = get_post_meta( intval( $ep_id ), 'wicket_acc_page_fld', true );

			$wicket_acc_link = get_post_meta( intval( $ep_id ), 'wicket_acc_link_fld', true );

			if ( 'sendpoint' === esc_attr( $wicket_acc_ep_type ) ) {

				//echo '<h2 class="wicket_acc_page_title">'.wp_kses_post( apply_filters( 'the_content', get_post_meta( intval( $ep_id ), 'wicket_acc_menu_title', true ) ) ).'</h2>';
				
				$post = get_post($ep_id); // specific post
				$the_content = apply_filters('the_content', $post->post_content);
				if ( !empty($the_content) ) {
				  echo $the_content;
				}

				return;

			} elseif ( 'pendpoint' === $wicket_acc_ep_type ) {

				$redirect = get_permalink( $wicket_acc_page );

				if ( wp_http_validate_url( $redirect ) ) {
					if ( headers_sent() ) {
						echo '<script>jQuery(document).ready(function(){ 
							window.location.href = "' . esc_url( $redirect ) . '"
						});</script>';
					} else {
						wp_safe_redirect( esc_url( $redirect ) );
						exit;
					}
				} else {
					wc_add_notice( esc_html__( 'The page URL is not valid', 'wicket-acc' ), $notice_type = 'error' );
					return;
				}
			} elseif ( 'lendpoint' === $wicket_acc_ep_type ) {

				$redirect = esc_url( $wicket_acc_link );

				if ( wp_http_validate_url( $redirect ) ) {
					if ( headers_sent() ) {
						echo '<script>jQuery(document).ready(function(){ 
							window.location.href = "' . esc_url( $redirect ) . '"
						});</script>';
					} else {
						wp_safe_redirect( esc_url( $redirect ) );
						exit;
					}
				} else {
					wc_add_notice( esc_html__( 'The page URL is not valid', 'wicket-acc' ), $notice_type = 'error' );
					return;
				}
			} elseif ( 'cendpoint' === esc_attr( $wicket_acc_ep_type ) ) {

				//echo '<h2 class="wicket_acc_page_title">'.wp_kses_post( apply_filters( 'the_content', get_post_meta( intval( $ep_id ), 'wicket_acc_menu_title', true ) ) ).'</h2>';

				$post = get_post($ep_id); // specific post
				$the_content = apply_filters('the_content', $post->post_content);
				if ( !empty($the_content) ) {
				  echo $the_content;
				}

				return;				

			} 

		}

		/**
		 * Get custom content.
		 *
		 * @param array $atts Array of form.
		 * @param array $content Content.
		 */
		public function wicket_acc_cus_pp_form( $atts, $content = null ) {

			$user_id = get_current_user_id();

			$one_action = false;


			$files = (array) wp_unslash( $_FILES );

			if ( isset( $files['profile_pic'] ) && isset( $files['profile_pic']['name'] ) && ! empty( $files['profile_pic'] ) && trim( sanitize_text_field( $files['profile_pic']['name'] ) ) !== '' ) {

				$validateProfile = wp_check_filetype_and_ext($files['profile_pic']['tmp_name'], $files['profile_pic']['name'], get_allowed_mime_types());
				

				if ( ! wp_match_mime_types( 'image', $validateProfile['type'] ) ) {
					
					wc_add_notice( esc_html__('Sorry. Only image files are accepted.', 'wicket-acc'), 'error' );

				} else {


					$picture_id = $this->wc_cus_upload_picture( sanitize_meta( '', $files['profile_pic'], '' ) );

					$this->wc_cus_save_profile_pic( $picture_id, $user_id );

					wc_add_notice( get_option( 'wicket_acc_set_pro_pic_success_message' ), 'success' );

					$one_action = true;
				}
			}

			if ( isset( $_GET['action'] ) && 'delete_profile' === $_GET['action'] && ! $one_action ) {
			
				$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : 0;

				if ( ! wp_verify_nonce( $nonce, '_wpnonce'  ) ) {

					wp_die( esc_html__( ' Nonce not verified. ', 'wicket-acc' ) );

				}

				$picture_id = get_user_meta( $user_id, 'profile_pic', true );

				delete_user_meta( $user_id, 'profile_pic' );

				wc_add_notice( get_option( 'wicket_acc_set_pro_pic_remove_message' ), 'success' );

			}

			$picture_id = get_user_meta( $user_id, 'profile_pic', true );

			if ( trim( $picture_id ) === '' ) {

				$delete_link         = '';
				$upload_update_image = 'Upload Profile Picture';

			} else {
				$hre =  get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) . '?action=delete_profile';

				$delete_link         = '<a class="wicket_acc_remove_button" title="Remove Profile Picture" href="' . wp_nonce_url($hre, '_wpnonce') . '"><i class="fa fa-window-close "></i></a>';
				$upload_update_image = 'Update Profile Picture';

			}
			?>

			<div class="wicket_acc_profile_Pic_form">
				<form class="woocommerce-EditAccountForm wicket_acc_profile_Pic_form edit-account" enctype="multipart/form-data" action="" method="POST">
					<div class="wicket_acc_profile_Pic">
						<?php echo wp_kses_post( get_avatar( $user_id ) ); ?>
			
						<?php echo wp_kses_post( ( $delete_link ) ); ?>
						<i class="fa fa-camera wicket_acc_upload_button" title="<?php echo esc_attr( $upload_update_image ); ?>"></i>
						<input name="profile_pic" id="wicket_acc_file_upload" class="wicket_acc_file_upload" type="file" accept="image/*"/>
						<div class="wicket_acc_pp_opt_close"><span class="close">&times;</span></div>
					</div>
					<button type="submit" name="wicket_acc_upload_image" value="Upload_image" class="wicket_acc_upload_image"></button>
				</form>
			</div>

			<?php

		}

		/**
		 * Save profile picture.
		 *
		 * @param int $picture_id Id of picture.
		 * @param int $user_id Id of user.
		 */
		public function wc_cus_save_profile_pic( $picture_id, $user_id ) {

			update_user_meta( $user_id, 'profile_pic', $picture_id );

		}

		/**
		 * Upload picture.
		 *
		 * @param string $foto Image.
		 */
		public function wc_cus_upload_picture( $foto ) {

			$wordpress_upload_dir = wp_upload_dir();

			$i              = 1;
			$profilepicture = $foto;

			$new_file_path = $wordpress_upload_dir['path'] . '/' . $profilepicture['name'];

			$check = getimagesize( $profilepicture['tmp_name'] );

			$new_file_mime = $check['mime'];

			$log = new WC_Logger();

			if ( empty( $profilepicture ) ) {

				$log->add( 'custom_profile_picture', 'Please select a file.' );

			}

			if ( $profilepicture['error'] ) {

				$log->add( 'custom_profile_picture', $profilepicture['error'] );

			}

			if ( $profilepicture['size'] > wp_max_upload_size() ) {

				$log->add( 'custom_profile_picture', 'File is too large. Please select a file size smaller than 5MB' );

			}

			if (wp_check_filetype_and_ext($new_file_path, $profilepicture['name'], get_allowed_mime_types())) {

				$log->add( 'custom_profile_picture', 'Sorry. This file type is not accepted.' );

			}

			while ( file_exists( $new_file_path ) ) {

				$i++;

				$new_file_path = $wordpress_upload_dir['path'] . '/' . $i . '_' . $profilepicture['name'];

			}

			if ( move_uploaded_file( $profilepicture['tmp_name'], $new_file_path ) ) {

				$upload_id = wp_insert_attachment(
					array(

						'guid'           => $new_file_path,

						'post_mime_type' => $new_file_mime,

						'post_title'     => preg_replace( '/\.[^.]+$/', '', $profilepicture['name'] ),

						'post_content'   => '',

						'post_status'    => 'inherit',

					),
					$new_file_path
				);

				require_once str_replace( get_bloginfo( 'url' ) . '/', ABSPATH, get_admin_url() ) . 'includes/image.php';

				// Generate and save the attachment metas into the database.

				wp_update_attachment_metadata( $upload_id, wp_generate_attachment_metadata( $upload_id, $new_file_path ) );

				return $upload_id;

			}

		}

		/**
		 * Upload picture.
		 *
		 * @param int $avatar Image.
		 * @param int $id_or_email Id of user.
		 * @param int $size Image size.
		 * @param int $default Id of user.
		 * @param int $alt Alt Text.
		 */
		public function wicket_acc_cus_change_avatar( $avatar, $id_or_email, $size, $default, $alt ) {

			$user = false;

			if ( is_numeric( $id_or_email ) ) {

				$id = (int) $id_or_email;

				$user = get_user_by( 'id', $id );

			} elseif ( is_object( $id_or_email ) ) {

				if ( ! empty( $id_or_email->user_id ) ) {

					$id = (int) $id_or_email->user_id;

					$user = get_user_by( 'id', $id );

				}
			} else {

				$user = get_user_by( 'email', $id_or_email );

			}

			if ( $user && is_object( $user ) ) {

				$picture_id = get_user_meta( $user->data->ID, 'profile_pic' );

				if ( ! empty( $picture_id ) ) {

					$avatar = wp_get_attachment_url( $picture_id[0] );

					$avatar = "<img alt='{$alt}' src='{$avatar}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";

				}
			}

			return $avatar;

		}

		public function wicket_acc_override_templates() {

			add_filter( 'woocommerce_locate_template', 'intercept_wc_template', 10, 3 );
			/**
			 * Filter the woocommerce template path to use this plugin instead of the one in WooCommerce or theme.
			 *
			 * @param string $template      Default template file path.
			 * @param string $template_name Template file slug.
			 * @param string $template_path Template file name.
			 *
			 * @return string The new Template file path.
			 */
			function intercept_wc_template( $template, $template_name, $template_path ) {

				if ( 'dashboard.php' === basename( $template ) ) {
					$template = trailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/dashboard.php';
				}

				if ( 'my-account.php' === basename( $template ) ) {
					$template = trailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/my-account.php';
				}

				return $template;

			}

		}

	}

	new Wicket_Acc_Front();
}
