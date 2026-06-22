<?php

/**
 * Permission Helper for Org Management.
 */

namespace WicketORM\Helpers;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Permission Helper class extending the base Helper.
 */
class PermissionHelper extends Helper
{
    /**
     * Normalize role name for comparison (lowercase, replace spaces with underscores).
     *
     * @param string $role The role name to normalize
     * @return string Normalized role name
     */
    private static function normalizeRoleName(string $role): string
    {
        return strtolower(str_replace(' ', '_', trim($role)));
    }

    /**
     * Build lookup array for role filtering.
     *
     * @param array $roles Roles to normalize into lookup map.
     * @return array<string, bool>
     */
    private static function build_role_lookup(array $roles): array
    {
        $lookup = [];

        foreach ($roles as $role) {
            $normalized = self::normalizeRoleName((string) $role);
            if ('' === $normalized) {
                continue;
            }
            $lookup[$normalized] = true;
        }

        return $lookup;
    }

    /**
     * Get role-only management access config.
     *
     * @return array
     */
    private static function get_role_only_management_access_config(): array
    {
        $config = \WicketORM\Services\ConfigService::getConfig();
        $permissions = is_array($config['access']['permissions'] ?? null) ? $config['access']['permissions'] : [];
        $role_only = $permissions['role_only_management_access'] ?? [];

        return is_array($role_only) ? $role_only : [];
    }

    /**
     * Get configured allow-list roles for role-only management access.
     *
     * @return array
     */
    private static function get_role_only_management_allowed_roles(): array
    {
        $role_only = self::get_role_only_management_access_config();
        $allowed_roles = is_array($role_only['allowed_roles'] ?? null)
            ? $role_only['allowed_roles']
            : [];

        return array_values(array_unique(array_filter(array_map(static function ($role): string {
            return self::normalizeRoleName((string) $role);
        }, $allowed_roles))));
    }

    /**
     * Determine if active-membership requirement can be bypassed for configured roles.
     *
     * @param string|null $org_id Organization ID.
     * @param array $required_roles Roles required by the target permission.
     * @return bool
     */
    private static function can_bypass_active_membership_requirement($org_id, array $required_roles): bool
    {
        if (!$org_id || !is_user_logged_in()) {
            return false;
        }

        $role_only = self::get_role_only_management_access_config();
        if (empty($role_only['enabled'])) {
            return false;
        }

        $allowed_roles = self::get_role_only_management_allowed_roles();
        $allowed_lookup = self::build_role_lookup($allowed_roles);
        if (empty($allowed_lookup)) {
            return false;
        }

        $required_lookup = self::build_role_lookup($required_roles);
        if (empty($required_lookup)) {
            return false;
        }

        $roles_to_check = array_keys(array_intersect_key($required_lookup, $allowed_lookup));
        if (empty($roles_to_check)) {
            return false;
        }

        return self::role_check($roles_to_check, $org_id, false);
    }

    /**
     * Determine if the user can access role-only organization-management surfaces.
     *
     * @param string|null $org_id Organization ID.
     * @return bool
     */
    private static function can_access_role_only_management_surface($org_id): bool
    {
        if (!$org_id || !is_user_logged_in()) {
            return false;
        }

        $role_only = self::get_role_only_management_access_config();
        if (empty($role_only['enabled'])) {
            return false;
        }

        $allowed_roles = self::get_role_only_management_allowed_roles();
        if (empty($allowed_roles)) {
            return false;
        }

        return self::role_check($allowed_roles, $org_id, false);
    }

    /**
     * Filter an associative array of role choices using allowed/excluded config.
     *
     * @param array $role_choices Associative array of slug => label.
     * @param array $allowed_roles Roles explicitly allowed (empty = all).
     * @param array $excluded_roles Roles to hide.
     * @return array Filtered associative array.
     */
    public static function filter_role_choices(array $role_choices, array $allowed_roles = [], array $excluded_roles = []): array
    {
        if (empty($role_choices)) {
            return $role_choices;
        }

        $allowed_lookup = self::build_role_lookup($allowed_roles);
        $excluded_lookup = self::build_role_lookup($excluded_roles);

        $filtered = [];
        foreach ($role_choices as $slug => $label) {
            $normalized = self::normalizeRoleName((string) $slug);

            if (!empty($allowed_lookup) && !isset($allowed_lookup[$normalized])) {
                continue;
            }

            if (!empty($excluded_lookup) && isset($excluded_lookup[$normalized])) {
                continue;
            }

            $filtered[$slug] = $label;
        }

        return $filtered;
    }

