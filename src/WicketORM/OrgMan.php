<?php

/**
 * Main orchestrator class for the Organization Management feature.
 */

namespace WicketORM;

// Exit if accessed directly.
if (!defined('ABSPATH') && !defined('WICKET_DOING_TESTS')) {
    exit;
}

if (!class_exists(Config\OrgManConfig::class)) {
    throw new \RuntimeException(
        'OrgMan requires Composer autoload. Include vendor/autoload.php before loading OrgMan.php.'
    );
}

/**
 * Singleton class for managing the Organization Management feature.
 */
final class OrgMan
{
    /**
     * The single instance of the class.
     *
     * @var OrgMan|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance of the class.
     *
     * @return OrgMan
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Backward-compatible alias for singleton accessor.
     *
     * @return OrgMan
     */
    public static function get_instance()
    {
        return self::getInstance();
    }

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
        $this->init();
    }

    /**
     * Holds the configuration.
     *
     * @var array
     */
    public $config = [];

    /**
     * Holds the service instances.
     *
     * @var array
     */
    private $services = [];

    /**
     * Holds the controller instances.
     *
     * @var array
     */
    private $controllers = [];

    private $content_map = [];

    /**
     * Initialize the feature, add hooks.
     */
    public function init()
    {
        // Explicitly include new services that might not be in the autoloader yet in all environments
        $base_path = __DIR__;
        if (file_exists($base_path . '/Services/CacheService.php')) {
            require_once $base_path . '/Services/CacheService.php';
        }

        $this->loadConfig();
        $this->initServices();
        $this->initControllers();
        $this->addHooks();
    }

    /**
     * Load the configuration files.
     */
    private function loadConfig()
    {
        $this->config = Services\ConfigService::getConfig();
    }

    /**
     * Initialize the services.
     */
    private function initServices()
    {
        $this->services['config'] = new Services\ConfigService();
        $this->services['organization'] = new Services\OrganizationService();
        $this->services['member'] = new Services\MemberService($this->services['config']);
        $this->services['permission'] = new Services\PermissionService();
        $this->services['business_info'] = new Services\BusinessInfoService();
        $this->services['document'] = new Services\DocumentService();
        $this->services['subsidiary'] = new Services\SubsidiaryService($this->services['config']);
        $this->services['notification'] = new Services\NotificationService();
        $this->services['additional_seats'] = new Services\AdditionalSeatsService($this->services['config']);
        $this->services['membership'] = new Services\MembershipService();
        $this->services['bulk_upload'] = new Services\BulkMemberUploadService($this->services['config']);

        if (!empty($this->config['exports']['enabled'])) {
            $this->services['member_export'] = new Services\MemberExportService($this->services['config']);
        }

        if (!empty($this->config['engagement']['enabled'])) {
            $this->services['engagement'] = new Services\EngagementService($this->services['config']);
        }
    }

    /**
     * Initialize the API controllers.
     */
    private function initControllers()
    {
        $this->controllers['business_info'] = new Controllers\BusinessInfoController($this->services['business_info']);
        $this->controllers['document'] = new Controllers\DocumentController($this->services['document']);
        $this->controllers['subsidiary'] = new Controllers\SubsidiaryController($this->services['subsidiary']);
        $this->controllers['configuration'] = new Controllers\ConfigurationController();

        if (isset($this->services['member_export'])) {
            $this->controllers['member_export'] = new Controllers\MemberExportController($this->services['member_export']);
        }

        if (isset($this->services['engagement'])) {
            $this->controllers['engagement'] = new Controllers\EngagementController($this->services['engagement']);
        }
    }

    private function addHooks()
    {
        add_action('rest_api_init', [$this, 'registerApiRoutes']);
        add_filter('the_content', [$this, 'injectOrgmanContent']);
        add_filter('the_content', [$this, 'cleanupOrgmanAutopArtifacts'], 9999);
        add_filter('body_class', [$this, 'addOrgmanBodyClass']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        add_filter('query_vars', [Helpers\TemplateHelper::class, 'add_hypermedia_query_vars']);
        add_action('parse_request', [Helpers\TemplateHelper::class, 'maybe_handle_hypermedia_request']);

        // Initialize helpers
        add_action('init', [Helpers\GravityFormsHelper::class, 'init']);
        add_action('init', [Helpers\TemplateHelper::class, 'init']);

        // Initialize configuration controller
        add_action('init', [$this->controllers['configuration'], 'init']);

        // Add WooCommerce order processing hooks
        $this->registerAdditionalSeatsHook('woocommerce_order_status_processing');
        $this->registerAdditionalSeatsHook('woocommerce_order_status_completed');
        $this->registerAdditionalSeatsHook('woocommerce_order_status_on-hold');
        $this->registerAdditionalSeatsHook('woocommerce_payment_complete');

        add_filter('woocommerce_get_return_url', [$this, 'filterWoocommerceReturnUrl'], 10, 2);
        add_action(Services\BulkMemberUploadService::CRON_HOOK, [$this, 'processBulkUploadJob'], 10, 1);

        if (isset($this->services['member_export'])) {
            add_action(Services\MemberExportService::CRON_HOOK, [$this, 'processMemberExportJob'], 10, 1);
            add_action(Services\MemberExportService::CLEANUP_HOOK, [$this, 'cleanupMemberExport'], 10, 1);
            add_action('init', [$this->services['member_export'], 'handleDownload'], 1);
            add_filter('query_vars', static function (array $vars): array {
                $vars[] = Services\MemberExportService::QUERY_VAR;

                return $vars;
            });
        }

        // Add hooks to transfer user meta to order items
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'addAdditionalSeatsDataToOrderItem'], 10, 4);
    }

    /**
     * Register an order-processing hook that logs when it fires before running the handler.
     *
     * @param string $hook Hook name.
     * @return void
     */
    private function registerAdditionalSeatsHook($hook)
    {
        add_action($hook, function ($order_id) use ($hook) {
            $logger = \Wicket()->log();
            $context = [
                'source' => 'wicket-orgman',
                'hook' => $hook,
                'order_id' => (int) $order_id,
            ];

            $logger->info('WooCommerce hook fired for additional seats order processing', $context);

            $this->handleAdditionalSeatsOrderProcessing($order_id);
        }, 10, 1);
    }

    /**
     * Process one scheduled bulk-upload batch.
     *
     * @param string $job_id
     * @return void
     */
    public function processBulkUploadJob($job_id)
    {
        $job_id = sanitize_key((string) $job_id);
        if ($job_id === '') {
            return;
        }

        $bulk_upload_service = $this->services['bulk_upload'] ?? null;
        if (!$bulk_upload_service instanceof Services\BulkMemberUploadService) {
            $bulk_upload_service = new Services\BulkMemberUploadService($this->services['config']);
        }

        $bulk_upload_service->processScheduledJob($job_id);
    }

    /**
     * Process one scheduled member export batch.
     *
     * @param string $job_id
     * @return void
     */
    public function processMemberExportJob($job_id)
    {
        $job_id = sanitize_key((string) $job_id);
        if ($job_id === '') {
            return;
        }

        $export_service = $this->services['member_export'] ?? null;
        if (!$export_service instanceof Services\MemberExportService) {
            $export_service = new Services\MemberExportService($this->services['config']);
        }

        $export_service->processScheduledJob($job_id);
    }

    /**
     * Clean up an expired member export.
     *
     * @param string $job_id
     * @return void
     */
    public function cleanupMemberExport($job_id)
    {
        $job_id = sanitize_key((string) $job_id);
        if ($job_id === '') {
            return;
        }

        $export_service = $this->services['member_export'] ?? null;
        if (!$export_service instanceof Services\MemberExportService) {
            $export_service = new Services\MemberExportService($this->services['config']);
        }

        $export_service->cleanupExpiredExport($job_id);
    }

    /**
     * Register all API routes.
     */
    public function registerApiRoutes()
    {
        foreach ($this->controllers as $controller) {
            if (method_exists($controller, 'registerRoutes')) {
                $controller->registerRoutes();
            }
        }
    }

    /**
     * Handle additional seats order processing.
     *
     * @param int $order_id The order ID.
     */
    public function handleAdditionalSeatsOrderProcessing($order_id)
    {
        $order = wc_get_order($order_id);

        $logger = \Wicket()->log();
        $context = ['source' => 'wicket-orgman'];

        $logger->info('Additional seats handler invoked', array_merge($context, [
            'order_id' => $order_id,
        ]));

        if (!$order) {
            $logger->error('Order not found', array_merge($context, [
                'order_id' => $order_id,
            ]));

            return;
        }

        $logger->debug('Order loaded', array_merge($context, [
            'order_id' => $order_id,
            'order_status' => $order->get_status(),
            'customer_id' => $order->get_customer_id(),
            'payment_method' => $order->get_payment_method(),
            'transaction_id' => $order->get_transaction_id(),
        ]));

        if ($order->get_meta('additional_seats_processed', true)) {
            $logger->info('Skipping: already processed', array_merge($context, [
                'order_id' => $order_id,
            ]));

            return;
        }

        // Check if this is an additional seats order
        $additional_seats_service = $this->services['additional_seats'];

        // Multi-tier path: when tier mode is active and the order contains any tier-specific
        // seat product, fulfil per line item via MDP only (no subscription/CPT), then return.
        // This keeps multi-membership orgs (e.g. ESCRS) isolated from the legacy subscription
        // flow while leaving single-SKU sites on the unchanged path below.
        if ($additional_seats_service->isTierMode() && $this->orderHasTierSeatItems($order)) {
            $processed = $this->processTierSeatLineItems($order);
            $logger->info('Tier-mode additional seats processing complete', array_merge($context, [
                'order_id' => $order_id,
                'processed_items' => $processed,
            ]));

            return;
        }

        $additional_seats_product_id = $additional_seats_service->getAdditionalSeatsProduct();

        if (!$additional_seats_product_id) {
            $logger->error('Additional seats product not found by SKU', array_merge($context, [
                'order_id' => $order_id,
            ]));

            return;
        }

        $logger->debug('Additional seats product resolved', array_merge($context, [
            'order_id' => $order_id,
            'additional_seats_product_id' => (int) $additional_seats_product_id,
        ]));

        $has_additional_seats = false;
        $org_uuid = '';
        $membership_id = '';
        $membership_post_id = 0;
        $total_additional_seats = 0;
        $membership_data = null;

        // First, try to get user meta data if available
        $user_id = $order->get_customer_id();
        $user_meta_data = $additional_seats_service->getPurchaseUserMeta($user_id);
        if ($user_meta_data) {
            $org_uuid = $user_meta_data['org_uuid'];
            $membership_id = $user_meta_data['membership_id'];
            $membership_data = $user_meta_data['membership_data'];

            $logger->debug('Loaded additional seats data from user meta', array_merge($context, [
                'order_id' => $order_id,
                'customer_id' => $user_id,
                'org_uuid_present' => $org_uuid !== '',
                'membership_id_present' => $membership_id !== '',
                'membership_data_present' => !empty($membership_data),
            ]));
        }

        // Check order items for additional seats product
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            if ($product && $product->get_id() === $additional_seats_product_id) {
                $has_additional_seats = true;
                $total_additional_seats += $item->get_quantity();

                $logger->debug('Found additional seats order item', array_merge($context, [
                    'order_id' => $order_id,
                    'order_item_id' => $item->get_id(),
                    'product_id' => (int) $product->get_id(),
                    'qty' => (int) $item->get_quantity(),
                ]));

                // Get meta data from the item (fallback if session data is missing)
                if (empty($org_uuid)) {
                    $org_uuid = $item->get_meta('org_uuid', true);
                }
                if (empty($membership_id)) {
                    $membership_id = $item->get_meta('membership_id', true);
                }

                if (empty($membership_post_id)) {
                    $membership_post_id = (int) $item->get_meta('membership_post_id_renew', true);
                    if (!$membership_post_id) {
                        $membership_post_id = (int) $item->get_meta('_membership_post_id_renew', true);
                    }
                }

                $logger->debug('Extracted additional seats item meta', array_merge($context, [
                    'order_id' => $order_id,
                    'order_item_id' => $item->get_id(),
                    'org_uuid_present' => $org_uuid !== '',
                    'membership_id_present' => $membership_id !== '',
                    'membership_post_id' => (int) $membership_post_id,
                ]));
            }
        }

        $logger->debug('Additional seats item scan complete', array_merge($context, [
            'order_id' => $order_id,
            'has_additional_seats' => $has_additional_seats,
            'total_additional_seats' => (int) $total_additional_seats,
            'org_uuid_present' => $org_uuid !== '',
            'membership_id_present' => $membership_id !== '',
            'membership_post_id' => (int) $membership_post_id,
        ]));

        if (!$membership_post_id) {
            $membership_post_id = (int) $order->get_meta('membership_post_id_renew', true);
            if (!$membership_post_id) {
                $membership_post_id = (int) $order->get_meta('_membership_post_id_renew', true);
            }
        }

        $logger->debug('Membership post id after order meta fallback', array_merge($context, [
            'order_id' => $order_id,
            'membership_post_id' => (int) $membership_post_id,
        ]));

        if (!$has_additional_seats || empty($org_uuid) || (empty($membership_id) && empty($membership_post_id))) {
            $logger->error('Invalid additional seats order data', [
                'source' => 'wicket-orgman',
                'order_id' => $order_id,
                'has_additional_seats' => $has_additional_seats,
                'org_uuid_present' => $org_uuid !== '',
                'membership_id_present' => $membership_id !== '',
                'membership_post_id' => (int) $membership_post_id,
                'total_additional_seats' => (int) $total_additional_seats,
            ]);

            return;
        }

        if (!$membership_post_id && !empty($membership_id)) {
            $logger->debug('Searching membership post by membership_wicket_uuid', array_merge($context, [
                'order_id' => $order_id,
                'membership_id' => $membership_id,
            ]));
            $query = new \WP_Query([
                'posts_per_page' => 1,
                'post_type' => 'wicket_membership',
                'post_status' => 'any',
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => 'membership_wicket_uuid',
                        'value' => $membership_id,
                        'compare' => '=',
                    ],
                ],
            ]);

            if (!empty($query->posts[0])) {
                $membership_post_id = (int) $query->posts[0];
            }

            $logger->debug('Membership post query result', array_merge($context, [
                'order_id' => $order_id,
                'membership_post_id' => (int) $membership_post_id,
                'found' => (bool) $membership_post_id,
            ]));
        }

        if (!$membership_post_id) {
            $logger->error('Unable to locate membership post for additional seats order', [
                'source' => 'wicket-orgman',
                'order_id' => $order_id,
                'membership_id' => $membership_id,
                'org_uuid' => $org_uuid,
            ]);

            return;
        }

        $subscription_id = (int) get_post_meta($membership_post_id, 'membership_subscription_id', true);
        if (!$subscription_id) {
            $logger->error('Membership post missing membership_subscription_id', [
                'source' => 'wicket-orgman',
                'order_id' => $order_id,
                'membership_post_id' => $membership_post_id,
            ]);

            return;
        }

        $logger->debug('Subscription linkage resolved', array_merge($context, [
            'order_id' => $order_id,
            'membership_post_id' => (int) $membership_post_id,
            'subscription_id' => (int) $subscription_id,
        ]));

        $current_seats = (int) get_post_meta($membership_post_id, 'org_seats', true);
        $new_seats = $current_seats + (int) $total_additional_seats;

        $logger->info('Seat calculation', array_merge($context, [
            'order_id' => $order_id,
            'membership_post_id' => (int) $membership_post_id,
            'subscription_id' => (int) $subscription_id,
            'current_seats' => (int) $current_seats,
            'additional_seats' => (int) $total_additional_seats,
            'new_seats' => (int) $new_seats,
        ]));

        // If no membership data in session, try to reconstruct it
        if (!$membership_data) {
            $logger->debug('Reconstructing membership data for MDP payload', array_merge($context, [
                'order_id' => $order_id,
                'org_uuid_present' => $org_uuid !== '',
                'membership_id_present' => $membership_id !== '',
            ]));
            $membership_data = $additional_seats_service->getMembershipDataForMdp($org_uuid, $membership_id);
        }

        // Store comprehensive data in order meta for MDP processing
        if ($membership_data) {
            $order->update_meta_data('orgman_membership_data', $membership_data);
            $logger->debug('Stored membership data on order for MDP', array_merge($context, [
                'order_id' => $order_id,
                'membership_data_keys' => is_array($membership_data) ? array_keys($membership_data) : null,
            ]));
        } else {
            $logger->warning('Missing membership data for MDP update', array_merge($context, [
                'order_id' => $order_id,
                'org_uuid_present' => $org_uuid !== '',
                'membership_id_present' => $membership_id !== '',
            ]));
        }

        $subscription = function_exists('wcs_get_subscription') ? wcs_get_subscription($subscription_id) : null;
        if (!$subscription) {
            $logger->error('Subscription not found for membership post', [
                'source' => 'wicket-orgman',
                'order_id' => $order_id,
                'membership_post_id' => $membership_post_id,
                'subscription_id' => $subscription_id,
            ]);

            return;
        }

        update_post_meta($membership_post_id, 'org_seats', $new_seats);

        $logger->info('Updated membership post org_seats', array_merge($context, [
            'order_id' => $order_id,
            'membership_post_id' => (int) $membership_post_id,
            'org_seats' => (int) $new_seats,
        ]));

        if (class_exists('\\Wicket_Memberships\\Membership_Controller')) {
            try {
                $controller = new \Wicket_Memberships\Membership_Controller();
                $controller->amend_membership_json($membership_post_id, [
                    'membership_seats' => $new_seats,
                ]);

                $logger->info('Amended membership json after additional seats', array_merge($context, [
                    'order_id' => $order_id,
                    'membership_post_id' => (int) $membership_post_id,
                    'new_seats' => (int) $new_seats,
                ]));
            } catch (\Throwable $e) {
                $logger->error('Failed to amend membership json after additional seats purchase: ' . $e->getMessage(), [
                    'source' => 'wicket-orgman',
                    'order_id' => $order_id,
                    'membership_post_id' => $membership_post_id,
                ]);
            }
        }

        $membership_product_id = (int) get_post_meta($membership_post_id, 'membership_product_id', true);
        $subscription_changed = false;
        if ($membership_product_id) {
            $updated_subscription_item = false;
            foreach ($subscription->get_items() as $subscription_item) {
                $item_product_id = (int) $subscription_item->get_product_id();
                if ($item_product_id === $membership_product_id) {
                    $subscription_item->set_quantity($new_seats);
                    $updated_subscription_item = true;
                    $logger->info('Updated subscription item quantity', array_merge($context, [
                        'order_id' => $order_id,
                        'subscription_id' => (int) $subscription_id,
                        'membership_product_id' => (int) $membership_product_id,
                        'subscription_item_id' => $subscription_item->get_id(),
                        'new_qty' => (int) $new_seats,
                    ]));
                    $subscription_changed = true;
                }
            }

            if (!$updated_subscription_item) {
                $logger->warning('Did not find matching subscription item to update quantity', array_merge($context, [
                    'order_id' => $order_id,
                    'subscription_id' => (int) $subscription_id,
                    'membership_product_id' => (int) $membership_product_id,
                ]));
            }

            $subscription->update_meta_data('seat_limit', $new_seats);
            $subscription_changed = true;
        } else {
            $logger->warning('Membership product id missing; cannot update subscription item quantity', array_merge($context, [
                'order_id' => $order_id,
                'membership_post_id' => (int) $membership_post_id,
                'subscription_id' => (int) $subscription_id,
            ]));
        }

        $discount_product_id = $additional_seats_service->getAdditionalSeatsDiscountProduct();
        if ($discount_product_id) {
            $updated_discount_item = false;
            foreach ($subscription->get_items() as $subscription_item) {
                $item_product_id = (int) $subscription_item->get_product_id();
                if ($item_product_id === (int) $discount_product_id) {
                    $current_discount_qty = (int) $subscription_item->get_quantity();
                    $new_discount_qty = $current_discount_qty + (int) $total_additional_seats;
                    $subscription_item->set_quantity($new_discount_qty);
                    $updated_discount_item = true;
                    $subscription_changed = true;

                    $logger->info('Updated discount subscription item quantity', array_merge($context, [
                        'order_id' => $order_id,
                        'subscription_id' => (int) $subscription_id,
                        'discount_product_id' => (int) $discount_product_id,
                        'subscription_item_id' => $subscription_item->get_id(),
                        'previous_qty' => (int) $current_discount_qty,
                        'added_qty' => (int) $total_additional_seats,
                        'new_qty' => (int) $new_discount_qty,
                    ]));
                    break;
                }
            }

            if (!$updated_discount_item) {
                $discount_product = wc_get_product($discount_product_id);

                if ($discount_product) {
                    $added_item = $subscription->add_product($discount_product, (int) $total_additional_seats);
                    if (!is_wp_error($added_item) && !empty($added_item)) {
                        $subscription_changed = true;

                        $logger->info('Added discount product to subscription', array_merge($context, [
                            'order_id' => $order_id,
                            'subscription_id' => (int) $subscription_id,
                            'discount_product_id' => (int) $discount_product_id,
                            'added_qty' => (int) $total_additional_seats,
                        ]));
                    } else {
                        $error_message = is_wp_error($added_item) ? $added_item->get_error_message() : 'unknown_error';
                        $logger->error('Failed to add discount product to subscription', array_merge($context, [
                            'order_id' => $order_id,
                            'subscription_id' => (int) $subscription_id,
                            'discount_product_id' => (int) $discount_product_id,
                            'added_qty' => (int) $total_additional_seats,
                            'error' => $error_message,
                        ]));
                    }
                } else {
                    $logger->error('Discount product could not be loaded', array_merge($context, [
                        'order_id' => $order_id,
                        'subscription_id' => (int) $subscription_id,
                        'discount_product_id' => (int) $discount_product_id,
                    ]));
                }
            }
        } else {
            $logger->warning('Additional seats discount product not found by SKU; skipping discount line update', array_merge($context, [
                'order_id' => $order_id,
                'subscription_id' => (int) $subscription_id,
            ]));
        }

        if ($subscription_changed) {
            $subscription->calculate_totals(false);
            $subscription->save();

            $logger->info('Saved subscription after additional seats update', array_merge($context, [
                'order_id' => $order_id,
                'subscription_id' => (int) $subscription_id,
                'seat_limit' => (int) $new_seats,
            ]));
        }

        $mdp_membership_id = $membership_id;
        if (empty($mdp_membership_id)) {
            $mdp_membership_id = (string) get_post_meta($membership_post_id, 'membership_wicket_uuid', true);
        }

        if (!empty($mdp_membership_id)) {
            $logger->info('Updating MDP max_assignments', array_merge($context, [
                'order_id' => $order_id,
                'mdp_membership_id' => $mdp_membership_id,
                'new_max_assignments' => (int) $new_seats,
            ]));

            $mdp_updated = $additional_seats_service->updateMdpMembershipMaxAssignments($mdp_membership_id, $new_seats);
            $logger->info('MDP update result', array_merge($context, [
                'order_id' => $order_id,
                'mdp_membership_id' => $mdp_membership_id,
                'success' => (bool) $mdp_updated,
            ]));
        } else {
            $logger->warning('Missing membership UUID for MDP update', array_merge($context, [
                'order_id' => $order_id,
                'membership_post_id' => (int) $membership_post_id,
            ]));
        }

        // Add order meta for tracking
        $order->update_meta_data('additional_seats_processed', true);
        $order->update_meta_data('additional_seats_count', $total_additional_seats);
        $order->update_meta_data('org_uuid', $org_uuid);
        $order->update_meta_data('membership_id', $membership_id);
        $order->update_meta_data('membership_post_id_renew', $membership_post_id);
        $order->save();

        $logger->info('Stored additional seats tracking meta on order', array_merge($context, [
            'order_id' => $order_id,
            'additional_seats_processed' => true,
            'additional_seats_count' => (int) $total_additional_seats,
            'org_uuid_present' => $org_uuid !== '',
            'membership_id_present' => $membership_id !== '',
            'membership_post_id' => (int) $membership_post_id,
            'subscription_id' => (int) $subscription_id,
        ]));

        // Clear user meta data after successful processing
        $additional_seats_service->clearPurchaseUserMeta($user_id);

        $logger->debug('Cleared purchase user meta', array_merge($context, [
            'order_id' => $order_id,
            'customer_id' => (int) $user_id,
        ]));

        $logger->info('Additional seats order processed successfully', [
            'source' => 'wicket-orgman',
            'order_id' => $order_id,
            'subscription_id' => $subscription->get_id(),
            'additional_seats' => $total_additional_seats,
            'org_uuid' => $org_uuid,
            'membership_id' => $membership_id,
            'membership_post_id' => $membership_post_id,
            'previous_seats' => $current_seats,
            'new_seats' => $new_seats,
        ]);
    }

    public function filterWoocommerceReturnUrl($return_url, $order)
    {
        $logger = \Wicket()->log();
        $context = ['source' => 'wicket-orgman'];

        if (!$order || !is_object($order)) {
            return $return_url;
        }

        $logger->debug('woocommerce_get_return_url invoked', array_merge($context, [
            'order_id' => is_callable([$order, 'get_id']) ? (int) $order->get_id() : null,
            'order_status' => is_callable([$order, 'get_status']) ? (string) $order->get_status() : null,
            'return_url' => (string) $return_url,
        ]));

        if ($order->get_meta('additional_seats_processed', true)) {
            $target_url = $this->getOrganizationMembersUrlFromOrder($order) ?: $return_url;
            $logger->info('Return URL overridden (already processed additional seats)', array_merge($context, [
                'order_id' => (int) $order->get_id(),
                'target_url' => (string) $target_url,
            ]));

            return $target_url;
        }

        if (!$this->orderHasAdditionalSeats($order)) {
            return $return_url;
        }

        $target_url = $this->getOrganizationMembersUrlFromOrder($order) ?: $return_url;
        $logger->info('Return URL overridden (additional seats order)', array_merge($context, [
            'order_id' => (int) $order->get_id(),
            'target_url' => (string) $target_url,
        ]));

        return $target_url;
    }

    private function orderHasAdditionalSeats($order)
    {
        $logger = \Wicket()->log();
        $context = ['source' => 'wicket-orgman'];

        $additional_seats_service = $this->services['additional_seats'] ?? null;
        if (!$additional_seats_service) {
            $logger->error('Additional seats service missing; cannot detect product', array_merge($context, [
                'order_id' => is_callable([$order, 'get_id']) ? (int) $order->get_id() : null,
            ]));

            return false;
        }

        // Multi-tier: any tier-specific seat product qualifies the order.
        if ($additional_seats_service->isTierMode()) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) {
                    continue;
                }
                $tier_slug = $additional_seats_service->classifySeatProduct((int) $product->get_id());
                if ($tier_slug !== null) {
                    $logger->debug('Tier seat product detected on order', array_merge($context, [
                        'order_id' => (int) $order->get_id(),
                        'order_item_id' => $item->get_id(),
                        'tier_slug' => $tier_slug,
                    ]));

                    return true;
                }
            }
        }

        $product_id = $additional_seats_service->getAdditionalSeatsProduct();
        if (!$product_id) {
            $logger->error('Additional seats product not found; cannot detect additional seats order', array_merge($context, [
                'order_id' => is_callable([$order, 'get_id']) ? (int) $order->get_id() : null,
            ]));

            return false;
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && (int) $product->get_id() === (int) $product_id) {
                $logger->debug('Additional seats product detected on order', array_merge($context, [
                    'order_id' => (int) $order->get_id(),
                    'order_item_id' => $item->get_id(),
                    'product_id' => (int) $product_id,
                ]));

                return true;
            }
        }

        return false;
    }

    private function getOrganizationMembersUrlFromOrder($order)
    {
        $logger = \Wicket()->log();
        $context = ['source' => 'wicket-orgman'];

        $org_uuid = (string) $order->get_meta('org_uuid', true);
        if ($org_uuid === '') {
            foreach ($order->get_items() as $item) {
                $org_uuid = (string) $item->get_meta('org_uuid', true);
                if ($org_uuid !== '') {
                    break;
                }
            }
        }

        $logger->debug('Resolved org_uuid for return URL', array_merge($context, [
            'order_id' => is_callable([$order, 'get_id']) ? (int) $order->get_id() : null,
            'org_uuid_present' => $org_uuid !== '',
        ]));

        // Get WPML-aware URL for organization-members page
        $base_url = Helpers\Helper::getMyAccountPageUrl('organization-members', '/my-account/organization-members/');

        $logger->debug('Resolved base organization-members URL', array_merge($context, [
            'order_id' => is_callable([$order, 'get_id']) ? (int) $order->get_id() : null,
            'base_url' => (string) $base_url,
        ]));

        if ($org_uuid !== '') {
            $url = add_query_arg('org_uuid', $org_uuid, $base_url);
            $logger->debug('Built organization-members return URL', array_merge($context, [
                'order_id' => is_callable([$order, 'get_id']) ? (int) $order->get_id() : null,
                'url' => (string) $url,
            ]));

            return $url;
        }

        return $base_url;
    }

    /**
     * Whether an order contains any tier-specific seat line item (multi-tier mode).
     *
     * @param \WC_Order $order WooCommerce order.
     * @return bool
     */
    private function orderHasTierSeatItems($order): bool
    {
        $additional_seats_service = $this->services['additional_seats'] ?? null;
        if (!$additional_seats_service || !$additional_seats_service->isTierMode()) {
            return false;
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }
            if ($additional_seats_service->classifySeatProduct((int) $product->get_id()) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fulfil tier-mode additional seats per line item via the MDP only.
     *
     * For each tier seat line item: read its quantity and stamped org_uuid/membership_id, then
     * raise that organization membership's max_assignments by the purchased quantity. No
     * WooCommerce Subscription, wicket_membership CPT, or Membership_Controller interaction
     * occurs here. Per-item idempotency meta ('tier_seats_applied') makes re-runs safe.
     *
     * @param \WC_Order $order WooCommerce order.
     * @return int Number of line items successfully fulfilled.
     */
    private function processTierSeatLineItems($order): int
    {
        $logger = \Wicket()->log();
        $context = ['source' => 'wicket-orgman', 'order_id' => (int) $order->get_id()];
        $additional_seats_service = $this->services['additional_seats'];
        $fulfilled = 0;
        $total_tier_items = 0;

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $tier_slug = $additional_seats_service->classifySeatProduct((int) $product->get_id());
            if ($tier_slug === null) {
                continue;
            }

            $total_tier_items++;

            // Idempotency: skip items already applied.
            if ($item->get_meta('tier_seats_applied', true)) {
                $logger->debug('Tier seat item already applied; skipping', array_merge($context, [
                    'order_item_id' => $item->get_id(),
                    'tier_slug' => $tier_slug,
                ]));

                continue;
            }

            $membership_id = (string) $item->get_meta('membership_id', true);
            $org_uuid = (string) $item->get_meta('org_uuid', true);
            $qty = (int) $item->get_quantity();

            if ($membership_id === '' || $qty < 1) {
                $logger->error('Tier seat line item missing membership_id or quantity; skipping', array_merge($context, [
                    'order_item_id' => $item->get_id(),
                    'tier_slug' => $tier_slug,
                    'org_uuid' => $org_uuid,
                    'membership_id' => $membership_id,
                    'qty' => $qty,
                ]));

                continue;
            }

            $ok = $this->applyTierSeatIncrease($org_uuid, $membership_id, $qty);
            if ($ok) {
                $item->update_meta_data('tier_seats_applied', true);
                $item->update_meta_data('tier_seats_applied_qty', $qty);
                $item->save();
                $fulfilled++;
            }
        }

        // Only mark the order fully processed when EVERY tier line item has been fulfilled.
        // Per-item 'tier_seats_applied' meta governs re-entry safety, so partial fulfilment
        // (e.g. one MDP PATCH failing) leaves the order un-stamped and eligible for retry on the
        // next order-status hook. Without this, a single failure would permanently drop the
        // unfulfilled items even though the customer paid.
        if ($total_tier_items > 0 && $fulfilled >= $total_tier_items) {
            $order->update_meta_data('additional_seats_processed', true);
            $order->update_meta_data('tier_seats_processed', true);
            $order->save();
        } elseif ($fulfilled > 0 && $fulfilled < $total_tier_items) {
            // Partial fulfilment: do NOT set the order-level guard. Log so retries are traceable.
            $order->update_meta_data('tier_seats_partial', true);
            $order->save();
            $logger->warning('Tier seat fulfilment partial; order left eligible for retry', array_merge($context, [
                'fulfilled' => $fulfilled,
                'total_tier_items' => $total_tier_items,
            ]));
        }

        $logger->info('Tier seat line items processed', array_merge($context, [
            'fulfilled' => $fulfilled,
            'total_tier_items' => $total_tier_items,
        ]));

        return $fulfilled;
    }

    /**
     * Raise an organization membership's max_assignments by a purchased quantity via the MDP.
     *
     * Reads the current max_assignments, adds the purchased seats, and PATCHes the membership.
     *
     * @param string $org_uuid      Organization UUID (contextual logging).
     * @param string $membership_id Organization membership UUID.
     * @param int    $qty           Seats purchased.
     * @return bool True when the MDP was updated.
     */
    private function applyTierSeatIncrease(string $org_uuid, string $membership_id, int $qty): bool
    {
        $logger = \Wicket()->log();
        $context = [
            'source' => 'wicket-orgman',
            'org_uuid' => $org_uuid,
            'membership_id' => $membership_id,
            'qty' => $qty,
        ];

        /** @var Services\AdditionalSeatsService $service */
        $service = $this->services['additional_seats'];

        $current = $service->getMembershipCurrentMaxAssignments($membership_id);
        if ($current === null) {
            $logger->error('Could not read current max_assignments; aborting tier seat increase', $context);

            return false;
        }

        $new_max = $current + $qty;

        $logger->info('Applying tier seat increase', array_merge($context, [
            'current_max_assignments' => $current,
            'new_max_assignments' => $new_max,
        ]));

        $ok = $service->updateMdpMembershipMaxAssignments($membership_id, $new_max);
        if (!$ok) {
            $logger->error('MDP max_assignments update failed for tier seat increase', array_merge($context, [
                'new_max_assignments' => $new_max,
            ]));

            return false;
        }

        $logger->info('Tier seat increase applied', array_merge($context, [
            'new_max_assignments' => $new_max,
        ]));

        return true;
    }

    /**
     * Inject OrgMan content after the_content on specific my-account pages.
     *
     * @param string $content The original content.
     * @return string Modified content with OrgMan content appended.
     */
    public function injectOrgmanContent($content)
    {
        if (!$this->isOrgmanScreen() || !in_the_loop() || is_admin()) {
            return $content;
        }

        $slug = $this->getCurrentPageSlug();
        $content_map = $this->getContentMap();

        if (isset($content_map[$slug])) {
            // Include notifications container
            ob_start();
            include_once __DIR__ . '/templates-partials/notifications-container.php';
            $notifications = ob_get_clean();

            // Get the OrgMan content
            ob_start();
            include $content_map[$slug];
            $orgman_content = ob_get_clean();

            $orgman_markup = '<!-- ORGMAN:BEGIN -->' . $notifications . $orgman_content . '<!-- ORGMAN:END -->';

            if ($slug === 'organization-profile' || $slug === 'supplemental-members') {
                // For organization-profile and supplemental-members we need the OrgMan content
                // to appear before the post content to match legacy layout.
                return $orgman_markup . $content;
            }

            return $content . $orgman_markup;
        }

        return $content;
    }

    /**
     * Remove wpautop artifacts added inside OrgMan injected markup.
     *
     * @param string $content The filtered content.
     * @return string
     */
    public function cleanupOrgmanAutopArtifacts($content)
    {
        if (!is_string($content) || strpos($content, '<!-- ORGMAN:BEGIN -->') === false) {
            return $content;
        }

        return (string) preg_replace_callback(
            '/<!-- ORGMAN:BEGIN -->(.*?)<!-- ORGMAN:END -->/s',
            static function ($matches) {
                $segment = (string) ($matches[1] ?? '');

                // If wpautop touched script/style blocks, strip injected <p>/<br> from inside them.
                $segment = preg_replace_callback(
                    '/<(script|style)\b[^>]*>.*?<\/\1>/is',
                    static function ($block_match) {
                        $block = (string) ($block_match[0] ?? '');
                        $block = preg_replace('/<\/?p\b[^>]*>/i', '', $block);
                        $block = preg_replace('/<br\s*\/?>\s*/i', '', (string) $block);

                        return (string) $block;
                    },
                    $segment
                );

                // Unwrap script/style tags that were wrapped by paragraph tags.
                $segment = preg_replace(
                    '/<p>\s*(<(?:script|style)\b[^>]*>.*?<\/(?:script|style)>)\s*<\/p>/is',
                    '$1',
                    (string) $segment
                );

                // Remove empty paragraphs and auto-inserted line breaks in injected component markup.
                $segment = preg_replace('/<p>\s*<\/p>/i', '', $segment);
                $segment = preg_replace('/<br\s*\/?>\s*/i', '', (string) $segment);

                // Strip all wpautop-injected <p> wrappers from ORGMAN markup.
                // Our templates use <div> and flexbox for layout, never intentional <p> tags.
                $segment = preg_replace('/<\/?(?:p)\b[^>]*>/i', '', (string) $segment);

                return (string) $segment;
            },
            $content
        );
    }

    /**
     * Enqueue shared assets for OrgMan pages.
     */
    public function enqueueAssets()
    {
        $is_orgman = $this->isOrgmanScreen();

        if (!$is_orgman) {
            return;
        }

        $base_uri = $this->getBaseUri();
        $base_path = $this->getBasePath();

        $css_file_path = $base_path . '/public/css/modern-orgman-static.css';
        $css_version = file_exists($css_file_path) ? filemtime($css_file_path) : '1.0.0';
        wp_enqueue_style('orgman-modern', $base_uri . 'public/css/modern-orgman-static.css', [], $css_version);

        $datastar_error_path = $base_path . '/public/js/datastar-error-handler.js';
        $datastar_error_version = file_exists($datastar_error_path) ? filemtime($datastar_error_path) : '1.0.0';
        wp_enqueue_script('orgman-datastar-error-handler', $base_uri . 'public/js/datastar-error-handler.js', [], $datastar_error_version, false);

        $notifications_js_path = $base_path . '/public/js/orgman-notifications.js';
        $notifications_js_version = file_exists($notifications_js_path) ? filemtime($notifications_js_path) : '1.0.0';
        wp_enqueue_script('orgman-notifications', $base_uri . 'public/js/orgman-notifications.js', [], $notifications_js_version, true);

        $content_behaviors_js_path = $base_path . '/public/js/orgman-content-behaviors.js';
        $content_behaviors_js_version = file_exists($content_behaviors_js_path) ? filemtime($content_behaviors_js_path) : '1.0.0';
        wp_enqueue_script('orgman-content-behaviors', $base_uri . 'public/js/orgman-content-behaviors.js', [], $content_behaviors_js_version, true);

        // Load Datastar from CDN as module script
        $datastar_version = '1.0.1';
        $datastar_src = 'https://cdn.jsdelivr.net/gh/starfederation/datastar@v' . $datastar_version . '/bundles/datastar.js';
        wp_enqueue_script_module('wicket-datastar', $datastar_src, [], $datastar_version);
    }

    /**
     * Add a body class for OrgMan-managed my-account pages.
     *
     * @param array $classes
     * @return array
     */
    public function addOrgmanBodyClass($classes)
    {
        if (!is_array($classes)) {
            $classes = [];
        }

        if ($this->isOrgmanScreen()) {
            $classes[] = 'wicket-orgman-screen';
            $slug = $this->getCurrentPageSlug();

            if ($slug !== '') {
                $classes[] = 'wicket-orgman-page';
                $classes[] = 'wicket-orgman-page-' . sanitize_html_class($slug);
            }

            if ($slug === 'supplemental-members') {
                $classes[] = 'wicket-orgman-supplemental';
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * Resolve the base path for the org roster library.
     *
     * @return string
     */
    private function getBasePath(): string
    {
        $base_path = __DIR__;

        $base_path = (string) apply_filters('wicket/org-roster/base_path', $base_path);
        $base_path = (string) apply_filters('wicket/acc/orgman/base_path', $base_path);

        return $base_path;
    }

    /**
     * Resolve the base URL for the org roster library assets.
     *
     * @return string
     */
    private function getBaseUri(): string
    {
        $base_path = $this->normalizePath($this->getBasePath());
        $content_dir = defined('WP_CONTENT_DIR') ? $this->normalizePath(WP_CONTENT_DIR) : '';
        $abs_path = defined('ABSPATH') ? $this->normalizePath(ABSPATH) : '';
        $base_uri = trailingslashit(content_url(''));

        if ($this->pathIsWithin($base_path, $content_dir)) {
            $relative_path = $this->relativePath($base_path, $content_dir);
            $base_uri = trailingslashit(content_url($relative_path));
        } elseif ($this->pathIsWithin($base_path, $abs_path)) {
            $relative_path = $this->relativePath($base_path, $abs_path);
            $base_uri = trailingslashit(site_url($relative_path));
        }

        $base_uri = (string) apply_filters('wicket/org-roster/base_url', $base_uri);
        $base_uri = (string) apply_filters('wicket/acc/orgman/base_url', $base_uri);

        return trailingslashit($base_uri);
    }

    /**
     * Normalize filesystem paths for safe prefix comparisons.
     */
    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Check whether a path is inside a root path.
     */
    private function pathIsWithin(string $path, string $root): bool
    {
        if ($path === '' || $root === '') {
            return false;
        }

        return $path === $root || strpos($path, $root . '/') === 0;
    }

    /**
     * Build a relative path from a root.
     */
    private function relativePath(string $path, string $root): string
    {
        return ltrim(substr($path, strlen($root)), '/');
    }

    /**
     * Determine if the current request targets an OrgMan-managed page.
     *
     * @return bool
     */
    private function isOrgmanScreen()
    {
        if (!is_singular('my-account')) {
            return false;
        }

        $slug = $this->getCurrentPageSlug();

        return isset($this->getContentMap()[$slug]);
    }

    /**
     * Get the slug for the current My Account page request.
     *
     * @return string
     */
    private function getCurrentPageSlug()
    {
        $post = get_queried_object();

        if ($post instanceof \WP_Post) {
            return (string) $post->post_name;
        }

        return '';
    }

    /**
     * Map My Account slugs to content-only template paths for injection.
     *
     * @return array
     */
    private function getContentMap()
    {
        if (!empty($this->content_map)) {
            return $this->content_map;
        }

        $base_path = __DIR__;

        $this->content_map = [
            'organization-management'           => $base_path . '/templates/content-organization-index.php',
            'organization-profile'              => $base_path . '/templates/content-organization-profile.php',
            'organization-members'              => $base_path . '/templates/content-organization-members.php',
            'organization-members-bulk'         => $base_path . '/templates/content-organization-members-bulk.php',
            'organization-contacts'             => $base_path . '/templates/content-organization-contacts.php',
            'supplemental-members'              => $base_path . '/templates/content-supplemental-members.php',
            // Legacy ACC slug compatibility.
            'org-management'                    => $base_path . '/templates/content-organization-index.php',
            'org-management-profile'            => $base_path . '/templates/content-organization-profile.php',
            'org-management-members'            => $base_path . '/templates/content-organization-members.php',
            'org-management-roster'             => $base_path . '/templates/content-organization-members.php',
        ];

        return $this->content_map;
    }

    /**
     * Add additional seats data to order item from user meta.
     *
     * @param \WC_Order_Item_Product $item The order item.
     * @param string $cart_item_key The cart item key.
     * @param array $values The cart item values.
     * @param \WC_Order $order The order object.
     */
    public function addAdditionalSeatsDataToOrderItem($item, $cart_item_key, $values, $order)
    {
        $additional_seats_service = $this->services['additional_seats'];
        $logger = \Wicket()->log();

        $product = $item->get_product();
        if (!$product) {
            return;
        }
        $product_id = (int) $product->get_id();

        // --- Multi-tier path: classify the line item as a tier-specific seat product. ---
        // Truth comes from the cart item data ($values), not user meta, because an org may hold
        // several memberships and a single user-meta record cannot represent them all.
        if ($additional_seats_service->isTierMode()) {
            $tier_slug = $additional_seats_service->classifySeatProduct($product_id);
            if ($tier_slug !== null) {
                $org_uuid = isset($values['org_uuid']) ? sanitize_text_field((string) $values['org_uuid']) : '';
                $membership_id = isset($values['membership_id']) ? sanitize_text_field((string) $values['membership_id']) : '';
                $values_tier_slug = isset($values['tier_slug']) ? sanitize_text_field((string) $values['tier_slug']) : '';
                if ($values_tier_slug === '') {
                    $values_tier_slug = $tier_slug;
                }

                if ($org_uuid === '' || $membership_id === '') {
                    $logger->warning('Tier seat line item missing org_uuid/membership_id cart data', [
                        'source' => 'wicket-orgman',
                        'order_id' => $order->get_id(),
                        'item_id' => $item->get_id(),
                        'product_id' => $product_id,
                        'tier_slug' => $values_tier_slug,
                    ]);

                    return;
                }

                $item->update_meta_data('org_uuid', $org_uuid);
                $item->update_meta_data('_org_uuid', $org_uuid);
                $item->update_meta_data('membership_id', $membership_id);
                $item->update_meta_data('tier_slug', $values_tier_slug);
                $item->update_meta_data('additional_seats', true);

                $logger->info('Stamped tier seat data on order item', [
                    'source' => 'wicket-orgman',
                    'order_id' => $order->get_id(),
                    'item_id' => $item->get_id(),
                    'product_id' => $product_id,
                    'org_uuid' => $org_uuid,
                    'membership_id' => $membership_id,
                    'tier_slug' => $values_tier_slug,
                ]);

                return;
            }
        }

        // --- Legacy single-SKU path (unchanged). ---
        $additional_seats_product_id = $additional_seats_service->getAdditionalSeatsProduct();

        if (!$additional_seats_product_id) {
            return;
        }

        // Check if this is an additional seats product
        if ($product_id !== $additional_seats_product_id) {
            return;
        }

        // Get user meta data
        $user_id = $order->get_customer_id();
        $user_meta_data = $additional_seats_service->getPurchaseUserMeta($user_id);

        if (!$user_meta_data) {
            $logger->warning('No user meta data found for additional seats order item', [
                'source' => 'wicket-orgman',
                'user_id' => $user_id,
                'order_id' => $order->get_id(),
                'item_id' => $item->get_id(),
            ]);

            return;
        }

        // Store data in order item meta
        $item->update_meta_data('org_uuid', $user_meta_data['org_uuid']);
        $item->update_meta_data('_org_uuid', $user_meta_data['org_uuid']);
        $item->update_meta_data('membership_id', $user_meta_data['membership_id']);
        $item->update_meta_data('current_seats', $user_meta_data['membership_data']['membership']['current_max_assignments'] ?? 1);

        if (!empty($values['membership_post_id_renew'])) {
            $item->update_meta_data('membership_post_id_renew', (int) $values['membership_post_id_renew']);
            $item->update_meta_data('_membership_post_id_renew', (int) $values['membership_post_id_renew']);
        }

        $logger = \Wicket()->log();
        $logger->info('Added additional seats data to order item', [
            'source' => 'wicket-orgman',
            'user_id' => $user_id,
            'order_id' => $order->get_id(),
            'item_id' => $item->get_id(),
            'org_uuid' => $user_meta_data['org_uuid'],
            'membership_id' => $user_meta_data['membership_id'],
        ]);
    }

    /**
     * Clear organization cache for a specific user.
     *
     * @param string $user_uuid The user UUID to clear cache for.
     * @return void
     */
    public function clearUserOrgCache($user_uuid)
    {
        if (empty($user_uuid)) {
            return;
        }

        // Clear user organizations cache
        $cache_key = 'orgman_user_orgs_' . md5($user_uuid . '_' . $user_uuid);
        delete_transient($cache_key);

        // Clear all active organization caches for this user
        global $wpdb;
        $like_pattern = $wpdb->esc_like('orgman_active_orgs_' . md5($user_uuid . '_' . $user_uuid . '_')) . '%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                $like_pattern
            )
        );

        // Clear membership-related caches for this user
        $like_pattern = $wpdb->esc_like('orgman_membership_' . md5($user_uuid . '_')) . '%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                $like_pattern
            )
        );
    }

    /**
     * Clear members cache for a specific organization.
     *
     * @param string $membership_uuid The membership UUID to clear cache for.
     * @return void
     */
    public function clearMembersCache($membership_uuid)
    {
        if (empty($membership_uuid)) {
            return;
        }

        $this->services['member']->clearMembersCache($membership_uuid);
    }

    /**
     * Clear all organization management transients.
     *
     * @return void
     */
    public function clearAllOrgCache()
    {
        global $wpdb;

        // Clear all orgman transients
        $like_pattern = $wpdb->esc_like('_transient_orgman_') . '%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
                $like_pattern,
                str_replace('_transient_', '_transient_timeout_', $like_pattern)
            )
        );

        \Wicket()->log()->info('Cleared all organization management cache', ['source' => 'wicket-orgman']);
    }
}

// Backward-compatible alias for themes/plugins still referencing the pre-0.8 namespace.
if (!class_exists(\OrgManagement\OrgMan::class, false)) {
    class_alias(OrgMan::class, 'OrgManagement\\OrgMan');
}
