<?php

/**
 * Gravity Forms Helper for Additional Seats functionality.
 */

namespace WicketORM\Helpers;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Include Gravity Forms API
if (!class_exists('GFAPI')) {
    require_once WP_PLUGIN_DIR . '/gravityforms/includes/api.php';
}

/**
 * Helper functions for Gravity Forms integration.
 */
class GravityFormsHelper extends Helper
{
    /**
     * Cache for WPML string translations keyed by context and language.
     *
     * @var array<string, array<string, string>>
     */
    private static array $wpml_string_cache = [];

    /**
     * Initialize Gravity Forms hooks.
     */
    public static function init()
    {
        // Check if Gravity Forms is active
        if (!class_exists('GFForms')) {
            return;
        }

        add_action('gform_after_submission', [__CLASS__, 'handle_additional_seats_submission'], 10, 2);
        add_filter('gform_pre_submission_filter', [__CLASS__, 'capture_additional_seats_submission'], 5, 1);
        add_filter('gform_confirmation', [__CLASS__, 'add_to_cart_confirmation'], 10, 4);
        add_filter('gform_field_validation', [__CLASS__, 'validate_seat_quantity'], 10, 4);
        add_filter('gform_pre_render', [__CLASS__, 'force_wpml_translations'], 1000, 4);
        add_action('woocommerce_before_cart', [__CLASS__, 'maybe_restore_additional_seats_cart'], 5);
        add_action('woocommerce_before_checkout_form', [__CLASS__, 'maybe_restore_additional_seats_cart'], 5);
        add_action('woocommerce_checkout_update_order_review', [__CLASS__, 'maybe_restore_additional_seats_cart_on_update'], 5);
        add_filter('woocommerce_checkout_redirect_empty_cart', [__CLASS__, 'prevent_empty_checkout_redirect'], 5);
    }

    /**
     * Handle the additional seats form submission.
     *
     * @param array $submission The submission object.
     * @param array $form The form object.
     */
    public static function handle_additional_seats_submission($submission, $form)
    {
        // Check if this is the additional seats form
        $configService = new \WicketORM\Services\ConfigService();
        $additional_seats_form_id = $configService->getAdditionalSeatsFormId();

        $logger = \Wicket()->log();
        $context = [
            'source' => 'wicket-orgman',
            'form_id' => $form['id'] ?? null,
            'submission_id' => $submission['id'] ?? null,
        ];

        if ($form['id'] !== $additional_seats_form_id) {
            $logger->debug('[OrgMan] Ignoring Gravity Forms submission for non additional seats form', $context);

            return;
        }

        // Get form data from mapped field IDs
        $field_map = self::get_additional_seats_field_map($form);
        $org_uuid_field_id = $field_map['org_uuid'] ?? null;
        $membership_field_id = $field_map['membership_id'] ?? null;
        $quantity_field_id = $field_map['seat_quantity'] ?? null;

        $org_uuid = $org_uuid_field_id ? ($submission[$org_uuid_field_id] ?? '') : '';
        $membership_id = $membership_field_id ? ($submission[$membership_field_id] ?? '') : '';
        $seat_quantity = $quantity_field_id ? ($submission[$quantity_field_id] ?? 1) : 1;
        if (empty($org_uuid)) {
            $org_uuid = isset($_POST['org_uuid']) ? sanitize_text_field(wp_unslash($_POST['org_uuid'])) : '';
        }
        if (empty($org_uuid)) {
            $org_uuid = isset($_GET['org_uuid']) ? sanitize_text_field(wp_unslash($_GET['org_uuid'])) : '';
        }
        if (empty($membership_id)) {
            $membership_id = self::get_request_membership_id();
        }
        if (empty($org_uuid) || empty($membership_id)) {
            $additional_seats_service = new \WicketORM\Services\AdditionalSeatsService($configService);
            $purchase_meta = $additional_seats_service->getPurchaseUserMeta();
            if (is_array($purchase_meta)) {
                if (empty($org_uuid) && !empty($purchase_meta['org_uuid'])) {
                    $org_uuid = sanitize_text_field((string) $purchase_meta['org_uuid']);
                }
                if (empty($membership_id) && !empty($purchase_meta['membership_id'])) {
                    $membership_id = sanitize_text_field((string) $purchase_meta['membership_id']);
                }
            }
        }

        $logger->info('[OrgMan] Handling Gravity Forms additional seats submission', array_merge($context, [
            'org_uuid_present' => !empty($org_uuid),
            'membership_id_present' => !empty($membership_id),
            'seat_quantity' => (int) $seat_quantity,
        ]));

        // Validate required fields
        if (empty($org_uuid) || empty($membership_id) || empty($seat_quantity)) {
            self::log_error('Missing required fields in additional seats submission', [
                'org_uuid' => $org_uuid,
                'membership_id' => $membership_id,
                'seat_quantity' => $seat_quantity,
            ]);
            $logger->error('[OrgMan] Gravity Forms additional seats submission missing required fields', array_merge($context, [
                'org_uuid' => $org_uuid,
                'membership_id' => $membership_id,
                'seat_quantity' => $seat_quantity,
            ]));

            return;
        }

        // Store data in session for cart processing
        $_SESSION['orgman_additional_seats'] = [
            'org_uuid' => sanitize_text_field($org_uuid),
            'membership_id' => sanitize_text_field($membership_id),
            'seat_quantity' => absint($seat_quantity),
        ];

        $logger->info('[OrgMan] Stored Gravity Forms additional seats submission in session', array_merge($context, [
            'org_uuid' => sanitize_text_field($org_uuid),
            'membership_id' => sanitize_text_field($membership_id),
            'seat_quantity' => absint($seat_quantity),
        ]));
    }

