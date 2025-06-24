<?php

namespace WicketAcc\MdpApi;

use Exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Exception\RequestException;
use Wicket\Client;

// No direct access
defined('ABSPATH') || exit;

/**
 * Class Init
 * Base class for MDP API interactions and gateway to specialized API handlers.
 * Provides access to specialized API classes like Person, Organization, etc., via magic __get().
 * Example: WACC()->MdpApi->Person->getCurrentPerson();.
 */
class Init
{
    /**
     * Holds instances of specialized API classes.
     * @var array
     */
    private array $specializedInstances = [];

    /**
     * Constructor.
     */
    public function __construct() {}

    /**
     * Initialize the API client.
     *
     * @return Client|false The Wicket Client instance or false on failure.
     */
    public function initClient()
    {
        try {
            if (!class_exists('\Wicket\Client')) {
                error_log('Wicket SDK Client class (\Wicket\Client) not found. Ensure it is installed via Composer and autoloading correctly.');

                return false;
            }

            $wicket_settings = $this->getMdpSettings();

            if (empty($wicket_settings['jwt']) || empty($wicket_settings['api_endpoint']) || empty($wicket_settings['person_id'])) {
                error_log('Wicket MDP API settings (JWT, API Endpoint, or Person ID) are missing.');

                return false;
            }

            $client = new Client($app_key = '', $wicket_settings['jwt'], $wicket_settings['api_endpoint']);
            $client->authorize($wicket_settings['person_id']);

            // Test the endpoint to make sure the API is working
            // Consider making this optional or handling failure more specifically
            $client->get($wicket_settings['api_endpoint']); // This might throw an exception if API is down

        } catch (Exception $e) {
            error_log('Wicket MDP API client initialization failed: ' . $e->getMessage());

            return false;
        }

        return $client;
    }

    /**
     * Alias for initClient().
     *
     * @deprecated Prefer using initClient() for new code.
     * @return Client|false The Wicket Client instance or false on failure.
     */
    public function init_client(): Client|false
    {
        return $this->initClient();
    }

    /**
     * Get Wicket client, authorized as the current user.
     *
     * This function initializes the Wicket API client and authorizes it as the current user.
     * This is useful for giving context to person operations and respecting permissions on the Wicket side.
     *
     * @return Client|null The initialized and authorized Wicket API client, or null if authorization fails.
     */
    public function initClientWithCurrentPerson()
    {
        $client = $this->initClient();

        if ($client) {
            $person_id = $this->Person->getCurrentPersonUuid();

            if ($person_id) {
                $client->authorize($person_id);
            } else {
                $client = null;
            }
        }

        return $client;
    }

    /**
     * Get a Wicket plugin option.
     *
     * @param string $key The option key.
     * @param mixed|null $default The default value if the key is not found.
     * @return mixed The option value or default.
     */
    public function getOption($key, $default = null)
    {
        $options = get_option('wicket_settings', []);

        return $options[$key] ?? $default;
    }

    /**
     * Generates a JSON Web Token (JWT) for a given Wicket person ID.
     *
     * This token can be used for authenticating the person for specific Wicket operations
     * or for passing identity to other services that integrate with Wicket.
     *
     * @param string $person_id The Wicket person UUID for whom the token is generated.
     * @param int    $expiresIn The duration in seconds for which the token will be valid. Defaults to 28800 (8 hours).
     * @return string The generated JSON Web Token (JWT).
     */
    public function getAccessTokenForPerson($person_id, $expiresIn = 60 * 60 * 8)
    {
        $settings = $this->getOption('wicket_settings');
        $iat = time();

        $token = [
            'sub' => $person_id,
            'iat' => $iat,
            'exp' => $iat + $expiresIn,
        ];

        return JWT::encode($token, $settings['jwt'], 'HS256');
    }

