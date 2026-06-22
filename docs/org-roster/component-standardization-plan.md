# Component Standardization Plan

**Created:** 2026-04-19  
**Status:** Analysis Complete  
**Objective:** Replace custom HTML patterns in wicket-lib-org-roster with standardized base-plugin components

## Executive Summary

The org-roster library contains extensive custom HTML/CSS patterns that duplicate functionality available in `wicket-wp-base-plugin` components. Standardizing on base-plugin components will:

1. **Improve consistency** across the Wicket stack
2. **Reduce maintenance burden** - updates to base components propagate automatically
3. **Enhance accessibility** - base components have established ARIA patterns
4. **Standardize styling** - unified design language across all plugins

## Available Base-Plugin Components

Located in: `wicket-wp-base-plugin/includes/components/`

### Core UI Components
- **alert.php** - Success/error/warning/information messages with colored variants
- **button.php** - Primary/secondary/ghost variants with icons, disabled states
- **card.php** - Generic card with title, subtitle, excerpt, image, CTA
- **tabs.php** - Tabbed interface with Alpine.js
- **accordion.php** - Expandable content sections
- **search-form.php** - Search input with icon and button

### Utility Components
- **icon.php** - Font Awesome icon display with screen reader text
- **link.php** - Styled links with optional icons
- **tag.php** - Badge/tag components with optional link
- **image.php** - Responsive images with aspect ratios

### Layout Components
- **breadcrumbs.php** - Navigation breadcrumbs
- **sidebar-contextual-nav.php** - Context-aware sidebar navigation
- **banner.php** - Hero/promotional banners

## Current Custom Patterns in Org-Roster

### 1. Alert/Notice Messages

**Current Implementation:** Custom HTML with inline classes

**Files Affected:**
- `templates-partials/export-members-status.php` (lines 29-38)
- `templates-partials/members-list-unified.php` (lines 181-194, 430-437, 443-456)
- `templates-partials/engagement-summary.php` (lines 30-36)
- `templates/organization-list.php` (lines 39-57)

**Example Current Code:**
```php
<div class="wt_bg-green-100 wt_border wt_border-green-400 wt_text-green-700 wt_px-4 wt_py-3 wt_rounded-sm">
    <?php echo esc_html($message); ?>
</div>
```

**Replacement Strategy:**
```php
get_component('alert', [
    'variant' => 'success', // or 'error', 'warning'
    'content' => $message,
]);
```

**Benefits:**
- Consistent styling across stack
- Built-in ARIA role="alert"
- Standardized color variants

### 2. Buttons

**Current Implementation:** Custom button markup with SVG icons

**Files Affected:**
- `templates-partials/members-list-unified.php` (lines 303-329, 333-354, 418-425)
- `templates/organization-list.php` (line 57)
- All modal/process handlers with submit buttons

**Example Current Code:**
```php
<button type="button" class="acc-edit-button edit-permissions-button button button--primary wt_inline-flex wt_items-center...">
    <?php esc_html_e('Edit Permissions', 'wicket-acc'); ?>
    <svg class="wt_w-4 wt_h-4"><!-- SVG path --></svg>
</button>
```

**Replacement Strategy:**
```php
get_component('button', [
    'variant'     => 'primary',
    'label'       => __('Edit Permissions', 'wicket-acc'),
    'suffix_icon' => 'fa-solid fa-pen-to-square',
    'a_tag'       => false,
    'type'        => 'button',
    'atts'        => [
        'data-member-uuid' => $member_uuid,
        'data-on:click'    => '$editPermissionsModalOpen = true',
    ],
]);
```

**Benefits:**
- Consistent button styling
- Built-in icon support
- Standardized hover/focus states
- Disabled state handling

### 3. Cards

**Current Implementation:** Custom card markup in multiple templates

**Files Affected:**
- `templates-partials/card-organization-groups.php` (entire file)
- `templates-partials/card-organization-membership-cycle.php`
- `templates-partials/card-organization-direct-cascade.php`
- `templates/organization-list.php` (lines 313-339)

**Example Current Code:**
```php
<div class="wt_w-full wt_rounded-card-accent wt_p-4 wt_mb-4 wt_hover_shadow-sm wt_transition-shadow wt_bg-card wt_border wt_border-color" role="listitem">
    <h2 class="wp-block-heading has-heading-sm-font-size wt_text-2xl wt_mb-3">
        <?php echo esc_html($org_name); ?>
    </h2>
    <!-- Card content -->
</div>
```

**Replacement Strategy:**
```php
get_component('card', [
    'title'     => $org_name,
    'subtitle'  => $membership_status,
    'excerpt'   => $org_description,
    'link'      => [
        'url'    => $profile_url,
        'text'   => __('View Organization', 'wicket-acc'),
        'target' => '_self',
    ],
    'cta_style' => 'link',
    'classes'   => ['wt_w-full', 'wt_hover_shadow-sm'],
]);
```

