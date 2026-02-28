<?php
/**
 * File Definitions
 * 
 * Master list of all meta-documentation files, SEO files, and their descriptions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get meta-documentation files grouped by category
 */
function mdsm_get_meta_files() {
    return array(
        'Project Overview & Development' => array(
            'readme.md' => 'Overview of the project or site',
            'development.md' => 'How the site is developed or deployed',
            'manifest.md' => 'High-level listing of all meta-documentation files',
            'version.md' => 'Current version of the project/site',
            'changelog.md' => 'Historical changes and updates',
            'roadmap.md' => 'Planned features or future development timeline',
            'architecture.md' => 'Technical architecture overview of the site',
            'faq.md' => 'Frequently asked questions about the project or site',
        ),
        'Licensing & Legal' => array(
            'license.md' => 'Licensing terms for code, content, or assets',
            'terms_of_service.md' => 'Legal terms for site users',
            'cookies.md' => 'Cookie usage and consent information',
            'accessibility.md' => 'Accessibility compliance information (WCAG standards, etc.)',
            'patents.md' => 'Patents or intellectual property restrictions',
            'trademark.md' => 'Trademark usage policies',
        ),
        'Security & Vulnerability Management' => array(
            'security.md' => 'How to report security vulnerabilities',
            'disclosure.md' => 'Responsible disclosure policy',
            'responsible-disclosure.md' => 'Companion or alternative to disclosure.md',
            'audit.md' => 'Security or compliance audit history',
            'incident_response.md' => 'Steps to take during or after a security breach',
            'encryption.md' => 'How data is encrypted at rest and in transit',
            'hardening.md' => 'Server, plugin, or WordPress hardening measures',
        ),
        'Privacy & Data Compliance' => array(
            'privacy.md' => 'Public-facing privacy policy',
            'gdpr.md' => 'GDPR-specific compliance information',
            'ccpa.md' => 'CCPA-specific compliance information',
            'data.md' => 'Technical details on collected or stored data',
            'retention.md' => 'Data retention policies',
            'deletion.md' => 'How users can request data deletion',
            'sharing.md' => 'Rules on how data is shared with third parties',
        ),
        'Governance & Identity' => array(
            'identity.md' => 'Legal or verified identity of the site/project owners',
            'governance.md' => 'Decision-making and project leadership rules',
            'authors.md' => 'Contributors\' names and roles',
            'team.md' => 'Team structure and responsibilities',
            'contacts.md' => 'Support, press, or official contact information',
            'roles_and_responsibilities.md' => 'Detailed responsibilities of team members',
            'stakeholders.md' => 'Key stakeholders, investors, or partners',
        ),
        'Supply Chain & Third-Party Services' => array(
            'supply_chain.md' => 'Third-party dependencies and plugins used',
            'third_party_services.md' => 'External services integrated with the site (CDN, analytics, APIs)',
            'integrations.md' => 'Detailed map of all internal and external integrations',
            'dependencies.md' => 'Technical dependency list beyond plugins (libraries, APIs, SDKs)',
        ),
    );
}

/**
 * Get SEO/crawling files
 */
function mdsm_get_seo_files() {
    return array(
        'robots.txt'  => 'Controls what parts of the site search engines may crawl',
        'llms.txt'    => 'Optional file for LLM or AI-related site instructions',
        'ai.txt'      => 'Granular permissions and instructions for AI crawlers and agents',
        'ads.txt'     => 'Authorized Digital Sellers — declares who is permitted to sell your ad inventory',
        'app-ads.txt' => 'Mobile app equivalent of ads.txt for in-app advertising inventory',
        'sellers.json' => 'Identifies the entities authorized to sell or resell your ad inventory',
    );
}

/**
 * Get custom markdown files created by users
 */
function mdsm_get_custom_markdown_files() {
    $custom_files = get_option('mdsm_custom_markdown_files', array());
    
    if (!is_array($custom_files)) {
        return array();
    }
    
    return $custom_files;
}

/**
 * Add a custom markdown file
 */
function mdsm_add_custom_markdown_file($filename, $description = '') {
    // Validate filename
    $filename = sanitize_file_name($filename);
    
    // Ensure .md extension
    if (!preg_match('/\.md$/', $filename)) {
        $filename .= '.md';
    }
    
    // Get existing files
    $custom_files = mdsm_get_custom_markdown_files();
    
    // Add new file if it doesn't exist
    if (!isset($custom_files[$filename])) {
        $custom_files[$filename] = sanitize_text_field($description);
        $result = update_option('mdsm_custom_markdown_files', $custom_files);
        return true;
    }
    
    return false;
}

/**
 * Delete a custom markdown file entry
 */
function mdsm_delete_custom_markdown_file($filename) {
    $custom_files = mdsm_get_custom_markdown_files();
    
    if (isset($custom_files[$filename])) {
        unset($custom_files[$filename]);
        update_option('mdsm_custom_markdown_files', $custom_files);
        return true;
    }
    
    return false;
}

/**
 * Get all file types
 */
function mdsm_get_file_types() {
    return array(
        'meta' => array(
            'label' => 'Meta Documentation Files',
            'extension' => '.md',
            'icon' => 'dashicons-media-document',
        ),
        'seo' => array(
            'label' => 'SEO & Crawling Files',
            'extension' => '.txt',
            'icon' => 'dashicons-search',
        ),
    );
}
