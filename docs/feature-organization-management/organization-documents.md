# Organization Documents Block

## Overview
The Organization Documents block allows organization managers to upload, view, and delete documents associated with an organization membership record in the MDP.

## Block Details
- **Slug**: `wicket-ac/organization-documents`
- **Template**: `templates-wicket/blocks/account-centre/organization-management.php` (when in documents mode)

## Core Functionality

### 1. Document Listing
The block displays a list of all documents currently attached to the organization's membership record.
- **Service**: `WACC()->Mdp()->Membership()->getOrganizationMemberships()` (includes documents).
- **Display**: Shows document name, upload date, and a download/view link.

### 2. File Uploads
Managers can upload new documents directly from the block:
- **Storage**: Files are temporarily stored in the WordPress `uploads/organization-documents/` directory before being registered in the MDP.
- **Support**: Supports single and multiple file uploads (depending on block configuration).
- **Service**: `WACC()->Mdp()->Membership()->assignPersonToOrgMembership()` (logic includes registering associated documents).

### 3. Document Deletion
Managers can remove documents that are no longer relevant.
- **Service**: `WACC()->Mdp()->Membership()->unassignPersonFromOrgMembership()` (cleanup logic for documents).

## Technical Implementation

### Core Services
- `Mdp\Membership`: API interaction for managing memberships and their associated documents.
- `OrganizationRoster`: Logic for identifying the correct membership record to associate documents with.

### Dynamic Interaction
The block uses **Datastar** for a modern, AJAX-based experience:
- **Uploads**: Performed in the background without a full page refresh.
- **Deletes**: Real-time removal of document cards from the UI upon success.

## ACF Configuration

| Field | Description |
|       |             |
| `max_file_size` | The maximum size (in MB) for individual document uploads. |
| `allowed_mime_types` | A list of allowed file types (PDF, JPG, DOCX, etc.). |
| `max_files_per_upload` | Limits the number of files a user can upload at once. |

## Access Control
Permissions are determined by organization-level roles in the MDP:
- **Viewing**: Any authenticated user associated with the organization.
- **Uploading/Deleting**: Restricted to `administrator`, `membership_manager`, or `membership_owner`.

## Security Best Practices
- **MIME Type Validation**: Always verify the file type on the server before processing.
- **Nonce Protection**: Form submissions for document management must include a valid WordPress nonce.
- **Organization Boundary**: Ensure the document being accessed/deleted belongs to the organization specified in the current context.
