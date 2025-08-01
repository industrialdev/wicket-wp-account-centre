<?php

namespace WicketAcc;

use WicketAcc\Admin\AdminSettings;
use WicketAcc\Admin\WicketAccSafeguard;
use WicketAcc\Mdp\Init as Mdp;
use WicketAcc\Services\Notification;

/*
 * @package  wicket-wp-account-centre
 * @author  Wicket Inc.
 *
 * Plugin Name:       Wicket Account Centre
 * Plugin URI:        https://wicket.io
 * Description:       Custom account management system for Wicket. Provides user account features, organization management, and additional blocks and pages. Integrates with WooCommerce when available.
 * Version:           1.5.230
 * Author:            Wicket Inc.
 * Developed By:      Wicket Inc.
 * Author URI:        https://wicket.io
 * Support:           https://wicket.io
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Requires Plugins: wicket-wp-base-plugin, advanced-custom-fields-pro
 * Domain Path:       /languages
 * Text Domain:       wicket-acc
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Constants
define('WICKET_ACC_VERSION', get_file_data(__FILE__, ['Version' => 'Version'], false)['Version']);
define('WICKET_ACC_PATH', plugin_dir_path(__FILE__));
define('WICKET_ACC_URL', plugin_dir_url(__FILE__));
define('WICKET_ACC_BASENAME', plugin_basename(__FILE__));
define('WICKET_ACC_UPLOADS_PATH', wp_get_upload_dir()['basedir'] . '/wicket-account-center/');
define('WICKET_ACC_UPLOADS_URL', wp_get_upload_dir()['baseurl'] . '/wicket-account-center/');
define('WICKET_ACC_PLUGIN_TEMPLATE_PATH', WICKET_ACC_PATH . 'templates-wicket/');
define('WICKET_ACC_PLUGIN_TEMPLATE_URL', WICKET_ACC_URL . 'templates-wicket/');
define('WICKET_ACC_USER_TEMPLATE_PATH', get_stylesheet_directory() . '/templates-wicket/');
define('WICKET_ACC_USER_TEMPLATE_URL', get_stylesheet_directory_uri() . '/templates-wicket/');
define('WICKET_ACC_TEMPLATES_FOLDER', 'account-centre');

// Composer Autoloader
if (file_exists(WICKET_ACC_PATH . 'vendor-dist/autoload.php')) {
    require_once WICKET_ACC_PATH . 'vendor-dist/autoload.php';
}

// Initialize the plugin when all plugins are loaded
add_action(
    'plugins_loaded',
    [WicketAcc::get_instance(), 'plugin_setup']
);

/**
 * The main Wicket Account Centre class.
 */
class WicketAcc
{
    /**
     * Plugin instance.
     *
     * @see get_instance()
     * @type object
     */
    protected static $instance = null;

    /**
     * URL to this plugin's directory.
     *
     * @type string
     */
    public $plugin_url = '';

    /**
     * Path to this plugin's directory.
     *
     * @type string
     */
    public $plugin_path = '';

    /**
     * Component instances.
     *
     * @var array
     */
    private array $instances = [];

    /**
     * Helpers instance.
     *
     * @var Helpers
     */
    private Helpers $helpersInstance;

    protected string $acc_post_type = 'my-account';

    protected array $acc_index_slugs = [
        'en' => 'my-account',
        'fr' => 'mon-compte',
        'es' => 'mi-cuenta',
    ];

    protected array $acc_wc_index_slugs = [
        'en' => 'wc-account',
        'fr' => 'wc-compte',
        'es' => 'wc-cuenta',
    ];

    protected array $acc_pages_map = [
        // Wicket pages
        'edit-profile'               => 'Edit Profile',
        'events'                     => 'My Events',
        'jobs'                       => 'My Jobs',
        'job-post'                   => 'Post a Job',
        'change-password'            => 'Change Password',
        'organization-management'    => 'Organization Management',
        'organization-profile'       => 'Organization Profile',
        'organization-members'       => 'Organization Members',
        'acc_global-headerbanner'    => 'Global Header-Banner',
        // WooCommerce endpoints https://developer.woocommerce.com/docs/woocommerce-endpoints/
        //'order-pay'                      => 'Order Pay',
        //'order-received'                 => 'Order Received',
        'add-payment-method'         => 'Add Payment Method',
        'set-default-payment-method' => 'Set Default Payment Method',
        'orders'                     => 'Orders',
        'view-order'                 => 'View Order',
        'downloads'                  => 'Downloads',
        'edit-account'               => 'Edit Account',
        'edit-address'               => 'Edit Address',
        'payment-methods'            => 'Payment Methods',
        //'customer-logout'                => 'Logout',
        // WooCommerce subscription endpoints
        'subscriptions'              => 'Subscriptions',
    ];

    protected array $acc_pages_map_auto_create = [
        'edit-profile',
        'change-password',
        'organization-management',
        'organization-profile',
        'organization-members',
        'acc_global-headerbanner',
        'add-payment-method',
        'set-default-payment-method',
        'orders',
        'view-order',
        'downloads',
        'payment-methods',
        'subscriptions',
    ];

