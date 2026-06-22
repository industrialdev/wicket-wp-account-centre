<?php
/**
 * Configuration Controller for Org Management.
 *
 * Handles configuration settings, filters, and hooks for the org-management system.
 * Replaces the additional-seats-config.php include file with a proper controller.
 */

namespace WicketORM\Controllers;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuration Controller class.
 */
class ConfigurationController
{
    /**
     * @var \WicketORM\Services\ConfigService
     */
    private $configService;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->configService = new \WicketORM\Services\ConfigService();
    }

    /**
     * Initialize configuration hooks and filters.
     */
    public function init(): void
    {
        $this->configureAdditionalSeats();
    }

    /**
     * Configure Additional Seats feature using config values.
     */
    private function configureAdditionalSeats(): void
    {
        // Get configuration values to use as defaults
        $config = $this->configService;

        // Enable/disable additional seats functionality
        add_filter('wicket/org-roster/additional_seats_enabled', function ($enabled) use ($config) {
            // Get config value directly to avoid recursion
            $config_data = $this->configService->getFullConfig();

            return $config_data['integrations']['additional_seats']['enabled'] ?? false;
        });

        // Set the SKU for additional seats product
        add_filter('wicket/org-roster/additional_seats_sku', function ($sku) use ($config) {
            // Get config value directly to avoid recursion
            $config_data = $this->configService->getFullConfig();

            return $config_data['integrations']['additional_seats']['sku'] ?? 'additional-seats';
        });

        // Set the SKU for additional seats discount product
        add_filter('wicket/org-roster/additional_seats_discount_sku', function ($sku) use ($config) {
            // Get config value directly to avoid recursion
            $config_data = $this->configService->getFullConfig();

            return $config_data['integrations']['additional_seats']['discount_sku'] ?? 'corporate-seat-discount';
        });

        // Set the Gravity Form ID for additional seats purchase
        add_filter('wicket/org-roster/additional_seats_form_id', function ($form_id) use ($config) {
            // Get config value directly to avoid recursion, but still support auto-detection if set to 0
            $config_data = $this->configService->getFullConfig();
            $default_form_id = $config_data['integrations']['additional_seats']['form_id'] ?? 0;
            if ($default_form_id === 0 && function_exists('wicket_gf_get_form_id_by_slug')) {
                $slug = $config_data['integrations']['additional_seats']['form_slug'] ?? 'additional-seats';
                $slug = is_string($slug) ? trim($slug) : '';
                $detected_form_id = $slug !== '' ? wicket_gf_get_form_id_by_slug($slug) : 0;

                return $detected_form_id ?: 0; // Return 0 if form not found
            }

            return $default_form_id;
        });

        // Set a minimum quantity for additional seats purchase
        add_filter('wicket/org-roster/additional_seats_min_quantity', function ($min_quantity) use ($config) {
            // Get config value directly to avoid recursion
            $config_data = $this->configService->getFullConfig();

            return $config_data['integrations']['additional_seats']['min_quantity'] ?? 1;
        });

        // Set a maximum quantity for additional seats purchase
        add_filter('wicket/org-roster/additional_seats_max_quantity', function ($max_quantity) use ($config) {
            // Get config value directly to avoid recursion
            $config_data = $this->configService->getFullConfig();

            return $config_data['integrations']['additional_seats']['max_quantity'] ?? 100;
        });
    }

    /**
     * Add additional seats notice on organization members page.
     */
    public function addAdditionalSeatsNotice(): void
    {
        if (!$this->configService->isAdditionalSeatsEnabled()) {
            return;
        }

        // Only show on organization members page
        if (!$this->isPage('organization-members')) {
            return;
        }

        // Check if user can purchase additional seats
        $org_uuid = isset($_GET['org_uuid']) ? sanitize_text_field($_GET['org_uuid']) : '';
        if (empty($org_uuid)) {
            return;
        }

        $additional_seats_service = new \WicketORM\Services\AdditionalSeatsService($this->configService);

        if ($additional_seats_service->canPurchaseAdditionalSeats($org_uuid)) {
            ?>
            <div class="orgman-notice orgman-additional-seats-notice" style="background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <p><strong><?php esc_html_e('Need more seats?', 'wicket-acc'); ?></strong></p>
                <p><?php esc_html_e('As the membership owner, you can purchase additional seats for your organization membership. Click the "Purchase Additional Seats" button below to get started.', 'wicket-acc'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Add custom CSS for additional seats styling.
     */
    public function addAdditionalSeatsCss(): void
    {
        if (!$this->isPage('organization-members') && !$this->isPage('supplemental-members')) {
            return;
        }
        ?>
        <style>
            .orgman-additional-seats-notice {
                border-left: 4px solid #0073aa !important;
            }

            .button.secondary.additional-seats-cta {
                background-color: #0073aa !important;
                border-color: #0073aa !important;
                color: white !important;
            }

            .button.secondary.additional-seats-cta:hover {
                background-color: #005a87 !important;
                border-color: #005a87 !important;
            }
        </style>
        <?php
    }

    /**
     * Add custom JavaScript for additional seats functionality.
     */
    public function addAdditionalSeatsJs(): void
    {
        if (!$this->isPage('organization-members') && !$this->isPage('supplemental-members')) {
            return;
        }
        ?>
        <script>
            // Example: Track additional seats button clicks
            document.addEventListener('DOMContentLoaded', function() {
                var purchaseButtons = document.querySelectorAll('[onclick*="supplemental-members"]');

                purchaseButtons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        // You can add analytics tracking here
                        console.log('Additional seats purchase button clicked');
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Enable the additional seats notice.
     */
    public function enableAdditionalSeatsNotice(): void
    {
        add_action('wp_footer', [$this, 'addAdditionalSeatsNotice']);
    }

    /**
     * Enable the additional seats CSS.
     */
    public function enableAdditionalSeatsCss(): void
    {
        add_action('wp_head', [$this, 'addAdditionalSeatsCss']);
    }

    /**
     * Enable the additional seats JavaScript.
     */
    public function enableAdditionalSeatsJs(): void
    {
        add_action('wp_footer', [$this, 'addAdditionalSeatsJs']);
    }

    /**
     * Helper function to check if we're on a specific page.
     * You can customize this based on your WordPress setup.
     *
     * @param string $page_slug The page slug to check.
     * @return bool True if on the specified page, false otherwise.
     */
    private function isPage(string $page_slug): bool
    {
        global $wp;

        if (isset($wp->query_vars['pagename'])) {
            return $wp->query_vars['pagename'] === $page_slug;
        }

        // Alternative check using the current URL
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        return strpos($current_url, '/' . $page_slug) !== false;
    }

    /**
     * Get additional seats configuration.
     *
     * @return array Additional seats configuration.
     */
    public function getAdditionalSeatsConfig(): array
    {
        return [
            'enabled' => apply_filters('wicket/org-roster/additional_seats_enabled', false),
            'sku' => apply_filters('wicket/org-roster/additional_seats_sku', 'additional-seats'),
            'discount_sku' => apply_filters('wicket/org-roster/additional_seats_discount_sku', 'corporate-seat-discount'),
            'form_id' => apply_filters('wicket/org-roster/additional_seats_form_id', 0),
            'min_quantity' => apply_filters('wicket/org-roster/additional_seats_min_quantity', 1),
            'max_quantity' => apply_filters('wicket/org-roster/additional_seats_max_quantity', 100),
        ];
    }

    /**
     * Check if additional seats feature is enabled.
     *
     * @return bool True if enabled, false otherwise.
     */
    public function isAdditionalSeatsEnabled(): bool
    {
        return (bool) apply_filters('wicket/org-roster/additional_seats_enabled', false);
    }

    /**
     * Get additional seats product SKU.
     *
     * @return string The SKU.
     */
    public function getAdditionalSeatsSku(): string
    {
        return (string) apply_filters('wicket/org-roster/additional_seats_sku', 'additional-seats');
    }

    /**
     * Get additional seats discount product SKU.
     *
     * @return string The SKU.
     */
    public function getAdditionalSeatsDiscountSku(): string
    {
        return (string) apply_filters('wicket/org-roster/additional_seats_discount_sku', 'corporate-seat-discount');
    }

    /**
     * Get additional seats form ID.
     *
     * @return int The form ID.
     */
    public function getAdditionalSeatsFormId(): int
    {
        return (int) apply_filters('wicket/org-roster/additional_seats_form_id', 0);
    }

    /**
     * Get additional seats minimum quantity.
     *
     * @return int The minimum quantity.
     */
    public function getAdditionalSeatsMinQuantity(): int
    {
        return (int) apply_filters('wicket/org-roster/additional_seats_min_quantity', 1);
    }

    /**
     * Get additional seats maximum quantity.
     *
     * @return int The maximum quantity.
     */
    public function getAdditionalSeatsMaxQuantity(): int
    {
        return (int) apply_filters('wicket/org-roster/additional_seats_max_quantity', 100);
    }
}