**Benefits:**
- Standardized card structure
- Built-in responsive layout
- Consistent spacing and typography
- Image support when needed

### 4. Icons

**Current Implementation:** Mixed inline SVGs and `<i>` tags

**Files Affected:**
- All template files using icons
- Status indicators (confirmed/unconfirmed dots)
- Button icons

**Example Current Code:**
```php
<span class="wt_inline-block wt_w-2 wt_h-2 wt_rounded-full wt_bg-green-500" aria-hidden="true"></span>
<svg class="wt_w-4 wt_h-4" viewBox="0 0 24 24"><!-- SVG path --></svg>
```

**Replacement Strategy:**
```php
get_component('icon', [
    'icon'    => 'fa-solid fa-circle-check',
    'text'    => __('Confirmed', 'wicket-acc'),
    'classes' => ['wt_text-green-500'],
]);

get_component('icon', [
    'icon'    => 'fa-solid fa-pen',
    'text'    => __('Edit', 'wicket-acc'),
    'classes' => ['wt_w-4', 'wt_h-4'],
]);
```

**Benefits:**
- Consistent icon sizing
- Built-in screen reader support
- Centralized icon library (Font Awesome)
- Reduced template code

### 5. Tags/Badges

**Current Implementation:** Custom badge markup in engagement summary

**Files Affected:**
- `templates-partials/engagement-summary.php` (lines 67-73)

**Example Current Code:**
```php
<span class="wt_badge wt_badge--primary">
    <?php echo esc_html((string) $badge); ?>
</span>
```

**Replacement Strategy:**
```php
get_component('tag', [
    'label' => (string) $badge,
    'icon'  => '', // Optional icon
]);
```

**Benefits:**
- Standardized badge styling
- Optional icon support
- Link variant when needed
- Reversed color variant available

### 6. Search Forms

**Current Implementation:** Custom search inputs (if any exist)

**Potential Files:** Member search/filter sections

**Replacement Strategy:**
```php
get_component('search-form', [
    'placeholder' => __('Search members...', 'wicket-acc'),
    'url-param'   => 'query',
]);
```

**Benefits:**
- Consistent search UI
- Built-in icon
- Standardized input styling

### 7. Tabs (If Applicable)

**Current Implementation:** Any tabbed interfaces in member management

**Replacement Strategy:**
```php
get_component('tabs', [
    'items' => [
        [
            'title'        => __('Direct Members', 'wicket-acc'),
            'body_content' => $direct_members_html,
        ],
        [
            'title'        => __('Cascade Members', 'wicket-acc'),
            'body_content' => $cascade_members_html,
        ],
    ],
]);
```

**Benefits:**
- Built-in Alpine.js integration
- ARIA compliance
- Consistent tab styling

## Implementation Priority

### Phase 1: High-Impact, Low-Risk Replacements ✅ COMPLETE

1. **Alert Messages** ✅ COMPLETE
   - Replaced all custom alert boxes with `alert` component
   - Files: export-members-status.php, members-list-unified.php, engagement-summary.php, organization-list.php
   - Effort: 2 hours (as estimated)
   - Risk: Low ✅ No issues

2. **Buttons** ✅ COMPLETE (Datastar Compatible!)
   - Replaced buttons with `button` component
   - **Finding:** Datastar attributes work perfectly with button component
   - Files: members-list-unified.php (5 button types converted)
   - Effort: 2 hours (less than estimated due to smooth implementation)
   - Risk: Low ✅ Datastar expressions preserved correctly
   - **Documentation:** `docs/button-standardization-complete.md`

### Phase 2: Medium-Impact Replacements

3. **Icons** (Widespread use, moderate complexity)
   - Replace inline SVGs with `icon` component
   - Focus on status indicators and button icons first
   - Estimated effort: 6-8 hours
   - Risk: Medium (need to ensure all icons exist in Font Awesome)

4. **Tags/Badges** (Limited use, quick replacement)
   - Replace engagement summary badges
   - Estimated effort: 1-2 hours
   - Risk: Low

### Phase 3: Structural Replacements

5. **Cards** (Significant refactoring, high impact)
   - Replace organization cards with `card` component
   - May need custom card variant for complex data
   - Estimated effort: 12-16 hours
   - Risk: Medium-High (structural changes)

## Technical Considerations

### 1. Dependency on Base-Plugin

**Current State:** Org-roster library has no dependency on base-plugin

**Post-Standardization:** Will require `wicket-wp-base-plugin` to be active

**Mitigation:**
- Add base-plugin to composer dependencies
- Wrap component calls in `function_exists()` checks if backward compatibility needed
- Document base-plugin requirement in README

### 2. Custom CSS Classes

**Current State:** Heavy use of `wt_` prefixed utility classes

**Post-Standardization:** Reduce custom CSS, rely on component styles

**Action Items:**
- Audit `public/css/modern-orgman-static.css` for unused utilities
- Keep only org-roster-specific styles
- Document which utilities are still needed and why

### 3. Component Customization

