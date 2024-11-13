<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Callout Block
 *
 **/
class Block_Callout extends WicketAcc
{
    /**
     * Constructor
     */
    public function __construct(
        protected array $block     = [],
        protected bool $is_preview = false,
        protected ?Blocks $blocks = null,
    ) {
        $this->block      = $block;
        $this->is_preview = $is_preview;
        $this->blocks     = $blocks ?? new Blocks();

        // Display the block
        $this->init_block();
    }

    /**
     * Init block
     *
     * @return void
     */
    protected function init_block()
    {
        $block_logic             = get_field('block_logic');
        $renewal_period     = get_field('renewal_period');
        $mandatory_fields = get_field('select_profile_mandatory_fields');
        $title                   = get_field('ac_callout_title');
        $description             = get_field('ac_callout_description');
        $links                   = get_field('ac_callout_links');
        $memberships             = wicket_get_active_memberships();
        $woo_memberships    = woo_get_active_memberships();
        $classes          = [];

        if ($this->is_preview) {
            if ($block_logic == '') {
                $block_logic == 'not-set';
            }
            $args = [
                'block_name'          => 'Membership Block',
                'block_description'   => 'This block displays Membership Callouts [ ' . $block_logic . ' ]',
                'block_slug'          => 'wicket-ac-memberships',
            ];

            $this->blocks->render_template('preview', $args);

            return;
        }

        switch ($block_logic) {

            case 'become_member':
                /**
                 * Special Case needed to display a Pending Callout when no membership exists but order created
                 * Check for Order status 'on-hold' with a Subscription Product in 'membership' Category
                 * If found we will display the Pending Callout for the Product's assigned Tier
                 * Use filter to add product_cat you want to look for
                 * apply_filters("wicket_renewal_filter_product_data", function() { return ['memberships']}, 10, 1);
                 */
                $orders = wc_get_orders(['type' => 'shop_order', 'status' => 'wc-on-hold', 'limit' => -1, 'customer' => get_current_user_id()]);
                $membership_cats = ['membership'];
                $membership_cats = apply_filters("wicket_renewal_filter_product_data", $membership_cats);

                foreach ($orders as $order) {
                    foreach ($order->get_items() as $item) {
                        if (class_exists('WC_Subscriptions_Product') && \WC_Subscriptions_Product::is_subscription($item->get_product_id())) {
                            $terms = get_the_terms($item->get_product_id(), 'product_cat');
                            if (empty($terms) || ! array_intersect($membership_cats, wp_list_pluck($terms, 'slug'))) {
                                continue; //if it is not a membership product check the next one
                            }
                            $Tier = \Wicket_Memberships\Membership_Tier::get_tier_by_product_id($item->get_product_id());
                            //if this is not a pending tier skip it since they just have a membership on hold
                            $tier_approval_required = $Tier->is_approval_required();
                            if (empty($Tier) || empty($tier_approval_required)) {
                                continue;
                            }
                            $iso_code = '';
                            if (defined('ICL_SITEPRESS_VERSION')) {
                                $iso_code = apply_filters('wpml_current_language', null);
                                if (empty($iso_code)) {
                                    $locale = get_locale();
                                    $iso_code = substr($locale, 0, 2);
                                }
                            }
                            $links = [];
                            $title = $Tier->get_approval_callout_header($iso_code);
                            $description = $Tier->get_approval_callout_content($iso_code) . '<!-- on-hold-order_id: ' . $order->ID . ' //-->';
                            $button_label = $Tier->get_approval_callout_button_label($iso_code);
                            $link['link'] = [
                                'title' => $button_label,
                                'url' => 'mailto: ' . $Tier->get_approval_email() . '?subject=' . __('Re: Pending Membership Request', 'wicket-memberships'),
                            ];
                            if(!empty($button_label) && $button_label != ' ') {
                                $links[] = $link;
                            }
                            /**
                             * We are returning early here.
                             */
                            $attrs = get_block_wrapper_attributes(['class' => 'callout-' . $block_logic . ' callout-pending_approval']);
                            echo '<div ' . $attrs . '>';
                            get_component('card-call-out', [
                                'title'       => $title,
                                'description' => $description,
                                'links'       => $links,
                                'style'       => '',
                            ]);
                            echo '</div>';
                            return; //skipping  this will show all the order / products currently on-hold
                        }
                    }
                }

                if (!class_exists('\Wicket_Memberships\Wicket_Memberships')) {
                    $show_block = (!$memberships && !$woo_memberships) ? true : false;
                } else {
                    if (class_exists('\Wicket_Memberships\Membership_Controller')) {
                        /**
                         * Check for an existing membership record that has not been approved yet to show the pending callout.
                         */
                        $renewal_type = 'pending_approval';
                        $membership_renewals = (new \Wicket_Memberships\Membership_Controller())->get_membership_callouts();
                        #$membership_renewals[$renewal_type] = '';
                        if (!empty($membership_renewals[$renewal_type])) {
                            foreach ($membership_renewals[$renewal_type] as $renewal_data) {
                                $links = [];
                                $title = $renewal_data['callout']['header'];
                                $description = $renewal_data['callout']['content'] . '<!-- pending-approval-order_id: ' . $renewal_data['membership']['meta']['membership_parent_order_id'] . ' //-->';
                                if(!empty($renewal_data['callout']['button_label']) && $renewal_data['callout']['button_label'] != ' ') {
                                    $link['link'] = [
                                        'title' => $renewal_data['callout']['button_label'],
                                        'url' => 'mailto: ' . $renewal_data['callout']['email'],
                                    ];
                                    $links[] = $link;
                                }
                                /**
                                 * We are returning early here.
                                 */
                                $attrs = get_block_wrapper_attributes(['class' => 'callout-' . $block_logic . ' callout-' . $renewal_type]);
                                echo '<div ' . $attrs . '>';
                                get_component('card-call-out', [
                                    'title'       => $title,
                                    'description' => $description,
                                    'links'       => $links,
                                    'style'       => '',
                                ]);
                                echo '</div>';
                            }
                            return;
                        }
                        $show_block = (!$memberships) ? true : false;
                    }
                }
                break;

            case 'renewal':
                if (!class_exists('\Wicket_Memberships\Wicket_Memberships')) {
                    //$membership_to_renew = is_renewal_period($memberships, $renewal_period);
                    //$membership_to_renew = (!$membership_to_renew) ? is_renewal_period($woo_memberships, $renewal_period) : $membership_to_renew;
                    $membership_to_renew = is_renewal_period($woo_memberships, $renewal_period);
                    $show_block = ($membership_to_renew) ? true : false;
                } else {
                    $membership_renewals = (new \Wicket_Memberships\Membership_Controller())->get_membership_callouts();
                    if($membership_renewals['membership_exists']) {
                        $hide_existing_classes = ['.acc_hide_mship_any'];
                        foreach($membership_renewals['membership_exists'] as $hide_tier) {
                            $hide_existing_classes[] = '.acc_hide_mship_' . $hide_tier;
                        }
                        add_action(
                            'wp_footer',
                            function () use ($hide_existing_classes) {
                                echo '<style id="acc-hide-classes" type="text/css">' . implode(', ', $hide_existing_classes) . ' { display: none; }</style>';
                            }
                        );
                    }
                    $membership_renewals['membership_exists'] = [];
                    foreach ($membership_renewals as $renewal_type => $renewal_data) {
                        foreach ($renewal_data as $membership) {
                            if (!empty($_ENV['WICKET_MEMBERSHIPS_DEBUG_ACC']) && $renewal_type == 'debug') {
                                if($membership['membership']['ends_in_days'] > 0) {
                                    echo '<pre style="font-size:10px;">';
                                    echo 'DEBUG:<br>';
                                    echo "Renewal Type: {$renewal_type}<br>";
                                    echo "Membership ID: {$membership['membership']['ID']}<br>";
                                    echo "Membership Tier: {$membership['membership']['meta']['membership_tier_name']}<br>";
                                    echo "Sta {$membership['membership']['meta']['membership_starts_at']}<br>";
                                    echo "Early {$membership['membership']['meta']['membership_early_renew_at']}<br>";
                                    echo "End {$membership['membership']['meta']['membership_ends_at']}<br>";
                                    echo "Exp {$membership['membership']['meta']['membership_expires_at']}<br>";
                                    echo "End in {$membership['membership']['ends_in_days']} Days <br>";
                                    echo '</pre>';
                                }
                                continue;
                            }
                            unset($links);
                            #echo '<pre>'; var_dump( $membership ); echo '</pre>';
                            if ($membership['membership']['meta']['membership_status'] == 'pending') {
                                //this status is convered in the Become a Member block
                                continue;
                            } elseif (!empty($membership['membership']['next_tier'])) {
                                #echo '<pre>'; var_dump( $membership['membership']['next_tier'] ); echo '</pre>';
                                $links = wicket_ac_memberships_get_product_link_data($membership, $renewal_type);
                            } elseif (!empty($membership['membership']['form_page'])) {
                                #echo '<pre>'; var_dump( $membership['membership']['form_page'] ); echo '</pre>';
                                $links = wicket_ac_memberships_get_page_link_data($membership);
                            }
                            $title = $membership['callout']['header'];
                            $description = $membership['callout']['content'];
                            if (!empty($_ENV['WICKET_MEMBERSHIPS_DEBUG_ACC'])) {
                                echo '<pre style="font-size:10px;">';
                                echo 'DEBUG:<br>';
                                echo "Renewal Type: {$renewal_type}<br>";
                                echo "Membership ID: {$membership['membership']['ID']}<br>";
                                echo "Membership Tier: {$membership['membership']['meta']['membership_tier_name']}<br>";
                                echo "Sta {$membership['membership']['meta']['membership_starts_at']}<br>";
                                echo "Early {$membership['membership']['meta']['membership_early_renew_at']}<br>";
                                echo "End {$membership['membership']['meta']['membership_ends_at']}<br>";
                                echo "Exp {$membership['membership']['meta']['membership_expires_at']}<br>";
                                echo "End in {$membership['membership']['ends_in_days']} Days <br>";
                                echo '</pre>';
                            }
                            /**
                             * We are returning early here.
                             */
                            $attrs = get_block_wrapper_attributes(['class' => 'callout-' . $block_logic . ' callout-' . $renewal_type]);
                            echo '<div ' . $attrs . '>';
                            get_component('card-call-out', [
                                'title'       => $title,
                                'description' => $description . '<!-- renewal-order_id: ' . $membership['membership']['meta']['membership_parent_order_id'] . ' //-->',
                                'links'       => $links,
                                'style'       => '',
                            ]);
                            echo '</div>';
                        }
                    }
                    if (!empty($_ENV['WICKET_MEMBERSHIPS_DEBUG_ACC'])) {
                        $args = [
                            'post_type' => 'wicket_mship_tier',
                            'post_status' => 'publish',
                            'posts_per_page' => -1,
                        ];
                        $tiers = new \WP_Query($args);
                        foreach($tiers->posts as $tier) {
                            $tier_hide_classes[] = '.acc_hide_mship_' . str_replace([' ','-',','], '', strToLower($tier->post_title));
                        }
                        echo '<div style="padding: 8px;border: solid 2px #ccc; border-radius: 5px;"><p>For testing callouts add <code style="background-color:#ccc;font-size:10pt;"> ?wicket_wp_membership_debug_days=123 </code>&nbsp;to see what callouts would appear in 123 days.</p>';
                        echo '<p>You can add the following classes:&nbsp;<code style="background-color:#ccc;font-size:10pt;"> .acc_hide_mship_any, ' . implode(', ', $tier_hide_classes) . ' </code>&nbsp;to any element on this page to hide when an active or delayed status membership exists for the user.</p></div>';
                    }
                    return;
                }
                break;

            case 'profile':
                $show_block = wicket_profile_widget_validation($mandatory_fields);
                $show_block = ($show_block && ($memberships || $woo_memberships)) ? true : false;
                break;
        }

        $attrs = get_block_wrapper_attributes(['class' => 'callout-' . $block_logic]);

        // Show the block if conditional logic is true OR if viewing in the block editor
        if ($show_block || is_admin()) :

            echo '<div ' . $attrs . '>';

            get_component('card-call-out', [
                'title'       => $title,
                'description' => $description,
                'links'       => $links,
                'style'       => '',
            ]);
            echo '</div>';

        endif;
    }
}
