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
        'starts_at' => $entry['attributes']['starts_at'],
        'ends_at' => $entry['attributes']['ends_at'],
        'name' => $membership_tier['attributes']['name']
      ];

      $membership_summary[] = $entry_summary;
    }
  }

  return $membership_summary;
}