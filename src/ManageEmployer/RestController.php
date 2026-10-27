<?php

declare(strict_types=1);

namespace WicketAcc\ManageEmployer;

// No direct access
defined('ABSPATH') || exit;

use WP_Error;

/**
 * REST routes for the manage-employer block.
 *
 * Namespace wicket-acc/v1/manage-employer. This layer is the only
 * authorization boundary: the shared MDP client authorizes as the settings
 * service person, so every route re-proves identity (logged-in member with a
 * person uuid, no guest-payment session, no active user switch on mutations)
 * and re-verifies the embedded signed settings before touching MDP.
 *
 * Permission callbacks fail closed. Capabilities are never consulted: WP
 * roles sync from MDP only at login and lag reality.
 *
 * @see atlas://plans/feature-acc-manage-employer-block.md
 */
final class RestController
{
    public const NAMESPACE_URI = 'wicket-acc/v1/manage-employer';

    private const SEARCH_MIN_CHARS = 3;
    private const SEARCH_PAGE_CAP = 50;
    private const SEARCH_PAGE_SIZE = 10;

    /**
     * Per-user request windows (count => window seconds).
     */
    private const RATE_SEARCH = [60 => 60];
    private const RATE_MUTATE = [10 => 600];
    private const RATE_CREATE_ORG = [5 => 3600];

    /**
     * Max active employer connections before create/add is refused.
     */
    private const MAX_ACTIVE_CONNECTIONS = 100;

    public function __construct(private readonly ?Service $service = null)
    {
    }

