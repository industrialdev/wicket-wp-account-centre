<?php

/**
 * Direct Assignment Strategy for Roster Management.
 */

namespace WicketORM\Services\Strategies;

use WicketORM\Services\ConfigService;
use WicketORM\Services\ConnectionService;
use WicketORM\Services\MembershipService;
use WicketORM\Services\OrganizationService;
use WicketORM\Services\PermissionService;
use WicketORM\Services\PersonService;
use WicketORM\Services\TouchpointService;
use WP_Error;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Implements the direct assignment mode for roster management.
 */
class DirectAssignmentStrategy implements RosterManagementStrategy
{
    /**
     * @var MembershipService|null
     */
    private $membershipService = null;

    /**
     * @var PersonService|null
     */
    private $personService = null;

    /**
     * @var ConnectionService|null
     */
    private $connectionService = null;

    /**
     * @var PermissionService|null
     */
    private $permissionService = null;

    /**
     * @var OrganizationService|null
     */
    private $organizationService = null;

    /**
     * @var ConfigService|null
     */
    private $configService = null;

    /**
     * @var \WC_Logger|null
     */
    private $logger = null;

    /**
     * @var TouchpointService|null
     */
    private $touchpointService = null;

    public function addMember($org_id, $member_data, $context = [])
    {
        $logger = $this->getLogger();
        $log_context = [
            'source' => 'wicket-orgman',
            'strategy' => 'direct',
            'org_id' => $org_id,
            'member_email' => $member_data['email'] ?? null,
        ];

        try {
            $logger->info('Direct strategy add_member invoked', $log_context);

            $person_uuid = $this->personService()->createOrUpdatePerson($member_data);
            if (is_wp_error($person_uuid)) {
                $logger->error('Failed to create/update person for member addition', array_merge($log_context, [
                    'error' => $person_uuid->get_error_message(),
                ]));

                return $person_uuid;
            }
            $log_context['person_uuid'] = $person_uuid;
            $logger->debug('Person record ready for membership assignment', $log_context);

            // Get configuration for member addition settings
            $config = $this->configService()->getFullConfig();
            $base_member_role = $config['member_management']['addition']['base_member_role'] ?? 'member';
            $auto_assign_roles = $config['member_management']['addition']['auto_assign_roles'] ?? [];

            // Use relationship type from context if provided, otherwise use config default
            $relationship_type = !empty($context['relationship_type'])
                ? $context['relationship_type']
                : ($config['relationships']['addition']['type'] ?? 'position');
            $relationship_description = $context['relationship_description'] ?? $member_data['relationship_description'] ?? '';
            $relationship_description = is_string($relationship_description) ? sanitize_textarea_field($relationship_description) : '';

            // Map custom relationship types to Wicket API types if needed
            $custom_types = $config['relationships']['labels']['custom'] ?? [];
            if (isset($custom_types[$relationship_type])) {
                // This is a custom relationship type - we'll use it as-is
                // Future enhancement: map to actual Wicket relationship types if needed
            }

            $membership_uuid = $this->resolveMembershipUuid($org_id, $context);
            if (is_wp_error($membership_uuid)) {
                $logger->error('Unable to resolve membership UUID for organization', array_merge($log_context, [
                    'error' => $membership_uuid->get_error_message(),
                ]));

                return $membership_uuid;
            }
            $log_context['membership_uuid'] = $membership_uuid;

            $has_membership = $this->connectionService()->personHasMembership($person_uuid, $membership_uuid);
            if (is_wp_error($has_membership)) {
                return $has_membership;
            }

            if (!$has_membership) {
                $has_relationship = $this->connectionService()->personHasRelationship($person_uuid, $org_id);
                if (is_wp_error($has_relationship)) {
                    return $has_relationship;
                }

                if (!$has_relationship) {
                    $connection_payload = $this->connectionService()->buildConnectionPayload(
                        $person_uuid,
                        $org_id,
                        'person_to_organization',
                        $relationship_type,
                        $relationship_description
                    );

                    $response_connection = $this->connectionService()->createConnection($connection_payload);

                    if (is_wp_error($response_connection)) {
                        return new WP_Error(
                            'connection_creation_failed',
                            $response_connection->get_error_message() ?? 'Failed to add employee connection'
                        );
                    }
                }

                // Assign person to membership seat to give them active membership
                $membership_assignment_result = $this->assignPersonToMembershipSeat($person_uuid, $membership_uuid);
                if (is_wp_error($membership_assignment_result)) {
                    $logger->error('Membership assignment failed', array_merge($log_context, [
                        'error' => $membership_assignment_result->get_error_message(),
                    ]));

                    return $membership_assignment_result;
                }
                $logger->info('Assigned person to membership seat', $log_context);
            } else {
                $logger->warning('Person already has membership; rejecting duplicate add', $log_context);
                $email = $member_data['email'] ?? '';

                return new WP_Error(
                    'member_already_exists',
                    $email !== ''
                        ? sprintf('A member with email %s already exists in this organization.', $email)
                        : 'This person is already a member of this organization.'
                );
            }

            // Assign base member role from config
            $role_result = $this->assignRole($person_uuid, $base_member_role, $org_id);
            if (is_wp_error($role_result)) {
                $logger->error('Base member role assignment failed', array_merge($log_context, [
                    'role' => $base_member_role,
                    'error' => $role_result->get_error_message(),
                ]));

                return $role_result;
            }
            $logger->debug('Base member role assigned', array_merge($log_context, [
                'role' => $base_member_role,
            ]));

            // Assign site-specific auto-roles from config
            if (!empty($auto_assign_roles)) {
                $logger->debug('Assigning configured auto roles', array_merge($log_context, [
                    'auto_roles' => $auto_assign_roles,
                ]));

                $auto_roles_result = $this->assignAdditionalRoles($person_uuid, $org_id, $auto_assign_roles);
                if (is_wp_error($auto_roles_result)) {
                    $logger->error('Auto-role assignment failed', array_merge($log_context, [
                        'roles' => $auto_assign_roles,
                        'error' => $auto_roles_result->get_error_message(),
                    ]));

                    return $auto_roles_result;
                }
            }

            // Assign any additional roles from the form
            $additional_roles = $context['roles'] ?? $member_data['roles'] ?? [];
            $additional_result = $this->assignAdditionalRoles($person_uuid, $org_id, $additional_roles);
            if (is_wp_error($additional_result)) {
                $logger->error('Additional role assignment failed', array_merge($log_context, [
                    'roles' => $additional_roles,
                    'error' => $additional_result->get_error_message(),
                ]));

                return $additional_result;
            }
            if (!empty($additional_roles)) {
                $logger->debug('Additional roles assigned', array_merge($log_context, [
                    'roles' => $additional_roles,
                ]));
            }

            $this->logTouchpoint($person_uuid, $org_id, $member_data, $context);
            $logger->debug('Touchpoint logged for member addition', $log_context);

            $email_result = $this->sendAssignmentEmail($person_uuid, $org_id, [
                'fallback_email' => $member_data['email'] ?? null,
            ]);
            if (is_wp_error($email_result)) {
                // Do not block success on email failures; log for observability.
                $logger->error('Failed to send assignment email', array_merge($log_context, [
                    'error' => $email_result->get_error_message(),
                ]));
            } else {
                $logger->info('Assignment email dispatched', $log_context);
            }

            $logger->info('Member added successfully via direct strategy', $log_context);

            return [
                'status'      => 'success',
                'message'     => 'Member added successfully.',
                'person_uuid' => $person_uuid,
            ];

        } catch (\Exception $e) {
            $logger->error('Direct strategy add_member threw exception', array_merge($log_context, [
                'exception' => $e->getMessage(),
            ]));

            return new WP_Error('add_member_exception', $e->getMessage());
        }
    }

