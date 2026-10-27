<?php

declare(strict_types=1);

namespace WicketAcc\Blocks;

use HyperBlocks\Block\Block;
use HyperBlocks\Config as HyperBlocksConfig;
use HyperBlocks\Registry as HyperBlocksRegistry;
use WicketAcc\ManageEmployer\RestController;
use WicketAcc\ManageEmployer\Settings;

// No direct access
defined('ABSPATH') || exit;

/**
 * HyperBlocks Block: ACC Manage Employer.
 *
 * Member-facing employer list: cards, add/end connections, org create, primary
 * organization. Field controls come from the canonical schema in
 * WicketAcc\ManageEmployer\Settings::schema(); tabs and sections drive the
 * schema-delivered editor sidebar (HyperBlocks 1.8+ entry.editorSidebar).
 *
 * Renders through templates-wicket/blocks/manage-employer.hb.php. The hb_sample
 * branch in that template returns before any MDP call so the editor preview can
 * never leak real employers.
 *
 * @see atlas://plans/feature-acc-manage-employer-block.md
 */
class ManageEmployer
{
    /**
     * Transient lifetime for the MDP-backed option lists.
     */
    private const OPTIONS_TTL = 12 * HOUR_IN_SECONDS;

    public function __construct()
    {
        add_action('init', [$this, 'registerBlock'], 9);
        add_action('rest_api_init', [new RestController(), 'registerRoutes']);
    }

    /**
     * Register the block with HyperBlocks.
     *
     * Priority 9 keeps registration ahead of core block parsing. The template
     * path allowlist is already registered by ChangePassword; registering the
     * same path twice is idempotent in HyperBlocks, but the guard keeps the
     * blocks independent of each other's load order.
     */
    public function registerBlock(): void
    {
        HyperBlocksConfig::registerTemplatePath(WICKET_ACC_PATH . 'templates-wicket/blocks');

        $fields = [];

        foreach (Settings::schema() as $name => $meta) {
            $field = \HyperBlocks\Block\Field::make($meta['type'], $name, $meta['label'])
                ->setTab($meta['tab'])
                ->setSection($meta['section'])
                ->setDefault($meta['default']);

            if (! empty($meta['dynamic_options'])) {
                // Options only matter in the editor sidebar. Fetching them on
                // every front-end init would put an MDP call on the hot path
                // whenever the cache is cold.
                $field->setOptions(is_admin()
                    ? ($name === 'org_types' || $name === 'create_org_types'
                        ? $this->orgTypeOptions()
                        : $this->relationshipTypeOptions())
                    : []);
            } elseif (! empty($meta['options'])) {
                $field->setOptions($meta['options']);
            }

            if (array_key_exists('conditional_logic', $meta)) {
                $field->setConditionalLogic($meta['conditional_logic']);
            }

            $fields[] = $field;
        }

        $block = Block::make(__('ACC Manage Employer', 'wicket-acc'))
            ->setName('wicket-acc/manage-employer')
            ->setIcon('building')
            ->addFields($fields)
            ->setDescription(__('Lets members view, add, and end their employer relationships.', 'wicket-acc'))
            ->setCategory('wicket-account-center')
            ->setKeywords([__('account-centre', 'wicket-acc'), __('employer', 'wicket-acc'), __('organization', 'wicket-acc')])
            ->setRenderTemplateFile('manage-employer.hb.php');

        HyperBlocksRegistry::getInstance()->registerFluentBlock($block);
    }

    /**
     * Organization type slugs for the editor dropdowns.
     *
     * Field::setOptions is evaluated once at init, so this must never be a
     * per-request MDP call: a transient serves the list for 12 hours and one
     * in-flight lock absorbs concurrent primes. Empty list means the dropdowns
     * render nothing and blank option semantics (all types) keep working.
     *
     * @return array<string, string> slug => label
     */
    public function orgTypeOptions(): array
    {
        return $this->cachedOptions('org_types', function (): array {
            if (! function_exists('WACC')) {
                return [];
            }

            $client = \WACC()->Mdp()->initClient();

            if (! $client) {
                return [];
            }

            try {
                $response = $client->get('organization_types', ['query' => ['per_page' => 200]]);
            } catch (\Throwable $e) {
                return [];
            }

            return $this->extractSlugLabels($response ?? []);
        });
    }

    /**
     * Relationship type slugs (person_to_organizations) for the create flow.
     *
     * @return array<string, string> slug => label
     */
    public function relationshipTypeOptions(): array
    {
        return $this->cachedOptions('relationship_types', function (): array {
            if (! function_exists('get_person_to_organizations_connection_types_list')) {
                return [];
            }

            $types = get_person_to_organizations_connection_types_list();

            if (! is_iterable($types)) {
                return [];
            }

            $options = [];

            foreach ($types as $type) {
                $slug = $this->typeSlug($type);
                $label = $this->typeLabel($type);

                if ($slug !== '') {
                    $options[$slug] = $label !== '' ? $label : $slug;
                }
            }

            return $options;
        });
    }

    /**
     * Transient wrapper shared by both providers. Empty results earn a short
     * negative cache so an MDP outage cannot turn every admin request into a
     * fresh blocking fetch attempt.
     *
     * @param callable():array<string,string> $fetch
     * @return array<string, string>
     */
    private function cachedOptions(string $key, callable $fetch): array
    {
        $transient = 'wicket_acc_me_options_' . $key;
        $cached = get_transient($transient);

        if (is_array($cached)) {
            return $cached;
        }

        if (get_transient($transient . '_miss') || get_transient($transient . '_lock')) {
            return [];
        }

        set_transient($transient . '_lock', 1, MINUTE_IN_SECONDS);

        $options = $fetch();

        if ($options !== []) {
            set_transient($transient, $options, self::OPTIONS_TTL);
        } else {
            set_transient($transient . '_miss', 1, 5 * MINUTE_IN_SECONDS);
        }

        delete_transient($transient . '_lock');

        return $options;
    }

    /**
     * Normalize an MDP organization_types index response to slug => label.
     *
     * @return array<string, string>
     */
    private function extractSlugLabels(array $response): array
    {
        $rows = $response['data'] ?? $response;

        if (! is_array($rows)) {
            return [];
        }

        $options = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $attributes = is_array($row['attributes'] ?? null) ? $row['attributes'] : [];

            // The id can be a uuid on some tenants; the slug attribute is the
            // stable key the type filters match on.
            $slug = (string) ($attributes['slug'] ?? $row['id'] ?? '');
            $label = (string) ($attributes['name'] ?? $attributes['title'] ?? '');

            if ($slug !== '') {
                $options[$slug] = $label !== '' ? $label : $slug;
            }
        }

        return $options;
    }

    /**
     * Pull the slug from a resource-type entity in either object or array shape.
     */
    private function typeSlug(mixed $type): string
    {
        if (is_object($type)) {
            $attributes = $type->attributes ?? null;

            return (string) ($attributes->slug ?? $type->id ?? '');
        }

        if (is_array($type)) {
            return (string) ($type['attributes']['slug'] ?? $type['id'] ?? '');
        }

        return '';
    }

    /**
     * Pull the display label from a resource-type entity in either shape.
     */
    private function typeLabel(mixed $type): string
    {
        if (is_object($type)) {
            $attributes = $type->attributes ?? null;

            return (string) ($attributes->name ?? $type->name ?? '');
        }

        if (is_array($type)) {
            return (string) ($type['attributes']['name'] ?? $type['name'] ?? '');
        }

        return '';
    }
}
