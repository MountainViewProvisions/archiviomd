<?php
/**
 * Public Document Index
 * 
 * Handles the public document index feature with two modes:
 * 1. Pre-generated page mode
 * 2. Shortcode mode
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDSM_Public_Index {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register shortcode
        add_shortcode('archiviomd_documents', array($this, 'render_shortcode'));
        
        // Enqueue styles for shortcode
        add_action('wp_enqueue_scripts', array($this, 'enqueue_shortcode_styles'));
    }
    
    /**
     * Enqueue styles for shortcode
     */
    public function enqueue_shortcode_styles() {
        // Only enqueue if shortcode is being used
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'archiviomd_documents')) {
            wp_add_inline_style('wp-block-library', $this->get_inline_styles());
        }
    }
    
    /**
     * Get inline styles for the index
     */
    private function get_inline_styles() {
        return '
        /* ArchivioMD Public Index Styles */
        .mdsm-public-index {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .mdsm-public-index * {
            box-sizing: border-box;
        }
        
        .mdsm-category-section {
            margin-bottom: 40px;
        }
        
        .mdsm-category-title {
            margin: 0 0 20px 0;
            font-size: 20px;
            font-weight: 600;
            color: #444;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .mdsm-document-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .mdsm-document-item {
            margin-bottom: 25px;
            padding: 20px;
            background: #fafafa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }
        
        .mdsm-document-name {
            margin: 0 0 8px 0;
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .mdsm-document-description {
            margin: 0 0 15px 0;
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }
        
        .mdsm-document-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .mdsm-doc-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.2s ease;
            border: 1px solid;
        }
        
        .mdsm-doc-link-md {
            background: #fff;
            color: #667eea;
            border-color: #667eea;
        }
        
        .mdsm-doc-link-md:hover {
            background: #667eea;
            color: #fff;
        }
        
        .mdsm-doc-link-html {
            background: #fff;
            color: #11998e;
            border-color: #11998e;
        }
        
        .mdsm-doc-link-html:hover {
            background: #11998e;
            color: #fff;
        }
        
        .mdsm-doc-link-icon {
            width: 16px;
            height: 16px;
        }
        
        .mdsm-empty-state {
            padding: 60px 20px;
            text-align: center;
            background: #fafafa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }
        
        .mdsm-empty-title {
            margin: 0 0 10px 0;
            font-size: 20px;
            font-weight: 600;
            color: #666;
        }
        
        .mdsm-empty-text {
            margin: 0;
            font-size: 14px;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .mdsm-public-index {
                padding: 30px 15px;
            }
            
            .mdsm-category-title {
                font-size: 18px;
            }
            
            .mdsm-document-item {
                padding: 15px;
            }
            
            .mdsm-document-links {
                flex-direction: column;
            }
            
            .mdsm-doc-link {
                justify-content: center;
                width: 100%;
            }
        }
        ';
    }
    
    /**
     * Render shortcode
     */
    public function render_shortcode($atts) {
        $documents = $this->get_public_documents();
        
        if (empty($documents)) {
            return '<div class="mdsm-public-index"><div class="mdsm-empty-state"><h2 class="mdsm-empty-title">No Documents Available</h2><p class="mdsm-empty-text">No public documents have been published yet.</p></div></div>';
        }
        
        ob_start();
        ?>
        <div class="mdsm-public-index">
            <?php foreach ($documents as $category => $files) : ?>
                <section class="mdsm-category-section">
                    <h2 class="mdsm-category-title"><?php echo esc_html($category); ?></h2>
                    <ul class="mdsm-document-list">
                        <?php foreach ($files as $file_name => $data) : ?>
                            <li class="mdsm-document-item">
                                <h3 class="mdsm-document-name"><?php echo esc_html($data['title']); ?></h3>
                                <?php if (!empty($data['description'])) : ?>
                                    <p class="mdsm-document-description"><?php echo esc_html($data['description']); ?></p>
                                <?php endif; ?>
                                <div class="mdsm-document-links">
                                    <?php if ($data['has_html']) : ?>
                                        <a href="<?php echo esc_url($data['html_url']); ?>" class="mdsm-doc-link mdsm-doc-link-html">
                                            <svg class="mdsm-doc-link-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            View HTML
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url($data['md_url']); ?>" class="mdsm-doc-link mdsm-doc-link-md">
                                        <svg class="mdsm-doc-link-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                        View Markdown
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get public documents organized by category
     */
    private function get_public_documents() {
        $public_docs = get_option('mdsm_public_documents', array());
        $doc_descriptions = get_option('mdsm_document_descriptions', array());
        
        if (empty($public_docs)) {
            return array();
        }
        
        $meta_files = mdsm_get_meta_files();
        $file_manager = new MDSM_File_Manager();
        $html_renderer = new MDSM_HTML_Renderer();
        
        $result = array();
        
        foreach ($meta_files as $category => $files) {
            foreach ($files as $file_name => $default_desc) {
                // Skip if not marked as public
                if (!isset($public_docs[$file_name]) || !$public_docs[$file_name]) {
                    continue;
                }
                
                // Check if file exists
                $file_info = $file_manager->get_file_info('meta', $file_name);
                if (!$file_info['exists']) {
                    continue;
                }
                
                // Get custom description or use default
                $description = isset($doc_descriptions[$file_name]) && !empty($doc_descriptions[$file_name]) 
                    ? $doc_descriptions[$file_name] 
                    : $default_desc;
                
                // Check for HTML version
                $has_html = $html_renderer->html_file_exists('meta', $file_name);
                $html_url = $has_html ? $html_renderer->get_html_file_url($html_renderer->get_html_filename($file_name)) : '';
                
                // Get title from filename
                $title = $this->get_document_title($file_name);
                
                if (!isset($result[$category])) {
                    $result[$category] = array();
                }
                
                $result[$category][$file_name] = array(
                    'title' => $title,
                    'description' => $description,
                    'md_url' => $file_info['url'],
                    'html_url' => $html_url,
                    'has_html' => $has_html,
                );
            }
        }
        
        // Remove empty categories
        $result = array_filter($result);
        
        return $result;
    }
    
    /**
     * Get document title from filename
     */
    private function get_document_title($filename) {
        // Remove extension
        $title = preg_replace('/\.md$/', '', $filename);
        
        // Replace underscores and hyphens with spaces
        $title = str_replace(array('_', '-'), ' ', $title);
        
        // Capitalize words
        $title = ucwords($title);
        
        return $title;
    }
}