    /**
     * Lazily instantiate MembershipService.
     *
     * @return MembershipService
     */
    private function membershipService(): MembershipService
    {
        if (!isset($this->membershipService)) {
            $this->membershipService = new MembershipService();
        }

        return $this->membershipService;
    }

    /**
     * Lazily instantiate PersonService.
     *
     * @return PersonService
     */
    private function personService(): PersonService
    {
        if (!isset($this->personService)) {
            $this->personService = new PersonService();
        }

        return $this->personService;
    }

    /**
     * Lazily instantiate ConnectionService.
     *
     * @return ConnectionService
     */
    private function connectionService(): ConnectionService
    {
        if (!isset($this->connectionService)) {
            $this->connectionService = new ConnectionService();
        }

        return $this->connectionService;
    }

    /**
     * Lazily instantiate TouchpointService.
     *
     * @return TouchpointService
     */
    private function touchpointService(): TouchpointService
    {
        if (!isset($this->touchpointService)) {
            $this->touchpointService = new TouchpointService();
        }

        return $this->touchpointService;
    }

    /**
     * Resolve the membership UUID for an organization.
     *
     * @param string $org_id
     * @param array  $context
     * @return string|WP_Error
     */
    private function resolveMembershipUuid($org_id, array $context = [])
    {
        $org_id = sanitize_text_field((string) $org_id);
        if ('' === $org_id) {
            return new WP_Error('invalid_org_id', 'Organization identifier is required.');
        }

        $context_membership_uuid = sanitize_text_field((string) ($context['membership_uuid'] ?? $context['membership_id'] ?? ''));
        if ('' !== $context_membership_uuid) {
            $membership_data = $this->membershipService()->getOrgMembershipData($context_membership_uuid);
            if (empty($membership_data) || !is_array($membership_data)) {
                return new WP_Error('invalid_membership_uuid', 'Membership UUID is invalid or unavailable.');
            }

            $membership_org_id = $membership_data['data']['relationships']['organization']['data']['id'] ?? '';
            if ('' !== $membership_org_id && $membership_org_id !== $org_id) {
                return new WP_Error('membership_org_mismatch', 'Membership does not belong to the selected organization.');
            }

            return $context_membership_uuid;
        }

        $membership_uuid = $this->membershipService()->getOrganizationMembershipUuid($org_id);

        if (empty($membership_uuid)) {
            return new WP_Error('no_membership', 'Could not find a valid corporate membership for this organization.');
        }

        return $membership_uuid;
    }

