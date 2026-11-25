<?php

declare(strict_types=1);

namespace WicketORM\Helpers;

if (!defined('ABSPATH') && !defined('WICKET_DOING_TESTS')) {
    exit;
}

/**
 * Signal payloads for the Edit Permissions modal load endpoint (process/member-edit-data).
 *
 * Values are staged under editPermissionsStaged* keys with the requested person
 * uuid echoed back; the modal applies them only while the echo still matches the
 * open member, so a stale in-flight response cannot repopulate the next member.
 */
class MemberEditDataSignals
{
    /**
     * Patch signals carrying a successfully loaded member for the modal guard.
     *
     * @param string $personUuid Person uuid the endpoint was asked about.
     * @param string $description Relationship description.
     * @param array<int, mixed> $roles Org role slugs; non-scalar entries dropped.
     * @param string $relationshipType Relationship type.
     * @return array<string, mixed>
     */
    public static function successPayload(
        string $personUuid,
        string $description,
        array $roles,
        string $relationshipType
    ): array {
        return [
            'editPermissionsStagedDescription' => $description,
            'editPermissionsStagedRoles' => array_values(array_map('strval', array_filter($roles, 'is_scalar'))),
            'editPermissionsStagedRelationshipType' => $relationshipType,
            'editPermissionsStagedLoadFailed' => false,
            'editPermissionsLoadedUuid' => $personUuid,
        ];
    }

    /**
     * Patch signals releasing the modal into the load-failed state, still guarded
     * by the echoed uuid.
     *
     * @param string $personUuid Person uuid the endpoint was asked about.
     * @return array<string, mixed>
     */
    public static function failurePayload(string $personUuid): array
    {
        return [
            'editPermissionsStagedLoadFailed' => true,
            'editPermissionsLoadedUuid' => $personUuid,
        ];
    }
}
