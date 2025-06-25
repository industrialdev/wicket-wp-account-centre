<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * MARK: PLEASE READ.
 *
 * Don't add any new functions to this file.
 * Don't add any new functions to this file.
 * Don't add any new functions to this file.
 * Don't add any new functions to this file.
 * Don't add any new functions to this file.
 * Don't add any new functions to this file.
 * Don't add any new functions to this file.
 *
 * Thank you.
 */

/**
 * Returns active memberships from wicket API.
 *
 * @deprecated 1.6.0 Available as method WACC()->MdpApi->Membership->getCurrentPersonActiveMemberships()
 */
function wicket_get_active_memberships($iso_code = 'en')
{
    return WACC()->MdpApi->Membership->getCurrentPersonActiveMemberships($iso_code);
}

/**
 * Returns active memberships from WooCommerce.
 *
 * @deprecated 1.6.0 Available as method WACC()->MdpApi->Membership->getCurrentUserWooActiveMemberships()
 */
function woo_get_active_memberships()
{
    return WACC()->MdpApi->Membership->getCurrentUserWooActiveMemberships();
}

/**
 * Returns active memberships relationship from wicket API.
 *
 * @deprecated 1.6.0 Available as method WACC()->MdpApi->Membership->getActiveMembershipRelationship()
 */
function wicket_get_active_memberships_relationship($org_uuid)
{
    return WACC()->MdpApi->Membership->getActiveMembershipRelationship($org_uuid);
}

/**
 * Check if membership is in renewal period.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param array $memberships
 * @param string $renewal_period
 * @return array
 */
function is_renewal_period($memberships, $renewal_period)
{
    $memberships_to_renew = [];
    $today = date('Y-m-d');

    if ($memberships) {
        foreach ($memberships as $membership) {
            $renewal_window = strtotime('-' . $renewal_period . ' days', strtotime($membership['ends_at']));
            if (strtotime($today) >= $renewal_window) {
                $memberships_to_renew = $membership;
            }
        }
    }

    return $memberships_to_renew;
}

/**
 * Validate profile widget fields.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param array $fields Fields to validate
 * @return bool
 */
function wicket_profile_widget_validation($fields = [])
{
    $person = wicket_current_person();
    $results = [];

    if ($fields && $person) {
        foreach ($fields as $field) {
            if ($field == 'addresses') {
                $results[] = wicket_validation_addresses($person);
            } else {
                $results[] = (!is_null($person->$field)) ? true : '';
            }
        }
    }

    if (count(array_unique($results)) === 1) {
        return false;
    }

    return true;
}

/**
 * Validates the addresses of the person.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param object $person Person object
 *
 * @return bool $addresses
 */
function wicket_validation_addresses($person)
{
    $addresses = [];
    $country_name = '';
    $city = '';
    $address1 = '';
    $zip_code = '';

    if ($person) {
        if ($person->relationship('addresses')) {
            foreach ($person->relationship('addresses') as $relationship) {
                foreach ($person->included() as $included) {
                    if ($relationship->id == $included['id']) {
                        $addresses[] = $included;
                    }
                }
            }
        }

        if ($addresses) {
            foreach ($addresses as $address) {
                if ($address['attributes']['primary'] == 1) {
                    $country_name = $address['attributes']['country_name'];
                    $city = $address['attributes']['city'];
                    $address1 = $address['attributes']['address1'];
                    $zip_code = ($country_name == 'United States' || $country_name == 'Canada') ? $address['attributes']['zip_code'] : 'pass';
                }
            }
        }
    }

    if ($country_name && $city && $address1 && $zip_code) {
        return true;
    }

    return false;
}

/**
 * Menu walker for the wicket ACC menu.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @return void
 */
class wicket_acc_menu_walker extends Walker_Nav_Menu
{
    private $current_item;
    private $submenu_order = 0;
    private $classes_end_level;

