# Wicket Account Centre for WordPress

## Description

This official Wicket plugin includes the Account Centre blocks and pages for WooCommerce and Wicket member data.

## Development

### Requirements

- WSL2 on Windows, or Linux/macOS with Bash 5.x or greater (ZSH is also compatible). On macOS, ensure Bash is up to date, even if you're using ZSH. Use [Homebrew](https://formulae.brew.sh/formula/bash) to update Bash if needed.
- [Composer](https://getcomposer.org/).
- [EditorConfig](https://editorconfig.org/) installed in your code editor.
- (Optional) [PHP CS Fixer](https://marketplace.visualstudio.com/items?itemName=junstyle.php-cs-fixer) extension for VSCode, or the equivalent for your editor of choice (e.g., [Sublime Text](https://packagecontrol.io/packages/PHP%20CS%20Fixer)). Having this extension installed allows PHP-CS-Fixer to run on file save, so your code is formatted automatically without needing to wait for a git commit to trigger the formatting.

Repository contains pre-compiled PHP binaries for Windows, Linux, and macOS, so devs can use PHP-CS-Fixer without having to install PHP in their own machine. Binaries provided by [static-php-cli](https://static-php.dev/).

You can run the command `composer php-format` from the root of the repository to run PHP-CS-Fixer with the embedded PHP binary.

### Setup local dev environment

Clone the repository locally.

It’s highly recommended that you clone this repository into an already configured instance of Wicket’s [WordPress Baseline](https://github.com/industrialdev/wordpress-baseline), so you can work on this plugin live using Docker.

Go to the plugin path and run:

```
composer install
```

Ensure that `wicket-wp-account-centre/includes/acf-json` is writable by Docker on your system (for the user/group that Docker is using). This is necessary to write the ACF field groups JSON files (when they are updated) and version control them.

### Day to day work

Do your work and have fun :)

When tested and ready, put your relevant changes into the `CHANGELOG.md` file. Use a new version number or update a previous one already present in the file. Your choice.

Then bump the plugin version number running `composer version-bump`

Commit and push.

# Documentation

Check this plugin documentation [here](docs/).
