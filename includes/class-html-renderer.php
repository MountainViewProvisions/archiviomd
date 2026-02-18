<?php
/**
 * HTML Renderer Class
 * 
 * Handles conversion of Markdown files to HTML with proper styling
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDSM_HTML_Renderer {
    
    /**
     * Convert Markdown to HTML
     */
    public function convert_markdown_to_html($markdown_content, $file_name = '') {
        // Parse the markdown
        $html = $this->parse_markdown($markdown_content);
        
        // Wrap in full HTML document
        return $this->wrap_html($html, $file_name);
    }
    
    /**
     * Parse markdown to HTML
     * Supports: headings, lists, code blocks, links, images, bold, italic, inline code
     */
    private function parse_markdown($markdown) {
        $lines = explode("\n", $markdown);
        $html = '';
        $in_code_block = false;
        $code_block = '';
        $code_language = '';
        $in_list = false;
        $list_type = '';
        
        foreach ($lines as $line) {
            // Code blocks
            if (preg_match('/^```(\w*)/', $line, $matches)) {
                if (!$in_code_block) {
                    $in_code_block = true;
                    $code_language = isset($matches[1]) ? $matches[1] : '';
                    $code_block = '';
                } else {
                    $in_code_block = false;
                    $lang_class = $code_language ? ' class="language-' . esc_attr($code_language) . '"' : '';
                    $html .= '<pre><code' . $lang_class . '>' . esc_html($code_block) . '</code></pre>';
                    $code_block = '';
                    $code_language = '';
                }
                continue;
            }
            
            if ($in_code_block) {
                $code_block .= $line . "\n";
                continue;
            }
            
            // Empty lines
            if (trim($line) === '') {
                if ($in_list) {
                    $html .= '</' . $list_type . '>';
                    $in_list = false;
                }
                $html .= '<br>';
                continue;
            }
            
            // Headings
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
                if ($in_list) {
                    $html .= '</' . $list_type . '>';
                    $in_list = false;
                }
                $level = strlen($matches[1]);
                $text = $this->parse_inline($matches[2]);
                $html .= '<h' . $level . '>' . $text . '</h' . $level . '>';
                continue;
            }
            
            // Unordered lists
            if (preg_match('/^[\*\-\+]\s+(.+)$/', $line, $matches)) {
                if (!$in_list || $list_type !== 'ul') {
                    if ($in_list) {
                        $html .= '</' . $list_type . '>';
                    }
                    $html .= '<ul>';
                    $in_list = true;
                    $list_type = 'ul';
                }
                $text = $this->parse_inline($matches[1]);
                $html .= '<li>' . $text . '</li>';
                continue;
            }
            
            // Ordered lists
            if (preg_match('/^\d+\.\s+(.+)$/', $line, $matches)) {
                if (!$in_list || $list_type !== 'ol') {
                    if ($in_list) {
                        $html .= '</' . $list_type . '>';
                    }
                    $html .= '<ol>';
                    $in_list = true;
                    $list_type = 'ol';
                }
                $text = $this->parse_inline($matches[1]);
                $html .= '<li>' . $text . '</li>';
                continue;
            }
            
            // Blockquotes
            if (preg_match('/^>\s+(.+)$/', $line, $matches)) {
                if ($in_list) {
                    $html .= '</' . $list_type . '>';
                    $in_list = false;
                }
                $text = $this->parse_inline($matches[1]);
                $html .= '<blockquote>' . $text . '</blockquote>';
                continue;
            }
            
            // Horizontal rules
            if (preg_match('/^(\*{3,}|-{3,}|_{3,})$/', $line)) {
                if ($in_list) {
                    $html .= '</' . $list_type . '>';
                    $in_list = false;
                }
                $html .= '<hr>';
                continue;
            }
            
            // Regular paragraphs
            if ($in_list) {
                $html .= '</' . $list_type . '>';
                $in_list = false;
            }
            $text = $this->parse_inline($line);
            $html .= '<p>' . $text . '</p>';
        }
        
        // Close any open lists
        if ($in_list) {
            $html .= '</' . $list_type . '>';
        }
        
        return $html;
    }
    
    /**
     * Parse inline markdown elements
     */
    private function parse_inline($text) {
        // Escape raw HTML in the text first to prevent stored XSS.
        // We do this before markdown pattern replacements because markdown syntax chars
        // (*, _, [, ], (, ), !, `) are not HTML special characters and survive esc_html intact.
        $text = esc_html( $text );
        
        // Images: ![alt](url)
        $text = preg_replace_callback(
            '/!\[([^\]]*)\]\(([^\)]+)\)/',
            function($matches) {
                return '<img src="' . esc_url( html_entity_decode( $matches[2], ENT_QUOTES ) ) . '" alt="' . esc_attr( html_entity_decode( $matches[1], ENT_QUOTES ) ) . '">';
            },
            $text
        );
        
        // Links: [text](url)
        $text = preg_replace_callback(
            '/\[([^\]]+)\]\(([^\)]+)\)/',
            function($matches) {
                $link_text = html_entity_decode( $matches[1], ENT_QUOTES );
                $link_url  = html_entity_decode( $matches[2], ENT_QUOTES );
                return '<a href="' . esc_url( $link_url ) . '">' . esc_html( $link_text ) . '</a>';
            },
            $text
        );
        
        // Bold: **text** or __text__
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
        
        // Italic: *text* or _text_
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_(.+?)_/', '<em>$1</em>', $text);
        
        // Inline code: `code`
        $text = preg_replace_callback(
            '/`([^`]+)`/',
            function($matches) {
                // html_entity_decode because $text was already esc_html'd above
                return '<code>' . esc_html( html_entity_decode( $matches[1], ENT_QUOTES ) ) . '</code>';
            },
            $text
        );
        
        return $text;
    }
    
    /**
     * Wrap HTML content in full document
     */
    private function wrap_html($content, $file_name = '') {
        $title = $file_name ? esc_html($file_name) : 'Documentation';
        $site_name = get_bloginfo('name');
        
        // Get metadata for this document
        $metadata_html = '';
        if ($file_name) {
            $metadata_manager = new MDSM_Document_Metadata();
            $metadata = $metadata_manager->get_metadata($file_name);
            
            if (!empty($metadata['uuid']) || !empty($metadata['checksum'])) {
                $metadata_html = $this->generate_metadata_footer($metadata);
            }
        }
        
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . ' - ' . esc_html($site_name) . '</title>
    <link rel="stylesheet" href="' . esc_url( MDSM_PLUGIN_URL . 'assets/css/document-render.css' ) . '?v=' . MDSM_VERSION . '">
</head>
<body>
    <div class="mdsm-document-header no-print">
        <div class="mdsm-header-content">
            <div class="mdsm-title-section">
                <h1 class="mdsm-doc-title">' . $title . '</h1>
                <div class="mdsm-meta">
                    <span class="mdsm-site">' . esc_html($site_name) . '</span>
                    <span class="mdsm-separator">•</span>
                    <span class="mdsm-date">' . date('F j, Y') . '</span>
                </div>
            </div>
            <div class="mdsm-actions">
                <button onclick="window.print()" class="mdsm-btn mdsm-btn-print" title="Print this document">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    Print
                </button>
                <button onclick="window.print()" class="mdsm-btn mdsm-btn-pdf" title="Save as PDF">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    Save as PDF
                </button>
            </div>
        </div>
    </div>
    <main class="mdsm-content">
        ' . $content . '
    </main>
    <footer class="mdsm-footer no-print">
        ' . $metadata_html . '
        <p class="mdsm-footer-credit">Generated by <a href="https://mountainviewprovisions.com/ArchivioMD" target="_blank" rel="noopener">ArchivioMD</a></p>
    </footer>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Generate metadata footer HTML
     */
    private function generate_metadata_footer($metadata) {
        $html = '<div class="mdsm-doc-metadata">';
        
        if (!empty($metadata['uuid'])) {
            $html .= '<div class="mdsm-metadata-item">';
            $html .= '<span class="mdsm-metadata-label">Document ID:</span> ';
            $html .= '<span class="mdsm-metadata-value">' . esc_html($metadata['uuid']) . '</span>';
            $html .= '</div>';
        }
        
        if (!empty($metadata['modified_at'])) {
            // Format timestamp for display
            $timestamp = $metadata['modified_at'];
            $formatted_time = gmdate('Y-m-d H:i:s \U\T\C', strtotime($timestamp));
            
            $html .= '<div class="mdsm-metadata-item">';
            $html .= '<span class="mdsm-metadata-label">Last Modified:</span> ';
            $html .= '<span class="mdsm-metadata-value">' . esc_html($formatted_time) . '</span>';
            $html .= '</div>';
        }
        
        if (!empty($metadata['checksum'])) {
            // Unpack packed checksum to get the actual hash, algorithm, and mode
            $unpacked   = MDSM_Hash_Helper::unpack($metadata['checksum']);
            $algo_label = MDSM_Hash_Helper::algorithm_label($unpacked['algorithm']);
            $mode_label = ($unpacked['mode'] === 'hmac') ? 'HMAC-' : '';
            $full_label = $mode_label . $algo_label;
            
            $html .= '<div class="mdsm-metadata-item">';
            $html .= '<span class="mdsm-metadata-label">' . esc_html($full_label) . ':</span> ';
            $html .= '<span class="mdsm-metadata-value mdsm-checksum">' . esc_html($unpacked['hash']) . '</span>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    
    /**
     * Generate HTML file from MD file
     */
    public function generate_html_file($file_type, $file_name) {
        if ($file_type !== 'meta') {
            return array(
                'success' => false,
                'message' => 'HTML generation only supported for meta files'
            );
        }
        
        // Get the markdown content
        $file_manager = new MDSM_File_Manager();
        $md_content = $file_manager->read_file($file_type, $file_name);
        
        if (empty($md_content)) {
            return array(
                'success' => false,
                'message' => 'Markdown file is empty or does not exist'
            );
        }
        
        // Convert to HTML
        $html_content = $this->convert_markdown_to_html($md_content, $file_name);
        
        // Get HTML filename
        $html_filename = $this->get_html_filename($file_name);
        
        // Get HTML file path
        $html_path = $this->get_html_file_path($file_type, $html_filename);
        
        if (!$html_path) {
            return array(
                'success' => false,
                'message' => 'Could not determine HTML file path'
            );
        }
        
        // Ensure directory exists
        $dir = dirname($html_path);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        
        // Write HTML file
        $result = file_put_contents($html_path, $html_content);
        
        if ($result === false) {
            return array(
                'success' => false,
                'message' => 'Could not write HTML file'
            );
        }
        
        @chmod($html_path, 0644);
        
        return array(
            'success' => true,
            'message' => 'HTML file generated successfully',
            'html_filename' => $html_filename,
            'html_url' => $this->get_html_file_url($html_filename)
        );
    }
    
    /**
     * Get HTML filename from MD filename
     */
    public function get_html_filename($md_filename) {
        return preg_replace('/\.md$/', '.html', $md_filename);
    }
    
    /**
     * Get HTML file path
     */
    public function get_html_file_path($file_type, $html_filename) {
        // Use same logic as MD files
        $file_manager = new MDSM_File_Manager();
        $md_filename = preg_replace('/\.html$/', '.md', $html_filename);
        $md_path = $file_manager->get_file_path($file_type, $md_filename);
        
        if (!$md_path) {
            return false;
        }
        
        // Replace .md with .html in the path
        return preg_replace('/\.md$/', '.html', $md_path);
    }
    
    /**
     * Get HTML file URL
     */
    public function get_html_file_url($html_filename) {
        return get_site_url() . '/' . $html_filename;
    }
    
    /**
     * Check if HTML file exists
     */
    public function html_file_exists($file_type, $md_filename) {
        $html_filename = $this->get_html_filename($md_filename);
        $html_path = $this->get_html_file_path($file_type, $html_filename);
        
        return $html_path && file_exists($html_path);
    }
    
    /**
     * Delete HTML file
     */
    public function delete_html_file($file_type, $md_filename) {
        $html_filename = $this->get_html_filename($md_filename);
        $html_path = $this->get_html_file_path($file_type, $html_filename);
        
        if (!$html_path || !file_exists($html_path)) {
            return array(
                'success' => true,
                'message' => 'HTML file does not exist'
            );
        }
        
        $result = @unlink($html_path);
        
        if (!$result) {
            return array(
                'success' => false,
                'message' => 'Could not delete HTML file'
            );
        }
        
        return array(
            'success' => true,
            'message' => 'HTML file deleted successfully'
        );
    }
}
