# Organization Management - Asana Tasks

## Phase 0: Infrastructure Setup (Weeks 1-2, +2 days)

### Project: ACC - Infrastructure Setup
1. Datastar API Infrastructure Research & Decision
   - Priority: P1
   - Estimate: 3
   - Description: Research and establish the Datastar API integration approach for dynamic content loading in the Account Centre plugin. Focus on WordPress compatibility, security considerations with nonces, and optimal implementation patterns. Key decisions include: CSRF handling strategy, response format standardization, and error handling approach. Must consider both server-side (PHP handlers) and client-side (JavaScript extensions) architecture. Final output should be a clear technical specification for the team to follow.
   - Subtasks:
     - [ ] Research existing solutions
     - [ ] Present options to team
     - [ ] Make final decision

2. Create MDP Configuration Options
   - Priority: P1
   - Estimate: 5
   - Description: Implement options for global MDP configuration settings. This will provide admins with a centralized interface to manage organization relationships, member permissions, and affiliation settings. Fields must support multilingual content and include validation rules. Configuration will determine how organizations interact with members and define the default behavior for new organization relationships.
   We can't have this as settings on every block, because all blocks should behave the same way with respect to how they will use MDP organization management: direct, cascade, group. That being the main setting we need, outside of the blocks.
   Many logic on process like display members, add members, remove members, how to show an user active or not, etc. depend on this global configuration. So we need it as a global setting on ACC.
   - Considerations: Implement a two-tier settings approach where global configurations handle core relationships, permissions, and shared elements, while block-specific settings manage individual appearance and behavior. This separation ensures consistent default behavior across the plugin while allowing flexibility for individual block instances to override settings when needed.
   - Subtasks:
     - [ ] Create field group structure visually on ACF Pro (with cached JSON files)
     - [ ] Add affiliation mode settings: direct, cascade, group.
     - [ ] Add relationship configuration: relationship types mapped to the MDP, how a relationship will end (removal vs end date), etc.
     - [ ] Add manager permissions: roles that can manage what or see/edit/delete things.
     - [ ] Test field group registration

3. Extend AdminSettings Class
   - Priority: P1
   - Estimate: 3
   - Description: Extend the existing AdminSettings class to handle the new MDP configuration options. Implement methods for retrieving, validating, and persisting organization management settings. This class will serve as the primary interface between the ACF fields and the rest of the plugin.
   - Considerations: Consider adding helper methods for common setting combinations used across blocks.
   - Subtasks:
     - [ ] Implement helper methods
     - [ ] Test settings persistence

4. Implement Base Classes for Organization Management
   - Priority: P1
   - Estimate: 5
   - Description: Create foundational classes that will handle core organization management functionality. Implement OrganizationManager for organization data handling, UserPermissions for role-based access control, and MDPIntegration for API communication. These classes will provide the backbone for all organization-related features.
   - Consideration: Design classes with clear separation of concerns, but NEVER forget about KISS and DRY principles. OrganizationManager handles data and state, UserPermissions manages access rules, and MDPIntegration abstracts API communication details.
   - Consideration: Note that this infrastructure task will overlap with Phase 1 Legacy Migration (tasks 1-5) as we migrate old functions into these new service classes.
   - Subtasks:
     - [ ] Create OrganizationManager class
     - [ ] Create OrganizationMember class
     - [ ] Create MemberPermissions class
     - [ ] Extend MdpApi class with organization-specific functionality

5. Frontend Infrastructure Implementation
   - Priority: P2
   - Estimate: 3
   - Description: Set up the frontend architecture to support modern, interactive features using Datastar. Implement utility functions for common frontend operations, establish ARIA patterns for accessibility, and create a robust foundation for dynamic content updates. Configure TailwindCSS to work seamlessly with our centralized theme variables system.
   - Consideration: mobile first, always responsive design.
   - Consideration: Ensure progressive enhancement - all features should work without JavaScript enabled, with Datastar adding enhanced interactivity.
   - Consideration: Follow WAI-ARIA best practices from the start, establishing reusable accessibility patterns.
   - Consideration: Strictly enforce the use of theme-variables.css for all styling properties - no hardcoded values allowed for colors, sizes, weights, dimensions, or any other design tokens. All styles must be configurable via the theme system.
   - Subtasks:
     - [ ] Add Datastar integration
     - [ ] Set up ES6 utilities if needed

