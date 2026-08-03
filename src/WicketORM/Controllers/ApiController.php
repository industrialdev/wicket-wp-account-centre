<?php

/**
 * Base Controller for the REST API.
 */

namespace WicketORM\Controllers;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract base class for API controllers.
 */
abstract class ApiController
{
    /**
     * The namespace for the API routes.
     *
     * @var string
     */
    protected $namespace = 'org-management/v1';

    /**
     * Register the routes for this controller.
     */
    abstract public function registerRoutes();

    /**
     * Check if a user has the required capability.
     *
     * @param \WP_REST_Request $request The request object.
     * @return bool|\WP_Error True if the user has the capability, WP_Error otherwise.
     */
    public function checkPermission(\WP_REST_Request $request)
    {
        $org_id = sanitize_text_field($request->get_param('org_id'));

        // Check if user has permission to manage this specific organization
        $has_permission = $this->userCanManageOrganization($org_id);

        if (!$has_permission) {
            return new \WP_Error(
                'rest_forbidden',
                esc_html__('You do not have permission to access this endpoint.', 'wicket-acc'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Simple permission check - just verify user is logged in.
     * Uses WordPress built-in is_user_logged_in function as string callback.
     *
     * @param \WP_REST_Request $request The request object.
     * @return bool|\WP_Error True if the user has the capability, WP_Error otherwise.
     */
    public function checkLoggedIn(\WP_REST_Request $request)
    {
        // Use the string callback approach for cleaner implementation
        return is_user_logged_in();
    }

    /**
     * Check if current user can manage the specified organization.
     *
     * @param string $org_id The organization ID to check.
     * @return bool True if user can manage the organization, false otherwise.
     */
    protected function userCanManageOrganization($org_id)
    {
        if (empty($org_id)) {
            return false;
        }

        // Get current user
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return false;
        }

        // Administrators can manage any organization
        if (in_array('administrator', $user->roles)) {
            return true;
        }

        // Check if user has organization-specific roles
        $org_roles = $this->getOrgRolesForPerson($user->user_login, $org_id);

        if (!empty($org_roles)) {
            // Define the roles that allow business information management
            // These match the roles used in the legacy system
            $allowed_roles = [
                'owner',
                'org_editor',
                'membership_manager',
            ];

            // Check if user has any of the allowed roles for this organization
            foreach ($allowed_roles as $allowed_role) {
                if (in_array($allowed_role, $org_roles)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get organization-specific roles for a person.
     *
     * @param string $person_uuid The person UUID.
     * @param string $org_id The organization ID.
     * @return array Array of role names.
     */
    protected function getOrgRolesForPerson($person_uuid, $org_id)
    {
        if (empty($person_uuid) || empty($org_id)) {
            return [];
        }

        // Use PermissionService directly
        if (class_exists('\WicketORM\Services\PermissionService')) {
            $permissionService = new \WicketORM\Services\PermissionService();
            $org_roles = $permissionService->getOrgRolesForPerson($person_uuid, $org_id);

            return is_array($org_roles) ? $org_roles : [];
        }

        // Fallback implementation if PermissionService is not available
        return [];
    }

    /**
     * Standardized JSON success response.
     *
     * @param mixed $data The data to return.
     * @param int   $status The HTTP status code.
     * @return \WP_REST_Response
     */
    protected function successResponse($data, $status = 200)
    {
        return $this->htmlResponse(['success' => true, 'data' => $data], $status);
    }

    /**
     * Standardized JSON error response.
     *
     * @param string $error_code A custom error code.
     * @param string $error_message The error message.
     * @param int    $status The HTTP status code.
     * @return \WP_REST_Response
     */
    protected function error($error_code, $error_message, $status = 400)
    {
        return $this->htmlResponse(['success' => false, 'error' => ['code' => $error_code, 'message' => $error_message]], $status);
    }
}