    /**
     * Filter submitted role values using allowed/excluded config.
     *
     * @param array $roles Array of submitted role slugs.
     * @param array $allowed_roles Roles explicitly allowed (empty = all).
     * @param array $excluded_roles Roles to hide.
     * @return array Filtered role array (re-indexed, unique).
     */
    public static function filter_role_submission(array $roles, array $allowed_roles = [], array $excluded_roles = []): array
    {
        if (empty($roles)) {
            return [];
        }

        $allowed_lookup = self::build_role_lookup($allowed_roles);
        $excluded_lookup = self::build_role_lookup($excluded_roles);

        $filtered = [];
        foreach ($roles as $role) {
            $normalized = self::normalizeRoleName((string) $role);

            if ('' === $normalized) {
                continue;
            }

            if (!empty($allowed_lookup) && !isset($allowed_lookup[$normalized])) {
                continue;
            }

            if (!empty($excluded_lookup) && isset($excluded_lookup[$normalized])) {
                continue;
            }

            $filtered[] = $role;
        }

        return array_values(array_unique($filtered));
    }

    /**
     * Check if current user has specific roles (org-scoped when $org_id is provided).
     *
     * This method combines WordPress role checking with organization-specific role checking.
     * Administrators automatically have all permissions.
     * Handles both role formats: "Org Editor" and "org_editor".
     *
     * @param array|string $roles Array or string of role slugs to check
     * @param string|null $org_id Organization ID for org-scoped role checking
     * @param bool $all_true Whether user must have ALL roles (true) or ANY role (false)
     * @return bool True if user has the required roles, false otherwise
     */
    public static function role_check($roles = [], $org_id = null, bool $all_true = false): bool
    {
        if (empty($roles)) {
            return false;
        }

        // Normalize roles to array and normalize each role name
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        $normalized_roles = array_map([self::class, 'normalizeRoleName'], $roles);

        // Get current user
        $user = wp_get_current_user();
        if (!is_user_logged_in()) {
            return false;
        }

        // Start with WordPress roles
        $wp_roles = (array) $user->roles;

        // Note: Removed admin bypass for organization-scoped permissions
        // Organization permissions should be based on organization roles, not WordPress admin role

        // Get organization roles if org_id is provided
        $org_roles = [];
        if ($org_id) {
            if (class_exists('\WicketORM\Services\PermissionService')) {
                $permissionService = new \WicketORM\Services\PermissionService();
                $org_roles = (array) $permissionService->getOrgRolesForPerson($user->user_login, $org_id);
            }
        }

        // Use org roles if available, otherwise use WP roles
        $check_roles = !empty($org_roles) ? $org_roles : $wp_roles;

        // Normalize all check roles for comparison
        $normalized_check_roles = array_map([self::class, 'normalizeRoleName'], $check_roles);

        // Check if user has required roles
        if ($all_true) {
            // User must have ALL specified roles
            foreach ($normalized_roles as $role) {
                if (!in_array($role, $normalized_check_roles, true)) {
                    return false;
                }
            }

            return true;
        } else {
            // User must have ANY of the specified roles
            foreach ($normalized_roles as $role) {
                if (in_array($role, $normalized_check_roles, true)) {
                    return true;
                }
            }

            return false;
        }
    }

