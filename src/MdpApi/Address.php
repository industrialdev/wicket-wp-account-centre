<?php

declare(strict_types=1);

namespace WicketAcc\MdpApi;

use Exception;
use GuzzleHttp\Exception\RequestException;

// No direct access
defined('ABSPATH') || exit;

/**
 * Handles MDP Address related API endpoints.
 */
class Address extends Init
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Retrieve a single address resource by its UUID.
     *
     * @param string $uuid The UUID of the address to fetch.
     * @return object|false The Wicket SDK Address resource object on success, false on failure.
     */
    public function getAddressByUuid(string $uuid): object|false
    {
        if (empty($uuid)) {
            WACC()->Log->warning('Address UUID cannot be empty.', ['source' => __METHOD__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            // initClient() already logs the error.
            return false;
        }

        try {
            return $client->addresses->fetch($uuid);
        } catch (RequestException $e) {
            $response_code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            WACC()->Log->error(
                'RequestException while fetching address by UUID.',
                [
                    'source' => __METHOD__,
                    'uuid' => $uuid,
                    'status_code' => $response_code,
                    'message' => $e->getMessage(),
                ]
            );

            return false;
        } catch (Exception $e) {
            WACC()->Log->error(
                'Generic Exception while fetching address by UUID.',
                [
                    'source' => __METHOD__,
                    'uuid' => $uuid,
                    'message' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * Bulk update attributes for multiple addresses.
     *
     * @param array $addressUpdates An array of address update instructions.
     *                              Each instruction should be an associative array with:
     *                              - 'uuid' (string): The UUID of the address to update.
     *                              - 'attributes' (array): Associative array of attributes to change.
     *                              Example: [
     *                                  ['uuid' => 'uuid1', 'attributes' => ['street_1' => '123 Main St', 'primary' => true]],
     *                                  ['uuid' => 'uuid2', 'attributes' => ['city' => 'New City']]
     *                              ]
     * @return array An array with 'success' (bool) and 'results' (array) containing 'updated' and 'failed' lists.
     */
    public function bulkUpdateAttributes(array $addressUpdates): array
    {
        $client = $this->initClient();
        if (!$client) {
            return [
                'success' => false,
                'results' => [
                    'updated' => [],
                    'failed' => [['uuid' => 'N/A', 'error' => 'API client initialization failed.']],
                ],
            ];
        }

        $results = ['updated' => [], 'failed' => []];
        $overallSuccess = true;

        $readOnlyAttributes = [
            'uuid', 'type_external_id', 'formatted_address_label', 'latitude', 'longitude',
            'created_at', 'updated_at', 'deleted_at', 'active', 'consent',
            'consent_third_party', 'consent_directory',
        ];

        foreach ($addressUpdates as $updateInstruction) {
            if (!is_array($updateInstruction) || empty($updateInstruction['uuid']) || !isset($updateInstruction['attributes']) || !is_array($updateInstruction['attributes'])) {
                $results['failed'][] = ['uuid' => $updateInstruction['uuid'] ?? 'Unknown', 'error' => 'Invalid update instruction format.'];
                $overallSuccess = false;
                continue;
            }

            $addressUuid = $updateInstruction['uuid'];
            $attributesToUpdate = $updateInstruction['attributes'];

            // Remove read-only attributes
            foreach ($readOnlyAttributes as $key) {
                unset($attributesToUpdate[$key]);
            }

            // Sanitize attributes if the global helper exists
            if (function_exists('wicket_filter_null_and_blank')) {
                $attributesToUpdate = wicket_filter_null_and_blank($attributesToUpdate);
            }

            if (empty($attributesToUpdate)) {
                // If after filtering, there are no attributes to update, consider it a success (no-op)
                // or mark as a specific kind of notice if needed. For now, skipping.
                $results['updated'][] = $addressUuid; // Or some other status like 'no_changes_needed'
                continue;
            }

            $payload = [
                'data' => [
                    'id' => $addressUuid,
                    'type' => 'addresses',
                    'attributes' => $attributesToUpdate,
                ],
            ];

            try {
                $client->patch("addresses/{$addressUuid}", ['json' => $payload]);
                $results['updated'][] = $addressUuid;
            } catch (RequestException $e) {
                $errorMsg = 'API Error: ' . $e->getMessage();
                $context = [
                    'source' => __METHOD__,
                    'address_uuid' => $addressUuid,
                    'payload' => $payload,
                    'original_exception' => $e->getMessage(),
                ];
                if ($e->hasResponse()) {
                    $context['statusCode'] = $e->getResponse()->getStatusCode();
                    $context['responseBody'] = $e->getResponse()->getBody()->getContents();
                }
                WACC()->Log->error("Failed to update address (UUID: {$addressUuid}).", $context);
                $results['failed'][] = ['uuid' => $addressUuid, 'error' => $errorMsg];
                $overallSuccess = false;
            } catch (Exception $e) {
                $errorMsg = 'Generic Exception: ' . $e->getMessage();
                WACC()->Log->error("Generic exception during address update (UUID: {$addressUuid}).", ['source' => __METHOD__, 'address_uuid' => $addressUuid, 'message' => $e->getMessage()]);
                $results['failed'][] = ['uuid' => $addressUuid, 'error' => $errorMsg];
                $overallSuccess = false;
            }
        }

        return ['success' => $overallSuccess, 'results' => $results];
    }
}