    /**
     * Modify confirmation to add product to cart.
     *
     * @param string $confirmation The confirmation message.
     * @param array $form The form object.
     * @param array $entry The entry object.
     * @param bool $ajax Whether the form was submitted via AJAX.
     * @return string The modified confirmation.
     */
    public static function add_to_cart_confirmation($confirmation, $form, $entry, $ajax)
    {
        $configService = new \WicketORM\Services\ConfigService();
        $additional_seats_form_id = $configService->getAdditionalSeatsFormId();

        $logger = \Wicket()->log();
        $context = [
            'source' => 'wicket-orgman',
            'form_id' => $form['id'] ?? null,
            'entry_id' => $entry['id'] ?? null,
        ];

        if ($form['id'] !== $additional_seats_form_id) {
            $logger->debug('[OrgMan] Skipping confirmation handling for non additional seats form', $context);

            return $confirmation;
        }

        if (empty($_SESSION['orgman_additional_seats'])) {
            $logger->warning('[OrgMan] Additional seats confirmation missing session data', $context);

            return $confirmation;
        }

        $seat_data = $_SESSION['orgman_additional_seats'];

        // Get the additional seats product
        $additional_seats_service = new \WicketORM\Services\AdditionalSeatsService($configService);
        $product_id = $additional_seats_service->getAdditionalSeatsProduct();

        if (!$product_id) {
            wc_add_notice(__('Additional seats product not found. Please contact support.', 'wicket-acc'), 'error');
            $logger->error('[OrgMan] Additional seats product missing during Gravity Forms confirmation', $context);

            return $confirmation;
        }

        if (WC()->session) {
            WC()->session->set_customer_session_cookie(true);
        }

        if (WC()->cart && !WC()->cart->is_empty()) {
            WC()->cart->empty_cart();
        }

        self::load_cart_session_if_needed();

        // Add product to cart
        $cart_key = WC()->cart->add_to_cart(
            $product_id,
            $seat_data['seat_quantity'],
            0,
            [],
            [
                'org_uuid' => $seat_data['org_uuid'],
                'membership_id' => $seat_data['membership_id'],
                'additional_seats' => true,
            ]
        );

        if ($cart_key) {
            // Clear session data
            unset($_SESSION['orgman_additional_seats']);

            if (WC()->session) {
                WC()->session->set_customer_session_cookie(true);
            }
            if (WC()->cart) {
                WC()->cart->set_session();
                WC()->cart->maybe_set_cart_cookies();
                if (WC()->session && method_exists(WC()->session, 'save_data')) {
                    WC()->session->save_data();
                }
            }

            $checkout_url = self::get_localized_checkout_url();

            // Redirect to checkout
            $confirmation = [
                'type' => 'redirect',
                'redirect' => $checkout_url,
            ];

            return $confirmation;
        } else {
            wc_add_notice(__('Unable to add additional seats to cart. Please try again.', 'wicket-acc'), 'error');
            $logger->error('[OrgMan] Failed to add additional seats product to cart', array_merge($context, [
                'product_id' => (int) $product_id,
            ]));

            return $confirmation;
        }
    }