    /**
     * Check if current user has an active membership for the organization.
     *
     * @param string|null $org_id Organization ID
     * @return bool True if user has active membership, false otherwise
     */
    public static function has_active_membership($org_id = null): bool
    {
        if (!$org_id || !is_user_logged_in()) {
            return false;
        }

        // Get person UUID from current user (user_login is the person UUID in Wicket)
        $person_uuid = wp_get_current_user()->user_login;

        if (empty($person_uuid)) {
            return false;
        }

        // Use wicket_get_person_active_memberships to support impersonation
        if (!function_exists('wicket_get_person_active_memberships')) {
            \Wicket()->log()->info('wicket_get_person_active_memberships function not available', [
                'source' => 'wicket-orgman',
                'org_id' => $org_id,
            ]);

            return false;
        }

        try {
            $memberships = wicket_get_person_active_memberships($person_uuid);

            $org_membership_ids = [];
            if (empty($memberships['included']) || !is_array($memberships['included'])) {
                // Don't return yet - check ownership and org-level roles below
            } else {
                foreach ($memberships['included'] as $included) {
                    if (
                        isset($included['type']) && $included['type'] === 'organization_memberships'
                        && isset($included['relationships']['organization']['data']['id'])
                    ) {
                        $org_membership_ids[] = $included['relationships']['organization']['data']['id'];

                        if ($included['relationships']['organization']['data']['id'] === $org_id) {
                            return true;
                        }
                    }
                }
            }

            // No direct membership found - check if this is a parent organization
            // with memberships inside, and user has org-level roles OR owns the membership

            // First check: user owns the organization membership
            if (class_exists('\WicketORM\Services\MembershipService')) {
                $membershipService = new \WicketORM\Services\MembershipService();
                $membership_uuid = $membershipService->getMembershipForOrganization($org_id);

                if (!empty($membership_uuid)) {
                    $membership_data = $membershipService->getOrgMembershipData($membership_uuid);

                    if (is_array($membership_data) && isset($membership_data['data']['relationships']['owner']['data']['id'])) {
                        $owner_id = $membership_data['data']['relationships']['owner']['data']['id'];

                        if ($owner_id === $person_uuid) {
                            return true;
                        }
                    }
                }
            }

            // Second check: user has any organization-level roles (not person-specific)
            if (class_exists('\WicketORM\Services\PermissionService')) {
                $permissionService = new \WicketORM\Services\PermissionService();
                $org_roles = $permissionService->getOrgRolesForPerson($person_uuid, $org_id);

                if (!empty($org_roles)) {
                    // User has org-level roles - check if org has any memberships
                    if (function_exists('wicket_get_org_memberships')) {
                        $org_memberships = wicket_get_org_memberships($org_id);

                        if (!empty($org_memberships) && is_array($org_memberships)) {
                            return true;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Log error but return false
            \Wicket()->log()->error('Error checking active membership: ' . $e->getMessage(), [
                'source' => 'wicket-orgman',
                'org_id' => $org_id,
                'person_uuid' => $person_uuid ?? 'unknown',
            ]);
        }

        return false;
    }

    /**
     * Check if current user can edit members in the specified organization.
     * Requires active membership + roles configured for managing members.
     *
     * @param string|null $org_id Organization ID
     * @return bool True if user can edit members, false otherwise
     */
    public static function can_edit_members($org_id = null): bool
    {
        $manage_roles = ConfigHelper::get_manage_members_roles();

        if (self::has_active_membership($org_id)) {
            return self::role_check($manage_roles, $org_id, false);
        }

        return self::can_bypass_active_membership_requirement($org_id, $manage_roles);
    }

    /**
     * Check if current user can add members to the specified organization.
     * Uses configurable permissions for adding members.
     *
     * @param string|null $org_id Organization ID
     * @return bool True if user can add members, false otherwise
     */
    public static function can_add_members($org_id = null): bool
    {
        $config = \WicketORM\Services\ConfigService::getConfig();
        $add_roles = $config['access']['permissions']['add_member_roles'] ?? ['membership_manager', 'membership_owner'];
        $roster_strategy = $config['membership']['strategy'] ?? 'direct';

        if ('membership_cycle' === $roster_strategy) {
            $cycle_roles = $config['membership']['cycle']['permissions']['add_member_roles'] ?? null;
            if (is_array($cycle_roles) && !empty($cycle_roles)) {
                $add_roles = $cycle_roles;
            }
        }

        if (self::has_active_membership($org_id)) {
            return self::role_check($add_roles, $org_id, false);
        }

        return self::can_bypass_active_membership_requirement($org_id, $add_roles);
    }

    /**
     * Check if current user can remove members from the specified organization.
     * Uses configurable permissions for removing members.
     *
     * @param string|null $org_id Organization ID
     * @return bool True if user can remove members, false otherwise
     */
    public static function can_remove_members($org_id = null): bool
    {
        $config = \WicketORM\Services\ConfigService::getConfig();
        $remove_roles = $config['access']['permissions']['remove_member_roles'] ?? ['membership_manager', 'membership_owner'];
        $roster_strategy = $config['membership']['strategy'] ?? 'direct';

        if ('membership_cycle' === $roster_strategy) {
            $cycle_roles = $config['membership']['cycle']['permissions']['remove_member_roles'] ?? null;
            if (is_array($cycle_roles) && !empty($cycle_roles)) {
                $remove_roles = $cycle_roles;
            }
        }

        if (self::has_active_membership($org_id)) {
            return self::role_check($remove_roles, $org_id, false);
        }

        return self::can_bypass_active_membership_requirement($org_id, $remove_roles);
    }

    /**
     * Check if current user can edit the organization.
     * Requires active membership + edit roles, or role-only management access when enabled.
     *
     * @param string|null $org_id Organization ID
     * @return bool True if user can edit organization, false otherwise
     */
    public static function can_edit_organization($org_id = null): bool
    {
        $edit_roles = ConfigHelper::get_edit_organization_roles();

        $has_active = self::has_active_membership($org_id);
        $has_roles = self::role_check($edit_roles, $org_id);

        if ($has_active && $has_roles) {
            return true;
        }

        $bypass = self::can_bypass_active_membership_requirement($org_id, $edit_roles);
        if ($bypass) {
            return true;
        }

        $role_only = self::can_access_role_only_management_surface($org_id);

        return $role_only;
    }

    /**
     * Check if current user has any management roles in the organization.
     *
     * @param string|null $org_id Organization ID
     * @return bool True if user has any management roles, false otherwise
     */
    public static function has_management_roles($org_id = null): bool
    {
        $management_roles = ConfigHelper::get_any_management_roles();

        return self::role_check($management_roles, $org_id, false);
    }

    /**
     * Check if current user can purchase additional seats.
     * Uses configurable permissions for purchasing seats.
     *
     * @param string|null $org_id Organization ID
     * @return bool True if user can purchase seats, false otherwise
     */
    public static function can_purchase_seats($org_id = null): bool
    {
        $config = \WicketORM\Services\ConfigService::getConfig();
        $purchase_roles = $config['access']['permissions']['purchase_seat_roles'] ?? ['membership_owner'];
        $roster_strategy = $config['membership']['strategy'] ?? 'direct';

        if ('membership_cycle' === $roster_strategy) {
            $cycle_roles = $config['membership']['cycle']['permissions']['purchase_seat_roles'] ?? null;
            if (is_array($cycle_roles) && !empty($cycle_roles)) {
                $purchase_roles = $cycle_roles;
            }
        }

        if (self::has_active_membership($org_id)) {
            return self::role_check($purchase_roles, $org_id, false);
        }

        return self::can_bypass_active_membership_requirement($org_id, $purchase_roles);
    }

    /**
     * Check if current user is a membership owner.
     * Requires the user to be marked as the owner in the organization membership + have the required role.
     *
     * @param string|null $org_id Organization ID
     * @return bool True if user is membership owner, false otherwise
     */
    public static function is_membership_owner($org_id = null): bool
    {
        $purchase_roles = ConfigHelper::get_purchase_seats_roles();

        return self::is_organization_membership_owner($org_id) && self::role_check($purchase_roles, $org_id);
    }

    /**
     * Check if current user is the owner of the organization membership.
     * This checks if the user is marked as the owner in the organization membership's owner relationship.
     *
     * @param string|null $org_id Organization ID
     * @return bool True if user is the membership owner, false otherwise
     */
    public static function is_organization_membership_owner($org_id = null): bool
    {
        if (!$org_id || !is_user_logged_in()) {
            return false;
        }

        // Get person UUID from current user (user_login is the person UUID in Wicket)
        $person_uuid = wp_get_current_user()->user_login;

        if (empty($person_uuid)) {
            return false;
        }

        try {
            // Get the organization membership to find the owner
            if (!class_exists('\WicketORM\Services\MembershipService')) {
                \Wicket()->log()->info('MembershipService class not available', [
                    'source' => 'wicket-orgman',
                    'org_id' => $org_id,
                ]);

                return false;
            }

            $membershipService = new \WicketORM\Services\MembershipService();
            $membership_uuid = $membershipService->getMembershipForOrganization($org_id);

            if (empty($membership_uuid)) {
                return false;
            }

            // Get the membership data to find the owner
            $membership_data = $membershipService->getOrgMembershipData($membership_uuid);

            if (!is_array($membership_data) || !isset($membership_data['data']['relationships']['owner']['data']['id'])) {
                return false;
            }

            $owner_id = $membership_data['data']['relationships']['owner']['data']['id'];

            return $person_uuid === $owner_id;

        } catch (\Throwable $e) {
            \Wicket()->log()->error('Error checking membership owner: ' . $e->getMessage(), [
                'source' => 'wicket-orgman',
                'org_id' => $org_id,
                'person_uuid' => $person_uuid ?? 'unknown',
            ]);
        }

        return false;
    }

    /**
     * Check if current user is a membership manager.
     * Requires active membership + roles configured for managing members.
     *
     * @param string|null $org_id Organization ID
     * @return bool True if user is membership manager, false otherwise
     */
    public static function is_membership_manager($org_id = null): bool
    {
        $manage_roles = ConfigHelper::get_manage_members_roles();

        $has_active = self::has_active_membership($org_id);

        if ($has_active) {
            $has_roles = self::role_check($manage_roles, $org_id, false);

            return $has_roles;
        }

        $bypass = self::can_bypass_active_membership_requirement($org_id, $manage_roles);

        return $bypass;
    }

    /**
     * Get current user's roles for a specific organization.
     *
     * @param string|null $org_id Organization ID
     * @return array Array of role slugs
     */
    public static function get_user_org_roles($org_id = null): array
    {
        if (!$org_id || !is_user_logged_in()) {
            return [];
        }

        if (class_exists('\WicketORM\Services\PermissionService')) {
            $permissionService = new \WicketORM\Services\PermissionService();
            $roles = $permissionService->getOrgRolesForPerson(wp_get_current_user()->user_login, $org_id);

            return is_array($roles) ? $roles : [];
        }

        return [];
    }

    /**
     * Format roles for display (Title Case, underscores to spaces).
     *
     * @param array $roles Array of role slugs
     * @return array Formatted role names
     */
    public static function format_roles_for_display(array $roles): array
    {
        return array_filter(array_map(function ($role) {
            return ucwords(str_replace('_', ' ', (string) $role));
        }, $roles));
    }

    // ------------------------------------------------------------------
    // Contacts Roster Permission Helpers
    // New methods. Do not modify existing methods above.
    // ------------------------------------------------------------------

    /**
     * Can current user manage contacts (add/remove) for this org.
     * Checks contacts.permissions.can_add config.
     * Does NOT require active membership.
     *
     * @param string|null $org_uuid Organization UUID.
     * @return bool
     */
    public static function can_manage_contacts(?string $org_uuid): bool
    {
        $config = \WicketORM\Services\ConfigService::getConfig();
        $contacts_config = $config['contacts'] ?? [];

        if (empty($contacts_config['enabled'])) {
            return false;
        }

        $can_add_roles = $contacts_config['permissions']['can_add'] ?? [];

        // Contacts are relationship-only: no active membership required
        return self::role_check($can_add_roles, $org_uuid, false);
    }

    /**
     * Can current user view contact list for this org.
     * Checks contacts.permissions.can_view config.
     * Does NOT require active membership.
     *
     * @param string|null $org_uuid Organization UUID.
     * @return bool
     */
    public static function can_view_contacts(?string $org_uuid): bool
    {
        $config = \WicketORM\Services\ConfigService::getConfig();
        $contacts_config = $config['contacts'] ?? [];

        if (empty($contacts_config['enabled'])) {
            return false;
        }

        $can_view_roles = $contacts_config['permissions']['can_view'] ?? [];

        return self::role_check($can_view_roles, $org_uuid, false);
    }

    /**
     * Is the person a contact (has any roster relationship type) for this org.
     *
     * @param string $person_uuid Person UUID.
     * @param string $org_uuid    Organization UUID.
     * @return bool
     */
    public static function is_contact(string $person_uuid, string $org_uuid): bool
    {
        $contactService = new \WicketORM\Services\ContactService();
        $rels = $contactService->getPersonContactRelationships($person_uuid, $org_uuid);

        return !empty($rels);
    }

    /**
     * Is contact removal prevented for this person.
     * Uses OrganizationService::getOrganizationOwner() to check if person is the
     * org owner (same pattern as remove-member.php).
     *
     * @param string $person_uuid Person UUID.
     * @param string $org_uuid    Organization UUID.
     * @return bool True if removal should be prevented.
     */
    public static function is_contact_removal_prevented(string $person_uuid, string $org_uuid): bool
    {
        $organizationService = new \WicketORM\Services\OrganizationService();
        $org_owner = $organizationService->getOrganizationOwner($org_uuid);

        if (is_wp_error($org_owner) || !$org_owner) {
            return false;
        }

        return isset($org_owner->uuid) && (string) $org_owner->uuid === $person_uuid;
    }
}
