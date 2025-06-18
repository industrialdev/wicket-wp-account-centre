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
}
