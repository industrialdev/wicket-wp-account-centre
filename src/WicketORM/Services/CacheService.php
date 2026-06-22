<?php

declare(strict_types=1);

namespace WicketORM\Services;

use WicketORM\Helpers\ConfigHelper;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Standardized caching service for the library with version salt.
 */
class CacheService
{
    /**
     * Get cached data by key.
     *
     * @param string $key The cache key.
     * @return mixed|false Cached data or false if not found/disabled.
     */
    public function get(string $key)
    {
        if (!ConfigHelper::is_cache_enabled()) {
            return false;
        }

        $versionedKey = $this->getVersionedKey($key);

        return get_transient($versionedKey);
    }

    /**
     * Set data in cache.
     *
     * @param string   $key      The cache key.
     * @param mixed    $data     The data to cache.
     * @param int|null $duration Optional duration in seconds.
     * @return bool True if set, false otherwise.
     */
    public function set(string $key, $data, ?int $duration = null): bool
    {
        if (!ConfigHelper::is_cache_enabled()) {
            return false;
        }

        $cacheDuration = $duration ?? ConfigHelper::get_cache_duration();
        $versionedKey = $this->getVersionedKey($key);

        return set_transient($versionedKey, $data, $cacheDuration);
    }

    /**
     * Fetch from cache, or execute callback on miss and store the result.
     *
     * When cache is disabled, get() returns false and set() no-ops,
     * so the callback executes and returns without caching.
     *
     * Note: callbacks returning false will not be cached, since get() returns
     * false on miss and the strict check cannot distinguish stored false from absent.
     * Use get()/set() directly when false is a legitimate cached value.
     *
     * @param string           $key      Cache identifier.
     * @param callable():mixed $callback Evaluated on miss to generate the value.
     * @param int|null         $ttl      Custom TTL. Falls back to configured duration.
     * @return mixed Cached value or callback result.
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $cached = $this->get($key);
        if ($cached !== false) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Delete data from cache.
     *
     * @param string $key The cache key.
     * @return bool True if successful, false otherwise.
     */
    public function delete(string $key): bool
    {
        if (!ConfigHelper::is_cache_enabled()) {
            return false;
        }

        $versionedKey = $this->getVersionedKey($key);

        return delete_transient($versionedKey);
    }

    /**
     * Prepend version salt to cache key.
     *
     * @param string $key
     * @return string
     */
    private function getVersionedKey(string $key): string
    {
        $salt = ConfigHelper::get_cache_salt();

        return 'orgman_' . $salt . '_' . md5($key);
    }

    /**
     * Get the current per-membership generation counter.
     * Raw (non-versioned) transient so it survives global salt bumps.
     */
    public function getMembershipGeneration(string $membershipUuid): int
    {
        $val = get_transient('orgman_mgen_' . md5($membershipUuid));

        return $val !== false ? (int) $val : 1;
    }

    /**
     * Bump the per-membership generation counter, instantly staling
     * all paged member list cache entries for this membership in O(1).
     */
    public function bumpMembershipGeneration(string $membershipUuid): void
    {
        $key = 'orgman_mgen_' . md5($membershipUuid);
        $current = get_transient($key);
        $next = $current !== false ? (int) $current + 1 : 2;
        set_transient($key, $next, 7 * DAY_IN_SECONDS);
    }

    /**
     * Standardized method to clear member-related caches for an organization.
     * Bumps per-membership generation so all pages are instantly stale in O(1).
     */
    public function invalidateMemberCache(string $membershipUuid, ?string $orgUuid = null, ?string $personUuid = null): void
    {
        if (empty($membershipUuid)) {
            return;
        }

        $logger = \Wicket()->log();
        $logger->debug('CacheService: Invalidating member cache', [
            'source'          => 'wicket-orgman',
            'membership_uuid' => $membershipUuid,
            'org_uuid'        => $orgUuid,
            'person_uuid'     => $personUuid,
        ]);

        if ($personUuid && $orgUuid) {
            $this->delete('orgman_person_roles_' . md5($personUuid . $orgUuid));
            delete_transient('orgman_person_roles_' . md5($personUuid . $orgUuid));
        }

        $this->delete('orgman_membership_data_' . md5($membershipUuid));
        delete_transient('orgman_membership_data_' . md5($membershipUuid));

        // Bump generation — all pages for this membership miss on next read.
        $this->bumpMembershipGeneration($membershipUuid);

        // Legacy "initial" key formats (first 5 pages only, kept for transition).
        $commonPageSizes = [10, 15, 20, 25, 50, 100];
        for ($p = 1; $p <= 5; $p++) {
            foreach ($commonPageSizes as $size) {
                delete_transient('orgman_members_initial_' . md5($membershipUuid . '_' . $p . '_' . $size));
            }
        }
    }
}