    public function start_el(&$output, $item, $depth = 0, $args = [], $id = 0)
    {
        $object = $item->object;
        $type = $item->type;
        $title = $item->title;
        $permalink = $item->url;
        $classes = $item->classes;
        $target = $item->target;
        $this->current_item = $item;

        if (!empty($classes)) {
            $classes[] = 'nav__menu-item';
        }

        $output .= "<li class='" . implode(' ', $classes) . "'>";

        // if item has children, replace a link for a button toggle
        if (in_array('menu-item-has-children', $classes) && $depth == 0) {
            $this->submenu_order = 0;
            $output .= "<button id='dropdown-{$item->ID}-control' class='nav__menu-link dropdown__toggle--menu' aria-expanded='false' aria-controls='dropdown-{$item->ID}'>";
            $output .= "<span>{$title}</span>";
        } elseif (isset($permalink)) {
            if ($target == '_blank') {
                $output .= '<a  target="_blank" rel="noopener" href="' . $permalink . '">' . $title;
                $output .= '<i class="fas fa-external-link" aria-hidden="true"></i> <span class="webaim-hidden">Opens in a new window</span>';
            } else {
                $output .= '<a href="' . $permalink . '">' . $title;
            }
        }

        // close button or <a> item
        if (in_array('menu-item-has-children', $classes) && $depth == 0) {
            $output .= '<i class="fa-solid fa-caret-down" aria-hidden="true"></i> </button>';
        } elseif (isset($permalink)) {
            $output .= '</a>';
        }
    }

    public function start_lvl(&$output, $depth = 0, $args = [])
    {
        $current_item_id = $this->current_item->ID;
        $current_item_classes = $this->current_item->classes;
        $this->classes_end_level = $current_item_classes;
        $indent = str_repeat("\t", $depth);

        // if item has children, replace ul sub-menu with dropdown
        if (in_array('menu-item-has-children', $current_item_classes) && $depth == 0) {
            $output .= "\n$indent<div id='dropdown-{$current_item_id}' class='nav__submenu' aria-expanded='false' aria-labelledby='dropdown-{$current_item_id}-control' role='region'><ul class='sub-menu'>\n";
        } else {
            $output .= "\n$indent<ul class='sub-menu'>\n";
        }
    }

    public function end_lvl(&$output, $depth = 0, $args = [])
    {
        $current_item_classes = $this->classes_end_level;
        $indent = str_repeat("\t", $depth);
        if (in_array('menu-item-has-children', $current_item_classes) && $depth == 0) {
            $output .= "$indent</ul></div>\n";
        } else {
            $output .= "$indent</ul>\n";
        }
    }
}

/**
 * Menu walker (mobile) for the wicket ACC menu.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @return void
 */
class wicket_acc_menu_mobile_walker extends Walker_Nav_Menu
{
    private $current_item;
    private $submenu_order = 0;
    private $classes_end_level;

    public function start_el(&$output, $item, $depth = 0, $args = [], $id = 0)
    {
        $object = $item->object;
        $type = $item->type;
        $title = $item->title;
        $permalink = $item->url;
        $classes = $item->classes;
        $target = $item->target;
        $this->current_item = $item;

        if (!empty($classes)) {
            $classes[] = 'nav__menu-item';
        }

        $output .= "<li class='" . implode(' ', $classes) . "'>";

        // if item has children, replace a link for a button toggle
        if (in_array('menu-item-has-children', $classes) && $depth == 0) {
            $this->submenu_order = 0;
            $output .= "<button id='mobile-dropdown-{$item->ID}-control' class='nav__menu-link dropdown__toggle--menu' aria-expanded='false' aria-controls='mobile-dropdown-{$item->ID}'>";
            $output .= "<span>{$title}</span>";
        } elseif (isset($permalink)) {
            if ($target == '_blank') {
                $output .= '<a  target="_blank" rel="noopener" href="' . $permalink . '">' . $title;
                $output .= '<i class="fas fa-external-link" aria-hidden="true"></i> <span class="webaim-hidden">Opens in a new window</span>';
            } else {
                $output .= '<a href="' . $permalink . '">' . $title;
            }
        }

        // close button or <a> item
        if (in_array('menu-item-has-children', $classes) && $depth == 0) {
            $output .= '<i class="fa-solid fa-caret-down" aria-hidden="true"></i> </button>';
        } elseif (isset($permalink)) {
            $output .= '</a>';
        }
    }

