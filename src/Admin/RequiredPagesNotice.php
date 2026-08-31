<?php

declare(strict_types=1);

namespace WicketAcc\Admin;

// No direct access
defined('ABSPATH') || exit;

/**
 * Surfaces missing my-account pages that the org-roster UI links to.
 *
 * Helper::getMyAccountPageUrl() falls back to a home_url() path when a slug
 * has no post, and that fallback 404s (WWID-1919: ESCRS "Bulk Upload" button).
 * docs/ORM/product/SETUP.md "Required WordPress Pages" documents the slugs,
 * but nothing checked them, so the break was silent until a user clicked.
 *
 * The notice lists the missing slugs and offers an explicit button to create
 * them. Nothing is created automatically: a site may deliberately rename or
 * omit a slug (WPML, custom flows), so creation stays in admin hands.
 */
class RequiredPagesNotice extends \WicketAcc\WicketAcc
{
    /**
     * Slug => default page title. Mirrors docs/ORM/product/SETUP.md
     * "Required WordPress Pages". Conditional slugs are removed per config in
     * requiredPages().
     *
     * @var array<string, string>
     */
    private const PAGE_TITLES = [
        'organization-management' => 'Organization Management',
        'organization-profile' => 'Organization Profile',
        'organization-members' => 'Organization Members',
        'organization-members-bulk' => 'Organization Members Bulk Upload',
        'supplemental-members' => 'Purchase Additional Seats',
        'organization-contacts' => 'Organization Contacts',
    ];

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('admin_notices', [$this, 'renderNotice']);
        add_action('admin_post_wicket_acc_create_required_pages', [$this, 'handleCreate']);
    }

    /**
     * Slugs that apply to this site right now, slug => page title.
     *
     * A page only counts as required when the feature linking to it is on:
     * the bulk-upload button renders only with show_bulk_upload, and the
     * contacts page only with contacts.enabled.
     *
     * @return array<string, string>
     */
    public function requiredPages(): array
    {
        $config = \WicketORM\Services\ConfigService::getConfig();

        $required = self::PAGE_TITLES;

        if (empty($config['presentation']['member_list']['show_bulk_upload'])) {
            unset($required['organization-members-bulk']);
        }

        if (empty($config['contacts']['enabled'])) {
            unset($required['organization-contacts']);
        }

        // Tenants that deliberately renamed or dropped a slug can filter it
        // out here instead of living with a permanent notice.
        return apply_filters('wicket_acc/required-pages/slugs', $required);
    }

    /**
     * Required slugs with no published my-account page.
     *
     * Mirrors Helper::getMyAccountPageUrl(): it looks up published posts
     * (get_posts default status), so a draft or trash page still counts as
     * missing here.
     *
     * @return array<string, string> slug => title
     */
    public function missingPages(): array
    {
        $missing = [];

        foreach ($this->requiredPages() as $slug => $title) {
            $existing = get_posts([
                'post_type' => 'my-account',
                'name' => $slug,
                'numberposts' => 1,
                'fields' => 'ids',
            ]);

            if (empty($existing)) {
                $missing[$slug] = $title;
            }
        }

        return $missing;
    }

    /**
     * Create a published my-account page per missing slug.
     *
     * @return array<string, int|\WP_Error> slug => new post ID or error
     */
    public function createMissingPages(): array
    {
        $created = [];

        foreach ($this->missingPages() as $slug => $title) {
            $created[$slug] = wp_insert_post([
                'post_type' => 'my-account',
                'post_status' => 'publish',
                'post_title' => $title,
                'post_name' => $slug,
            ], true);
        }

        return $created;
    }

    /**
     * Admin notice listing missing pages with a create button.
     *
     * @return void
     */
    public function renderNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $created = isset($_GET['wicket_acc_pages_created']) ? absint($_GET['wicket_acc_pages_created']) : null;
        $failed = isset($_GET['wicket_acc_pages_failed']) ? absint($_GET['wicket_acc_pages_failed']) : 0;
        if ($created !== null) {
            if ($failed > 0) {
                printf(
                    '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                    esc_html(sprintf(
                        /* translators: 1: number created, 2: number failed. */
                        __('Account Centre: created %1$d page(s), %2$d could not be created. Check the Account Centre page list for details.', 'wicket-acc'),
                        $created,
                        $failed
                    ))
                );
            } else {
                printf(
                    '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                    esc_html(sprintf(
                        /* translators: %d: number of pages created. */
                        __('Account Centre: created %d missing page(s).', 'wicket-acc'),
                        $created
                    ))
                );
            }
        }

        $missing = $this->missingPages();
        if (empty($missing)) {
            return;
        }

        $slug_list = implode(
            ', ',
            array_map(static fn (string $slug): string => '<code>' . esc_html($slug) . '</code>', array_keys($missing))
        );
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('Account Centre: missing account pages.', 'wicket-acc'); ?></strong>
                <?php
                printf(
                    /* translators: %s: comma-separated list of page slugs. */
                    esc_html__('The Account Centre links to account pages that do not exist on this site, so those links lead to a 404. Missing: %s', 'wicket-acc'),
                    wp_kses_post($slug_list)
                );
        ?>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="padding-bottom: 8px;">
                <input type="hidden" name="action" value="wicket_acc_create_required_pages">
                <?php wp_nonce_field('wicket_acc_create_required_pages'); ?>
                <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url($_SERVER['REQUEST_URI'] ?? ''); ?>">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Create missing pages', 'wicket-acc'); ?>
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * admin_post handler for the create button.
     *
     * @return void
     */
    public function handleCreate(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to create Account Centre pages.', 'wicket-acc'));
        }

        check_admin_referer('wicket_acc_create_required_pages');

        $results = $this->createMissingPages();

        $created = 0;
        $failed = 0;
        foreach ($results as $result) {
            if (is_wp_error($result)) {
                $failed++;
                continue;
            }

            $created++;
        }

        $redirect = wp_get_referer() ?: admin_url();
        wp_safe_redirect(add_query_arg([
            'wicket_acc_pages_created' => $created,
            'wicket_acc_pages_failed' => $failed,
        ], $redirect));
        exit;
    }
}
