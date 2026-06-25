<?php

declare(strict_types=1);

namespace WicketORM\Templates;

// Ensure this file is not accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/*
 * Renders the organization members partial.
 *
 * This template displays the list of organization members with search and filter functionality.
 * It uses Datastar for dynamic updates.
 *
 * @since 1.0.0
 */

if (isset($args['org_uuid'])) {
    $org_uuid = sanitize_text_field((string) $args['org_uuid']);
} elseif (isset($_GET['org_uuid'])) {
    $org_uuid = sanitize_text_field((string) $_GET['org_uuid']);
}
$org_uuid_dom_suffix = sanitize_html_class($org_uuid ?? 'default');
$lang = wicket_get_current_language();

// Fetch organization members if the Wicket function exists.
$membershipService = new \WicketORM\Services\MembershipService();
$configService = new \WicketORM\Services\ConfigService();
$member_service = new \WicketORM\Services\MemberService($configService);
$permissionService = new \WicketORM\Services\PermissionService();

$additional_seats_service = new \WicketORM\Services\AdditionalSeatsService($configService);

// Load org management configuration
$orgman_config = \WicketORM\Services\ConfigService::getConfig();
$requested_membership_uuid = isset($_GET['membership_uuid']) ? sanitize_text_field((string) wp_unslash($_GET['membership_uuid'])) : '';

$membershipUuid = $requested_membership_uuid !== ''
    ? $requested_membership_uuid
    : $membershipService->getMembershipForOrganization($org_uuid);

$presentation_config = is_array($orgman_config['presentation'] ?? null)
    ? $orgman_config['presentation']
    : [];
$member_list_config = is_array($presentation_config['member_list'] ?? null)
    ? $presentation_config['member_list']
    : [];

$membersResult = [
    'members'    => [],
    'pagination' => [
        'currentPage' => 1,
        'totalPages'  => 1,
        'pageSize'    => 10,
        'totalItems'  => 0,
    ],
    'org_uuid'   => $org_uuid,
    'query'      => '',
];

if (!empty($membershipUuid)) {
    try {
        $requested_page_size = (int) ($member_list_config['page_size'] ?? 15);
        $membersResult = $member_service->getMembers(
            $membershipUuid,
            $org_uuid,
            [
                'page' => 1,
                'size' => $requested_page_size,
            ],
            true // Enable lazy loading
        );
    } catch (\Throwable $e) {
        \Wicket()->log()->error('Failed to load members list: ' . $e->getMessage(), [
            'source' => 'wicket-orgman',
            'membership_uuid' => $membershipUuid,
        ]);
    }
}

if (!isset($membersResult['pagination'])) {
    $membersResult['pagination'] = [
        'currentPage' => 1,
        'totalPages'  => 1,
        'pageSize'    => 10,
        'totalItems'  => count($membersResult['members'] ?? []),
    ];
}

$members = $membersResult['members'] ?? [];
$pagination = $membersResult['pagination'];
$query = $membersResult['query'] ?? '';
$totalMemberCount = (int) ($pagination['totalItems'] ?? count($members));
$show_bulk_upload = (bool) ($member_list_config['show_bulk_upload'] ?? false);

$available_roles = $permissionService->getAvailableRoles();
$role_descriptions = $orgman_config['access']['roles']['descriptions'] ?? [];

$containerId = 'members-list-container-' . $org_uuid_dom_suffix;
$membersListEndpoint = \WicketORM\Helpers\template_url() . 'members-list';
$membersListSeparator = str_contains($membersListEndpoint, '?') ? '&' : '?';
$encodedOrgUuid = rawurlencode((string) $org_uuid);
$searchAction = '';
$searchSuccess = '';

