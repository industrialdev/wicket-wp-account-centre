# Wicket Account Centre for WordPress

## Description

This official Wicket plugin includes the Account Centre blocks and pages for WooCommerce and Wicket member data.

## Development

### Requirements

- WSL2 on Windows, or Linux/macOS with Bash 5.x or greater (ZSH is also compatible). On macOS, ensure Bash is up to date, even if you're using ZSH. Use [Homebrew](https://formulae.brew.sh/formula/bash) to update Bash if needed.
- [Composer](https://getcomposer.org/).
- [EditorConfig](https://editorconfig.org/) installed in your code editor.
- [Strauss](https://github.com/BrianHenryIE/strauss/).
- (Optional) [PHP CS Fixer](https://marketplace.visualstudio.com/items?itemName=junstyle.php-cs-fixer) extension for VSCode, or the equivalent for your editor of choice (e.g., [Sublime Text](https://packagecontrol.io/packages/PHP%20CS%20Fixer)). Having this extension installed allows PHP-CS-Fixer to run on file save, so your code is formatted automatically without needing to wait for a git commit to trigger the formatting.

Repository contains pre-compiled PHP binaries for Windows, Linux, and macOS, so devs can use PHP-CS-Fixer without having to install PHP in their own machine. Binaries provided by [static-php-cli](https://static-php.dev/).

You can run the command `composer cs:fix` from the root of the repository to run PHP-CS-Fixer with the embedded PHP binary.

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

When tested and ready, put your relevant changes into the `CHANGELOG.md` file, only when they are mayor changes, new features, or breaking changes. Use a new version number or update a previous one already present in the file. Your choice.

If you added new libraries through Composer or classes on `src/`, run `composer production` to update the autoloader.

Then bump the plugin version number running `composer version-bump`.

Commit and push.

### ⚠️ IMPORTANT: Before Tagging a New Version

**Always run `composer production` before tagging a new version.** This command:
- Removes development dependencies
- Optimizes autoloader for production
- Generates a clean build without dev packages

```bash
composer production
```

Without this step, the plugin will include unnecessary dev dependencies in the release.

### Running Tests

The plugin uses **PEST** and **PHPUnit** for testing.

```bash
# Run all tests
composer test

# Run unit tests only
composer test:unit

# Run tests with coverage report
composer test:coverage

# Run browser tests (requires local WordPress instance with Wicket Docker setup)
composer test:browser

# Run specific test file
./vendor/bin/pest tests/unit/WicketAccTest.php
```

**Note:** Browser tests require a local WordPress instance running with the Wicket Docker setup. Ensure you have a site (e.g., PACE or any other) running locally before executing browser tests.

**Playwright Setup:** Install Playwright globally if desired: `npm install -g playwright@latest && npx playwright install`

### Writing New Tests

1. **Create test file** in `tests/unit/` with pattern `*Test.php`
2. **Extend AbstractTestCase** for WordPress function mocking

```php
<?php

declare(strict_types=1);

namespace WicketAcc\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketAcc\WicketAcc;

#[CoversClass(WicketAcc::class)]
class MyNewTest extends AbstractTestCase
{
    private WicketAcc $wicket_acc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wicket_acc = WicketAcc::get_instance();
    }

    public function test_something(): void
    {
        $this->assertTrue(true);
    }
}
```

3. **Use Brain Monkey** to mock WordPress functions:

```php
\Brain\Monkey\Functions\stubs([
    'get_option' => 'value',
    'get_current_user_id' => 1,
    'WACC' => new class {
        public function getAttachmentUrlFromOption() {
            return '';
        }
    },
]);
```

4. **Run tests** - PHPUnit auto-discovers test files matching `*Test.php`

### Test Structure

```
tests/
├── bootstrap.php              # PHPUnit bootstrap with WordPress mocks
└── unit/
    ├── AbstractTestCase.php   # Base test class with Brain Monkey setup
    ├── WicketAccTest.php      # Main class tests
    ├── ProfileTest.php        # Profile service tests
    ├── ConstantsTest.php      # Plugin constants tests
    ├── OrganizationManagementTest.php
    ├── OrganizationProfileTest.php
    ├── OrganizationRosterTest.php
    ├── MdpInitTest.php
    ├── RouterTest.php
    ├── SettingsTest.php
    ├── WooCommerceTest.php
    └── BlocksTest.php
```

### Code Style

```bash
# Check code style
composer lint

# Fix code style automatically
composer format
```

### Available Composer Scripts

```bash
composer production       # Build for production (remove dev deps, optimize autoload)
composer test            # Run all tests
composer test:unit       # Run unit tests only
composer test:coverage   # Run tests with HTML coverage report
composer test:browser    # Run browser tests
composer lint            # Check code style
composer format          # Fix code style
composer check           # Run lint + test
composer version-bump    # Bump plugin version
```

# Documentation

Check this plugin documentation [here](docs/).
