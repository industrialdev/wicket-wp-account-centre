<?php

/**
 * HyperBlocks template: ACC Manage Employer (wicket-acc/manage-employer).
 *
 * Server-rendered chrome (title, tabs, pagination, feedback, empty states)
 * plus the vanilla-JS runtime that fills cards over REST. The hb_sample
 * branch is the editor's sample-data preview. It MUST return before any
 * MDP access: the editor renders as the author's own person, and the
 * sample toggle exists to show fake data instead of real employers.
 * Renderer context provides $hb_sample; it is never a block attribute.
 *
 * Variables extracted from block attributes (see ManageEmployer\Settings::schema()):
 *
 * @var string $org_name_singular
 * @var string $org_name_plural
 * @var bool   $hb_sample
 */
$singular = $org_name_singular ?? 'Employer';
$plural = $org_name_plural ?? 'Employers';

// The REST routes refuse payloads without a verified token, so this embed is
// what makes the block runtime usable at all. resolve() applies defaults for
// attribute keys Gutenberg never serialized. Sample previews get neither the
// settings nor a token: they never talk to the API.
$me_settings = empty($hb_sample)
    ? WicketAcc\ManageEmployer\Settings::resolve(
        WicketAcc\ManageEmployer\Settings::pick(get_defined_vars())
    )
    : [];
$me_token = empty($hb_sample) ? WicketAcc\ManageEmployer\Settings::sign($me_settings) : '';

// M4 list UI. The instance id and per-render REST nonce drive the vanilla JS
// runtime; settings and token ride every REST call as signed query params.
$me_sfx = (string) wp_unique_id();
$me_id = 'me-' . $me_sfx;
$me_nonce = empty($hb_sample) ? (string) wp_create_nonce('wp_rest') : '';
$me_mode = (string) ($me_settings['relationship_status_filter'] ?? 'all');
WicketAcc\ManageEmployer\Frontend::boot();

// Titles per story 2.1: My {Singular}(s) / My Current {Singular}(s) /
// My Past {Singular}(s).
$me_title = match ($me_mode) {
    'active' => sprintf(__('My Current %s(s)', 'wicket-acc'), $singular),
    'inactive' => sprintf(__('My Past %s(s)', 'wicket-acc'), $singular),
    default => sprintf(__('My %s(s)', 'wicket-acc'), $singular),
};

$me_add_label = __('Add employer', 'wicket-acc');

// Labels live in one config attribute: PHP does the translation, the script
// only paints. The plugin has no JS translation pipeline.
$me_config = [
    'id' => $me_id,
    'singular' => $singular,
    'plural' => $plural,
    'mode' => $me_mode,
    'display_org_type' => !empty($me_settings['display_org_type']),
    'display_org_address' => !empty($me_settings['display_org_address']),
    'org_address_format' => (string) ($me_settings['org_address_format'] ?? 'full'),
    'display_membership_status' => !empty($me_settings['display_membership_status']),
    'display_relationship_dates' => !empty($me_settings['display_relationship_dates']),
    'labels' => [
        'prev' => __('Previous', 'wicket-acc'),
        'next' => __('Next', 'wicket-acc'),
        'page_of' => __('Page %1$s of %2$s', 'wicket-acc'),
        'primary' => __('Primary', 'wicket-acc'),
        'present' => __('Present', 'wicket-acc'),
        'dismiss' => __('Dismiss', 'wicket-acc'),
        'add' => $me_add_label,
        'adding' => __('Adding…', 'wicket-acc'),
        'added' => __('Employer added.', 'wicket-acc'),
        'already' => __('Already connected.', 'wicket-acc'),
        'added_with_errors' => __('Added with errors: %s', 'wicket-acc'),
        'no_results' => __('No organizations found.', 'wicket-acc'),
        'search_failed' => __('Search failed.', 'wicket-acc'),
        'load_failed' => __('Your employers could not be loaded.', 'wicket-acc'),
        'session_expired' => __('Session expired. Reload the page.', 'wicket-acc'),
        'request_failed' => __('Request failed.', 'wicket-acc'),
        'unknown_employer' => __('Unknown employer', 'wicket-acc'),
        'empty_current' => sprintf(
            /* translators: 1: plural organization label, 2: add button label. */
            __('You have no current %1$s. Use the "%2$s" button above to search for your organization, or create a new record if it doesn\'t exist yet.', 'wicket-acc'),
            $plural,
            $me_add_label
        ),
        'empty_past' => sprintf(
            /* translators: %s: plural organization label. */
            __('You have no past %s on record.', 'wicket-acc'),
            $plural
        ),
    ],
];

?>

