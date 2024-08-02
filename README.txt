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

## ACC Main Page

The page that hosts the Account-Centre needs to have the shortcode block:

[woocommerce_my_account]

This is the Account Centre main page. Content can be added that will appear on all the account Centre pages.
Keep in mind that WooCommerce could already have created this page. Use that if it's available.

## ACC Pages

Inside Account Centre menu in WP, you can create multiple pages that will be available as child pages of the main ACC page.

ACC will create the default pages for you (and you can see them in ACC Options), but you can also create your own pages for extra information or to display data from other sources.

## ACC Menu

Under Appearance > Menus you can control ACC menu elements.

Assign your menu to either Account Centre 1st or 2nd menu location.

## ACC Blocks

There are unique Wicket Blocks available for the Account Centre pages (or any WP page) that are used to manage and display user/MDP data.

- ACC Welcome Block: display the user's active memberships.
- ACC Additional Info: display the user's additional information fields.
- ACC Individual Profile: update the user's profile information.
- ACC Organization Profile: update an organization profile owned by user.
- ACC Callout Block Become a Member: prompt the user to obtain a membership.
- ACC Callout Complete your Profile: prompt the user to complete their profile information.
- ACC Membership Renewal: prompt the user to renew their membership(s).
- ACC Profile Picture Change: allow the user to update their profile picture.
- ACC Touchpoints MicroSpec: display a list of events (from MicroSpec) and their data.
- ACC Touchpoints TEC: display a list of events (from The Events Calendar) and their data.
