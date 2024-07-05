<?php

/**
 * Returns active memberships from wicket API
 *
 * @return array $memberships (slug and id)
 */

 function wicket_get_active_memberships( $iso_code = 'en' ) {
  $all_summaries = [];
  $membership_summary = [];

  $plan_lookup_slugs = [];
  $plan_lookup_ids = [];

  $wicket_memberships = wicket_get_current_person_memberships();

  if ($wicket_memberships) {
    $helper = new \Wicket\ResponseHelper($wicket_memberships);

    foreach ($helper->data as $entry) {
      $membership_tier = $helper->getIncludedRelationship($entry, 'membership');
      if (!$membership_tier) continue;
      if ($entry['attributes']['status'] != 'Active') continue;
      $entry_summary = [
        'membership_category' => $entry['attributes']['membership_category'],
        'starts_at' => $entry['attributes']['starts_at'],
        'ends_at' => $entry['attributes']['ends_at'],
        'name' => $membership_tier['attributes']['name_' . $iso_code],
        'type' => $membership_tier['attributes']['type']
      ];

      $membership_summary[] = $entry_summary;
    }
  }

  return $membership_summary;
}


function woo_get_active_memberships() {

  $membership_summary = null;

  $args = array(
    'status' => array('active', 'complimentary'),
  );

  if (function_exists('wc_memberships_get_user_memberships')) {
    $memberships = wc_memberships_get_user_memberships(get_current_user_id(), $args);

    foreach($memberships as $membership){
      $entry_summary = [
        'starts_at' => $membership->get_start_date(),
        'ends_at' => $membership->get_end_date(),
        'name' => $membership->plan->name
      ];

      $membership_summary[] = $entry_summary;
    }
  }

  return $membership_summary;
}


function wicket_get_active_memberships_relationship() {
  $person_type = '';
  $wicket_memberships = wicket_get_current_person_memberships();
  $person_uuid = wicket_current_person_uuid();
  $org_info = [];

  if($wicket_memberships){
    foreach($wicket_memberships['included'] as $included){
      if($included['type'] !== 'organizations') continue;
      $org_uuid = (isset($included['id'])) ? $included['id'] : '';
      $org_connections = wicket_get_org_connections_by_id($org_uuid);
      $org_info['name'] = (isset($included['attributes']['legal_name'])) ? $included['attributes']['legal_name'] : '';
      
      if($org_connections){
        foreach($org_connections['data'] as $org_included){
          $person_to_org_uuid = (isset($org_included['relationships']['person']['data']['id'])) ? $org_included['relationships']['person']['data']['id'] : '';
          if($person_to_org_uuid == $person_uuid){
            $person_type = (isset($org_included['attributes']['type'])) ? $org_included['attributes']['type'] : '';
          }
        }
      }
    }
  }

  $person_type = str_replace("-", " ", $person_type);
  $org_info['relationship'] = ucwords($person_type);
  
  return $org_info;
}

function is_renewal_period($memberships, $renewal_period){
  $memberships_to_renew = [];
	$today = date('Y-m-d');

  if($memberships){
    foreach($memberships as $membership) {
      $renewal_window = strtotime('-'.$renewal_period.' days', strtotime($membership['ends_at']));
      if(strtotime($today) >= $renewal_window) {
        $memberships_to_renew = $membership;
      }
    }
  }

  return $memberships_to_renew;
}

function wicket_profile_widget_validation( $fields = [] ){

  $person = wicket_current_person();
  $results = [];


  if($fields && $person){
    foreach($fields as $field){
      if($field == 'addresses'){
        $results[] = wicket_validation_addresses( $person );
      } else {
        $results[] = (!is_null($person->$field)) ? true : '';
      }
    }
  }

  if(count(array_unique($results)) === 1){
    return false;
  }

  return true;

}

function wicket_validation_addresses( $person ){

  $addresses = [];
  $country_name = '';
  $city = '';
  $address1 = '';
  $zip_code = '';

  if($person){
    if($person->relationship('addresses')){
      foreach ($person->relationship('addresses') as $relationship) {
        foreach ($person->included() as $included) {
          if ($relationship->id == $included['id']) {
            $addresses[] = $included;
          }
        }
      }
    }
    
    if($addresses) {
      foreach ($addresses as $address) {
        if($address['attributes']['primary'] == 1){
          $country_name = $address['attributes']["country_name"];
          $city = $address['attributes']["city"];
          $address1 = $address['attributes']["address1"];
          $zip_code = ($country_name == 'United States' || $country_name == 'Canada') ? $address['attributes']["zip_code"] : 'pass';
        }
      }
    }
  }
 

  if($country_name && $city && $address1 && $zip_code){
    return true;
  }

  return false;
}

