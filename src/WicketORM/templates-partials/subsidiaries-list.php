<?php
/**
 * Template Partial for displaying the list of subsidiaries.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="subsidiaries-list-container" class="subsidiaries-container">
	<?php if (isset($notice)) : ?>
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

	<?php if (!empty($subsidiaries)) : ?>
		<div class="subsidiaries-wt_grid">
			<?php foreach ($subsidiaries as $subsidiary) : ?>
				<div class="subsidiary-card">
					<div class="subsidiary-info">
						<h3 class="subsidiary-name"><?php echo esc_html($subsidiary['name']); ?></h3>
						<p class="subsidiary-type"><?php echo esc_html(ucfirst($subsidiary['type'])); ?></p>
						<p class="subsidiary-status">
							<span class="status-badge status-<?php echo esc_attr($subsidiary['status']); ?>">
								<?php echo esc_html(ucfirst($subsidiary['status'])); ?>
							</span>
						</p>
					</div>
					<div class="subsidiary-actions">
						<form method="POST"
							  action="?action=hypermedia&template=subsidiaries-remove"
							  data-on:submit="submit->post('<?php echo \WicketORM\Helpers\template_url(); ?>subsidiaries-remove&org_id=<?php echo esc_attr($org_id); ?>&subsidiary_org_id=<?php echo esc_attr($subsidiary['id']); ?>')
							  data-on:success="innerHTML->#subsidiaries-list-container">
							<input type="hidden" name="org_id" value="<?php echo esc_attr($org_id); ?>">
							<input type="hidden" name="subsidiary_org_id" value="<?php echo esc_attr($subsidiary['id']); ?>">
							<?php wp_nonce_field('org_management_subsidiary_remove_' . $org_id, '_wpnonce'); ?>
							<button type="submit"
									class="button button--secondary component-button"
									data-on:click="confirm('<?php echo esc_js(sprintf(__('Are you sure you want to remove %s as a subsidiary?', 'wicket-acc'), $subsidiary['name'])); ?>')">
								<?php esc_html_e('Remove', 'wicket-acc'); ?>
							</button>
						</form>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="no-subsidiaries">
			<p><?php esc_html_e('No subsidiaries found for this organization.', 'wicket-acc'); ?></p>
		</div>
	<?php endif; ?>

	<!-- Add Subsidiary Section -->
	<div class="add-subsidiary-section wt_mt-6">
		<h3><?php esc_html_e('Add Subsidiary', 'wicket-acc'); ?></h3>

		<!-- Search for Organization -->
		<div class="subsidiary-search">
			<label for="subsidiary-search" class="form-label">
				<?php esc_html_e('Search for an organization to add as a subsidiary:', 'wicket-acc'); ?>
			</label>
			<div class="search-input-group">
				<input type="text"
					   id="subsidiary-search"
					   name="subsidiary_search"
					   class="form-control"
					   placeholder="<?php esc_attr_e('Type organization name...', 'wicket-acc'); ?>"
					   data-on:input="input->debounce(500ms)->get('<?php echo \WicketORM\Helpers\template_url(); ?>subsidiaries-search&org_id=<?php echo esc_attr($org_id); ?>&search=event.target.value')"
					   data-init="@get('<?php echo \WicketORM\Helpers\template_url(); ?>subsidiaries-search&org_id=<?php echo esc_attr($org_id); ?>&search=event.target.value')">
				<div id="subsidiary-search-results" class="search-results-dropdown"></div>
			</div>
		</div>

		<!-- Bulk Upload Section -->
		<div class="bulk-upload-section wt_mt-6">
			<h4><?php esc_html_e('Bulk Upload Subsidiaries', 'wicket-acc'); ?></h4>
			<p class="description"><?php esc_html_e('Upload an Excel spreadsheet to add multiple subsidiaries at once.', 'wicket-acc'); ?></p>

			<form method="POST"
				  action="?action=hypermedia&template=subsidiaries-bulk-upload"
				  data-on:submit="submit->post('<?php echo \WicketORM\Helpers\template_url(); ?>subsidiaries-bulk-upload&org_id=<?php echo esc_attr($org_id); ?>')"
				  data-on:success="innerHTML->#subsidiaries-list-container"
				  enctype="multipart/form-data">
				<input type="hidden" name="org_id" value="<?php echo esc_attr($org_id); ?>">
				<?php wp_nonce_field('org_management_subsidiary_bulk_upload_' . $org_id, '_wpnonce'); ?>

				<div class="file-upload-group">
					<label for="bulk-file" class="form-label">
						<?php esc_html_e('Select Excel File (.xlsx, .xls):', 'wicket-acc'); ?>
					</label>
					<input type="file"
						   id="bulk-file"
						   name="bulk_file"
						   class="form-control-file"
						   accept=".xlsx,.xls"
						   required>
				</div>

				<button type="submit" class="button button--primary wt_mt-3 component-button"><?php esc_html_e('Upload and Process', 'wicket-acc'); ?></button>
			</form>
		</div>
	</div>
</div>

<script>
// Datastar script for handling subsidiary search results
document.addEventListener('DOMContentLoaded', function() {
	// Handle search results display
	Datastar.signals.setSignalValue('subsidiarySearchResults', '');

	// Listen for search results updates
	Datastar.signals.getSignalValue('subsidiarySearchResults').subscribe(function(results) {
		const resultsContainer = document.getElementById('subsidiary-search-results');
		if (results && results.candidates && results.candidates.length > 0) {
			let html = '<div class="search-results">';
			results.candidates.forEach(function(candidate) {
				html += `
					<div class="search-result-item">
						<div class="candidate-info">
							<strong>${candidate.name}</strong>
							<span class="candidate-type">${candidate.type}</span>
						</div>
						<button class="button button--small button--primary component-button"
								onclick="addSubsidiary('${candidate.id}', '${candidate.name.replace(/'/g, "\\'")}')"><?php esc_html_e('Add', 'wicket-acc'); ?></button>
					</div>
				`;
			});
			html += '</div>';
			resultsContainer.innerHTML = html;
		} else {
			resultsContainer.innerHTML = '<div class="no-results"><?php esc_html_e('No organizations found.', 'wicket-acc'); ?></div>';
		}
	});
});

// Function to add a subsidiary
function addSubsidiary(subsidiaryId, subsidiaryName) {
	if (confirm('<?php echo esc_js(__('Are you sure you want to add this subsidiary?', 'wicket-acc')); ?>')) {
		// Create form data
		const formData = new FormData();
		formData.append('org_id', '<?php echo esc_js($org_id); ?>');
		formData.append('subsidiary_org_id', subsidiaryId);
		formData.append('_wpnonce', '<?php echo wp_create_nonce('org_management_subsidiary_add_' . $org_id); ?>');

		// Send request via Datastar
		Datastar.fetch('<?php echo esc_url(rest_url('wicket/orm/v1/subsidiaries/add')); ?>', {
			method: 'POST',
			body: formData,
			headers: {
				'Content-Type': 'multipart/form-data'
			}
		}).then(response => {
			if (response.ok) {
				return response.text();
			}
			throw new Error('Request failed');
		}).then(html => {
			document.getElementById('subsidiaries-list-container').innerHTML = html;
		}).catch(error => {
			console.error('Error adding subsidiary:', error);
		});
	}
}
</script>
