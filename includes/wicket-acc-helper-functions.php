<?php

/**
 * Returns active memberships from wicket API
 *
 * @return array $memberships (slug and id)
 */

 function wicket_get_active_memberships() {
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
        'name' => $membership_tier['attributes']['name'],
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
