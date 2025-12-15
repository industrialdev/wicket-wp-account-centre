<?php

declare(strict_types=1);

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Handles Organization Roster functionalities.
 */
class OrganizationRoster
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Initialization logic can be added here.
    }

    /**
     * Returns the membership UUID for the current person within a specific organization.
     *
     * @param string $organizationUuid The organization UUID.
     *
     * @return string|false The membership UUID or false if not found.
     */
    public function getPersonMembershipByOrganization(string $organizationUuid = ''): string|false
    {
        if (empty($organizationUuid)) {
            return false;
        }

        $personUuid = WACC()->Mdp()->Person()->getCurrentPersonUuid();

        if (empty($personUuid)) {
            return false;
        }

        $memberships = WACC()->Mdp()->Person()->getPersonOrganizationMemberships($personUuid);

        if (empty($memberships['data']) || !is_array($memberships['data'])) {
            return false;
        }

        // Iterate over the memberships to find the one related to the given org UUID.
        foreach ($memberships['data'] as $membership) {
            if (
                isset($membership['relationships']['organization']['data']['id'])
                && $membership['relationships']['organization']['data']['id'] === $organizationUuid
            ) {
                // Found the membership UUID.
                return $membership['id'];
            }
        }

        return false;
    }
}
