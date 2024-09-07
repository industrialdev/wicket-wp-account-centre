<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Admin Menu Editor
 * Add ACC pages to the WP menu editor
 */
class AdminMenuEditor extends WicketAcc
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		add_action('admin_head-nav-menus.php', [$this, 'add_nav_menu_meta_boxes']);
	}

	/**
	 * Add custom nav menu meta box
	 */
	public function add_nav_menu_meta_boxes()
	{
		add_meta_box(
			'wicket_acc_nav_link',
			__('Account Centre', 'wicket-acc'),
			[$this, 'nav_menu_meta_box'],
			'nav-menus',
			'side',
			'default'
		);
	}

	/**
	 * Display custom nav menu meta box
	 */
	public function nav_menu_meta_box()
	{
		$posts = get_posts([
			'post_type'      => 'my-account',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		]);

?>
		<div id="wicket-acc-menu-items" class="posttypediv">
			<div id="tabs-panel-wicket-acc" class="tabs-panel tabs-panel-active">
				<ul id="wicket-acc-checklist" class="categorychecklist form-no-clear">
					<?php
					$i = -1;
					foreach ($posts as $post) {
					?>
						<li>
							<label class="menu-item-title">
								<input type="checkbox" class="menu-item-checkbox" name="menu-item[<?php echo esc_attr($i); ?>][menu-item-object-id]" value="<?php echo esc_attr($post->ID); ?>" /> <?php echo esc_html($post->post_title); ?>
							</label>
							<input type="hidden" class="menu-item-type" name="menu-item[<?php echo esc_attr($i); ?>][menu-item-type]" value="post_type" />
							<input type="hidden" class="menu-item-object" name="menu-item[<?php echo esc_attr($i); ?>][menu-item-object]" value="my-account" />
							<input type="hidden" class="menu-item-title" name="menu-item[<?php echo esc_attr($i); ?>][menu-item-title]" value="<?php echo esc_attr($post->post_title); ?>" />
						</li>
					<?php
						$i--;
					}
					?>
				</ul>
			</div>
			<p class="button-controls">
				<span class="add-to-menu">
					<input type="submit" class="button-secondary submit-add-to-menu right" value="<?php esc_attr_e('Add to Menu', 'wicket-acc'); ?>" name="add-post-type-menu-item" id="submit-wicket-acc-menu-items" />
					<span class="spinner"></span>
				</span>
			</p>
		</div>
<?php
	}
}
