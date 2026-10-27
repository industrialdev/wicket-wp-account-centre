<?php

declare(strict_types=1);

namespace WicketAcc\ManageEmployer;

use Wicket\Client;
use WicketORM\Services\ConnectionService;

// No direct access
defined('ABSPATH') || exit;

/**
 * MDP transport for the manage-employer block.
 *
 * Non-memoized fetches only: the member sees their own state as the API sees
 * it, never a cached copy from another code path. Never call
 * wicket_get_person_connections() here — it memoizes per request and this
 * block must observe mutations it just performed.
 *
 * The REST layer is the only authorization boundary: the shared client
 * (WACC()->Mdp()->initClient()) authorizes as the settings service person, so
 * MDP policies cannot protect members from each other. Every uuid arriving in
 * a request body must be proven to belong to the person before use.
 *
 * @see atlas://plans/feature-acc-manage-employer-block.md
 */
final class Service
{
    /**
     * Relationship types this block may never create or end.
     *
     * A member dropping their own company_admin connection would surrender
     * roster privileges without any admin path back. Extend only via the
     * filter below, never by editing callers.
     */
    private const PROTECTED_TYPES = ['company_admin'];

    public function __construct(
        private readonly ?Client $client = null,
        private readonly ?ConnectionService $connections = null
    ) {
    }

    /**
     * Current and past person-to-organization connections, the primary uuid,
     * and the organization details the block settings ask for.
     *
     * @param array{org_types: array, display_org_type: bool, display_org_address: bool, display_membership_status: bool, relationship_status_filter: string, orgs_per_page: int} $settings
     * @return array{connections: array, orgs: array<string, array>, primary_uuid: ?string, meta: array}|WP_Error
     */
    public function getState(string $personUuid, int $page, array $settings): array|WP_Error
    {
        $client = $this->client();

        if (! $client) {
            return new WP_Error('me_client_unavailable', 'The MDP client is unavailable.');
        }

        $connections = $this->fetchConnections($personUuid, $page, $settings['orgs_per_page']);

        if (is_wp_error($connections)) {
            return $connections;
        }

        $rows = $this->filterByRelationshipStatus($connections['data'] ?? [], $settings['relationship_status_filter']);

        $orgUuids = [];
        foreach ($rows as $row) {
            $orgUuid = $this->connectionOrgUuid($row);
            if ($orgUuid !== '') {
                $orgUuids[$orgUuid] = true;
            }
        }

        $orgs = $this->fetchOrgs(array_keys($orgUuids), $settings);

        if (is_wp_error($orgs)) {
            return $orgs;
        }

        $primary = $this->fetchPrimaryUuid($personUuid);

        if (is_wp_error($primary)) {
            return $primary;
        }

        return [
            'connections' => $rows,
            'orgs' => $orgs,
            'primary_uuid' => $primary,
            'meta' => $connections['meta'] ?? [],
        ];
    }

    /**
     * Ransack org-index search. Type filters go to the org index only — the
     * Searchkick search endpoint silently returns zero rows for them (M0).
     *
     * @param array<int, string> $allowedTypes Settings org types; blank list means all.
     * @param array<int, string> $clientTypes  Client filter, intersected with the allowed list.
     * @return array{results: array, meta: array}|WP_Error
     */
    public function searchOrganizations(string $term, int $page, array $allowedTypes, array $clientTypes = []): array|WP_Error
    {
        $client = $this->client();

        if (! $client) {
            return new WP_Error('me_client_unavailable', 'The MDP client is unavailable.');
        }

        $types = $this->intersectTypes($allowedTypes, $clientTypes);

        $query = [
            'filter' => ['legal_name_en_cont' => $term],
            'fields' => ['organizations' => 'legal_name_en,type'],
            'page' => ['number' => $page, 'size' => 10],
        ];

        if ($types !== []) {
            $query['filter']['type_in'] = $types;
        }

        try {
            $response = $client->get('organizations', ['query' => $query]);
        } catch (\Throwable $e) {
            return new WP_Error('me_search_failed', $e->getMessage());
        }

        $results = [];

        foreach (($response['data'] ?? []) as $row) {
            $results[] = [
                'uuid' => (string) ($row['id'] ?? ''),
                'legal_name' => (string) ($row['attributes']['legal_name_en'] ?? ''),
                'type' => (string) ($row['attributes']['type'] ?? ''),
            ];
        }

        return [
            'results' => $results,
            'meta' => $response['meta'] ?? [],
        ];
    }

