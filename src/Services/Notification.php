<?php

declare(strict_types=1);

namespace WicketAcc\Services;

use WP_User;

// No direct access
defined('ABSPATH') || exit;

/**
 * Handles MDP Notification related functionality.
 */
class Notification
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        WACC()->Notification = $this;
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
        $org = WACC()->Mdp()->Organization()->getOrganizationByUuid($orgUuid, 'emails');
        if (empty($org['data'])) {
            WACC()->Log()->error('Failed to retrieve organization data for notification.', [
                'source' => __CLASS__,
                'orgUuid' => $orgUuid,
            ]);

            return false;
        }

        // Fetch person details using the user's login, which is the person UUID
        $person = WACC()->Mdp()->Person()->getPersonByUuid($user->user_login);
        if (empty($person)) {
            WACC()->Log()->error('Failed to retrieve person data for notification.', [
                'source' => __CLASS__,
                'personUuid' => $user->user_login,
            ]);

            return false;
        }

        // Determine language for localized content
        $lang = WACC()->Language()->getCurrentLanguage();
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
            WACC()->Log()->error('Failed to send team assignment email.', [
                'source' => __CLASS__,
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
        $org = WACC()->Mdp()->Organization()->getOrganizationByUuid($orgUuid, 'emails');
        if (empty($org['data'])) {
            WACC()->Log()->error('Failed to retrieve organization data for new person notification.', [
                'source' => __CLASS__,
                'orgUuid' => $orgUuid,
            ]);

            return false;
        }

        // Determine language for localized content
        $lang = WACC()->Language()->getCurrentLanguage();
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
            WACC()->Log()->error('Failed to send new person team assignment email.', [
                'source' => __CLASS__,
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
            WACC()->Log()->error('Failed to send membership approval required email.', [
                'source' => __CLASS__,
                'to' => $to,
            ]);
        }

        return $sent;
    }

    /**
     * Sends an email to a person assigned to an organization with instructions on how to access their team profile.
     *
     * @param string $personUuid The person's UUID.
     * @param string $orgUuid The organization's UUID for branding.
     * @return void
     */
    public function sendPersonToOrgAssignmentEmail(string $personUuid, string $orgUuid): void
    {
        $lang = WACC()->Language()->getCurrentLanguage();
        $person = WACC()->Mdp()->Person()->getPerson($personUuid);
        $org = WACC()->Mdp()->Organization()->getOrganizationInfo($orgUuid);

        if (!$person || !$org) {
            WACC()->Log()->error('Failed to send assignment email: Invalid person or organization.', [
                'source' => __CLASS__,
                'personUuid' => $personUuid,
                'orgUuid' => $orgUuid,
            ]);

            return;
        }

        $home_url = get_home_url();
        $site_name = get_bloginfo('name');
        $base_domain = parse_url(get_site_url(), PHP_URL_HOST);
        $organization_name = $org['legal_name'] ?? $site_name;

        $to = $person['primary_email_address'] ?? '';
        if (empty($to)) {
            WACC()->Log()->error('Failed to send assignment email: Person has no primary email address.', [
                'source' => __CLASS__,
                'personUuid' => $personUuid,
            ]);

            return;
        }

        $first_name = $person['given_name'] ?? '';
        $subject = 'Welcome to ' . $organization_name;

        $body = "Hi $first_name, <br>
<p>You have been assigned a membership as part of $organization_name.</p>
<p>You will receive an account confirmation email from phca@wicketcloud.com, this will allow you to set your password and login for the first time.</p>
<p>Going forward you can visit <a href='$home_url'>$site_name</a> and login to complete your profile and access your resources.</p>
<br>
Thank you,
<br>
$organization_name";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $headers[] = 'From: ' . $organization_name . ' <no-reply@' . $base_domain . '>';

        wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Sends an email notification to the organization owner.
     *
     * @param array $emailData {
     *     Required. An array of email data.
     *     @type string $orgUuid The organization UUID.
     *     @type string $subject The email subject.
     *     @type string $body The email body.
     *     @type string $lang The language code.
     *     @type string $to (Optional) The recipient email address. Defaults to org's main email.
     * }
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public function sendEmailNotification(array $emailData): bool
    {
        $requiredKeys = ['orgUuid', 'subject', 'body', 'lang'];
        foreach ($requiredKeys as $key) {
            if (empty($emailData[$key])) {
                WACC()->Log()->error("Email notification missing required data: `{$key}`.", [
                    'source' => __CLASS__,
                    'emailData' => $emailData,
                ]);

                return false;
            }
        }

        $orgInfo = WACC()->Mdp()->Organization()->getOrganizationInfoExtended($emailData['orgUuid'], $emailData['lang']);

        if (!$orgInfo) {
            WACC()->Log()->error('Failed to send notification: Invalid organization.', [
                'source' => __CLASS__,
                'orgUuid' => $emailData['orgUuid'],
            ]);

            return false;
        }

        $to = $emailData['to'] ?? $orgInfo['org_meta']['main_email']['address'] ?? '';
        if (empty($to)) {
            WACC()->Log()->error('Failed to send notification: No recipient email address found.', [
                'source' => __CLASS__,
                'orgUuid' => $emailData['orgUuid'],
            ]);

            return false;
        }

        $subject = sanitize_text_field($emailData['subject']);
        $body = $emailData['body'];
        $base_domain = parse_url(get_site_url(), PHP_URL_HOST);
        $from_name = $orgInfo['legal_name'] ?? get_bloginfo('name');

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $headers[] = 'From: ' . $from_name . ' <no-reply@' . $base_domain . '>';

        return wp_mail($to, $subject, $body, $headers);
    }
}
