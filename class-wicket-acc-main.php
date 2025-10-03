<?php

namespace WicketAcc;

use WicketAcc\Admin\AdminSettings;
use WicketAcc\Admin\Safeguards;
use WicketAcc\Admin\Tweaks;
use WicketAcc\Mdp\Init as Mdp;
use WicketAcc\Services\Notification;

/*
 * @package  wicket-wp-account-centre
 * @author  Wicket Inc.
 *
 * Plugin Name:       Wicket Account Centre
 * Plugin URI:        https://wicket.io
 * Description:       Custom account management system for Wicket. Provides user account features, organization management, and additional blocks and pages. Integrates with WooCommerce when available.
 * Version:           1.5.512
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
if (file_exists(WICKET_ACC_PATH . 'vendor/autoload.php')) {
    require_once WICKET_ACC_PATH . 'vendor/autoload.php';
}

// Initialize the plugin when all plugins are loaded
add_action(
    'plugins_loaded',
    [WicketAcc::get_instance(), 'plugin_setup']
);

/**
 * The main Wicket Account Centre class.
 *
 * Provides method-based access to all services:
 * - WACC()->Mdp() - MDP integration service
 * - WACC()->Profile() - User profile management
 * - WACC()->OrganizationManagement() - Organization management
 * - WACC()->OrganizationProfile() - Organization profile management
 * - WACC()->OrganizationRoster() - Organization roster management
 * - WACC()->Blocks() - Custom blocks
 * - WACC()->User() - User management
 * - WACC()->Log() - Logging service
 * - WACC()->Language() - Language/localization
 * - WACC()->Notification() - Notification service
 * - WACC()->Settings() - Plugin settings
 * - WACC()->WooCommerce() - WooCommerce integration (if active)
 *
 * @method Mdp Mdp() Get MDP integration service
 * @method Profile Profile() Get user profile management service
 * @method OrganizationManagement OrganizationManagement() Get organization management service
 * @method OrganizationProfile OrganizationProfile() Get organization profile service
 * @method OrganizationRoster OrganizationRoster() Get organization roster service
 * @method Blocks Blocks() Get custom blocks service
 * @method User User() Get user management service
 * @method Log Log() Get logging service
 * @method Language Language() Get language/localization service
 * @method Notification Notification() Get notification service
 * @method Settings Settings() Get plugin settings service
 * @method WooCommerce WooCommerce() Get WooCommerce integration service
 * @method Helpers Helpers() Get helpers service
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
        'en' => 'my-account',
        'fr' => 'mon-compte',
        'es' => 'mi-cuenta',
    ];

    protected array $acc_pages_map = [
        // Wicket pages
        'edit-profile'            => 'Edit Profile',
        'events'                  => 'My Events',
        'jobs'                    => 'My Jobs',
        'job-post'                => 'Post a Job',
        'change-password'         => 'Change Password',
        'organization-management' => 'Organization Management',
        'organization-profile'    => 'Organization Profile',
        'organization-members'    => 'Organization Members',
        'acc_global-headerbanner' => 'Global Header-Banner',
        // WooCommerce endpoints https://developer.woocommerce.com/docs/woocommerce-endpoints/
        //'order-pay'                      => 'Order Pay', // Handled by checkout, not account
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
        'subscriptions'               => 'Subscriptions',
        'view-subscription'           => 'View Subscription',
        'subscription-payment-method' => 'Subscription Payment Method',
    ];

    protected array $acc_pages_map_auto_create = [
        'dashboard',
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
        'view-subscription',
        'subscription-payment-method',
    ];

    /**
     * WooCommerce endpoints with their translations.
     * Keys are the canonical endpoint names, values are arrays of translations by language code.
     * This is the centralized source of truth for all WooCommerce endpoints in the ACC plugin.
     *
     * @var array
     */
    protected array $acc_wc_endpoints = [
        'add-payment-method'         => [
            'en' => 'add-payment-method',
            'fr' => 'add-payment-method',
            'es' => 'agregar-medio-pago',
        ],
        'set-default-payment-method' => [
            'en' => 'set-default-payment-method',
            'fr' => 'definir-mode-paiement-defaut',
            'es' => 'establecer-medio-pago-principal',
        ],
        'delete-payment-method'      => [
            'en' => 'delete-payment-method',
            'fr' => 'supprimer-mode-paiement',
            'es' => 'eliminar-medio-pago',
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
        'view-subscription'          => [
            'en' => 'view-subscription',
            'fr' => 'voir-abonnement',
            'es' => 'ver-suscripcion',
        ],
        'subscription-payment-method' => [
            'en' => 'subscription-payment-method',
            'fr' => 'abonnement-mode-de-paiement',
            'es' => 'suscripcion-metodo-de-pago',
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
            'es' => 'editar-direccion',
        ],
        'payment-methods'            => [
            'en' => 'payment-methods',
            'fr' => 'modes-de-paiement',
            'es' => 'metodos-de-pago',
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
     * Magic method to provide method-based access to service instances and helper methods.
     *
     * Supports two patterns:
     * 1. Service access: WACC()->ServiceName() returns the service instance
     * 2. Helper methods: WACC()->helperMethod() calls methods from the Helpers class
     *
     * @param string $name Method name (service name or helper method)
     * @param array $arguments Method arguments
     *
     * @return object|mixed Service instance or helper method result
     * @throws \Exception When method/service doesn't exist
     */
    public function __call($name, $arguments)
    {
        $helpers = $this->getHelpers();

        // Handle Helpers class methods directly
        if (method_exists($helpers, $name)) {
            return call_user_func_array([$helpers, $name], $arguments);
        }

        // Handle MdpApi alias for backward compatibility
        if ($name === 'MdpApi') {
            $name = 'Mdp';
        }

        // Handle Helpers alias for backward compatibility
        if ($name === 'Helpers') {
            return $this->getHelpers();
        }

        // Handle service instance access via method calls
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        throw new \Exception("Method or service '$name' does not exist. Available services: " . implode(', ', array_keys($this->instances)));
    }

    /**
     * Get the Helpers instance (lazy initialization).
     *
     * @return Helpers
     */
    private function getHelpers(): Helpers
    {
        if (!isset($this->helpersInstance)) {
            $this->helpersInstance = new Helpers();
        }

        return $this->helpersInstance;
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
            'Settings'               => new Settings(),
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
            new Tweaks();
            new Safeguards(); // Initialize the safeguard class for admin tasks
        }

        // Load WooCommerce integration if active
        if ($this->isWooCommerceActive()) {
            $this->instances['WooCommerce'] = new WooCommerce();
        }
    }
} // end Class.
