<?php

/**
 * Additional Seats Service for handling seat purchase functionality.
 */

namespace WicketORM\Services;

// Exit if accessed directly.
if (!defined('ABSPATH') && !defined('WICKET_DOING_TESTS')) {
    exit;
}

/**
 * Handles additional seats functionality for the organization management system.
 */
class AdditionalSeatsService
{
    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * Constructor.
     *
     * @param ConfigService $configService The configuration service.
     */
    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Check whether all prerequisites for the additional seats feature are in place.
     *
     * Returns an empty array when everything is configured correctly.
     * Returns a list of structured issue descriptors when one or more items are missing.
     *
     * Each descriptor is an array with:
     *   'parts' => array of segments, each either:
     *              ['type' => 'text',  'value' => string]  — plain translatable text
     *              ['type' => 'token', 'value' => string]  — copyable code token (SKU / slug)
     *
     * Checks performed:
     *  1. WooCommerce product with the configured SKU (and fallback SKU) exists and is purchasable.
     *  2. A Gravity Form is resolvable (by explicit form_id or by slug).
     *  3. A 'my-account' CPT post with slug 'supplemental-members' exists.
     *
     * @return array[] Empty array = all good; non-empty = list of structured issue descriptors.
     */
    public function getAdditionalSeatsSetupIssues(): array
    {
        $issues = [];

        // --- 1. WooCommerce product ---
        if (!function_exists('wc_get_product_id_by_sku') || !function_exists('wc_get_product')) {
            $issues[] = ['parts' => [
                ['type' => 'text', 'value' => __('WooCommerce is not active. The additional seats feature requires WooCommerce.', 'wicket-acc')],
            ]];
        } else {
            $primary_sku = $this->configService->getAdditionalSeatsSku();
            $fallback_sku = 'corporate-seats';
            $skus_to_check = array_values(array_unique(array_filter([$primary_sku, $fallback_sku])));

            $found_product = false;
            foreach ($skus_to_check as $sku) {
                $product_id = wc_get_product_id_by_sku($sku);
                if ($product_id) {
                    $product = wc_get_product($product_id);
                    if ($product && $product->is_purchasable()) {
                        $found_product = true;
                        break;
                    }
                }
            }

            if (!$found_product) {
                $parts = [
                    ['type' => 'text', 'value' => __('No purchasable WooCommerce product found. Create a Simple product with SKU ', 'wicket-acc')],
                ];
                foreach ($skus_to_check as $i => $sku) {
                    if ($i > 0) {
                        $parts[] = ['type' => 'text', 'value' => __(' or ', 'wicket-acc')];
                    }
                    $parts[] = ['type' => 'token', 'value' => $sku];
                }
                $parts[] = ['type' => 'text', 'value' => __(' and set it to Published/Purchasable.', 'wicket-acc')];
                $issues[] = ['parts' => $parts];
            }
        }

        // --- 2. Gravity Form ---
        $form_id = $this->configService->getAdditionalSeatsFormId();
        if (empty($form_id)) {
            $config = $this->configService->getFullConfig();
            $form_slug = $config['integrations']['additional_seats']['form_slug'] ?? 'additional-seats';
            $issues[] = ['parts' => [
                ['type' => 'text',  'value' => __('No Gravity Form configured for additional seats. Create a Gravity Form and map its slug ', 'wicket-acc')],
                ['type' => 'token', 'value' => $form_slug],
                ['type' => 'text',  'value' => __(' via Gravity Forms > Wicket Settings > Form Slug ID Mapping.', 'wicket-acc')],
            ]];
        }

        // --- 3. supplemental-members page ---
        $page_posts = get_posts([
            'post_type'   => 'my-account',
            'name'        => 'supplemental-members',
            'numberposts' => 1,
            'post_status' => 'publish',
        ]);
        if (empty($page_posts)) {
            $issues[] = ['parts' => [
                ['type' => 'text',  'value' => __('The ', 'wicket-acc')],
                ['type' => 'token', 'value' => 'supplemental-members'],
                ['type' => 'text',  'value' => __(' my-account page is missing. Create a "my-account" CPT entry with slug ', 'wicket-acc')],
                ['type' => 'token', 'value' => 'supplemental-members'],
                ['type' => 'text',  'value' => __(' and embed the additional seats Gravity Form on it.', 'wicket-acc')],
            ]];
        }

        return $issues;
    }