6. Base & Standard Datastar API Implementation
   - Priority: P2
   - Estimate: 5
   - Description: Implement the server-side infrastructure for handling Datastar requests within WordPress. Create a robust routing system for Datastar endpoints, standardize response formats, and establish security measures including nonce validation and permission checks.
   - Consideration: Implement proper error handling with appropriate HTTP status codes and user-friendly error messages in both HTML and JSON formats.
   - Consideration: Cache responses appropriately, considering both server-side caching and Datastar's client-side caching headers.
   - Consideration: Ensure all responses are properly escaped and sanitized, following WordPress security best practices.
   - Subtasks:
     - [ ] Set up WordPress integration (as decided on task 1)
     - [ ] Implement centralized template rendering and response system
     - [ ] Implement security measures
     - [ ] Create response handlers
     - [ ] Test endpoints

7. Block Infrastructure Setup
   - Priority: P2
   - Estimate: 3
   - Description: Create a standardized foundation for all Account Centre blocks. Implement a base block class that handles common functionality, ACF field registration, and template rendering. This infrastructure will ensure consistency across all organization management blocks while reducing code duplication.
   - Consideration: Base block class must handle all shared functionality: settings management, template loading, Datastar integration, and error handling.
   - Consideration: Block templates should follow a consistent structure with clear separation between logic and presentation.
   - Consideration: ACF field registration should support both global config inheritance and block-specific overrides.
   - Subtasks:
     - [ ] Extend current ACF block registration system
     - [ ] Implement new base block infrastructure
     - [ ] Set up TailwindCSS integration in no-build mode (only CDN dev)
     - [ ] Create base block templates

8. Port Legacy Organization Functions into ACC
   - Priority: P1
   - Estimate: 8 (3 days)
   - Description: Migrate existing organization management functions into the new ACC service architecture. This involves carefully moving functionality while maintaining backward compatibility through deprecation notices. The migration must preserve all existing features while improving code organization and maintainability.
   - Consideration: Implement one service at a time, starting with core organization functions, then member management, followed by contact and business information. This approach minimizes risk and allows for proper testing at each stage.
   - Consideration: Use dependency injection and service containers to improve testability and maintain loose coupling between components. This will make future updates and maintenance easier.
   - Consideration: Document all deprecated functions with clear migration paths for any custom code that might be using them. Maintain them in code, but flag them as deprecated.
   - Subtasks:
     - [ ] Migrate core organization management functions
     - [ ] Migrate member and permissions management
     - [ ] Migrate contact and business information handlers
     - [ ] Migrate utility functions and data loaders
     - [ ] Add deprecation notices to legacy functions
     - [ ] Document new service architecture

## Phase 1: Core Implementation (Weeks 3-5, +2 days)

### Project: ACC - Core Organization Blocks

9. Organization Selector Shortcode
   - Priority: P1
   - Estimate: 8
   - Description: Create a foundational shortcode [wicket_organization_selector] that will be used across the plugin for organization selection. This shortcode enables users to switch between organizations they have access to, serving as a crucial navigation component that other blocks can embed and utilize. It should provide a consistent interface for organization selection while being highly configurable through shortcode attributes.
   - Consideration: Design the shortcode to be highly configurable through attributes (e.g., [wicket_organization_selector mode="cards" filter="active" show_count="true"]). Support various display modes, filtering options, and callback handling.
   - Consideration: Use Datastar for seamless organization switching without page reloads. Implement proper state management using WordPress transients to maintain selected organization across page loads.
   - Consideration: Create actions and filters to allow other blocks to hook into organization selection events (e.g., 'wicket_organization_selected', 'wicket_organization_changed'). Implement proper caching of user's organization list to minimize API calls.
   - Subtasks:
      - [ ] Create shortcode registration and handler
      - [ ] Implement attribute parsing and validation
      - [ ] Add organization list with filtering
      - [ ] Create display modes (cards, list, dropdown)
      - [ ] Add loading states and error handling
      - [ ] Test shortcode in various contexts