    public function start_lvl(&$output, $depth = 0, $args = [])
    {
        $current_item_id = $this->current_item->ID;
        $current_item_classes = $this->current_item->classes;
        $this->classes_end_level = $current_item_classes;
        $indent = str_repeat("\t", $depth);

        // if item has children, replace ul sub-menu with dropdown
        if (in_array('menu-item-has-children', $current_item_classes) && $depth == 0) {
            $output .= "\n$indent<div id='mobile-dropdown-{$current_item_id}' class='nav__submenu' aria-expanded='false' aria-labelledby='mobile-dropdown-{$current_item_id}-control' role='region'><ul class='sub-menu'>\n";
        } else {
            $output .= "\n$indent<ul class='sub-menu'>\n";
        }
    }

    public function end_lvl(&$output, $depth = 0, $args = [])
    {
        $current_item_classes = $this->classes_end_level;
        $indent = str_repeat("\t", $depth);
        if (in_array('menu-item-has-children', $current_item_classes) && $depth == 0) {
            $output .= "$indent</ul></div>\n";
        } else {
            $output .= "$indent</ul>\n";
        }
    }
}

/**
 * Add multiple products to cart.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @return void
 */
function wicket_ac_maybe_add_multiple_products_to_cart()
{
    if (!class_exists('WC_Form_Handler') || empty($_REQUEST['add-to-cart'])) {
        return;
    }
    remove_action('wp_loaded', ['WC_Form_Handler', 'add_to_cart_action'], 20);

    $product_ids = explode(',', $_REQUEST['add-to-cart']);
    $count = count($product_ids);
    $number = 0;
    $url = get_permalink($_REQUEST['page_id']);

    foreach ($product_ids as $product_id) {
        if (++$number === $count) {
            $_REQUEST['add-to-cart'] = $product_id;

            return WC_Form_Handler::add_to_cart_action($url);
        }

        $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint($product_id));
        $adding_to_cart = wc_get_product($product_id);

        if (!$adding_to_cart) {
            continue;
        }

        $add_to_cart_handler = apply_filters('woocommerce_add_to_cart_handler', $adding_to_cart->product_type, $adding_to_cart);
        $quantity = empty($_REQUEST['quantity']) ? 1 : wc_stock_amount($_REQUEST['quantity']);
        $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity);

        if (
            $passed_validation
            && (
                strpos($add_to_cart_handler, 'variable') !== false
                || strpos($add_to_cart_handler, 'variation') !== false
            )
        ) {
            $variation_id = $product_id;
            $variation = wc_get_product($variation_id);
            $parent_id = $variation->get_parent_id();
            $variation_attributes = $variation->get_attributes();
            $variation_data = [];
            foreach ($variation_attributes as $key => $value) {
                $variation_data['attribute_' . $key] = $value;
            }
            WC()->cart->add_to_cart($parent_id, $quantity, $variation_id, $variation_data);
        } else {
            if ($passed_validation && false !== WC()->cart->add_to_cart($product_id, $quantity)) {
                wc_add_to_cart_message([$product_id => $quantity], true);
            }
        }
    }
}
add_action('wp_loaded', 'wicket_ac_maybe_add_multiple_products_to_cart', 15);

/**
 *  Example of a Next Tier[product_data] filter that can be added to the child theme.
 */

