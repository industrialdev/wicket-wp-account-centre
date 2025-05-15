# Organization Management Implementation Plan

AKA Roster Management

## Architecture Decisions

### Separation of Concerns: Global vs Block Settings

#### Global Settings (ACC Options)
Settings that affect system-wide behavior or are shared between blocks:
- MDP Affiliation Mode (direct/cascade/group)
  - Controls how organization relationships work across the entire system
  - Prevents inconsistent membership models between blocks
- Relationship Types Configuration
  - Visible relationship types list
  - Default relationship type
  - Ensures consistent relationship options
- Membership Manager Permissions
  - Add relationships permission
  - Assign security roles permission
  - Remove members permission
  - Maintains consistent access control
- Group Management Settings
  - Parent/child group settings
  - Hierarchy depth control
  - Applies when in group mode

#### Block Settings (Instance-specific)
Settings that only affect the presentation layer of individual blocks:
- Listing Settings
  - Results per page
  - Search visibility
  - Pagination options
- Display Settings
  - Visible fields configuration
  - Custom messages
  - UI/UX preferences

This separation ensures:
1. Consistent behavior for core functionality
2. Single source of truth for important configurations
3. Flexibility for UI/UX at the block level
4. Prevention of conflicting membership models

## Phase 0: Infrastructure Planning and Setup (Weeks 1-2, +2 days)

### 0.1 Global Configuration Setup
- [ ] Create ACF Field Group for MDP Configuration
  - [ ] Create field group: `acc_mdp_config`
  - [ ] Location Rule: Options Page == ACC Options
  - [ ] Add global MDP affiliation mode setting
    - [ ] Field Key: `field_acc_mdp_affiliation_mode`
    - [ ] Field Type: Select
    - [ ] Choices:
      - [ ] Direct affiliation
      - [ ] Cascade affiliation
      - [ ] Group affiliation
  - [ ] Add relationship configuration
    - [ ] Field Key: `field_acc_mdp_relationship_config`
    - [ ] Field Type: Group
    - [ ] Sub-fields:
      - [ ] Visible relationship types (repeater)
        - [ ] Relationship type (text)
      - [ ] Default relationship type (select)

  - [ ] Add membership manager permissions
    - [ ] Field Key: `field_acc_mdp_manager_permissions`
    - [ ] Field Type: Group
    - [ ] Sub-fields:
      - [ ] Can add relationships (true/false)
      - [ ] Can assign security roles (true/false)
      - [ ] Can remove members (true/false)
        - [ ] Warning message about revoking relationships

  - [ ] Add group management configuration
    - [ ] Field Key: `field_acc_mdp_group_config`
    - [ ] Field Type: Group
    - [ ] Conditional Logic: Show if affiliation_mode == 'group'
    - [ ] Sub-fields:
      - [ ] Parent/child settings
      - [ ] Hierarchy depth control
  - [ ] Save ACF JSON to `/includes/acf-json/`

- [ ] Extend AdminSettings Class
  - [ ] Add migration warnings hook
    - [ ] Hook: `acf/update_value/key=field_acc_mdp_affiliation_mode`
    - [ ] Display impact warnings
    - [ ] Provide migration steps
  - [ ] Create helper methods in `OrganizationManager`
    - [ ] `getMdpAffiliationMode()`
    - [ ] `getGroupManagementSettings()`

### 0.2 HTMX API Infrastructure Decision (Team)
- [ ] Evaluate HTMX API Infrastructure Options
  - [ ] Research existing solutions:
    - [ ] HTMX-API-WP library evaluation
    - [ ] Custom implementation feasibility
  - [ ] Compare options based on:
    - [ ] WordPress integration capabilities
    - [ ] Security compliance with WordPress standards
      - [ ] Nonce handling
      - [ ] CSRF protection
      - [ ] Capability checks
    - [ ] Performance impact
    - [ ] Maintenance requirements
    - [ ] Documentation quality
    - [ ] Integration with ACF Pro blocks
  - [ ] Evaluate against technical requirements:
    - [ ] PHP 8.2+ compatibility
  - [ ] Team decision on implementation approach

### 0.2 Base Classes and Interfaces (Post-Decision)
- [ ] Design architecture based on HTMX API decision

- [ ] Create `OrganizationManager` base class
  - Core methods for organization data handling
  - URL parameter handling
  - Access control validation
  - MDP API integration layer

- [ ] Create `UserPermissions` service
  - Role validation methods
  - Permission checking utilities
  - Integration with WordPress capabilities

- [ ] Create `MDPIntegration` service
  - API communication layer
  - Response handling
  - Error management
  - Cache implementation

