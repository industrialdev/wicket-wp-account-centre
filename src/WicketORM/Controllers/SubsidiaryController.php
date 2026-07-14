<?php

/**
 * Subsidiary management controller.
 */

namespace WicketORM\Controllers;

use WicketORM\Services\SubsidiaryService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles routes related to organization subsidiary management.
 */
class SubsidiaryController extends ApiController
{
    /**
     * @var SubsidiaryService
     */
    private $subsidiary_service;

    /**
     * Constructor.
     *
     * @param SubsidiaryService $subsidiary_service Service dependency.
     */
    public function __construct(SubsidiaryService $subsidiary_service)
    {
        $this->subsidiary_service = $subsidiary_service;
        $this->namespace = 'wicket/orm/v1/subsidiaries';
    }

    /**
     * Register REST routes handled by this controller.
     */
    public function registerRoutes()
    {
        // Route for getting subsidiary list
        register_rest_route($this->namespace, '/list', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getSubsidiaryList'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);

        // Route for adding a subsidiary
        register_rest_route($this->namespace, '/add', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'addSubsidiary'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);

        // Route for removing a subsidiary
        register_rest_route($this->namespace, '/remove', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'removeSubsidiary'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);

        // Route for searching subsidiary candidates
        register_rest_route($this->namespace, '/search', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'searchSubsidiaryCandidates'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);

        // Route for bulk subsidiary upload
        register_rest_route($this->namespace, '/bulk-upload', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'bulkUploadSubsidiaries'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);
    }

    /**
     * Get the rendered subsidiary list partial.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function getSubsidiaryList(WP_REST_Request $request)
    {
        $org_id = sanitize_text_field($request->get_param('org_id'));

        if (empty($org_id)) {
            return $this->htmlResponse('subsidiaries-list', [
                'org_id' => '',
                'subsidiaries' => [],
                'notice' => [
                    'type'    => 'error',
                    'message' => __('Organization ID is required.', 'wicket-acc'),
                ],
            ]);
        }

        $subsidiaries = $this->subsidiary_service->getSubsidiaries($org_id);

        if (is_wp_error($subsidiaries)) {
            return $this->htmlResponse('subsidiaries-list', [
                'org_id' => $org_id,
                'subsidiaries' => [],
                'notice' => [
                    'type'    => 'error',
                    'message' => $subsidiaries->get_error_message(),
                ],
            ]);
        }

        $view_model = [
            'org_id' => $org_id,
            'subsidiaries' => $subsidiaries,
        ];

        return $this->htmlResponse('subsidiaries-list', $view_model);
    }

    /**
     * Add a subsidiary to an organization.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function addSubsidiary(WP_REST_Request $request)
    {
        $org_id = sanitize_text_field($request->get_param('org_id'));
        $subsidiary_org_id = sanitize_text_field($request->get_param('subsidiary_org_id'));

        if (empty($org_id) || empty($subsidiary_org_id)) {
            return $this->htmlResponse('subsidiaries-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => __('Organization ID and Subsidiary Organization ID are required.', 'wicket-acc'),
                ],
            ]);
        }

        // Verify nonce for security
        $nonce = $request->get_param('_wpnonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'org_management_subsidiary_add_' . $org_id)) {
            return $this->htmlResponse('subsidiaries-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => __('Security verification failed. Please try again.', 'wicket-acc'),
                ],
            ]);
        }

        $result = $this->subsidiary_service->addSubsidiary($org_id, $subsidiary_org_id);

        if (is_wp_error($result)) {
            return $this->htmlResponse('subsidiaries-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => $result->get_error_message(),
                ],
            ]);
        }

        // Refresh the subsidiary list after successful addition
        $subsidiaries = $this->subsidiary_service->getSubsidiaries($org_id);

        $view_model = [
            'org_id' => $org_id,
            'subsidiaries' => is_wp_error($subsidiaries) ? [] : $subsidiaries,
            'notice' => [
                'type'    => 'success',
                'message' => __('Subsidiary added successfully.', 'wicket-acc'),
            ],
        ];

        return $this->htmlResponse('subsidiaries-list', $view_model);
    }

    /**
     * Remove a subsidiary from an organization.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function removeSubsidiary(WP_REST_Request $request)
    {
        $org_id = sanitize_text_field($request->get_param('org_id'));
        $subsidiary_org_id = sanitize_text_field($request->get_param('subsidiary_org_id'));

        if (empty($org_id) || empty($subsidiary_org_id)) {
            return $this->htmlResponse('subsidiaries-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => __('Organization ID and Subsidiary Organization ID are required.', 'wicket-acc'),
                ],
            ]);
        }

        // Verify nonce for security
        $nonce = $request->get_param('_wpnonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'org_management_subsidiary_remove_' . $org_id)) {
            return $this->htmlResponse('subsidiaries-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => __('Security verification failed. Please try again.', 'wicket-acc'),
                ],
            ]);
        }

        $result = $this->subsidiary_service->removeSubsidiary($org_id, $subsidiary_org_id);

        if (is_wp_error($result)) {
            return $this->htmlResponse('subsidiaries-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => $result->get_error_message(),
                ],
            ]);
        }

        // Refresh the subsidiary list after successful removal
        $subsidiaries = $this->subsidiary_service->getSubsidiaries($org_id);

        $view_model = [
            'org_id' => $org_id,
            'subsidiaries' => is_wp_error($subsidiaries) ? [] : $subsidiaries,
            'notice' => [
                'type'    => 'success',
                'message' => __('Subsidiary removed successfully.', 'wicket-acc'),
            ],
        ];

        return $this->htmlResponse('subsidiaries-list', $view_model);
    }

    /**
     * Search for organizations that can be added as subsidiaries.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function searchSubsidiaryCandidates(WP_REST_Request $request)
    {
        $org_id = sanitize_text_field($request->get_param('org_id'));
        $search_term = sanitize_text_field($request->get_param('search'));

        if (empty($org_id)) {
            return $this->successResponse([
                'candidates' => [],
                'error' => __('Organization ID is required.', 'wicket-acc'),
            ]);
        }

        $candidates = $this->subsidiary_service->searchSubsidiaryCandidates($search_term, $org_id);

        return $this->successResponse([
            'candidates' => $candidates,
            'search_term' => $search_term,
        ]);
    }

    /**
     * Handle bulk subsidiary upload from spreadsheet.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function bulkUploadSubsidiaries(WP_REST_Request $request)
    {
        $org_id = sanitize_text_field($request->get_param('org_id'));

        if (empty($org_id)) {
            return $this->htmlResponse('subsidiaries-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => __('Organization ID is required.', 'wicket-acc'),
                ],
            ]);
        }

        // Verify nonce for security
        $nonce = $request->get_param('_wpnonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'org_management_subsidiary_bulk_upload_' . $org_id)) {
            return $this->htmlResponse('subsidiaries-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => __('Security verification failed. Please try again.', 'wicket-acc'),
                ],
            ]);
        }

        // Handle file upload from $_FILES
        $uploaded_file = $_FILES['bulk_file'] ?? null;

        if (!$uploaded_file || $uploaded_file['error'] !== UPLOAD_ERR_OK) {
            $error_message = __('File upload failed.', 'wicket-acc');

            if ($uploaded_file && isset($uploaded_file['error'])) {
                switch ($uploaded_file['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message = __('File size exceeds the maximum allowed size.', 'wicket-acc');
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message = __('File was only partially uploaded.', 'wicket-acc');
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_message = __('No file was uploaded.', 'wicket-acc');
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error_message = __('Missing temporary folder.', 'wicket-acc');
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error_message = __('Failed to write file to disk.', 'wicket-acc');
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $error_message = __('File upload stopped by extension.', 'wicket-acc');
                        break;
                }
            }

            return $this->htmlResponse('subsidiaries-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => $error_message,
                ],
            ]);
        }

        $result = $this->subsidiary_service->processBulkSubsidiaryUpload($org_id, $uploaded_file);

        if (is_wp_error($result)) {
            return $this->htmlResponse('subsidiaries-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => $result->get_error_message(),
                ],
            ]);
        }

        // Refresh the subsidiary list after bulk upload
        $subsidiaries = $this->subsidiary_service->getSubsidiaries($org_id);

        $view_model = [
            'org_id' => $org_id,
            'subsidiaries' => is_wp_error($subsidiaries) ? [] : $subsidiaries,
            'notice' => [
                'type'    => 'success',
                'message' => $result['message'] ?? __('Bulk upload processed successfully.', 'wicket-acc'),
            ],
        ];

        return $this->htmlResponse('subsidiaries-list', $view_model);
    }

    /**
     * Render template partial and wrap in REST response.
     *
     * @param string $template Template name (without extension).
     * @param array  $data     Data for the template.
     * @return WP_REST_Response
     */
    private function htmlResponse($template, array $data)
    {
        ob_start();
        if (!empty($data)) {
            extract($data);
        }
        $template_path = dirname(dirname(__FILE__)) . '/templates-partials/' . $template . '.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<p>Template not found: ' . esc_html($template_path) . '</p>';
        }
        $html = ob_get_clean();

        $response = new WP_REST_Response($html);
        $response->header('Content-Type', 'text/html');

        return $response;
    }
}
