<?php

namespace WicketAcc;

use WicketAcc\Admin\AdminSettings;
use WicketAcc\Admin\WicketAccSafeguard;
use WicketAcc\MdpApi\Init as MdpApi;
use WicketAcc\Services\Notification;

/*
 * @package  wicket-wp-account-centre
 * @author  Wicket Inc.
 *
 * Plugin Name:       Wicket Account Centre
 * Plugin URI:        https://wicket.io
 * Description:       Custom account management system for Wicket. Provides user account features, organization management, and additional blocks and pages. Integrates with WooCommerce when available.
 * Version:           1.5.181
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

/**
 * The main Wicket Account Centre class.
 */
class WicketAcc
{
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
        'subscriptions' => 'Subscriptions',
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
        'order-pay' => [
            'en' => 'order-pay',
            'fr' => 'ordre-paiement',
            'es' => 'orden-pago',
        ],
        'order-received' => [
            'en' => 'order-received',
            'fr' => 'ordre-recibida',
            'es' => 'orden-recibida',
        ],
        'add-payment-method' => [
            'en' => 'add-payment-method',
            'fr' => 'ajouter-mode-paiement',
            'es' => 'agregar-medio-pago',
        ],
        'set-default-payment-method' => [
            'en' => 'set-default-payment-method',
            'fr' => 'definir-mode-paiement-defaut',
            'es' => 'establecer-medio-pago-principal',
        ],
        'orders' => [
            'en' => 'orders',
            'fr' => 'commandes',
            'es' => 'ordenes',
        ],
        'view-order' => [
            'en' => 'view-order',
            'fr' => 'afficher-commande',
            'es' => 'ver-orden',
        ],
        'downloads' => [
            'en' => 'downloads',
            'fr' => 'telechargements',
            'es' => 'descargas',
        ],
        'edit-account' => [
            'en' => 'edit-account',
            'fr' => 'editer-compte',
            'es' => 'editar-cuenta',
        ],
        'edit-address' => [
            'en' => 'edit-address',
            'fr' => 'editer-adresse',
            'es' => 'editar-dirección',
        ],
        'payment-methods' => [
            'en' => 'payment-methods',
            'fr' => 'modes-de-paiement',
            'es' => 'medios-de-pago',
        ],
        'customer-logout' => [
            'en' => 'customer-logout',
            'fr' => 'deconnexion',
            'es' => 'cerrar-sesion',
        ],
        'subscriptions' => [
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
     * Constructor.
     */
    public function __construct() {}

    /**
     * Run.
     */
    public function run()
    {
        add_filter('wp_dropdown_pages', 'wicket_acc_alter_wp_job_manager_pages', 10, 3);

        register_activation_hook(__FILE__, [$this, 'plugin_activated']);

        // Load global helper files
        $includes_global = [
            'includes/helpers.php',
            'includes/legacy.php',
        ];
        foreach ($includes_global as $global_file_path) {
            if (file_exists(WICKET_ACC_PATH . $global_file_path)) {
                include_once WICKET_ACC_PATH . $global_file_path;
            }
        }

        // Carbon Fields
        new CarbonFieldsInit();

        if (is_admin()) {
            new AdminSettings();
            new WicketAccSafeguard(); // Initialize the safeguard class for admin tasks
        }

        new MdpApi();
        new Router();
        new Blocks();
        new Helpers();
        new Shortcodes();
        new Registers();
        new Profile();
        new OrganizationManagement();
        new OrganizationProfile();
        new OrganizationRoster();
        new Assets();
        new User();
        new Language();
        new Notification();

        // Load WooCommerce integration if active
        if (WACC()->isWooCommerceActive()) {
            new WooCommerce();
        }
    }

    /**
     * Plugin activation.
     */
    public function plugin_activated() {}
} // end Class.

// Initialize the plugin
$WicketAcc = new WicketAcc();
$WicketAcc->run();
