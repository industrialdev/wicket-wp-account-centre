<?php
/**
 * Documents list partial.
 */
if (!isset($org_id)) {
    $org_id = '';
}

$documents ??= [];
$category ??= '';
$notice ??= null;
?>
<div class="documents-list">
	<?php if ($notice) : ?>
		<div class="notifications-wt_inline-container">
			<div class="notification notification-<?php echo esc_attr($notice['type']); ?> notification-wt_inline">
				<div class="notification-icon">
					<?php
                    $icon = match ($notice['type']) {
                        'success' => '✓',
                        'error' => '✕',
                        'warning' => '!',
                        default => 'i'
                    };
	    echo esc_html($icon);
	    ?>
				</div>
				<div class="notification-content">
					<div class="notification-message"><?php echo wp_kses_post($notice['message']); ?></div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<div class="documents-toolbar wt_mb-4">
		<form class="document-upload-form"
		      ds-post="<?php echo esc_url(rest_url('org-management/v1/documents/upload')); ?>"
		      ds-target="#documents-list-container"
		      ds-swap="innerHTML"
		      enctype="multipart/form-data">
			<input type="hidden" name="org_id" value="<?php echo esc_attr($org_id); ?>">
			<input type="hidden" name="category" value="<?php echo esc_attr($category); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('org_management_document_upload_' . $org_id)); ?>">

			<div class="grid wt_grid-cols-1 md_wt_grid-cols-12 wt_gap-4 wt_items-end">
				<div class="md:col-span-5">
					<label for="document_file" class="wt_block wt_text-sm wt_font-medium wt_text-gray-700 wt_mb-1">
						<?php esc_html_e('Select Document', 'wicket-acc'); ?>
					</label>
					<input type="file"
					       id="document_file"
					       name="document"
					       class="wt_block wt_w-full wt_text-sm wt_text-gray-500
					              file:mr-4 file:py-2 file:px-4
					              file:rounded file:border-0
					              file:text-sm file:font-semibold
					              file:bg-blue-50 file:text-blue-700
					              hover:file:bg-blue-100"
					       required>
				</div>

				<div class="md:col-span-4">
					<label for="document_title" class="wt_block wt_text-sm wt_font-medium wt_text-gray-700 wt_mb-1">
						<?php esc_html_e('Document Title', 'wicket-acc'); ?>
					</label>
					<input type="text"
					       id="document_title"
					       name="title"
					       class="wt_w-full wt_px-3 wt_py-2 wt_border wt_border-gray-300 wt_rounded-md wt_shadow-xs wt_focus_outline-hidden wt_focus_ring-2 wt_focus_ring-blue-500 wt_focus_border-blue-500 sm_wt_text-sm"
					       placeholder="<?php esc_attr_e('Enter document title', 'wicket-acc'); ?>">
				</div>

				<div class="md:col-span-2">
					<button type="submit" class="wt_w-full wt_flex wt_justify-center wt_py-2 wt_px-4 wt_border wt_border-transparent wt_rounded-md wt_shadow-xs wt_text-sm wt_font-medium wt_text-white wt_bg-blue-600 wt_hover_bg-blue-700 wt_focus_outline-hidden wt_focus_ring-2 wt_focus_ring-offset-2 wt_focus_ring-blue-500"><?php esc_html_e('Upload Document', 'wicket-acc'); ?></button>
				</div>
			</div>

			<div class="wt_mt-2">
				<label for="document_description" class="wt_block wt_text-sm wt_font-medium wt_text-gray-700 wt_mb-1">
					<?php esc_html_e('Description (Optional)', 'wicket-acc'); ?>
				</label>
				<textarea id="document_description"
				          name="description"
				          rows="2"
				          class="wt_shadow-xs wt_focus_ring-2 wt_focus_ring-blue-500 wt_focus_border-blue-500 wt_mt-1 wt_block wt_w-full sm_wt_text-sm wt_border wt_border-gray-300 wt_rounded-md wt_p-2"
				          placeholder="<?php esc_attr_e('Enter document description', 'wicket-acc'); ?>"></textarea>
			</div>
		</form>
	</div>

	<?php if (!empty($documents)) : ?>
		<div class="documents-wt_grid wt_mt-6">
			<?php foreach ($documents as $document) : ?>
				<div class="document-card wt_border wt_border-gray-200 wt_rounded-md wt_p-4 wt_mb-3 wt_flex wt_items-center wt_justify-between" data-document-id="<?php echo esc_attr($document['id']); ?>">
					<div class="document-info wt_flex wt_items-center">
						<div class="document-icon wt_mr-3">
							<?php
	            // Show appropriate icon based on file type
	            $file_ext = pathinfo($document['filename'], PATHINFO_EXTENSION);
			    $file_ext_lower = strtolower($file_ext);
			    $icon_class = 'document-file-icon';

			    switch ($file_ext_lower) {
			        case 'pdf':
			            $icon_class = 'document-pdf-icon';
			            break;
			        case 'doc':
			        case 'docx':
			            $icon_class = 'document-word-icon';
			            break;
			        case 'xls':
			        case 'xlsx':
			            $icon_class = 'document-excel-icon';
			            break;
			        case 'jpg':
			        case 'jpeg':
			        case 'png':
			        case 'gif':
			            $icon_class = 'document-image-icon';
			            break;
			        default:
			            $icon_class = 'document-generic-icon';
			    }
			    ?>
							<span class="<?php echo esc_attr($icon_class); ?>" style="font-size: 24px;">📄</span>
						</div>
						<div class="document-details">
							<h3 class="wt_font-medium wt_text-gray-900"><?php echo esc_html($document['title']); ?></h3>
							<p class="wt_text-sm wt_text-gray-500"><?php echo esc_html($document['filename']); ?> (<?php echo esc_html(size_format($document['filesize'])); ?>)</p>
							<?php if (!empty($document['description'])) : ?>
								<p class="wt_text-sm wt_text-gray-600 wt_mt-1"><?php echo esc_html($document['description']); ?></p>
							<?php endif; ?>
							<p class="wt_text-xs wt_text-gray-400 wt_mt-1">
								<?php echo esc_html(date_i18n(get_option('date_format'), strtotime($document['upload_date']))); ?>
								<?php if (!empty($document['category'])) : ?>
									<span class="wt_ml-2 wt_px-2 wt_py-0.5 wt_bg-gray-100 wt_rounded-full wt_text-xs"><?php echo esc_html($document['category']); ?></span>
								<?php endif; ?>
							</p>
						</div>
					</div>
					<div class="document-actions wt_flex wt_gap-2">
						<a href="<?php echo esc_url($document['url']); ?>"
						   target="_blank"
						   class="wt_text-blue-600 wt_hover_text-blue-900 wt_text-sm wt_font-medium"
						   download>
							<?php esc_html_e('Download', 'wicket-acc'); ?>
						</a>
						|
						<button
							ds-delete="<?php echo esc_url(rest_url('org-management/v1/documents/delete/' . $document['id'] . '?org_id=' . $org_id . '&category=' . $category)); ?>"
							ds-target="#documents-list-container"
							ds-swap="innerHTML"
							ds-confirm="<?php esc_attr_e('Are you sure you want to delete this document?', 'wicket-acc'); ?>"
							class="wt_text-red-600 wt_hover_text-red-900 wt_text-sm wt_font-medium">
							<?php esc_html_e('Delete', 'wicket-acc'); ?>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="empty-documents-state wt_text-center wt_py-8">
			<div class="wt_text-gray-400 wt_text-5xl wt_mb-4">📁</div>
			<h3 class="wt_text-lg wt_font-medium wt_text-gray-900 wt_mb-1"><?php esc_html_e('No documents found', 'wicket-acc'); ?></h3>
			<p class="wt_text-gray-500"><?php esc_html_e('Upload your first document using the form above.', 'wicket-acc'); ?></p>
		</div>
	<?php endif; ?>
</div>
