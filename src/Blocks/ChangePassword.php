<?php

declare(strict_types=1);

namespace WicketAcc\Blocks;

use HyperBlocks\Block\Block;
use HyperBlocks\Block\Field;
use HyperBlocks\Config as HyperBlocksConfig;
use HyperBlocks\Registry as HyperBlocksRegistry;

// No direct access
defined('ABSPATH') || exit;

/**
 * HyperBlocks Block: ACC Change Password.
 *
 * Block: ACC Change Password.
 *
 * Renders through a .hb.php template at
 * templates-wicket/blocks/change-password.hb.php; form-submission errors are
 * exposed to that template via getFormErrors() because HyperBlocks render
 * templates run in an isolated scope that cannot see $this/self.
 */
class ChangePassword
{
    /**
     * Store form errors for the current request.
     *
     * @var array
     */
    private static array $form_errors = [];

    public function __construct()
    {
        // Register the plugin's block-template directory as a TEMPLATE-ONLY
        // path (not a discovery path). HyperBlocks validates render-template
        // paths against this allowlist, but does NOT glob it for block
        // definitions — important because templates-wicket/blocks/account-centre/
        // holds pre-existing ACF render templates that expect a render context
        // and would fatal if require_once'd as block definitions on init.
        HyperBlocksConfig::registerTemplatePath(WICKET_ACC_PATH . 'templates-wicket/blocks');

        add_action('init', [$this, 'registerAssets']);
        add_action('init', [$this, 'registerBlock'], 9);
        add_action('init', [$this, 'processWicketPasswordForm']);
    }

    /**
     * Register the block's style handle.
     *
     * Hooked on init (not the constructor) so it fires after WordPress is
     * ready for script/style registration — registering during plugin
     * bootstrap triggers a "called incorrectly" notice.
     */
    public function registerAssets(): void
    {
        wp_register_style(
            'wicket-acc-password-block',
            WICKET_ACC_URL . 'assets/css/blocks/change-password.css',
            [],
            '1.0.0'
        );
    }

    /**
     * Form errors captured by processWicketPasswordForm for the current
     * request. Public so the isolated-scope render template can read them
     * via ChangePassword::getFormErrors() without access to self::$form_errors.
     *
     * @return array
     */
    public static function getFormErrors(): array
    {
        return self::$form_errors;
    }

    /**
     * Register the block with HyperBlocks.
     */
    public function registerBlock(): void
    {
        $block = Block::make(__('ACC Change Password', 'wicket-acc'))
            ->setName('wicket-acc/change-password')
            ->setIcon('lock')
            ->addFields([
                Field::make('text', 'form_title', __('Form Title', 'wicket-acc'))
                    ->setDefault(__('Change Password', 'wicket-acc')),
                Field::make('textarea', 'form_instructions', __('Form Instructions', 'wicket-acc'))
                    ->setDefault(__('Enter your current password and choose a new one.', 'wicket-acc')),
            ])
            ->setDescription(__('A Wicket block for changing the user password.', 'wicket-acc'))
            ->setCategory('wicket-account-center')
            ->setKeywords([__('account-centre', 'wicket-acc'), __('password', 'wicket-acc'), __('wicket', 'wicket-acc')])
            ->setStyle('wicket-acc-password-block')
            ->setRenderTemplateFile('change-password.hb.php');

        HyperBlocksRegistry::getInstance()->registerFluentBlock($block);
    }

    /**
     * Process the change-password form submission.
     *
     * Validates inputs, calls the MDP people API, and either redirects on
     * success or stores errors for the render template to display.
     */
    public function processWicketPasswordForm()
    {
        if (!isset($_POST['wicket_update_password'])) {
            return;
        }

        $errors = [];
        $client = WACC()->Mdp()->initClient();
        $person = wicket_current_person();

        // Passwords must not be sanitized — sanitize_text_field() strips special
        // characters and would silently corrupt passwords before they reach the API.
        // wp_unslash() is safe: it only removes magic-quote backslashes, never alters chars.
        $current_password = wp_unslash($_POST['current_password'] ?? '');
        $password = wp_unslash($_POST['password'] ?? '');
        $password_confirmation = wp_unslash($_POST['password_confirmation'] ?? '');

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