    /**
     * Check if the current user is authorized to purchase additional seats for an organization.
     * Requires roles configured in 'purchase_seats' permission (membership_owner, membership_manager, or org_editor by default).
     *
     * @param string $org_uuid The organization UUID.
     * @return bool True if user is authorized, false otherwise.
     */
    public function canPurchaseAdditionalSeats($org_uuid)
    {
        // Check if additional seats functionality is enabled
        $enabled = $this->configService->isAdditionalSeatsEnabled();

        if (!$enabled) {
            return false;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return false;
        }

        // Use PermissionHelper to check if user has purchase_seats permission for this organization
        // This includes active membership requirement and proper role checking
        return \WicketORM\Helpers\PermissionHelper::can_purchase_seats($org_uuid);
    }

    /**
     * Get the additional seats product by SKU.
     *
     * @return int|null The product ID or null if not found.
     */
    public function getAdditionalSeatsProduct()
    {
        $primary_sku = $this->configService->getAdditionalSeatsSku();
        $fallback_skus = ['corporate-seats'];
        $skus = array_values(array_unique(array_filter(array_merge([$primary_sku], $fallback_skus))));
        $product = $this->resolvePurchasableProductBySkus($skus, 'Additional seats product');

        return (int) ($product['product_id'] ?? 0) ?: null;
    }

    /**
     * Get the discount product used to offset additional seat pricing on renewal.
     *
     * @return int|null The product ID or null if not found.
     */
    public function getAdditionalSeatsDiscountProduct()
    {
        $discount_sku = $this->configService->getAdditionalSeatsDiscountSku();
        $skus = array_values(array_unique(array_filter([$discount_sku])));
        $product = $this->resolvePurchasableProductBySkus($skus, 'Additional seats discount product');

        return (int) ($product['product_id'] ?? 0) ?: null;
    }

