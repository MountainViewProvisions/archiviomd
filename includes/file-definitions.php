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
        'robots.txt' => 'Controls what parts of the site search engines may crawl',
        'llms.txt' => 'Optional file for LLM or AI-related site instructions',
    );
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