10. Organization Info Card Block
    - Priority: P1
    - Estimate: 5
    - Description: A compact, reusable block for displaying key organization information. This block serves as a standardized way to present organization details across the plugin, including name, status, membership counts, and key metrics. It should be highly configurable to show/hide different information based on context and user permissions.
    - Consideration: Design the block to be as lightweight as possible since it may appear multiple times on a single page. Implement proper data caching to prevent redundant API calls.
    - Consideration: Use theme variables exclusively for styling to ensure consistent appearance across different contexts. The card should adapt to both light and dark themes.
    - Consideration: Make the block fully responsive and ensure it maintains readability at all breakpoints. Consider implementing different layouts for mobile vs desktop views.
    - Subtasks:
      - [ ] Create block structure with ACF fields for display options
      - [ ] Implement card template with flexible sections
      - [ ] Add data retrieval with caching
      - [ ] Create responsive layout variations
      - [ ] Add permission-based content filtering
      - [ ] Test in different contexts and themes

11. Organization Profile View Block
    - Priority: P2
    - Estimate: 5
    - Description: Main interface for viewing detailed organization information. This block displays comprehensive organization details including contact information, business details, membership status, and related organizations. It serves as the primary read-only view for organization data, with links to relevant management blocks for users with appropriate permissions.
    - Consideration: Implement progressive loading using Datastar to improve initial page load time. Load secondary information (like membership stats, related orgs) after the primary profile data is displayed.
    - Consideration: Structure the view with clear visual hierarchy - primary info (name, status, type) first, followed by contact details, then extended information.
    - Subtasks:
      - [ ] Create block structure with section components
      - [ ] Implement progressive loading with Datastar
      - [ ] Add schema.org markup
      - [ ] Create permission-based view variations
      - [ ] Add links to management blocks
      - [ ] Implement error states and loading indicators
      - [ ] Test accessibility compliance

12. Organization Profile Edit Block
    - Priority: P2
    - Estimate: 8
    - Description: Edit interface for managing organization profile data. This block provides a form-based interface for updating organization details with real-time validation, proper error handling, and field-level permissions. It should handle both basic information updates and more complex operations like status changes or relationship modifications.
    - Consideration: Implement field-level validation with real-time feedback using Datastar. Show validation errors inline as users type, but only trigger API calls on form submission or explicit save actions.
    - Consideration: Use a multi-step form approach for complex changes (like status updates) that require additional confirmation or documentation. Each step should be independently validated.
    - Consideration: Implement proper state management to handle unsaved changes, including warning users before navigating away from unsaved edits.
    - Subtasks:
      - [ ] Create form structure with field grouping
      - [ ] Implement real-time validation with Datastar
      - [ ] Add multi-step workflows for complex changes
      - [ ] Create permission-based field visibility
      - [ ] Add unsaved changes detection
      - [ ] Implement error handling and recovery
      - [ ] Test form submission scenarios

## Phase 2: Core Features - Extended (Weeks 6-7)

### Project: ACC - Extended Organization Blocks

13. Organization Members Block
    - Priority: P2
    - Estimate: 8
    - Description: Main interface for viewing and managing organization members. This block provides a comprehensive view of all organization members with advanced filtering, sorting. It includes role management, status updates, and integration with the member addition process. The interface should handle large member lists efficiently while maintaining responsive performance.
    - Consideration: Implement virtual scrolling or pagination with Datastar for large member lists. Use progressive loading to fetch member details only when needed, with proper loading indicators to maintain user experience.
    - Consideration: Cache member data appropriately to reduce server load, but implement proper cache invalidation when member details are updated through any interface.
    - Consideration: Leave the road paved for future work on a bulk member editing and/or deletion process.
    - Subtasks:
      - [ ] Create responsive table/grid view for members
      - [ ] Implement advanced filtering and sorting
      - [ ] Add virtual scrolling with Datastar
      - [ ] Implement role management controls
      - [ ] Add member status indicators
      - [ ] Implement data caching strategy
      - [ ] Test performance with large datasets