**Challenge:** Base-plugin components may not match exact current styling

**Solutions:**
- Use `classes` parameter to add custom classes
- Create org-roster-specific component variants in base-plugin if needed
- Contribute improvements to base-plugin components for broader use

### 4. Datastar Integration

**Challenge:** Current templates use Datastar attributes extensively

**Solution:**
- Use `atts` parameter in components to pass Datastar attributes
- Example: `'atts' => ['data-on:click' => '$modalOpen = true']`

## Migration Strategy

### 1. Create Compatibility Layer

**File:** `src/Helpers/ComponentHelper.php`

```php
namespace WicketORM\Helpers;

class ComponentHelper {
    public static function get_component_safe(string $slug, array $args = []): string {
        if (function_exists('get_component')) {
            ob_start();
            get_component($slug, $args);
            return ob_get_clean();
        }
        
        // Fallback rendering for when base-plugin is not available
        return self::render_fallback($slug, $args);
    }
    
    private static function render_fallback(string $slug, array $args): string {
        switch ($slug) {
            case 'alert':
                return self::render_alert_fallback($args);
            case 'button':
                return self::render_button_fallback($args);
            // ... other fallbacks
        }
    }
}
```

### 2. Gradual Migration Approach

**Per-File Migration:**
1. Create backup of original file
2. Replace one component type at a time
3. Test thoroughly in all roster modes (direct, cascade, groups, membership_cycle)
4. Commit changes with clear message
5. Move to next file

**Per-Component Migration:**
1. Start with `alert` component (lowest risk)
2. Progress to `button` and `icon`
3. End with structural components (`card`)

### 3. Testing Checklist

Before/After Comparison:
- [ ] Visual regression testing across all roster modes
- [ ] Accessibility audit (screen reader, keyboard navigation)
- [ ] Cross-browser testing (Chrome, Firefox, Safari, Edge)
- [ ] Mobile responsiveness verification
- [ ] Datastar functionality preserved
- [ ] No JavaScript errors in console
- [ ] All existing functionality works (add, remove, edit members)

## Estimated Effort Summary

| Component | Files Affected | Estimated Hours | Risk Level |
|-----------|---------------|-----------------|------------|
| Alert | 4 | 2-3 | Low |
| Button | 8+ | 4-6 | Low |
| Icon | 15+ | 6-8 | Medium |
| Tag/Badge | 1 | 1-2 | Low |
| Card | 4+ | 12-16 | Medium-High |
| **Total** | **30+** | **25-35** | **Medium** |

## Benefits Realization

### Immediate Benefits (Post-Phase 1)
- Reduced custom CSS by ~15%
- Consistent alert styling across stack
- Easier maintenance of button states

### Medium-Term Benefits (Post-Phase 2)
- 30% reduction in template code
- Centralized icon management
- Improved accessibility compliance

### Long-Term Benefits (Post-Phase 3)
- 50%+ reduction in custom HTML/CSS
- Automatic inheritance of base-plugin improvements
- Faster feature development
- Consistent user experience across all Wicket plugins

## Recommendations

### 1. Proceed with Standardization

**Reasoning:** 
- Clear benefits outweigh costs
- Low-risk incremental approach available
- Aligns with stack-wide design goals
- Reduces long-term maintenance burden

### 2. Start with Alerts and Buttons

**Reasoning:**
- Quick wins (6-9 hours total)
- High visibility improvements
- Low risk (isolated changes)
- Builds familiarity with component system

### 3. Plan for Base-Plugin Dependency

**Actions:**
- Update composer.json to require base-plugin
- Document requirement in README
- Add activation check in OrgMan.php
- Provide clear upgrade path for existing installations

### 4. Contribute Improvements Back

**Philosophy:**
- If org-roster needs custom component variant, consider adding it to base-plugin
- Benefits entire stack
- Reduces org-roster-specific code
- Aligns with "leave it better than you found it" principle

## Open Questions

1. **Backward Compatibility:** Should we support installations without base-plugin?
   - Recommendation: No, make it a hard requirement for clarity

2. **Custom Component Variants:** Should we add org-roster-specific variants to base-plugin?
   - Recommendation: Yes, if reusable by other plugins

3. **CSS Utility Cleanup:** How aggressive should we be in removing `wt_` classes?
   - Recommendation: Audit and remove unused, keep necessary org-roster-specific utilities

4. **Testing Infrastructure:** Should we add visual regression testing?
   - Recommendation: Yes, consider tools like BackstopJS for component changes

## Next Steps

1. **Review and Approve:** Stakeholder review of this plan
2. **Create Issues:** Break down into GitHub issues with acceptance criteria
3. **Set Up Dependencies:** Ensure base-plugin is available in all environments
4. **Begin Phase 1:** Start with alert and button replacements
5. **Document Changes:** Update this document as implementation progresses

---

**Document Maintainer:** Development Team  
**Last Updated:** 2026-04-19  
**Version:** 1.0 - Initial Analysis