    /**
     * Capture additional seats submission values before confirmation handling.
     *
     * @param array $form The form object.
     * @return array
     */
    public static function capture_additional_seats_submission($form)
    {
        $configService = new \WicketORM\Services\ConfigService();
        $additional_seats_form_id = $configService->getAdditionalSeatsFormId();

        if ((int) ($form['id'] ?? 0) !== (int) $additional_seats_form_id) {
            return $form;
        }

        $field_map = self::get_additional_seats_field_map($form);
        $org_uuid_field_id = $field_map['org_uuid'] ?? null;
        $membership_field_id = $field_map['membership_id'] ?? null;
        $quantity_field_id = $field_map['seat_quantity'] ?? null;

        $org_uuid = $org_uuid_field_id ? self::get_posted_field_value($org_uuid_field_id) : '';
        $membership_id = $membership_field_id ? self::get_posted_field_value($membership_field_id) : '';
        $seat_quantity = $quantity_field_id ? self::get_posted_field_value($quantity_field_id) : '';

        if ($org_uuid === '') {
            $org_uuid = isset($_POST['org_uuid']) ? sanitize_text_field(wp_unslash($_POST['org_uuid'])) : '';
        }
        if ($org_uuid === '') {
            $org_uuid = isset($_GET['org_uuid']) ? sanitize_text_field(wp_unslash($_GET['org_uuid'])) : '';
        }
        if ($membership_id === '') {
            $membership_id = self::get_request_membership_id();
        }
        if ($org_uuid === '' || $membership_id === '') {
            $additional_seats_service = new \WicketORM\Services\AdditionalSeatsService($configService);
            $purchase_meta = $additional_seats_service->getPurchaseUserMeta();
            if (is_array($purchase_meta)) {
                if ($org_uuid === '' && !empty($purchase_meta['org_uuid'])) {
                    $org_uuid = sanitize_text_field((string) $purchase_meta['org_uuid']);
                }
                if ($membership_id === '' && !empty($purchase_meta['membership_id'])) {
                    $membership_id = sanitize_text_field((string) $purchase_meta['membership_id']);
                }
            }
        }

        $quantity = absint($seat_quantity);
        if ($org_uuid === '' || $membership_id === '' || $quantity < 1) {
            return $form;
        }

        $_SESSION['orgman_additional_seats'] = [
            'org_uuid' => sanitize_text_field($org_uuid),
            'membership_id' => sanitize_text_field($membership_id),
            'seat_quantity' => $quantity,
        ];

        $user_id = get_current_user_id();
        if ($user_id) {
            update_user_meta($user_id, 'orgman_additional_seats_pending', [
                'org_uuid' => sanitize_text_field($org_uuid),
                'membership_id' => sanitize_text_field($membership_id),
                'seat_quantity' => $quantity,
                'created_at' => current_time('mysql'),
            ]);
        }

        return $form;
    }

    /**
     * Get posted value for a field ID.
     *
     * @param int $field_id The Gravity Forms field ID.
     * @return string
     */
    private static function get_posted_field_value(int $field_id): string
    {
        $input_key = 'input_' . $field_id;
        if (function_exists('rgpost')) {
            $value = rgpost($input_key);
        } else {
            $value = isset($_POST[$input_key]) ? wp_unslash($_POST[$input_key]) : '';
        }

        return is_string($value) ? $value : '';
    }

    /**
     * Read membership identifier from request, supporting membership_id and membership_uuid.
     *
     * @return string
     */
    private static function get_request_membership_id(): string
    {
        if (isset($_POST['membership_id'])) {
            return sanitize_text_field(wp_unslash($_POST['membership_id']));
        }
        if (isset($_POST['membership_uuid'])) {
            return sanitize_text_field(wp_unslash($_POST['membership_uuid']));
        }
        if (isset($_GET['membership_id'])) {
            return sanitize_text_field(wp_unslash($_GET['membership_id']));
        }
        if (isset($_GET['membership_uuid'])) {
            return sanitize_text_field(wp_unslash($_GET['membership_uuid']));
        }

        return '';
    }

    /**
     * Ensure WooCommerce cart session is loaded before mutations.
     *
     * @return void
     */
    private static function load_cart_session_if_needed(): void
    {
        if (!WC()->cart) {
            return;
        }

        if (!did_action('woocommerce_load_cart_from_session')) {
            WC()->cart->get_cart();
        }
    }

