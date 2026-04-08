# Organization Management Overview

## Overview
The Organization Management (or Roster Management) module provides a suite of features for managing organizational data, member relationships, and subsidiary hierarchies directly within WordPress. It acts as a frontend for the Wicket Member Data Platform (MDP).

## Core Features

### 1. Organization Roster (Members)
- **Members List**: View and filter all members associated with an organization.
- **Member Addition**: Invite or add new members with specific roles.
- **Role Management**: Assign or revoke roles (e.g., Administrator, Member) for individuals.
- **Status Tracking**: Monitor membership status and active relationships.

### 2. Profile Management
- **Organization Profile View**: Detailed, read-only display of organization metadata.
- **Profile Editing**: Dynamic form-based editing for organization details (name, type, status).
- **Business Information**: Specialized management for tax IDs, business numbers, and classifications.

### 3. Document Management
- Upload and manage organization-specific documents.
- Permission-based access to sensitive files.
- Integration with WordPress media library for file handling.

### 4. Hierarchical Management
- **Subsidiaries**: View and manage parent-child relationships between organizations.
- **Organization Selector**: A global shortcode/component allowing users to switch context between different organizations they manage.

## Technical Architecture

### Service Classes
- `WACC()->OrganizationManagement()`: Core business logic for organization operations.
- `WACC()->OrganizationRoster()`: Logic specifically for member management.
- `WACC()->Mdp()->Organization()`: API wrapper for MDP organization endpoints.

### Dynamic Interaction (Datastar)
The organization management interface is built using **Datastar** for real-time, interactive updates. Actions like searching, filtering, and role changes are performed without full page reloads.

### Access Control
Permissions are determined by:
- **WordPress Capabilities**: Standard WP role checks.
- **MDP Roles**: Roles assigned to the person-organization relationship in the MDP (e.g., "Organization Editor").
- **Global Settings**: Affiliation modes (Direct, Cascade, Group) configured in the **ACC Options**.

## Documentation Links
- [ACC Options (Settings)](./acc-options.md)
- [Organization Profile View](./organization-profile-view.md)
- [Organization Profile Edit](./organization-profile-edit.md)
- [Organization Selector Shortcode](./organization-selector-shortcode.md)