/*
  function wicket_admin_filter_products($next_tier) {
    $product_data = $next_tier['product_data'];
    $next_product_id = $next_tier['next_product_id'];
    $product_type_id = 'variation_id'; // 'product_id'
    $renewal_products = [1219];
    foreach($product_data as $product) {
      if(! in_array($product[$product_type_id], $renewal_products)) {
        continue;
      }
      $return_products[] = $product;
    }
    $next_tier['product_data'] = $return_products;
    return $next_tier;
  }
  add_filter( "wicket_renewal_filter_product_data", 'wicket_admin_filter_products', 15);
  */

  /**
   * Create single link url for multiple membership renewal
   * @param mixed $multi_tier_links
   * @param mixed $links
   * @return string
   */
  function wicket_ac_memberships_get_product_multi_tier_links( $multi_tier_links, $links ) {
    $query_string_arg = '';
    $q = empty($multi_tier_links) ? "?" : "&";
    $parts = parse_url($links[0]['link']['url']);
    //$url =  $parts['scheme'] . '://' . $parts['host'] . $parts['path'];    
    parse_str($parts['query'], $query);
    //var_dump($query);
    //var_dump($links);
    //var_dump($multi_tier_links);
    if(!empty( $query['add-to-cart'])) {
      $query_string_arg = "membership_post_id_renew";
      $product_id = isset($query['add-to-cart']) ? (int) $query['add-to-cart'] : null;
      if(count($links) > 1) {
        [$parent_product_id, $variation_id] = wicket_multiple_products_use_tier_reference( $product_id );
        if(!empty($variation_id)) {
          $product_id = $variation_id;
        } else {
          $product_id = $parent_product_id;
        }
      }
      $membership_post_id_renew = isset($query['membership_post_id_renew']) ? (int) $query['membership_post_id_renew'] : null;  
    } else {
      $query_string_arg = "multi_tier_renewal";
      $membership_post_id_renew = isset($query['membership_post_id_renew']) ? (int) $query['membership_post_id_renew'] : null;  
      $product_id = get_post_meta( $membership_post_id_renew, 'membership_tier_post_id', true);
    }

    $return_link = $multi_tier_links .= $q . $query_string_arg . "[$product_id]=$membership_post_id_renew";
    return $return_link;
  }

  /** 
   * We have multiple products on a tier so use the tier_reference value on the product if found
   * 
   */

   function wicket_multiple_products_use_tier_reference( $product_id ) {
    $tier_obj = \Wicket_Memberships\Membership_Tier::get_tier_by_product_id( $product_id );
    if(!empty($tier_obj)) {
      $membership_tier_slug = $tier_obj->get_membership_tier_slug();
      [$parent_product_id, $variation_id] = wicket_get_product_by_tier_reference_with_slug($membership_tier_slug);
    }
    if(!empty($parent_product_id)) {
      return [$parent_product_id, $variation_id];
    }
  }
   
   /**
 * Returns productlinks for renewal callouts based on the next tier's products assigned.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param mixed $membership
 * @param mixed $renewal_type
 * @return string[][][]
 */
function wicket_ac_memberships_get_product_link_data($membership, $renewal_type)
{
    $late_fee_product_id = '';
    $membership_post_id = $membership['membership']['ID'];
    $next_tier = $membership['membership']['next_tier'];

    if (!empty($membership['late_fee_product_id']) && $membership['late_fee_product_id'] > 0) {
        $late_fee_product_id = ',' . $membership['late_fee_product_id'];
    }

    $next_tier = apply_filters('wicket_renewal_filter_product_data', $next_tier);

    foreach ($next_tier['product_data'] as $product_data) {
        $button_label = $membership['callout']['button_label'];
        if (
            !empty($membership['membership']['meta']['org_seats'])
            && $membership['membership']['meta']['org_seats'] > 0
            && $membership['membership']['meta']['org_seats'] != $product_data['max_seats']
        ) {
            continue;
        }
      
        $product = wc_get_product($product_data['variation_id']);
        if (empty($product)) {
            $product = wc_get_product($product_data['product_id']);
        }
      
        $button_label .= ' (' . $product->get_name() . ')';
        $product_id = $product->get_id();
        $link_url = '/cart/?membership_post_id_renew=' . $membership_post_id . '&add-to-cart=' . $product_id . $late_fee_product_id . '&quantity=1';
                $link['link'] = [
            'title' => $button_label,
            'url'   => $link_url,
        ];
        $link['link']['target'] = '';
        $link['link_style'] = '';    
        $links[] = $link;
        if (!empty($specific_renewal_product)) {
            break;
        }
    }

    return $links;
}

function wicket_ac_memberships_get_subscription_renewal_link_data($membership) {
  $url = $membership['membership']['subscription_renewal']['permalink'];
  $parsed_url = wp_parse_url( $url );
  $query_string = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
  $checkout_url = wc_get_checkout_url();
  //$url = trailingslashit( $checkout_url ) . $query_string;
  $button_label = $membership['callout']['button_label'];
  $link['link'] = [
      'title' => $button_label,
      'url' => $url,
  ];
  $link['link']['target'] = '';
  $link['link_style'] = '';    
  $links[] = $link;
  return $links;
}


/**
 * Get page link data for memberships.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param mixed $membership
 * @return array
 */

function wicket_ac_memberships_get_page_link_data($membership)
{
    $url = $membership['membership']['form_page']['permalink'] . '?membership_post_id_renew=' . $membership['membership']['ID'];
    if (!empty($membership['late_fee_product_id'])) {
        $url .= '&late_fee_product_id=' . $membership['late_fee_product_id'];
    }
    if (!empty($_ENV['WICKET_MEMBERSHIPS_DEBUG_RENEW'])) {
        $url .= '&process_renewal=1';
    }
    $button_label = $membership['callout']['button_label'];
    $link['link'] = [
        'title' => $button_label,
        'url'   => $url,
    ];
    $link['link']['target'] = '';
    $link['link_style'] = '';    
    $links[] = $link;

    return $links;
}

