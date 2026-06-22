<?php

/**
 * Document management controller.
 */

namespace WicketORM\Controllers;

use WicketORM\Services\DocumentService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles routes related to organization document management.
 */
class DocumentController extends ApiController
{
    /**
     * @var DocumentService
     */
    private $document_service;

    /**
     * Constructor.
     *
     * @param DocumentService $document_service Service dependency.
     */
    public function __construct(DocumentService $document_service)
    {
        $this->document_service = $document_service;
        $this->namespace = 'org-management/v1/documents';
    }

    /**
     * Register REST routes handled by this controller.
     */
    public function registerRoutes()
    {
        // Route for getting document list
        register_rest_route($this->namespace, '/list', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getDocumentList'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);

        // Route for handling document uploads
        register_rest_route($this->namespace, '/upload', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'uploadDocument'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);

        // Route for deleting documents
        register_rest_route($this->namespace, '/delete/(?P<document_id>[\d]+)', [
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'deleteDocument'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);
    }

    /**
     * Get the rendered document list partial.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function getDocumentList(WP_REST_Request $request)
    {
        $org_id = sanitize_text_field($request->get_param('org_id'));
        $category = sanitize_text_field($request->get_param('category'));

        if (empty($org_id)) {
            return $this->htmlResponse('documents-list', [
                'org_id' => '',
                'documents' => [],
                'notice' => [
                    'type'    => 'error',
                    'message' => __('Organization ID is required.', 'wicket-acc'),
                ],
            ]);
        }

        $documents = $this->document_service->getDocumentsByOrg($org_id, $category);

        $view_model = [
            'org_id' => $org_id,
            'documents' => $documents,
            'category' => $category,
        ];

        return $this->htmlResponse('documents-list', $view_model);
    }

    /**
     * Handle document upload.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function uploadDocument(WP_REST_Request $request)
    {
        $org_id = sanitize_text_field($request->get_param('org_id'));
        $category = sanitize_text_field($request->get_param('category'));
        $title = sanitize_text_field($request->get_param('title'));
        $description = sanitize_textarea_field($request->get_param('description'));

        if (empty($org_id)) {
            return $this->htmlResponse('documents-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => __('Organization ID is required.', 'wicket-acc'),
                ],
            ]);
        }

        // Verify nonce for security
        $nonce = $request->get_param('_wpnonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'org_management_document_upload_' . $org_id)) {
            return $this->htmlResponse('documents-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => __('Security verification failed. Please try again.', 'wicket-acc'),
                ],
            ]);
        }

        // Handle file upload from $_FILES
        $uploaded_file = $_FILES['document'] ?? null;

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

            return $this->htmlResponse('documents-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => $error_message,
                ],
            ]);
        }

        // Prepare metadata
        $metadata = [
            'title'       => $title ?: $uploaded_file['name'],
            'description' => $description,
            'category'    => $category,
        ];

        $result = $this->document_service->uploadDocument($org_id, $uploaded_file, $metadata);

        if (is_wp_error($result)) {
            return $this->htmlResponse('documents-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => $result->get_error_message(),
                ],
            ]);
        }

        // Refresh the document list after successful upload
        $documents = $this->document_service->getDocumentsByOrg($org_id, $category);

        $view_model = [
            'org_id' => $org_id,
            'documents' => $documents,
            'category' => $category,
            'notice' => [
                'type'    => 'success',
                'message' => sprintf(
                    __('Document "%s" uploaded successfully.', 'wicket-acc'),
                    $result['title']
                ),
            ],
        ];

        return $this->htmlResponse('documents-list', $view_model);
    }

    /**
     * Handle document deletion.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function deleteDocument(WP_REST_Request $request)
    {
        $org_id = sanitize_text_field($request->get_param('org_id'));
        $document_id = absint($request->get_param('document_id'));

        if (empty($org_id) || empty($document_id)) {
            return $this->htmlResponse('documents-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => __('Organization ID and Document ID are required.', 'wicket-acc'),
                ],
            ]);
        }

        // Verify nonce for security
        $nonce = $request->get_param('_wpnonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'org_management_document_delete_' . $document_id)) {
            return $this->htmlResponse('documents-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => __('Security verification failed. Please try again.', 'wicket-acc'),
                ],
            ]);
        }

        $result = $this->document_service->deleteDocument($document_id);

        if (is_wp_error($result)) {
            return $this->htmlResponse('documents-list', [
                'notice' => [
                    'type'    => 'error',
                    'message' => $result->get_error_message(),
                ],
            ]);
        }

        // Refresh the document list after successful deletion
        $category = sanitize_text_field($request->get_param('category'));
        $documents = $this->document_service->getDocumentsByOrg($org_id, $category);

        $view_model = [
            'org_id' => $org_id,
            'documents' => $documents,
            'category' => $category,
            'notice' => [
                'type'    => 'success',
                'message' => __('Document deleted successfully.', 'wicket-acc'),
            ],
        ];

        return $this->htmlResponse('documents-list', $view_model);
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
