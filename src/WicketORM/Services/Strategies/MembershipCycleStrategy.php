<?php

/**
 * Membership Cycle Strategy for Roster Management.
 */

namespace WicketORM\Services\Strategies;

use WicketORM\Services\MembershipService;
use WicketORM\Services\OrganizationService;
use WicketORM\Services\PermissionService;
use WicketORM\Services\TouchpointService;
use WP_Error;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Implements cycle-scoped roster management where the target membership UUID is explicit.
 */
class MembershipCycleStrategy implements RosterManagementStrategy
{
    /**
     * @var DirectAssignmentStrategy|null
     */
    private $directStrategy = null;

    /**
     * @var MembershipService|null
     */
    private $membershipService = null;

    /**
     * @var OrganizationService|null
     */
    private $organizationService = null;

    /**
     * @var TouchpointService|null
     */
    private $touchpointService = null;

    /**
     * @var PermissionService|null
     */
    private $permissionService = null;

    /**
     * Add member via direct assignment, but scoped to explicit membership cycle.
     *
     * @param string $org_id
     * @param array  $member_data
     * @param array  $context
     * @return array|WP_Error
     */
    public function addMember($org_id, $member_data, $context = [])
    {
        $membership_uuid = $this->extractMembershipUuid($context);
        if ('' === $membership_uuid) {
            return new WP_Error('missing_membership_uuid', 'Membership UUID is required for membership_cycle strategy.');
        }

        if (!\WicketORM\Helpers\PermissionHelper::can_add_members($org_id)) {
            return new WP_Error('no_permission', 'You do not have permission to add members to this organization.');
        }

        $scope_valid = $this->validateMembershipScope($org_id, $membership_uuid);
        if (is_wp_error($scope_valid)) {
            return $scope_valid;
        }

        $context['membership_uuid'] = $membership_uuid;

        return $this->directStrategy()->addMember($org_id, $member_data, $context);
    }

    /**
     * Remove member by ending person membership assignment for the explicit cycle.
     *
     * @param string $org_id
     * @param string $person_uuid
     * @param array  $context
     * @return array|WP_Error
     */
    public function removeMember($org_id, $person_uuid, $context = [])
    {
        $membership_uuid = $this->extractMembershipUuid($context);
        if ('' === $membership_uuid) {
            return new WP_Error('missing_membership_uuid', 'Membership UUID is required for membership_cycle strategy.');
        }

        $person_membership_id = sanitize_text_field((string) ($context['person_membership_id'] ?? ''));
        if ('' === $person_membership_id) {
            return new WP_Error('missing_person_membership_id', 'Person membership ID is required.');
        }

        if (!\WicketORM\Helpers\PermissionHelper::can_remove_members($org_id)) {
            return new WP_Error('no_permission', 'You do not have permission to remove members from this organization.');
        }

        $scope_valid = $this->validateMembershipScope($org_id, $membership_uuid);
        if (is_wp_error($scope_valid)) {
            return $scope_valid;
        }

        $config = $this->configService()->getFullConfig();
        $cycle_config = is_array($config['membership']['cycle'] ?? null) ? $config['membership']['cycle'] : [];
        $access_permissions = is_array($config['access']['permissions'] ?? null) ? $config['access']['permissions'] : [];
        $prevent_owner_removal = (bool) ($cycle_config['prevent_owner_removal'] ?? true);
        $owner_must_have_membership_owner = (bool) ($access_permissions['owner_removal_requires_membership_owner_role'] ?? false);
        if ($prevent_owner_removal && !empty($org_id)) {
            $org_owner = $this->organizationService()->getOrganizationOwner($org_id);
            $owner_uuid = '';
            if (is_array($org_owner)) {
                $owner_uuid = $org_owner['id'] ?? ($org_owner['uuid'] ?? ($org_owner['data']['id'] ?? ''));
            } elseif (is_object($org_owner)) {
                $owner_uuid = $org_owner->id ?? ($org_owner->uuid ?? '');
            }

            $is_org_owner = !is_wp_error($org_owner)
                && $org_owner
                && !empty($owner_uuid)
                && (string) $owner_uuid === (string) $person_uuid;

            if ($is_org_owner) {
                $owner_role_match = true;
                if ($owner_must_have_membership_owner) {
                    $current_roles = $this->permissionService()->getPersonCurrentRolesByOrgId($person_uuid, $org_id);
                    $owner_role_match = is_array($current_roles) && in_array('membership_owner', $current_roles, true);
                }

                if ($owner_role_match) {
                    return new WP_Error('owner_removal_forbidden', 'The organization owner (Primary Member) cannot be removed.');
                }
            }
        }

        $remove_result = $this->membershipService()->endPersonMembershipToday($person_membership_id);
        if (is_wp_error($remove_result)) {
            return $remove_result;
        }

        if ($this->touchpointService()->isAvailable()) {
            try {
                $touchpoint_context = array_merge($context, [
                    'strategy' => 'membership_cycle',
                ]);
                $this->touchpointService()->logMemberRemoved($person_uuid, $org_id, $touchpoint_context);
            } catch (\Throwable $e) {
                // Do not fail removal on touchpoint issues.
            }
        }

        return ['status' => 'success', 'message' => 'Member removed successfully.'];
    }

    /**
     * Extract sanitized membership UUID from context.
     *
     * @param array $context
     * @return string
     */
    private function extractMembershipUuid(array $context): string
    {
        $membership_uuid = $context['membership_uuid'] ?? $context['membership_id'] ?? '';

        return sanitize_text_field((string) $membership_uuid);
    }

    /**
     * Validate membership UUID belongs to organization.
     *
     * @param string $org_id
     * @param string $membership_uuid
     * @return true|WP_Error
     */
    private function validateMembershipScope($org_id, string $membership_uuid)
    {
        $org_id = sanitize_text_field((string) $org_id);
        if ('' === $org_id) {
            return new WP_Error('invalid_org_id', 'Organization identifier is required.');
        }

        $membership_data = $this->membershipService()->getOrgMembershipData($membership_uuid);
        if (empty($membership_data) || !is_array($membership_data)) {
            return new WP_Error('invalid_membership_uuid', 'Membership UUID is invalid or unavailable.');
        }

        $membership_org_id = $membership_data['data']['relationships']['organization']['data']['id'] ?? '';
        if ('' !== $membership_org_id && $membership_org_id !== $org_id) {
            return new WP_Error('membership_org_mismatch', 'Membership does not belong to the selected organization.');
        }

        return true;
    }

    /**
     * Lazily instantiate direct strategy.
     *
     * @return DirectAssignmentStrategy
     */
    private function directStrategy(): DirectAssignmentStrategy
    {
        if (!isset($this->directStrategy)) {
            $this->directStrategy = new DirectAssignmentStrategy();
        }

        return $this->directStrategy;
    }

    /**
     * Lazily instantiate membership service.
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
     * Lazily instantiate organization service.
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
     * Lazily instantiate touchpoint service.
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
     * Lazily instantiate permission service.
     *
     * @return PermissionService
     */
    private function permissionService(): PermissionService
    {
        if (!isset($this->permissionService)) {
            $this->permissionService = new PermissionService();
        }

        return $this->permissionService;
    }
}