    /**
     * Add an employer card: create one connection per selected relationship type.
     *
     * Card equals organization, so an existing active connection to the same
     * org is an idempotent no-op. Creates run sequentially with per-type
     * failure reporting because MDP has no uniqueness on person+org+type and
     * would happily return 201 for every duplicate.
     *
     * @param array<int, string> $relationshipTypes Settings-provided types; never request data.
     * @return array{created: array<int, string>, skipped_existing: bool, errors: array<int, string>}|WP_Error
     */
    public function addEmployer(string $personUuid, string $orgUuid, array $relationshipTypes): array|WP_Error
    {
        $connectionService = $this->connectionService();

        if (! $connectionService) {
            return new WP_Error('me_client_unavailable', 'The MDP client is unavailable.');
        }

        $relationshipTypes = $this->creatableTypes($relationshipTypes);

        if ($relationshipTypes === []) {
            return new WP_Error('me_no_relationship_types', 'No creatable relationship types are configured for this block.');
        }

        $active = $connectionService->getActivePersonOrganizationConnections($personUuid, $orgUuid);

        if (is_wp_error($active)) {
            return $active;
        }

        if ($active !== []) {
            return ['created' => [], 'skipped_existing' => true, 'errors' => []];
        }

        $created = [];
        $errors = [];

        foreach ($relationshipTypes as $type) {
            $payload = $connectionService->buildConnectionPayload($personUuid, $orgUuid, 'person_to_organization', $type);

            if (is_wp_error($payload)) {
                $errors[] = $payload->get_error_message();

                continue;
            }

            $result = $connectionService->createConnection($payload);

            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();

                continue;
            }

            $created[] = $type;
        }

