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
        $block_logic 			= get_field('block_logic');
        $renewal_period 	= get_field('renewal_period');
        $mandatory_fields = get_field('select_profile_mandatory_fields');
        $title       			= get_field('ac_callout_title');
        $description 			= get_field('ac_callout_description');
        $links       			= get_field('ac_callout_links');
        $memberships 			= wicket_get_active_memberships();
        $woo_memberships	= woo_get_active_memberships();
        $classes          = [];

        if ($this->is_preview) {
            if($block_logic == '') {
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
                if (!class_exists('\Wicket_Memberships\Wicket_Memberships')) {
                    $show_block = (!$memberships && !$woo_memberships) ? true : false;
                } else {
                    if(class_exists('\Wicket_Memberships\Membership_Controller')) {
                        $renewal_type = 'pending_approval';
                        $membership_renewals = (new \Wicket_Memberships\Membership_Controller())->get_membership_callouts();
                        #$membership_renewals[$renewal_type] = '';
                        if(!empty($membership_renewals[$renewal_type])) {
                            foreach($membership_renewals[$renewal_type] as $renewal_data) {
                                $links = [];
                                $title = $renewal_data['callout']['header'];
                                $description = $renewal_data['callout']['content'];
                                $link['link'] = [
                                    'title' => $renewal_data['callout']['button_label'],
                                    'url' => 'mailto: ' . $renewal_data['callout']['email'],
                                ];
                                $links[] = $link;
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
                    $membership_to_renew = is_renewal_period($memberships, $renewal_period);
                    $membership_to_renew = (!$membership_to_renew) ? is_renewal_period($woo_memberships, $renewal_period) : $membership_to_renew;
                    $show_block = ($membership_to_renew) ? true : false;
                } else {
                    $membership_renewals = (new \Wicket_Memberships\Membership_Controller())->get_membership_callouts();
                    #echo '<pre>'; var_dump( $membership_renewals );exit;
                    if (!empty($_ENV['WICKET_MEMBERSHIPS_DEBUG_RENEW'])) {
                        echo '<p>For testing callouts add <pre>?wicket_wp_membership_debug_days=123</pre> to see what callouts would appear in 123 days.</p><br><br>';
                    }
                    foreach ($membership_renewals as $renewal_type => $renewal_data) {
                        foreach ($renewal_data as $membership) {
                            if (!empty($_ENV['WICKET_MEMBERSHIPS_DEBUG_ACC']) && $renewal_type == 'debug') {
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
                                'description' => $description,
                                'links'       => $links,
                                'style'       => '',
                            ]);
                            echo '</div>';
                        }
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
