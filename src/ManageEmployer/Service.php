<?php

declare(strict_types=1);

namespace WicketAcc\ManageEmployer;

use Wicket\Client;
use WicketORM\Services\ConnectionService;
use WP_Error;

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
            'meta' => array_intersect_key(
                is_array($connections['meta'] ?? null) ? $connections['meta'] : [],
                array_flip(['total', 'total_pages', 'per_page', 'current_page'])
            ),
        ];
    }

    /**
     * Ransack org-index search. Type filters go to the org index only — the
     * Searchkick search endpoint silently returns zero rows for them (M0).
     *
     * @param array<int, string> $allowedTypes Settings org types; blank list means all.
     * @param array<int, string> $clientTypes  Client filter, intersected with the allowed list.
     * @param array<int, string> $connectedUuids Org uuids the person already connects to, flagged in results.
     * @return array{results: array, meta: array}|WP_Error
     */
    public function searchOrganizations(string $term, int $page, array $allowedTypes, array $clientTypes = [], array $connectedUuids = []): array|WP_Error
    {
        $client = $this->client();

        if (! $client) {
            return new WP_Error('me_client_unavailable', 'The MDP client is unavailable.');
        }

        $types = $this->intersectTypes($allowedTypes, $clientTypes);

        // A client filter disjoint from the allowed list matches nothing; it
        // must never widen the query back to all types.
        if ($allowedTypes !== [] && $types === []) {
            return ['results' => [], 'meta' => []];
        }

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
            error_log('[wicket-acc manage-employer] search failed: ' . $e->getMessage());

            return new WP_Error('me_search_failed', 'The organization search is temporarily unavailable.', ['status' => 502]);
        }

        $results = [];

        foreach (($response['data'] ?? []) as $row) {
            $uuid = (string) ($row['id'] ?? '');

            $results[] = [
                'uuid' => $uuid,
                'legal_name' => (string) ($row['attributes']['legal_name_en'] ?? ''),
                'type' => (string) ($row['attributes']['type'] ?? ''),
                'connected' => in_array($uuid, $connectedUuids, true),
            ];
        }

        return [
            'results' => $results,
            'meta' => is_array($response['meta'] ?? null) ? $response['meta'] : [],
        ];
    }

    /**
     * Every active person-side employer connection across all MDP pages.
     *
     * @return array<int, array>|WP_Error
     */
    public function activeEmployerConnections(string $personUuid): array|WP_Error
    {
        $connections = [];
        $page = 1;

        do {
            $result = $this->fetchConnections($personUuid, $page, 100);

            if (is_wp_error($result)) {
                return $result;
            }

            $rows = $result['data'];

            foreach ($this->filterByRelationshipStatus($rows, Settings::MODE_ACTIVE) as $row) {
                if ($this->connectionIsPersonSide($row, $personUuid)) {
                    $connections[] = $row;
                }
            }

            $totalPages = (int) ($result['meta']['total_pages'] ?? 0);
            $hasMore = $rows !== [] && ($totalPages > 0 ? $page < $totalPages : count($rows) === 100);
            $page++;

            if ($hasMore && $page > 100) {
                return new WP_Error('me_connections_failed', 'Your connections exceed the supported loading limit.', ['status' => 502]);
            }
        } while ($hasMore);

        return $connections;
    }

    /**
     * Org uuids behind every active employer connection.
     * Drives the connection cap and the already-connected search flag.
     *
     * @return array<int, string>|WP_Error
     */
    public function activeEmployerOrgUuids(string $personUuid): array|WP_Error
    {
        $connections = $this->activeEmployerConnections($personUuid);

        if (is_wp_error($connections)) {
            return $connections;
        }

        $uuids = [];

        foreach ($connections as $row) {
            $uuid = $this->connectionOrgUuid($row);

            if ($uuid !== '') {
                $uuids[$uuid] = true;
            }
        }

        return array_keys($uuids);
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

        $lockKey = md5($personUuid . '|' . $orgUuid);

        if (! $this->acquireMutationLock($lockKey)) {
            return new WP_Error('me_busy', 'An update to this employer is already in progress. Try again in a moment.', ['status' => 429]);
        }

        try {
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
        } finally {
            $this->releaseMutationLock($lockKey);
        }
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
     * The type never comes from the client alone: a single configured type is
     * applied server-side, and with several the client value must sit inside
     * the configured list.
     *
     * @param array<int, string> $allowedTypes Settings org types (create_org_types falling back to org_types).
     * @return array{uuid: string, duplicate: bool}|WP_Error
     */
    public function createOrganization(string $name, string $clientType, array $allowedTypes): array|WP_Error
    {
        $client = $this->client();

        if (! $client) {
            return new WP_Error('me_client_unavailable', 'The MDP client is unavailable.');
        }

        $name = trim($name);

        if ($name === '') {
            return new WP_Error('me_org_name_required', 'An organization name is required.', ['status' => 400]);
        }

        $allowed = $this->typeList($allowedTypes);

        if ($allowed === []) {
            return new WP_Error('me_org_type_unavailable', 'No organization types are configured for this block.', ['status' => 400]);
        }

        if (count($allowed) === 1) {
            $type = $allowed[0];
        } else {
            $candidate = sanitize_key($clientType);

            if ($candidate === '' || ! in_array($candidate, array_map('strtolower', $allowed), true)) {
                return new WP_Error('me_org_type_invalid', 'Pick one of the configured organization types.', ['status' => 400]);
            }

            $type = $candidate;
        }

        $lockKey = 'org-' . md5(strtolower($name) . '|' . $type);

        if (! $this->acquireMutationLock($lockKey)) {
            return new WP_Error('me_busy', 'An organization with this name is already being created.', ['status' => 429]);
        }

        try {
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
                error_log('[wicket-acc manage-employer] org create failed: ' . $e->getMessage());

                return new WP_Error('me_org_create_failed', 'The organization could not be created.', ['status' => 502]);
            }

            $uuid = (string) ($response['data']['id'] ?? '');

            if ($uuid === '') {
                return new WP_Error('me_org_create_failed', 'The MDP response carried no organization uuid.', ['status' => 502]);
            }

            return ['uuid' => $uuid, 'duplicate' => false];
        } finally {
            $this->releaseMutationLock($lockKey);
        }
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
            error_log('[wicket-acc manage-employer] primary write failed: ' . $e->getMessage());

            return new WP_Error('me_primary_write_failed', 'The primary organization could not be saved.', ['status' => 502]);
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
     * org side of an ACTIVE person_to_organization connection.
     *
     * @param array<int, array> $connections
     */
    public function ownsActiveConnection(array $connections, string $personUuid, string $connectionUuid, string $orgUuid): bool
    {
        foreach ($connections as $row) {
            if (($row['id'] ?? '') !== $connectionUuid) {
                continue;
            }

            // Absent attribute is fine: the index query already filtered by
            // connection type. Present-but-different means a foreign row.
            $connectionType = (string) ($row['attributes']['connection_type'] ?? '');

            if ($connectionType !== '' && $connectionType !== 'person_to_organization') {
                continue;
            }

            if ($this->connectionOrgUuid($row) !== $orgUuid) {
                continue;
            }

            return $this->connectionIsActive($row) && $this->connectionIsPersonSide($row, $personUuid);
        }

        return false;
    }

    /**
     * True when an ACTIVE connection row in the list points at the org.
     *
     * @param array<int, array> $connections
     */
    public function listHasOrg(array $connections, string $orgUuid): bool
    {
        foreach ($connections as $row) {
            if ($this->connectionOrgUuid($row) === $orgUuid && $this->connectionIsActive($row)) {
                return true;
            }
        }

        return false;
    }

    private function connectionIsActive(array $row): bool
    {
        $endsAt = (string) ($row['attributes']['ends_at'] ?? '');

        return $endsAt === '' || strtotime($endsAt) > time();
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
            error_log('[wicket-acc manage-employer] org lookup failed: ' . $e->getMessage());

            return new WP_Error('me_org_lookup_failed', 'That organization could not be verified.', ['status' => 502]);
        }

        return (string) ($response['data']['attributes']['type'] ?? '');
    }

    /**
     * Relationship types the block may create: the settings selection minus
     * protected types, compared with hyphen and underscore treated as one so
     * a hyphenated tenant slug cannot slip past the company_admin guard.
     *
     * @param array<int, string> $types
     * @return array<int, string>
     */
    public function creatableTypes(array $types): array
    {
        $protected = array_map(
            static fn (string $type): string => str_replace(['-', ' '], '_', strtolower($type)),
            self::PROTECTED_TYPES
        );

        return array_values(array_filter(
            $this->slugList($types),
            static fn (string $type): bool => ! in_array(str_replace('-', '_', $type), $protected, true)
        ));
    }

    /**
     * Client type filter intersected with the allowed list. An empty allowed
     * list means all types; an empty client filter means the whole allowed
     * list — never everything. A disjoint intersection returns empty and the
     * search route answers no results.
     *
     * @param array<int, string> $allowedTypes
     * @param array<int, string> $clientTypes
     * @return array<int, string>
     */
    public function intersectTypes(array $allowedTypes, array $clientTypes): array
    {
        $allowed = $this->typeList($allowedTypes);

        if ($allowed === []) {
            return $this->slugList($clientTypes);
        }

        if ($clientTypes === []) {
            return $allowed;
        }

        return array_values(array_intersect($allowed, $this->typeList($clientTypes)));
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
            error_log('[wicket-acc manage-employer] connections fetch failed: ' . $e->getMessage());

            return new WP_Error('me_connections_failed', 'Your connections could not be loaded.', ['status' => 502]);
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
     * settings display. One fetch per uuid: `filter[id_in]` is not an M0-verified
     * index filter, and unknown filters silently return zero rows there.
     *
     * @param array<int, string> $uuids
     * @param array{display_org_type: bool, display_org_address: bool} $settings
     * @return array<string, array>|WP_Error
     */
    private function fetchOrgs(array $uuids, array $settings): array|WP_Error
    {
        $orgs = [];

        foreach ($uuids as $uuid) {
            $org = $this->fetchOrg($uuid, $settings);

            if (is_wp_error($org)) {
                return $org;
            }

            if ($org !== null) {
                $orgs[$uuid] = $org;
            }
        }

        return $orgs;
    }

    /**
     * @return array{uuid: string, legal_name: string, type?: string, addresses?: array}|null|WP_Error null when the org no longer exists.
     */
    private function fetchOrg(string $uuid, array $settings): array|null|WP_Error
    {
        $client = $this->client();

        if (! $client) {
            return new WP_Error('me_client_unavailable', 'The MDP client is unavailable.');
        }

        $fields = ['legal_name_en'];

        if (! empty($settings['display_org_type'])) {
            $fields[] = 'type';
        }

        $query = ['fields' => ['organizations' => implode(',', $fields)]];

        if (! empty($settings['display_org_address'])) {
            $query['include'] = 'addresses';
        }

        try {
            $response = $client->get('organizations/' . rawurlencode($uuid), ['query' => $query]);
        } catch (\Throwable $e) {
            error_log('[wicket-acc manage-employer] org fetch failed: ' . $e->getMessage());

            return new WP_Error('me_orgs_failed', 'Organization details could not be loaded.', ['status' => 502]);
        }

        $attributes = $response['data']['attributes'] ?? null;

        if (! is_array($attributes)) {
            return null;
        }

        $org = [
            'uuid' => (string) ($response['data']['id'] ?? $uuid),
            'legal_name' => (string) ($attributes['legal_name_en'] ?? ''),
        ];

        if (! empty($settings['display_org_type'])) {
            $org['type'] = (string) ($attributes['type'] ?? '');
        }

        if (! empty($settings['display_org_address'])) {
            $org['addresses'] = $this->includedAddressesFor(is_array($response) ? $response : [], $uuid);
        }

        return $org;
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
            error_log('[wicket-acc manage-employer] primary read failed: ' . $e->getMessage());

            return new WP_Error('me_primary_read_failed', 'The primary organization could not be read.', ['status' => 502]);
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
                    'page' => ['number' => 1, 'size' => 25],
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[wicket-acc manage-employer] duplicate check failed: ' . $e->getMessage());

            return new WP_Error('me_org_duplicate_check_failed', 'The duplicate check could not run.', ['status' => 502]);
        }

        $needle = $this->lowerLabel(trim($name));

        foreach (($response['data'] ?? []) as $row) {
            $existing = $this->lowerLabel(trim((string) ($row['attributes']['legal_name_en'] ?? '')));

            if ($existing !== '' && $existing === $needle) {
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
     * Settings-supplied type values, case-preserving: MDP type filters must
     * match the stored values exactly, and slugList's lowercase would silently
     * empty the result for mixed-case slugs.
     *
     * @param array<int, string> $types
     * @return array<int, string>
     */
    private function typeList(array $types): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($type): string => trim((string) $type),
            $types
        ))));
    }

    private function lowerLabel(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    /**
     * Atomic per-person/org (or per-name) mutation lock. add_option fails when
     * the row exists, which no transient or cache primitive guarantees across
     * the object-cache configurations this stack runs.
     */
    private function acquireMutationLock(string $key): bool
    {
        $option = 'wicket_acc_me_lock_' . $key;
        $now = time();
        $existing = get_option($option);

        if ($existing === false) {
            return add_option($option, $now, '', false);
        }

        if (is_int($existing) && ($now - $existing) < 30) {
            return false;
        }

        // Stale lock from a crashed request: take over.
        update_option($option, $now);

        return true;
    }

    private function releaseMutationLock(string $key): void
    {
        delete_option('wicket_acc_me_lock_' . $key);
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
