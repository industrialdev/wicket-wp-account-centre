# ACC Organization Profile Class Documentation

## Overview
The `OrganizationProfile` class is a simple utility responsible for retrieving the logo of an organization. It constructs the URL for an organization's logo based on its ID and checks for the existence of the logo file in various formats (e.g., jpg, png).

## Class Definition
```php
namespace WicketAcc;

class OrganizationProfile extends WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct(
        protected array $extensions = ['jpg', 'jpeg', 'png', 'gif'],
        protected string $uploads_path = WICKET_ACC_UPLOADS_PATH . 'organization-logos/',
        protected string $uploads_url = WICKET_ACC_UPLOADS_URL . 'organization-logos/'
    );

    /**
     * Get the organization logo URL.
     *
     * @param int $org_id Organization ID.
     *
     * @return string|bool Organization logo URL, an empty string if not found, or false on error (e.g., missing org_id).
     */
    public function get_organization_logo($org_id = null);
}
```

## Core Methods

### get_organization_logo
This is the main method of the class. It takes an organization ID and attempts to return the URL to its logo.

- It first checks if an `org_id` is provided. If not, it returns `false`.
- It then iterates through a predefined list of file extensions (e.g., `.jpg`, `.jpeg`, `.png`, `.gif`) to find a corresponding logo file (e.g., `ORG_ID.jpg`) in the designated uploads path.
- If a logo file is found, it constructs and returns the public URL to that file.
- If no specific logo file is found after checking all specified extensions, it returns an empty string (`''`).

## Usage Example
The `OrganizationProfile` class is accessible via the `WACC()` global function.

```php
// Get the logo for organization with ID 123
$logo_url = WACC()->org_profile()->get_organization_logo(123);

if ($logo_url) {
    echo '<img src="' . esc_url($logo_url) . '" alt="Organization Logo">';
} else {
    // Handle case where no logo is found
}
```

## Error Handling
- The `get_organization_logo()` method returns `false` if the `org_id` is not provided.
- If no logo file is found for the given `org_id`, it returns an empty string. It is important to handle this case in the front-end code.