    /**
     * Assign additional roles, if any.
     *
     * @param string       $person_uuid
     * @param string       $org_id
     * @param array|string $roles
     * @return true|WP_Error
     */
    private function assignAdditionalRoles(string $person_uuid, string $org_id, $roles)
    {
        $roles = $this->normalizeRoles($roles);

        // Filter out membership_owner if configured to prevent assignment
        $config = $this->configService()->getFullConfig();
        if (!empty($config['access']['permissions']['prevent_owner_assignment'])) {
            $roles = array_values(array_diff($roles, ['membership_owner']));
        }

        if (empty($roles)) {
            return true;
        }

        foreach ($roles as $role) {
            $result = $this->assignRole($person_uuid, $role, $org_id);
            if (is_wp_error($result)) {
                $this->getLogger()->error('Additional role assignment failed', [
                    'source' => 'wicket-orgman',
                    'strategy' => 'direct',
                    'person_uuid' => $person_uuid,
                    'org_id' => $org_id,
                    'role' => $role,
                    'error' => $result->get_error_message(),
                ]);

                return $result;
            }
        }

        return true;
    }

    /**
     * Normalize role input to an array of unique role slugs.
     *
     * @param array|string $roles
     * @return array
     */
    private function normalizeRoles($roles): array
    {
        if (is_string($roles)) {
            $roles = array_map('trim', explode(',', $roles));
        }

        if (!is_array($roles)) {
            return [];
        }

        $sanitized = [];
        foreach ($roles as $role) {
            $role = sanitize_key((string) $role);
            if ('' !== $role && 'member' !== $role) {
                $sanitized[] = $role;
            }
        }

        return array_values(array_unique($sanitized));
    }

