<?php

namespace WicketAcc;

use WicketAcc\MdpApi\Init as MdpApi;
use WicketAcc\Services\Notification;

// No direct access
defined('ABSPATH') || exit;

/**
 * Magic wrapper Class for WACC() helpers.
 */
class MethodRouter
{
    private $instances = [];
    private $helpersInstance;

    /**
     * Constructor.
     */
    public function __construct()
    {
        // The constructor is intentionally left empty to be populated by the init method.
    }

    /**
     * Initialize the services.
     */
    public function init()
    {
        // Register all class instances except Helpers
        $this->instances = [
            'MdpApi'                 => new MdpApi(),
            'Profile'                => new Profile(),
            'OrganizationManagement' => new OrganizationManagement(),
            'OrganizationProfile'    => new OrganizationProfile(),
            'OrganizationRoster'     => new OrganizationRoster(),
            'Blocks'                 => new Blocks(),
            'User'                   => new User(),
            'Log'                    => new Log(),
            'WooCommerce'            => new WooCommerce(),
            'Language'               => new Language(),
            'Notification'           => new Notification(),
        ];

        // Now instantiate Membership since MdpApi and its children are available
        $this->instances['Membership'] = new \WicketAcc\MdpApi\Membership(
            $this->instances['MdpApi']->Person,
            $this->instances['MdpApi']->Organization
        );

        // Store Helpers instance separately
        $this->helpersInstance = new Helpers();
    }

    /**
     * Get the instance of a class.
     *
     * @param string $name
     *
     * @return object|Blocks|MdpApi|OrganizationProfile|Profile|User|Log|WooCommerce|Language|\WicketAcc\MdpApi\Membership|OrganizationManagement|OrganizationRoster
     * @throws \Exception
     */
    public function __get($name): Blocks|MdpApi|OrganizationProfile|Profile|User|Log|WooCommerce|Language|\WicketAcc\MdpApi\Membership|OrganizationManagement|OrganizationRoster
    {
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        throw new \Exception("Class instance $name does not exist.");
    }

    /**
     * Call magic method for class instances.
     *
     * @param string $name
     * @param array $arguments
     *
     * @return object|mixed
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        // Handle Helpers class methods directly
        if (method_exists($this->helpersInstance, $name)) {
            return call_user_func_array([$this->helpersInstance, $name], $arguments);
        }

        // Handle dynamic class instance call
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        //throw new \Exception("Method or class instance $name does not exist.");
        throw new \Exception("Method or class instance '$name' does not exist. Available instances: " . implode(', ', array_keys($this->instances)));
    }

    /**
     * Static call magic method for Helpers.
     *
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     * @throws \Exception
     */
    public static function __callStatic($name, $arguments)
    {
        $router = new self();

        return $router->__call($name, $arguments);
    }
}
