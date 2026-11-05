<?php

declare(strict_types=1);

namespace WicketAcc\ManageEmployer;

// No direct access
defined('ABSPATH') || exit;

/**
 * M3 spike front-end runtime: asset gating and HTML fragment endpoints.
 *
 * Prototype for the M3 decision gate (vanilla JS vs Datastar). Everything in
 * this class is spike-scoped and gets reworked or deleted when M4 lands the
 * real front-end. The fragment endpoints are not REST routes on purpose: they
 * exist to prove the Datastar fragment path, so they render HTML through the
 * same guarded REST handlers instead of declaring new stable surfaces.
 *
 * @see atlas://plans/feature-acc-manage-employer-block.md
 */
final class Frontend
{
    private const REST_BASE = 'wicket-acc/v1/manage-employer';

    /**
     * The runtime enqueued for the current page render. Empty until the
     * template boots; one block per page per runtime in the spike.
     */
    private string $booted = '';

    public function __construct()
    {
        add_action('template_redirect', [$this, 'handleFragmentRequest']);
    }

    /**
     * Vanilla JS or Datastar, switchable per render for the comparison.
     */
    public static function runtime(): string
    {
        $runtime = apply_filters('wicket_acc_manage_employer_runtime', 'vanilla');

        return in_array($runtime, ['vanilla', 'datastar'], true) ? $runtime : 'vanilla';
    }

    /**
     * Enqueue spike assets. Called from the block template at render time, so
     * pages without the block never load any of this (block-scoped enqueue).
     */
    public static function boot(string $instanceId): void
    {
        static $booted = '';

        if ($booted !== '') {
            return;
        }

        $booted = $instanceId;
        $runtime = self::runtime();

        $cssPath = WICKET_ACC_PATH . 'assets/css/wicket-acc-me-spike.css';

        if (file_exists($cssPath)) {
            wp_enqueue_style(
                'wicket-acc-me-spike',
                WICKET_ACC_URL . 'assets/css/wicket-acc-me-spike.css',
                [],
                (string) filemtime($cssPath)
            );
        }

        if ($runtime === 'datastar') {
            // Local copy, never the CDN the OrgMan admin screens use. The
            // vendored bundle is the ESM build (ends in export{}), so it loads
            // as a module, footer, only on block renders.
            $datastarPath = WICKET_ACC_PATH . 'assets/js/datastar.js';

            if (file_exists($datastarPath)) {
                wp_enqueue_script_module(
                    'wicket-acc-me-datastar',
                    WICKET_ACC_URL . 'assets/js/datastar.js',
                    [],
                    (string) filemtime($datastarPath)
                );
            }

            $gluePath = WICKET_ACC_PATH . 'assets/js/wicket-acc-me-spike-datastar.js';

            if (file_exists($gluePath)) {
                // No dependency on the module handle: wp_enqueue_script_module
                // registers in a separate queue, and a classic script naming it
                // as a dep fails with an unregistered-dependency notice. Print
                // order still works out: modules execute after classic footer
                // scripts, so the listener lands before Datastar processes.
                wp_enqueue_script(
                    'wicket-acc-me-spike-datastar',
                    WICKET_ACC_URL . 'assets/js/wicket-acc-me-spike-datastar.js',
                    [],
                    (string) filemtime($gluePath),
                    true
                );
            }
        } else {
            $vanillaPath = WICKET_ACC_PATH . 'assets/js/wicket-acc-me-spike-vanilla.js';

            if (file_exists($vanillaPath)) {
                wp_enqueue_script(
                    'wicket-acc-me-spike-vanilla',
                    WICKET_ACC_URL . 'assets/js/wicket-acc-me-spike-vanilla.js',
                    [],
                    (string) filemtime($vanillaPath),
                    true
                );
            }
        }
    }

