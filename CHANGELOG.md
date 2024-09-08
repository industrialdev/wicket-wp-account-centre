# Wicket Account Centre Changelog
Previously known as My Account Page Editor

# 1.3.2 / 2024-09-07
- Re-worked the entire ACC "router" logic. Now it follows a more standard WordPress routing approach, without use of custom rewrite rules, introducing less bugs, and preventing conflicts with other plugins, like WPML. Also: it's more efficient. Speedier.

# 1.2.20 / 2024-09-04
- Revamped the entire /account-centre/ rewrites from before, that were only hijacking parts of the WooCommerce my-account page. Now the new implementation does not interfere with WordPress rewrite rules. This was causing issues with 3rd party plugins, like WPML, that simply broke because they weren't able to determine the proper URL for a page.
- Fixed WPML compatibility issues with ACC pages.
- Added ACC pages into the default WordPress menu editor.
- Added Shortcuts to the ACC Menu on the backend, for easy access to: Menu Editor, WooCommerce Endpoints. Can be extended with new shortcuts in the future.

# 1.1.56 / 2024-09-02
- Switch display Pending Approval Callout to swap out the Become A Member Callout and remove from Renewal Callout
- Added options to Touchpoints Blocks to show/hide View More Events link and to display an optional Title.
- Removed leftovers from old profile picture implementation.
- Fixed Welcome Block link when using WPML.

# 1.1.50 / 2024-08-29
- Added a new option to change the spelling of Centre/Center in the ACC. Option is linked with previous option available at base theme level. Both read/write to the same DB field.

# 1.1.36 / 2024-08-21
- Fixes an issue where a malformed image could trigger a fatal error on the Picture Profile Change block.
- Fixes ID placement and spacing of items on the Welcome block.

# 1.1.31 / 2024-08-19
- Applied Wicket language style to block: Welcome.
- Fixed and issue where ACC blocks were loading stylings twice. WP already handles styles and scripts inclusion for blocks defined in block.json file.
- Fixed responsive issues on the Welcome block.

# 1.1.20 / 2024-08-16
- Additional info block: Resources overrides can be activated on an annual date range, Org UUIDs can be set dynamically with URL param, can tell AIs to show required star, and Slug Support.
- Fixed an issue for additional ACC pages not loading navigation menu. Pages that aren't default WooCommerce pages, weren't being considered on the previous method of template override. Now, every page inside ACC is considered. A default template is provided for all pages: page-wicket-acc.php
- Added option to set a default profile picture for the user at the ACC options. So sites can have a different default profile picture deppending on their needs.

# 1.1.16 / 2024-08-14
- Added option to hide the "Additional Info" section from the Org Profile block. To be used when the Additional Info block is used by itself with a custom schema.

# 1.1.14 / 2024-08-13
- Fixed an issue where the Touchpoints Events Calendar block wasn't showing events.

## 1.1.13 / 2024-08-07
- Implemented a way to keep but disable any block (for devs). Add an underscore to the block folder name to disable it.
- Fixed an issue related to ACC assuming WPML was always installed and active.

## 1.1.12 / 2024-08-02
- Removed page for Locations, Banners and Subsidiaries from ACC options.
- When saving ACC options, flush rewrite rules once.
- Fixed loading of ACC pages (CPT wicket_acc).
- Improved method to override CPT wicket_acc URLs.
- Fixed WP menu not showing on the ACC pages, because of missing default value for the sidebar location.
- Fixed an issue with the welcome block not showing MDP ID on the front end when the option was enabled.
- Fixed an issue with ACC plugin not overriding the default WP avatar behavior properly.
- Fixed an issue where ACC wasn't using the correct spelling of the ACC, based on the main base plugin option.
- Fixed an issue where the Touchpoints Events Calendar block wasn't showing events.
- Fixed an issue where the Touchpoints Events Calendar block wasn't displaying links for upcoming and past events.
- Fixed an issue where the Touchpoints Events Calendar block wasn't aligning navigation links properly.
- Fixed an issue where the Touchpoints Events Calendar block wasn't properly getting the configured number of results and type of events to display.

## 1.1.6 / 2024-08-01
- New block: Touchpoints MicroSpec. Can also show single event data on a page.
- Added instructions on how to use WACC() helpers, and how to extend it.
- Fixed the exporting of json files from ACF field groups. Caused by a dual issue with the plugin and the docker container and his permissions to write to the file system. New set of json files are now cleanly generated and available for syncing on clients sites via ACF settings.

## 1.1.3 / 2024-07-30
- Proper namespace for the plugin.
- Proper template interception/overriding for child themes.

## 1.1.1 / 2024-07-29
- Added user MDP ID into Welcome Block.
- Tested and released the new WACC() helper function.

## 1.1.0 / 2024-07-26
- New Block: Touchpoints MicroSpec.
- Added functionality to load block templates from child theme to be overridden by theme devs, using same logic as WooCommerce. Copy files from /templates-wicket/ to your theme/child theme /templates-wicket/ folder and edit as needed.
- Added a global WACC() function to access the plugin's functionality, without polluting the global namespace with more helper functions
- Fixed an issue in AC plugin that was improperly doing a https://developer.wordpress.org/reference/functions/flush_rewrite_rules/ on EVERY WP page load.
- Profile Picture Block: Added ability to remove an uploaded image.

## 1.0.22 / 2024-07-22
- New Block: Profile Picture Change.
- Reordered Touchpoints Block.

## 1.0.21 / 2024-07-18
- Add WPML Multilingual Compatibility

## 1.0.16 / 2024-06-26
- Fix: Container and menu style updates

## 1.0.14 / 2024-06-25
- Fix: Expose custom templates

## 1.0.13 / 2024-06-21
- Add: Localization

## 1.0.10 / 2024-06-13
- Add: Membership Plugin Integration

## 1.0.9 / 2024-06-13
- Add: Secondary menu area

## 1.x.x / 2023-09-02
- Forked: Forked plugin for Wicket Account Centre

## 1.3.2 / 2023-09-01
- Fix: Fixed file upload security issue

## 1.3.1 / 2023-08-28
- Fix: Fixed layout issues on frontend

## 1.3.0 / 2023-08-24
- Update: Security update

## 1.2.1 / 2023-08-17
- Fix: Fix typos

## 1.2.0 / 2023-08-16
- Update: Compatible with WooCommerce High-Performance Order Storage (HPOS)
- Update: Compatibility updated for latest versions of WooCommerce and WordPress

## 1.1.4 / 2022-10-24
- Fix: Fixed "Failed Security Check" message issue

## 1.1.3 / 2022-09-21
- Fix: Fixed nonce issues
- Fix: Fixed JS issue in admin panel

## 1.1.2 / 2022-09-21
- Update: Compatibility updated for latest versions of WooCommerce and WordPress

## 1.1.1 / 2020-11-24
- Fix: Backend icons preview issue fixed for windows
- Add: Added shortcode compatibility in text editor

## 1.1.0 / 2020-11-23
- Add: Sorting options for default and new endpoints
- Fix: Compatibility fixes with top themes
- Add: Custom background and text colors for endpoint tabs
- Update: Option to use the theme default my account layout or replace with an attractive yet simple plugin's my account design

## 1.0.0 / 2020-07-27
- Other: Initial release of the plugin