    /**
     * Validate seat quantity field.
     *
     * @param array $result The validation result.
     * @param mixed $value The field value.
     * @param array $form The form object.
     * @param array $field The field object.
     * @return array The modified validation result.
     */
    public static function validate_seat_quantity($result, $value, $form, $field)
    {
        $configService = new \WicketORM\Services\ConfigService();
        $additional_seats_form_id = $configService->getAdditionalSeatsFormId();

        $field_map = self::get_additional_seats_field_map($form);
        $quantity_field_id = $field_map['seat_quantity'] ?? null;

        if ($form['id'] !== $additional_seats_form_id || !$quantity_field_id || (int) $field->id !== (int) $quantity_field_id) {
            return $result;
        }

        $quantity = absint($value);

        if ($quantity < 1) {
            $result['is_valid'] = false;
            $result['message'] = __('Please enter at least 1 seat.', 'wicket-acc');
        } elseif ($quantity > 100) {
            $result['is_valid'] = false;
            $result['message'] = __('Maximum 100 seats can be purchased at once. Please contact support for larger orders.', 'wicket-acc');
        }

        return $result;
    }

    /**
     * Get the form field values for additional seats.
     *
     * @param string $org_uuid The organization UUID.
     * @param string $membership_id The membership ID.
     * @return array The form field values.
     */
    public static function get_form_field_values($org_uuid, $membership_id)
    {
        return [
            'org_uuid' => $org_uuid,
            'membership_id' => $membership_id,
        ];
    }

    /**
     * Check if the current page is the supplemental members page.
     *
     * @return bool True if on supplemental members page.
     */
    public static function is_supplemental_members_page()
    {
        global $wp;

        return isset($wp->query_vars['pagename'])
               && $wp->query_vars['pagename'] === 'my-account/supplemental-members';
    }

    /**
     * Get form HTML for additional seats.
     *
     * @param string $org_uuid The organization UUID.
     * @param string $membership_id The membership ID.
     * @return string The form HTML or empty string if form not found.
     */
    public static function get_form_html($org_uuid, $membership_id)
    {
        $configService = new \WicketORM\Services\ConfigService();
        $form_id = $configService->getAdditionalSeatsFormIdForCurrentLanguage();
        $query_form_id = isset($_GET['gf_id']) ? absint($_GET['gf_id']) : 0;
        if ($query_form_id > 0) {
            $form_id = $configService->getLocalizedFormId($query_form_id);
        }

        if (empty($form_id)) {
            return '';
        }

        $form = \GFAPI::get_form($form_id);
        if (!$form) {
            return '';
        }

        // Set default values for hidden fields
        $_GET['org_uuid'] = $org_uuid;
        $_GET['membership_id'] = $membership_id;

        $previous_lang = null;
        $current_lang = function_exists('wicket_get_current_language') ? wicket_get_current_language() : null;
        if (defined('ICL_SITEPRESS_VERSION') && $current_lang) {
            $previous_lang = apply_filters('wpml_current_language', null);
            if (is_string($previous_lang) && $previous_lang !== $current_lang) {
                do_action('wpml_switch_language', $current_lang);
            }
        }

        $field_values = [
            'org_uuid' => $org_uuid,
            'membership_id' => $membership_id,
        ];

        // Render the form without AJAX to preserve Woo session/cart cookies.
        ob_start();
        gravity_form($form_id, false, false, false, $field_values, false);
        $form_html = ob_get_clean();

        if (defined('ICL_SITEPRESS_VERSION') && $current_lang) {
            if (is_string($previous_lang) && $previous_lang !== $current_lang) {
                do_action('wpml_switch_language', $previous_lang);
            }
        }

        return $form_html;
    }