class wicket_acc_menu_walker extends Walker_Nav_Menu {
  private $current_item;
  private $submenu_order = 0;
  private $classes_end_level;

  function start_el(&$output, $item, $depth=0, $args=array(), $id = 0) {
    $object = $item->object;
    $type = $item->type;
    $title = $item->title;
    $permalink = $item->url;
    $classes = $item->classes;
    $target = $item->target;
    $this->current_item = $item;

   $output .= "<li class='" .  implode(" ", $classes) . "'>";

    // if item has children, replace a link for a button toggle
    if( in_array('menu-item-has-children', $classes) && $depth == 0) {
      $this->submenu_order = 0;
      $output .= "<button id='dropdown-{$item->ID}-control' class='nav__menu-link dropdown__toggle--menu' aria-expanded='false' aria-controls='dropdown-{$item->ID}'>";
      $output .= $title;
    }
    elseif (isset($permalink)) {
      if($target == '_blank') {
        $output .= '<a  target="_blank" rel="noopener" href="' . $permalink . '">' . $title;
        $output .= '<i class="fas fa-external-link" aria-hidden="true"></i> <span class="webaim-hidden">Opens in a new window</span>';
      } else {
        $output .= '<a href="' . $permalink . '">' . $title;
      }
    }

    // close button or <a> item
    if( in_array('menu-item-has-children', $classes) && $depth == 0 ) {
      $output .= '<i class="fa-solid fa-caret-down" aria-hidden="true"></i> </button>';
    } elseif (isset($permalink)) {
      $output .= '</a>';
    }
  }

  function start_lvl( &$output, $depth = 0, $args = array() ) {
    $current_item_id = $this->current_item->ID;
    $current_item_classes = $this->current_item->classes;
    $this->classes_end_level = $current_item_classes;
    $indent = str_repeat("\t", $depth);

    // if item has children, replace ul sub-menu with dropdown
    if( in_array('menu-item-has-children', $current_item_classes) && $depth == 0 ) {
      $output .= "\n$indent<div id='dropdown-{$current_item_id}' class='nav__submenu' aria-expanded='false' aria-labelledby='dropdown-{$current_item_id}-control' role='region'><ul class='sub-menu'>\n";
    }
    else {
      $output .= "\n$indent<ul class='sub-menu'>\n";
    }
  }

  function end_lvl( &$output, $depth = 0, $args = array() ) {
    $current_item_classes = $this->classes_end_level;
    $indent = str_repeat("\t", $depth);
    if( in_array('menu-item-has-children', $current_item_classes) && $depth == 0 ) {
      $output .= "$indent</ul></div>\n";
    }
    else {
      $output .= "$indent</ul>\n";
    }
  }

}

class wicket_acc_menu_mobile_walker extends Walker_Nav_Menu {
  private $current_item;
  private $submenu_order = 0;
  private $classes_end_level;

  function start_el(&$output, $item, $depth=0, $args=array(), $id = 0) {
    $object = $item->object;
    $type = $item->type;
    $title = $item->title;
    $permalink = $item->url;
    $classes = $item->classes;
    $target = $item->target;
    $this->current_item = $item;

   $output .= "<li class='" .  implode(" ", $classes) . "'>";

    // if item has children, replace a link for a button toggle
    if( in_array('menu-item-has-children', $classes) && $depth == 0) {
      $this->submenu_order = 0;
      $output .= "<button id='mobile-dropdown-{$item->ID}-control' class='nav__menu-link dropdown__toggle--menu' aria-expanded='false' aria-controls='mobile-dropdown-{$item->ID}'>";
      $output .= $title;
    }
    elseif (isset($permalink)) {
      if($target == '_blank') {
        $output .= '<a  target="_blank" rel="noopener" href="' . $permalink . '">' . $title;
        $output .= '<i class="fas fa-external-link" aria-hidden="true"></i> <span class="webaim-hidden">Opens in a new window</span>';
      } else {
        $output .= '<a href="' . $permalink . '">' . $title;
      }
    }

    // close button or <a> item
    if( in_array('menu-item-has-children', $classes) && $depth == 0 ) {
      $output .= '<i class="fa-solid fa-caret-down" aria-hidden="true"></i> </button>';
    } elseif (isset($permalink)) {
      $output .= '</a>';
    }
  }

