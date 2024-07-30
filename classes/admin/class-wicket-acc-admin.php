<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Admin file for Wicket Account Centre
 *
 * @package  wicket-account-centre
 * @version  1.0.0
 */

/**
 * Admin class of module
 */
class WPAdmin extends WicketAcc
{
	/**
	 * Constructor of class
	 */
	public function __construct()
	{
		// Enqueue Admin CSS JS
		add_action('admin_enqueue_scripts', array($this, 'wicket_acc_admin_enqueue_scripts'));
		// add menus & sub menu
		add_action('admin_menu', array($this, 'wicket_acc_custom_menu_admin'));
		// create meta box
		add_action('add_meta_boxes', array($this, 'wicket_acc_meta_box'));
		// Save MetaBox Values
		add_action('save_post_wicket_acc', array($this, 'save_metabox_values'));
		// Settings Fields
		//add_action('admin_init', array($this, 'wicket_acc_settings_fields'));
		/* add admin columns */
		add_filter('manage_edit-wicket_acc_columns', array($this, 'wicket_acc_cpt_edit_columns'));
		add_action('admin_head', array($this, 'wicket_acc_cpt_columns_width'));
		add_action('manage_wicket_acc_posts_custom_column', array($this, 'wicket_acc_cpt_manage_columns'), 10, 2);
	}

