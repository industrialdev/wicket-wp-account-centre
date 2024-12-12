<?php

namespace WicketAcc\Blocks\Password;

use Exception;
use WicketAcc\Blocks;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Password Block.
 **/
class init extends Blocks
{
    /**
     * Constructor.
     */
    public function __construct(
        protected array $block = [],
        protected bool $is_preview = false,
    ) {
        $this->block = $block;
        $this->is_preview = $is_preview;

        add_action('init', [$this, 'process_wicket_password_form']);

        // Display the block
        $this->init_block();
    }

    public function process_wicket_password_form()
    {
        $errors = [];
        if (isset($_POST['wicket_update_password'])) {
            if (!session_id()) {
                session_start();
            }

            $client = wicket_api_client_current_user();
            $person = wicket_current_person();

            /**------------------------------------------------------------------
             * Update Password
                ------------------------------------------------------------------*/
            $current_password = $_POST['current_password'] ?? '';
            $password = $_POST['password'] ?? '';
            $password_confirmation = $_POST['password_confirmation'] ?? '';

            if ($current_password == '') {
                $current_pass_blank = [];
                $current_pass_blank['meta'] = (object) ['field' => 'user.current_password'];
                $current_pass_blank['title'] = __("can't be blank");
                $errors[] = (object) $current_pass_blank;
            }
            if ($password == '') {
                $pass_blank = [];
                $pass_blank['meta'] = (object) ['field' => 'user.password'];
                $pass_blank['title'] = __("can't be blank");
                $errors[] = (object) $pass_blank;
            }
            if ($password_confirmation == '') {
                $confirm_pass_blank = [];
                $confirm_pass_blank['meta'] = (object) ['field' => 'user.password_confirmation'];
                $confirm_pass_blank['title'] = __("can't be blank");
                $errors[] = (object) $confirm_pass_blank;
            }
            if ($password_confirmation != $password) {
                $pass_blank = [];
                $pass_blank['meta'] = (object) ['field' => 'user.password'];
                $pass_blank['title'] = __(' - Passwords do not match');
                $errors[] = (object) $pass_blank;
            }
            $_SESSION['wicket_password_form_errors'] = $errors;

            // don't send anything if errors
            if (empty($errors)) {
                $update_user = new Wicket\Entities\People([
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
                } catch (Exception $e) {
                    $_SESSION['wicket_password_form_errors'] = json_decode($e->getResponse()->getBody())->errors;
                }
                // redirect here if there was updates made to reload person info and prevent form re-submission
                if (empty($_SESSION['wicket_password_form_errors'])) {
                    unset($_SESSION['wicket_password_form_errors']);
                    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?success');
                    die;
                }
            }
        } elseif (isset($_SESSION['wicket_password_form_errors'])) {
            unset($_SESSION['wicket_password_form_errors']);
        }
    }

    protected function init_block()
    {
        if (!isset($_POST['wicket_update_password'])) {
            unset($_SESSION['wicket_password_form_errors']);
        }

        $attrs = get_block_wrapper_attributes(
            [
                'class' => 'wicket-acc-block wicket-acc-block-password flex flex-col gap-8',
            ]
        );

        $password_form_has_errors = isset($_SESSION['wicket_password_form_errors']) && !empty($_SESSION['wicket_password_form_errors']);
        ?>
		<div <?php echo $attrs; ?> >
			<?php if ($password_form_has_errors) : ?>
				<div class='alert alert-danger' role="alert">
					<strong><?php printf(_n('The form could not be submitted because 1 error was found', 'The form could not be submitted because %s errors were found', count($_SESSION['wicket_password_form_errors']), 'wicket-acc'), number_format_i18n(count($_SESSION['wicket_password_form_errors']))); ?></strong>
					<?php
                            $counter = 1;
			    echo '<ul>';
			    foreach ($_SESSION['wicket_password_form_errors'] as $key => $error) {
			        if ($error->meta->field == 'user.current_password') {
			            $prefix = __('Current Password') . ' ';
			            printf(__("<li><a href='#current_password'><strong>%s</strong> %s</a></li>", 'wicket-acc'), 'Error: ' . $counter, $prefix . __($error->title));
			        }
			        if ($error->meta->field == 'user.password') {
			            $prefix = __('New Password') . ' ';
			            printf(__("<li><a href='#password'><strong>%s</strong> %s</a></li>", 'wicket-acc'), 'Error: ' . $counter, $prefix . __($error->title));
			        }
			        if ($error->meta->field == 'user.password_confirmation') {
			            $prefix = __('Confirm Password') . ' ';
			            printf(__("<li><a href='#password_confirmation'><strong>%s</strong> %s</a></li>", 'wicket-acc'), 'Error: ' . $counter, $prefix . __($error->title));
			        }
			        $counter++;
			    }
			    echo '</ul>';
			    ?>
				</div>
			<?php elseif (isset($_GET['success'])) : ?>
				<div class='alert alert-success' role="alert">
					<strong><?php _e('Your password has been updated.', 'wicket-acc'); ?></strong>
				</div>
			<?php endif; ?>

			<form class='manage_password_form' method="post">
				<div class="form__group">
					<label class="form__label" for="current_password"><?php _e('Current password', 'wicket-acc') ?>
						<span class="required">*</span>
						<?php
			        if ($password_form_has_errors) {
			            foreach ($_SESSION['wicket_password_form_errors'] as $key => $error) {
			                if (isset($error->meta->field) && $error->meta->field == 'user.current_password') {
			                    $current_password_err = true;
			                }
			            }
			        }
        ?>
					</label>
					<input class="form__input" <?php if (isset($current_password_err) && $current_password_err) : echo "class='error_input'";
					endif; ?> required type="password" id="current_password" name="current_password" value="">
				</div>

				<div class="form__group">
					<label class="form__label" for="password"><?php _e('New password', 'wicket-acc') ?>
						<span class="required">*</span>
						<?php
					    if ($password_form_has_errors) {
					        foreach ($_SESSION['wicket_password_form_errors'] as $key => $error) {
					            if (isset($error->meta->field) && $error->meta->field == 'user.password') {
					                $password_err = true;
					            }
					        }
					    }
        ?>
					</label>
					<p class='small-text'><?php _e('Minimum of 8 characters', 'wicket-acc') ?></p>
					<input class="form__input
						<?php echo $password_form_has_errors ? 'error_input' : '' ?>
						<?php echo (isset($password_err) && $password_err) ? 'error_input' : '' ?>"
						required type="password" name="password" id="password" value="">
				</div>

				<div class="form__group">
					<label class="form__label" for="password_confirmation"><?php _e('Confirm new password', 'wicket-acc') ?>
						<span class="required">*</span>
						<?php
        if ($password_form_has_errors) {
            foreach ($_SESSION['wicket_password_form_errors'] as $key => $error) {
                if (isset($error->meta->field) && $error->meta->field == 'user.password_confirmation') {
                    $password_confirm_err = true;
                }
            }
        }
        ?>
					</label>
					<input class="form__input
						<?php echo $password_form_has_errors ? 'error_input' : '' ?>
						<?php echo (isset($password_confirm_err) && $password_confirm_err) ? 'error_input' : '' ?>"
						type="password" id="password_confirmation" name="password_confirmation" value="">
				</div>

				<input type="hidden" name="wicket_update_password" value="wicket_update_password--1" />

				<?php
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
    }
}
