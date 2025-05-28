<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * tp - Touchpoint data
 */
$tp = $args['tp'];

$action = $tp['attributes']['action'];
$action_code = $tp['attributes']['code'];
$details = $tp['attributes']['details'];

// Completed a course
$course_name = $tp['attributes']['data']['course_name'];
$course_id = $tp['attributes']['data']['course_id'];
$course_start_date = $tp['attributes']['data']['start_date'];
$course_end_date = $tp['attributes']['data']['end_date'];
$enrolled_start_date = $tp['attributes']['data']['start_date']; 
$enrolled_end_date = $tp['attributes']['data']['end_date']; 
$grade_achieved = $tp['attributes']['data']['final_grade']['gradeformatted'];
$grade_percentage = $tp['attributes']['data']['final_grade']['percentageformatted'];
?>

<div class="event-card <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'my-0 p-6 border border-gray-200 gap-6 rounded-md shadow-md flex-row' ?>"
    data-uuid="<?php echo $tp['id']; ?>">

    <h2 class='text-lg'><?php echo $action ?></h2>

    <?php if($action_code == 'completed_a_course'): ?>
        <div class="flex-auto md:w-auto event-content-wrap space-y-4">
            <p><?php echo $course_name ?></p>
            
            <p class="event-date <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-sm leading-relaxed' ?>">
                <strong><?php _e('Course ID:', 'wicket-acc'); ?></strong> <?php echo $course_id; ?>
            </p>
            
            <p class="event-date <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-sm leading-relaxed' ?>">
                <strong><?php _e('Start Date:', 'wicket-acc'); ?></strong> <?php echo $course_start_date; ?>
            </p>
            
            <?php if($course_end_date): ?>
            <p class="event-date <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-sm leading-relaxed' ?>">
                <strong><?php _e('End Date:', 'wicket-acc'); ?></strong> <?php echo $course_end_date; ?>
            </p>
            <?php endif; ?>
            
            <?php if($grade_achieved): ?>
            <p class="event-date <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-sm leading-relaxed' ?>">
                <strong><?php _e('Grade Achieved:', 'wicket-acc'); ?></strong> <?php echo $grade_achieved; ?>
            </p>
            <?php endif; ?>
            
            <?php if($grade_percentage): ?>
            <p class="event-date <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-sm leading-relaxed' ?>">
                <strong><?php _e('Grade Percentage:', 'wicket-acc'); ?></strong> <?php echo $grade_percentage; ?>
            </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if($action_code == 'enrolled_in_a_course'): ?>
        <div class="flex-auto md:w-auto event-content-wrap space-y-4">
            <p><?php echo $course_name ?></p>
            
            <p class="event-date <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-sm leading-relaxed' ?>">
                <strong><?php _e('Course ID:', 'wicket-acc'); ?></strong> <?php echo $course_id; ?>
            </p>
            
            <p class="event-date <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-sm leading-relaxed' ?>">
                <strong><?php _e('Start Date:', 'wicket-acc'); ?></strong> <?php echo $enrolled_start_date; ?>
            </p>
            
            <?php if($enrolled_end_date): ?>
            <p class="event-date <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-sm leading-relaxed' ?>">
                <strong><?php _e('End Date:', 'wicket-acc'); ?></strong> <?php echo $enrolled_end_date; ?>
            </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if($action_code == 'created_account'): ?>
        <div class="flex-auto md:w-auto event-content-wrap space-y-4">
            <p><?php echo $course_name ?></p>
            
            <p class="event-date <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-sm leading-relaxed' ?>">
                <?php _e($details, 'wicket-acc'); ?>
            </p>
        </div>
    <?php endif; ?>

</div>