if (!empty($membershipUuid)) {
    $membership_query_fragment = '&membership_uuid=' . rawurlencode((string) $membershipUuid);
    $searchAction = "@get('{$membersListEndpoint}{$membersListSeparator}org_uuid={$encodedOrgUuid}{$membership_query_fragment}&page=1&query=' + encodeURIComponent(\$searchQuery))";
    $searchSuccess = '$listLoading = false; ' . wp_sprintf("select('#%s') | set(html)", $containerId);
}

$signals = [
    'searchQuery' => $query,
];
$member_view_config = is_array($presentation_config['member_view'] ?? null)
    ? $presentation_config['member_view']
    : [];
$use_unified_view = (bool) ($member_view_config['use_unified'] ?? false);
if ($use_unified_view) {
    $mode = (string) ($configService->getRosterMode() ?? 'direct');
    $members = $membersResult['members'] ?? [];
    $pagination = $membersResult['pagination'] ?? [];
    $query = $membersResult['query'] ?? '';
    $membership_uuid = $membershipUuid ?? '';
    $members_list_endpoint = $membersListEndpoint;
    $members_list_target = $containerId;
    include __DIR__ . '/members-view-unified.php';

    return;
}
?>
	<div class="members-list wt_relative"
	data-signals:='<?php echo wp_json_encode([
	    'membersLoading' => false,
	    'listLoading' => false,
	    'bulkUploadModalOpen' => false,
	    'bulkUploadSubmitting' => false,
	    'addMemberModalOpen' => false,
	    'addMemberSubmitting' => false,
	    'addMemberSuccess' => false,
	    'addMemberSuccessMessage' => '',
	    'autoCloseCountdown' => 0,
	    'searchQuery' => $query,
	    'searchSubmitted' => false,
	], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>'
	data-on:datastar-fetch="if ((evt.detail.type === 'finished' || evt.detail.type === 'error') && evt.detail.el && evt.detail.el.closest('.members-search, .members-pagination')) { $listLoading = false }">
	<?php /* Seat count moved to members-list.php for dynamic refresh */ ?>
	<div id="org-members-search-form-<?php echo esc_attr($org_uuid); ?>"
		class="members-search wt_flex wt_items-center wt_gap-2 wt_mb-6">
		<div class="members-search__field wt_relative wt_w-full">
			<div class="members-search__icon wt_absolute wt_inset-y-0 wt_left-0 wt_flex wt_items-center wt_pl-3 wt_pointer-events-none">
				<svg class="wt_w-5 wt_h-5 wt_text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"
					xmlns="http://www.w3.org/2000/svg">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
						d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
				</svg>
			</div>
			<?php
$searchSubmitAction = !empty($searchAction) ? '$listLoading = true; $searchSubmitted = true; ' . $searchAction : '';
?>
			<input type="text"
				id="search-input-<?php echo esc_attr($org_uuid); ?>"
				data-bind="searchQuery"
				class="members-search__input wt_border wt_border-color wt_text-content wt_text-sm wt_rounded-md wt_focus_ring-2 wt_focus_ring-bg-interactive wt_focus_border-bg-interactive wt_block wt_w-full wt_pl-10 wt_p-2.5"
				placeholder="<?php esc_attr_e('Start typing to search for members...', 'wicket-acc'); ?>"
				<?php if (!empty($searchSuccess)) : ?>
			data-on:success="<?php echo esc_attr($searchSuccess); ?>"
			data-indicator:members-loading
				<?php endif; ?>
				<?php if (!empty($searchSubmitAction)) : ?>
			data-on:keydown="if (evt.key === 'Enter') { <?php echo esc_attr($searchSubmitAction); ?> }"
			data-on:keydown__prevent-default="evt.key === 'Enter'"
				<?php endif; ?>
			>
		</div>
		<?php
        $searchButtonAction = $searchSubmitAction;