/**
 * Alter the 'pages' admin settings to provide the wicket ACC pages along with normal pages.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param string $output
 * @param array $parsed_args
 * @param array $pages
 * @return string $output
 */
function wicket_acc_alter_wp_job_manager_pages($output, $parsed_args, $pages)
{
    // Check if Job Manager is active
    if (!class_exists('WP_Job_Manager')) {
        return $output;
    }

    if (in_array($parsed_args['name'], ['job_manager_submit_job_form_page_id', 'job_manager_job_dashboard_page_id', 'job_manager_jobs_page_id', 'job_manager_terms_and_conditions_page_id'])) {
        $parsed_args['post_type'] = ['my-account', 'page'];
        $output = wp_job_dropdown_pages($parsed_args);

        return $output;
    } else {
        return $output;
    }
}

/**
 * Dropdown pages for the wicket ACC Job Manager.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param array $parsed_args
 * @return string $output
 */
function wp_job_dropdown_pages($parsed_args = '')
{
    $pages = get_posts(['post_type' => $parsed_args['post_type'], 'numberposts' => -1]);
    $output = '';

    if (!empty($pages)) {
        $output = "<select name='" . esc_attr($parsed_args['name']) . "'" . " id='" . esc_attr($parsed_args['id']) . "'>\n";
        if ($parsed_args['show_option_no_change']) {
            $output .= "\t<option value=\"-1\">" . $parsed_args['show_option_no_change'] . "</option>\n";
        }
        if ($parsed_args['show_option_none']) {
            $output .= "\t<option value=\"" . esc_attr($parsed_args['option_none_value']) . '">' . $parsed_args['show_option_none'] . "</option>\n";
        }
        $output .= walk_page_dropdown_tree($pages, $parsed_args['depth'], $parsed_args);
        $output .= "</select>\n";
    }

    return $output;
}

/**
 * Get user profile picture
 * To pull in as wp_user profile image.
 *
 * @deprecated 1.5.0 Pending reimplementation as a method
 * @param mixed $user WP_User object, user ID or email
 * @param array $args (Optional)
 * @return string URL to the profile picture
 */
function wicket_acc_get_avatar($user, $args = [])
{
    $extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $uploads_dir = wp_get_upload_dir();
    $uploads_url = $uploads_dir['baseurl'] . '/wicket-profile-pictures';
    $default_avatar = WICKET_ACC_URL . '/dist/assets/images/profile-picture-default.svg';

    // Get the user ID
    if (is_numeric($user)) {
        $current_user_id = $user;
    } elseif (is_email($user)) {
        $user = get_user_by('email', $user);
        $current_user_id = $user->ID;
    } elseif ($user instanceof WP_User) {
        $current_user_id = $user->ID;
    } else {
        // Can we get the current user id directly?
        $current_user_id = get_current_user_id();
    }

    if (!$current_user_id) {
        // Filter URL (for child themes to manipulate) and return
        return apply_filters('wicket/acc/get_avatar', $default_avatar);
    }

    // Search for the image
    foreach ($extensions as $ext) {
        $file_path = $uploads_url . '/' . $current_user_id . '.' . $ext;

        if (file_exists($file_path)) {
            // Found it!
            $pp_profile_picture = $uploads_url . '/' . $current_user_id . '.' . $ext;
            break;
        }
    }

    // Still no image? Return the default svg
    if (empty($pp_profile_picture)) {
        $pp_profile_picture = $default_avatar;
    }

    // Filter URL (for child themes to manipulate) and return
    return apply_filters('wicket/acc/get_avatar', $pp_profile_picture);
}

/*
 * MARK: PLEASE READ.
 *
 * Don't add any new functions to this file.
 * Don't add any new functions to this file.
 * Don't add any new functions to this file.
 * Don't add any new functions to this file.
 * Don't add any new functions to this file.
 * Don't add any new functions to this file.
 * Don't add any new functions to this file.
 *
 * Thank you.
 */