    /**
     * Datastar fragment source: GET search/state and POST add rendered as HTML.
     *
     * rest_do_request runs the real REST handlers, so permission callbacks,
     * signed settings, rate limits, and ownership checks belong to the REST
     * layer, not duplicated here. The fragment layer only turns JSON into HTML.
     *
     * Datastar requests carry the page signals as a JSON `datastar` query
     * parameter (GET) or body (POST); the search term and selection are read
     * from there. The add posts the hidden org_uuid input form-encoded, so its
     * value comes from $_POST.
     */
    public function handleFragmentRequest(): void
    {
        $fragment = isset($_GET['wicket_me_fragment']) ? sanitize_key(wp_unslash($_GET['wicket_me_fragment'])) : '';

        if ($fragment === '') {
            return;
        }

        // rest_do_request bypasses the cookie-nonce check REST applies to
        // writes, and signals can arrive on the query string, so the add
        // fragment must prove method and nonce itself or it is a CSRF hole:
        // SameSite=Lax still sends cookies on top-level GET navigations.
        if ($fragment === 'add') {
            $nonceHeader = isset($_SERVER['HTTP_X_WP_NONCE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE'])) : '';

            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !wp_verify_nonce($nonceHeader, 'wp_rest')) {
                status_header(403);
                exit;
            }
        }

        if (!is_user_logged_in()) {
            status_header(403);
            exit;
        }

        $settings = isset($_GET['settings']) && is_string($_GET['settings']) ? (string) wp_unslash($_GET['settings']) : '';
        $token = isset($_GET['settings_token']) && is_string($_GET['settings_token']) ? (string) wp_unslash($_GET['settings_token']) : '';
        $fid = isset($_GET['fid']) ? preg_replace('/[^A-Za-z0-9_-]/', '', wp_unslash($_GET['fid'])) : '';
        $fid = is_string($fid) && $fid !== '' ? $fid : 'spike';

        // Datastar sends signals in the `datastar` query parameter on GET
        // requests and in a {"datastar": {...}} JSON body on POST requests.
        $raw = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
                $raw = (string) file_get_contents('php://input');
            }
        } elseif (isset($_GET['datastar']) && is_string($_GET['datastar'])) {
            $raw = (string) wp_unslash($_GET['datastar']);
        }

        $signals = [];

        if ($raw !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                $signals = is_array($decoded['datastar'] ?? null) ? $decoded['datastar'] : $decoded;
            }
        }

        switch ($fragment) {
            case 'search':
                $term = isset($signals['meQuery_' . $fid]) && is_string($signals['meQuery_' . $fid])
                    ? $signals['meQuery_' . $fid]
                    : '';
                $this->renderFragment('GET', '/search', ['q' => $term, 'page' => 1], $settings, $token, $fid, 'results');
                break;
            case 'state':
                $this->renderFragment('GET', '/state', [], $settings, $token, $fid, 'list');
                break;
            case 'add':
                $orgUuid = '';

                if (isset($signals['meSelected_' . $fid]) && is_string($signals['meSelected_' . $fid])) {
                    $orgUuid = sanitize_text_field($signals['meSelected_' . $fid]);
                }

                // The POST response carries the mutation result only; the list
                // fragment renders the state read that follows it, so the
                // morph always carries the current cards.
                $this->renderFragment('POST', '/relationships', ['org_uuid' => $orgUuid], $settings, $token, $fid, 'list');
                break;
            default:
                status_header(404);
                exit;
        }

        exit;
    }

    /**
     * Dispatch one REST call server-side and render its payload as a fragment.
     *
     * Errors render inside the same root element as success responses, so the
     * Datastar morph always finds its target id.
     *
     * @param array<string, mixed> $params
     */
    private function renderFragment(string $method, string $route, array $params, string $settings, string $token, string $fid, string $kind): void
    {
        $request = new \WP_REST_Request($method, '/' . self::REST_BASE . $route);
        // set_query_params, not set_url_params: dispatch() rewrites url params
        // with the route's regex captures (empty on non-parameterized routes),
        // which would drop the settings and token from the request.
        $request->set_query_params(array_merge($params, [
            'settings' => $settings,
            'settings_token' => $token,
        ]));

        $response = rest_do_request($request);
        $data = $response->get_data();
        $status = $response->get_status();

        // A successful add returns the mutation result, not the list; follow
        // it with a state read so the rendered fragment carries fresh cards.
        // MDP read-your-writes can lag, so the newest card may miss here — the
        // planned bounded retry (M5) covers that.
        if ($status < 400 && $method === 'POST') {
            $stateRequest = new \WP_REST_Request('GET', '/' . self::REST_BASE . '/state');
            $stateRequest->set_query_params([
                'settings' => $settings,
                'settings_token' => $token,
            ]);
            $stateResponse = rest_do_request($stateRequest);

            if ($stateResponse->get_status() < 400) {
                $data = $stateResponse->get_data();
                $status = 200;
            }
        }

        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');

        if ($status >= 400) {
            status_header($status);
            $message = is_array($data) && isset($data['message']) ? (string) $data['message'] : 'Request failed.';
            // Errors go to the static feedback region so a 429 never wipes
            // the member's cards, and the live region announces them.
            printf(
                '<p id="me-%1$s-feedback" class="me-spike__feedback me-spike__feedback--error" role="status">%2$s</p>',
                esc_attr($fid),
                esc_html($message)
            );

            exit;
        }

        status_header(200);

        if ($kind === 'results') {
            $this->renderSearchResults($fid, is_array($data) ? $data : []);
        } else {
            $this->renderEmployerList($fid, is_array($data) ? $data : []);
        }

        exit;
    }

    /**
     * Search fragment: the combobox listbox options.
     *
     * @param array<string, mixed> $data
     */
    private function renderSearchResults(string $fid, array $data): void
    {
        $results = is_array($data['results'] ?? null) ? $data['results'] : [];

        echo '<ul id="me-' . esc_attr($fid) . '-results" class="me-spike__options" role="listbox" aria-label="Search results">';

        if ($results === []) {
            echo '<li class="me-spike__option me-spike__option--empty" role="option" aria-disabled="true">No organizations found.</li>';
            echo '</ul>';

            return;
        }

        foreach ($results as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $uuid = (string) ($row['uuid'] ?? '');
            $name = (string) ($row['legal_name'] ?? '');
            $connected = !empty($row['connected']);

            // Plain data attributes only: Datastar (observed on the vendored v1.0.1) did not wire
            // data-on on elements that arrive via morph while hidden, so the
            // visible wrapper delegates option clicks.
            printf(
                '<li class="me-spike__option%1$s" role="option" id="me-%2$s-option-%3$d" data-uuid="%4$s" data-name="%5$s" aria-selected="false">%6$s%7$s</li>',
                $connected ? ' me-spike__option--connected' : '',
                esc_attr($fid),
                $index,
                esc_attr($uuid),
                esc_attr($name),
                esc_html($name),
                $connected ? ' <span class="me-spike__badge">Connected</span>' : ''
            );
        }

        echo '</ul>';
    }

    /**
     * List fragment: employer cards after a state read or an add.
     *
     * @param array<string, mixed> $data
     */
    private function renderEmployerList(string $fid, array $data): void
    {
        echo '<ul id="me-' . esc_attr($fid) . '-list" class="me-spike__cards">';

        $connections = is_array($data['connections'] ?? null) ? $data['connections'] : [];
        $orgs = is_array($data['orgs'] ?? null) ? $data['orgs'] : [];
        $primary = (string) ($data['primary_uuid'] ?? '');

        if ($connections === []) {
            echo '<li class="me-spike__empty">No employers found.</li>';
            echo '</ul>';

            return;
        }

        foreach ($connections as $connection) {
            $relationships = is_array($connection['relationships'] ?? null) ? $connection['relationships'] : [];
            $orgUuid = (string) ($relationships['to']['data']['id'] ?? $relationships['organization']['data']['id'] ?? '');
            $org = is_array($orgs[$orgUuid] ?? null) ? $orgs[$orgUuid] : [];
            $name = (string) ($org['legal_name'] ?? '');

            if ($name === '') {
                $name = $orgUuid !== '' ? $orgUuid : 'Unknown employer';
            }

            printf(
                '<li class="me-spike__card"><strong>%s</strong>%s</li>',
                esc_html($name),
                $orgUuid !== '' && $orgUuid === $primary ? ' <span class="me-spike__badge">Primary</span>' : ''
            );
        }

        echo '</ul>';
    }
}
