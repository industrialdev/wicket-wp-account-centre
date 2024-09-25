# Wicket Account Centre for WordPress

## Description

This official Wicket plugin includes the Account Centre blocks and pages for WooCommerce and Wicket member data.

## Development

### Requeriments

- WSL2 on Windows. Linux/macOS with Bash 5.x or greater (ZSH is compatible too). On macOS, make sure you have Bash up to date (even if using ZSH). Use [Homebrew](https://formulae.brew.sh/formula/bash) to update it.
- [Composer](https://getcomposer.org/).
- [EditorConfig](https://editorconfig.org/) in your code editor.
- (Optional) [PHP Intelephense](https://intelephense.com/) in your code editor. This way, you can auto-format your code as you save a file. Also it has some [useful features](https://github.com/bmewburn/intelephense-docs/blob/master/features.md) that you might like. Check the [Installation Docs](https://github.com/bmewburn/intelephense-docs/blob/master/installation.md) for more details.

### Setup local dev environment

Clone the repository locally.

It's highly recommended that you clone this repository into an already configured instance of Wicket's [WordPress Baseline](https://github.com/industrialdev/wordpress-baseline), so you can work on this plugin live using Docker.

Go to the plugin path and run `composer install`.

Make sure `wicket-wp-account-centre/includes/acf-json` is writable in your system by Docker (whatever user/group is being used to run it). This is needed to write the ACF field groups json files (when they are updated) and version control them.




## Features

### ACC Pages

Inside Account Centre menu in WP, you can create multiple pages that will be available inside the ACC custom post type.

ACC will create the default pages for you and map them in ACC Options. You can change that mapping at any time.

### ACC Blocks

There are unique Wicket Blocks available for the Account Centre pages (or any WP page) that are used to manage and display user/MDP data.

1. ACC Welcome Block: display the user's active memberships.
2. ACC Additional Info: display the user's additional information fields.
3. ACC Individual Profile: update the user's profile information.
4. ACC Organization Profile: update an organization profile owned by user.
5. ACC Callout Block Become a Member: prompt the user to obtain a membership.
6. ACC Invididual Profile: prompt the user to complete their profile information.
7. ACC Membership Renewal: prompt the user to renew their membership(s).
8. ACC Profile Picture Change: allow the user to update their profile picture.
9. ACC Touchpoints MicroSpec: display a list of events (from MicroSpec) and their data.
10. ACC Touchpoints TEC: display a list of events (from The Events Calendar) and their data.
11. ACC Organization Management: display a list of organizations (from the Organization Management plugin) and their members. Allows to manage memberships.