    /**
     * Force WPML string translations when Gravity Forms output is not localized.
     *
     * @param array      $form The form object.
     * @param bool|null  $ajax Whether the form is being displayed via AJAX.
     * @param array|null $field_values The field values to be used to populate the form.
     * @param string     $context The render context.
     * @return array
     */
    public static function force_wpml_translations($form, $ajax, $field_values, $context)
    {
        if (!defined('ICL_SITEPRESS_VERSION')) {
            return $form;
        }

        $configService = new \WicketORM\Services\ConfigService();
        $configured_form_id = $configService->getAdditionalSeatsFormId();
        $localized_form_id = $configService->getAdditionalSeatsFormIdForCurrentLanguage();
        $form_id = (int) ($form['id'] ?? 0);
        $query_form_id = isset($_GET['gf_id']) ? absint($_GET['gf_id']) : 0;

        if ($form_id === 0 || ($form_id !== $configured_form_id && $form_id !== $localized_form_id && $query_form_id !== $form_id)) {
            return $form;
        }

        $current_lang = function_exists('wicket_get_current_language') ? wicket_get_current_language() : null;
        if (!is_string($current_lang) || $current_lang === '') {
            return $form;
        }

        $context_key = sanitize_title_with_dashes(
            (defined('ICL_GRAVITY_FORM_ELEMENT_TYPE') ? ICL_GRAVITY_FORM_ELEMENT_TYPE : 'gravity_form') . '-' . $form_id
        );

        $string_helper = class_exists('GFML_String_Name_Helper') ? new \GFML_String_Name_Helper() : null;

        if ($string_helper) {
            $form_title_key = $string_helper->get_form_title();
            $form_button_key = $string_helper->get_form_submit_button();
            $form_description_key = $string_helper->get_form_description();
            $html_field_keys = [];
            foreach ($form['fields'] as $field) {
                if (isset($field->type) && $field->type === 'html' && isset($field->content)) {
                    $string_helper->field = $field;
                    $html_field_keys[] = $string_helper->get_field_html();
                }
            }

            $string_names = array_values(array_unique(array_merge([
                $form_title_key,
                $form_button_key,
                $form_description_key,
            ], $html_field_keys)));
            self::preload_wpml_strings($context_key, $current_lang, $string_names);

            if (isset($form['title'])) {
                $form['title'] = self::translate_wpml_string((string) $form['title'], $context_key, $form_title_key, $current_lang);
            }
            if (isset($form['button']['text'])) {
                $form['button']['text'] = self::translate_wpml_string((string) $form['button']['text'], $context_key, $form_button_key, $current_lang);
            }
            if (isset($form['description'])) {
                $form['description'] = self::translate_wpml_string((string) $form['description'], $context_key, $form_description_key, $current_lang);
            }

            foreach ($form['fields'] as &$field) {
                $string_helper->field = $field;

                if (isset($field->label) && is_string($field->label) && $field->label !== '') {
                    $string_helper->field_key = 'label';
                    $label_name = $string_helper->get_field_common();
                    $field->label = self::translate_wpml_string($field->label, $context_key, $label_name, $current_lang);
                }

                if (isset($field->type) && $field->type === 'html' && isset($field->content)) {
                    $string_name = $string_helper->get_field_html();
                    $field->content = self::translate_wpml_string((string) $field->content, $context_key, $string_name, $current_lang);
                }
            }
            unset($field);
        }

        return $form;
    }

    /**
     * Map additional seats form field IDs based on known identifiers.
     *
     * @param array $form The Gravity Forms form object.
     * @return array<string, int>
     */
    private static function get_additional_seats_field_map(array $form): array
    {
        $map = [];
        if (empty($form['fields'])) {
            return $map;
        }

        foreach ($form['fields'] as $field) {
            if (!isset($field->id)) {
                continue;
            }

            $input_name = isset($field->inputName) ? (string) $field->inputName : '';
            $admin_label = isset($field->adminLabel) ? (string) $field->adminLabel : '';
            $css_class = isset($field->cssClass) ? (string) $field->cssClass : '';
            $label = isset($field->label) ? (string) $field->label : '';

            if ($field->type === 'hidden') {
                if ($input_name === 'org_uuid' || $admin_label === 'org_uuid' || $label === 'org_uuid') {
                    $map['org_uuid'] = (int) $field->id;
                }
                if ($input_name === 'membership_id' || $admin_label === 'membership_id' || $label === 'membership_id') {
                    $map['membership_id'] = (int) $field->id;
                }
            }

            if ($field->type === 'number') {
                if ($input_name === 'seat_quantity' || $input_name === 'additional_seats' || $admin_label === 'seat_quantity') {
                    $map['seat_quantity'] = (int) $field->id;
                } elseif ($css_class !== '' && strpos($css_class, 'supplemental_users') !== false) {
                    $map['seat_quantity'] = (int) $field->id;
                } elseif ($label === 'Number of Additional Seats') {
                    $map['seat_quantity'] = (int) $field->id;
                }
            }
        }

        return $map;
    }

