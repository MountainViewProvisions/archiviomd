<?php
/**
 * Sitemap Generator Class
 * 
 * Generates XML sitemaps for small and large sites
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDSM_Sitemap_Generator {
    
    /**
     * Generate sitemap
     */
    public function generate($type = 'small') {
        if ($type === 'small') {
            return $this->generate_single_sitemap();
        } else {
            return $this->generate_sitemap_index();
        }
    }
    
    /**
     * Generate single sitemap for small sites
     */
    private function generate_single_sitemap() {
        $urls = $this->get_all_urls();
        $xml = $this->create_sitemap_xml($urls);
        
        $result = $this->save_sitemap('sitemap.xml', $xml);
        
        if ($result) {
            return array(
                'success' => true,
                'message' => 'Sitemap generated successfully',
                'files' => array('sitemap.xml'),
                'url' => get_site_url() . '/sitemap.xml'
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Could not save sitemap'
        );
    }
    
    /**
     * Generate sitemap index for large sites
     */
    private function generate_sitemap_index() {
        $sitemaps = array();
        
        // Generate posts sitemap
        $posts = $this->get_posts_urls();
        if (!empty($posts)) {
            $xml = $this->create_sitemap_xml($posts);
            if ($this->save_sitemap('sitemap-posts.xml', $xml)) {
                $sitemaps[] = 'sitemap-posts.xml';
            }
        }
        
        // Generate pages sitemap
        $pages = $this->get_pages_urls();
        if (!empty($pages)) {
            $xml = $this->create_sitemap_xml($pages);
            if ($this->save_sitemap('sitemap-pages.xml', $xml)) {
                $sitemaps[] = 'sitemap-pages.xml';
            }
        }
        
        // Generate custom post types sitemaps
        $post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
        foreach ($post_types as $post_type) {
            $urls = $this->get_post_type_urls($post_type->name);
            if (!empty($urls)) {
                $xml = $this->create_sitemap_xml($urls);
                $filename = 'sitemap-' . $post_type->name . '.xml';
                if ($this->save_sitemap($filename, $xml)) {
                    $sitemaps[] = $filename;
                }
            }
        }
        
        // Generate index
        $index_xml = $this->create_sitemap_index_xml($sitemaps);
        $result = $this->save_sitemap('sitemap_index.xml', $index_xml);
        
        if ($result) {
            return array(
                'success' => true,
                'message' => 'Sitemap index generated successfully',
                'files' => array_merge(array('sitemap_index.xml'), $sitemaps),
                'url' => get_site_url() . '/sitemap_index.xml'
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Could not save sitemap index'
        );
    }
    
    /**
     * Get all URLs for single sitemap
     */
    private function get_all_urls() {
        $urls = array();
        
        // Homepage
        $urls[] = array(
            'loc' => get_home_url(),
            'lastmod' => current_time('c'),
            'changefreq' => 'daily',
            'priority' => '1.0'
        );
        
        // Posts
        $urls = array_merge($urls, $this->get_posts_urls());
        
        // Pages
        $urls = array_merge($urls, $this->get_pages_urls());
        
        // Custom post types
        $post_types = get_post_types(array('public' => true, '_builtin' => false), 'names');
        foreach ($post_types as $post_type) {
            $urls = array_merge($urls, $this->get_post_type_urls($post_type));
        }
        
        return $urls;
    }
    
    /**
     * Get posts URLs
     */
    private function get_posts_urls() {
        $urls = array();
        
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'modified',
            'order' => 'DESC'
        ));
        
        foreach ($posts as $post) {
            $urls[] = array(
                'loc' => get_permalink($post->ID),
                'lastmod' => get_the_modified_time('c', $post->ID),
                'changefreq' => 'weekly',
                'priority' => '0.8'
            );
        }
        
        return $urls;
    }
    
    /**
     * Get pages URLs
     */
    private function get_pages_urls() {
        $urls = array();
        
        $pages = get_pages(array(
            'post_status' => 'publish',
            'sort_column' => 'post_modified',
            'sort_order' => 'DESC'
        ));
        
        foreach ($pages as $page) {
            $urls[] = array(
                'loc' => get_permalink($page->ID),
                'lastmod' => get_the_modified_time('c', $page->ID),
                'changefreq' => 'monthly',
                'priority' => '0.6'
            );
        }
        
        return $urls;
    }
    
    /**
     * Get custom post type URLs
     */
    private function get_post_type_urls($post_type) {
        $urls = array();
        
        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'modified',
            'order' => 'DESC'
        ));
        
        foreach ($posts as $post) {
            $urls[] = array(
                'loc' => get_permalink($post->ID),
                'lastmod' => get_the_modified_time('c', $post->ID),
                'changefreq' => 'weekly',
                'priority' => '0.7'
            );
        }
        
        return $urls;
    }
    
    /**
     * Create sitemap XML
     */
    private function create_sitemap_xml($urls) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . esc_url($url['loc']) . '</loc>' . "\n";
            
            if (isset($url['lastmod'])) {
                $xml .= '    <lastmod>' . esc_xml($url['lastmod']) . '</lastmod>' . "\n";
            }
            
            if (isset($url['changefreq'])) {
                $xml .= '    <changefreq>' . esc_xml($url['changefreq']) . '</changefreq>' . "\n";
            }
            
            if (isset($url['priority'])) {
                $xml .= '    <priority>' . esc_xml($url['priority']) . '</priority>' . "\n";
            }
            
            $xml .= '  </url>' . "\n";
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }
    
    /**
     * Create sitemap index XML
     */
    private function create_sitemap_index_xml($sitemaps) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($sitemaps as $sitemap) {
            $xml .= '  <sitemap>' . "\n";
            $xml .= '    <loc>' . esc_url(get_site_url() . '/' . $sitemap) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . current_time('c') . '</lastmod>' . "\n";
            $xml .= '  </sitemap>' . "\n";
        }
        
        $xml .= '</sitemapindex>';
        
        return $xml;
    }
    
    /**
     * Save sitemap file
     */
    private function save_sitemap($filename, $content) {
        // Try to save in root
        $root_path = ABSPATH . $filename;
        
        if (is_writable(ABSPATH)) {
            $result = file_put_contents($root_path, $content);
            if ($result !== false) {
                @chmod($root_path, 0644);
                return true;
            }
        }
        
        // Fallback to plugin directory
        $upload_dir = wp_upload_dir();
        $fallback_path = $upload_dir['basedir'] . '/meta-docs/' . $filename;
        
        wp_mkdir_p(dirname($fallback_path));
        $result = file_put_contents($fallback_path, $content);
        
        if ($result !== false) {
            @chmod($fallback_path, 0644);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get sitemap info
     */
    public function get_sitemap_info() {
        $info = array();
        
        // Check for single sitemap
        $single_sitemap = ABSPATH . 'sitemap.xml';
        if (file_exists($single_sitemap)) {
            $info['type'] = 'small';
            $info['main_file'] = 'sitemap.xml';
            $info['url'] = get_site_url() . '/sitemap.xml';
            $info['last_modified'] = date('Y-m-d H:i:s', filemtime($single_sitemap));
            return $info;
        }
        
        // Check for sitemap index
        $sitemap_index = ABSPATH . 'sitemap_index.xml';
        if (file_exists($sitemap_index)) {
            $info['type'] = 'large';
            $info['main_file'] = 'sitemap_index.xml';
            $info['url'] = get_site_url() . '/sitemap_index.xml';
            $info['last_modified'] = date('Y-m-d H:i:s', filemtime($sitemap_index));
            
            // Count sitemap files
            $files = glob(ABSPATH . 'sitemap-*.xml');
            $info['file_count'] = count($files) + 1; // +1 for index
            
            return $info;
        }
        
        return array(
            'type' => 'none',
            'message' => 'No sitemap found'
        );
    }
}