    /**
     * Resolve a purchasable product by one or more candidate SKUs.
     *
     * @param array<int, string> $skus Candidate SKUs in preferred order.
     * @param string $product_context Product label used in logs.
     * @return array{product_id:int, resolved_sku:string}|null
     */
    private function resolvePurchasableProductBySkus(array $skus, $product_context = 'Product')
    {
        $logger = \Wicket()->log();

        if (empty($skus)) {
            return null;
        }

        $current_lang = null;
        $default_lang = null;
        if (defined('ICL_SITEPRESS_VERSION')) {
            $current_lang = apply_filters('wpml_current_language', null);
            if (!is_string($current_lang) || $current_lang === '') {
                $current_lang = function_exists('wicket_get_current_language') ? wicket_get_current_language() : null;
            }
            $default_lang = apply_filters('wpml_default_language', null);
        }

        $product_id = null;
        $resolved_sku = null;
        $fallback_id = null;
        $fallback_sku = null;
        foreach ($skus as $sku) {
            $candidate_id = wc_get_product_id_by_sku($sku);
            if (empty($candidate_id) && is_string($current_lang) && $current_lang !== '' && is_string($default_lang) && $default_lang !== '' && $current_lang !== $default_lang) {
                do_action('wpml_switch_language', $default_lang);
                $candidate_id = wc_get_product_id_by_sku($sku);
                do_action('wpml_switch_language', $current_lang);
            }
            if (empty($candidate_id)) {
                continue;
            }

            if (is_string($current_lang) && $current_lang !== '' && is_string($default_lang) && $default_lang !== '' && $current_lang !== $default_lang) {
                $translated_id = apply_filters('wpml_object_id', $candidate_id, 'product', false, $current_lang);
                if ($translated_id) {
                    $product_id = (int) $translated_id;
                    $resolved_sku = $sku;
                    break;
                }
                if (empty($fallback_id)) {
                    $fallback_id = (int) $candidate_id;
                    $fallback_sku = $sku;
                }
                continue;
            }

            $product_id = (int) $candidate_id;
            $resolved_sku = $sku;
            break;
        }

        if (!$product_id && $fallback_id) {
            $product_id = $fallback_id;
            $resolved_sku = $fallback_sku;
            $logger->warning('[OrgMan] ' . $product_context . ' translation missing, using default language product', [
                'source' => 'wicket-orgman',
                'product_id' => (int) $product_id,
                'sku' => $resolved_sku,
                'language' => is_string($current_lang) ? $current_lang : null,
                'default_language' => is_string($default_lang) ? $default_lang : null,
            ]);
        }

        if (!$product_id) {
            $logger->warning('[OrgMan] ' . $product_context . ' not found for configured SKUs', [
                'source' => 'wicket-orgman',
                'skus' => $skus,
                'language' => is_string($current_lang) ? $current_lang : null,
            ]);

            return null;
        }

        $product = wc_get_product($product_id);

        if (!$product || !$product->is_purchasable()) {
            $logger->error('[OrgMan] ' . $product_context . ' is not purchasable: ' . $product_id, ['source' => 'wicket-orgman']);

            return null;
        }

        $logger->info('[OrgMan] ' . $product_context . ' resolved for purchase', [
            'source' => 'wicket-orgman',
            'product_id' => (int) $product_id,
            'sku' => $resolved_sku,
            'language' => is_string($current_lang) ? $current_lang : null,
        ]);

        return [
            'product_id' => (int) $product_id,
            'resolved_sku' => (string) $resolved_sku,
        ];
    }

    /**
     * Get product information by SKU.
     *
     * @return array|null Product information or null if not found.
     */
    public function getAdditionalSeatsProductInfo()
    {
        $product_id = $this->getAdditionalSeatsProduct();

        if (!$product_id) {
            return null;
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            return null;
        }

        return [
            'id' => $product_id,
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'price' => $product->get_price(),
            'description' => $product->get_description(),
            'is_purchasable' => $product->is_purchasable(),
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
        ];
    }

    /**
     * Update the subscription seat count after purchase.
     *
     * @param int $order_id The WooCommerce order ID.
     * @param int $subscription_id The subscription ID.
     * @param int $additional_seats The number of additional seats purchased.
     * @return bool True if successful, false otherwise.
     */
    public function updateSubscriptionSeatCount($order_id, $subscription_id, $additional_seats)
    {
        $logger = \Wicket()->log();

        try {
            $subscription = wcs_get_subscription($subscription_id);

            if (!$subscription) {
                $logger->error('[OrgMan] Subscription not found: ' . $subscription_id, ['source' => 'wicket-orgman']);

                return false;
            }

            // Get current seat limit from subscription meta
            $current_seat_limit = $subscription->get_meta('seat_limit', true);
            $new_seat_limit = (int) $current_seat_limit + (int) $additional_seats;

            // Update subscription meta
            $subscription->update_meta_data('seat_limit', $new_seat_limit);
            $subscription->save();

            // Update MDP via Wicket API if available
            $this->updateMdpSeatLimit($subscription, $new_seat_limit);

            $logger->info('[OrgMan] Updated subscription seat count', [
                'source' => 'wicket-orgman',
                'order_id' => $order_id,
                'subscription_id' => $subscription_id,
                'previous_seats' => $current_seat_limit,
                'additional_seats' => $additional_seats,
                'new_total' => $new_seat_limit,
            ]);

            return true;

        } catch (\Exception $e) {
            $logger->error('[OrgMan] Failed to update subscription seat count: ' . $e->getMessage(), [
                'source' => 'wicket-orgman',
                'order_id' => $order_id,
                'subscription_id' => $subscription_id,
                'additional_seats' => $additional_seats,
            ]);

            return false;
        }
    }

