<?php

declare(strict_types=1);

namespace WicketAcc\MdpApi;

use WP_User;

// No direct access
defined('ABSPATH') || exit;

/**
 * Handles MDP Notification related functionality.
 */
class Notification extends Init
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Send an email to a user about their team assignment by an organization manager.
     *
     * @param WP_User $user The WordPress user object.
     * @param string $orgUuid The UUID of the organization.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public function sendPersonToTeamAssignmentEmail(WP_User $user, string $orgUuid): bool
    {
        // Fetch organization details, including emails
        $org = WACC()->MdpApi->Organization->getOrganizationByUuid($orgUuid, 'emails');
        if (empty($org['data'])) {
            WACC()->Log->error('Failed to retrieve organization data for notification.', [
                'source' => __METHOD__,
                'orgUuid' => $orgUuid,
            ]);

            return false;
        }

        // Fetch person details using the user's login, which is the person UUID
        $person = WACC()->MdpApi->Person->getPersonByUuid($user->user_login);
        if (empty($person)) {
            WACC()->Log->error('Failed to retrieve person data for notification.', [
                'source' => __METHOD__,
                'personUuid' => $user->user_login,
            ]);

            return false;
        }

        // Determine language for localized content
        $lang = WACC()->Language->getCurrentLanguage();
        $organizationName = $org['data']['attributes']["legal_name_{$lang}"] ?? $org['data']['attributes']['legal_name'] ?? 'your organization';

        // Prepare email content
        $to = $person->primary_email_address;
        $firstName = $person->given_name;
        $subject = sprintf(__('Welcome to %s!', 'wicket-acc'), $organizationName);

        $body = sprintf(
            __('Hi %1$s, <br><br>You have been assigned a membership as part of %2$s.<br><br>Visit our website and login to complete your profile and explore your member benefits.<br><br>Thank you,<br><br>%2$s', 'wicket-acc'),
            $firstName,
            $organizationName
        );

        $fromEmail = $this->getOrganizationPrimaryEmail($org);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$organizationName} <{$fromEmail}>",
        ];

        // Send the email using WordPress's mail function
        $sent = wp_mail($to, $subject, $body, $headers);

        if (!$sent) {
            WACC()->Log->error('Failed to send team assignment email.', [
                'source' => __METHOD__,
                'to' => $to,
                'personUuid' => $user->user_login,
                'orgUuid' => $orgUuid,
            ]);
        }

        return $sent;
    }

    /**
     * Send an email to a NEW user about their team assignment.
     *
     * This is intended for users who have just been created and do not yet have a full person record.
     *
     * @param string $firstName The user's first name.
     * @param string $lastName The user's last name.
     * @param string $email The user's email address.
     * @param string $orgUuid The UUID of the organization.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public function sendNewPersonToTeamAssignmentEmail(string $firstName, string $lastName, string $email, string $orgUuid): bool
    {
        // Fetch organization details, including emails
        $org = WACC()->MdpApi->Organization->getOrganizationByUuid($orgUuid, 'emails');
        if (empty($org['data'])) {
            WACC()->Log->error('Failed to retrieve organization data for new person notification.', [
                'source' => __METHOD__,
                'orgUuid' => $orgUuid,
            ]);

            return false;
        }

        // Determine language for localized content
        $lang = WACC()->Language->getCurrentLanguage();
        $organizationName = $org['data']['attributes']["legal_name_{$lang}"] ?? $org['data']['attributes']['legal_name'] ?? 'your organization';

        // Prepare email content
        $to = $email;
        $subject = sprintf(__('Welcome to %s!', 'wicket-acc'), $organizationName);

        $body = sprintf(
            __('Hi %1$s, <br><br>You have been assigned a membership as part of %2$s.<br><br>You will soon receive an Account Confirmation email with instructions on how to finalize your login account.<br>Once you have confirmed your account, visit njbia.org and login to complete your profile and explore your member benefits.<br><br>Thank you,<br><br>%2$s', 'wicket-acc'),
            $firstName,
            $organizationName
        );

        $fromEmail = $this->getOrganizationPrimaryEmail($org);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$organizationName} <{$fromEmail}>",
        ];

        // Send the email using WordPress's mail function
        $sent = wp_mail($to, $subject, $body, $headers);

        if (!$sent) {
            WACC()->Log->error('Failed to send new person team assignment email.', [
                'source' => __METHOD__,
                'to' => $to,
                'orgUuid' => $orgUuid,
            ]);
        }

        return $sent;
    }

    /**
     * Get the primary email address for an organization from the API response.
     *
     * @param array $orgData The organization data from the API, including included resources.
     * @return string The primary email address or a default fallback.
     */
    private function getOrganizationPrimaryEmail(array $orgData): string
    {
        if (!empty($orgData['included'])) {
            foreach ($orgData['included'] as $resource) {
                if ($resource['type'] === 'emails' && !empty($resource['attributes']['is_primary'])) {
                    return $resource['attributes']['address'];
                }
            }
        }

        return 'no-reply@wicketcloud.com'; // Default fallback
    }

    /**
     * Send an email for a new membership pending approval.
     *
     * @param string $email The recipient's email address.
     * @param string $membershipLink The link to the membership approval page.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public function sendApprovalRequiredEmail(string $email, string $membershipLink): bool
    {
        $to = $email;
        $subject = __('Membership Pending Approval', 'wicket-acc');
        $body = sprintf(
            __('You have a membership pending approval.<br>Please use the following link to process the membership request.<br><a href="%1$s">%1$s</a>', 'wicket-acc'),
            esc_url($membershipLink)
        );

        $fromEmail = apply_filters('wicket_approval_email_from', get_bloginfo('admin_email'));
        $fromName = get_bloginfo('name');

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$fromName} <{$fromEmail}>",
        ];

        $sent = wp_mail($to, $subject, $body, $headers);

        if (!$sent) {
            WACC()->Log->error('Failed to send membership approval required email.', [
                'source' => __METHOD__,
                'to' => $to,
            ]);
        }

        return $sent;
    }
}