### 0.3 Frontend Infrastructure Implementation
- [ ] Set up JavaScript Infrastructure
  - [ ] Implement Vanilla ES6 base utilities
  - [ ] Add HTMX integration (following JS Priority)
    - [ ] Add HTMX library (latest version)
    - [ ] Create initialization module
    - [ ] Implement WordPress-compliant event handlers
    - [ ] Create accessibility-aware utility functions
  - [ ] Add Hyperscript integration
    - [ ] Add Hyperscript library (latest version)
    - [ ] Create initialization module
    - [ ] Implement WAI-ARIA compliant behaviors

- [ ] Implement chosen HTMX API solution
  - [ ] Setup and configuration
  - [ ] WordPress integration
    - [ ] Action hooks setup
    - [ ] Filter implementation
    - [ ] Custom hooks creation
  - [ ] Security implementation
    - [ ] WordPress nonce integration
    - [ ] CSRF protection
    - [ ] Input sanitization
    - [ ] Request validation
  - [ ] Response handling
    - [ ] Status codes
    - [ ] Error messages
    - [ ] Localization support

### 0.4 Block Infrastructure
- [ ] Set up ACF Pro block registration system
- [ ] Create base block class with common functionality
  - [ ] HTMX integration methods
  - [ ] Hyperscript integration methods
  - [ ] Dynamic content loading utilities
- [ ] Set up TailwindCSS with theme variables integration

## Core Implementation

### Profile Management
- [ ] Implement `OrganizationProfileView` block
- [ ] Create view templates
- [ ] Implement data retrieval methods
- [ ] Add access control
- [ ] Implement organization switching
- [ ] Create selector interface

### Profile Editing
- [ ] Implement `OrganizationProfileEdit` block
- [ ] Create edit forms
- [ ] Implement validation
- [ ] Add update methods
- [ ] Add session management

### Members List
- [ ] Implement `OrganizationMembers` block
- [ ] Create list view with pagination
- [ ] Add search functionality
- [ ] Implement sorting and filtering

### Member Actions
- [ ] Implement permission management
- [ ] Add member removal functionality
- [ ] Create role management interface
- [ ] Implement batch operations

## Document Management
### Document Infrastructure
- [ ] Set up WordPress file handling
- [ ] Create document storage structure
- [ ] Implement MDP document integration

### Document Interface
- [ ] Create document upload interface
- [ ] Implement document list view
- [ ] Add download functionality

## UI/UX Implementation & Final Review

### Design Implementation
- [ ] Review and document Wicket's design system guidelines
- [ ] Audit existing blocks against Figma designs
- [ ] Create design consistency checklist
- [ ] Review theme variable implementation
- [ ] Check component pattern alignment
- [ ] Verify spacing and layout consistency

### Design QA
- [ ] Verify colors and typography
- [ ] Check spacing and alignment
- [ ] Test interactive states
- [ ] Validate responsive breakpoints

### Performance & Documentation
- [ ] Profile HTMX request patterns
- [ ] Analyze WordPress query performance
- [ ] Review CSS and JS asset loading
- [ ] Create comprehensive documentation
- [ ] Document hooks and filters
- [ ] Write security implementation guidelines

## Dependencies and Shared Components

### Critical Path Dependencies
1. Core Infrastructure → All Other Components
2. Organization Profile View → Organization Profile Edit
3. Members List → Member Actions
4. Document Infrastructure → Document Interface
5. Business Info Structure → Business Info Interface

### Shared Components (Build First)
1. `OrganizationManager` - Used by all blocks
2. `UserPermissions` - Required for access control
3. `MDPIntegration` - Required for API communication
4. Base block class - Used by all blocks
5. Frontend Infrastructure
   - HTMX setup and utilities
   - Hyperscript setup and behaviors
   - AJAX endpoints infrastructure
   - Dynamic content handlers

## Development Guidelines

### Code Organization
- Follow PSR-12 standards
- Use strict typing
- Implement interfaces for major components
- Use dependency injection
- Create comprehensive unit tests

### Version Control
- Create feature branch from development
- Use conventional commits
- Maintain comprehensive changelog

### Quality Assurance
- Code review required for all PRs
- Documentation required for all features
- Manual testing of critical paths

## Risk Mitigation

### Technical Risks
1. MDP API Changes
   - Implement version checking
   - Create API response validators
   - Maintain comprehensive tests

2. WordPress Version Compatibility
   - Test with multiple WP versions
   - Use WordPress coding standards
   - Avoid deprecated functions

3. Performance Issues
   - Implement caching strategy
   - Monitor API call frequency
   - Use WordPress transients

### Development Risks
1. Feature Dependencies
   - Daily sync between developers
   - Shared documentation maintenance
   - Clear component ownership

2. Integration Challenges
   - Regular integration checks
   - Feature flag system
   - Comprehensive logging

3. HTMX Implementation Risks
   - Security considerations for dynamic endpoints
   - Performance impact of template loading
   - Browser compatibility issues
   - State management complexity

## Success Criteria
- All features implemented and manually tested
- Performance meets requirements
- No critical security issues
- Successful user acceptance testing

### Nice to have
- Documentation completed
- Automated tests for all components
