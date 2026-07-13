<?php

declare(strict_types=1);

namespace WicketORM\Config;

final class OrgManConfig
{
    /**
     * Get organization roster configuration.
     *
     * @return array
     */
    public static function get(): array
    {
        if (!defined('ABSPATH')) {
            exit;
        }

        $orgmanConfig = [
            'access' => [
                'roles' => [
                    'owner' => 'membership_owner',
                    'manager' => 'membership_manager',
                    'editor' => 'org_editor',
                    'aliases' => [],
                    'labels' => [
                        'membership_manager' => __('Membership Manager', 'wicket-acc'),
                        'org_editor' => __('Org. Editor', 'wicket-acc'),
                        'membership_owner' => __('Membership Owner', 'wicket-acc'),
                    ],
                    'descriptions' => [
                        'membership_owner' => __('Primary owner role for this organization membership', 'wicket-acc'),
                        'membership_manager' => __('Can add, remove, and edit users in the roster', 'wicket-acc'),
                        'org_editor' => __('Ability to edit Organization\'s profile', 'wicket-acc'),
                    ],
                ],
                'permissions' => [
                    'organization_edit_roles' => [
                        'org_editor',
                    ],
                    'manage_member_roles' => [
                        'membership_manager',
                        'membership_owner',
                    ],
                    'add_member_roles' => [
                        'membership_manager',
                        'membership_owner',
                    ],
                    'remove_member_roles' => [
                        'membership_manager',
                        'membership_owner',
                    ],
                    'purchase_seat_roles' => [
                        'membership_owner',
                        'membership_manager',
                        'org_editor',
                    ],
                    'any_management_roles' => [
                        'org_editor',
                        'membership_manager',
                        'membership_owner',
                    ],
                    'prevent_owner_removal' => false,
                    'owner_removal_requires_membership_owner_role' => false,
                    'prevent_owner_assignment' => true,
                    'relationship_grants' => [
                        'enabled' => false,
                        'roles_by_type' => [
                            'ceo' => [
                                'org_editor',
                                'membership_manager',
                            ],
                            'primary_hr_contact' => [
                                'org_editor',
                                'membership_manager',
                            ],
                            'member_contact' => [
                                'org_editor',
                                'membership_manager',
                            ],
                            'employee_staff' => [],
                            'advertising_sponsor_contact' => [],
                            'advertising_sponsor_billing' => [],
                        ],
                    ],
                    'role_only_management_access' => [
                        'enabled' => false,
                        'allowed_roles' => [
                            'membership_owner',
                        ],
                    ],
                ],
            ],
            'membership' => [
                'strategy' => 'direct',
                'resolution' => [
                    'prefer_current_cycle' => false,
                ],
                'cycle' => [
                    'key' => 'membership_cycle',
                    'permissions' => [
                        'add_member_roles' => [
                            'membership_manager',
                        ],
                        'remove_member_roles' => [
                            'membership_manager',
                        ],
                        'purchase_seat_roles' => [
                            'membership_owner',
                            'membership_manager',
                            'org_editor',
                        ],
                    ],
                    'prevent_owner_removal' => true,
                ],
                'seat_limits' => [
                    'tier_max_assignments' => [],
                    'tier_name_case_sensitive' => false,
                ],
            ],
            'relationships' => [
                'defaults' => [
                    'type' => 'Position',
                ],
                'removal' => [],
                'addition' => [
                    'type' => 'position',
                ],
                'filters' => [
                    'allowlist' => [],
                    'denylist' => [],
                ],
                'display' => [
                    'member_card_active_only' => false,
                ],
                'labels' => [
                    'custom' => [
                        'ceo' => __('CEO', 'wicket-acc'),
                        'primary_hr_contact' => __('Primary HR Contact', 'wicket-acc'),
                        'employee_staff' => __('Employee', 'wicket-acc'),
                        'member_contact' => __('Member Contact', 'wicket-acc'),
                    ],
                    'special' => [
                        'advertising_sponsor_contact' => __('Advertising/Sponsor Contact', 'wicket-acc'),
                        'advertising_sponsor_billing' => __('Advertising/Sponsor Billing Contact', 'wicket-acc'),
                    ],
                ],
            ],
            'member_management' => [
                'addition' => [
                    'auto_assign_roles' => [],
                    'base_member_role' => 'member',
                    'repair_stale_relationship_without_membership' => true,
                    'protected_relationship_types' => [],
                    'auto_opt_in_communications' => [
                        'enabled' => true,
                        'email' => true,
                        'sublists' => [
                            'one',
                            'two',
                            'three',
                            'four',
                            'five',
                        ],
                    ],
                ],
                'removal' => [
                    'direct' => [
                        'preserve_relationship' => false,
                    ],
                ],
                'forms' => [
                    'add_member' => [
                        'layout' => 'full',
                        'fields' => [
                            'first_name' => [
                                'enabled' => true,
                                'required' => true,
                                'label' => __('First Name', 'wicket-acc'),
                            ],
                            'last_name' => [
                                'enabled' => true,
                                'required' => true,
                                'label' => __('Last Name', 'wicket-acc'),
                            ],
                            'email' => [
                                'enabled' => true,
                                'required' => true,
                                'label' => __('Email Address', 'wicket-acc'),
                            ],
                            'relationship_type' => [
                                'enabled' => false,
                                'required' => false,
                                'label' => __('Relationship Type', 'wicket-acc'),
                            ],
                            'description' => [
                                'enabled' => true,
                                'required' => false,
                                'label' => __('Description', 'wicket-acc'),
                                'input_type' => 'textarea',
                            ],
                            'permissions' => [
                                'enabled' => true,
                                'required' => true,
                                'label' => __('Permissions', 'wicket-acc'),
                                'allowlist' => [],
                                'denylist' => [],
                            ],
                        ],
                        'allow_relationship_type_editing' => false,
                        'clear_form_on_error' => false,
                    ],
                ],
                'bulk_upload' => [
                    'batch_size' => 25,
                    'columns' => [
                        'first_name' => [
                            'enabled' => true,
                            'required' => true,
                            'header' => __('First Name', 'wicket-acc'),
                            'aliases' => ['first name', 'firstname', 'first'],
                        ],
                        'last_name' => [
                            'enabled' => true,
                            'required' => true,
                            'header' => __('Last Name', 'wicket-acc'),
                            'aliases' => ['last name', 'lastname', 'last'],
                        ],
                        'email' => [
                            'enabled' => true,
                            'required' => true,
                            'header' => __('Email Address', 'wicket-acc'),
                            'aliases' => ['email address', 'email', 'e-mail'],
                        ],
                        'relationship_type' => [
                            'enabled' => true,
                            'required' => true,
                            'header' => __('Relationship Type', 'wicket-acc'),
                            'aliases' => ['relationship type', 'relationship'],
                        ],
                        'roles' => [
                            'enabled' => true,
                            'required' => false,
                            'header' => __('Roles', 'wicket-acc'),
                            'aliases' => ['roles', 'permissions', 'role'],
                        ],
                    ],
                    'relationship_type' => [
                        'required' => true,
                        'allowed_types' => [
                            'employee_staff',
                            'grade_4',
                        ],
                        'aliases' => [
                            'employee' => 'employee_staff',
                            'grade 4' => 'grade_4',
                            'grade_4' => 'grade_4',
                        ],
                    ],
                ],
                'permissions_modal' => [
                    'allowlist' => [],
                    'denylist' => [],
                ],
                'edit' => [
                    'require_active_membership_for_role_updates' => false,
                ],
            ],
            'groups' => [
                'matching' => [
                    'tag_name' => 'Roster Management',
                    'tag_case_sensitive' => false,
                ],
                'roles' => [
                    'management' => [
                        'president',
                        'delegate',
                        'alternate_delegate',
                        'council_delegate',
                        'council_alternate_delegate',
                        'correspondent',
                    ],
                    'roster' => [
                        'member',
                        'observer',
                    ],
                    'member' => 'member',
                    'observer' => 'observer',
                    'seat_limited' => ['member'],
                ],
                'list' => [
                    'page_size' => 10,
                    'member_page_size' => 10,
                ],
                'additional_info' => [
                    'key' => 'association',
                    'value_field' => 'name',
                    'fallback_to_org_uuid' => true,
                ],
                'removal' => [
                    'mode' => 'end_date',
                    // Keep this at the base-plugin UTC instant format unless a site explicitly needs a custom API format.
                    'end_date_format' => 'Y-m-d\\TH:i:s\\Z',
                ],
                'presentation' => [
                    'enable_group_profile_edit' => true,
                    'use_unified_member_list' => true,
                    'use_unified_member_view' => true,
                    'show_edit_permissions' => false,
                    'add_member_auto_close_on_success' => false,
                    'add_member_auto_close_delay_seconds' => 7,
                    'search_clear_requires_submit' => true,
                    'editable_fields' => [
                        'name',
                        'description',
                    ],
                ],
            ],
            'presentation' => [
                'organization_details' => [
                    'show_actions' => true,
                ],
                'organization_list' => [
                    'show_my_role' => true,
                    'page_size' => 10,
                    'use_custom_title' => false,
                    'custom_title' => '',
                    'show_membership_details' => false,
                    'show_organization_name' => true,
                    'show_managed_orgs_summary' => false,
                ],
                'relationships' => [
                    'show_type' => false,
                ],
                'member_list' => [
                    'use_legacy_list' => false,
                    'page_size' => 10,
                    'show_edit_permissions' => true,
                    'show_remove_button' => true,
                    'show_bulk_upload' => false,
                    'display_roles' => [
                        'allowlist' => [],
                        'denylist' => [],
                    ],
                    'account_status' => [
                        'enabled' => true,
                        'show_unconfirmed_label' => true,
                        'confirmed_tooltip' => __('Account confirmed', 'wicket-acc'),
                        'unconfirmed_tooltip' => __('Account not confirmed', 'wicket-acc'),
                        'unconfirmed_label' => __('Account not confirmed', 'wicket-acc'),
                    ],
                    'show_assignment_info' => true,
                    'seat_limit_message' => __('All seats have been assigned. Please purchase additional seats to add more members.', 'wicket-acc'),
                    'remove_policy_callout' => [
                        'enabled' => false,
                        'placement' => 'above_members',
                        'title' => __('Remove Members', 'wicket-acc'),
                        'message' => __('To remove a member from your organization, please contact your association directly.', 'wicket-acc'),
                        'email' => '',
                    ],
                ],
                'member_view' => [
                    'use_unified' => true,
                    'search_clear_requires_submit' => false,
                    'add_member_auto_close_on_success' => false,
                    'add_member_auto_close_delay_seconds' => 7,
                ],
                'member_card' => [
                    'fields' => [
                        'name' => [
                            'enabled' => true,
                            'label' => __('Name', 'wicket-acc'),
                        ],
                        'job_title' => [
                            'enabled' => true,
                            'label' => __('Job Title', 'wicket-acc'),
                        ],
                        'description' => [
                            'enabled' => true,
                            'label' => __('Description', 'wicket-acc'),
                            'input_type' => 'textarea',
                        ],
                        'email' => [
                            'enabled' => true,
                            'label' => __('Email', 'wicket-acc'),
                        ],
                        'roles' => [
                            'enabled' => true,
                            'label' => __('Roles', 'wicket-acc'),
                        ],
                        'relationship_type' => [
                            'enabled' => false,
                            'label' => __('Relationship', 'wicket-acc'),
                        ],
                    ],
                ],
            ],
            'integrations' => [
                'additional_seats' => [
                    'enabled' => false,
                    'sku' => 'additional-seats',
                    'discount_sku' => 'corporate-seat-discount',
                    'form_id' => 0,
                    'form_slug' => 'additional-seats',
                    'min_quantity' => 1,
                    'max_quantity' => 900,
                    // Multi-tier seat purchases (opt-in). When tier_mode is true (or tier_skus is
                    // non-empty) the additional-seats flow resolves one WooCommerce product per
                    // membership tier slug instead of a single shared product. Sites that hold
                    // more than one org membership tier at once (e.g. ESCRS) use this so each
                    // purchase targets the correct membership record. Legacy single-SKU sites
                    // leave this off and keep the existing behaviour.
                    'tier_mode' => false,
                    'tier_skus' => [
                        // 'tier-slug' => 'tier-specific-sku',
                    ],
                    'tier_slug_field' => 'tier-slug',
                    // When true, the "Purchase Additional Seats" button only renders once all
                    // seats are assigned. Defaults to false so the button is always available to
                    // authorized users (members can buy more seats before running out). Sites that
                    // prefer the original behaviour set this to true.
                    'show_button_when_full_only' => false,
                ],
                'documents' => [
                    'allowed_types' => [
                        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif',
                    ],
                    'max_size' => 10 * 1024 * 1024,
                ],
                'business_info' => [
                    'seat_limit_info' => null,
                ],
                'notifications' => [
                    'confirmation_email_from' => 'no-reply@wicketcloud.com',
                ],
            ],
            'platform' => [
                'cache' => [
                    'enabled' => true,
                    'duration' => 5 * 60,
                    'search_clear_cache_duration' => 1 * 60 * 60,
                    'cache_salt' => '202604243100',
                ],
            ],
            'removal' => [
                'end_date_anchor' => 'action_time',
            ],
            'contacts' => [
                'enabled' => false,
                'relationship_types' => [
                    'roster' => [
                        'president',
                        'president_elect',
                        'secretary',
                        'ceo',
                        'treasurer',
                        'main_contact',
                    ],
                ],
                'permissions' => [
                    'can_add' => ['membership_manager'],
                    'can_remove' => ['membership_manager'],
                    'can_view' => ['membership_manager'],
                ],
                'on_add' => [
                    'assign_roles' => [
                        'org_editor',
                        'membership_manager',
                    ],
                ],
                'on_removal' => [
                    'strip_roles' => [
                        'org_editor',
                        'membership_manager',
                    ],
                    'skip_strip_if_has_membership' => true,
                ],
                'presentation' => [
                    'page_size' => 10,
                ],
                'form' => [
                    'relationship_type' => [
                        'president' => __('President', 'wicket-acc'),
                        'president_elect' => __('President Elect', 'wicket-acc'),
                        'secretary' => __('Secretary', 'wicket-acc'),
                        'ceo' => __('CEO', 'wicket-acc'),
                        'treasurer' => __('Treasurer', 'wicket-acc'),
                        'main_contact' => __('Main Contact', 'wicket-acc'),
                    ],
                    'permissions' => [
                        'org_editor' => __('Organization Editor', 'wicket-acc'),
                        'membership_manager' => __('Membership Manager', 'wicket-acc'),
                    ],
                ],
            ],
            'exports' => [
                'enabled'               => false,
                'batch_size'            => 50,
                // Roster size above which the export routes through the async
                // WP-Cron path instead of an in-request build. Below this the
                // sync path avoids WP-Cron entirely (it only fires on traffic
                // and is fragile on low-traffic sites).
                // Overridable per site via wicket/org-roster/config.
                'sync_threshold'        => 250,
                // Token TTL (days) for BOTH sync and async download links.
                // Default 14; overridable per site via the wicket/org-roster/config filter.
                'token_expiration_days' => 14,
                'max_downloads'         => 10,
                'upload_dir_slug'       => 'wicket-exports',
                // NOTE: the legacy boolean 'columns' map was removed in WWID-1907.
                // Export columns now derive from BulkMemberUploadService::getExportColumns()
                // (the same config the front-end roster bulk-upload validates against),
                // so upload and download can never drift apart.
            ],
            'engagement' => [
                'enabled'                => false,
                'member_org_uuids'       => [],
                'person_data_fields_key' => 'data_fields',
                'org_data_fields_key'    => 'data_fields',
                'sections'               => [
                    'foundation' => [
                        'enabled'                    => true,
                        'label'                      => __('Foundation', 'wicket-acc'),
                        'requires_active_membership' => false,
                        'fields'                     => [
                            'current_fy'     => ['mdp_key' => 'fdn_current_fy',     'label' => __('Current FY', 'wicket-acc'), 'format' => 'currency'],
                            'last_fy'        => ['mdp_key' => 'fdn_last_fy',        'label' => __('Last FY', 'wicket-acc'), 'format' => 'currency'],
                            'last_giving_dt' => ['mdp_key' => 'fdn_last_giving_dt', 'label' => __('Last Gift', 'wicket-acc'), 'format' => 'date'],
                            'lifetime_level' => ['mdp_key' => 'fdn_lifetime_level', 'label' => __('Lifetime Level', 'wicket-acc'), 'format' => 'string'],
                            'last_fy_level'  => ['mdp_key' => 'fdn_last_fy_level',  'label' => __('Last FY Level', 'wicket-acc'), 'format' => 'string'],
                            'leadership_soc' => ['mdp_key' => 'fdn_leadership_soc', 'label' => __('Leadership Soc.', 'wicket-acc'), 'format' => 'yesno'],
                            'legacy_soc'     => ['mdp_key' => 'fdn_legacy_soc',     'label' => __('Legacy Soc.', 'wicket-acc'), 'format' => 'yesno'],
                        ],
                        'badge_pattern'        => '/^fdn_Donor_FY(\d{2})$/',
                        'badge_label_template' => 'Foundation Donor FY{year}',
                    ],
                    'pac' => [
                        'enabled'                    => true,
                        'label'                      => __('PAC', 'wicket-acc'),
                        'requires_active_membership' => true,
                        'fields'                     => [
                            'current_fy'     => ['mdp_key' => 'pac_current_fy',     'label' => __('Current FY', 'wicket-acc'), 'format' => 'currency'],
                            'last_fy'        => ['mdp_key' => 'pac_last_fy',        'label' => __('Last FY', 'wicket-acc'), 'format' => 'currency'],
                            'last_giving_dt' => ['mdp_key' => 'pac_last_giving_dt', 'label' => __('Last Gift', 'wicket-acc'), 'format' => 'date'],
                        ],
                        'badge_pattern'        => '/^DonorPAC_FY(\d{2})$/',
                        'badge_label_template' => 'PAC Donor FY{year}',
                    ],
                ],
            ],
        ];

        $orgmanConfig = apply_filters('wicket/org-roster/config', $orgmanConfig);
        $orgmanConfig = apply_filters('wicket/acc/orgman/config', $orgmanConfig);

        return $orgmanConfig;
    }
}
