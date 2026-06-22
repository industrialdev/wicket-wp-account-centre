<?php

/**
 * Member export controller.
 */

namespace WicketORM\Controllers;

use WicketORM\Services\MemberExportService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles REST routes for async member CSV export.
 */
class MemberExportController extends ApiController
{
    /**
     * @var MemberExportService
     */
    private $export_service;

    /**
     * @param MemberExportService $export_service
     */
    public function __construct(MemberExportService $export_service)
    {
        $this->export_service = $export_service;
        $this->namespace = 'org-management/v1/exports';
    }

    /**
     * Register REST routes.
     */
    public function registerRoutes()
    {
        register_rest_route($this->namespace, '/initiate', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'initiateExport'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);

        register_rest_route($this->namespace, '/status', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getExportStatus'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);
    }

    /**
     * Initiate an export job. Returns an SSE Datastar response.
     *
     * @param WP_REST_Request $request
     * @return void  (SSE exits directly)
     */
    public function initiateExport(WP_REST_Request $request)
    {
        $org_id = sanitize_text_field($request->get_param('org_id'));
        $membership_uuid = sanitize_text_field($request->get_param('membership_uuid'));
        $org_dom_suffix = sanitize_html_class($request->get_param('org_dom_suffix') ?: ($org_id ?: 'default'));

        $error_signals = [
            'exportSubmitting' => false,
        ];

        if (empty($org_id)) {
            \WicketORM\Helpers\DatastarSSE::renderError(
                __('Organization identifier is missing.', 'wicket-acc'),
                '#export-messages-' . $org_dom_suffix,
                $error_signals
            );

            return;
        }

        $nonce = $request->get_param('_wpnonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wicket_orgman_export_' . $org_id)) {
            \WicketORM\Helpers\DatastarSSE::renderError(
                __('Security verification failed. Please refresh and try again.', 'wicket-acc'),
                '#export-messages-' . $org_dom_suffix,
                $error_signals
            );

            return;
        }

        if (!$this->userCanManageOrganization($org_id)) {
            \WicketORM\Helpers\DatastarSSE::renderError(
                __('You do not have permission to export members for this organization.', 'wicket-acc'),
                '#export-messages-' . $org_dom_suffix,
                $error_signals
            );

            return;
        }

        $current_user = wp_get_current_user();
        $recipient_email = sanitize_email($request->get_param('recipient_email') ?: ($current_user->user_email ?? ''));

        $result = $this->export_service->enqueueExport($org_id, $membership_uuid, $recipient_email);

        if (is_wp_error($result)) {
            \WicketORM\Helpers\DatastarSSE::renderError(
                $result->get_error_message(),
                '#export-messages-' . $org_dom_suffix,
                $error_signals
            );

            return;
        }

        \WicketORM\Helpers\DatastarSSE::renderSuccess(
            sprintf(
                /* translators: %s: email address */
                esc_html__('Your export has been queued. You will receive an email at %s when it is ready to download.', 'wicket-acc'),
                esc_html($recipient_email)
            ),
            '#export-messages-' . $org_dom_suffix,
            ['exportSubmitting' => false, 'exportQueued' => true]
        );
    }

    /**
     * Return the current status of an export job as HTML.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getExportStatus(WP_REST_Request $request)
    {
        $job_id = sanitize_key($request->get_param('job_id'));

        if (empty($job_id)) {
            return new WP_REST_Response('', 200);
        }

        $status = $this->export_service->getJobStatus($job_id);

        return $this->htmlResponse('export-members-status', [
            'job_id' => $job_id,
            'status' => $status,
        ]);
    }

    /**
     * @param string $template
     * @param array  $data
     * @return WP_REST_Response
     */
    private function htmlResponse(string $template, array $data): WP_REST_Response
    {
        ob_start();
        if (!empty($data)) {
            extract($data);
        }
        $template_path = dirname(dirname(__FILE__)) . '/templates-partials/' . $template . '.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
        $html = ob_get_clean();

        $response = new WP_REST_Response($html);
        $response->header('Content-Type', 'text/html');

        return $response;
    }
}
