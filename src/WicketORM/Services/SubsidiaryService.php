<?php

/**
 * Subsidiary management service.
 */

namespace WicketORM\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles subsidiary operations for organization management.
 */
class SubsidiaryService
{
    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * Constructor.
     *
     * @param ConfigService $configService
     */
    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Get subsidiaries for an organization.
     *
     * @param string $org_id Organization UUID.
     * @return array|WP_Error Array of subsidiaries or WP_Error on failure.
     */
    public function getSubsidiaries($org_id)
    {
        if (empty($org_id)) {
            return new WP_Error('missing_org_id', __('Organization ID is required.', 'wicket-acc'));
        }

        $subsidiaries = [];

        // Get subsidiaries from MDP if available
        if (function_exists('wicket_get_organization')) {
            $org = wicket_get_organization($org_id);
            if (isset($org['data']['relationships']['subsidiaries']['data'])) {
                $subsidiary_data = $org['data']['relationships']['subsidiaries']['data'];

                foreach ($subsidiary_data as $subsidiary) {
                    if ($subsidiary['type'] === 'organizations') {
                        $subsidiary_info = wicket_get_organization($subsidiary['id']);
                        if ($subsidiary_info && !is_wp_error($subsidiary_info)) {
                            $subsidiaries[] = $this->formatSubsidiaryData($subsidiary_info);
                        }
                    }
                }
            }
        }

        return $subsidiaries;
    }

    /**
     * Add a subsidiary to an organization.
     *
     * @param string $parent_org_id Parent organization UUID.
     * @param string $subsidiary_org_id Subsidiary organization UUID.
     * @return array|WP_Error Result data or WP_Error on failure.
     */
    public function addSubsidiary($parent_org_id, $subsidiary_org_id)
    {
        if (empty($parent_org_id)) {
            return new WP_Error('missing_parent_org_id', __('Parent organization ID is required.', 'wicket-acc'));
        }

        if (empty($subsidiary_org_id)) {
            return new WP_Error('missing_subsidiary_org_id', __('Subsidiary organization ID is required.', 'wicket-acc'));
        }

        // Validate that both organizations exist
        if (!$this->validateOrganizationExists($parent_org_id)) {
            return new WP_Error('parent_org_not_found', __('Parent organization not found.', 'wicket-acc'));
        }

        if (!$this->validateOrganizationExists($subsidiary_org_id)) {
            return new WP_Error('subsidiary_org_not_found', __('Subsidiary organization not found.', 'wicket-acc'));
        }

        // Check if relationship already exists
        if ($this->isSubsidiaryRelationship($parent_org_id, $subsidiary_org_id)) {
            return new WP_Error('relationship_exists', __('This subsidiary relationship already exists.', 'wicket-acc'));
        }

        // Create the relationship using MDP if available
        if (function_exists('wicket_create_relationship')) {
            $result = wicket_create_relationship(
                'organizations',
                $parent_org_id,
                'subsidiaries',
                'organizations',
                $subsidiary_org_id
            );

            if (is_wp_error($result)) {
                return $result;
            }
        }

        return [
            'success' => true,
            'message' => __('Subsidiary added successfully.', 'wicket-acc'),
            'parent_org_id' => $parent_org_id,
            'subsidiary_org_id' => $subsidiary_org_id,
        ];
    }

    /**
     * Remove a subsidiary from an organization.
     *
     * @param string $parent_org_id Parent organization UUID.
     * @param string $subsidiary_org_id Subsidiary organization UUID.
     * @return array|WP_Error Result data or WP_Error on failure.
     */
    public function removeSubsidiary($parent_org_id, $subsidiary_org_id)
    {
        if (empty($parent_org_id)) {
            return new WP_Error('missing_parent_org_id', __('Parent organization ID is required.', 'wicket-acc'));
        }

        if (empty($subsidiary_org_id)) {
            return new WP_Error('missing_subsidiary_org_id', __('Subsidiary organization ID is required.', 'wicket-acc'));
        }

        // Check if relationship exists
        if (!$this->isSubsidiaryRelationship($parent_org_id, $subsidiary_org_id)) {
            return new WP_Error('relationship_not_found', __('Subsidiary relationship not found.', 'wicket-acc'));
        }

        // Remove the relationship using MDP if available
        if (function_exists('wicket_delete_relationship')) {
            $result = wicket_delete_relationship(
                'organizations',
                $parent_org_id,
                'subsidiaries',
                'organizations',
                $subsidiary_org_id
            );

            if (is_wp_error($result)) {
                return $result;
            }
        }

        return [
            'success' => true,
            'message' => __('Subsidiary removed successfully.', 'wicket-acc'),
            'parent_org_id' => $parent_org_id,
            'subsidiary_org_id' => $subsidiary_org_id,
        ];
    }

