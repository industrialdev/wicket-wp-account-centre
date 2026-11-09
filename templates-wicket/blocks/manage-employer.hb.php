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
$me_end_label = __('End', 'wicket-acc');
$me_create_heading = sprintf(
    /* translators: %s: singular organization label. */
    __("Can't find your %s?", 'wicket-acc'),
    $singular
);

// Create-org type options: the dedicated create list wins, else the org-type
// allow-list (same fallback the REST layer enforces). One option hides the
// dropdown per story 2.5; zero renders an empty dropdown the required check
// catches.
$me_create_types = !empty($me_settings['create_org_types'])
    ? $me_settings['create_org_types']
    : $me_settings['org_types'];

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
    'hide_end_button' => !empty($me_settings['hide_end_button']),
    'enable_primary' => !empty($me_settings['enable_primary']),
    'hide_primary_tag' => !empty($me_settings['hide_primary_tag']),
    'hide_set_primary_button' => !empty($me_settings['hide_set_primary_button']),
    'removal_alert_enabled' => !empty($me_settings['removal_alert_enabled']),
    'disable_create' => !empty($me_settings['disable_create']),
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
        'end' => $me_end_label,
        'ending' => __('Ending…', 'wicket-acc'),
        'ended' => __('%1$s has been removed from your current employers.', 'wicket-acc'),
        'confirm_cancel' => __('Cancel', 'wicket-acc'),
        'end_date_label' => __('End date', 'wicket-acc'),
        'set_primary' => __('Set as Primary', 'wicket-acc'),
        'setting_primary' => __('Updating…', 'wicket-acc'),
        'primary_set' => __('%1$s is now your primary employer.', 'wicket-acc'),
        'primary_set_note' => __('This updates your primary organization in MDP.', 'wicket-acc'),
        'rejoin' => __('Rejoin', 'wicket-acc'),
        'rejoining' => __('Rejoining…', 'wicket-acc'),
        'rejoined' => __('%1$s was added back to your current employers.', 'wicket-acc'),
        'create_heading' => $me_create_heading,
        'create_name_label' => __('Organization name', 'wicket-acc'),
        'create_type_label' => __('Organization type', 'wicket-acc'),
        'create_submit' => __('Create', 'wicket-acc'),
        'creating' => __('Creating…', 'wicket-acc'),
        'created' => __('%1$s has been created and added to your current employers.', 'wicket-acc'),
        'duplicate_org' => __('An organization named %1$s already exists, so you were connected to that one.', 'wicket-acc'),
        'name_and_type_required' => __('Please enter both an organization name and type.', 'wicket-acc'),
        'stale_update' => __('Saved. The list below is still updating.', 'wicket-acc'),
        'no_results' => __('No organizations found.', 'wicket-acc'),
        'results_found' => __('%1$s results found.', 'wicket-acc'),
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
				aria-haspopup="listbox"
				aria-controls="<?php echo esc_attr($me_id); ?>-results"
				aria-describedby="<?php echo esc_attr($me_id); ?>-add-error"
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
			<p class="wicket-acc-me__results-status" data-me-results-status role="status" aria-live="polite"></p>
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
		<p class="wicket-acc-me__create-error" data-me-add-error id="<?php echo esc_attr($me_id); ?>-add-error" hidden></p>
		<?php if (empty($me_settings['disable_create'])) : ?>
		<div class="wicket-acc-me__create" data-me-create>
			<p class="wicket-acc-me__create-heading"><?php echo esc_html($me_create_heading); ?></p>
			<label for="<?php echo esc_attr($me_id); ?>-create-name"><?php esc_html_e('Organization name', 'wicket-acc'); ?></label>
			<input
				type="text"
				id="<?php echo esc_attr($me_id); ?>-create-name"
				class="wicket-acc-me__create-name"
				aria-describedby="<?php echo esc_attr($me_id); ?>-create-error"
				autocomplete="off"
				maxlength="200"
			>
			<?php if (count($me_create_types) !== 1) : ?>
			<label for="<?php echo esc_attr($me_id); ?>-create-type"><?php esc_html_e('Organization type', 'wicket-acc'); ?></label>
			<select id="<?php echo esc_attr($me_id); ?>-create-type" class="wicket-acc-me__create-type" aria-describedby="<?php echo esc_attr($me_id); ?>-create-error">
				<option value=""><?php esc_html_e('Select a type', 'wicket-acc'); ?></option>
				<?php foreach ($me_create_types as $me_create_type) : ?>
				<option value="<?php echo esc_attr((string) $me_create_type); ?>"><?php echo esc_html(ucwords(str_replace(['_', '-'], ' ', (string) $me_create_type))); ?></option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>
			<p class="wicket-acc-me__create-error" data-me-create-error id="<?php echo esc_attr($me_id); ?>-create-error" hidden></p>
			<button type="button" class="button wicket-acc-me__create-submit" id="<?php echo esc_attr($me_id); ?>-create-submit">
				<?php esc_html_e('Create', 'wicket-acc'); ?>
			</button>
		</div>
		<?php endif; ?>
		<?php
	    $me_modal_body = ob_get_clean();

		    get_component('modal', [
		        'id' => $me_id . '-dialog',
		        'title' => __('Add an employer', 'wicket-acc'),
		        'body' => $me_modal_body,
		        'width' => 'md',
		        'close_label' => __('Close', 'wicket-acc'),
		        'classes' => ['wicket-acc-me-dialog'],
		        'atts' => ['aria-modal' => 'true'],
		    ]);

		    // End confirmation (story 3.3): JS names the organization at open time
		    // and the body carries the end-date field. Rendered only when the removal
		    // alert is on and the End button can exist; alert off means End applies
		    // immediately.
		    if (empty($me_settings['hide_end_button']) && !empty($me_settings['removal_alert_enabled'])) :
		        ob_start(); ?>
		<div class="wicket-acc-me__confirm-body">
			<p><?php echo esc_html((string) $me_settings['removal_alert_description']); ?></p>
			<label for="<?php echo esc_attr($me_id); ?>-end-date"><?php esc_html_e('End date', 'wicket-acc'); ?></label>
			<input
				type="date"
				id="<?php echo esc_attr($me_id); ?>-end-date"
				class="wicket-acc-me__end-date"
			>
		</div>
		<?php
		        $me_confirm_body = ob_get_clean();

		        ob_start(); ?>
		<div class="wicket-acc-me__confirm-footer">
			<button type="button" class="button" id="<?php echo esc_attr($me_id); ?>-confirm-cancel">
				<?php esc_html_e('Cancel', 'wicket-acc'); ?>
			</button>
			<button type="button" class="button wicket-acc-me__confirm-end" id="<?php echo esc_attr($me_id); ?>-confirm-end">
				<?php echo esc_html($me_end_label); ?>
			</button>
		</div>
		<?php
		        $me_confirm_footer = ob_get_clean();

		        get_component('modal', [
		            'id' => $me_id . '-confirm',
		            'title' => (string) $me_settings['removal_alert_title'],
		            'body' => $me_confirm_body,
		            'footer' => $me_confirm_footer,
		            'width' => 'md',
		            'close_label' => __('Close', 'wicket-acc'),
		            'classes' => ['wicket-acc-me-dialog', 'wicket-acc-me-dialog--confirm'],
		            'atts' => ['aria-modal' => 'true'],
		        ]);
		    endif;
		    ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