    /**
     * Assign person to membership seat using Wicket API.
     *
     * @param string $person_uuid
     * @param string $membership_uuid
     * @return true|WP_Error
     */
    private function assignPersonToMembershipSeat(string $person_uuid, string $membership_uuid)
    {
        $logger = $this->getLogger();
        $context = [
            'source' => 'wicket-orgman',
            'strategy' => 'direct',
            'person_uuid' => $person_uuid,
            'membership_uuid' => $membership_uuid,
        ];

        if (!function_exists('wicket_assign_person_to_org_membership')) {
            $logger->error('Membership assignment helper missing', $context);

            return new WP_Error('missing_dependency', 'Membership assignment helper is unavailable.');
        }

        try {
            // Get organization membership data to pass to the assignment function
            $membership_data = $this->membershipService()->getOrgMembershipData($membership_uuid);
            if (is_wp_error($membership_data)) {
                $logger->error('Membership data lookup returned WP_Error', array_merge($context, [
                    'error' => $membership_data->get_error_message(),
                ]));

                return $membership_data;
            }

            if (empty($membership_data) || empty($membership_data['data'])) {
                $logger->error('Membership data missing payload', $context);

                return new WP_Error('membership_data_missing', 'Membership details unavailable.');
            }

            // Extract membership type ID from relationships
            $membership_type_id = $membership_data['data']['relationships']['membership']['data']['id'] ?? '';

            // Fallback: inspect included resources for memberships/membership_types entries
            if (empty($membership_type_id) && !empty($membership_data['included']) && is_array($membership_data['included'])) {
                foreach ($membership_data['included'] as $included) {
                    $included_type = $included['type'] ?? '';
                    if (in_array($included_type, ['memberships', 'membership', 'membership_types'], true)) {
                        $membership_type_id = $included['id'] ?? '';
                        if (!empty($membership_type_id)) {
                            break;
                        }
                    }
                }
            }

            if (empty($membership_type_id)) {
                $logger->error('Membership type ID missing in membership data', $context);

                return new WP_Error('membership_type_missing', 'Could not find membership type ID.');
            }

            // Pass membership data as array; the helper expects array access
            $result = wicket_assign_person_to_org_membership(
                $person_uuid,        // person ID
                $membership_type_id, // membership type ID
                $membership_uuid,    // organization membership ID
                $membership_data     // organization membership data
            );

            // Check if the API call was successful
            if (empty($result) || isset($result['errors'])) {
                $error_message = $result['errors'][0]['detail'] ?? 'Failed to assign person to membership seat.';
                $logger->warning('Membership assignment API returned error, verifying existing membership', array_merge($context, [
                    'membership_type_id' => $membership_type_id,
                    'api_error' => $error_message,
                ]));

                $post_check = $this->connectionService()->personHasMembership($person_uuid, $membership_uuid);
                if (true === $post_check) {
                    $logger->info('Membership assignment already present after API error', $context);

                    return true;
                }

                if (is_wp_error($post_check)) {
                    $logger->error('Membership verification after API error failed', array_merge($context, [
                        'verification_error' => $post_check->get_error_message(),
                    ]));
                }

                return new WP_Error('membership_assignment_failed', $error_message);
            }

            $logger->info('Membership assignment API succeeded', array_merge($context, [
                'membership_type_id' => $membership_type_id,
            ]));

            return true;

        } catch (\Throwable $e) {
            $logger->error('Membership assignment threw exception', array_merge($context, [
                'exception' => $e->getMessage(),
            ]));

            return new WP_Error('membership_assignment_exception', $e->getMessage());
        }
    }

    /**
     * Assign a single role through Wicket.
     *
     * @param string $person_uuid
     * @param string $role
     * @param string $org_id
     * @return true|WP_Error
     */
    private function assignRole(string $person_uuid, string $role, string $org_id)
    {
        $role = sanitize_key($role);
        if ('' === $role) {
            return new WP_Error('invalid_role', 'Role is required.');
        }

        if (!function_exists('wicket_assign_role')) {
            return new WP_Error('missing_dependency', 'Role assignment helper is unavailable.');
        }

        $permission_service = $this->permissionService();
        if ($permission_service instanceof PermissionService) {
            $current_roles = $permission_service->getPersonCurrentRolesByOrgId($person_uuid, $org_id);
            if (in_array($role, $current_roles, true)) {
                return true;
            }
        }

        try {
            $result = wicket_assign_role($person_uuid, $role, $org_id);
        } catch (\Throwable $e) {
            return new WP_Error('role_assignment_failed', $e->getMessage());
        }

        if (false === $result) {
            return new WP_Error('role_assignment_failed', sprintf('Failed assigning role %s.', $role));
        }

        return true;
    }

    /**
     * Lazily instantiate PermissionService.
     *
     * @return PermissionService|null
     */
    private function permissionService(): ?PermissionService
    {
        if (isset($this->permissionService)) {
            return $this->permissionService;
        }

        if (class_exists(PermissionService::class)) {
            $this->permissionService = new PermissionService();
        }

        return $this->permissionService;
    }

    /**
     * Lazily instantiate OrganizationService.
     *
     * @return OrganizationService
     */
    private function organizationService(): OrganizationService
    {
        if (!isset($this->organizationService)) {
            $this->organizationService = new OrganizationService();
        }

        return $this->organizationService;
    }

    /**
     * Lazily instantiate ConfigService.
     *
     * @return ConfigService
     */
    private function configService(): ConfigService
    {
        if (!isset($this->configService)) {
            $this->configService = new ConfigService();
        }

        return $this->configService;
    }