    /**
     * Search for organizations that can be added as subsidiaries.
     *
     * @param string $search_term Search term.
     * @param string $current_org_id Current organization ID to exclude from results.
     * @return array Array of searchable organizations.
     */
    public function searchSubsidiaryCandidates($search_term, $current_org_id)
    {
        $candidates = [];

        // Use MDP to search for organizations if available
        if (function_exists('wicket_search_organizations')) {
            $search_results = wicket_search_organizations($search_term);

            if (!is_wp_error($search_results) && isset($search_results['data'])) {
                foreach ($search_results['data'] as $org) {
                    // Exclude the current organization and its existing subsidiaries
                    if ($org['id'] !== $current_org_id && !$this->isSubsidiaryRelationship($current_org_id, $org['id'])) {
                        $candidates[] = $this->formatSubsidiaryData(['data' => $org]);
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * Check if an organization exists.
     *
     * @param string $org_id Organization UUID.
     * @return bool True if organization exists.
     */
    private function validateOrganizationExists($org_id)
    {
        if (!function_exists('wicket_get_organization')) {
            return false;
        }

        $org = wicket_get_organization($org_id);

        return !is_wp_error($org) && isset($org['data']);
    }

    /**
     * Check if a subsidiary relationship exists.
     *
     * @param string $parent_org_id Parent organization UUID.
     * @param string $subsidiary_org_id Subsidiary organization UUID.
     * @return bool True if relationship exists.
     */
    private function isSubsidiaryRelationship($parent_org_id, $subsidiary_org_id)
    {
        $subsidiaries = $this->getSubsidiaries($parent_org_id);

        foreach ($subsidiaries as $subsidiary) {
            if ($subsidiary['id'] === $subsidiary_org_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format subsidiary data for consistent output.
     *
     * @param array $org_data Raw organization data from MDP.
     * @return array Formatted subsidiary data.
     */
    private function formatSubsidiaryData($org_data)
    {
        if (!isset($org_data['data'])) {
            return [];
        }

        $attributes = $org_data['data']['attributes'] ?? [];

        return [
            'id' => $org_data['data']['id'],
            'name' => $attributes['name'] ?? '',
            'type' => $attributes['type'] ?? '',
            'status' => $attributes['status'] ?? '',
            'created_at' => $attributes['created_at'] ?? '',
            'updated_at' => $attributes['updated_at'] ?? '',
        ];
    }

    /**
     * Process bulk subsidiary upload from spreadsheet.
     *
     * @param string $parent_org_id Parent organization UUID.
     * @param array  $file_data Uploaded file data.
     * @return array|WP_Error Result data or WP_Error on failure.
     */
    public function processBulkSubsidiaryUpload($parent_org_id, $file_data)
    {
        if (empty($parent_org_id)) {
            return new WP_Error('missing_org_id', __('Organization ID is required.', 'wicket-acc'));
        }

        if (empty($file_data) || !is_array($file_data)) {
            return new WP_Error('invalid_file_data', __('File data is required.', 'wicket-acc'));
        }

        // Validate file type (Excel files)
        $allowed_types = ['xlsx', 'xls'];
        $file_type = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));

        if (!in_array($file_type, $allowed_types, true)) {
            return new WP_Error(
                'invalid_file_type',
                sprintf(
                    __('File type %s is not allowed. Please upload an Excel file.', 'wicket-acc'),
                    $file_type
                )
            );
        }

        // For now, return a placeholder response
        // In a real implementation, this would process the Excel file
        // and create subsidiary relationships based on the data

        return [
            'success' => true,
            'message' => __('Bulk subsidiary upload feature is not yet implemented.', 'wicket-acc'),
            'file_name' => $file_data['name'],
            'file_size' => $file_data['size'],
        ];
    }
}