    protected array $acc_wc_endpoints = [
        'order-pay'                  => [
            'en' => 'order-pay',
            'fr' => 'ordre-paiement',
            'es' => 'orden-pago',
        ],
        'order-received'             => [
            'en' => 'order-received',
            'fr' => 'ordre-recibida',
            'es' => 'orden-recibida',
        ],
        'add-payment-method'         => [
            'en' => 'add-payment-method',
            'fr' => 'ajouter-mode-paiement',
            'es' => 'agregar-medio-pago',
        ],
        'set-default-payment-method' => [
            'en' => 'set-default-payment-method',
            'fr' => 'definir-mode-paiement-defaut',
            'es' => 'establecer-medio-pago-principal',
        ],
        'orders'                     => [
            'en' => 'orders',
            'fr' => 'commandes',
            'es' => 'ordenes',
        ],
        'view-order'                 => [
            'en' => 'view-order',
            'fr' => 'afficher-commande',
            'es' => 'ver-orden',
        ],
        'downloads'                  => [
            'en' => 'downloads',
            'fr' => 'telechargements',
            'es' => 'descargas',
        ],
        'edit-account'               => [
            'en' => 'edit-account',
            'fr' => 'editer-compte',
            'es' => 'editar-cuenta',
        ],
        'edit-address'               => [
            'en' => 'edit-address',
            'fr' => 'editer-adresse',
            'es' => 'editar-dirección',
        ],
        'payment-methods'            => [
            'en' => 'payment-methods',
            'fr' => 'modes-de-paiement',
            'es' => 'medios-de-pago',
        ],
        'customer-logout'            => [
            'en' => 'customer-logout',
            'fr' => 'deconnexion',
            'es' => 'cerrar-sesion',
        ],
        'subscriptions'              => [
            'en' => 'subscriptions',
            'fr' => 'souscriptions',
            'es' => 'suscripciones',
        ],
    ];

    protected array $acc_prefer_wc_endpoints = [
        'add-payment-method',
        'payment-methods',
    ];

    /**
     * Access this plugin's working instance.
     *
     * @wp-hook plugins_loaded
     * @return  object of this class
     */
    public static function get_instance()
    {
        null === self::$instance and self::$instance = new self();

        return self::$instance;
    }

    /**
     * Constructor. Intentionally left empty and public.
     *
     * @see plugin_setup()
     */
    public function __construct() {}

    /**
     * Get the instance of a class.
     *
     * @param string $name
     *
     * @return object|Blocks|Mdp|OrganizationProfile|Profile|User|Log|WooCommerce|Language|OrganizationManagement|OrganizationRoster
     * @throws \Exception
     */
    public function __get($name): Blocks|Mdp|OrganizationProfile|Profile|User|Log|WooCommerce|Language|OrganizationManagement|OrganizationRoster
    {
        // Handle MdpApi alias for backward compatibility
        if ($name === 'MdpApi') {
            $name = 'Mdp';
        }

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

        throw new \Exception("Method or class instance '$name' does not exist. Available instances: " . implode(', ', array_keys($this->instances)));
    }

    /**
     * Used for regular plugin work.
     *
     * @wp-hook plugins_loaded
     * @since   2012.09.10
     * @return  void
     */
    public function plugin_setup()
    {
        $this->plugin_url = WICKET_ACC_URL;
        $this->plugin_path = WICKET_ACC_PATH;

        Log::registerFatalErrorHandler();

        add_filter('wp_dropdown_pages', 'wicket_acc_alter_wp_job_manager_pages', 10, 3);

        // Load global helper files
        $includes_global = [
            'includes/helpers.php',
            'includes/legacy.php',
        ];
        foreach ($includes_global as $global_file_path) {
            if (file_exists($this->plugin_path . $global_file_path)) {
                include_once $this->plugin_path . $global_file_path;
            }
        }

        $this->helpersInstance = new Helpers();

        // Instantiate services
        $this->instances = [
            'Mdp'                    => new Mdp(),
            'Profile'                => new Profile(),
            'OrganizationManagement' => new OrganizationManagement(),
            'OrganizationProfile'    => new OrganizationProfile(),
            'OrganizationRoster'     => new OrganizationRoster(),
            'Blocks'                 => new Blocks(),
            'User'                   => new User(),
            'Log'                    => new Log(),
            'Language'               => new Language(),
            'Notification'           => new Notification(),
        ];

        // Instantiate classes for their hooks
        new CFInitOptions(); // Options always should be first, it bootstraps Carbon Fields
        new CFInitBlocks();
        new Router();
        new Shortcodes();
        new Registers();
        new Assets();

        if (is_admin()) {
            new AdminSettings();

            // Only initialize safeguard on non-development environments
            if (!defined('WP_ENVIRONMENT_TYPE') || WP_ENVIRONMENT_TYPE !== 'development') {
                new WicketAccSafeguard(); // Initialize the safeguard class for admin tasks
            }
        }

        // Load WooCommerce integration if active
        if ($this->isWooCommerceActive()) {
            $this->instances['WooCommerce'] = new WooCommerce();
        }
    }
} // end Class.
