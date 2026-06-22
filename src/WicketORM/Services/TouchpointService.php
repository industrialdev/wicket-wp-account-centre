<?php

namespace WicketORM\Services;

if (!defined('ABSPATH') && !defined('WICKET_DOING_TESTS')) {
    exit;
}

class TouchpointService
{
    private const SERVICE_NAME = 'Roster Manage';
    private const ACTION_MEMBER_ADDED = 'Organization member added';
    private const ACTION_MEMBER_REMOVED = 'Organization member removed';

    /**
     * Check whether the base-plugin touchpoint helpers are available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return function_exists('write_touchpoint') && function_exists('get_create_touchpoint_service_id');
    }

    /**
     * Create or resolve an MDP touchpoint service identifier.
     *
     * @param string $serviceName
     * @param string $serviceDescription
     * @param string $integrationType
     * @return string|false
     */
    public function getOrCreateServiceId(
        string $serviceName,
        string $serviceDescription = 'Custom from WP',
        string $integrationType = 'custom'
    ) {
        if (!$this->isAvailable()) {
            return false;
        }

        return get_create_touchpoint_service_id($serviceName, $serviceDescription, $integrationType);
    }

    /**
     * Write a touchpoint into the MDP through the base-plugin helpers.
     *
     * @param array  $params
     * @param string $serviceName
     * @param string $serviceDescription
     * @param string $integrationType
     * @return bool
     */
    public function write(
        array $params,
        string $serviceName,
        string $serviceDescription = 'Custom from WP',
        string $integrationType = 'custom'
    ): bool {
        if (!$this->isAvailable()) {
            return false;
        }

        $serviceId = $this->getOrCreateServiceId($serviceName, $serviceDescription, $integrationType);
        if (!is_string($serviceId) || $serviceId === '') {
            return false;
        }

        return (bool) write_touchpoint($params, $serviceId);
    }

    /**
     * Write the standard roster member-added touchpoint.
     *
     * @param string $personUuid
     * @param string $orgId
     * @param array  $memberData
     * @param array  $context
     * @return bool
     */
    public function logMemberAdded(string $personUuid, string $orgId, array $memberData = [], array $context = []): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $details = sprintf(
            "Person was added to organization %s on %s.\n\nPerson: %s %s\n\nEmail: %s\n\nID: %s",
            $this->getOrganizationName($context),
            gmdate('c'),
            sanitize_text_field((string) ($memberData['first_name'] ?? '')),
            sanitize_text_field((string) ($memberData['last_name'] ?? '')),
            sanitize_email((string) ($memberData['email'] ?? '')),
            $personUuid
        );

        return $this->write(
            $this->buildMemberTouchpointParams(self::ACTION_MEMBER_ADDED, $personUuid, $orgId, $details, $context),
            self::SERVICE_NAME,
            'Added member'
        );
    }

    /**
     * Write the standard roster member-removed touchpoint.
     *
     * @param string $personUuid
     * @param string $orgId
     * @param array  $context
     * @return bool
     */
    public function logMemberRemoved(string $personUuid, string $orgId, array $context = []): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $personData = $this->getPersonData($personUuid);
        $details = sprintf(
            "Person was removed from organization %s on %s.\n\nPerson: %s %s\n\nEmail: %s\n\nID: %s",
            $this->getOrganizationName($context),
            gmdate('c'),
            $personData['first_name'],
            $personData['last_name'],
            $personData['email'],
            $personUuid
        );

        return $this->write(
            $this->buildMemberTouchpointParams(self::ACTION_MEMBER_REMOVED, $personUuid, $orgId, $details, $context),
            self::SERVICE_NAME,
            'Removed member'
        );
    }

    /**
     * @param string $action
     * @param string $personUuid
     * @param string $orgId
     * @param string $details
     * @param array  $context
     * @return array
     */
    private function buildMemberTouchpointParams(
        string $action,
        string $personUuid,
        string $orgId,
        string $details,
        array $context = []
    ): array {
        $data = [
            'org_id' => sanitize_text_field($orgId),
        ];

        $strategy = sanitize_key((string) ($context['strategy'] ?? ''));
        if ($strategy !== '') {
            $data['strategy'] = $strategy;
        }

        $groupUuid = sanitize_text_field((string) ($context['group_uuid'] ?? ''));
        if ($groupUuid !== '') {
            $data['group_uuid'] = $groupUuid;
        }

        $membershipUuid = sanitize_text_field((string) ($context['membership_uuid'] ?? $context['membership_id'] ?? ''));
        if ($membershipUuid !== '') {
            $data['membership_uuid'] = $membershipUuid;
        }

        return [
            'person_id' => $personUuid,
            'action' => $action,
            'details' => $details,
            'data' => $data,
        ];
    }

    /**
     * @param array $context
     * @return string
     */
    private function getOrganizationName(array $context): string
    {
        return sanitize_text_field((string) ($context['org_name'] ?? ''));
    }

    /**
     * @param string $personUuid
     * @return array{first_name:string,last_name:string,email:string}
     */
    private function getPersonData(string $personUuid): array
    {
        if (!function_exists('wicket_get_person_by_id')) {
            return [
                'first_name' => '',
                'last_name' => '',
                'email' => '',
            ];
        }

        $person = wicket_get_person_by_id($personUuid);
        if (!$person) {
            return [
                'first_name' => '',
                'last_name' => '',
                'email' => '',
            ];
        }

        $first_name = '';
        $last_name = '';
        $email = '';

        if (is_array($person)) {
            $first_name = $person['given_name'] ?? ($person['attributes']['given_name'] ?? '');
            $last_name = $person['family_name'] ?? ($person['attributes']['family_name'] ?? '');
            $email = $person['primary_email_address'] ?? ($person['attributes']['primary_email_address'] ?? '');
        } elseif (is_object($person)) {
            $first_name = $person->given_name ?? '';
            $last_name = $person->family_name ?? '';
            $email = $person->primary_email_address ?? '';
        }

        return [
            'first_name' => sanitize_text_field((string) $first_name),
            'last_name' => sanitize_text_field((string) $last_name),
            'email' => sanitize_email((string) $email),
        ];
    }
}
