<?php
/**
 * ArchivioMD Uninstall Handler
 * 
 * COMPLIANCE-CRITICAL: This file handles plugin uninstallation with strict
 * audit-ready safeguards. Metadata deletion is OPT-IN ONLY and requires
 * explicit administrator approval.
 * 
 * DEFAULT BEHAVIOR: Preserve all metadata (audit evidence)
 * OPT-IN BEHAVIOR: Delete only ArchivioMD-owned database options
 * NEVER TOUCHES: Markdown files remain untouched under all circumstances
 */

// Exit if not called by WordPress uninstall process
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Uninstall cleanup process
 * 
 * This function implements a conservative, audit-ready approach:
 * 1. Check if metadata cleanup is explicitly enabled (opt-in)
 * 2. If disabled (default), exit immediately - preserve everything
 * 3. If enabled, delete ONLY ArchivioMD database options
 * 4. NEVER delete or modify Markdown files
 */
function archiviomd_uninstall_cleanup() {
    
    // CRITICAL: Check opt-in flag (default: false = preserve metadata)
    $cleanup_enabled = get_option('mdsm_uninstall_cleanup_enabled', false);
    
    if (!$cleanup_enabled) {
        // DEFAULT BEHAVIOR: Preserve all metadata for audit compliance
        // Exit silently without any deletions
        return;
    }
    
    // OPT-IN BEHAVIOR: User explicitly enabled cleanup
    // Delete only ArchivioMD-owned database options
    
    global $wpdb;
    
    // 1. Delete all document metadata (UUIDs, checksums, changelogs)
    //    Pattern: mdsm_doc_meta_*
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE 'mdsm_doc_meta_%'"
    );
    
    // 2. Delete plugin configuration options
    $plugin_options = array(
        'mdsm_auto_update_sitemap',
        'mdsm_sitemap_type',
        'mdsm_custom_markdown_files',
        'mdsm_public_index_enabled',
        'mdsm_public_index_page_id',
        'mdsm_public_documents',
        'mdsm_document_descriptions',
        'mdsm_backup_notice_dismissed',
        'mdsm_permalink_notice_dismissed',
        'mdsm_uninstall_cleanup_enabled', // Delete the opt-in flag itself
    );
    
    foreach ($plugin_options as $option_name) {
        delete_option($option_name);
    }
    
    // 3. Delete public index page if it was created by the plugin
    $page_id = get_option('mdsm_public_index_page_id');
    if ($page_id) {
        // Force delete (bypass trash)
        wp_delete_post($page_id, true);
    }
    
    // IMPORTANT: Markdown files in /wp-content/uploads/meta-docs/ are NOT deleted
    // IMPORTANT: Generated sitemaps and HTML files are NOT deleted
    // These files are considered site content, not plugin data
    // Administrators must manually delete these files if desired
}

// Execute cleanup
archiviomd_uninstall_cleanup();