        return [
            'created' => $created,
            'skipped_existing' => false,
            'errors' => $errors,
        ];
    }

    /**
     * End every active connection between the person and one organization.
     *
     * Delegates to the WicketORM service so protected types survive and the
     * end dates follow the shared UTC/MDP-timezone rules.
     *
     * @return array{ended: array<int, string>, errors: array<int, string>}|WP_Error
     */
    public function endEmployer(string $personUuid, string $orgUuid): array|WP_Error
    {
        $connectionService = $this->connectionService();

        if (! $connectionService) {
            return new WP_Error('me_client_unavailable', 'The MDP client is unavailable.');
        }

        return $connectionService->endAllActivePersonOrganizationConnections($personUuid, $orgUuid, self::PROTECTED_TYPES);
    }

    /**
     * Create an organization after a server-side duplicate check, then the
     * caller connects the person with the settings relationship types.
     *
     * @return array{uuid: string, duplicate: bool}|WP_Error
     */
    public function createOrganization(string $name, string $type, array $allowedTypes): array|WP_Error
    {
        $client = $this->client();

        if (! $client) {
            return new WP_Error('me_client_unavailable', 'The MDP client is unavailable.');
        }

        $name = trim($name);
        $type = $this->coerceType($type, $allowedTypes);

        if ($name === '') {
            return new WP_Error('me_org_name_required', 'An organization name is required.');
        }

        if ($type === '') {
            return new WP_Error('me_org_type_required', 'An organization type is required.');
        }

        $duplicate = $this->findDuplicateOrganization($name, $type);

        if (is_wp_error($duplicate)) {
            return $duplicate;
        }

        if ($duplicate !== '') {
            return ['uuid' => $duplicate, 'duplicate' => true];
        }

        try {
            $response = $client->post('organizations', [
                'json' => [
                    'data' => [
                        'type' => 'organizations',
                        'attributes' => [
                            'legal_name' => $name,
                            'legal_name_en' => $name,
                            'type' => $type,
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return new WP_Error('me_org_create_failed', $e->getMessage());
        }

        $uuid = (string) ($response['data']['id'] ?? '');

        if ($uuid === '') {
            return new WP_Error('me_org_create_failed', 'The MDP response carried no organization uuid.');
        }

        return ['uuid' => $uuid, 'duplicate' => false];
    }

    /**
     * Point the person's primary_organization_id at one org, then verify by
     * re-read. A flag-off tenant answers 200 while changing nothing, so the
     * re-read is the only honest success signal.
     *
     * @return true|WP_Error
     */
    public function setPrimary(string $personUuid, string $orgUuid): bool|WP_Error
    {
        $client = $this->client();

        if (! $client) {
            return new WP_Error('me_client_unavailable', 'The MDP client is unavailable.');
        }

        try {
            $client->patch('people/' . rawurlencode($personUuid), [
                'json' => [
                    'data' => [
                        'type' => 'people',
                        'id' => $personUuid,
                        'attributes' => ['primary_organization_id' => $orgUuid],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return new WP_Error('me_primary_write_failed', $e->getMessage());
        }

        // Bounded re-read: the fallback worker runs async, but the PATCH echoes
        // synchronously on flag-on tenants. Three tries cover a slow echo.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            if ($attempt > 0) {
                sleep(1);
            }

            $primary = $this->fetchPrimaryUuid($personUuid);

            if (is_wp_error($primary)) {
                return $primary;
            }

            if ($primary === $orgUuid) {
                return true;
            }
        }

        return new WP_Error(
            'me_primary_not_applied',
            'The MDP accepted the request but the primary organization did not change. The tenant flag is likely off.'
        );
    }

    /**
     * True when the uuid appears in the person's own connection list as the
     * org side of an active person_to_organization connection.
     *
     * @param array<int, array> $connections
     */
    public function ownsActiveConnection(array $connections, string $personUuid, string $connectionUuid, string $orgUuid): bool
    {
        foreach ($connections as $row) {
            if (($row['id'] ?? '') !== $connectionUuid) {
                continue;
            }

            if (($row['attributes']['connection_type'] ?? '') !== 'person_to_organization') {
                continue;
            }

            if ($this->connectionOrgUuid($row) !== $orgUuid) {
                continue;
            }

            return $this->connectionIsPersonSide($row, $personUuid);
        }

        return false;
    }

    /**
     * True when any connection row in the list points at the org.
     *
     * @param array<int, array> $connections
     */
    public function listHasOrg(array $connections, string $orgUuid): bool
    {
        foreach ($connections as $row) {
            if ($this->connectionOrgUuid($row) === $orgUuid) {
                return true;
            }
        }

        return false;
    }

    /**
     * One organization's type, for allow-list checks on candidate adds.
     */
    public function fetchOrgType(string $orgUuid): string|WP_Error
    {
        $client = $this->client();

        if (! $client) {
            return new WP_Error('me_client_unavailable', 'The MDP client is unavailable.');
        }

        try {
            $response = $client->get('organizations/' . rawurlencode($orgUuid), [
                'query' => ['fields' => ['organizations' => 'type']],
            ]);
        } catch (\Throwable $e) {
            return new WP_Error('me_org_lookup_failed', $e->getMessage());
        }

        return (string) ($response['data']['attributes']['type'] ?? '');
    }

    /**
     * Relationship types the block may create: the settings selection minus
     * protected types.
     *
     * @param array<int, string> $types
     * @return array<int, string>
     */
    public function creatableTypes(array $types): array
    {
        return array_values(array_diff($this->slugList($types), self::PROTECTED_TYPES));
    }

    /**
     * Client type filter intersected with the allowed list. An empty allowed
     * list means all types; an empty client filter means the whole allowed
     * list — never everything.
     *
     * @param array<int, string> $allowedTypes
     * @param array<int, string> $clientTypes
     * @return array<int, string>
     */
    public function intersectTypes(array $allowedTypes, array $clientTypes): array
    {
        $allowed = $this->slugList($allowedTypes);

        if ($allowed === []) {
            return $this->slugList($clientTypes);
        }

        if ($clientTypes === []) {
            return $allowed;
        }

        return array_values(array_intersect($allowed, $this->slugList($clientTypes)));
    }

    /**
     * Fetch one page of the person's own connections, person-to-organization only.
     *
     * @return array{data: array, meta: array}|WP_Error
     */
    private function fetchConnections(string $personUuid, int $page, int $perPage): array|WP_Error
    {
        $client = $this->client();

        if (! $client) {
            return new WP_Error('me_client_unavailable', 'The MDP client is unavailable.');
        }

        try {
            $response = $client->get('people/' . rawurlencode($personUuid) . '/connections', [
                'query' => [
                    'filter' => ['connection_type_eq' => 'person_to_organization'],
                    'sort' => '-created_at',
                    'page' => ['number' => $page, 'size' => $perPage],
                ],
            ]);
        } catch (\Throwable $e) {
            return new WP_Error('me_connections_failed', $e->getMessage());
        }

        if (! isset($response['data']) || ! is_array($response['data'])) {
            return new WP_Error('me_connections_failed', 'The MDP response carried no connection list.');
        }

        return [
            'data' => $response['data'],
            'meta' => is_array($response['meta'] ?? null) ? $response['meta'] : [],
        ];
    }

    /**
     * Organization details for the state payload, scoped to what the block
     * settings display.
     *
     * @param array<int, string> $uuids
     * @param array{display_org_type: bool, display_org_address: bool} $settings
     * @return array<string, array>|WP_Error
     */
    private function fetchOrgs(array $uuids, array $settings): array|WP_Error
    {
        if ($uuids === []) {
            return [];
        }

        $client = $this->client();

        if (! $client) {
            return new WP_Error('me_client_unavailable', 'The MDP client is unavailable.');
        }

        $fields = ['legal_name_en'];

        if (! empty($settings['display_org_type'])) {
            $fields[] = 'type';
        }

        $query = [
            'filter' => ['id_in' => $uuids],
            'fields' => ['organizations' => implode(',', $fields)],
            'per_page' => count($uuids),
        ];

        if (! empty($settings['display_org_address'])) {
            $query['include'] = 'addresses';
        }

        try {
            $response = $client->get('organizations', ['query' => $query]);
        } catch (\Throwable $e) {
            return new WP_Error('me_orgs_failed', $e->getMessage());
        }

        $orgs = [];

        foreach (($response['data'] ?? []) as $row) {
            $uuid = (string) ($row['id'] ?? '');

            if ($uuid === '') {
                continue;
            }

            $org = [
                'uuid' => $uuid,
                'legal_name' => (string) ($row['attributes']['legal_name_en'] ?? ''),
            ];

            if (! empty($settings['display_org_type'])) {
                $org['type'] = (string) ($row['attributes']['type'] ?? '');
            }

            if (! empty($settings['display_org_address'])) {
                $org['addresses'] = $this->includedAddressesFor($response, $uuid);
            }

            $orgs[$uuid] = $org;
        }

        return $orgs;
    }

    /**
     * Address records under a JSONAPI `included` payload, matched to one org.
     *
     * @return array<int, array>
     */
    private function includedAddressesFor(array $response, string $orgUuid): array
    {
        $addresses = [];

        foreach (($response['included'] ?? []) as $row) {
            if (($row['type'] ?? '') !== 'addresses') {
                continue;
            }

            $ownerUuid = (string) ($row['relationships']['organization']['data']['id'] ?? '');

            if ($ownerUuid !== $orgUuid) {
                continue;
            }

            $addresses[] = $row['attributes'] ?? [];
        }

        return $addresses;
    }

    /**
     * @return string|WP_Error The primary organization uuid, empty string when unset.
     */
    private function fetchPrimaryUuid(string $personUuid): string|WP_Error
    {
        $client = $this->client();

        if (! $client) {
            return new WP_Error('me_client_unavailable', 'The MDP client is unavailable.');
        }

        try {
            $response = $client->get('people/' . rawurlencode($personUuid), [
                'query' => ['include' => 'primary_organization'],
            ]);
        } catch (\Throwable $e) {
            return new WP_Error('me_primary_read_failed', $e->getMessage());
        }

        return (string) ($response['data']['relationships']['primary_organization']['data']['id'] ?? '');
    }

    /**
     * Duplicate probe on the org index: substring fetch, exact compare. The
     * ransack eq predicate is case-sensitive and unverified, so the honest
     * check is contains + PHP-side exact match.
     */
    private function findDuplicateOrganization(string $name, string $type): string|WP_Error
    {
        $client = $this->client();

        if (! $client) {
            return new WP_Error('me_client_unavailable', 'The MDP client is unavailable.');
        }

        try {
            $response = $client->get('organizations', [
                'query' => [
                    'filter' => [
                        'legal_name_en_cont' => $name,
                        'type_eq' => $type,
                    ],
                    'fields' => ['organizations' => 'legal_name_en,type'],
                    'per_page' => 25,
                ],
            ]);
        } catch (\Throwable $e) {
            return new WP_Error('me_org_duplicate_check_failed', $e->getMessage());
        }

        $needle = mb_strtolower(trim($name));

        foreach (($response['data'] ?? []) as $row) {
            $existing = mb_strtolower(trim((string) ($row['attributes']['legal_name_en'] ?? '')));

            if ($existing === $needle) {
                return (string) ($row['id'] ?? '');
            }
        }

        return '';
    }

    /**
     * @param array<int, array> $rows
     * @return array<int, array>
     */
    private function filterByRelationshipStatus(array $rows, string $status): array
    {
        if ($status === Settings::MODE_ALL) {
            return $rows;
        }

        $wantActive = $status === Settings::MODE_ACTIVE;

        return array_values(array_filter($rows, static function (array $row) use ($wantActive): bool {
            $endsAt = (string) ($row['attributes']['ends_at'] ?? '');
            $active = $endsAt === '' || strtotime($endsAt) > time();

            return $active === $wantActive;
        }));
    }

    /**
     * The org side of a connection record, across the two relationship shapes
     * the MDP serializes (`to` on writes, `organization` on indexes).
     */
    private function connectionOrgUuid(array $row): string
    {
        $relationships = $row['relationships'] ?? [];

        foreach (['to', 'organization'] as $key) {
            $candidate = (string) ($relationships[$key]['data']['id'] ?? '');

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private function connectionIsPersonSide(array $row, string $personUuid): bool
    {
        $relationships = $row['relationships'] ?? [];
        $from = (string) ($relationships['from']['data']['id'] ?? $relationships['person']['data']['id'] ?? '');

        return $from === $personUuid;
    }

    /**
     * @param array<int, string> $types
     * @return array<int, string>
     */
    private function slugList(array $types): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($type): string => sanitize_key((string) $type),
            $types
        ))));
    }

    /**
     * @param array<int, string> $allowedTypes
     */
    private function coerceType(string $type, array $allowedTypes): string
    {
        $candidate = sanitize_key($type);

        if ($candidate === '') {
            return '';
        }

        $allowed = $this->slugList($allowedTypes);

        if ($allowed === []) {
            return $candidate;
        }

        return in_array($candidate, $allowed, true) ? $candidate : '';
    }

    private function client(): ?Client
    {
        if ($this->client instanceof Client) {
            return $this->client;
        }

        $client = \WACC()->Mdp()->initClient();

        return $client instanceof Client ? $client : null;
    }

    private function connectionService(): ?ConnectionService
    {
        if ($this->connections instanceof ConnectionService) {
            return $this->connections;
        }

        if (! class_exists(ConnectionService::class)) {
            return null;
        }

        return new ConnectionService();
    }
}