    public function updateMdpMembershipMaxAssignments($membership_id, $new_max_assignments)
    {
        $logger = \Wicket()->log();

        $membership_id = is_string($membership_id) ? trim($membership_id) : '';
        $new_max_assignments = (int) $new_max_assignments;

        if (empty($membership_id) || $new_max_assignments < 0) {
            $logger->error('[OrgMan] Invalid MDP update request', [
                'source' => 'wicket-orgman',
                'membership_id' => $membership_id,
                'new_max_assignments' => $new_max_assignments,
            ]);

            return false;
        }

        try {
            if (!function_exists('wicket_api_client')) {
                $logger->error('[OrgMan] wicket_api_client function not available', [
                    'source' => 'wicket-orgman',
                    'membership_id' => $membership_id,
                ]);

                return false;
            }

            $current_membership = $this->getMembershipDataFromApi($membership_id);

            if (empty($current_membership) || !is_array($current_membership)) {
                $logger->error('[OrgMan] Failed to fetch current MDP membership', [
                    'source' => 'wicket-orgman',
                    'membership_id' => $membership_id,
                ]);

                return false;
            }

            $payload = [
                'data' => [
                    'type' => 'organization_memberships',
                    'attributes' => [
                        'max_assignments' => $new_max_assignments,
                    ],
                ],
            ];

            $client = wicket_api_client();
            $api_path = "organization_memberships/{$membership_id}";

            try {
                $response = $client->patch($api_path, ['json' => $payload]);
            } catch (\Exception $e) {
                $logger->error('[OrgMan] MDP API request failed with exception', [
                    'source' => 'wicket-orgman',
                    'membership_id' => $membership_id,
                    'new_max_assignments' => $new_max_assignments,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }

            if ($response && isset($response['data'])) {
                $logger->info('[OrgMan] Updated MDP max_assignments', [
                    'source' => 'wicket-orgman',
                    'membership_id' => $membership_id,
                    'new_max_assignments' => $new_max_assignments,
                ]);

                return true;
            }

            $logger->error('[OrgMan] MDP update returned invalid response', [
                'source' => 'wicket-orgman',
                'membership_id' => $membership_id,
                'new_max_assignments' => $new_max_assignments,
                'response' => $response,
            ]);

            return false;
        } catch (\Throwable $e) {
            $logger->error('[OrgMan] Failed to update MDP max_assignments: ' . $e->getMessage(), [
                'source' => 'wicket-orgman',
                'membership_id' => $membership_id,
                'new_max_assignments' => $new_max_assignments,
            ]);

            return false;
        }
    }

    /**
     * Update the Membership Data Platform (MDP) seat limit.
     *
     * @param \WC_Subscription $subscription The subscription object.
     * @param int $new_seat_limit The new seat limit.
     * @return bool True if successful, false otherwise.
     */
    private function updateMdpSeatLimit($subscription, $new_seat_limit)
    {
        $logger = \Wicket()->log();

        try {
            // Get comprehensive membership data from order meta
            $order_id = $subscription->get_parent_id();
            $order = wc_get_order($order_id);

            if (!$order) {
                $logger->error('[OrgMan] Cannot find order for subscription', [
                    'source' => 'wicket-orgman',
                    'subscription_id' => $subscription->get_id(),
                    'order_id' => $order_id,
                ]);

                return false;
            }

            $membership_data = $order->get_meta('orgman_membership_data', true);

            if (empty($membership_data)) {
                $logger->error('[OrgMan] No membership data found in order', [
                    'source' => 'wicket-orgman',
                    'subscription_id' => $subscription->get_id(),
                    'order_id' => $order_id,
                ]);

                return false;
            }

            // Get additional seats count from order meta
            $additional_seats = (int) $order->get_meta('additional_seats_count', true);
            $current_seats = (int) ($membership_data['membership']['current_max_assignments'] ?? 1);

            // Calculate new seat limit
            $new_calculated_limit = $current_seats + $additional_seats;

            // Construct the MDP API payload
            $org_uuid = $membership_data['organization']['id'];
            $membership_id = $membership_data['membership']['id'];

            $payload = [
                'data' => [
                    'type' => 'organization_memberships',
                    'id' => $membership_id,
                    'attributes' => array_merge($membership_data['membership']['attributes'], [
                        'max_assignments' => $new_calculated_limit,
                    ]),
                ],
            ];

            $logger->info('[OrgMan] Sending MDP API request', [
                'source' => 'wicket-orgman',
                'subscription_id' => $subscription->get_id(),
                'order_id' => $order_id,
                'current_seats' => $current_seats,
                'additional_seats' => $additional_seats,
                'new_limit' => $new_calculated_limit,
            ]);

            // Make the API request using Wicket MDP client
            if (!function_exists('wicket_api_client')) {
                $logger->error('[OrgMan] wicket_api_client function not available', [
                    'source' => 'wicket-orgman',
                    'subscription_id' => $subscription->get_id(),
                ]);

                return false;
            }

            $client = wicket_api_client();
            $api_path = "/organization_memberships/{$membership_id}";

            $logger->info('[OrgMan] Making MDP API request using client SDK', [
                'source' => 'wicket-orgman',
                'subscription_id' => $subscription->get_id(),
                'order_id' => $order_id,
                'api_path' => $api_path,
                'current_seats' => $current_seats,
                'additional_seats' => $additional_seats,
                'new_limit' => $new_calculated_limit,
            ]);

            try {
                $response = $client->patch($api_path, ['json' => $payload]);

                if ($response && isset($response['data'])) {
                    $logger->info('[OrgMan] MDP API request successful', [
                        'source' => 'wicket-orgman',
                        'subscription_id' => $subscription->get_id(),
                        'order_id' => $order_id,
                        'new_limit' => $new_calculated_limit,
                    ]);

                    return true;
                } else {
                    $logger->error('[OrgMan] MDP API request returned invalid response', [
                        'source' => 'wicket-orgman',
                        'subscription_id' => $subscription->get_id(),
                        'order_id' => $order_id,
                        'response' => $response,
                    ]);

                    return false;
                }

            } catch (\Exception $e) {
                $logger->error('[OrgMan] MDP API request failed with exception', [
                    'source' => 'wicket-orgman',
                    'subscription_id' => $subscription->get_id(),
                    'order_id' => $order_id,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }

        } catch (\Exception $e) {
            $logger->error('[OrgMan] Failed to update MDP seat limit: ' . $e->getMessage(), [
                'source' => 'wicket-orgman',
                'subscription_id' => $subscription->get_id(),
            ]);

            return false;
        }
    }

    /**
     * Store additional seats purchase data in user meta.
     *
     * @param string $org_uuid The organization UUID.
     * @param string $membership_id The membership ID.
     * @param array $membership_data The membership data needed for MDP API.
     * @return bool True if stored successfully.
     */
    public function storePurchaseUserMeta($org_uuid, $membership_id, $membership_data)
    {
        $current_user_id = get_current_user_id();

        if (!$current_user_id) {
            return false;
        }

        $purchase_data = [
            'org_uuid' => $org_uuid,
            'membership_id' => $membership_id,
            'membership_data' => $membership_data,
            'created_at' => current_time('mysql'),
        ];

        $success = update_user_meta($current_user_id, 'orgman_additional_seats_data', $purchase_data);

        $logger = \Wicket()->log();
        if ($success) {
            $logger->info('[OrgMan] Purchase user meta stored', [
                'source' => 'wicket-orgman',
                'user_id' => $current_user_id,
                'org_uuid' => $org_uuid,
                'membership_id' => $membership_id,
            ]);
        } else {
            $logger->error('[OrgMan] Failed to store purchase user meta', [
                'source' => 'wicket-orgman',
                'user_id' => $current_user_id,
                'org_uuid' => $org_uuid,
                'membership_id' => $membership_id,
            ]);
        }

        return $success;
    }

    /**
     * Get stored purchase user meta.
     *
     * @param int $user_id Optional user ID, defaults to current user.
     * @return array|null The stored data or null if not found.
     */
    public function getPurchaseUserMeta($user_id = 0)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return null;
        }

        return get_user_meta($user_id, 'orgman_additional_seats_data', true);
    }

    /**
     * Clear purchase user meta.
     *
     * @param int $user_id Optional user ID, defaults to current user.
     * @return bool True if cleared successfully.
     */
    public function clearPurchaseUserMeta($user_id = 0)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        return delete_user_meta($user_id, 'orgman_additional_seats_data');
    }

    /**
     * Get comprehensive membership data for MDP API.
     *
     * @param string $org_uuid The organization UUID.
     * @param string $membership_id The membership ID.
     * @return array|null Membership data or null if not found.
     */
    public function getMembershipDataForMdp($org_uuid, $membership_id)
    {
        $logger = \Wicket()->log();

        try {
            // Get organization data
            $org_data = null;
            if (function_exists('wicket_get_organization')) {
                $org_response = wicket_get_organization($org_uuid);
                if (is_array($org_response) && isset($org_response['data'])) {
                    $org_data = $org_response['data'];
                }
            }

            if (!$org_data) {
                $logger->error('[OrgMan] Failed to get organization data', [
                    'source' => 'wicket-orgman',
                    'org_uuid' => $org_uuid,
                ]);

                return null;
            }

            // Get membership data to find current seat limits and relationships
            $current_membership = $this->getMembershipDataFromApi($membership_id);

            if (!$current_membership) {
                $logger->error('[OrgMan] Failed to get current membership data', [
                    'source' => 'wicket-orgman',
                    'membership_id' => $membership_id,
                ]);

                return null;
            }

            // Extract current max_assignments from membership
            $current_max_assignments = $current_membership['attributes']['max_assignments'] ?? 1;

            $membership_data = [
                'organization' => [
                    'id' => $org_uuid,
                    'type' => 'organizations',
                    'meta' => [
                        'ancestry_depth' => $org_data['attributes']['ancestry_depth'] ?? 0,
                        'can_manage' => true,
                        'can_update' => true,
                    ],
                ],
                'membership' => [
                    'id' => $membership_id,
                    'type' => 'organization_memberships',
                    'current_max_assignments' => $current_max_assignments,
                    'attributes' => [
                        'starts_at' => $current_membership['attributes']['starts_at'] ?? null,
                        'ends_at' => $current_membership['attributes']['ends_at'] ?? null,
                        'grace_period_days' => $current_membership['attributes']['grace_period_days'] ?? 0,
                        'grant_owner_assignment' => $current_membership['attributes']['grant_owner_assignment'] ?? false,
                        'copy_previous_assignments' => $current_membership['attributes']['copy_previous_assignments'] ?? false,
                        'is_cascadeable' => $current_membership['attributes']['is_cascadeable'] ?? false,
                    ],
                    'relationships' => $current_membership['relationships'] ?? [],
                ],
            ];

            return $membership_data;

        } catch (\Exception $e) {
            $logger->error('[OrgMan] Failed to collect membership data: ' . $e->getMessage(), [
                'source' => 'wicket-orgman',
                'org_uuid' => $org_uuid,
                'membership_id' => $membership_id,
            ]);

            return null;
        }
    }

    /**
     * Get the Gravity Form URL for additional seats purchase.
     *
     * @param string $org_uuid The organization UUID.
     * @param string $membership_id The membership ID.
     * @return string The form URL.
     */
    public function getPurchaseFormUrl($org_uuid, $membership_id)
    {
        $form_id = $this->configService->getAdditionalSeatsFormIdForCurrentLanguage();

        if (empty($form_id)) {
            return '';
        }

        // Find the my-account CPT page with slug 'supplemental-members'
        $args = [
            'post_type' => 'my-account',
            'name' => 'supplemental-members',
            'numberposts' => 1,
        ];

        $posts = get_posts($args);

        if (!empty($posts)) {
            $post_id = $posts[0]->ID;

            // Only attempt multilingual translation if multilingual plugin is active
            if (function_exists('wicket_is_multilang_active') && wicket_is_multilang_active()) {
                // Get current language
                $current_lang = function_exists('wicket_get_current_language') ? wicket_get_current_language() : null;

                // Get translated post ID if language is available
                if ($current_lang && function_exists('apply_filters')) {
                    $translated_post_id = apply_filters('wpml_object_id', $post_id, 'my-account', false, $current_lang);
                    if ($translated_post_id) {
                        $post_id = $translated_post_id;
                    }
                }
            }

            $base_url = get_permalink($post_id);
        } else {
            // Fallback if page not found
            $base_url = home_url('/my-account/supplemental-members/');
        }

        // Get comprehensive membership data
        $membership_data = $this->getMembershipDataForMdp($org_uuid, $membership_id);

        if (!$membership_data) {
            return '';
        }

        // Store data in user meta for later use
        $stored = $this->storePurchaseUserMeta($org_uuid, $membership_id, $membership_data);
        if (!$stored) {
            $logger = \Wicket()->log();
            $logger->error('[OrgMan] Unable to build purchase form URL: failed to persist purchase user meta', [
                'source' => 'wicket-orgman',
                'org_uuid' => is_string($org_uuid) ? $org_uuid : null,
                'membership_id' => is_string($membership_id) ? $membership_id : null,
            ]);

            return '';
        }

        $args = [
            'org_uuid' => $org_uuid,
            'membership_id' => $membership_id,
            'gf_id' => $form_id,
            'current_seats' => $membership_data['membership']['current_max_assignments'] ?? 1,
        ];

        return add_query_arg($args, $base_url);
    }

    /**
     * Get membership data from Wicket API.
     *
     * @param string $membership_id The membership ID.
     * @return array|null Membership data or null if not found.
     */
    private function getMembershipDataFromApi($membership_id)
    {
        $logger = \Wicket()->log();

        try {
            if (!function_exists('wicket_api_client')) {
                $logger->error('[OrgMan] wicket_api_client function not available', [
                    'source' => 'wicket-orgman',
                    'membership_id' => $membership_id,
                ]);

                return null;
            }

            $client = wicket_api_client();
            $api_path = "/organization_memberships/{$membership_id}";

            $logger->info('[OrgMan] Fetching membership data using client SDK', [
                'source' => 'wicket-orgman',
                'membership_id' => $membership_id,
                'api_path' => $api_path,
            ]);

            try {
                $response = $client->get($api_path);

                if ($response && isset($response['data'])) {
                    return $response['data'];
                } else {
                    $logger->error('[OrgMan] Invalid response from membership API', [
                        'source' => 'wicket-orgman',
                        'membership_id' => $membership_id,
                        'response' => $response,
                    ]);

                    return null;
                }

            } catch (\Exception $e) {
                $logger->error('[OrgMan] Failed to fetch membership data with exception', [
                    'source' => 'wicket-orgman',
                    'membership_id' => $membership_id,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }

        } catch (\Exception $e) {
            $logger->error('[OrgMan] Exception fetching membership data: ' . $e->getMessage(), [
                'source' => 'wicket-orgman',
                'membership_id' => $membership_id,
            ]);

            return null;
        }
    }
}