$clearButtonAction = '';
if (!empty($searchAction)) {
    $clearButtonAction = sprintf('(($listLoading = true), ($searchQuery = \'\'), ($searchSubmitted = false), %s)', $searchAction);
}
?>
		<div class="members-search__actions wt_flex wt_items-center wt_gap-2">
			<button
				<?php if (!empty($searchButtonAction)) : ?>data-on:click="<?php echo esc_attr($searchButtonAction); ?>"<?php endif; ?>
				<?php if (!empty($searchSuccess)) : ?>data-on:success="<?php echo esc_attr($searchSuccess); ?>"<?php endif; ?>
				data-show="!$searchSubmitted"
				data-indicator:members-loading
				class="members-search__submit button button--primary wt_whitespace-nowrap component-button"
				<?php disabled(empty($membershipUuid)); ?>><?php esc_html_e('Search', 'wicket-acc'); ?></button>
			<button
				<?php if (!empty($clearButtonAction)) : ?>data-on:click="<?php echo esc_attr($clearButtonAction); ?>"<?php endif; ?>
				<?php if (!empty($searchSuccess)) : ?>data-on:success="<?php echo esc_attr($searchSuccess); ?>"<?php endif; ?>
				data-show="$searchSubmitted && $searchQuery && $searchQuery.trim() !== ''"
				data-indicator:members-loading
				class="members-search__clear button button--secondary wt_whitespace-nowrap component-button"
				<?php disabled(empty($membershipUuid)); ?>><?php esc_html_e('Clear', 'wicket-acc'); ?></button>
		</div>
	</div>
    </div>
	<?php if (empty($membershipUuid)) : ?>
	</p>
	<?php else : ?>
	<?php
