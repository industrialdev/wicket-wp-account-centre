<?php

declare(strict_types=1);

namespace WicketAcc\MdpApi;

use Exception;
use GuzzleHttp\Exception\RequestException;

// No direct access
defined('ABSPATH') || exit;

/**
 * Handles various MDP helper/utility API endpoints.
 */
class Helper extends Init
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Retrieve a single interval resource by its ID.
     *
     * @param int|string $uuid The UUID of the interval to fetch.
     * @return object|false The Wicket SDK Interval resource object on success, false on failure.
     */
    public function getIntervalById(int|string $uuid): object|false
    {
        if (empty($uuid)) {
            WACC()->Log->warning('Interval ID cannot be empty.', ['source' => __METHOD__]);

            return false;
        }

        $client = $this->initClient();
        if (!$client) {
            // initClient() already logs the error.
            return false;
        }

        try {
            return $client->intervals->fetch($uuid);
        } catch (RequestException $e) {
            $response_code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            WACC()->Log->error(
                'RequestException while fetching interval by ID.',
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
                'Generic Exception while fetching interval by ID.',
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