	/**
	 * Enqueue scripts for admin
	 */
	public function wicket_acc_admin_enqueue_scripts()
	{

		// Upload Font-Awesome 4
		wp_enqueue_script('wicket_acc_admin', plugins_url('../assets/js/wicket_acc_admin.js', __FILE__), array('jquery'), '1.0', false);

		wp_enqueue_style('wicket_acc_admin', plugins_url('../assets/css/wicket_acc_admin.css', __FILE__), array(), '1.0');

		// Enqueue Select2 JS CSS
		$screen = get_current_screen();
		if ($screen->id == 'wicket_acc_page_customize-my-account-page-layout') {
			wp_enqueue_style('select2', plugins_url('../assets/css/select2.css', __FILE__), array(), '1.0');
			wp_enqueue_script('select2', plugins_url('../assets/js/select2.js', __FILE__), false, '1.0', array('jquery'), '1.0', false);
		}

		// Enqueue WP_MEDIA
		wp_enqueue_media();

		wp_enqueue_style('Font_Awesome', 'https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css', false, '1.0');

		wp_enqueue_style('jquery_ui', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css', array(), '1.12.1');

		wp_enqueue_script('jquery_ui', 'https://code.jquery.com/ui/1.12.1/jquery-ui.js', array('jquery'), '1.12.1', false);
	}

	/**
	 * Add menu in admin bar
	 */
	public function wicket_acc_custom_menu_admin()
	{

		add_submenu_page(
			'edit.php?post_type=wicket_acc', // parent_slug
			esc_html__('Account Centre Page Editor', 'wicket-acc'), // page title
			esc_html__('Settings', 'wicket-acc'), // menu title
			'manage_options', // capability
			'customize-my-account-page-layout', // slug
			array($this, 'wicket_acc_settings_callback'), // callback function
			10 // position
		);
	}

	/**
	 * Add meta box in custom post type
	 */
	public function wicket_acc_meta_box()
	{

		add_meta_box(
			'wicket_acc_metabox', // id.
			esc_html__('Account Centre Settings', 'wicket-acc'), // title which will be shown at top of metabox.
			array($this, 'wicket_acc_metabox_cb'), // callback function name.
			'wicket_acc', // The screen or screens on which to show the box (such as a post type, 'link', or 'comment').
			'normal', // The context within the screen where the boxes should display.
			'high' // The priority within the context where the boxes should show ('high', 'low').
		);
	}

	/**
	 * Add fields in custom meta box
	 */
	public function wicket_acc_metabox_cb()
	{

		global $post;

		wp_nonce_field('wicket_acc_fields_nonce', 'wicket_acc_fields_nonce');

		$wicket_acc_slug = get_post_meta(intval($post->ID), 'wicket_acc_slug_fld', true);

		$wicket_acc_user_role = get_post_meta(intval($post->ID), 'wicket_acc_user_role', true);

		$wicket_acc_eptype = get_post_meta(intval($post->ID), 'wicket_acc_endpType_fld', true);

		$wicket_acc_menu_title = get_post_meta(intval($post->ID), 'wicket_acc_menu_title', true);

		if (empty($wicket_acc_slug)) {
			$wicket_acc_slug = 'wicket_slug_' . $post->ID;
		}

		if (empty($wicket_acc_grp_mem)) {
			$wicket_acc_grp_mem = array();
		}

?>
		<div class="new-endpoint-form">

			<table class="addify-table-option">

				<tr class="addify-option-field">

					<th>

						<div class="option-head">

							<h3>

								<?php esc_html_e('Menu Title', 'wicket-acc'); ?>

							</h3>

						</div>

					</th>

					<td>

						<input type="text" class="wicket_acc_input width-60" name="wicket_acc_menu_title" id="wicket_acc_slug_fld" value="<?php echo esc_attr($wicket_acc_menu_title) ? esc_attr($wicket_acc_menu_title) : ''; ?>" />
						<br>
						<p><?php esc_html_e('Assign a title to show in menu of my-account navigation bar. If the menu title is empty the endpoint title will be used as menu title.', 'wicket-acc'); ?></p>
					</td>

				</tr>

				<tr class="addify-option-field">

					<th>

						<div class="option-head">

							<h3>

								<?php esc_html_e('Menu Slug', 'wicket-acc'); ?>

							</h3>

						</div>

					</th>

					<td>

						<input type="text" class="wicket_acc_input width-60" name="wicket_acc_slug_fld" id="wicket_acc_slug_fld" value="<?php echo esc_attr($wicket_acc_slug) ? esc_attr($wicket_acc_slug) : ''; ?>" />
						<br>
						<p><?php esc_html_e('Assign a unique slug to each endpoint. For Core Endpoints, the slug must match exactly.', 'wicket-acc'); ?></p>
					</td>

				</tr>

				<tr class="addify-option-field user_role_fld">

					<th>

						<div class="option-head">

							<h3>

								<?php esc_html_e('User Role', 'wicket-acc'); ?>

							</h3>

						</div>

					</th>

					<td>

						<?php

						if (empty($wicket_acc_user_role)) {

							$wicket_acc_user_role = array();
						}

						global $wp_roles;

						foreach ($wp_roles->get_names() as $key => $name) {
						?>
							<input type="checkbox" name="wicket_acc_user_role[]" value="<?php echo esc_attr($key); ?>" <?php
																																																					if (in_array($key, $wicket_acc_user_role, true)) {
																																																						echo 'checked';
																																																					}
																																																					?>>

							<?php
							echo esc_attr($name);
							?>
						<?php
						}
						?>
						<input type="checkbox" name="wicket_acc_user_role[]" value="guest" <?php
																																								if (in_array('guest', $wicket_acc_user_role, true)) {
																																									echo 'checked';
																																								}
																																								?>>
						<?php
						echo esc_html__('Guest', 'wicket-acc');
						?>
						<br>
						<p class="description"><?php esc_html_e('Leave empty to display for all user roles.', 'wicket-acc'); ?></p>

					</td>

				</tr>

			</table>

		</div>

	<?php

	}

	/**
	 * Add admin settings
	 */
	public function wicket_acc_settings_callback()
	{

		global $active_tab;

		if (isset($_GET['tab'])) {

			$active_tab = sanitize_text_field(wp_unslash($_GET['tab']));
		} else {

			$active_tab = 'endpoints_settings';
		}

	?>

		<div class="wrap">

			<div id="icon-tools" class="icon32"></div>

			<h2> <?php echo esc_html__('Account Centre Settings', 'wicket-acc'); ?></h2>

			<?php settings_errors(); ?>

			<h2 class="nav-tab-wrapper">

				<a href="?post_type=wicket_acc&page=customize-my-account-page-layout&tab=endpoints_settings" class="nav-tab  <?php echo esc_attr($active_tab) === 'endpoints_settings' ? ' nav-tab-active' : ''; ?>"> <?php esc_html_e('Account Centre Settings', 'wicket-acc'); ?> </a>

				<a href="?post_type=wicket_acc&page=customize-my-account-page-layout&tab=profile_img_settings" class="nav-tab  <?php echo esc_attr($active_tab) === 'profile_img_settings' ? ' nav-tab-active' : ''; ?>"> <?php esc_html_e('Profile Picture', 'wicket-acc'); ?> </a>

			</h2>

			<form method="post" action="options.php" id="save_options_form">

				<?php

				if ('endpoints_settings' === $active_tab) {

					settings_fields('wicket_acc_settings');

					do_settings_sections('wicket_acc_settings_page');
				} elseif ('profile_img_settings' === $active_tab) {

					settings_fields('wicket_acc_pro_pic_settings');

					do_settings_sections('wicket_acc_pro_pic_settings_page');
				}

				submit_button(esc_html__('Save Settings', 'wicket-acc'), 'primary', 'wicket_acc_save_settings_btn');

				wp_nonce_field('wicket_acc_fields_nonce', 'wicket_acc_fields_nonce');

				?>
			</form>

		</div>

<?php
	}

	/**
	 * Add admin settings fields
	 */
	public function wicket_acc_settings_fields()
	{
		//include_once WICKET_ACC_PATH . 'admin/settings/general-settings.php';
	}

	/**
	 * Add admin settings fields
	 *
	 * @param int $post_id Post id.
	 */
	public function save_metabox_values($post_id)
	{
		// return if we're doing an auto save
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (get_post_status($post_id) === 'auto-draft' || get_post_status($post_id) === 'trash') {
			return;
		}

		// if our nonce isn't there, or we can't verify it, return
		if (!isset($_POST['wicket_acc_fields_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['wicket_acc_fields_nonce']), 'wicket_acc_fields_nonce')) {
			die('Failed Security Check!');
		}

		// if our current user can't edit this post, return
		if (!current_user_can('edit_posts')) {
			return;
		}

		update_post_meta($post_id, 'wicket_acc_endpType_fld', sanitize_meta('', wp_unslash('cendpoint'), ''));


		if (isset($_POST['wicket_acc_slug_fld'])) {

			update_post_meta($post_id, 'wicket_acc_slug_fld', sanitize_meta('', wp_unslash($_POST['wicket_acc_slug_fld']), ''));
		}

		if (isset($_POST['wicket_acc_user_role'])) {

			update_post_meta($post_id, 'wicket_acc_user_role', sanitize_meta('', wp_unslash($_POST['wicket_acc_user_role']), ''));
		} else {

			update_post_meta($post_id, 'wicket_acc_user_role', array());
		}

		if (isset($_POST['wicket_acc_menu_title'])) {
			update_post_meta($post_id, 'wicket_acc_menu_title', sanitize_meta('', wp_unslash($_POST['wicket_acc_menu_title']), ''));
		}

		if (isset($_POST['wicket_acc_group_child'])) {

			$child_array = sanitize_meta('', wp_unslash($_POST['wicket_acc_group_child']), '');
			if (is_array($child_array)) {
				update_post_meta($post_id, 'wicket_acc_group_child', wp_json_encode($child_array));
			} else {
				update_post_meta($post_id, 'wicket_acc_group_child', wp_json_encode(array()));
			}
		} else {
			update_post_meta($post_id, 'wicket_acc_group_child', wp_json_encode(array()));
		}

		if (isset($_POST['wicket_acc_group_default_filed'])) {

			$child_array = sanitize_meta('', wp_unslash($_POST['wicket_acc_group_default_filed']), '');
			if (is_array($child_array)) {
				update_post_meta($post_id, 'wicket_acc_group_default_filed', wp_json_encode($child_array));
			} else {
				update_post_meta($post_id, 'wicket_acc_group_default_filed', wp_json_encode(array()));
			}
		} else {
			update_post_meta($post_id, 'wicket_acc_group_default_filed', wp_json_encode(array()));
		}
	}

	/**
	 * Add admin columns
	 */
	public function wicket_acc_cpt_edit_columns($columns)
	{

		$columns = array(
			'cb' => '<input type="checkbox" />',
			'title' => __('Title', 'wicket-acc'),
			'ep_type' => __('Type', 'wicket-acc'),
			'date' => __('Date', 'wicket-acc'),
		);
		return $columns;
	}

	/**
	 * Set admin columns width
	 */
	public function wicket_acc_cpt_columns_width()
	{

		echo '<style type="text/css">';
		echo '.column-ep_type { text-align: left; width:220px !important; overflow:hidden; font-size: 14px !important; }';
		echo '</style>';
	}

	/**
	 * Manage admin columns
	 */
	public function wicket_acc_cpt_manage_columns($column, $post_id)
	{

		global $post;
		$wicket_acc_eptype = get_post_meta(intval($post->ID), 'wicket_acc_endpType_fld', true);

		switch ($column) {
			case 'ep_type':

				if ('cendpoint' === esc_attr($wicket_acc_eptype)) {
					echo esc_html_e('Core Endpoint', 'wicket-acc');
				}

				break;

			default:
				break;
		}
	}
}

if (function_exists('acf_get_field')) {
	new WPAdmin();
}
