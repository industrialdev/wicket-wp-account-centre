<?php

/**
 * Block template: ACC Change Password.
 *
 * Rendered by HyperBlocks in isolated scope. Field values are extracted as
 * variables ($form_title, $form_instructions). Form-submission state (errors
 * from the current POST) is accessed via the block class's static getter,
 * since isolated scope cannot see self::$form_errors.
 *
 * @var string $form_title       Block attribute: form heading.
 * @var string $form_instructions Block attribute: intro text under the heading.
 */

// No direct access
defined('ABSPATH') || exit;

use WicketAcc\Blocks\ChangePassword;

$formErrors = ChangePassword::getFormErrors();
$hasErrors = !empty($formErrors);

// Field-level attribute defaults: HyperBlocks auto-applies setDefault when
// an attribute is null (unsaved block). The empty-string re-check below
// handles the case where an editor explicitly cleared a field and saved —
// we coerce it back to the default. Change this if blank titles should be
// respected.
$formTitle = $form_title ?? __('Change Password', 'wicket-acc');
if ($formTitle === '') {
    $formTitle = __('Change Password', 'wicket-acc');
}
$formInstructions = $form_instructions ?? __('Enter your current password and choose a new one.', 'wicket-acc');
if ($formInstructions === '') {
    $formInstructions = __('Enter your current password and choose a new one.', 'wicket-acc');
}

$attrs = get_block_wrapper_attributes([
    'class' => 'wicket wicket-acc-block wicket-acc-block-password flex flex-col gap-8',
    'data-theme' => WACC()->Settings()->getWicketCssTheme(),
]);

// Helper: does the given API field have an error this request?
$hasFieldError = static function (string $field) use ($hasErrors, $formErrors): bool {
    if (!$hasErrors) {
        return false;
    }
    foreach ($formErrors as $error) {
        if (isset($error->meta->field) && $error->meta->field === $field) {
            return true;
        }
    }

    return false;
};

$currentPasswordError = $hasFieldError('user.current_password');
$passwordError = $hasFieldError('user.password');
$passwordConfirmError = $hasFieldError('user.password_confirmation');
?>

<div <?= $attrs ?>>
    <?php if ($formTitle): ?>
        <h3><?= esc_html($formTitle) ?></h3>
    <?php endif; ?>

    <?php if ($formInstructions): ?>
        <p class="form-instructions"><?= esc_html($formInstructions) ?></p>
    <?php endif; ?>

    <?php if ($hasErrors): ?>
        <div class='alert alert-danger' role="alert">
            <strong>
                <?= sprintf(
                    _n(
                        'The form could not be submitted because 1 error was found',
                        'The form could not be submitted because %s errors were found',
                        count($formErrors),
                        'wicket-acc'
                    ),
                    number_format_i18n(count($formErrors))
                ) ?>
            </strong>
            <ul>
                <?php foreach ($formErrors as $index => $error):
                    $errorMap = [
                        'user.current_password' => [__('Current Password', 'wicket-acc'), '#current_password'],
                        'user.password' => [__('New Password', 'wicket-acc'), '#password'],
                        'user.password_confirmation' => [__('Confirm Password', 'wicket-acc'), '#password_confirmation'],
                    ];
                    $field = $error->meta->field ?? '';
                    if (!isset($errorMap[$field])) {
                        continue;
                    }
                    [$prefix, $anchor] = $errorMap[$field];
                    ?>
                    <li>
                        <a href="<?= esc_attr($anchor) ?>">
                            <strong><?= sprintf(__('Error: %d', 'wicket-acc'), $index + 1) ?></strong>
                            <?= esc_html($prefix . ' ' . ($error->title ?? '')) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php elseif (isset($_GET['success'])): ?>
        <div class='alert alert-success' role="alert">
            <strong><?php _e('Your password has been updated.', 'wicket-acc'); ?></strong>
        </div>
    <?php endif; ?>

    <form class='manage_password_form' method="post">
        <?php
        $fields = [
            [
                'id' => 'current_password',
                'label' => __('Current password', 'wicket-acc'),
                'hasError' => $currentPasswordError,
            ],
            [
                'id' => 'password',
                'label' => __('New password', 'wicket-acc'),
                'hasError' => $passwordError,
                'helpText' => __('Minimum of 8 characters', 'wicket-acc'),
            ],
            [
                'id' => 'password_confirmation',
                'label' => __('Confirm new password', 'wicket-acc'),
                'hasError' => $passwordConfirmError,
            ],
        ];

foreach ($fields as $field):
    $errorClass = ($hasErrors || $field['hasError']) ? 'error_input' : '';
    ?>
            <div class="form__group">
                <label class="form__label" for="<?= esc_attr($field['id']) ?>">
                    <?= esc_html($field['label']) ?>
                    <span class="required">*</span>
                </label>

                <?php if (isset($field['helpText'])): ?>
                    <p class='small-text'>
                        <?= esc_html($field['helpText']) ?>
                    </p>
                <?php endif; ?>

                <input class="form__input <?= esc_attr($errorClass) ?>" required
                    type="password"
                    id="<?= esc_attr($field['id']) ?>"
                    name="<?= esc_attr($field['id']) ?>"
                    value="">
            </div>
        <?php endforeach; ?>

        <input type="hidden" name="wicket_update_password" value="wicket_update_password--1" />

        <?php
        // Render the submit button using a component
        get_component('button', [
            'variant' => 'primary',
            'type'    => 'submit',
            'classes' => ['wicket_update_password--1'],
            'label'   => __('Change password', 'wicket-acc'),
        ]);
?>
    </form>
</div>
