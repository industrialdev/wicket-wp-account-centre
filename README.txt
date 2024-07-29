=== Wicket - Account Centre plugin for WordPress ===
Contributors: Wicket-Team
Tags: wicket, account centre
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

This official Wicket plugin includes the Account Centre blocks and pages for WooCommerce and Wicket member data.

1. Create WP Page for Account-Center and add shortcode block with: [woocommerce_my_account]
		- this is the Account Center - Dashboard template. Content can be added that will appear on all the account center pages.
2. Under the Account Center menu you can now add the Dashboard Pages.
3. There are unique Wicket Blocks available for the Account Center pages that are used to manage data in the MDP.
4. The following blocks are available:
	- AC Welcome Block: display the user's active memberships.
	- AC Additional Info: display the user's additional information fields.
	- AC Individual Profile: update the user's profile information.
	- AC Organization Profile: update an organization profile owned by user.
	- AC Callout Block:
			- Become a Member: prompt the user to obtain a membership.
			- Complete your Profile: prompt the user to complete their profile information.
			- Membership Renewal: prompt the user to renew their membership(s).
5. Setup a Menu for the Account Center
	- there are 2 possible menu locations for the account center
	- add pages using Woocommerce Endpoints or as custom url option: /account-center/{ac-page-slug}
	- assign your menu to either Account Center 1st or 2nd menu area
6. Using WPML the Account Center can be localized to reflect languages in the MDP.
	- create your multilingual menus directly in WPML.
