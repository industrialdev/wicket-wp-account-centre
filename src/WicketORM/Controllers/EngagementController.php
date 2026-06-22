<?php

/**
 * Engagement data controller.
 */

namespace WicketORM\Controllers;

use WicketORM\Services\EngagementService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles REST routes for MDP engagement/donation data display.
 */
class EngagementController extends ApiController
{
    /**
     * @var EngagementService
     */
    private $engagement_service;

    /**
     * @param EngagementService $engagement_service
     */
    public function __construct(EngagementService $engagement_service)
    {
        $this->engagement_service = $engagement_service;
        $this->namespace = 'org-management/v1/engagement';
    }

    /**
     * Register REST routes.
     */
    public function registerRoutes()
    {
        register_rest_route($this->namespace, '/person', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getPersonEngagement'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);

        register_rest_route($this->namespace, '/organization', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getOrgEngagement'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);
    }

    /**
     * Return the engagement summary partial for a person.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getPersonEngagement(WP_REST_Request $request)
    {
        $org_id = sanitize_text_field($request->get_param('org_id'));

        // Default to the current logged-in user's Wicket UUID
        $person_uuid = sanitize_text_field($request->get_param('person_uuid'));
        if ($person_uuid === '' && function_exists('wicket_current_person_uuid')) {
            $person_uuid = (string) wicket_current_person_uuid();
        }

        if ($person_uuid === '') {
            return $this->htmlResponse('engagement-summary', [
                'engagement' => null,
                'notice'     => ['type' => 'error', 'message' => __('Unable to determine the current person.', 'wicket-acc')],
            ]);
        }

        $engagement = $this->engagement_service->getPersonEngagement($person_uuid, $org_id);

        if (is_wp_error($engagement)) {
            return $this->htmlResponse('engagement-summary', [
                'engagement' => null,
                'notice'     => ['type' => 'error', 'message' => $engagement->get_error_message()],
            ]);
        }

        return $this->htmlResponse('engagement-summary', [
            'engagement' => $engagement,
            'notice'     => null,
        ]);
    }

    /**
     * Return the engagement summary partial for an organization.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getOrgEngagement(WP_REST_Request $request)
    {
        $org_id = sanitize_text_field($request->get_param('org_id'));

        if ($org_id === '') {
            return $this->htmlResponse('engagement-summary', [
                'engagement' => null,
                'notice'     => ['type' => 'error', 'message' => __('Organization ID is required.', 'wicket-acc')],
            ]);
        }

        $engagement = $this->engagement_service->getOrgEngagement($org_id);

        if (is_wp_error($engagement)) {
            return $this->htmlResponse('engagement-summary', [
                'engagement' => null,
                'notice'     => ['type' => 'error', 'message' => $engagement->get_error_message()],
            ]);
        }

        return $this->htmlResponse('engagement-summary', [
            'engagement' => $engagement,
            'notice'     => null,
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
