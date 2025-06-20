# Old Organization Management Functions Documentation

This document provides a comprehensive list of functions used for organization management in the Wicket WordPress plugin.

## Core Organization Functions

### Organization Information & Retrieval

### Member Management

- Returns boolean success status

### Role Management

- `all_true`: requires all roles vs any role
- Returns boolean

### Contact Information Management

### Business Information



### Relationship Management

### UI Components

### Utility Functions

### File Storage & Cache

## Important Notes

- Most functions require authentication with MDP (Wicket API)
- Many functions use global variables defined in initialization
- Error handling typically returns false or triggers wp_die()
- Some functions depend on WordPress roles and capabilities
- HTMX is used for dynamic content loading
- Caching is implemented for expensive operations

## Usage Examples

```php