    /**
     * Log a touchpoint to track member addition.
     *
     * @param string $person_uuid
     * @param string $org_id
     * @param array  $member_data
     * @param array  $context
     * @return void
     */
    private function logTouchpoint(string $person_uuid, string $org_id, array $member_data, array $context): void
    {
        if (!$this->touchpointService()->isAvailable()) {
            return;
        }

        try {
            $context['strategy'] = 'direct';
            $this->touchpointService()->logMemberAdded($person_uuid, $org_id, $member_data, $context);
        } catch (\Throwable $e) {
            \Wicket()->log()->error('Failed to write touchpoint: ' . $e->getMessage(), ['source' => 'wicket-orgman']);
        }
    }

    /**
     * Log a touchpoint to track member removal.
     *
     * @param string $person_uuid
     * @param string $org_id
     * @param array  $context
     * @return void
     */
    private function logRemovalTouchpoint(string $person_uuid, string $org_id, array $context): void
    {
        if (!$this->touchpointService()->isAvailable()) {
            return;
        }

        try {
            $context['strategy'] = 'direct';
            $this->touchpointService()->logMemberRemoved($person_uuid, $org_id, $context);
        } catch (\Throwable $e) {
            \Wicket()->log()->error('Failed to write removal touchpoint: ' . $e->getMessage(), ['source' => 'wicket-orgman']);
        }
    }

    /**
     * Dispatch assignment email notification.
     *
     * @param string $person_uuid
     * @param string $org_id
     * @param array  $options {
     *     @type string|null $fallback_email Optional email address captured from the form.
     * }
     * @return true|WP_Error
     */
    private function sendAssignmentEmail(string $person_uuid, string $org_id, array $options = [])
    {
        $logger = $this->getLogger();
        $context = [
            'source' => 'wicket-orgman',
            'strategy' => 'direct',
            'org_id' => $org_id,
            'person_uuid' => $person_uuid,
        ];
        $fallback_email = isset($options['fallback_email']) ? sanitize_email((string) $options['fallback_email']) : '';

        if (!function_exists('wicket_get_organization') || !function_exists('wicket_get_person_by_id')) {
            $logger->error('Email notification dependencies missing', $context);

            return new WP_Error('missing_dependency', 'Email notification dependencies are unavailable.');
        }

        $logger->debug('Preparing assignment email payload', $context);
        $org = wicket_get_organization($org_id);
        $person = wicket_get_person_by_id($person_uuid);
        $home_url = home_url();
        $site_url = get_site_url();
        $site_name = get_bloginfo('name');
        $base_domain = wp_parse_url($site_url, PHP_URL_HOST);

        if ($base_domain === 'localhost') {
            $base_domain = 'localhost.com';
        }

        $to = '';
        if (is_array($person)) {
            $to = $person['primary_email_address'] ?? ($person['attributes']['primary_email_address'] ?? '');
        } elseif (is_object($person)) {
            $to = $person->primary_email_address ?? '';
        }

        if (empty($to) && !empty($fallback_email)) {
            $to = $fallback_email;
            $logger->warning('Assignment email falling back to provided email', array_merge($context, [
                'fallback_email' => $fallback_email,
            ]));
        }

        if (empty($to)) {
            $logger->error('Assignment email aborted: missing person email', $context);

            return new WP_Error('person_email_missing', 'Unable to determine person email address.');
        }

        $lang = wicket_get_current_language();
        $organization_name = $site_name;

        if ($org && isset($org['data']['attributes'])) {
            $attributes = $org['data']['attributes'];
            $name_key = 'legal_name_' . $lang;
            if (isset($attributes[$name_key]) && '' !== $attributes[$name_key]) {
                $organization_name = $attributes[$name_key];
            }
        }

        $to = sanitize_email($to);
        $first_name = '';
        if (is_array($person)) {
            $first_name = $person['given_name'] ?? ($person['attributes']['given_name'] ?? '');
        } elseif (is_object($person)) {
            $first_name = $person->given_name ?? '';
        }
        $first_name = sanitize_text_field($first_name);
        $subject = sprintf('Welcome to %s', $organization_name);

        // Get configuration for email
        $config = $this->configService()->getFullConfig();
        $confirmation_email_from = $config['integrations']['notifications']['confirmation_email_from'] ?? 'no-reply@wicketcloud.com';

        $body = sprintf(
            "Hi %s,<br>
            <p>You have been assigned a membership as part of %s.</p>
            <p>You will receive an account confirmation email from %s, this will allow you to set your password and login for the first time.</p>
            <p>Going forward you can visit <a href='%s'>%s</a> and login to complete your profile and access your resources.</p>
            <br>
            Thank you,<br>
            %s",
            esc_html($first_name),
            esc_html($organization_name),
            esc_html($confirmation_email_from),
            esc_url($home_url),
            esc_html($site_name),
            esc_html($organization_name)
        );

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        if ($base_domain) {
            $headers[] = sprintf('From: %s <no-reply@%s>', $organization_name, $base_domain);
        }

        $logger->debug('Dispatching assignment email', array_merge($context, [
            'recipient' => $to,
            'subject' => $subject,
        ]));

        $sent = wp_mail($to, $subject, $body, $headers);

        if (!$sent) {
            $logger->error('Assignment email send failed', array_merge($context, [
                'recipient' => $to,
            ]));

            return new WP_Error('email_failed', 'Failed to send assignment email.');
        }

        $logger->info('Assignment email sent', array_merge($context, [
            'recipient' => $to,
        ]));

        return true;
    }

