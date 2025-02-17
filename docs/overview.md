# Wicket's ACC (Account Centre) Plugin

## Purpose
The Account Centre (ACC) plugin serves as a bridge between WordPress and the Member Data Platform (MDP), providing frontend blocks and functionality for organization management and member data access.

# Technical Architecture

## Stack Components
- Advanced Custom Fields Pro: Block creation and management
- TailwindCSS: Styling and component design. Read and re-use CSS variables in /uploads/wicket-theme/css/theme-variables.css for custom styling
- HTMX: Dynamic UI updates and API interactions
- Hyperscript: Enhanced client-side behaviors
- MDP API: Data source integration

## Security
- WordPress capabilities mapping to MDP roles
- WordPress nonce validation
- API token management
- REQUEST (GET/POST) data validation and sanitization according to WordPress standards

## Technical Stack
- WordPress Core
- Advanced Custom Fields Pro (ACF)
- TailwindCSS for styling
- HTMX for dynamic interactions
- Hyperscript for enhanced interactivity

