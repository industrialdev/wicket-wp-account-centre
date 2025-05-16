<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/*
 * Template for displaying a single Maple touchpoint card
 *
 * Available data:
 *
 * $args - Passed data
 **/

$args = wp_parse_args($args);
$course_name = $args['attributes']['data']['course_name'];
$course_id = $args['attributes']['data']['course_id'];
$course_url = $args['attributes']['data']['course_url'];

if (!$course_name) {
    return;
}

?>

<div class="maple-card">
	<?php if ($course_url) : ?>
    <a href="<?php echo $course_url; ?>" class="maple-card__link" target="_blank">
    <?php endif; ?>
		<h3 class="maple-card__title <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-lg font-bold mb-2' ?>">
			<?php echo $course_name ?>
		</h3>
    <?php if ($course_url) : ?>
    </a>
	<?php endif; ?>

	<?php if ($course_id) : ?>
		<div class="maple-card__course-id <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-sm' ?>">
			<strong><?php _e('ID:', 'wicket-acc'); ?></strong> <?php echo $course_id; ?>
		</div>
	<?php endif; ?>

</div>