    /**
     * Restore additional seats cart item if session/cart was lost.
     *
     * @return void
     */
    public static function maybe_restore_additional_seats_cart($force = false): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;

        if (!is_user_logged_in()) {
            return;
        }

        if (!WC()->cart) {
            return;
        }

        $logger = \Wicket()->log();
        $is_cart_hook = did_action('woocommerce_before_cart') > 0;
        $is_checkout_hook = did_action('woocommerce_before_checkout_form') > 0;
        $force_restore = is_bool($force) ? $force : false;
        $is_cart_page = is_cart() || $is_cart_hook;
        $is_checkout_page = is_checkout() || $is_checkout_hook || $force_restore;

        if (!($is_cart_page || $is_checkout_page)) {
            return;
        }

        if (!WC()->cart->is_empty()) {
            $user_id = get_current_user_id();
            if ($user_id) {
                delete_user_meta($user_id, 'orgman_additional_seats_pending');
            }

            return;
        }

        $user_id = get_current_user_id();
        $pending = get_user_meta($user_id, 'orgman_additional_seats_pending', true);
        if (!is_array($pending)) {
            $logger->info('[OrgMan] No pending additional seats data to restore', [
                'source' => 'wicket-orgman',
                'user_id' => $user_id,
                'is_cart' => $is_cart_page,
                'is_checkout' => $is_checkout_page,
            ]);

            return;
        }

        $org_uuid = isset($pending['org_uuid']) ? sanitize_text_field((string) $pending['org_uuid']) : '';
        $membership_id = isset($pending['membership_id']) ? sanitize_text_field((string) $pending['membership_id']) : '';
        $seat_quantity = isset($pending['seat_quantity']) ? absint($pending['seat_quantity']) : 0;
        if ($org_uuid === '' || $membership_id === '' || $seat_quantity < 1) {
            $logger->warning('[OrgMan] Pending additional seats data invalid', [
                'source' => 'wicket-orgman',
                'user_id' => $user_id,
                'org_uuid' => $org_uuid,
                'membership_id' => $membership_id,
                'seat_quantity' => $seat_quantity,
            ]);

            return;
        }

        $configService = new \WicketORM\Services\ConfigService();
        $additional_seats_service = new \WicketORM\Services\AdditionalSeatsService($configService);
        $product_id = $additional_seats_service->getAdditionalSeatsProduct();
        if (!$product_id) {
            wc_add_notice(__('Additional seats product not found. Please contact support.', 'wicket-acc'), 'error');
            $logger->error('[OrgMan] Additional seats product missing during restore', [
                'source' => 'wicket-orgman',
                'user_id' => $user_id,
            ]);

            return;
        }

        if (WC()->session) {
            WC()->session->set_customer_session_cookie(true);
        }
        self::load_cart_session_if_needed();

        $cart_key = WC()->cart->add_to_cart(
            $product_id,
            $seat_quantity,
            0,
            [],
            [
                'org_uuid' => $org_uuid,
                'membership_id' => $membership_id,
                'additional_seats' => true,
            ]
        );