    /**
     * Generates an access token for organization-specific Wicket widgets.
     *
     * This token allows the use of widgets like the profile and additional info widgets
     * in the context of a specific organization. It requires the UUID of the person
     * (presumably the currently logged-in user) and the UUID of the target organization.
     * The token is obtained by calling the 'widget_tokens' endpoint on the Wicket API.
     *
     * @param string $person_id The Wicket person UUID of the user for whom the token is generated.
     * @param string $org_uuid  The Wicket organization UUID for which the widget context is set.
     * @return string|false The generated widget access token as a string on success, or false on failure.
     */
    public function getAccessTokenForOrg($person_id, $org_uuid)
    {
        $client = $this->initClient();

        $payload = [
            'data' => [
                'type' => 'widget_tokens',
                'attributes' => [
                    'widget_context' => 'organizations',
                ],
                'relationships' => [
                    'subject' => [
                        'data' => [
                            'type' => 'people',
                            'id' => $person_id,
                        ],
                    ],
                    'resource' => [
                        'data' => [
                            'type' => 'organizations',
                            'id' => $org_uuid,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = $client->post('widget_tokens', ['json' => $payload]);

            // Assuming the Wicket client's post method returns an array/ArrayAccess with the token
            // If it returns a Guzzle ResponseInterface, you'd need: json_decode($response->getBody()->getContents(), true)['token']
            return $response['token'] ?? false; // Return false if token is not set

        } catch (RequestException $e) {
            error_log('Wicket API RequestException in getAccessTokenForOrg: ' . $e->getMessage());
            if ($e->hasResponse()) {
                $responseBodyContents = $e->getResponse()->getBody()->getContents();
                error_log('Wicket API Response Body: ' . $responseBodyContents);
                $decodedBody = json_decode($responseBodyContents);
                if (json_last_error() === JSON_ERROR_NONE && isset($decodedBody->errors)) {
                    error_log('Wicket API Errors: ' . json_encode($decodedBody->errors));
                }
            }
        } catch (Exception $e) { // Fallback for other types of exceptions
            error_log('Generic Exception in getAccessTokenForOrg: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Get Wicket MDP settings based on the configured environment.
     *
     * @param string|null $environment Specific environment ('prod', 'stage') to get settings for. Defaults to current plugin setting.
     * @return array The settings array for the environment.
     */
    public function getMdpSettings($environment = null)
    {
        $settings = [];
        $current_environment = $environment ?? $this->getOption('wicket_admin_settings_environment');

        switch ($current_environment) {
            case 'prod':
                $settings['api_endpoint'] = $this->getOption('wicket_admin_settings_prod_api_endpoint');
                $settings['jwt'] = $this->getOption('wicket_admin_settings_prod_secret_key');
                $settings['person_id'] = $this->getOption('wicket_admin_settings_prod_person_id');
                $settings['parent_org'] = $this->getOption('wicket_admin_settings_prod_parent_org');
                $settings['wicket_admin'] = $this->getOption('wicket_admin_settings_prod_wicket_admin');
                break;
            case 'stage':
                $settings['api_endpoint'] = $this->getOption('wicket_admin_settings_stage_api_endpoint');
                $settings['jwt'] = $this->getOption('wicket_admin_settings_stage_secret_key');
                $settings['person_id'] = $this->getOption('wicket_admin_settings_stage_person_id');
                $settings['parent_org'] = $this->getOption('wicket_admin_settings_stage_parent_org');
                $settings['wicket_admin'] = $this->getOption('wicket_admin_settings_stage_wicket_admin');
                break;
            default:
                error_log('Unknown Wicket MDP environment specified or configured: ' . $current_environment);
                // Return empty or default settings to prevent further errors if critical settings are missing
                $settings = [
                    'api_endpoint' => '',
                    'jwt' => '',
                    'person_id' => '',
                    'parent_org' => '',
                    'wicket_admin' => '',
                ];
                break;
        }

        return $settings;
    }

    /**
     * Magic getter for specialized API classes.
     *
     * @param string $name The name of the specialized class (e.g., 'People', 'Person').
     *                     Must match one of the valid specialized class names.
     * @return object|null An instance of the specialized class or null if not a valid property.
     * @throws Exception If the requested class is a valid specialized API class but cannot be found/instantiated.
     */
    public function __get(string $name)
    {
        $validClasses = [
            'Person'       => Person::class,
            'Address'      => Address::class,
            'Organization' => Organization::class,
            'Group'        => Group::class,
            'Touchpoint'   => Touchpoint::class,
            'Segment'      => Segment::class,
            'Membership'   => Membership::class,
            'Helper'       => Helper::class,
            'Schema'       => Schema::class,
            'Roles'        => Roles::class,
        ];

        if (array_key_exists($name, $validClasses)) {
            if (!isset($this->specializedInstances[$name])) {
                $className = $validClasses[$name];
                if (class_exists($className)) {
                    // Special handling for Membership class dependencies
                    if ($name === 'Membership') {
                        $this->specializedInstances[$name] = new $className($this->Person, $this->Organization);
                    } else {
                        $this->specializedInstances[$name] = new $className();
                    }
                } else {
                    throw new Exception("Specialized API class {$className} not found. Ensure it exists, the namespace is correct, and autoloading is configured.");
                }
            }

            return $this->specializedInstances[$name];
        }

        trigger_error('Undefined property: ' . __CLASS__ . "::\\$$name", E_USER_NOTICE);

        return null;
    }
}
