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
    ? \WicketAcc\ManageEmployer\Settings::resolve(
        \WicketAcc\ManageEmployer\Settings::pick(get_defined_vars())
    )
    : [];
$me_token = empty($hb_sample) ? \WicketAcc\ManageEmployer\Settings::sign($me_settings) : '';
?>

<div
	class="wicket-acc-manage-employer"
	data-wicket-acc-manage-employer
	<?php if (empty($hb_sample)) : ?>
		data-wicket-me-settings="<?php echo esc_attr((string) wp_json_encode($me_settings)); ?>"
		data-wicket-me-token="<?php echo esc_attr($me_token); ?>"
	<?php endif; ?>
>
	<?php if (! empty($hb_sample)) : ?>
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
		<div class="wicket-acc-manage-employer__empty">
			<p><?php printf(esc_html__('No %s found.', 'wicket-acc'), esc_html($plural)); ?></p>
		</div>
	<?php endif; ?>
</div>