$members_list_endpoint = $membersListEndpoint;
	    $members_list_target = $containerId;
	    $org_uuid_for_partial = $org_uuid;
	    $member_list_config = is_array($orgman_config['presentation']['member_list'] ?? null)
	        ? $orgman_config['presentation']['member_list']
	        : [];
	    $use_legacy_member_list = (bool) ($member_list_config['use_legacy_list'] ?? false);
	    if ($use_legacy_member_list) {
	        include __DIR__ . '/members-list.php';
	    } else {
	        $mode = (string) ($configService->getRosterMode() ?? 'direct');
	        $members = $membersResult['members'] ?? [];
	        $pagination = $membersResult['pagination'] ?? [];
	        $query = $membersResult['query'] ?? '';
	        $membership_uuid = $membershipUuid ?? '';
	        include __DIR__ . '/members-list-unified.php';
	    }

	    $membership_query_fragment = $membershipUuid ? '&membership_uuid=' . rawurlencode((string) $membershipUuid) : '';
	    $add_member_modal_reset_actions = "(() => { const modal = document.getElementById('membersAddModal'); const messages = modal ? modal.querySelector('[id^=\"add-member-messages-\"]') : document.querySelector('[id^=\"add-member-messages-\"]'); if (messages) messages.innerHTML = ''; const form = modal ? modal.querySelector('form') : document.querySelector('#membersAddModal form'); if (form && form.reset) form.reset(); })(); \$membersLoading = false; \$addMemberSubmitting = false; \$addMemberSuccess = false; \$autoCloseCountdown = 0; \$addMemberModalOpen = false; \$addMemberSuccessMessage = '';";
	    $add_member_request_close_actions = '$addMemberModalOpen = false;';
	    $add_member_success_actions = "console.log('Member added successfully'); \$addMemberSubmitting = false; \$membersLoading = false; \$addMemberSuccess = true;";
	    $org_view_config = is_array($presentation_config['member_view'] ?? null) ? $presentation_config['member_view'] : [];
	    $org_add_member_auto_close_on_success = (bool) ($org_view_config['add_member_auto_close_on_success'] ?? false);
	    $org_add_member_auto_close_delay_seconds = max(0, (int) ($org_view_config['add_member_auto_close_delay_seconds'] ?? 7));
	    if ($org_add_member_auto_close_on_success && $org_add_member_auto_close_delay_seconds > 0) {
	        $add_member_success_actions .= " \$autoCloseCountdown = {$org_add_member_auto_close_delay_seconds};";
	    }
	    $add_member_error_actions = "console.error('Failed to add member'); \$addMemberSubmitting = false; \$membersLoading = false; \$addMemberSuccess = false; \$autoCloseCountdown = 0;";
	    if ($clear_form_on_error) {
	        $add_member_error_actions .= " el.closest('form').reset();";
	    }
	    $add_member_endpoint = \WicketORM\Helpers\template_url() . 'process/add-member';
	    ?>

	<div class="wt_mt-6">
		<?php /* Add Member button moved to members-list.php for dynamic refresh */ ?>

		<?php
	    // Implementer setup warning: runs first, independently of role checks, so
	    // administrators always see missing-prerequisite messages regardless of whether
	    // they hold a membership_owner role on this org.
	    $is_admin = current_user_can('administrator');
	    $additional_seats_enabled = $configService->isAdditionalSeatsEnabled();
	    $setup_issues = [];
	    if ($additional_seats_enabled && $is_admin) {
	        $setup_issues = $additional_seats_service->getAdditionalSeatsSetupIssues();
	    }

	    if (!empty($setup_issues)):
	        ?>
		<div class="orgman-setup-warning wt_mt-4 wt_p-4 wt_rounded-md" style="background:#fff8e1;border:2px solid #f9a825;color:#5d4037;">
			<h3 class="orgman-setup-warning__title wt_mt-0 wt_mb-2 wt_font-semibold wt_text-lg" style="display:flex;align-items:center;gap:0.4rem;">
				<span aria-hidden="true">⚠️</span> <?php esc_html_e('Visible to administrators only', 'wicket-acc'); ?>
			</h3>
			<p class="wt_font-semibold wt_mb-2">
				<?php esc_html_e('Additional Seats: Setup Incomplete.', 'wicket-acc'); ?>
			</p>
			<p class="wt_mb-2"><?php esc_html_e('The "Purchase Additional Seats" button is hidden because the following items are not yet configured:', 'wicket-acc'); ?></p>
			<ul style="list-style:disc;padding-left:1.25rem;margin:0 0 0.5rem;">
				<?php foreach ($setup_issues as $issue): ?>
				<li><?php
	                    foreach ($issue['parts'] as $part) {
	                        if ($part['type'] === 'token') {
	                            echo '<code class="orgman-copy-token" data-copy-value="' . esc_attr($part['value']) . '" title="' . esc_attr__('Click to copy', 'wicket-acc') . '" style="cursor:pointer;background:#fff3cd;border:1px solid #f9a825;border-radius:3px;padding:1px 5px;font-family:monospace;font-size:0.9em;">' . esc_html($part['value']) . '</code>';
	                        } else {
	                            echo esc_html($part['value']);
	                        }
	                    }
				    ?></li>
				<?php endforeach; ?>
			</ul>
		<?php
		$orgman_cfg = $configService->getFullConfig();
		$orgman_form_slug = $orgman_cfg['integrations']['additional_seats']['form_slug'] ?? 'additional-seats';
		$orgman_form_slug = is_string($orgman_form_slug) ? trim($orgman_form_slug) : 'additional-seats';
		$orgman_tier_field = $configService->getAdditionalSeatsTierSlugField();
		$orgman_token_attrs = 'title="' . esc_attr__('Click to copy', 'wicket-acc') . '" style="cursor:pointer;background:#fff3cd;border:1px solid #f9a825;border-radius:3px;padding:1px 5px;font-family:monospace;font-size:0.9em;"';
		?>
		<ul class="orgman-setup-warning__config" style="list-style:none;padding-left:0;margin:0.5rem 0 0;border-top:1px solid #f9a825;padding-top:0.5rem;opacity:0.85;">
			<li style="margin-bottom:0.5rem;">
				<strong><?php esc_html_e('Expected Gravity Form slug:', 'wicket-acc'); ?></strong>
				<code class="orgman-copy-token" data-copy-value="<?php echo esc_attr($orgman_form_slug); ?>" <?php echo $orgman_token_attrs; // phpcs:ignore ?>><?php echo esc_html($orgman_form_slug); ?></code><br>
				<em style="display:block;margin-top:0.25rem;"><?php esc_html_e('Map this slug to the additional-seats Gravity Form under Gravity Forms > Wicket Settings > Form Slug ID Mapping.', 'wicket-acc'); ?></em>
			</li>
			<?php if ($orgman_tier_field !== '') : ?>
			<li style="margin-bottom:0;">
				<strong><?php esc_html_e('Tier slug hidden-field parameter:', 'wicket-acc'); ?></strong>
				<code class="orgman-copy-token" data-copy-value="<?php echo esc_attr($orgman_tier_field); ?>" <?php echo $orgman_token_attrs; // phpcs:ignore ?>><?php echo esc_html($orgman_tier_field); ?></code><br>
				<em style="display:block;margin-top:0.25rem;"><?php esc_html_e('Name of the hidden field (Parameter Name) on the form that receives the membership tier slug from the URL. GF conditional logic reads it to show only that tier’s quantity input, and the submission handler reads it to pick the right tier-specific product.', 'wicket-acc'); ?></em>
			</li>
			<?php endif; ?>
		</ul>
		</div>
		<script>
		(function () {
		    if (window.__orgmanCopyTokenBound) return;
		    window.__orgmanCopyTokenBound = true;
		    document.addEventListener('click', function (e) {
		        var token = e.target.closest('.orgman-copy-token');
		        if (!token) return;
		        var value = token.dataset.copyValue || token.textContent;
		        if (!navigator.clipboard) return;
		        navigator.clipboard.writeText(value).then(function () {
		            var existing = token.querySelector('.orgman-copy-feedback');
		            if (existing) existing.remove();
		            var tip = document.createElement('span');
		            tip.className = 'orgman-copy-feedback';
		            tip.textContent = '✓ Copied!';
		            tip.style.cssText = 'margin-left:6px;font-size:0.8em;color:#155724;font-family:sans-serif;font-weight:600;';
		            token.appendChild(tip);
		            token.style.background = '#d4edda';
		            setTimeout(function () {
		                tip.remove();
		                token.style.background = '#fff3cd';
		            }, 1500);
		        });
		    });
		}());
		</script>
		<?php endif; ?>

		<?php
            // Check if user can purchase additional seats (requires membership_owner role on this org).
            $can_purchase_seats = $additional_seats_service->canPurchaseAdditionalSeats($org_uuid);