    /**
     * Retrieve shared logger.
     *
     * @return \WC_Logger
     */
    private function getLogger()
    {
        if (null === $this->logger) {
            $this->logger = \Wicket()->log();
        }

        return $this->logger;
    }

    public function removeMember($org_id, $person_uuid, $context = [])
    {
        try {
            $required_functions = [];
            foreach ($required_functions as $func) {
                if (!function_exists($func)) {
                    return new WP_Error('missing_function', "Legacy function {$func} not found.");
                }
            }

            // Direct mode ends connections and roles; it does not read person_membership_id.
            // The value is intentionally not captured: connection-only removals are valid here.

            $config = $this->configService()->getFullConfig();
            $preserve_relationship = (bool) ($config['member_management']['removal']['direct']['preserve_relationship'] ?? false);
            $prevent_owner_removal = (bool) ($config['access']['permissions']['prevent_owner_removal'] ?? false);
            $owner_must_have_membership_owner = (bool) ($config['access']['permissions']['owner_removal_requires_membership_owner_role'] ?? false);

            $owner_guard = \WicketORM\Helpers\PermissionHelper::guardOwnerRemoval(
                $org_id,
                $person_uuid,
                $prevent_owner_removal,
                $owner_must_have_membership_owner,
                $this->organizationService(),
                $this->permissionService()
            );
            if (is_wp_error($owner_guard)) {
                return $owner_guard;
            }

            if (!$preserve_relationship) {
                $connection_ids = [];
                if (isset($context['connection_id'])) {
                    $raw_connection_ids = is_string($context['connection_id'])
                        ? explode(',', $context['connection_id'])
                        : [(string) $context['connection_id']];
                    $connection_ids = array_values(array_filter(array_map('trim', $raw_connection_ids)));
                }

                if (empty($connection_ids)) {
                    $connections = $this->connectionService()->getPersonConnectionsById($person_uuid);
                    if (is_array($connections) && !empty($connections['data'])) {
                        foreach ($connections['data'] as $connection) {
                            $connection_org_id = (string) ($connection['relationships']['organization']['data']['id'] ?? '');
                            $connection_id = (string) ($connection['id'] ?? '');
                            $connection_active = (bool) ($connection['attributes']['active'] ?? false);

                            if ($connection_org_id !== (string) $org_id || $connection_id === '' || !$connection_active) {
                                continue;
                            }

                            $connection_ids[] = $connection_id;
                        }
                    }
                }

                foreach (array_values(array_unique($connection_ids)) as $connection_id) {
                    $result = $this->connectionService()->endRelationshipAtActionTime($person_uuid, $connection_id, $org_id);
                    if (is_wp_error($result)) {
                        return $result;
                    }
                }
            }

            // Remove all org-scoped roles
            if (function_exists('wicket_remove_role')) {
                $roles_to_remove = $this->permissionService()->getPersonCurrentRolesByOrgId($person_uuid, $org_id);
                if (!empty($roles_to_remove)) {
                    foreach ($roles_to_remove as $role) {
                        wicket_remove_role($person_uuid, $role, $org_id);
                    }
                }
            }

            $this->logRemovalTouchpoint($person_uuid, $org_id, $context);

            return ['status' => 'success', 'message' => 'Member removed successfully.'];

        } catch (\Exception $e) {
            return new WP_Error('remove_member_exception', $e->getMessage());
        }
    }
}
