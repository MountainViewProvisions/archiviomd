<?php
/**
 * Admin Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$file_manager = new MDSM_File_Manager();
$sitemap_generator = new MDSM_Sitemap_Generator();

$meta_files = mdsm_get_meta_files();
$seo_files = mdsm_get_seo_files();
$sitemap_info = $sitemap_generator->get_sitemap_info();

$auto_update = get_option('mdsm_auto_update_sitemap', false);
$sitemap_type = get_option('mdsm_sitemap_type', 'small');

// Count existing files
$meta_exists = $file_manager->get_existing_files_count('meta');
$meta_total = 0;
foreach ($meta_files as $category => $files) {
    $meta_total += count($files);
}
// Add custom markdown files to total
$custom_markdown_files = mdsm_get_custom_markdown_files();
$meta_total += count($custom_markdown_files);

$seo_exists = $file_manager->get_existing_files_count('seo');
$seo_total = count($seo_files);
?>

<div class="wrap mdsm-admin-wrap">
    <h1 class="mdsm-page-title">
        <span class="dashicons dashicons-media-document"></span>
        <?php esc_html_e('Meta Documentation & SEO Manager', 'archiviomd'); ?>
    </h1>
    
    <div class="mdsm-header-info">
        <p class="mdsm-description">
            <?php esc_html_e('Manage your site\'s meta-documentation files, SEO configuration files, and XML sitemaps from one central location.', 'archiviomd'); ?>
        </p>
    </div>

    <!-- Search/Filter Bar -->
    <div class="mdsm-search-bar">
        <input type="text" id="mdsm-search" class="mdsm-search-input" placeholder="<?php esc_attr_e('Search files...', 'archiviomd'); ?>">
        <span class="mdsm-search-icon dashicons dashicons-search"></span>
    </div>

    <!-- Tabs Navigation -->
    <div class="mdsm-tabs">
        <button class="mdsm-tab-button active" data-tab="meta-docs">
            <span class="dashicons dashicons-media-document"></span>
            <?php esc_html_e('Meta Documentation', 'archiviomd'); ?>
            <span class="mdsm-badge"><?php echo esc_html($meta_exists . '/' . $meta_total); ?></span>
        </button>
        <button class="mdsm-tab-button" data-tab="seo-files">
            <span class="dashicons dashicons-search"></span>
            <?php esc_html_e('SEO Files', 'archiviomd'); ?>
            <span class="mdsm-badge"><?php echo esc_html($seo_exists . '/' . $seo_total); ?></span>
        </button>
        <button class="mdsm-tab-button" data-tab="sitemaps">
            <span class="dashicons dashicons-networking"></span>
            <?php esc_html_e('Sitemaps', 'archiviomd'); ?>
        </button>
        <button class="mdsm-tab-button" data-tab="public-index">
            <span class="dashicons dashicons-admin-page"></span>
            <?php esc_html_e('Public Index', 'archiviomd'); ?>
        </button>
    </div>

    <!-- Tab Content: Meta Documentation -->
    <div class="mdsm-tab-content active" id="tab-meta-docs">
        <div class="mdsm-section-header">
            <h2><?php esc_html_e('Meta Documentation Files', 'archiviomd'); ?></h2>
            <p><?php esc_html_e('These Markdown files provide comprehensive documentation about your site, organized by category.', 'archiviomd'); ?></p>
        </div>

        <?php foreach ($meta_files as $category => $files) : ?>
            <div class="mdsm-category collapsed">
                <div class="mdsm-category-header">
                    <h3>
                        <span class="dashicons dashicons-category"></span>
                        <?php echo esc_html($category); ?>
                    </h3>
                    <button class="mdsm-collapse-toggle" aria-expanded="false">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                </div>
                
                <div class="mdsm-category-content">
                    <div class="mdsm-file-grid">
                        <?php foreach ($files as $file_name => $description) : 
                            $file_info = $file_manager->get_file_info('meta', $file_name);
                        ?>
                            <div class="mdsm-file-card" data-filename="<?php echo esc_attr($file_name); ?>" data-description="<?php echo esc_attr($description); ?>">
                                <div class="mdsm-file-header">
                                    <div class="mdsm-file-title">
                                        <span class="mdsm-file-icon dashicons dashicons-media-text"></span>
                                        <span class="mdsm-file-name"><?php echo esc_html($file_name); ?></span>
                                        <?php if ($file_info['exists']) : ?>
                                            <span class="mdsm-status-badge mdsm-status-exists"><?php esc_html_e('Active', 'archiviomd'); ?></span>
                                        <?php else : ?>
                                            <span class="mdsm-status-badge mdsm-status-empty"><?php esc_html_e('Empty', 'archiviomd'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <button class="mdsm-edit-button" data-file-type="meta" data-file-name="<?php echo esc_attr($file_name); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                        <?php esc_html_e('Edit', 'archiviomd'); ?>
                                    </button>
                                </div>
                                
                                <div class="mdsm-file-description">
                                    <?php echo esc_html($description); ?>
                                </div>
                                
                                <div class="mdsm-file-meta">
                                    <div class="mdsm-file-location">
                                        <span class="dashicons dashicons-location"></span>
                                        <span><?php echo esc_html($file_info['location']); ?></span>
                                    </div>
                                    
                                    <?php 
                                    // Display metadata if exists
                                    if ($file_info['exists'] && isset($file_info['metadata']) && !empty($file_info['metadata']['uuid'])) : 
                                        $metadata = $file_info['metadata'];
                                    ?>
                                        <div class="mdsm-file-metadata">
                                            <div class="mdsm-metadata-row">
                                                <span class="mdsm-metadata-label">
                                                    <span class="dashicons dashicons-tag"></span>
                                                    Document ID:
                                                </span>
                                                <code class="mdsm-metadata-value mdsm-uuid" title="<?php echo esc_attr($metadata['uuid']); ?>">
                                                    <?php echo esc_html($metadata['uuid']); ?>
                                                </code>
                                            </div>
                                            <?php if (!empty($metadata['modified_at'])) : 
                                                $formatted_time = gmdate('Y-m-d H:i:s \U\T\C', strtotime($metadata['modified_at']));
                                            ?>
                                                <div class="mdsm-metadata-row">
                                                    <span class="mdsm-metadata-label">
                                                        <span class="dashicons dashicons-clock"></span>
                                                        Last Modified:
                                                    </span>
                                                    <code class="mdsm-metadata-value">
                                                        <?php echo esc_html($formatted_time); ?>
                                                    </code>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($metadata['checksum'])) : 
                                                // Parse the packed hash format to get algorithm and mode
                                                $hash_info = MDSM_Hash_Helper::unpack($metadata['checksum']);
                                                $algo_label = MDSM_Hash_Helper::algorithm_label($hash_info['algorithm']);
                                                $mode_label = ($hash_info['mode'] === 'hmac') ? 'HMAC-' : '';
                                                $display_label = $mode_label . $algo_label;
                                            ?>
                                                <div class="mdsm-metadata-row">
                                                    <span class="mdsm-metadata-label">
                                                        <span class="dashicons dashicons-shield"></span>
                                                        <?php echo esc_html($display_label); ?>:
                                                    </span>
                                                    <code class="mdsm-metadata-value mdsm-checksum" title="<?php echo esc_attr($metadata['checksum']); ?>">
                                                        <?php echo esc_html(substr($hash_info['hash'], 0, 16) . '...'); ?>
                                                    </code>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($metadata['changelog'])) : ?>
                                                <button class="mdsm-view-changelog" data-file-name="<?php echo esc_attr($file_name); ?>">
                                                    <span class="dashicons dashicons-list-view"></span>
                                    View Change Log (<?php echo absint( count( $metadata['changelog'] ) ); ?> entries)
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($file_info['exists'] && $file_info['url']) : 
                                        $html_renderer = new MDSM_HTML_Renderer();
                                        $html_exists = $html_renderer->html_file_exists('meta', $file_name);
                                        $html_url = $html_exists ? $html_renderer->get_html_file_url($html_renderer->get_html_filename($file_name)) : '';
                                    ?>
                                        <div class="mdsm-file-actions">
                                            <a href="<?php echo esc_url($file_info['url']); ?>" target="_blank" class="mdsm-view-link">
                                                <span class="dashicons dashicons-external"></span>
                                                <?php esc_html_e('View MD', 'archiviomd'); ?>
                                            </a>
                                            <button class="mdsm-copy-link" data-url="<?php echo esc_attr($file_info['url']); ?>" title="<?php esc_attr_e('Copy MD Link', 'archiviomd'); ?>">
                                                <span class="dashicons dashicons-admin-links"></span>
                                                <?php esc_html_e('Copy MD Link', 'archiviomd'); ?>
                                            </button>
                                            <?php if ($html_exists) : ?>
                                                <a href="<?php echo esc_url($html_url); ?>" target="_blank" class="mdsm-view-link mdsm-html-link">
                                                    <span class="dashicons dashicons-media-document"></span>
                                                    <?php esc_html_e('View HTML', 'archiviomd'); ?>
                                                </a>
                                                <button class="mdsm-copy-html-link" data-url="<?php echo esc_attr($html_url); ?>" data-file-type="meta" data-file-name="<?php echo esc_attr($file_name); ?>" title="<?php esc_attr_e('Copy HTML Link', 'archiviomd'); ?>">
                                                    <span class="dashicons dashicons-admin-links"></span>
                                                    <?php esc_html_e('Copy HTML', 'archiviomd'); ?>
                                                </button>
                                            <?php else : ?>
                                                <button class="mdsm-generate-html" data-file-type="meta" data-file-name="<?php echo esc_attr($file_name); ?>" title="<?php esc_attr_e('Generate HTML Version', 'archiviomd'); ?>">
                                                    <span class="dashicons dashicons-media-code"></span>
                                                    <?php esc_html_e('Generate HTML', 'archiviomd'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
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
        ?>
        <div class="mdsm-category collapsed">
            <div class="mdsm-category-header">
                <h3>
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e('Custom Markdown', 'archiviomd'); ?>
                </h3>
                <button class="mdsm-collapse-toggle" aria-expanded="false">
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </button>
            </div>
            
            <div class="mdsm-category-content">
                <div class="mdsm-custom-markdown-controls">
                    <button type="button" id="add-custom-markdown">
                        <?php esc_html_e('Custom Markdown', 'archiviomd'); ?>
                    </button>
                </div>
                
                <div class="mdsm-file-grid">
                    <?php if (empty($custom_files)) : ?>
                        <p class="mdsm-empty-message"><?php esc_html_e('No custom markdown files yet. Click "Custom Markdown" to create one.', 'archiviomd'); ?></p>
                    <?php else : ?>
                        <?php foreach ($custom_files as $file_name => $description) : 
                            $file_info = $file_manager->get_file_info('meta', $file_name);
                        ?>
                            <div class="mdsm-file-card mdsm-custom-file-card" data-filename="<?php echo esc_attr($file_name); ?>" data-description="<?php echo esc_attr($description); ?>">
                                <div class="mdsm-file-header">
                                    <div class="mdsm-file-title">
                                        <span class="mdsm-file-icon dashicons dashicons-media-text"></span>
                                        <span class="mdsm-file-name"><?php echo esc_html($file_name); ?></span>
                                        <?php if ($file_info['exists']) : ?>
                                            <span class="mdsm-status-badge mdsm-status-exists"><?php esc_html_e('Active', 'archiviomd'); ?></span>
                                        <?php else : ?>
                                            <span class="mdsm-status-badge mdsm-status-empty"><?php esc_html_e('Empty', 'archiviomd'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mdsm-custom-file-actions">
                                        <button class="mdsm-edit-button" data-file-type="meta" data-file-name="<?php echo esc_attr($file_name); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                            <?php esc_html_e('Edit', 'archiviomd'); ?>
                                        </button>
                                        <button class="mdsm-delete-custom-file" data-file-name="<?php echo esc_attr($file_name); ?>" title="<?php esc_attr_e('Delete custom file entry', 'archiviomd'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mdsm-file-description">
                                    <?php echo esc_html($description ? $description : 'Custom markdown file'); ?>
                                </div>
                                
                                <div class="mdsm-file-meta">
                                    <div class="mdsm-file-location">
                                        <span class="dashicons dashicons-location"></span>
                                        <span><?php echo esc_html($file_info['location']); ?></span>
                                    </div>
                                    <?php if ($file_info['exists'] && $file_info['url']) : 
                                        $html_renderer = new MDSM_HTML_Renderer();
                                        $html_exists = $html_renderer->html_file_exists('meta', $file_name);
                                        $html_url = $html_exists ? $html_renderer->get_html_file_url($html_renderer->get_html_filename($file_name)) : '';
                                    ?>
                                        <div class="mdsm-file-actions">
                                            <a href="<?php echo esc_url($file_info['url']); ?>" target="_blank" class="mdsm-view-link">
                                                <span class="dashicons dashicons-external"></span>
                                                <?php esc_html_e('View MD', 'archiviomd'); ?>
                                            </a>
                                            <button class="mdsm-copy-link" data-url="<?php echo esc_attr($file_info['url']); ?>" title="<?php esc_attr_e('Copy MD Link', 'archiviomd'); ?>">
                                                <span class="dashicons dashicons-admin-links"></span>
                                                <?php esc_html_e('Copy MD Link', 'archiviomd'); ?>
                                            </button>
                                            <?php if ($html_exists) : ?>
                                                <a href="<?php echo esc_url($html_url); ?>" target="_blank" class="mdsm-view-link mdsm-html-link">
                                                    <span class="dashicons dashicons-media-document"></span>
                                                    <?php esc_html_e('View HTML', 'archiviomd'); ?>
                                                </a>
                                                <button class="mdsm-copy-html-link" data-url="<?php echo esc_attr($html_url); ?>" data-file-type="meta" data-file-name="<?php echo esc_attr($file_name); ?>" title="<?php esc_attr_e('Copy HTML Link', 'archiviomd'); ?>">
                                                    <span class="dashicons dashicons-admin-links"></span>
                                                    <?php esc_html_e('Copy HTML', 'archiviomd'); ?>
                                                </button>
                                            <?php else : ?>
                                                <button class="mdsm-generate-html" data-file-type="meta" data-file-name="<?php echo esc_attr($file_name); ?>" title="<?php esc_attr_e('Generate HTML Version', 'archiviomd'); ?>">
                                                    <span class="dashicons dashicons-media-document"></span>
                                                    <?php esc_html_e('Generate HTML', 'archiviomd'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Content: SEO Files -->
    <div class="mdsm-tab-content" id="tab-seo-files">
        <div class="mdsm-section-header">
            <h2><?php esc_html_e('SEO & Crawling Files', 'archiviomd'); ?></h2>
            <p><?php esc_html_e('Control how search engines and AI crawlers interact with your site.', 'archiviomd'); ?></p>
        </div>

        <div class="mdsm-file-grid">
            <?php foreach ($seo_files as $file_name => $description) : 
                $file_info = $file_manager->get_file_info('seo', $file_name);
            ?>
                <div class="mdsm-file-card mdsm-file-card-large" data-filename="<?php echo esc_attr($file_name); ?>" data-description="<?php echo esc_attr($description); ?>">
                    <div class="mdsm-file-header">
                        <div class="mdsm-file-title">
                            <span class="mdsm-file-icon dashicons dashicons-admin-generic"></span>
                            <span class="mdsm-file-name"><?php echo esc_html($file_name); ?></span>
                            <?php if ($file_info['exists']) : ?>
                                <span class="mdsm-status-badge mdsm-status-exists"><?php esc_html_e('Active', 'archiviomd'); ?></span>
                            <?php else : ?>
                                <span class="mdsm-status-badge mdsm-status-empty"><?php esc_html_e('Empty', 'archiviomd'); ?></span>
                            <?php endif; ?>
                        </div>
                        <button class="mdsm-edit-button" data-file-type="seo" data-file-name="<?php echo esc_attr($file_name); ?>">
                            <span class="dashicons dashicons-edit"></span>
                            <?php esc_html_e('Edit', 'archiviomd'); ?>
                        </button>
                    </div>
                    
                    <div class="mdsm-file-description">
                        <?php echo esc_html($description); ?>
                    </div>
                    
                    <div class="mdsm-file-meta">
                        <div class="mdsm-file-location">
                            <span class="dashicons dashicons-location"></span>
                            <span><?php echo esc_html($file_info['location']); ?></span>
                        </div>
                        <?php if ($file_info['exists'] && $file_info['url']) : ?>
                            <div class="mdsm-file-actions">
                                <a href="<?php echo esc_url($file_info['url']); ?>" target="_blank" class="mdsm-view-link">
                                    <span class="dashicons dashicons-external"></span>
                                    <?php esc_html_e('View', 'archiviomd'); ?>
                                </a>
                                <button class="mdsm-copy-link" data-url="<?php echo esc_attr($file_info['url']); ?>">
                                    <span class="dashicons dashicons-admin-links"></span>
                                    <?php esc_html_e('Copy Link', 'archiviomd'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($file_name === 'robots.txt' && $file_info['exists'] && $file_info['location'] !== '/site-root/') : ?>
                        <div class="mdsm-warning-notice">
                            <span class="dashicons dashicons-warning"></span>
                            <strong>⚠️ Important:</strong> robots.txt MUST be in your site root (/) to work properly with search engines. It is currently stored in: <code><?php echo esc_html($file_info['location']); ?></code>
                            <br><small>Search engines will not find robots.txt in this location. Please contact your hosting provider to enable write permissions to your site root directory, or manually move this file to <?php echo esc_html(ABSPATH); ?>robots.txt</small>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tab Content: Sitemaps -->
    <div class="mdsm-tab-content" id="tab-sitemaps">
        <div class="mdsm-section-header">
            <h2><?php esc_html_e('XML Sitemaps', 'archiviomd'); ?></h2>
            <p><?php esc_html_e('Generate and manage XML sitemaps to help search engines discover and index your content.', 'archiviomd'); ?></p>
        </div>

        <div class="mdsm-sitemap-panel">
            <?php if ($sitemap_info['type'] !== 'none') : ?>
                <div class="mdsm-sitemap-status">
                    <div class="mdsm-status-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="mdsm-status-info">
                        <h3><?php esc_html_e('Sitemap Active', 'archiviomd'); ?></h3>
                        <p>
                            <?php 
                            if ($sitemap_info['type'] === 'small') {
                                esc_html_e('Single sitemap configuration (for small sites)', 'archiviomd');
                            } else {
                                printf( esc_html__( 'Sitemap index configuration (%d files)', 'archiviomd'), $sitemap_info['file_count']);
                            }
                            ?>
                        </p>
                        <div class="mdsm-sitemap-meta">
                            <div>
                                <strong><?php esc_html_e('Main File:', 'archiviomd'); ?></strong>
                                <a href="<?php echo esc_url($sitemap_info['url']); ?>" target="_blank">
                                    <?php echo esc_html($sitemap_info['main_file']); ?>
                                    <span class="dashicons dashicons-external"></span>
                                </a>
                            </div>
                            <div>
                                <strong><?php esc_html_e('Last Updated:', 'archiviomd'); ?></strong>
                                <?php echo esc_html($sitemap_info['last_modified']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else : ?>
                <div class="mdsm-sitemap-status mdsm-sitemap-status-empty">
                    <div class="mdsm-status-icon">
                        <span class="dashicons dashicons-info"></span>
                    </div>
                    <div class="mdsm-status-info">
                        <h3><?php esc_html_e('No Sitemap Generated', 'archiviomd'); ?></h3>
                        <p><?php esc_html_e('Generate a sitemap to help search engines discover your content.', 'archiviomd'); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mdsm-sitemap-options">
                <h3><?php esc_html_e('Sitemap Configuration', 'archiviomd'); ?></h3>
                
                <div class="mdsm-option-group">
                    <label class="mdsm-radio-label">
                        <input type="radio" name="sitemap_type" value="small" <?php checked($sitemap_type, 'small'); ?>>
                        <div class="mdsm-radio-content">
                            <strong><?php esc_html_e('Small Site (Single Sitemap)', 'archiviomd'); ?></strong>
                            <p><?php esc_html_e('All URLs in a single sitemap.xml file. Ideal for sites with fewer than 50,000 URLs.', 'archiviomd'); ?></p>
                        </div>
                    </label>

                    <label class="mdsm-radio-label">
                        <input type="radio" name="sitemap_type" value="large" <?php checked($sitemap_type, 'large'); ?>>
                        <div class="mdsm-radio-content">
                            <strong><?php esc_html_e('Large Site (Multiple Sitemaps)', 'archiviomd'); ?></strong>
                            <p><?php esc_html_e('Separate sitemaps for posts, pages, and custom post types, with a sitemap_index.xml referencing them all.', 'archiviomd'); ?></p>
                        </div>
                    </label>
                </div>

                <div class="mdsm-option-group">
                    <label class="mdsm-checkbox-label">
                        <input type="checkbox" id="auto_update_sitemap" <?php checked($auto_update, true); ?>>
                        <div class="mdsm-checkbox-content">
                            <strong><?php esc_html_e('Automatic Updates', 'archiviomd'); ?></strong>
                            <p><?php esc_html_e('Automatically regenerate the sitemap whenever content is added, updated, or deleted.', 'archiviomd'); ?></p>
                        </div>
                    </label>
                </div>

                <div class="mdsm-button-group">
                    <button type="button" id="generate-sitemap" class="button button-primary button-hero">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Generate Sitemap Now', 'archiviomd'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Content: Public Index -->
    <div class="mdsm-tab-content" id="tab-public-index">
        <?php require_once MDSM_PLUGIN_DIR . 'admin/public-index-page.php'; ?>
    </div>
</div>

<!-- File Editor Modal -->
<div id="mdsm-editor-modal" class="mdsm-modal">
    <div class="mdsm-modal-content">
        <div class="mdsm-modal-header">
            <h2 id="mdsm-editor-title"><?php esc_html_e('Edit File', 'archiviomd'); ?></h2>
            <button class="mdsm-modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        
        <div class="mdsm-modal-body">
            <div class="mdsm-editor-info">
                <p id="mdsm-editor-description"></p>
                <div class="mdsm-editor-meta">
                    <span id="mdsm-editor-location"></span>
                </div>
            </div>
            
            <textarea id="mdsm-editor-textarea" class="mdsm-editor-textarea" rows="20"></textarea>
            
            <div class="mdsm-editor-help">
                <p><strong><?php esc_html_e('Tip:', 'archiviomd'); ?></strong> <?php esc_html_e('Leave the content empty and save to delete the file.', 'archiviomd'); ?></p>
            </div>
        </div>
        
        <div class="mdsm-modal-footer">
            <button type="button" class="button mdsm-modal-close"><?php esc_html_e('Cancel', 'archiviomd'); ?></button>
            <button type="button" id="mdsm-save-file" class="button button-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e('Save File', 'archiviomd'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Changelog Modal -->
<div id="mdsm-changelog-modal" class="mdsm-modal">
    <div class="mdsm-modal-content">
        <div class="mdsm-modal-header">
            <h2><?php esc_html_e('Document Change Log', 'archiviomd'); ?></h2>
            <button class="mdsm-modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        
        <div class="mdsm-modal-body">
            <div class="mdsm-changelog-info">
                <p id="mdsm-changelog-filename"></p>
            </div>
            
            <div id="mdsm-changelog-content" class="mdsm-changelog-content">
                <!-- Changelog entries will be inserted here -->
            </div>
        </div>
        
        <div class="mdsm-modal-footer">
            <button type="button" class="button mdsm-modal-close"><?php esc_html_e('Close', 'archiviomd'); ?></button>
        </div>
    </div>
</div>

<!-- Notification Toast -->
<div id="mdsm-toast" class="mdsm-toast"></div>
