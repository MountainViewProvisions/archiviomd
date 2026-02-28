<?php
/**
 * Public Document Index Admin Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['mdsm_save_public_index']) && check_admin_referer('mdsm_save_public_index', 'mdsm_public_index_nonce')) {
    if (current_user_can('manage_options')) {
        // Get mode
        $enabled = isset( $_POST['index_output_mode'] ) && sanitize_text_field( wp_unslash( $_POST['index_output_mode'] ) ) === 'page';
        
        // Get page ID
        $page_id = isset( $_POST['index_page_id'] ) ? absint( $_POST['index_page_id'] ) : 0;
        
        // Get selected documents
        $public_docs = array();
        if ( isset( $_POST['public_docs'] ) && is_array( $_POST['public_docs'] ) ) {
            foreach ( wp_unslash( $_POST['public_docs'] ) as $filename ) {
                $filename              = sanitize_text_field( $filename );
                $public_docs[ $filename ] = true;
            }
        }
        
        // Get descriptions
        $descriptions = array();
        if (isset($_POST) && is_array($_POST)) {
            foreach ( $_POST as $key => $value ) {
                $key   = sanitize_text_field( wp_unslash( $key ) );
                $value = sanitize_text_field( wp_unslash( $value ) );
                if (strpos($key, 'doc_desc_') === 0) {
                    $filename = str_replace('doc_desc_', '', $key);
                    if (!empty($value)) {
                        $descriptions[sanitize_text_field($filename)] = sanitize_text_field($value);
                    }
                }
            }
        }
        
        // Save options
        update_option('mdsm_public_index_enabled', $enabled);
        update_option('mdsm_public_index_page_id', $page_id);
        update_option('mdsm_public_documents', $public_docs);
        update_option('mdsm_document_descriptions', $descriptions);
        
        // Show success message
        add_settings_error(
            'mdsm_public_index',
            'mdsm_public_index_saved',
            __('Public Index settings saved successfully!', 'archiviomd'),
            'success'
        );
    }
}

// Get current settings
$index_enabled = get_option('mdsm_public_index_enabled', false);
$index_slug = get_option('mdsm_public_index_slug', 'docs');
$index_title = get_option('mdsm_public_index_title', 'Documentation Index');
$public_docs = get_option('mdsm_public_documents', array());
$doc_descriptions = get_option('mdsm_document_descriptions', array());

// Get all meta files
$meta_files = mdsm_get_meta_files();
$file_manager = new MDSM_File_Manager();

// Also get custom markdown files
$custom_files = mdsm_get_custom_markdown_files();

// Get index URL if enabled
$index_url = '';
if ($index_enabled && !empty($index_slug)) {
    $index_url = home_url($index_slug);
}
?>

<form method="post" action="" id="mdsm-public-index-form">
    <?php wp_nonce_field('mdsm_save_public_index', 'mdsm_public_index_nonce'); ?>
    <?php settings_errors('mdsm_public_index'); ?>
    
    <div class="mdsm-section-header">
        <h2><?php esc_html_e('Public Document Index', 'archiviomd'); ?></h2>
        <p><?php esc_html_e('Create a public index of selected documentation files. Choose between a pre-generated page or use a shortcode.', 'archiviomd'); ?></p>
    </div>

    <!-- Output Mode Selection -->
    <div class="mdsm-public-index-panel">
    <div class="mdsm-index-mode-section">
        <h3><?php esc_html_e('Output Mode', 'archiviomd'); ?></h3>
        
        <div class="mdsm-option-group">
            <label class="mdsm-radio-label">
                <input type="radio" name="index_output_mode" value="page" <?php checked($index_enabled, true); ?>>
                <div class="mdsm-radio-content">
                    <strong><?php esc_html_e('Display on WordPress Page', 'archiviomd'); ?></strong>
                    <p><?php esc_html_e('Select an existing WordPress page to display the document index. The shortcode will be automatically used.', 'archiviomd'); ?></p>
                </div>
            </label>

            <label class="mdsm-radio-label">
                <input type="radio" name="index_output_mode" value="shortcode" <?php checked($index_enabled, false); ?>>
                <div class="mdsm-radio-content">
                    <strong><?php esc_html_e('Manual Shortcode Placement', 'archiviomd'); ?></strong>
                    <p><?php esc_html_e('Use the shortcode [archiviomd_documents] anywhere you want - in posts, pages, or widgets.', 'archiviomd'); ?></p>
                </div>
            </label>
        </div>
    </div>

    <!-- Page Configuration (only shown when page mode is selected) -->
    <div class="mdsm-page-config-section" id="mdsm-page-config">
        <h3><?php esc_html_e('Page Configuration', 'archiviomd'); ?></h3>
        
        <div class="mdsm-form-group">
            <label for="index_page_id" class="mdsm-form-label">
                <?php esc_html_e('Select Page', 'archiviomd'); ?>
            </label>
            <?php
            $selected_page_id = get_option('mdsm_public_index_page_id', 0);
            $pages = get_pages(array(
                'post_status' => 'publish',
                'sort_column' => 'post_title',
                'sort_order' => 'ASC'
            ));
            ?>
            <select id="index_page_id" name="index_page_id" class="mdsm-select-input">
                <option value=""><?php esc_html_e('-- Select a Page --', 'archiviomd'); ?></option>
                <?php foreach ($pages as $page) : ?>
                    <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($selected_page_id, $page->ID); ?>>
                        <?php echo esc_html($page->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="mdsm-form-help">
                <?php esc_html_e('Select which published page should display the Public Document Index. Add the [archiviomd_documents] shortcode to the selected page.', 'archiviomd'); ?>
            </p>
        </div>

        <?php if ($selected_page_id) : ?>
            <div class="mdsm-index-preview">
                <a href="<?php echo esc_url(get_permalink($selected_page_id)); ?>" target="_blank" class="mdsm-preview-link">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e('View Selected Page', 'archiviomd'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Shortcode Info (only shown when shortcode mode is selected) -->
    <div class="mdsm-shortcode-info-section" id="mdsm-shortcode-info" style="display: none;">
        <h3><?php esc_html_e('Shortcode Usage', 'archiviomd'); ?></h3>
        <div class="mdsm-shortcode-box">
            <code class="mdsm-shortcode-code">[archiviomd_documents]</code>
            <button type="button" class="mdsm-copy-shortcode" data-shortcode="[archiviomd_documents]">
                <span class="dashicons dashicons-admin-page"></span>
                <?php esc_html_e('Copy', 'archiviomd'); ?>
            </button>
        </div>
        <p class="mdsm-form-help">
            <?php esc_html_e('Add this shortcode to any post or page to display the document index.', 'archiviomd'); ?>
        </p>
    </div>

    <!-- Document Selection -->
    <div class="mdsm-document-selection-section">
        <h3><?php esc_html_e('Document Visibility', 'archiviomd'); ?></h3>
        <p class="mdsm-section-description">
            <?php esc_html_e('Select which documents should appear in the public index. Only existing documents can be published.', 'archiviomd'); ?>
        </p>

        <?php foreach ($meta_files as $category => $files) : ?>
            <div class="mdsm-index-category mdsm-category collapsed">
                <div class="mdsm-category-header">
                    <h4 class="mdsm-index-category-title">
                        <span class="dashicons dashicons-category"></span>
                        <?php echo esc_html($category); ?>
                    </h4>
                    <button class="mdsm-collapse-toggle" aria-expanded="false">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                </div>
                <div class="mdsm-category-content">
                    <div class="mdsm-index-file-list">
                    <?php foreach ($files as $file_name => $default_desc) : 
                        $file_info = $file_manager->get_file_info('meta', $file_name);
                        $is_public = isset($public_docs[$file_name]) && $public_docs[$file_name];
                        $custom_desc = isset($doc_descriptions[$file_name]) ? $doc_descriptions[$file_name] : '';
                        $disabled = !$file_info['exists'];
                    ?>
                        <div class="mdsm-index-file-row <?php echo $disabled ? 'mdsm-file-disabled' : ''; ?>">
                            <div class="mdsm-file-checkbox-group">
                                <label class="mdsm-checkbox-inline">
                                    <input 
                                        type="checkbox" 
                                        name="public_docs[]" 
                                        value="<?php echo esc_attr($file_name); ?>"
                                        <?php checked($is_public, true); ?>
                                        <?php disabled($disabled, true); ?>
                                        class="mdsm-doc-checkbox"
                                    >
                                    <span class="mdsm-file-checkbox-label">
                                        <?php echo esc_html($file_name); ?>
                                        <?php if ($disabled) : ?>
                                            <span class="mdsm-file-status-badge mdsm-status-missing"><?php esc_html_e('Not Created', 'archiviomd'); ?></span>
                                        <?php else : ?>
                                            <span class="mdsm-file-status-badge mdsm-status-available"><?php esc_html_e('Available', 'archiviomd'); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            </div>
                            <div class="mdsm-file-description-group">
                                <input 
                                    type="text" 
                                    name="doc_desc_<?php echo esc_attr($file_name); ?>" 
                                    value="<?php echo esc_attr($custom_desc); ?>" 
                                    placeholder="<?php echo esc_attr($default_desc); ?>"
                                    class="mdsm-description-input"
                                    <?php disabled($disabled, true); ?>
                                >
                                <small class="mdsm-input-hint"><?php esc_html_e('Optional: Custom description for public display', 'archiviomd'); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Custom Markdown Category -->
        <?php 
        $custom_files = mdsm_get_custom_markdown_files();
        if (!empty($custom_files)) :
        ?>
            <div class="mdsm-index-category mdsm-category collapsed">
                <div class="mdsm-category-header">
                    <h4 class="mdsm-index-category-title">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e('Custom Markdown', 'archiviomd'); ?>
                    </h4>
                    <button class="mdsm-collapse-toggle" aria-expanded="false">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                </div>
                <div class="mdsm-category-content">
                    <div class="mdsm-index-file-list">
                    <?php foreach ($custom_files as $file_name => $default_desc) : 
                        $file_info = $file_manager->get_file_info('meta', $file_name);
                        $is_public = isset($public_docs[$file_name]) && $public_docs[$file_name];
                        $custom_desc = isset($doc_descriptions[$file_name]) ? $doc_descriptions[$file_name] : '';
                        $disabled = !$file_info['exists'];
                    ?>
                        <div class="mdsm-index-file-row <?php echo $disabled ? 'mdsm-file-disabled' : ''; ?>">
                            <div class="mdsm-file-checkbox-group">
                                <label class="mdsm-checkbox-inline">
                                    <input 
                                        type="checkbox" 
                                        name="public_docs[]" 
                                        value="<?php echo esc_attr($file_name); ?>"
                                        <?php checked($is_public, true); ?>
                                        <?php disabled($disabled, true); ?>
                                        class="mdsm-doc-checkbox"
                                    >
                                    <span class="mdsm-file-checkbox-label">
                                        <?php echo esc_html($file_name); ?>
                                        <?php if ($disabled) : ?>
                                            <span class="mdsm-file-status-badge mdsm-status-missing"><?php esc_html_e('Not Created', 'archiviomd'); ?></span>
                                        <?php else : ?>
                                            <span class="mdsm-file-status-badge mdsm-status-available"><?php esc_html_e('Available', 'archiviomd'); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            </div>
                            <div class="mdsm-file-description-group">
                                <input 
                                    type="text" 
                                    name="doc_desc_<?php echo esc_attr($file_name); ?>" 
                                    value="<?php echo esc_attr($custom_desc); ?>" 
                                    placeholder="<?php echo esc_attr($default_desc ? $default_desc : 'Custom markdown file'); ?>"
                                    class="mdsm-description-input"
                                    <?php disabled($disabled, true); ?>
                                >
                                <small class="mdsm-input-hint"><?php esc_html_e('Optional: Custom description for public display', 'archiviomd'); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Save Button -->
    <div class="mdsm-button-group">
        <button type="submit" name="mdsm_save_public_index" id="save-public-index" class="button button-primary button-hero">
            <span class="dashicons dashicons-saved"></span>
            <?php esc_html_e('Save Public Index Settings', 'archiviomd'); ?>
        </button>
    </div>
</div>
</form>
