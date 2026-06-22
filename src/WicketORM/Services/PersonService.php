<?php

/**
 * Person Service for Org Management.
 */

declare(strict_types=1);

namespace WicketORM\Services;

use WP_Error;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Provides helpers for interacting with Wicket person records.
 */
class PersonService
{
    /**
     * Create or locate a person in Wicket and ensure key profile fields are updated.
     *
     * @param array $personData
     * @return string|WP_Error Person UUID or error.
     */
    public function createOrUpdatePerson(array $personData)
    {
        $extras = [];
        if (isset($personData['job_title'])) {
            $extras['job_title'] = $personData['job_title'];
        }
        if (isset($personData['phone'])) {
            $extras['phone'] = $personData['phone'];
        }

        return wicket_create_or_get_person(
            (string) ($personData['first_name'] ?? ''),
            (string) ($personData['last_name'] ?? ''),
            (string) ($personData['email'] ?? ''),
            $extras
        );
    }

    /**
     * Create or get a person by email address.
     *
     * This method provides backward compatibility with the legacy function signature.
     *
     * @param string $first_name The person's first name.
     * @param string $last_name The person's last name.
     * @param string $email The person's email address.
     * @param array  $extras Optional extra data to update on the person.
     * @return string|WP_Error The person UUID or WP_Error on failure.
     */
    public function createOrGetPerson($first_name, $last_name, $email, $extras = [])
    {
        return wicket_create_or_get_person((string) $first_name, (string) $last_name, (string) $email, is_array($extras) ? $extras : []);
    }
}