$purchase_url = $can_purchase_seats ? $additional_seats_service->getPurchaseFormUrl($org_uuid, $membershipUuid) : '';

if ($can_purchase_seats && !empty($purchase_url)):
    ?>
		<?php
        get_component('card-call-out', [
            'title' => __('Need More Seats?', 'wicket-acc'),
            'description' => __('Purchase additional seats for your organization membership to accommodate more team members.', 'wicket-acc'),
            'style' => 'secondary',
            'links' => [
                [
                    'link' => [
                        'title' => __('Purchase Additional Seats', 'wicket-acc'),
                        'url' => $purchase_url,
                        'target' => '_self',
                    ],
                    'link_style' => 'secondary',
                ],
            ],
            'classes' => ['my-3'],
        ]);
    ?>
		<?php endif; ?>

		<?php if (\WicketORM\Helpers\PermissionHelper::can_add_members($org_uuid)): ?>
		<dialog id="membersAddModal"
			class="modal wt_m-auto max_wt_3xl wt_rounded-md wt_shadow-md backdrop_wt_bg-black-50"
			data-show="$addMemberModalOpen" data-effect="if ($addMemberModalOpen) el.showModal(); else el.close();"
			data-on:close="<?php echo esc_attr($add_member_modal_reset_actions); ?>">
			<div class="wt_bg-white wt_p-6 wt_relative">
				<button type="button" class="orgman-modal__close wt_absolute wt_right-4 wt_top-4 wt_text-lg wt_font-semibold"
					data-on:click="<?php echo esc_attr($add_member_request_close_actions); ?>" data-show="!$addMemberSuccess"
					data-class="{ 'wt_pointer-events-none': $addMemberSubmitting, 'wt_opacity-50': $addMemberSubmitting }"
					data-attr:aria-disabled="$addMemberSubmitting ? 'true' : 'false'">
					×
				</button>

				<h2 class="wp-block-heading has-heading-sm-font-size wt_text-2xl wt_font-semibold wt_mb-4">
					<?php esc_html_e('Add Member', 'wicket-acc'); ?>
				</h2>

				<div
					id="add-member-messages-<?php echo esc_attr($org_uuid_dom_suffix); ?>">
				</div>

				<form name="add_new_person_membership_form" id="add_new_person_membership_form"
					class="wt_flex wt_flex-col wt_gap-4" method="POST"
					data-show="!$addMemberSuccess"
					data-on:submit="if(!$addMemberSubmitting){ $addMemberSubmitting = true; $membersLoading = true; @post('<?php echo esc_js($add_member_endpoint); ?>', { contentType: 'form' }); }"
					data-on:submit__prevent-default="true"
					data-on:success="<?php echo esc_attr($add_member_success_actions); ?>"
					data-on:error="<?php echo esc_attr($add_member_error_actions); ?>"
					data-on:datastar-fetch="if (evt.detail.type === 'finished' && typeof $addMemberFormError !== 'undefined' && $addMemberFormError) { if (el && el.reset) el.reset(); $addMemberFormError = false; }"
					data-on:reset="$addMemberSubmitting = false">
					<input type="hidden" name="org_uuid"
						value="<?php echo esc_attr($org_uuid); ?>">
					<input type="hidden" name="org_dom_suffix"
						value="<?php echo esc_attr($org_uuid_dom_suffix); ?>">
					<input type="hidden" name="membership_id"
						value="<?php echo esc_attr($membershipUuid); ?>">
					<input type="hidden" name="included_id"
						value="<?php echo esc_attr($membersResult['included_id'] ?? ''); ?>">
					<input type="hidden" name="nonce"
						value="<?php echo esc_attr(wp_create_nonce('wicket-orgman-add-member')); ?>">

					<?php
            // Render configurable form fields
            $form_config = $orgman_config['member_management']['forms']['add_member']['fields'] ?? [];
		    $clear_form_on_error = $orgman_config['member_management']['forms']['add_member']['clear_form_on_error'] ?? false;
		    $relationship_types = $orgman_config['relationships']['labels']['custom'] ?? [];
		    ?>

					<?php if ($form_config['first_name']['enabled'] ?? false): ?>
					<div>
						<label class="wt_block wt_text-sm wt_font-medium wt_mb-1" for="new-member-first-name">
							<?php echo esc_html($form_config['first_name']['label'] ?? __('First Name', 'wicket-acc')); ?>
							<?php echo ($form_config['first_name']['required'] ?? false) ? '*' : ''; ?>
						</label>
						<input type="text" id="new-member-first-name" name="first_name"
							<?php echo ($form_config['first_name']['required'] ?? false) ? 'required' : ''; ?>
						class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2">
					</div>
					<?php endif; ?>

					<?php if ($form_config['last_name']['enabled'] ?? false): ?>
					<div>
						<label class="wt_block wt_text-sm wt_font-medium wt_mb-1" for="new-member-last-name">
							<?php echo esc_html($form_config['last_name']['label'] ?? __('Last Name', 'wicket-acc')); ?>
							<?php echo ($form_config['last_name']['required'] ?? false) ? '*' : ''; ?>
						</label>
						<input type="text" id="new-member-last-name" name="last_name"
							<?php echo ($form_config['last_name']['required'] ?? false) ? 'required' : ''; ?>
						class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2">
					</div>
					<?php endif; ?>

					<?php if ($form_config['email']['enabled'] ?? false): ?>
					<div>
						<label class="wt_block wt_text-sm wt_font-medium wt_mb-1" for="new-member-email">
							<?php echo esc_html($form_config['email']['label'] ?? __('Email Address', 'wicket-acc')); ?>
							<?php echo ($form_config['email']['required'] ?? false) ? '*' : ''; ?>
						</label>
						<input type="email" id="new-member-email" name="email"
							<?php echo ($form_config['email']['required'] ?? false) ? 'required' : ''; ?>
						class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2"
						placeholder="<?php echo esc_attr(__('user@mail.com', 'wicket-acc')); ?>">
					</div>
					<?php endif; ?>

					<?php if ($form_config['relationship_type']['enabled'] ?? false && !empty($relationship_types)): ?>
					<div>
						<label class="wt_block wt_text-sm wt_font-medium wt_mb-1" for="new-member-relationship-type">
							<?php echo esc_html($form_config['relationship_type']['label'] ?? __('Relationship Type', 'wicket-acc')); ?>
							<?php echo ($form_config['relationship_type']['required'] ?? false) ? '*' : ''; ?>
						</label>
						<select id="new-member-relationship-type" name="relationship_type"
							<?php echo ($form_config['relationship_type']['required'] ?? false) ? 'required' : ''; ?>
							class="wt_w-full wt_border wt_border-color wt_rounded-md wt_p-2">
							<option value="">
								<?php esc_html_e('Select a relationship type', 'wicket-acc'); ?>
							</option>
							<?php foreach ($relationship_types as $type_key => $type_label): ?>
							<option
								value="<?php echo esc_attr($type_key); ?>">
								<?php echo esc_html($type_label); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php endif; ?>

					<?php
		    $permissions_field_config = $orgman_config['member_management']['forms']['add_member']['fields']['permissions'] ?? [];
		    $allowed_roles = $permissions_field_config['allowlist'] ?? [];
		    $excluded_roles = $permissions_field_config['denylist'] ?? [];
		    // Filter out membership_owner if configured to prevent assignment
		    if (!empty($orgman_config['access']['permissions']['prevent_owner_assignment'])) {
		        unset($available_roles['membership_owner']);
		    }
		    $available_roles = \WicketORM\Helpers\PermissionHelper::filter_role_choices(
		        $available_roles,
		        is_array($allowed_roles) ? $allowed_roles : [],
		        is_array($excluded_roles) ? $excluded_roles : []
		    );
		    ?>

					<?php if (!empty($available_roles)) : ?>
					<fieldset class="wt_flex wt_flex-col wt_gap-2">
						<legend class="wt_text-sm wt_font-medium">
							<?php esc_html_e('Security Roles', 'wicket-acc'); ?>
						</legend>
						<?php foreach ($available_roles as $role_slug => $role_name) : ?>
						<label class="wt_flex wt_items-center wt_gap-2">
							<input type="checkbox" name="roles[]"
								value="<?php echo esc_attr($role_slug); ?>"
								class="form-checkbox">
							<span><?php echo esc_html($role_name); ?></span>
							<?php if (!empty($role_descriptions[$role_slug])): ?>
								<span class="wt_text-content-secondary wt_ml-1"><?php echo esc_html($role_descriptions[$role_slug]); ?></span>
							<?php endif; ?>
						</label>
						<?php endforeach; ?>
					</fieldset>
					<?php endif; ?>

					<div class="wt_flex wt_justify-end wt_gap-3 wt_pt-4" data-show="!$addMemberSuccess">
						<button type="button" class="button button--secondary component-button"
							data-on:click="<?php echo esc_attr($add_member_request_close_actions); ?>"
							data-class="{ 'wt_pointer-events-none': $addMemberSubmitting, 'wt_opacity-50': $addMemberSubmitting }"
							data-attr:aria-disabled="$addMemberSubmitting ? 'true' : 'false'"><?php esc_html_e('Cancel', 'wicket-acc'); ?></button>
						<button type="submit" class="button button--primary wt_button_submit_async wt_inline-flex wt_items-center wt_gap-2 component-button"
							data-class="{ 'wt_pointer-events-none': $addMemberSubmitting, 'wt_opacity-50': $addMemberSubmitting, 'wt_is-loading': $addMemberSubmitting }"
							data-attr:aria-disabled="$addMemberSubmitting ? 'true' : 'false'">
							<span class="wt_submit_label" data-show="!$addMemberSubmitting">
								<?php esc_html_e('Add Member', 'wicket-acc'); ?>
							</span>
							<span class="wt_loader wt_loader_button wt_submit_loader"
								data-show="$addMemberSubmitting"
								aria-hidden="true"></span>
						</button>
					</div>
				</form>
				<div class="wt_pt-4" data-show="$addMemberSuccess">
					<?php if ($org_add_member_auto_close_on_success) : ?>
						<p class="wt_text-sm wt_text-content wt_mb-3" data-show="$autoCloseCountdown > 0"
							data-on-interval__duration.1000="if ($autoCloseCountdown > 1) { $autoCloseCountdown-- } else if ($autoCloseCountdown === 1) { <?php echo esc_attr($add_member_request_close_actions); ?> }">
							<?php esc_html_e('This dialog will close automatically in', 'wicket-acc'); ?>
							<span class="wt_font-semibold" data-text="$autoCloseCountdown"></span>
							<?php esc_html_e('seconds.', 'wicket-acc'); ?>
						</p>
					<?php endif; ?>
					<div class="wt_mb-4 wt_bg-green-100 wt_border wt_border-green-400 wt_text-green-700 wt_px-4 wt_py-3 wt_rounded-sm" data-show="$addMemberSuccessMessage !== ''">
						<p><strong><?php esc_html_e('Success!', 'wicket-acc'); ?></strong></p>
						<p data-text="$addMemberSuccessMessage"></p>
					</div>
					<div class="wt_flex wt_justify-end">
						<button type="button" class="button button--primary component-button"
							data-on:click="<?php echo esc_attr($add_member_request_close_actions); ?>">
							<?php esc_html_e('Close', 'wicket-acc'); ?>
						</button>
					</div>
				</div>
			</div>
		</dialog>

		<?php if ($show_bulk_upload) : ?>
		<?php
		    $bulk_upload_endpoint = \WicketORM\Helpers\template_url() . 'process/bulk-upload-members';
		    $bulk_upload_messages_id = 'bulk-upload-messages-' . sanitize_html_class($org_uuid ?: 'default');
		    $membership_uuid = $membershipUuid;
		    $bulk_upload_wrapper_class = 'wt_rounded-md wt_border wt_border-color wt_bg-white wt_p-4';
		    ?>
		<dialog id="membersBulkUploadModal"
			class="modal wt_m-auto max_wt_3xl wt_rounded-md wt_shadow-md backdrop_wt_bg-black-50"
			data-show="$bulkUploadModalOpen"
			data-effect="if ($bulkUploadModalOpen) el.showModal(); else el.close();"
			data-on:close="($membersLoading = false); $bulkUploadModalOpen = false">
			<div class="wt_bg-white wt_p-6 wt_relative">
				<button type="button" class="orgman-modal__close wt_absolute wt_right-4 wt_top-4 wt_text-lg wt_font-semibold"
					data-on:click="$bulkUploadModalOpen = false"
					data-class="{ 'wt_pointer-events-none': $bulkUploadSubmitting, 'wt_opacity-50': $bulkUploadSubmitting }"
					data-attr:aria-disabled="$bulkUploadSubmitting ? 'true' : 'false'">
					×
				</button>
				<?php include __DIR__ . '/members-bulk-upload.php'; ?>
			</div>
		</dialog>
		<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>