    /**
     * Register all routes. Hooked on rest_api_init.
     */
    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE_URI, '/state', [
            'methods' => 'GET',
            'permission_callback' => fn (): bool => $this->canRead(),
            'callback' => [$this, 'handleState'],
            'args' => [
                'page' => ['type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint'],
                'settings' => ['type' => 'string', 'required' => true],
                'settings_token' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE_URI, '/search', [
            'methods' => 'GET',
            'permission_callback' => fn (): bool => $this->canRead(),
            'callback' => [$this, 'handleSearch'],
            'args' => [
                'q' => ['type' => 'string', 'required' => true],
                'page' => ['type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint'],
                'type' => ['type' => 'string'],
                'settings' => ['type' => 'string', 'required' => true],
                'settings_token' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE_URI, '/relationships', [
            'methods' => 'POST',
            'permission_callback' => fn (): bool => $this->canMutate(),
            'callback' => [$this, 'handleAddRelationship'],
            'args' => [
                'org_uuid' => ['type' => 'string', 'required' => true],
                'settings' => ['type' => 'string', 'required' => true],
                'settings_token' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE_URI, '/relationships/(?P<uuid>[A-Za-z0-9-]+)', [
            'methods' => 'PATCH',
            'permission_callback' => fn (): bool => $this->canMutate(),
            'callback' => [$this, 'handleEndRelationship'],
            'args' => [
                'uuid' => ['type' => 'string', 'required' => true],
                'org_uuid' => ['type' => 'string', 'required' => true],
                'settings' => ['type' => 'string', 'required' => true],
                'settings_token' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE_URI, '/organizations', [
            'methods' => 'POST',
            'permission_callback' => fn (): bool => $this->canMutate(),
            'callback' => [$this, 'handleCreateOrganization'],
            'args' => [
                'name' => ['type' => 'string', 'required' => true],
                'org_type' => ['type' => 'string'],
                'settings' => ['type' => 'string', 'required' => true],
                'settings_token' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE_URI, '/primary', [
            'methods' => 'PATCH',
            'permission_callback' => fn (): bool => $this->canMutate(),
            'callback' => [$this, 'handleSetPrimary'],
            'args' => [
                'org_uuid' => ['type' => 'string', 'required' => true],
                'settings' => ['type' => 'string', 'required' => true],
                'settings_token' => ['type' => 'string', 'required' => true],
            ],
        ]);
    }

    /**
     * GET state: connections, org details, primary uuid. Never cached.
     */
    public function handleState(\WP_REST_Request $request): \WP_REST_Response
    {
        $guard = $this->settings($request);

        if (is_wp_error($guard)) {
            return $this->error($guard);
        }

        $state = $this->service()->getState(
            $this->personUuid(),
            max(1, absint($request->get_param('page'))),
            $guard
        );

        if (is_wp_error($state)) {
            return $this->error($state);
        }

        return $this->response($state, 200, true);
    }

    /**
     * GET search: debounced org search, hard page cap, per-user rate limit.
     */
    public function handleSearch(\WP_REST_Request $request): \WP_REST_Response
    {
        $guard = $this->settings($request);

        if (is_wp_error($guard)) {
            return $this->error($guard);
        }

        $term = sanitize_text_field((string) $request->get_param('q'));

        if (mb_strlen($term) < self::SEARCH_MIN_CHARS) {
            return $this->error(new WP_Error(
                'me_search_term_short',
                sprintf('The search term needs at least %d characters.', self::SEARCH_MIN_CHARS),
                ['status' => 400]
            ));
        }

        $limited = $this->rateLimit('search', self::RATE_SEARCH);

        if (is_wp_error($limited)) {
            return $this->error($limited);
        }

        $page = min(self::SEARCH_PAGE_CAP, max(1, absint($request->get_param('page'))));

        $typeParam = $request->get_param('type');
        $clientTypes = is_string($typeParam)
            ? array_filter(array_map('sanitize_key', explode(',', $typeParam)))
            : array_filter(array_map('sanitize_key', (array) $typeParam));

        $connected = $this->service()->activeEmployerOrgUuids($this->personUuid());

        if (is_wp_error($connected)) {
            return $this->error($connected);
        }

        $results = $this->service()->searchOrganizations($term, $page, $guard['org_types'], $clientTypes, $connected);

        if (is_wp_error($results)) {
            return $this->error($results);
        }

        return $this->response($results, 200, true);
    }

    /**
     * POST relationships: add an employer card.
     */
    public function handleAddRelationship(\WP_REST_Request $request): \WP_REST_Response
    {
        $guard = $this->settings($request);

        if (is_wp_error($guard)) {
            return $this->error($guard);
        }

        if ($guard['display_membership_status_filter'] === Settings::MODE_INACTIVE) {
            return $this->error(new WP_Error('me_read_only', 'This block is in read-only mode.', ['status' => 403]));
        }

        $limited = $this->rateLimit('mutate', self::RATE_MUTATE);

        if (is_wp_error($limited)) {
            return $this->error($limited);
        }

        $personUuid = $this->personUuid();
        $orgUuid = sanitize_text_field((string) $request->get_param('org_uuid'));

        if (! wp_is_uuid($orgUuid)) {
            return $this->error(new WP_Error('me_org_invalid', 'That organization id is malformed.', ['status' => 400]));
        }

        $capError = $this->connectionCapError($personUuid);

        if (is_wp_error($capError)) {
            return $this->error($capError);
        }

        $allowed = $this->verifyOrgAllowed($orgUuid, $guard);

        if (is_wp_error($allowed)) {
            return $this->error($allowed);
        }

        $result = $this->service()->addEmployer($personUuid, $orgUuid, $guard['relationship_types_on_create']);

        if (is_wp_error($result)) {
            return $this->error($result);
        }

        return $this->response($result, $result['errors'] === [] ? 200 : 207);
    }

    /**
     * PATCH relationships/{uuid}: end the employer card. The uuid must sit in
     * the person's own connection list; the end date is computed server-side.
     */
    public function handleEndRelationship(\WP_REST_Request $request): \WP_REST_Response
    {
        $guard = $this->settings($request);

        if (is_wp_error($guard)) {
            return $this->error($guard);
        }

        if (! empty($guard['hide_end_button'])) {
            return $this->error(new WP_Error('me_end_disabled', 'Ending is disabled for this block.', ['status' => 403]));
        }

        if ($guard['display_membership_status_filter'] === Settings::MODE_INACTIVE) {
            return $this->error(new WP_Error('me_read_only', 'This block is in read-only mode.', ['status' => 403]));
        }

        $limited = $this->rateLimit('mutate', self::RATE_MUTATE);

        if (is_wp_error($limited)) {
            return $this->error($limited);
        }

        $personUuid = $this->personUuid();
        $orgUuid = sanitize_text_field((string) $request->get_param('org_uuid'));
        $connectionUuid = sanitize_text_field((string) $request->get_param('uuid'));

        if (! wp_is_uuid($connectionUuid) || ! wp_is_uuid($orgUuid)) {
            return $this->error(new WP_Error('me_connection_invalid', 'That connection id is malformed.', ['status' => 400]));
        }

        $state = $this->service()->getState($personUuid, 1, $this->stateScope($guard));

        if (is_wp_error($state)) {
            return $this->error($state);
        }

        if (! $this->service()->ownsActiveConnection($state['connections'], $personUuid, $connectionUuid, $orgUuid)) {
            return $this->error(new WP_Error(
                'me_connection_not_owned',
                'That connection does not belong to your account.',
                ['status' => 403]
            ));
        }

        $result = $this->service()->endEmployer($personUuid, $orgUuid);

        if (is_wp_error($result)) {
            return $this->error($result);
        }

        if ($result['ended'] === [] && $result['errors'] === []) {
            // Ownership precheck saw an active card, so an empty end is a
            // lost race (another tab ended it first).
            return $this->error(new WP_Error('me_connection_not_active', 'That employer is no longer active.', ['status' => 409]));
        }

        return $this->response($result, $result['errors'] === [] ? 200 : 207);
    }

    /**
     * POST organizations: create an org, then connect the person to it.
     */
    public function handleCreateOrganization(\WP_REST_Request $request): \WP_REST_Response
    {
        $guard = $this->settings($request);

        if (is_wp_error($guard)) {
            return $this->error($guard);
        }

        if (! empty($guard['disable_create'])) {
            return $this->error(new WP_Error('me_create_disabled', 'Creating organizations is disabled.', ['status' => 403]));
        }

        if ($guard['display_membership_status_filter'] === Settings::MODE_INACTIVE) {
            return $this->error(new WP_Error('me_read_only', 'This block is in read-only mode.', ['status' => 403]));
        }

        $limited = $this->rateLimit('create_org', self::RATE_CREATE_ORG);

        if (is_wp_error($limited)) {
            return $this->error($limited);
        }

        $personUuid = $this->personUuid();
        $name = sanitize_text_field((string) $request->get_param('name'));

        if (mb_strlen($name) > 200) {
            $name = mb_substr($name, 0, 200);
        }

        $capError = $this->connectionCapError($personUuid);

        if (is_wp_error($capError)) {
            return $this->error($capError);
        }

        $created = $this->service()->createOrganization($name, sanitize_key((string) $request->get_param('org_type')), $guard['create_org_types'] ?: $guard['org_types']);

        if (is_wp_error($created)) {
            return $this->error($created);
        }

        $connected = $this->service()->addEmployer($personUuid, $created['uuid'], $guard['relationship_types_on_create']);

        if (is_wp_error($connected)) {
            return $this->error($connected);
        }

        return $this->response([
            'org_uuid' => $created['uuid'],
            'duplicate' => $created['duplicate'],
            'connection' => $connected,
        ], $connected['errors'] === [] ? 201 : 207);
    }

    /**
     * PATCH primary: point the person's primary_organization_id at one org.
     */
    public function handleSetPrimary(\WP_REST_Request $request): \WP_REST_Response
    {
        $guard = $this->settings($request);

        if (is_wp_error($guard)) {
            return $this->error($guard);
        }

        if (empty($guard['enable_primary'])) {
            return $this->error(new WP_Error('me_primary_disabled', 'Primary organization is disabled for this block.', ['status' => 403]));
        }

        if ($guard['display_membership_status_filter'] === Settings::MODE_INACTIVE) {
            return $this->error(new WP_Error('me_read_only', 'This block is in read-only mode.', ['status' => 403]));
        }

        $limited = $this->rateLimit('mutate', self::RATE_MUTATE);

        if (is_wp_error($limited)) {
            return $this->error($limited);
        }

        $personUuid = $this->personUuid();
        $orgUuid = sanitize_text_field((string) $request->get_param('org_uuid'));

        if (! wp_is_uuid($orgUuid)) {
            return $this->error(new WP_Error('me_org_invalid', 'That organization id is malformed.', ['status' => 400]));
        }

        $state = $this->service()->getState($personUuid, 1, $this->stateScope($guard));

        if (is_wp_error($state)) {
            return $this->error($state);
        }

        if (! $this->service()->listHasOrg($state['connections'], $orgUuid)) {
            return $this->error(new WP_Error(
                'me_org_not_connected',
                'The primary organization must be one of your own employers.',
                ['status' => 403]
            ));
        }

        $result = $this->service()->setPrimary($personUuid, $orgUuid);

        if (is_wp_error($result)) {
            return $this->error($result);
        }

        return $this->response(['ok' => true]);
    }

    /**
     * Read permission: logged-in member with a person uuid and no guest-payment
     * session.
     */
    private function canRead(): bool
    {
        return $this->isAuthenticatedMember();
    }

    /**
     * Write permission: read rules plus a closed door while a user switch is
     * active. Service-JWT writes are unaudited in MDP, so admin-style sessions
     * never mutate member relationships through this block.
     */
    private function canMutate(): bool
    {
        if (! $this->isAuthenticatedMember()) {
            return false;
        }

        if (class_exists('user_switching') && method_exists('user_switching', 'get_old_user')) {
            if (\user_switching::get_old_user()) {
                return false;
            }
        }

        return true;
    }

    private function isAuthenticatedMember(): bool
    {
        if (! is_user_logged_in()) {
            return false;
        }

        if ($this->personUuid() === '') {
            return false;
        }

        // A guest-payment session is logged in as the payer's target member,
        // so it must never pass for the member. The core class supports
        // argless instantiation precisely for checks like this one.
        if (class_exists(\Wicket\GuestPayment\WicketGuestPaymentCore::class)) {
            if ((new \Wicket\GuestPayment\WicketGuestPaymentCore())->is_guest_payment_session()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verify the embedded settings payload: decode, resolve, HMAC-check.
     *
     * @return array<string, mixed>|WP_Error The resolved settings.
     */
    private function settings(\WP_REST_Request $request): array|WP_Error
    {
        $raw = (string) $request->get_param('settings');
        $token = (string) $request->get_param('settings_token');

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return new WP_Error('me_settings_invalid', 'The settings payload is malformed.', ['status' => 400]);
        }

        $resolved = Settings::resolve($decoded);

        if (! Settings::verify($token, $resolved)) {
            return new WP_Error('me_settings_unsigned', 'The settings token failed verification.', ['status' => 400]);
        }

        return $resolved;
    }

    /**
     * The candidate org must exist and its type must sit in the settings
     * allow-list when one is configured. This is what "allow-checked set"
     * means server-side: the search UI narrowing is never trusted.
     *
     * @param array<string, mixed> $settings
     * @return true|WP_Error
     */
    private function verifyOrgAllowed(string $orgUuid, array $settings): bool|WP_Error
    {
        if ($orgUuid === '') {
            return new WP_Error('me_org_required', 'An organization uuid is required.', ['status' => 400]);
        }

        $type = $this->service()->fetchOrgType($orgUuid);

        if (is_wp_error($type)) {
            return $type;
        }

        if ($type === '') {
            return new WP_Error('me_org_not_found', 'That organization does not exist.', ['status' => 404]);
        }

        $allowed = array_values(array_filter(array_map('strval', (array) $settings['org_types'])));

        if ($allowed !== [] && ! in_array($type, $allowed, true)) {
            return new WP_Error('me_org_not_allowed', 'That organization is not on the allowed list.', ['status' => 403]);
        }

        return true;
    }

    /**
     * The plan caps how many employer cards one member may hold. Checked
     * before add and create; the create path also connects the creator.
     *
     * @return true|WP_Error
     */
    private function connectionCapError(string $personUuid): bool|WP_Error
    {
        $activeUuids = $this->service()->activeEmployerOrgUuids($personUuid);

        if (is_wp_error($activeUuids)) {
            return $activeUuids;
        }

        if (count($activeUuids) >= self::MAX_ACTIVE_CONNECTIONS) {
            return new WP_Error('me_connection_cap', 'The maximum number of employers is already reached.', ['status' => 403]);
        }

        return true;
    }

    /**
     * Minimal display scope for ownership re-checks: names only, no extras.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function stateScope(array $settings): array
    {
        return [
            'org_types' => $settings['org_types'],
            'display_org_type' => false,
            'display_org_address' => false,
            'display_membership_status' => false,
            'relationship_status_filter' => Settings::MODE_ALL,
            'orgs_per_page' => 100,
        ];
    }

    /**
     * Fixed-window per-user counter. The window end is stored inside the
     * value so a failed check can never extend its own window.
     *
     * @param array<int, int> $policy count => window seconds
     * @return true|WP_Error
     */
    private function rateLimit(string $action, array $policy): bool|WP_Error
    {
        $userId = get_current_user_id();

        foreach ($policy as $max => $window) {
            $key = sprintf('wicket_acc_me_rl_%s_%d_%d', $action, $userId, $window);
            $stored = get_transient($key);
            $now = time();

            if (is_array($stored) && ($stored['window_end'] ?? 0) > $now) {
                $count = (int) ($stored['count'] ?? 0);
            } else {
                $count = 0;
                set_transient($key, ['count' => 0, 'window_end' => $now + $window], $window);
            }

            if ($count >= $max) {
                return new WP_Error(
                    'me_rate_limited',
                    'Too many requests. Wait a moment and try again.',
                    ['status' => 429]
                );
            }

            set_transient($key, ['count' => $count + 1, 'window_end' => $now + $window], $window);
        }

        return true;
    }

    private function personUuid(): string
    {
        return (string) wicket_current_person_uuid();
    }

    private function service(): Service
    {
        return $this->service ?? new Service();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function response(array $data, int $status = 200, bool $noStore = false): \WP_REST_Response
    {
        $response = new \WP_REST_Response($data, $status);

        if ($noStore) {
            $response->header('Cache-Control', 'no-store');
        }

        return $response;
    }

    private function error(WP_Error $error): \WP_REST_Response
    {
        $status = $error->get_error_data('status');

        $response = new \WP_REST_Response([
            'code' => $error->get_error_code(),
            'message' => $error->get_error_message(),
        ], is_int($status) && $status >= 400 ? $status : 500);

        return $response;
    }
}
