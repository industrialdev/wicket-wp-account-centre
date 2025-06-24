# Wicket's ACC (Account Centre) Plugin

## Purpose
The Account Centre (ACC) plugin serves as a bridge between WordPress and the Member Data Platform (MDP), providing frontend blocks and functionality for organization management and member data access.

# Technical Architecture

## Technical Stack
- Advanced Custom Fields Pro: Block creation and management
- TailwindCSS: Styling and component design. Read and re-use CSS variables in /uploads/wicket-theme/css/theme-variables.css for custom styling
- Datastar: Dynamic UI updates and API interactions using Hypermedia
- MDP API: Data source integration

## Code Structure
- **PSR-4 Autoloading**: The plugin uses Composer for dependency management and follows the PSR-4 standard for autoloading classes.
- **Source Directory**: All PHP classes are located in the `src/` directory, organized by namespaces.
- **Main Plugin File**: The entry point is `class-wicket-acc-main.php`, which initializes the plugin and its components.

## Security
- WordPress capabilities mapping to MDP roles
- WordPress nonce validation
- API token management
- REQUEST (GET/POST) data validation and sanitization according to WordPress standards