<div
	class="wicket-acc-manage-employer"
	data-wicket-acc-manage-employer
	<?php if (empty($hb_sample)) : ?>
		data-wicket-me-id="<?php echo esc_attr($me_id); ?>"
		data-wicket-me-config="<?php echo esc_attr((string) wp_json_encode($me_config)); ?>"
		data-wicket-me-rest="<?php echo esc_url_raw(rest_url('wicket-acc/v1/manage-employer')); ?>"
		data-wicket-me-nonce="<?php echo esc_attr($me_nonce); ?>"
		data-wicket-me-settings="<?php echo esc_attr((string) wp_json_encode($me_settings)); ?>"
		data-wicket-me-token="<?php echo esc_attr($me_token); ?>"
	<?php endif; ?>
>

	<?php if (!empty($hb_sample)) : ?>
		<div class="wicket-acc-manage-employer__sample-note">
			<?php
            printf(
                /* translators: %s: plural organization label. */
                esc_html__('Sample preview — no real %s are shown here.', 'wicket-acc'),
                esc_html($plural)
            );
	    ?>
		</div>
		<div class="wicket-acc-manage-employer__empty">
			<p><?php echo esc_html__('No employers found.', 'wicket-acc'); ?></p>
		</div>
	<?php else : ?>

		<h2 class="wicket-acc-me__title"><?php echo esc_html($me_title); ?></h2>

		<div class="wicket-acc-me__feedback" data-me-feedback role="status" aria-live="polite" hidden>
			<span class="wicket-acc-me__feedback-text"></span>
			<button type="button" class="wicket-acc-me__feedback-dismiss" aria-label="<?php esc_attr_e('Dismiss', 'wicket-acc'); ?>">&times;</button>
		</div>

		<?php if ($me_mode === 'all') : ?>
		<div class="wicket-acc-me__tabs">
			<button type="button" class="wicket-acc-me__tab is-active" data-me-tab="current" aria-pressed="true">
				<?php printf(esc_html__('Current %s', 'wicket-acc'), esc_html($plural)); ?>
				<span class="wicket-acc-me__tab-count" data-me-count="current"></span>
			</button>
			<button type="button" class="wicket-acc-me__tab" data-me-tab="past" aria-pressed="false">
				<?php printf(esc_html__('Past %s', 'wicket-acc'), esc_html($plural)); ?>
				<span class="wicket-acc-me__tab-count" data-me-count="past"></span>
			</button>
		</div>
		<?php endif; ?>

		<ul id="<?php echo esc_attr($me_id); ?>-list" class="wicket-acc-me__cards" aria-busy="true">
			<?php for ($me_sk = 0; $me_sk < 3; $me_sk++) : ?>
			<li class="wicket-acc-me__sk" aria-hidden="true"><span class="wicket-acc-me__sk-line"></span><span class="wicket-acc-me__sk-line wicket-acc-me__sk-line--short"></span></li>
			<?php endfor; ?>
		</ul>

		<nav class="wicket-acc-me__pagination" data-me-pagination hidden>
			<button type="button" class="wicket-acc-me__page-btn" data-me-page-prev><?php esc_html_e('Previous', 'wicket-acc'); ?></button>
			<p class="wicket-acc-me__page-status" data-me-page-status aria-live="polite"></p>
			<button type="button" class="wicket-acc-me__page-btn" data-me-page-next><?php esc_html_e('Next', 'wicket-acc'); ?></button>
		</nav>

		<?php if ($me_mode !== 'inactive') : ?>
		<?php get_component('modal-trigger', [
		    'modal_id' => $me_id . '-dialog',
		    'label' => $me_add_label,
		    'variant' => 'primary',
		]); ?>

		<?php ob_start(); ?>
		<div class="wicket-acc-me__combobox">
			<label for="<?php echo esc_attr($me_id); ?>-search"><?php esc_html_e('Find an organization', 'wicket-acc'); ?></label>
			<input
				type="text"
				id="<?php echo esc_attr($me_id); ?>-search"
				class="wicket-acc-me__search"
				role="combobox"
				aria-expanded="false"
				aria-controls="<?php echo esc_attr($me_id); ?>-results"
				aria-autocomplete="list"
				autocomplete="off"
				placeholder="<?php esc_attr_e('Type at least 3 characters', 'wicket-acc'); ?>"
			>
			<ul
				id="<?php echo esc_attr($me_id); ?>-results"
				class="wicket-acc-me__options"
				role="listbox"
				aria-label="<?php esc_attr_e('Search results', 'wicket-acc'); ?>"
			></ul>
			<input
				type="hidden"
				name="org_uuid"
				id="<?php echo esc_attr($me_id); ?>-selected"
				value=""
			>
			<button
				type="button"
				class="button wicket-acc-me__add"
				id="<?php echo esc_attr($me_id); ?>-add"
				disabled
			>
				<?php echo esc_html($me_add_label); ?>
			</button>
		</div>
		<?php
	    $me_modal_body = ob_get_clean();

		    get_component('modal', [
		        'id' => $me_id . '-dialog',
		        'title' => __('Add an employer', 'wicket-acc'),
		        'body' => $me_modal_body,
		        'width' => 'md',
		        'close_label' => __('Close', 'wicket-acc'),
		        'classes' => ['wicket-acc-me-dialog'],
		    ]);
		    ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