        if ($cart_key) {
            WC()->cart->set_session();
            WC()->cart->maybe_set_cart_cookies();
            if (WC()->session && method_exists(WC()->session, 'save_data')) {
                WC()->session->save_data();
            }
            if ($is_cart_page) {
                wp_safe_redirect(self::get_localized_checkout_url());
                exit;
            }
        }
    }

    /**
     * Restore additional seats cart item during checkout AJAX updates.
     *
     * @param string $posted_data The serialized checkout data.
     * @return void
     */
    public static function maybe_restore_additional_seats_cart_on_update(string $posted_data): void
    {
        self::maybe_restore_additional_seats_cart(true);
    }

    /**
     * Prevent checkout redirect when pending additional seats data exists.
     *
     * @param bool $redirect Whether to redirect.
     * @return bool
     */
    public static function prevent_empty_checkout_redirect(bool $redirect): bool
    {
        if (!$redirect) {
            return $redirect;
        }

        if (!is_user_logged_in()) {
            return $redirect;
        }

        if (self::has_pending_additional_seats()) {
            return false;
        }

        return $redirect;
    }

    /**
     * Check if pending additional seats data exists for current user.
     *
     * @return bool
     */
    private static function has_pending_additional_seats(): bool
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }

        $pending = get_user_meta($user_id, 'orgman_additional_seats_pending', true);

        return is_array($pending) && !empty($pending['org_uuid']) && !empty($pending['membership_id']);
    }

    /**
     * Get localized checkout URL if WPML is active.
     *
     * @return string
     */
    private static function get_localized_checkout_url(): string
    {
        $checkout_url = wc_get_checkout_url();
        if (!function_exists('wicket_is_multilang_active') || !wicket_is_multilang_active()) {
            return $checkout_url;
        }

        $current_lang = apply_filters('wpml_current_language', null);
        if (!is_string($current_lang) || $current_lang === '') {
            $current_lang = function_exists('wicket_get_current_language') ? wicket_get_current_language() : null;
        }
        if (!is_string($current_lang) || $current_lang === '') {
            $referer = wp_get_referer();
            if (is_string($referer)) {
                $referer_lang = apply_filters('wpml_get_language_from_url', $referer, null);
                if (is_string($referer_lang) && $referer_lang !== '') {
                    $current_lang = $referer_lang;
                }
            }
        }

        if (is_string($current_lang) && $current_lang !== '') {
            $checkout_page_id = wc_get_page_id('checkout');
            if ($checkout_page_id > 0) {
                $translated_id = apply_filters('wpml_object_id', $checkout_page_id, 'page', false, $current_lang);
                if ($translated_id) {
                    $checkout_url = get_permalink($translated_id);
                }
            }
        }

        return $checkout_url;
    }

    /**
     * Translate a string via WPML with a DB fallback.
     *
     * @param string $value The original string value.
     * @param string $context The WPML context.
     * @param string $name The WPML string name.
     * @param string $language The language code.
     * @return string
     */
    private static function translate_wpml_string(string $value, string $context, string $name, string $language): string
    {
        $translated = apply_filters('wpml_translate_single_string', $value, $context, $name, $language);
        if (is_string($translated) && $translated !== $value) {
            return $translated;
        }

        $cache_key = $context . '|' . $language;
        if (isset(self::$wpml_string_cache[$cache_key][$name])) {
            $cached = self::$wpml_string_cache[$cache_key][$name];

            return $cached !== '' ? $cached : $value;
        }

        $db_value = self::get_wpml_string_from_db($context, $name, $language);
        self::$wpml_string_cache[$cache_key][$name] = is_string($db_value) ? $db_value : '';

        return is_string($db_value) && $db_value !== '' ? $db_value : $value;
    }

    /**
     * Preload WPML string translations for a context and language.
     *
     * @param string $context The WPML context.
     * @param string $language The language code.
     * @param array  $names The string names to preload.
     * @return void
     */
    private static function preload_wpml_strings(string $context, string $language, array $names): void
    {
        if (empty($names)) {
            return;
        }

        $cache_key = $context . '|' . $language;
        if (isset(self::$wpml_string_cache[$cache_key])) {
            return;
        }

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($names), '%s'));
        $query = $wpdb->prepare(
            "SELECT s.name, t.value AS translated_value
             FROM {$wpdb->prefix}icl_strings s
             INNER JOIN {$wpdb->prefix}icl_string_translations t
               ON t.string_id = s.id AND t.language = %s
             WHERE s.context = %s AND s.name IN ({$placeholders})",
            array_merge([$language, $context], $names)
        );
        $rows = $wpdb->get_results($query);
        $translations = [];
        foreach ($rows as $row) {
            if (isset($row->name) && isset($row->translated_value)) {
                $translations[(string) $row->name] = (string) $row->translated_value;
            }
        }

        self::$wpml_string_cache[$cache_key] = $translations;
    }

    /**
     * Fetch a single WPML translation directly from the database.
     *
     * @param string $context The WPML context.
     * @param string $name The WPML string name.
     * @param string $language The language code.
     * @return string|null
     */
    private static function get_wpml_string_from_db(string $context, string $name, string $language): ?string
    {
        global $wpdb;
        $db_value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT t.value
                 FROM {$wpdb->prefix}icl_strings s
                 INNER JOIN {$wpdb->prefix}icl_string_translations t
                   ON t.string_id = s.id AND t.language = %s
                 WHERE s.context = %s AND s.name = %s
                 LIMIT 1",
                $language,
                $context,
                $name
            )
        );

        return is_string($db_value) ? $db_value : null;
    }
}
