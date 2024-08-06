<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/**
 * Available $args[] variables:
 *
 * organization_id - Organization UUID
 * organization_info - Organization info
 * organization_memberships - Organization memberships
 */
?>
<section id="content" class="wicket-acc-organization-management section page-default">

	<div class="wicket-welcome-block bg-light-010 rounded-100 p-4 mb-4">
		<h2 class='font-bold text-lg black_header'>
			<?php echo $args['organization_info']['org_name']; ?>
		</h2>

		<?php if (!empty($args['organization_info']['org_address'])) : ?>
			<p class='formatted_address_label mb-4'>
				<?php echo $args['organization_info']['org_address']['formatted_address_label']; ?>
			</p>

			<p class="email_address mb-4">
			<h5 class="font-bold"><?php _e('Email Address', 'wicket') ?></h5>
			<?php echo $args['organization_info']['org_email']['address']; ?>
			</p>

			<p class="phone_number mb-4">
			<h5 class="font-bold"><?php _e('Phone Number', 'wicket') ?></h5>
			<?php echo $args['organization_info']['org_phone']['number_international_format']; ?>
			</p>
		<?php endif; ?>
	</div>

	<hr aria-hidden="true">

	<?php
	if (empty($args['organization_memberships'])) {
	?>
		<p><?php _e('No memberships found', 'wicket'); ?></p>
	<?php
	}
	?>


	echo "<table class='team_assignment_table mb-5'>";
		echo "<thead>";
			echo "<th>" . __('Membership Tier', 'wicket') . "</th>";
			echo "<th>" . __('Number of Assigned People', 'wicket') . "</th>";
			echo "<th></th>";
			echo "</thead>";

		foreach ($org_memberships as $org_mship) {
		//var_dump($org_mship);
		//die();

		$org_mship_uuid = $org_mship['membership']['id'];
		$membership_uuid = $org_mship['membership']['id'];
		$included_uuid = $org_mship['included']['id'];

		$active_assignments = $org_mship['membership']['attributes']['active_assignments_count'];
		$max_assignments = $org_mship['membership']['attributes']['max_assignments'];
		$max_assignments = $max_assignments ?? __('Unlimited', 'wicket');
		$starts_at = $org_mship['membership']['attributes']['starts_at'];
		$ends_at = $org_mship['membership']['attributes']['ends_at'];

		echo "<tr>";
			echo "<td>";
				echo "<strong>" . $org_mship['included']['attributes']['name_' . $lang] . "</strong>";
				$date = date('F j, Y', strtotime($ends_at));
				$expiry = $ends_at != '' ? __('Expires') . ' ' . $date : '';
				echo "<br>" . $expiry;
				echo "</td>";
			echo "<td class='fw-bold'>";
				echo $active_assignments . ' / ' . $max_assignments . " " . __('Seats', 'wicket');
				echo "</td>";
			echo "<td>";

				// Proper get link: organization-members
				$org_members_url = untrailingslashit(get_permalink(get_page_by_path($parent_page_slug . '/organization-members')));

				// Link: organization-roster
				$org_roster_url = untrailingslashit(get_permalink(get_page_by_path($parent_page_slug . '/organization-roster')));

				echo "<a class='primary_link_color underline_link' href='$org_members_url/?org_id=$org_id&membership_id=$membership_uuid&included_id=$included_uuid'>" . __('Manage Members', 'wicket') . "</a>";

				echo '<br />';
				// Manage Roster

				echo "<a class='primary_link_color underline_link' href='$org_roster_url/?org_id=$org_id'>" . __('Manage Employees', 'wicket') . "</a>";

				echo "</td>";
			echo "</tr>";
		}
		echo "</table>";
	?>

	<?php
	if ($org_uuid) {
	?>
		echo "<h2 class='primary_link_color'>" . __('Choose an Organization:', 'wicket') . "</h2>";
		echo "<ul>";
			// lookup org details based on UUID found on the role
			foreach ($org_ids as $org_uuid) {
			$organization = $client->get("organizations/$org_uuid");
			echo "<li>";
				echo "<a class='primary_link_color' href='" . home_url(add_query_arg(array(), $wp->request)) . "?org_id=$org_uuid'>";
					echo $organization['data']['attributes']['legal_name_' . $lang];
					echo "</a>";
				echo "</li>";
			}
			echo '</ul>';
	<?php
	} else {
	?>
		<p><?php _e('You currently have no organizations to manage members for.', 'wicket'); ?></p>
	<?php
	}
	?>

</section>
