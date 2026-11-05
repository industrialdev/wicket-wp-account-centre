<?php

/**
 * HyperBlocks template: ACC Manage Employer (wicket-acc/manage-employer).
 *
 * Server-rendered shell for the manage-employer block. The list UI, cards, and
 * modals land with M4; this skeleton exists so the block renders safely from
 * the moment it is registered.
 *
 * The hb_sample branch is the editor's sample-data preview. It MUST return
 * before any MDP access: the editor renders as the author's own person, and
 * the sample toggle exists to show fake data instead of real employers.
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

// M3 spike: front-end runtime comparison (vanilla vs Datastar). Instance id,
// asset boot, and fragment URLs are computed before the markup so the root
// carries the runtime attrs. Throwaway; the M4 list UI replaces it.
$me_sfx = empty($hb_sample) ? (string) wp_unique_id() : '';
$me_id = 'me-' . $me_sfx;
$me_runtime = empty($hb_sample) ? WicketAcc\ManageEmployer\Frontend::runtime() : '';
$me_nonce = empty($hb_sample) ? (string) wp_create_nonce('wp_rest') : '';

if (empty($hb_sample)) {
    (new WicketAcc\ManageEmployer\Frontend())->boot($me_id);

    $me_fragment_args = [
        'fid' => $me_sfx,
        'settings' => rawurlencode((string) wp_json_encode($me_settings)),
        'settings_token' => rawurlencode($me_token),
    ];
    $me_state_url = add_query_arg(array_merge(['wicket_me_fragment' => 'state'], $me_fragment_args), home_url('/'));
    $me_search_url = add_query_arg(array_merge(['wicket_me_fragment' => 'search'], $me_fragment_args), home_url('/'));
    $me_add_url = add_query_arg(array_merge(['wicket_me_fragment' => 'add'], $me_fragment_args), home_url('/'));
}

$me_is_datastar = $me_runtime === 'datastar';
?>

<div
	class="wicket-acc-manage-employer"
	data-wicket-acc-manage-employer
	<?php if (empty($hb_sample)) : ?>
		data-wicket-me-settings="<?php echo esc_attr((string) wp_json_encode($me_settings)); ?>"
		data-wicket-me-token="<?php echo esc_attr($me_token); ?>"
		data-wicket-me-id="<?php echo esc_attr($me_id); ?>"
		data-wicket-me-rest="<?php echo esc_url_raw(rest_url('wicket-acc/v1/manage-employer')); ?>"
		data-wicket-me-nonce="<?php echo esc_attr($me_nonce); ?>"
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
		<?php if ($me_is_datastar) : ?>
		<?php
	    // Datastar (observed on the vendored v1.0.1) wired data-on handlers only on elements visible at
	    // walk time, so everything inside the closed <dialog> must stay inert.
	    // All interactivity delegates from this visible wrapper: input events
	    // update the query signal, clicks on option rows select, clicks on the
	    // add button post the signals. The signal suffix keeps names per
	    // instance (signals are page-global).
	    $me_click_expr = sprintf(
	        "evt.target.classList.contains('me-spike__add') && @post('%1\$s', {openWhenHidden:true, headers:{'X-WP-Nonce':\$_meNonce_%2\$s}}) || evt.target.closest('[data-uuid]') && (\$meSelected_%2\$s = evt.target.closest('[data-uuid]').dataset.uuid) && (\$meQuery_%2\$s = evt.target.closest('[data-uuid]').dataset.name)",
	        esc_js($me_add_url),
	        esc_html($me_sfx)
	    );
		    $me_input_expr = sprintf(
		        "evt.target.hasAttribute('data-me-search') && (\$meQuery_%1\$s = evt.target.value) && @get('%2\$s')",
		        esc_html($me_sfx),
		        esc_js($me_search_url)
		    );
		    ?>
		<div
			data-signals="<?php echo esc_attr((string) wp_json_encode([
			    'meQuery_' . $me_sfx => '',
			    'meSelected_' . $me_sfx => '',
			    'meAddOpen_' . $me_sfx => false,
			    // Underscore prefix: private signal, never shipped to the backend.
			    '_meNonce_' . $me_sfx => $me_nonce,
			])); ?>"
			data-on:input__debounce.300ms="<?php echo esc_attr($me_input_expr); ?>"
			data-on:click="<?php echo esc_attr($me_click_expr); ?>"
		>
		<?php endif; ?>

		<p class="me-spike__feedback" id="<?php echo esc_attr($me_id); ?>-feedback" role="status" aria-live="polite" hidden></p>

		<ul
			id="<?php echo esc_attr($me_id); ?>-list"
			class="me-spike__cards"
			<?php if ($me_is_datastar) : ?>
				data-init="@get('<?php echo esc_attr($me_state_url); ?>')"
			<?php endif; ?>
		>
			<li class="me-spike__empty"><?php echo esc_html__('Loading…', 'wicket-acc'); ?></li>
		</ul>

		<?php
		    if ($me_is_datastar) {
		        get_component('modal-trigger', [
		            'modal_id' => $me_id . '-dialog',
		            'mode' => 'datastar',
		            'open_signal' => 'meAddOpen_' . $me_sfx,
		            'label' => __('Add employer', 'wicket-acc'),
		            'variant' => 'primary',
		        ]);
		    } else {
		        get_component('modal-trigger', [
		            'modal_id' => $me_id . '-dialog',
		            'label' => __('Add employer', 'wicket-acc'),
		            'variant' => 'primary',
		        ]);
		    }

ob_start();
?>
		<div class="me-spike__combobox">
			<label for="<?php echo esc_attr($me_id); ?>-search"><?php esc_html_e('Find an organization', 'wicket-acc'); ?></label>
			<input
				type="text"
				id="<?php echo esc_attr($me_id); ?>-search"
				class="me-spike__search"
				role="combobox"
				aria-expanded="false"
				aria-controls="<?php echo esc_attr($me_id); ?>-results"
				aria-autocomplete="list"
				autocomplete="off"
				placeholder="<?php esc_attr_e('Type at least 3 characters', 'wicket-acc'); ?>"
				<?php if ($me_is_datastar) : ?>
					data-me-search
				<?php endif; ?>
			>
			<ul
				id="<?php echo esc_attr($me_id); ?>-results"
				class="me-spike__options"
				role="listbox"
				aria-label="<?php esc_attr_e('Search results', 'wicket-acc'); ?>"
			></ul>
			<input
				type="hidden"
				name="org_uuid"
				id="<?php echo esc_attr($me_id); ?>-selected"
				value=""
				<?php if ($me_is_datastar) : ?>
					hidden
				<?php endif; ?>
			>
			<button
				type="button"
				class="button me-spike__add"
				id="<?php echo esc_attr($me_id); ?>-add"
				<?php if (!$me_is_datastar) : ?>
					disabled
				<?php endif; ?>
			>
				<?php esc_html_e('Add employer', 'wicket-acc'); ?>
			</button>
		</div>
		<?php
$body = ob_get_clean();

$modal_args = [
    'id' => $me_id . '-dialog',
    'title' => __('Add an employer', 'wicket-acc'),
    'body' => $body,
    'width' => 'md',
    'close_label' => __('Close', 'wicket-acc'),
    'classes' => ['me-spike-dialog'],
];

if ($me_is_datastar) {
    $modal_args['mode'] = 'datastar';
    $modal_args['open_signal'] = 'meAddOpen_' . $me_sfx;
    $modal_args['reset_actions'] = sprintf(
        '$%1$s = \'\'; $%2$s = \'\'',
        'meQuery_' . $me_sfx,
        'meSelected_' . $me_sfx
    );
}

get_component('modal', $modal_args);
?>

		<?php if ($me_is_datastar) : ?>
		</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
