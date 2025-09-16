<?php

namespace WicketAcc\Blocks;

use Carbon_Fields\Block;
use Carbon_Fields\Field;

// No direct access
defined('ABSPATH') || exit;

/**
 * Block: ACC Change Password.
 */
class ChangePassword
{
    /**
     * Store form errors for the current request.
     *
     * @var array
     */
    private static $form_errors = [];

    public function __construct()
    {
        // Register the style immediately
        wp_register_style(
            'wicket-acc-password-block',
            WICKET_ACC_URL . 'assets/css/blocks/change-password.css',
            [],
            '1.0.0'
        );

        add_action('carbon_fields_register_fields', [$this, 'registerBlock']);
        add_action('init', [$this, 'processWicketPasswordForm']);
    }

    public function registerBlock()
    {
        Block::make('wicket-acc/change-password', __('ACC Password Block (Carbon)'))
          ->add_fields([
              Field::make('text', 'form_title', __('Form Title'))
                ->set_default_value(__('Change Password', 'wicket-acc')),
              Field::make('textarea', 'form_instructions', __('Form Instructions'))
                ->set_default_value(__('Enter your current password and choose a new one.', 'wicket-acc'))
                ->set_rows(3),
          ])
          ->set_description(__('A Wicket block for Password'))
          ->set_category('wicket-account-centre', __('Wicket Account Centre'))
          ->set_keywords([__('account-centre'), __('password'), __('wicket')])
          ->set_mode('preview')
          ->set_style('wicket-acc-password-block')
          ->set_render_callback([$this, 'renderBlock']);
    }

    public function renderBlock($fields, $attributes, $inner_blocks)
    {
        $formErrors = self::$form_errors;
        $hasErrors = !empty($formErrors);
        $formTitle = $fields['form_title'] ?? __('Change Password', 'wicket-acc');
        $formInstructions = $fields['form_instructions'] ?? __('Enter your current password and choose a new one.', 'wicket-acc');

        $attrs = get_block_wrapper_attributes([
            'class' => 'wicket wicket-acc-block wicket-acc-block-password flex flex-col gap-8',
            'data-theme' => WACC()->Settings()->getWicketCssTheme(),
        ]);

        // Helper function to check for field errors
        $hasFieldError = fn (string $field): bool => $hasErrors && !empty(array_filter($formErrors, fn ($error) => $error->meta->field === $field));

        $currentPasswordError = $hasFieldError('user.current_password');
        $passwordError = $hasFieldError('user.password');
        $passwordConfirmError = $hasFieldError('user.password_confirmation');

        ob_start();
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
            <?php foreach ($formErrors as $index => $error): ?>
              <?php
                                                                                    $errorMap = [
                                                                                        'user.current_password' => ['Current Password', '#current_password'],
                                                                                        'user.password' => ['New Password', '#password'],
                                                                                        'user.password_confirmation' => ['Confirm Password', '#password_confirmation'],
                                                                                    ];

                if (isset($errorMap[$error->meta->field])):
                    [$prefix, $anchor] = $errorMap[$error->meta->field];
                    ?>
                <li>
                  <a href="<?= $anchor ?>">
                    <strong>Error: <?= $index + 1 ?></strong>
                    <?= esc_html($prefix . ' ' . __($error->title)) ?>
                  </a>
                </li>
              <?php endif; ?>
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
            <label class="form__label" for="<?= $field['id'] ?>">
              <?= $field['label'] ?>
              <span class="required">*</span>
            </label>

            <?php if (isset($field['helpText'])): ?>
              <p class='small-text'>
                <?= $field['helpText'] ?>
              </p>
            <?php endif; ?>

            <input class="form__input <?= $errorClass ?>" required
              type="password"
              id="<?= $field['id'] ?>"
              name="<?= $field['id'] ?>"
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
    <?php
    echo ob_get_clean();
    }

    public function processWicketPasswordForm()
    {
        if (!isset($_POST['wicket_update_password'])) {
            return;
        }

        $errors = [];
        $client = WACC()->Mdp()->initClient();
        $person = wicket_current_person();

        $current_password = sanitize_text_field($_POST['current_password'] ?? '');
        $password = sanitize_text_field($_POST['password'] ?? '');
        $password_confirmation = sanitize_text_field($_POST['password_confirmation'] ?? '');

        // Validate current password
        if ($current_password == '') {
            $current_pass_blank = [];
            $current_pass_blank['meta'] = (object) ['field' => 'user.current_password'];
            $current_pass_blank['title'] = __("can't be blank");
            $errors[] = (object) $current_pass_blank;
        }

        // Validate new password
        if ($password == '') {
            $pass_blank = [];
            $pass_blank['meta'] = (object) ['field' => 'user.password'];
            $pass_blank['title'] = __("can't be blank");
            $errors[] = (object) $pass_blank;
        }

        // Validate confirmation
        if ($password_confirmation == '') {
            $confirm_pass_blank = [];
            $confirm_pass_blank['meta'] = (object) ['field' => 'user.password_confirmation'];
            $confirm_pass_blank['title'] = __("can't be blank");
            $errors[] = (object) $confirm_pass_blank;
        }

        // Match confirmation
        if ($password_confirmation != $password) {
            $pass_blank = [];
            $pass_blank['meta'] = (object) ['field' => 'user.password'];
            $pass_blank['title'] = __(' - Passwords do not match');
            $errors[] = (object) $pass_blank;
        }

        // Only update via API if no errors
        if (empty($errors)) {
            $update_user = new \Wicket\Entities\People([
                'user' => [
                    'current_password'      => $current_password,
                    'password'              => $password,
                    'password_confirmation' => $password_confirmation,
                ],
            ]);
            $update_user->id = $person->id;
            $update_user->type = $person->type;

            try {
                $client->people->update($update_user);

                // On success, redirect to current page with success param
                wp_safe_redirect(strtok($_SERVER['REQUEST_URI'], '?') . '?success');
                exit;
            } catch (\Exception $e) {
                // On API error, store errors for display
                self::$form_errors = json_decode($e->getResponse()->getBody())->errors;
            }
        } else {
            // On validation error, store for display
            self::$form_errors = $errors;
        }
    }
}
