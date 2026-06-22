<?php

/**
 * Notification service for managing user notifications.
 */

namespace WicketORM\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles notification operations for the organization management system.
 */
class NotificationService
{
    /**
     * @var array
     */
    private $notifications = [];

    /**
     * Add a notification to be displayed.
     *
     * @param string $message The notification message.
     * @param string $type The notification type (success, error, warning, info).
     * @param string $title Optional notification title.
     * @param array  $options Additional options.
     * @return array The notification data.
     */
    public function addNotification($message, $type = 'info', $title = '', $options = [])
    {
        $notification = [
            'id'       => uniqid('notification_'),
            'type'     => in_array($type, ['success', 'error', 'warning', 'info']) ? $type : 'info',
            'title'    => sanitize_text_field($title),
            'message'  => wp_kses_post($message),
            'time'     => current_time('timestamp'),
            'autoClose' => $options['autoClose'] ?? true,
            'duration'  => $options['duration'] ?? 5000,
            'inline'   => $options['inline'] ?? false,
        ];

        $this->notifications[] = $notification;

        return $notification;
    }

    /**
     * Add a success notification.
     *
     * @param string $message The success message.
     * @param string $title Optional title.
     * @param array  $options Additional options.
     * @return array The notification data.
     */
    public function success($message, $title = '', $options = [])
    {
        return $this->addNotification($message, 'success', $title, $options);
    }

    /**
     * Add an error notification.
     *
     * @param string $message The error message.
     * @param string $title Optional title.
     * @param array  $options Additional options.
     * @return array The notification data.
     */
    public function error($message, $title = '', $options = [])
    {
        return $this->addNotification($message, 'error', $title, $options);
    }

    /**
     * Add a warning notification.
     *
     * @param string $message The warning message.
     * @param string $title Optional title.
     * @param array  $options Additional options.
     * @return array The notification data.
     */
    public function warning($message, $title = '', $options = [])
    {
        return $this->addNotification($message, 'warning', $title, $options);
    }

    /**
     * Add an info notification.
     *
     * @param string $message The info message.
     * @param string $title Optional title.
     * @param array  $options Additional options.
     * @return array The notification data.
     */
    public function info($message, $title = '', $options = [])
    {
        return $this->addNotification($message, 'info', $title, $options);
    }

    /**
     * Get all notifications.
     *
     * @return array Array of notifications.
     */
    public function getNotifications()
    {
        return $this->notifications;
    }

    /**
     * Clear all notifications.
     *
     * @return void
     */
    public function clearNotifications()
    {
        $this->notifications = [];
    }

    /**
     * Convert WP_Error to notification.
     *
     * @param WP_Error $wp_error The WP_Error object.
     * @param array    $options Additional options.
     * @return array|null The notification data or null if no error.
     */
    public function convertWpError($wp_error, $options = [])
    {
        if (!is_wp_error($wp_error)) {
            return null;
        }

        $message = $wp_error->get_error_message();
        $title = $wp_error->get_error_code();

        return $this->error($message, $title, $options);
    }