14. Organization Members Add Block
    - Priority: P2
    - Estimate: 8
    - Description: Create a focused interface for adding new members to an organization, with role assignment capabilities and proper validation. This block should provide a streamlined process for organization administrators to add members while ensuring proper permissions and data validation. The interface should support both single member addition and potential bulk operations.
    - Consideration: Implement real-time email validation and role permission checks using Datastar. Use proper error handling to show meaningful messages for various scenarios (e.g., already a member, invalid email, insufficient permissions).
    - Consideration: Create action hooks for member addition events to allow other components to react (e.g., notifications, logging). Use the standard hook prefix 'wicket/acc/member-*'.
    - Subtasks:
      - [ ] Create member addition form with email and role fields
      - [ ] Implement role selection with proper permission checks
      - [ ] Add email validation with Datastar integration
      - [ ] Create success/error message handling
      - [ ] Add loading states and progress indicators
      - [ ] Email notification on member addition to new members
      - [ ] Test all member addition scenarios

15. Organization Documents Block
    - Priority: P3
    - Estimate: 5
    - Description: Create a block for managing organization-related documents, providing a centralized interface for uploading, organizing, and accessing important files. This block should support various document types, implement proper access controls, and integrate with WordPress media management while maintaining organization-specific context.
    - Consideration: Implement progressive loading using Datastar for document lists, with proper filtering and sorting capabilities. Use infinite scroll or load-more patterns for large document collections.
    - Consideration: Create a robust permission system that respects both WordPress capabilities and organization roles. Use action hooks with 'wicket/acc/document-*' prefix for extensibility.
    - Consideration: Implement proper file type validation, size limits, and storage management.
    - Subtasks:
      - [ ] Create document upload interface
      - [ ] Implement document list view with Datastar
      - [ ] Create document preview functionality
      - [ ] Implement permission-based access control
      - [ ] Add document metadata management
      - [ ] Test various file types and sizes

16. Organization Business Info Block
    - Priority: P2
    - Estimate: 8
    - Description: Create a block for managing organization business information, providing a structured interface for viewing and editing key business details such as tax information, business numbers, and industry classifications. This block should handle complex business data while maintaining data integrity and proper validation.
    - Consideration: Implement field-specific validation using Datastar for real-time feedback. Handle different business number formats and validations based on organization type and jurisdiction.
    - Subtasks:
      - [ ] Create business info display layout
      - [ ] Implement edit mode with validation
      - [ ] Add field-specific formatting
      - [ ] Create sensitive data handling
      - [ ] Implement save and update logic
      - [ ] Test various business types

17. Organization Subsidiaries Block
    - Priority: P3
    - Estimate: 8
    - Description: Create a block for managing organization subsidiary relationships, providing a hierarchical view of parent-child organization connections. This block should enable users to visualize, establish, and manage organizational hierarchies while maintaining proper access controls and data consistency.
    - Consideration: Implement an intuitive tree-view interface using Datastar for dynamic loading of subsidiary levels. Use proper indentation and visual cues to represent hierarchy depth.
    - Subtasks:
      - [ ] Create hierarchical view interface
      - [ ] Implement subsidiary management
      - [ ] Add relationship validation
      - [ ] Create hierarchy visualization
      - [ ] Implement access controls
      - [ ] Test complex hierarchies

18. Organization Management General Block
    - Priority: P2
    - Estimate: 8
    - Description: Create a central management block that provides a comprehensive overview and quick access to all organization management functions. This block should serve as a dashboard-like interface, integrating with other organization blocks and providing essential management tools in one place.
    - Consideration: Implement a responsive grid layout that adapts to available space and device size. Use Datastar for dynamic content loading and updates.
    - Consideration: Ensure proper permission checks for displaying management options and sensitive information.
    - Subtasks:
      - [ ] Create main management interface
      - [ ] Implement quick action buttons
      - [ ] Add organization status overview
      - [ ] Create navigation to other blocks
      - [ ] Implement permission checks
      - [ ] Add loading states
      - [ ] Test responsive behavior

