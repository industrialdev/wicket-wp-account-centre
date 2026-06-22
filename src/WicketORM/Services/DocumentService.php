<?php

/**
 * Document management service.
 */

namespace WicketORM\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles document operations for organization management.
 */
class DocumentService
{
    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * Constructor.
     *
     * @param ConfigService|null $configService Optional ConfigService instance.
     */
    public function __construct(?ConfigService $configService = null)
    {
        $this->configService = $configService ?: new ConfigService();
    }

    /**
     * Retrieve documents for an organization.
     *
     * @param string $org_id Organization UUID.
     * @return array|WP_Error Array of documents or WP_Error on failure.
     */
    public function getDocuments($org_id)
    {
        if (empty($org_id)) {
            return new WP_Error('missing_org_id', __('Organization ID is required.', 'wicket-acc'));
        }

        // Implementation will depend on how documents are stored
        // For now, return an empty array as a placeholder
        $documents = [];

        // This would typically fetch documents from MDP or WordPress media
        if (function_exists('wicket_get_organization')) {
            $org = wicket_get_organization($org_id);
            if (isset($org['data']['attributes']['documents'])) {
                $documents = $org['data']['attributes']['documents'];
            }
        }

        return $documents;
    }

    /**
     * Upload a document for an organization.
     *
     * @param string $org_id Organization UUID.
     * @param array  $file_data File data from $_FILES.
     * @param array  $metadata Additional metadata for the document.
     * @return array|WP_Error Document info or WP_Error on failure.
     */
    public function uploadDocument($org_id, $file_data, $metadata = [])
    {
        if (empty($org_id)) {
            return new WP_Error('missing_org_id', __('Organization ID is required.', 'wicket-acc'));
        }

        if (empty($file_data) || !is_array($file_data)) {
            return new WP_Error('invalid_file_data', __('File data is required.', 'wicket-acc'));
        }

        // Validate file type and size using config
        $allowed_types = $this->configService->getAllowedDocumentTypes();

        $file_type = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));

        if (!in_array($file_type, $allowed_types, true)) {
            return new WP_Error(
                'invalid_file_type',
                sprintf(
                    __('File type %s is not allowed.', 'wicket-acc'),
                    $file_type
                )
            );
        }

        // Validate file size using config
        $max_size = $this->configService->getMaxDocumentSize();
        if ($file_data['size'] > $max_size) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    __('File size exceeds the maximum allowed size of %s.', 'wicket-acc'),
                    size_format($max_size)
                )
            );
        }

        // Handle WordPress media upload
        $upload_dir = wp_upload_dir();
        $filename = sanitize_file_name($file_data['name']);
        $file_tmp = $file_data['tmp_name'];

        // Move uploaded file to WordPress uploads
        $upload_file = $upload_dir['path'] . '/' . $filename;
        $moved = move_uploaded_file($file_tmp, $upload_file);

        if (!$moved) {
            return new WP_Error('upload_failed', __('File upload failed.', 'wicket-acc'));
        }

        // Insert as WordPress attachment
        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = [
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => sanitize_text_field($metadata['title'] ?? preg_replace('/\.[^.]+$/', '', $filename)),
            'post_content'   => sanitize_textarea_field($metadata['description'] ?? ''),
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $upload_file);
        if (is_wp_error($attach_id)) {
            return $attach_id;
        }

        // Include image.php to use wp_generate_attachment_metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attach_data = wp_generate_attachment_metadata($attach_id, $upload_file);
        wp_update_attachment_metadata($attach_id, $attach_data);

        // Link this attachment to the organization using post meta
        update_post_meta($attach_id, '_org_management_org_id', $org_id);

        // Apply additional metadata if provided
        if (isset($metadata['category'])) {
            update_post_meta($attach_id, '_org_management_category', sanitize_text_field($metadata['category']));
        }

        // Return document info
        return [
            'id'          => $attach_id,
            'title'       => get_the_title($attach_id),
            'description' => get_post_field('post_content', $attach_id),
            'filename'    => basename($upload_file),
            'filesize'    => filesize($upload_file),
            'filetype'    => $wp_filetype['type'],
            'url'         => wp_get_attachment_url($attach_id),
            'upload_date' => get_post_field('post_date', $attach_id),
            'category'    => get_post_meta($attach_id, '_org_management_category', true),
        ];
    }

    /**
     * Delete a document by ID.
     *
     * @param int $document_id Document ID.
     * @param bool $force_delete Whether to bypass trash.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function deleteDocument($document_id, $force_delete = true)
    {
        if (empty($document_id)) {
            return new WP_Error('missing_document_id', __('Document ID is required.', 'wicket-acc'));
        }

        $document = get_post($document_id);
        if (!$document || $document->post_type !== 'attachment') {
            return new WP_Error('document_not_found', __('Document not found.', 'wicket-acc'));
        }

        // Only delete attachments that belong to org management
        $org_id = get_post_meta($document_id, '_org_management_org_id', true);
        if (empty($org_id)) {
            return new WP_Error('invalid_document', __('Invalid document.', 'wicket-acc'));
        }

        $deleted = wp_delete_attachment($document_id, $force_delete);
        if (!$deleted) {
            return new WP_Error('delete_failed', __('Failed to delete document.', 'wicket-acc'));
        }

        return true;
    }

    /**
     * Get documents by organization and category.
     *
     * @param string $org_id Organization UUID.
     * @param string $category Optional category filter.
     * @return array Array of document objects.
     */
    public function getDocumentsByOrg($org_id, $category = null)
    {
        if (empty($org_id)) {
            return [];
        }

        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_org_management_org_id',
                    'value'   => $org_id,
                    'compare' => '=',
                ],
            ],
        ];

        if (!empty($category)) {
            $args['meta_query'][] = [
                'key'     => '_org_management_category',
                'value'   => $category,
                'compare' => '=',
            ];
        }

        $query = new \WP_Query($args);
        $documents = [];

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $documents[] = [
                    'id'          => $post->ID,
                    'title'       => $post->post_title,
                    'description' => $post->post_content,
                    'filename'    => basename(get_attached_file($post->ID)),
                    'filesize'    => filesize(get_attached_file($post->ID)),
                    'filetype'    => get_post_mime_type($post->ID),
                    'url'         => wp_get_attachment_url($post->ID),
                    'upload_date' => $post->post_date,
                    'category'    => get_post_meta($post->ID, '_org_management_category', true),
                ];
            }
        }

        return $documents;
    }
}