    /**
     * Generate JavaScript for displaying notifications.
     *
     * @return string JavaScript code.
     */
    public function generateJs()
    {
        if (empty($this->notifications)) {
            return '';
        }

        $js_notifications = [];

        foreach ($this->notifications as $notification) {
            $js_notifications[] = [
                'type'     => $notification['type'],
                'title'    => $notification['title'],
                'message'  => $notification['message'],
                'autoClose' => $notification['autoClose'],
                'duration'  => $notification['duration'],
                'inline'   => $notification['inline'],
            ];
        }

        $json_data = json_encode($js_notifications);

        return <<<JS
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof notificationSystem !== 'undefined') {
                    const notifications = {$json_data};
                    notifications.forEach(function(notification) {
                        notificationSystem.show(notification);
                    });
                }
            });
            </script>
            JS;
    }

    /**
     * Send person to organization assignment email.
     *
     * This method provides backward compatibility with the legacy function signature.
     * Sends a welcome email to a person when they are assigned to an organization.
     *
     * @param string $person_uuid The UUID of the person.
     * @param string $org_id The organization ID.
     * @return bool|WP_Error True if email sent successfully, false if not, WP_Error on failure.
     */
    public function sendPersonToOrgAssignmentEmail($person_uuid, $org_id)
    {
        $logger = \Wicket()->log();
        $context = [
            'source' => 'wicket-orgman',
            'notification' => 'person_to_org_assignment',
            'org_id' => $org_id,
            'person_uuid' => $person_uuid,
        ];

        if (empty($person_uuid) || empty($org_id)) {
            $logger->error('[OrgMan] Assignment email aborted: missing identifiers', $context);

            return new WP_Error('invalid_params', 'Person UUID and organization ID are required.');
        }

        if (!function_exists('wicket_get_organization') || !function_exists('wicket_get_person_by_id')) {
            $logger->error('[OrgMan] Assignment email dependencies missing', $context);

            return new WP_Error('missing_dependency', 'Wicket API functions are unavailable.');
        }

        try {
            $logger->debug('[OrgMan] Preparing notification email payload', $context);
            // Get site information (always available)
            $lang = wicket_get_current_language();
            $home_url = get_home_url();
            $site_name = get_bloginfo('name');
            $site_url = get_site_url();
            $base_domain = parse_url($site_url, PHP_URL_HOST);

            // Fix localhost domain for local development (Mailtrap compatibility)
            if ($base_domain === 'localhost') {
                $base_domain = 'localhost.com';
            }

            // Try to get organization data, but don't fail if it's not available
            $organization_name = $site_name; // Default fallback
            try {
                $org = wicket_get_organization($org_id);
                if ($org && isset($org['data']['attributes'])) {
                    $attributes = $org['data']['attributes'];
                    $name_key = 'legal_name_' . $lang;
                    if (isset($attributes[$name_key]) && '' !== $attributes[$name_key]) {
                        $organization_name = $attributes[$name_key];
                    }
                }
            } catch (\Exception $org_exception) {
                // Log the error but continue with fallback organization name
                $logger->warning('[OrgMan] Could not fetch organization data for assignment email', array_merge($context, [
                    'exception' => $org_exception->getMessage(),
                ]));
            }

            // Get person data
            $person = wicket_get_person_by_id($person_uuid);
            if (!$person) {
                $logger->error('[OrgMan] Assignment email aborted: person not found', $context);

                return new WP_Error('person_not_found', 'Person not found.');
            }

            // Get person details
            $to = $person->primary_email_address ?? '';
            if (empty($to)) {
                $logger->error('[OrgMan] Assignment email aborted: person missing primary email', $context);

                return new WP_Error('email_missing', 'Person does not have a primary email address.');
            }

            // Get configuration for email
            $config = \WicketORM\Services\ConfigService::getConfig();
            $confirmation_email_from = $config['integrations']['notifications']['confirmation_email_from'] ?? 'no-reply@wicketcloud.com';

            $first_name = $person->given_name ?? '';
            $last_name = $person->family_name ?? '';
            $subject = 'Welcome to ' . $organization_name;

            $body = "Hi $first_name, <br>
		 <p>You have been assigned a membership as part of $organization_name.</p>
		 <p>You will receive an account confirmation email from $confirmation_email_from, this will allow you to set your password and login for the first time.</p>
		 <p>Going forward you can visit <a href='$home_url'>$site_name</a> and login to complete your profile and access your resources.</p>
		 <br>
		 Thank you,
		 <br>
		 $organization_name";

            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $headers[] = 'From: ' . $organization_name . ' <no-reply@' . $base_domain . '>';

            $logger->debug('[OrgMan] Dispatching notification email', array_merge($context, [
                'recipient' => $to,
                'subject' => $subject,
            ]));

            $result = wp_mail($to, $subject, $body, $headers);

            if (!$result) {
                $logger->error('[OrgMan] Notification email wp_mail returned false', array_merge($context, [
                    'recipient' => $to,
                ]));

                return new WP_Error('email_failed', 'Failed to send assignment email.');
            }

            $logger->info('[OrgMan] Assignment email sent successfully', array_merge($context, [
                'recipient' => $to,
            ]));

            return true;

        } catch (\Exception $e) {
            $logger->error('[OrgMan] Assignment email exception', array_merge($context, [
                'exception' => $e->getMessage(),
            ]));

            return new WP_Error('email_exception', $e->getMessage());
        }
    }

    /**
     * Render notifications as legacy format (for backward compatibility).
     *
     * @return string HTML for legacy notices.
     */
    public function renderLegacy()
    {
        if (empty($this->notifications)) {
            return '';
        }

        $output = '';

        foreach ($this->notifications as $notification) {
            $class = 'notice notice-' . $notification['type'];
            $output .= sprintf(
                '<div class="%s"><p>%s</p></div>',
                esc_attr($class),
                wp_kses_post($notification['message'])
            );
        }

        return $output;
    }

    /**
     * Send email to person on group assignment.
     *
     * This method provides backward compatibility with the legacy function signature.
     * Handles different notification types including group assignment and representative changes.
     *
     * @param mixed $person_input Person UUID string or person object
     * @param array $data Additional data including notification_type, org_id, person_email, group_name, etc.
     * @return bool|WP_Error True if email sent successfully, false if not, WP_Error on failure.
     */
    public function emailToPersonOnGroupAssignment($person_input, array $data = [])
    {
        // Validate required parameters
        if (empty($person_input)) {
            return new WP_Error('invalid_params', 'Person input is required.');
        }

        if (empty($data) || !is_array($data)) {
            return new WP_Error('invalid_params', 'Data array is required.');
        }

        $notification_type = $data['notification_type'] ?? '';
        if (empty($notification_type)) {
            return new WP_Error('invalid_params', 'Notification type is required.');
        }

        $org_id = $data['org_id'] ?? '';
        if (empty($org_id)) {
            return new WP_Error('invalid_params', 'Organization ID is required.');
        }

        // Check for required dependencies
        if (!function_exists('wicket_get_organization') || !function_exists('wicket_get_person_by_id')) {
            return new WP_Error('missing_dependency', 'Wicket API functions are unavailable.');
        }

        try {
            // Resolve person object
            if (is_string($person_input)) {
                $person = wicket_get_person_by_id($person_input);
                if (!$person) {
                    return new WP_Error('person_not_found', 'Person not found.');
                }
            } elseif (is_object($person_input)) {
                $person = $person_input;
            } else {
                return new WP_Error('invalid_person_input', 'Invalid person input type.');
            }

            // Get organization data
            $org = wicket_get_organization($org_id);
            if (!$org) {
                return new WP_Error('organization_not_found', 'Organization not found.');
            }

            // Get email address
            $person_email = $data['person_email'] ?? '';
            if (empty($person_email)) {
                if (isset($person->primary_email_address) && !empty($person->primary_email_address)) {
                    $person_email = $person->primary_email_address;
                } else {
                    return new WP_Error('email_missing', 'Person email address is required.');
                }
            }

            // Validate email format
            if (!is_email($person_email)) {
                return new WP_Error('invalid_email', 'Invalid email address.');
            }

            // Get language
            $lang = wicket_get_current_language();

            // Get organization name
            $organization_name = $site_name = get_bloginfo('name');
            if (isset($org['data']['attributes']['legal_name_' . $lang]) && !empty($org['data']['attributes']['legal_name_' . $lang])) {
                $organization_name = $org['data']['attributes']['legal_name_' . $lang];
            }

            // Get URLs
            $home_url = get_home_url();
            $site_url = get_site_url();
            $base_domain = parse_url($site_url, PHP_URL_HOST);

            // Get person name
            $first_name = sanitize_text_field($person->given_name ?? '');
            $last_name = sanitize_text_field($person->family_name ?? '');

            // Get configuration for email
            $config = \WicketORM\Services\ConfigService::getConfig();
            $confirmation_email_from = $config['integrations']['notifications']['confirmation_email_from'] ?? 'no-reply@wicketcloud.com';

            // Generate email content based on notification type
            switch ($notification_type) {
                case 'group_assignment':
                    $subject = "Welcome to {$organization_name}";
                    $group_name = sanitize_text_field($data['group_name'] ?? '');
                    $body = "Hi {$first_name}, <br>
					 <p>You have been assigned a membership as part of {$organization_name}.</p>
					 <p>You will receive an account confirmation email from {$confirmation_email_from}, this will allow you to set your password and login for the first time.</p>
					 <p>Going forward you can visit <a href='{$home_url}'>{$site_name}</a> and login to complete your profile and access your resources.</p>
					 <br>
					 Thank you,
					 <br>
					 {$organization_name}";
                    break;

                case 'representative_change':
                    $subject = 'Your Representative Information Has Been Updated';
                    $body = "Hi {$first_name}, <br>
					 <p>Your representative information has been updated in the {$organization_name} organization.</p>
					 <p>Please log in to your account to review the changes.</p>
					 <p>Visit <a href='{$home_url}'>{$site_name}</a> to access your account.</p>
					 <br>
					 Thank you,
					 <br>
					 {$organization_name}";
                    break;

                default:
                    $subject = "Update from {$organization_name}";
                    $body = "Hi {$first_name}, <br>
					 <p>You have received an update from {$organization_name}.</p>
					 <p>Please log in to your account for more details.</p>
					 <p>Visit <a href='{$home_url}'>{$site_name}</a> to access your account.</p>
					 <br>
					 Thank you,
					 <br>
					 {$organization_name}";
                    break;
            }

            // Prepare email headers
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            if ($base_domain) {
                $headers[] = "From: {$organization_name} <no-reply@{$base_domain}>";
            }

            // Send email
            $to = sanitize_email($person_email);
            $result = wp_mail($to, $subject, $body, $headers);

            if (!$result) {
                return new WP_Error('email_failed', 'Failed to send group assignment email.');
            }

            return true;

        } catch (\Exception $e) {
            \Wicket()->log()->error('NotificationService::email_to_person_on_group_assignment() - Exception: ' . $e->getMessage(), ['source' => 'wicket-orgman']);

            return new WP_Error('email_exception', $e->getMessage());
        }
    }
}