## Phase 3: UI/UX Implementation & Final Review (Weeks 8-9)

### Project: ACC - Design Implementation
19. Figma Design Alignment
    - Priority: P1
    - Estimate: 13 (2-3 days)
    - Description: To retrospectively review all WordPress blocks created and ensure they align with the Figma design guidelines provided by the designer, to follow Wicket's design system and be consistent with other blocks and components from other Wicket plugins.
    - Consideration: Review all blocks against Wicket's design system documentation to ensure consistent use of colors, typography, spacing, and component patterns across plugins.
    - Consideration: Compare existing block implementations with Figma designs, identifying and documenting any deviations that need adjustment.
    - Consideration: Check proper use of theme variables from `uploads/wicket-theme/css/theme-variables.css` for consistent styling.
    - Subtasks:
      - [ ] Review and document Wicket's design system guidelines
      - [ ] Audit existing blocks against Figma designs
      - [ ] Create design consistency checklist
      - [ ] Review theme variable implementation
      - [ ] Check component pattern alignment
      - [ ] Verify spacing and layout consistency
      - [ ] Implement & test responsive adjustments

20. Design QA
    - Priority: P2
    - Estimate: 13
    - Description: Perform comprehensive quality assurance on the implemented designs, ensuring pixel-perfect alignment with Figma specifications and consistent behavior across all blocks. This includes verification of responsive layouts, interactive states, and accessibility requirements.
    - Consideration: Use browser developer tools to verify exact spacing, colors, and typography measurements against Figma specifications.
    - Consideration: Test all interactive states (hover, focus, active, disabled) for consistency with design system.
    - Subtasks:
      - [ ] Verify colors and typography
      - [ ] Check spacing and alignment
      - [ ] Test interactive states
      - [ ] Validate responsive breakpoints

21. Performance Optimization Review
    - Priority: P3
    - Estimate: 8
    - Description: Conduct a thorough performance review of all organization management blocks, focusing on Datastar implementation efficiency, WordPress query optimization, and frontend asset loading. Identify and implement optimizations to ensure smooth user experience even with large datasets.
    - Consideration: Use browser performance tools to measure and optimize Datastar request patterns, DOM updates, and CSS rendering.
    - Consideration: Review WordPress query patterns for optimization opportunities, particularly in list views and data-heavy operations.
    - Subtasks:
      - [ ] Profile Datastar request patterns
      - [ ] Analyze WordPress query performance
      - [ ] Review CSS and JS asset loading
      - [ ] Optimize large dataset handling
      - [ ] Implement performance monitoring and caching when needed

22. Documentation & Developer Guidelines
    - Priority: P4
    - Estimate: 8
    - Description: Create comprehensive documentation following WordPress standards and self-documenting code principles. Include technical implementation details, hook references, and developer guidelines to ensure maintainability and extensibility.
    - Consideration: Follow PSR-12 standards and document all hooks, filters, and shortcode attributes with clear examples that demonstrate proper WordPress integration patterns.
    - Consideration: Emphasize security requirements, input validation, and proper capability checks in all documentation examples.
    - Subtasks:
      - [ ] Document block architecture and data flow
      - [ ] Create hook and filter reference with examples
      - [ ] Document shortcode attributes and use cases
      - [ ] Write security implementation guidelines
      - [ ] Document Datastar integration patterns

## Task Priority Legend
- P1: Critical path, must be completed first
- P2: High priority, core functionality
- P3: Medium priority, supporting features
- P4: Lower priority, can be deferred
- P5: Nice to have, can be done later

## Estimation Notes
Estimates follow the Fibonacci-style scale.

- 1 = 1 hour or less
- 2 = 2 hours top
- 3 = half day work
- 5 = full day work
- 8 = two days work
- 13 = three or more days work, needs to be split into smaller tasks

All estimates include testing and code review time. Assumes 2 developers working in parallel, ideally.
