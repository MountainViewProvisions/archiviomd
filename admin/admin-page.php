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

$seo_exists = $file_manager->get_existing_files_count('seo');
$seo_total = count($seo_files);
?>

<div class="wrap mdsm-admin-wrap">
    <h1 class="mdsm-page-title">
        <span class="dashicons dashicons-media-document"></span>
        <?php _e('Meta Documentation & SEO Manager', 'meta-doc-seo'); ?>
    </h1>
    
    <div class="mdsm-header-info">
        <p class="mdsm-description">
            <?php _e('Manage your site\'s meta-documentation files, SEO configuration files, and XML sitemaps from one central location.', 'meta-doc-seo'); ?>
        </p>
    </div>

    <!-- Search/Filter Bar -->
    <div class="mdsm-search-bar">
        <input type="text" id="mdsm-search" class="mdsm-search-input" placeholder="<?php esc_attr_e('Search files...', 'meta-doc-seo'); ?>">
        <span class="mdsm-search-icon dashicons dashicons-search"></span>
    </div>

    <!-- Tabs Navigation -->
    <div class="mdsm-tabs">
        <button class="mdsm-tab-button active" data-tab="meta-docs">
            <span class="dashicons dashicons-media-document"></span>
            <?php _e('Meta Documentation', 'meta-doc-seo'); ?>
            <span class="mdsm-badge"><?php echo esc_html($meta_exists . '/' . $meta_total); ?></span>
        </button>
        <button class="mdsm-tab-button" data-tab="seo-files">
            <span class="dashicons dashicons-search"></span>
            <?php _e('SEO Files', 'meta-doc-seo'); ?>
            <span class="mdsm-badge"><?php echo esc_html($seo_exists . '/' . $seo_total); ?></span>
        </button>
        <button class="mdsm-tab-button" data-tab="sitemaps">
            <span class="dashicons dashicons-networking"></span>
            <?php _e('Sitemaps', 'meta-doc-seo'); ?>
        </button>
    </div>

    <!-- Tab Content: Meta Documentation -->
    <div class="mdsm-tab-content active" id="tab-meta-docs">
        <div class="mdsm-section-header">
            <h2><?php _e('Meta Documentation Files', 'meta-doc-seo'); ?></h2>
            <p><?php _e('These Markdown files provide comprehensive documentation about your site, organized by category.', 'meta-doc-seo'); ?></p>
        </div>

        <?php foreach ($meta_files as $category => $files) : ?>
            <div class="mdsm-category">
                <div class="mdsm-category-header">
                    <h3>
                        <span class="dashicons dashicons-category"></span>
                        <?php echo esc_html($category); ?>
                    </h3>
                    <button class="mdsm-collapse-toggle" aria-expanded="true">
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
                                            <span class="mdsm-status-badge mdsm-status-exists"><?php _e('Active', 'meta-doc-seo'); ?></span>
                                        <?php else : ?>
                                            <span class="mdsm-status-badge mdsm-status-empty"><?php _e('Empty', 'meta-doc-seo'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <button class="mdsm-edit-button" data-file-type="meta" data-file-name="<?php echo esc_attr($file_name); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                        <?php _e('Edit', 'meta-doc-seo'); ?>
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
                                                <?php _e('View', 'meta-doc-seo'); ?>
                                            </a>
                                            <button class="mdsm-copy-link" data-url="<?php echo esc_attr($file_info['url']); ?>">
                                                <span class="dashicons dashicons-admin-links"></span>
                                                <?php _e('Copy Link', 'meta-doc-seo'); ?>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Tab Content: SEO Files -->
    <div class="mdsm-tab-content" id="tab-seo-files">
        <div class="mdsm-section-header">
            <h2><?php _e('SEO & Crawling Files', 'meta-doc-seo'); ?></h2>
            <p><?php _e('Control how search engines and AI crawlers interact with your site.', 'meta-doc-seo'); ?></p>
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
                                <span class="mdsm-status-badge mdsm-status-exists"><?php _e('Active', 'meta-doc-seo'); ?></span>
                            <?php else : ?>
                                <span class="mdsm-status-badge mdsm-status-empty"><?php _e('Empty', 'meta-doc-seo'); ?></span>
                            <?php endif; ?>
                        </div>
                        <button class="mdsm-edit-button" data-file-type="seo" data-file-name="<?php echo esc_attr($file_name); ?>">
                            <span class="dashicons dashicons-edit"></span>
                            <?php _e('Edit', 'meta-doc-seo'); ?>
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
                                    <?php _e('View', 'meta-doc-seo'); ?>
                                </a>
                                <button class="mdsm-copy-link" data-url="<?php echo esc_attr($file_info['url']); ?>">
                                    <span class="dashicons dashicons-admin-links"></span>
                                    <?php _e('Copy Link', 'meta-doc-seo'); ?>
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
            <h2><?php _e('XML Sitemaps', 'meta-doc-seo'); ?></h2>
            <p><?php _e('Generate and manage XML sitemaps to help search engines discover and index your content.', 'meta-doc-seo'); ?></p>
        </div>

        <div class="mdsm-sitemap-panel">
            <?php if ($sitemap_info['type'] !== 'none') : ?>
                <div class="mdsm-sitemap-status">
                    <div class="mdsm-status-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="mdsm-status-info">
                        <h3><?php _e('Sitemap Active', 'meta-doc-seo'); ?></h3>
                        <p>
                            <?php 
                            if ($sitemap_info['type'] === 'small') {
                                _e('Single sitemap configuration (for small sites)', 'meta-doc-seo');
                            } else {
                                printf(__('Sitemap index configuration (%d files)', 'meta-doc-seo'), $sitemap_info['file_count']);
                            }
                            ?>
                        </p>
                        <div class="mdsm-sitemap-meta">
                            <div>
                                <strong><?php _e('Main File:', 'meta-doc-seo'); ?></strong>
                                <a href="<?php echo esc_url($sitemap_info['url']); ?>" target="_blank">
                                    <?php echo esc_html($sitemap_info['main_file']); ?>
                                    <span class="dashicons dashicons-external"></span>
                                </a>
                            </div>
                            <div>
                                <strong><?php _e('Last Updated:', 'meta-doc-seo'); ?></strong>
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
                        <h3><?php _e('No Sitemap Generated', 'meta-doc-seo'); ?></h3>
                        <p><?php _e('Generate a sitemap to help search engines discover your content.', 'meta-doc-seo'); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mdsm-sitemap-options">
                <h3><?php _e('Sitemap Configuration', 'meta-doc-seo'); ?></h3>
                
                <div class="mdsm-option-group">
                    <label class="mdsm-radio-label">
                        <input type="radio" name="sitemap_type" value="small" <?php checked($sitemap_type, 'small'); ?>>
                        <div class="mdsm-radio-content">
                            <strong><?php _e('Small Site (Single Sitemap)', 'meta-doc-seo'); ?></strong>
                            <p><?php _e('All URLs in a single sitemap.xml file. Ideal for sites with fewer than 50,000 URLs.', 'meta-doc-seo'); ?></p>
                        </div>
                    </label>

                    <label class="mdsm-radio-label">
                        <input type="radio" name="sitemap_type" value="large" <?php checked($sitemap_type, 'large'); ?>>
                        <div class="mdsm-radio-content">
                            <strong><?php _e('Large Site (Multiple Sitemaps)', 'meta-doc-seo'); ?></strong>
                            <p><?php _e('Separate sitemaps for posts, pages, and custom post types, with a sitemap_index.xml referencing them all.', 'meta-doc-seo'); ?></p>
                        </div>
                    </label>
                </div>

                <div class="mdsm-option-group">
                    <label class="mdsm-checkbox-label">
                        <input type="checkbox" id="auto_update_sitemap" <?php checked($auto_update, true); ?>>
                        <div class="mdsm-checkbox-content">
                            <strong><?php _e('Automatic Updates', 'meta-doc-seo'); ?></strong>
                            <p><?php _e('Automatically regenerate the sitemap whenever content is added, updated, or deleted.', 'meta-doc-seo'); ?></p>
                        </div>
                    </label>
                </div>

                <div class="mdsm-button-group">
                    <button type="button" id="generate-sitemap" class="button button-primary button-hero">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Generate Sitemap Now', 'meta-doc-seo'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- File Editor Modal -->
<div id="mdsm-editor-modal" class="mdsm-modal">
    <div class="mdsm-modal-content">
        <div class="mdsm-modal-header">
            <h2 id="mdsm-editor-title"><?php _e('Edit File', 'meta-doc-seo'); ?></h2>
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
                <p><strong><?php _e('Tip:', 'meta-doc-seo'); ?></strong> <?php _e('Leave the content empty and save to delete the file.', 'meta-doc-seo'); ?></p>
            </div>
        </div>
        
        <div class="mdsm-modal-footer">
            <button type="button" class="button mdsm-modal-close"><?php _e('Cancel', 'meta-doc-seo'); ?></button>
            <button type="button" id="mdsm-save-file" class="button button-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php _e('Save File', 'meta-doc-seo'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Notification Toast -->
<div id="mdsm-toast" class="mdsm-toast"></div>
