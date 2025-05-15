# Organization Documents Block

## Overview
Displays and manages organization membership documentation stored in MDP. Supports multiple file uploads and provides a list view of all stored documents.

## Block Identification
- **Slug**: `wicket-ac/organization-documents`
- **Name**: Organization Documents

## Access Control

### Required Roles
- View documents:
  - member
  - administrator
  - membership_manager
  - membership_owner
  - org_editor
- Upload/Delete documents:
  - administrator
  - membership_manager
  - membership_owner

## Data Structure

### Document Item
```php
[
    'uuid' => 'string',
    'name' => 'string',
    'url' => 'string',
    'uploaded_at' => 'string',  // ISO 8601 date
    'uploaded_by' => [
        'uuid' => 'string',
        'name' => 'string'
    ],
    'actions' => [
        'download' => [
            'url' => 'string',
            'visible' => true  // All authenticated users can download
        ],
        'delete' => [
            'url' => 'string',
            'visible' => 'boolean'  // Based on user roles
        ]
    ]
]
```

### Upload Configuration
```php
[
    'max_file_size' => '10MB',
    'allowed_mime_types' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png'
    ],
    'max_files_per_upload' => 10
]
```

## Integration Methods

```php
class OrganizationDocuments {
    /**
     * Retrieves all documents for an organization
     * Includes download URLs and available actions
     *
     * @param string $organizationUuid Organization identifier
     * @return array List of documents with metadata
     * @throws OrganizationNotFoundException If organization not found
     */
    public function getDocuments(string $organizationUuid): array;

    /**
     * Handles file upload to server and MDP registration
     * Supports single or multiple file uploads
     *
     * @param string $organizationUuid Organization identifier
     * @param array $files Uploaded files data
     * @return array Upload results with success/error messages
     * @throws UploadException If file upload fails
     * @throws ValidationException If files invalid
     */
    public function uploadDocuments(
        string $organizationUuid,
        array $files
    ): array;

    /**
     * Removes document from MDP and server storage
     *
     * @param string $organizationUuid Organization identifier
     * @param string $documentUuid Document to remove
     * @return bool Success status
     * @throws UnauthorizedException If user lacks permission
     */
    public function removeDocument(
        string $organizationUuid,
        string $documentUuid
    ): bool;

    /**
     * Processes batch of file uploads
     * Handles both WP filesystem and MDP registration
     *
     * @param string $organizationUuid Organization identifier
     * @param array $files Array of $_FILES items
     * @return array Processing results per file
     * @throws BatchUploadException If batch process fails
     */
    public function processBatchUpload(
        string $organizationUuid,
        array $files
    ): array;

    /**
     * Generates file URL based on WordPress upload directory
     * Creates organization-specific URL structure
     *
     * @param string $organizationUuid Organization identifier
     * @param string $filename Original filename
     * @return string Generated URL
     */
    private function generateFileUrl(
        string $organizationUuid,
        string $filename
    ): string;
}

class DocumentPermissions {
    /**
     * Checks if user can upload new documents
     * Requires administrator, membership_manager, or membership_owner role
     *
     * @param string $organizationUuid Organization to check against
     * @return bool True if user can upload
     */
    public function canUploadDocuments(string $organizationUuid): bool;

    /**
     * Checks if user can remove documents
     * Requires same roles as upload
     *
     * @param string $organizationUuid Organization to check against
     * @return bool True if user can remove documents
     */
    public function canRemoveDocuments(string $organizationUuid): bool;
}
```

### WP Filesystem Integration
```php
class DocumentFileSystem {
    /**
     * Handles file upload to WordPress uploads directory
     * Creates organization-specific subdirectories
     * Returns URL and path information
     *
     * @param array $file $_FILES array item
     * @param string $organizationUuid For directory structure
     * @return array File info with URL and server path
     * @throws FilesystemException If upload fails
     */
    public function uploadFile(array $file, string $organizationUuid): array;

    /**
     * Creates organization directory in uploads if not exists
     * Sets proper permissions
     *
     * @param string $organizationUuid Organization identifier
     * @return string Full path to organization directory
     */
    private function ensureOrganizationDirectory(string $organizationUuid): string;

    /**
     * Removes file from WordPress filesystem
     * Verifies file belongs to organization before deletion
     *
     * @param string $filepath Path to file
     * @param string $organizationUuid For verification
     * @return bool Success status
     */
    public function removeFile(string $filepath, string $organizationUuid): bool;
}

class DocumentProcessor {
    /**
     * Processes uploaded file and registers with MDP
     * Handles both filesystem and MDP operations
     *
     * @param array $file File data from upload
     * @param string $organizationUuid Organization identifier
     * @return array Process result with MDP and filesystem info
     */
    public function processUpload(array $file, string $organizationUuid): array;

    /**
     * Removes document from both filesystem and MDP
     * Handles cleanup in both systems
     *
     * @param string $documentUuid MDP document identifier
     * @param string $organizationUuid Organization identifier
     * @return bool Success status
     */
    public function removeDocument(
        string $documentUuid,
        string $organizationUuid
    ): bool;
}
```

## Required Legacy Functions
- `wicket_orgman_get_organization_documents()`
- `wicket_orgman_upload_organization_document()`
- `wicket_orgman_remove_organization_document()`
- `wicket_orgman_role_check()`
- `wicket_orgman_register_document()`
- `wicket_orgman_remove_document()`

## HTMX Integration

### Document Upload
```php
[
    'endpoints' => [
        'upload' => [
            'url' => '/wp-htmx/organization/{uuid}/documents/upload',
            'method' => 'POST',
            'encoding' => 'multipart/form-data',
            'target' => '#documents-list',
            'swap' => 'afterend',
            'indicator' => '.uploading'
        ],
        'remove' => [
            'url' => '/wp-htmx/organization/{uuid}/documents/{doc_uuid}/remove',
            'method' => 'DELETE',
            'target' => '#document-{doc_uuid}',
            'swap' => 'outerHTML',
            'confirm' => 'Are you sure you want to remove this document?'
        ]
    ]
]
```

## UI Components

### Document List
- Grid or list view of documents
- Download link for each document
- Delete button for users with permission
- Upload progress indicators
- File type icons
- Upload date and user information

### Upload Interface
- Drag-and-drop zone
- Multiple file selection
- Progress indicators
- File type validation
- Size limit warnings
- Success/error messages

## Error Handling
- Invalid file types
- File size exceeded
- Upload failures
- Download errors
- Permission denied messages
- API communication errors
- WordPress filesystem errors
- Directory permission issues
- Invalid file structure
- URL generation failures
- File deletion failures

## State Management
- Upload progress tracking
- Document list updates
- Permission status caching
- Error state recovery
````
