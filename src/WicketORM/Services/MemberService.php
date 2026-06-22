<?php

/**
 * Member Model for handling member data.
 */

namespace WicketORM\Services;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles data operations for organization members.
 */
class MemberService
{
    /**
     * @var PermissionService|null
     */
    private $permissionService = null;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var array
     */
    private $config;

    /**
     * @var MembershipRosterReader
     */
    private MembershipRosterReader $reader;

    /**
     * @var MembershipRosterWriter
     */
    protected MembershipRosterWriter $writer;

    /**
     * @var CacheService|null
     */
    private ?CacheService $cacheService = null;

    /**
     * Constructor.
     *
     * @param ConfigService $configService
     */
    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
        $this->config = $this->configService->getFullConfig();
        $this->reader = new MembershipRosterReader($configService);
        $configRef = &$this->config;
        $this->writer = new MembershipRosterWriter($configService, $this->reader, $configRef);
    }

    /**
     * Lazily instantiate CacheService.
     *
     * @return CacheService
     */
    private function cacheService(): CacheService
    {
        if (!isset($this->cacheService)) {
            $this->cacheService = new CacheService();
        }

        return $this->cacheService;
    }

    /**
     * Add a member to an organization.
     *
     * @param string $org_id The organization ID.
     * @param array  $member_data Data for the new member.
     * @return array|\WP_Error
     */
    public function addMember($org_id, $member_data, $context = [])
    {
        return $this->writer->addMember($org_id, $member_data, $context);
    }

    /**
     * Remove a member from an organization.
     *
     * @param string $org_id The organization ID.
     * @param string $person_uuid The UUID of the person to remove.
     * @param array  $context Additional context for the operation.
     * @return array|\WP_Error
     */
    public function removeMember($org_id, $person_uuid, $context = [])
    {
        return $this->writer->removeMember($org_id, $person_uuid, $context);
    }

    /**
     * Check if a user has the required roles within an organization.
     *
     * @param string $person_uuid The user's UUID.
     * @param string $org_id The organization ID.
     * @param array  $roles The roles to check for.
     * @return bool True if the user has at least one of the roles, false otherwise.
     */
    public function hasRole($person_uuid, $org_id, $roles)
    {
        return $this->personHasOrgRoles($person_uuid, $roles, $org_id, false);
    }

    /**
     * Check if a person has specific roles within an organization.
     *
     * @param string       $person_uuid The UUID of the person
     * @param array|string $roles The roles to check. Can be a string or an array of roles.
     * @param string       $org_id The organization ID
     * @param bool         $all_true Default: false. If true, all roles must be in the user's roles. If false, at least one role must be in the user's roles.
     * @return bool True if condition met, false if not.
     */
    public function personHasOrgRoles($person_uuid, $roles, $org_id, $all_true = false)
    {
        if (empty($person_uuid) || empty($roles) || empty($org_id)) {
            return false;
        }

        // Get current person roles for the organization
        $current_roles = $this->permissionService()->getPersonCurrentRolesByOrgId($person_uuid, $org_id);

        if (!is_array($current_roles) || empty($current_roles)) {
            return false;
        }

        // Normalize roles to array
        if (!is_array($roles)) {
            if (str_contains($roles, ',')) {
                $roles = explode(',', $roles);
            } else {
                $roles = [$roles];
            }
        }

        // Sanitize roles
        $roles = array_map('sanitize_key', array_filter($roles));

        if (empty($roles)) {
            return false;
        }

        if ($all_true) {
            // All roles must be present
            foreach ($roles as $role) {
                if (!in_array($role, $current_roles, true)) {
                    return false;
                }
            }

            return true;
        } else {
            // At least one role must be present
            foreach ($roles as $role) {
                if (in_array($role, $current_roles, true)) {
                    return true;
                }
            }

            return false;
        }
    }

    /**
     * Lazily instantiate PermissionService.
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

    /**
     * Retrieve organization membership members via legacy helper.
     *
     * @param string $membershipUuid Membership identifier.
     * @param array  $args           Optional arguments (page, size, query).
     * @return array|null
     */
    public function getMembershipMembers(string $membershipUuid, array $args = []): ?array
    {
        return $this->reader->getMembershipMembers($membershipUuid, $args);
    }

    public function clearMembersCache(string $membershipUuid): void
    {
        $this->reader->clearMembersCache($membershipUuid);
    }

    public function getMembers(string $membershipUuid, string $orgUuid, array $args = [], bool $lazy = false): array
    {
        $page = max(1, (int) ($args['page'] ?? 1));
        $size = max(1, (int) ($args['size'] ?? 15));
        $query = isset($args['query']) ? sanitize_text_field((string) $args['query']) : '';

        $membersResponse = $this->getMembershipMembers(
            $membershipUuid,
            [
                'page'  => $page,
                'size'  => $size,
                'query' => $query ?: null,
                'lazy'  => $lazy,
            ]
        );

        $result = $this->reader->prepareMembersResult(
            $membersResponse,
            [
                'org_uuid'        => $orgUuid,
                'membership_uuid' => $membershipUuid,
                'page'            => $page,
                'size'            => $size,
                'query'           => $query,
                'lazy'            => $lazy,
            ]
        );

        // Pre-warm lazy-details cache for each member when full data is available.
        if (!$lazy && !empty($result['members'])) {
            $cacheService = $this->cacheService();
            $gen = $cacheService->getMembershipGeneration($membershipUuid);
            foreach ($result['members'] as $member) {
                $personUuid = $member['person_uuid'] ?? '';
                if ($personUuid !== '') {
                    $lazyCacheKey = 'orgman_lazy_details_' . md5($personUuid . $orgUuid . $membershipUuid . $gen);
                    $cacheService->set($lazyCacheKey, $member);
                }
            }
        }

        return $result;
    }

    public function getMemberByPersonUuid(string $personUuid, string $membershipUuid, string $orgUuid): ?array
    {
        return $this->reader->getMemberByPersonUuid($personUuid, $membershipUuid, $orgUuid);
    }

    public function getGroupMembers(string $group_uuid, string $org_identifier, array $args = []): array
    {
        $page = max(1, (int) ($args['page'] ?? 1));
        $size = max(1, (int) ($args['size'] ?? 15));
        $query = isset($args['query']) ? sanitize_text_field((string) $args['query']) : '';
        $org_uuid = isset($args['org_uuid']) ? sanitize_text_field((string) $args['org_uuid']) : '';

        $group_service = new GroupService();

        return $group_service->getGroupMembers($group_uuid, $org_identifier, [
            'page' => $page,
            'size' => $size,
            'query' => $query,
            'org_uuid' => $org_uuid,
        ]);
    }

    /**
     * Search members with pagination support.
     *
     * @param string $membershipUuid Organization membership identifier.
     * @param string $orgUuid        Organization identifier.
     * @param string $search         Search term.
     * @param array  $args           Optional arguments (page, size).
     * @return array
     */
    public function searchMembers(string $membershipUuid, string $orgUuid, string $search, array $args = []): array
    {
        $args['query'] = $search;

        return $this->getMembers($membershipUuid, $orgUuid, $args);
    }

    public function getPersonCurrentRolesByOrgId($personUuid, $orgUuid)
    {
        return $this->reader->getPersonCurrentRolesByOrgId($personUuid, $orgUuid);
    }

    public function getFormattedRolesString($personUuid, $orgUuid)
    {
        return $this->reader->getFormattedRolesString($personUuid, $orgUuid);
    }

    public function isCurrentUserConfirmed(): bool
    {
        return $this->isUserConfirmed();
    }

    /**
     * Check if user is confirmed by UUID (alias for isUserConfirmed with current user fallback).
     *
     * @param string|null $personUuid The person UUID. If null or empty, checks current user.
     * @return bool True if the user is confirmed, false if not or if user not found
     */
    public function checkUserConfirmation(?string $personUuid = null): bool
    {
        return $this->isUserConfirmed($personUuid);
    }

    /**
     * Get person data by UUID using the Wicket API.
     *
     * @param string $personUuid The person UUID
     * @return array|null Person data or null on failure
     */
    public function getPersonById($personUuid)
    {
        return $this->reader->getPersonById($personUuid);
    }

    public function updateMemberRoles($personUuid, $orgUuid, $membershipUuid, $roles)
    {
        return $this->writer->updateMemberRoles($personUuid, $orgUuid, $membershipUuid, $roles);
    }

    public function updateMemberRelationship($personUuid, $orgUuid, $relationshipType)
    {
        return $this->writer->updateMemberRelationship($personUuid, $orgUuid, $relationshipType);
    }

    public function updateMemberDescription($personUuid, $orgUuid, $description)
    {
        return $this->writer->updateMemberDescription($personUuid, $orgUuid, $description);
    }
}