  function start_lvl( &$output, $depth = 0, $args = array() ) {
    $current_item_id = $this->current_item->ID;
    $current_item_classes = $this->current_item->classes;
    $this->classes_end_level = $current_item_classes;
    $indent = str_repeat("\t", $depth);

    // if item has children, replace ul sub-menu with dropdown
    if( in_array('menu-item-has-children', $current_item_classes) && $depth == 0 ) {
      $output .= "\n$indent<div id='mobile-dropdown-{$current_item_id}' class='nav__submenu' aria-expanded='false' aria-labelledby='mobile-dropdown-{$current_item_id}-control' role='region'><ul class='sub-menu'>\n";
    }
    else {
      $output .= "\n$indent<ul class='sub-menu'>\n";
    }
  }

  function end_lvl( &$output, $depth = 0, $args = array() ) {
    $current_item_classes = $this->classes_end_level;
    $indent = str_repeat("\t", $depth);
    if( in_array('menu-item-has-children', $current_item_classes) && $depth == 0 ) {
      $output .= "$indent</ul></div>\n";
    }
    else {
      $output .= "$indent</ul>\n";
    }
  }

}

function wicket_ac_maybe_add_multiple_products_to_cart() {
if ( ! class_exists( 'WC_Form_Handler' ) || empty( $_REQUEST['add-to-cart'] ) ) {
    return;
}
remove_action( 'wp_loaded', array( 'WC_Form_Handler', 'add_to_cart_action' ), 20 );

$product_ids = explode( ',', $_REQUEST['add-to-cart'] );
$count       = count( $product_ids );
$number      = 0;
$url = get_permalink( $_REQUEST['page_id'] );

foreach ( $product_ids as $product_id ) {
    if ( ++$number === $count ) {
        $_REQUEST['add-to-cart'] = $product_id;
        return WC_Form_Handler::add_to_cart_action( $url );
    }

    $product_id = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $product_id ) );
    $adding_to_cart = wc_get_product( $product_id );

    if ( ! $adding_to_cart ) {
        continue;
    }

    $add_to_cart_handler = apply_filters( 'woocommerce_add_to_cart_handler', $adding_to_cart->product_type, $adding_to_cart );

    if ( 'simple' !== $add_to_cart_handler ) {
        continue;
    }

    $quantity = empty( $_REQUEST['quantity'] ) ? 1 : wc_stock_amount( $_REQUEST['quantity'] );
    $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );

    if ( $passed_validation && false !== WC()->cart->add_to_cart( $product_id, $quantity ) ) {
        wc_add_to_cart_message( array( $product_id => $quantity ), true );
    }
  }
}

add_action( 'wp_loaded', 'wicket_ac_maybe_add_multiple_products_to_cart', 15 );

function wicket_ac_memberships_get_product_link_data( $membership ) {
  $late_fee_product_id = '';
  $next_tier = $membership['membership']['next_tier'];
  $button_label = $membership['callout']['button_label'];
  if( !empty($membership['late_fee_product_id']) ) {
    $late_fee_product_id = ',' . $membership['late_fee_product_id'];
  }
  foreach( $next_tier['product_data'] as $product_data ) {
    $product = wc_get_product( $product_data['product_id'] );
    if( $product->get_type() == 'variable' || $product->get_type() == 'variable-subscription' ){
      $variable_products = $product->get_children();
      foreach( $variable_products as $variable_product ) {
        if( ! empty( $variable_product['ID']) ) {
          $variable_product = $variable_product['ID'];
        }
        $product = wc_get_product( $variable_product );
        $link['link'] = [
          'title' => $product->get_name(),
          'url' => '/cart/?add-to-cart=' . $variable_product . $late_fee_product_id . '&quantity=1'
        ];      
        $links[] = $link;
      }
    } else {
      $link['link'] = [
        'title' => $button_label,
        'url' => '/cart/?add-to-cart=' . $product_data['product_id'] . $late_fee_product_id . '&quantity=1'
      ];    
      $links[] = $link;
    }
  }
  return $links;
}

function wicket_ac_memberships_get_page_link_data( $membership ) {
  if( !empty($membership['late_fee_product_id']) ) {
    $url = '/?page_id=' . $membership['membership']['form_page']['page_id'] . '&add-to-cart=' . $membership['late_fee_product_id'];
  } else {
    $url = $membership['membership']['form_page']['permalink'];
  }
  $button_label = $membership['callout']['button_label'];
  $link['link'] = [
    'title' => $button_label,
    'url' => $url
  ];    
  $links[] = $link;
  return $links;
}