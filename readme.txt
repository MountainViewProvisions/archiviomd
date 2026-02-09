=== ArchivioMD ===
Contributors: mountainviewprovisions
Tags: documentation, markdown, seo, sitemap, robots.txt
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.1.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional management of meta-documentation files, SEO files (robots.txt, llms.txt), and sitemaps with metadata tracking, HTML rendering, and compliance tools.

== Description ==

ArchivioMD is a comprehensive WordPress plugin for managing meta-documentation, SEO configuration files, and XML sitemaps from a centralized admin interface. It provides document metadata tracking (UUIDs, checksums, changelogs), HTML rendering from Markdown, and compliance-ready tools for audit and data management.

= Core Features =

**Document Management**
* Create and edit Markdown files for meta-documentation (security.txt, privacy policy, terms of service, etc.)
* Manage SEO files (robots.txt, llms.txt, ads.txt, sellers.json)
* Automatic UUID assignment and SHA-256 checksum tracking for document integrity
* Append-only changelog for all document modifications (timestamp, user, checksum)
* HTML rendering from Markdown files with syntax highlighting and responsive design

**Sitemap Generation**
* Generate XML sitemaps (standard or comprehensive format)
* Optional automatic sitemap updates on post publish/delete
* Support for sitemap index and post-type-specific sitemaps
* Direct integration with WordPress content

**Public Index Page**
* Optional public-facing document index page
* Customizable document visibility and descriptions
* Integrates seamlessly with your WordPress theme

**Compliance & Audit Tools** (Tools → ArchivioMD)
* Metadata Export: Download all document metadata as CSV for compliance audits
* Backup & Restore: Create portable ZIP archives of metadata and files; restore with mandatory dry-run verification
* Metadata Verification: Manual checksum verification against stored SHA-256 hashes
* Metadata Cleanup on Uninstall: Optional (disabled by default) cleanup of metadata when uninstalling plugin

= Key Capabilities =

* **Metadata Integrity**: Every document gets a unique UUID and SHA-256 checksum
* **Audit Trail**: Append-only changelog tracks all modifications with user and timestamp
* **Manual Verification**: Admin-triggered checksum verification (no automatic enforcement)
* **Export & Backup**: On-demand CSV exports and full backup archives
* **Restore Protection**: Mandatory dry-run analysis before any restore operation
* **Conservative Defaults**: Metadata preserved by default; cleanup requires explicit opt-in

= Ideal For =

* Organizations requiring audit-ready document management
* Sites needing centralized meta-documentation
* Compliance-conscious WordPress administrators
* Sites requiring document integrity verification
* Teams managing SEO configuration files

= Important Notes =

**Database Storage**: All metadata (UUIDs, checksums, changelogs) is stored in the WordPress database (wp_options table). Regular WordPress database backups are required for complete data protection.

**Manual Operations**: This plugin emphasizes manual, admin-triggered actions. Verification, export, and backup operations run only when explicitly initiated by an administrator. There is no automatic enforcement, silent cleanup, or background processing.

**File Locations**: Markdown and SEO files are stored in `/wp-content/uploads/meta-docs/`. These files are considered site content and are preserved even when the plugin is uninstalled (unless manually deleted by the administrator).

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins → Add New
3. Search for "ArchivioMD"
4. Click "Install Now" and then "Activate"
5. Navigate to Settings → Permalinks and click "Save Changes" (required for file serving)

= Manual Installation =

1. Download the plugin ZIP file
2. Upload to WordPress via Plugins → Add New → Upload Plugin
3. Activate the plugin
4. Navigate to Settings → Permalinks and click "Save Changes" (required for file serving)

= From ZIP File via FTP =

1. Download and extract the plugin ZIP file
2. Upload the `archivio-with-metadata` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin Plugins menu
4. Navigate to Settings → Permalinks and click "Save Changes" (required for file serving)

= Post-Installation =

After activation, you will see:
* **Main Menu**: "Meta Docs & SEO" in the WordPress admin sidebar
* **Tools Menu**: "ArchivioMD" under Tools for compliance features
* **Admin Notice**: Reminder to flush permalinks (dismissible)

== Getting Started ==

= First Steps =

1. **Flush Permalinks** (Critical)
   * Navigate to Settings → Permalinks
   * Click "Save Changes" (no changes needed, just save)
   * This enables WordPress to serve your meta-documentation files

2. **Create Your First Document**
   * Go to Meta Docs & SEO
   * Find a predefined file (e.g., "security.txt.md")
   * Click to expand, enter content, and save
   * The plugin automatically assigns a UUID and records the first changelog entry

3. **View Your Document**
   * Click "View File" to see the Markdown file at yoursite.com/filename.md
   * Click "Generate HTML" to create an HTML version at yoursite.com/filename.html

4. **Review Metadata** (Optional)
   * Click "View Changelog" to see document history
   * Note the UUID, checksum, and modification timestamp
   * All metadata is stored separately from the file content

= Recommended Workflow =

1. Set up regular WordPress database backups (protects metadata)
2. Create and edit documents as needed
3. Periodically verify checksums via Tools → ArchivioMD → Metadata Verification
4. Export metadata to CSV for compliance records (Tools → ArchivioMD)
5. Create backups before major changes using the Backup & Restore tool

== Feature Locations & Usage ==

= Main Interface: Meta Docs & SEO =

**Location**: WordPress Admin → Meta Docs & SEO (left sidebar menu)

**Tabs Available**:

1. **Meta Documentation**
   * Organized by category (Legal & Compliance, Contact & Support, Technical, Custom)
   * Click category header to expand/collapse
   * Click file card to edit content
   * Save button stores file and updates metadata automatically
   * View File: Opens Markdown file in new tab
   * Generate HTML: Creates HTML version with syntax highlighting
   * Delete HTML: Removes generated HTML file
   * View Changelog: Shows full document history (admin only)

2. **SEO Files**
   * Manage robots.txt, llms.txt, ads.txt, sellers.json, app-ads.txt
   * Same editing interface as meta-documentation
   * Direct URL access at yoursite.com/robots.txt, etc.

3. **Sitemaps**
   * Generate sitemap.xml manually
   * Choose between "Small Site" or "Comprehensive" format
   * Optional auto-update on post publish/delete
   * View generated sitemap at yoursite.com/sitemap.xml

4. **Public Index**
   * Create a public-facing page listing your documents
   * Enable/disable individual documents for public visibility
   * Add custom descriptions for each document
   * Automatically creates a WordPress page (editable like any post)

= Compliance Tools: Tools → ArchivioMD =

**Location**: WordPress Admin → Tools → ArchivioMD

**Four Tools Available**:

**1. Metadata Export (CSV)**

*Purpose*: Export all document metadata for compliance audits and record-keeping.

*Includes*: UUID, filename, path, last-modified timestamp (UTC), SHA-256 checksum, changelog count, full changelog entries.

*Usage*:
1. Click "Export Metadata to CSV"
2. Wait for processing (shows spinner)
3. CSV file downloads automatically
4. Open in Excel, Google Sheets, or any spreadsheet application

*Use Cases*: Compliance reporting, audit evidence, metadata backup, migration planning.

**2. Document Backup & Restore**

*Purpose*: Create portable ZIP archives of all metadata and files; restore from previous backups.

*Critical Information*:
* Metadata lives in the WordPress database (wp_options table)
* Regular WordPress database backups are REQUIRED for full protection
* This tool creates portable archives for disaster recovery and migration
* Restore operations require explicit confirmation and show mandatory dry-run first

*Create Backup*:
1. Click "Create Backup Archive"
2. Wait for processing
3. ZIP file downloads automatically
4. Store securely (contains all metadata + files)

*Backup Contains*:
* All ArchivioMD metadata (JSON format)
* All Markdown files
* Manifest file with backup details and checksums

*Restore from Backup*:
1. Click "Select Backup Archive (.zip)" and choose backup file
2. Click "Analyze Backup (Dry Run)" - this is READ-ONLY
3. Review the dry-run report showing what will happen:
   * Files to restore (new)
   * Files to overwrite (existing)
   * Conflicts or issues
4. If acceptable, click "Confirm and Execute Restore"
5. Confirm in the final warning dialog
6. Restoration proceeds (overwrites existing metadata and files)

*Important*: Restore is DESTRUCTIVE. Always review the dry-run report carefully.

**3. Metadata Verification Tool**

*Purpose*: Manually verify document integrity by comparing current file checksums against stored SHA-256 values.

*Characteristics*:
* Manual: Runs only when you click the button
* Read-only: No automatic corrections or enforcement
* Non-intrusive: Reports status without modifying files or metadata

*Usage*:
1. Click "Verify All Document Checksums"
2. Wait for processing
3. Review results table showing:
   * ✓ VERIFIED: Current checksum matches stored checksum
   * ✗ MISMATCH: File has been modified outside the plugin
   * ⚠ MISSING FILE: File not found on disk

*When to Use*:
* Periodic compliance checks
* After FTP/SSH file operations
* Before important backups
* Investigating potential file tampering

*What It Does NOT Do*:
* Does not automatically fix mismatches
* Does not prevent file access or modifications
* Does not send alerts or notifications
* Does not block the site or show warnings to visitors

**4. Metadata Cleanup on Uninstall**

*Purpose*: Control whether metadata is deleted when the plugin is uninstalled.

*Default Behavior (Recommended)*: DISABLED - All metadata is preserved when plugin is uninstalled. Metadata constitutes audit evidence and should be retained according to your organization's data retention policies.

*Status Display*:
* Current status always visible: "DISABLED" (green) or "ENABLED" (red)
* Clear indication of what will happen on uninstall

*What Gets Deleted (if enabled)*:
* All document metadata (UUIDs, checksums, timestamps, changelogs)
* Plugin configuration settings
* Public index page (if created by plugin)

*What Is NEVER Deleted*:
* Markdown files in `/wp-content/uploads/meta-docs/` - NEVER touched
* Generated HTML files - Remain intact
* Generated sitemaps - Remain intact
* WordPress core data, posts, pages, other plugins - Unaffected

*Enabling Cleanup (Opt-In Process)*:
1. Check "Enable metadata deletion on plugin uninstall" checkbox
2. Confirmation section appears
3. Type exactly: DELETE METADATA (all caps, case-sensitive)
4. Click "Save Cleanup Settings"
5. Confirm in warning dialog (explains irreversible action)
6. Settings saved, page reloads showing "ENABLED" status

*Disabling Cleanup (Restore Default)*:
1. Click "Disable Cleanup (Restore Default)" button (appears when enabled)
2. Confirm action
3. Settings saved, page reloads showing "DISABLED" status

*Compliance Recommendations*:
* Keep cleanup DISABLED (default) for audit compliance
* Metadata provides valuable audit trails
* Export metadata to CSV before enabling cleanup
* Create backup via Tool 2 before enabling cleanup
* Consult your organization's data retention policies

*When Cleanup Might Be Appropriate*:
* End of document lifecycle
* Permanent plugin removal with data deletion requirement
* Site decommissioning
* Specific compliance requirement for data deletion

*Audit Trail*:
* All cleanup setting changes are logged to PHP error_log
* Logs include: timestamp (UTC), username, user ID, action (ENABLED/DISABLED)

= Admin Notices =

**Database Backup Reminder** (Dismissible)
* Appears on admin pages for administrators
* Reminds that metadata is stored in WordPress database
* Recommends regular database backups
* Links to Tools → ArchivioMD compliance tools
* Dismiss by clicking X (won't show again)

**Permalink Flush Notice** (Dismissible)
* Appears on plugin page after activation
* Critical reminder to flush permalinks
* Required for file serving to work properly
* Dismiss after completing Settings → Permalinks → Save Changes

== Frequently Asked Questions ==

= Where are my files stored? =

Markdown and SEO files are stored in `/wp-content/uploads/meta-docs/`. Metadata (UUIDs, checksums, changelogs) is stored in the WordPress database in the `wp_options` table with the prefix `mdsm_doc_meta_`.

= Do I need to back up the database? =

Yes. Regular WordPress database backups are essential because all metadata is stored in the database. The plugin's Backup & Restore tool provides additional portable archives, but standard database backups are still required.

= What happens if I uninstall the plugin? =

**By default**: All metadata is preserved in the database, and all files remain in the uploads directory. You can reinstall the plugin later and everything will be intact.

**If cleanup enabled**: Only database options are deleted. Files always remain in the uploads directory and must be manually deleted if desired.

= Can I edit files via FTP? =

Yes, but this will cause checksum mismatches. The plugin tracks file integrity via SHA-256 checksums. If you edit files outside the plugin, the Metadata Verification tool will report a mismatch. To fix this, re-save the file through the plugin's admin interface to update the stored checksum.

= How do I verify file integrity? =

Go to Tools → ArchivioMD → Metadata Verification Tool and click "Verify All Document Checksums". This compares current file checksums against stored values and reports the results.

= Does this plugin enforce file integrity? =

No. The plugin tracks integrity via checksums and provides manual verification tools, but it does not prevent, block, or automatically correct modifications. Verification is admin-triggered and read-only.

= Can I migrate to another WordPress site? =

Yes. Use Tools → ArchivioMD → Backup & Restore to create a backup archive. Install the plugin on the new site and restore the backup. The dry-run feature shows exactly what will happen before restoration.

= Why do I need to flush permalinks? =

WordPress needs to recognize the custom URLs for your meta-documentation files (like /robots.txt or /security.txt.md). Flushing permalinks tells WordPress to update its URL rewrite rules. This is a one-time requirement after plugin activation.

= Is this plugin GDPR compliant? =

The plugin itself does not collect, store, or process personal data from visitors. It stores administrative metadata (document UUIDs, checksums, changelogs) with WordPress user IDs. Compliance with GDPR and other regulations depends on how you use the plugin and what content you publish. Consult with your legal team for compliance guidance.

= Can non-admin users access these features? =

No. All plugin features require the `manage_options` capability (administrator role). Only administrators can create, edit, delete files, or access compliance tools.

= What if I have multiple administrators? =

The changelog tracks which user (by ID and username) made each modification, including the timestamp in UTC format. This provides a complete audit trail of who did what and when.

= Can I customize the HTML output? =

The plugin generates HTML from Markdown using a predefined template with syntax highlighting. The HTML files can be edited directly via FTP if customization is needed, but changes will be overwritten if you regenerate the HTML through the plugin.

= What Markdown syntax is supported? =

The plugin uses PHP Parsedown for Markdown processing, which supports standard Markdown syntax including headings, lists, links, code blocks, tables, and emphasis. GitHub-flavored Markdown features like task lists are also supported.

== Screenshots ==

1. Main admin interface showing meta-documentation categories and file management
2. File editor with metadata display (UUID, checksum, changelog)
3. Sitemap generation interface with auto-update options
4. Public index page settings and document visibility controls
5. Compliance Tools page (Tools → ArchivioMD) showing all four tools
6. Metadata Export (CSV) download in progress
7. Backup & Restore with mandatory dry-run verification report
8. Metadata Verification results showing verified, mismatched, and missing files
9. Metadata Cleanup on Uninstall settings with opt-in confirmation
10. Changelog viewer showing document modification history

== Changelog ==

= 1.1.1 - 2026-02-08 =
* Added: Metadata Cleanup on Uninstall feature (opt-in, disabled by default)
* Added: Tool 4 section in compliance tools page
* Added: WordPress standard uninstall.php handler
* Added: Audit logging for cleanup setting changes
* Enhanced: Compliance tools page with additional safeguards
* Enhanced: Documentation and user guidance for data retention
* Security: Enhanced nonce verification and capability checks
* No breaking changes - fully backward compatible

= 1.1.0 =
* Initial public release
* Meta-documentation management with Markdown support
* SEO file management (robots.txt, llms.txt, ads.txt, etc.)
* XML sitemap generation (manual and auto-update)
* Document metadata tracking (UUIDs, SHA-256, changelogs)
* HTML rendering from Markdown files
* Public index page with customizable document visibility
* Compliance tools: Metadata Export (CSV)
* Compliance tools: Backup & Restore with dry-run verification
* Compliance tools: Manual metadata verification
* Admin-only access with proper capability checks
* Dismissible admin notices for guidance

== Upgrade Notice ==

= 1.1.1 =
Adds optional metadata cleanup on uninstall (disabled by default). All existing functionality preserved. No action required unless you want to configure cleanup settings.

= 1.1.0 =
Initial release. After activation, navigate to Settings → Permalinks and click Save Changes to enable file serving.

== Additional Information ==

= System Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher (or equivalent MariaDB)
* Writable `/wp-content/uploads/` directory
* Permalink structure enabled (not "Plain")

= Recommended Environment =

* Regular WordPress database backups
* HTTPS enabled for secure admin access
* Up-to-date WordPress core, themes, and plugins
* PHP error logging enabled for audit trail

= Performance Considerations =

* All operations are admin-triggered (no automatic background processing)
* File serving uses WordPress rewrite rules (cached by permalink system)
* Database queries optimized for single-option reads
* HTML generation is on-demand only
* No impact on frontend page load times

= Security Considerations =

* Admin-only access (manage_options capability required)
* WordPress nonce verification on all form submissions
* Input sanitization using WordPress sanitize_* functions
* Output escaping using WordPress esc_* functions
* No direct database queries (uses WordPress options API)
* No user-uploaded file execution
* Files served with appropriate content-type headers

= Compliance & Audit Notes =

**What This Plugin Provides**:
* Metadata tracking for document integrity
* Manual verification tools for admin use
* Export capabilities for compliance reporting
* Audit trail via append-only changelogs
* Backup and restore functionality
* Conservative defaults (preserve data by default)

**What This Plugin Does NOT Provide**:
* Automatic compliance certification
* Legal advice or guarantees
* Automatic enforcement of integrity
* Silent or background data cleanup
* Scheduled compliance tasks
* Integration with external compliance platforms

**Administrator Responsibilities**:
* Maintain regular WordPress database backups
* Review and export metadata periodically
* Verify file integrity as needed
* Configure cleanup settings according to organizational policies
* Consult legal/compliance teams for data retention requirements
* Manually delete files when appropriate

**Audit Readiness**:
* All metadata changes are logged with timestamps and user IDs
* Checksums use SHA-256 (industry-standard cryptographic hash)
* UUIDs follow RFC 4122 version 4 specification
* Timestamps use UTC ISO 8601 format
* Changelogs are append-only (no deletions or modifications)
* CSV exports contain all metadata for external analysis

**Environmental Dependencies**:
* Audit trail quality depends on WordPress user management
* Timestamp accuracy depends on server time configuration
* Backup reliability depends on WordPress database backup system
* File integrity depends on filesystem permissions and security
* URL accessibility depends on permalink configuration

= Support & Development =

For support, feature requests, or bug reports, please use the WordPress.org support forums for this plugin.

Development happens on GitHub. Contributions are welcome.

= Privacy Policy =

ArchivioMD does not collect, store, or transmit any personal data from site visitors. The plugin stores administrative metadata (document UUIDs, checksums, modification logs) associated with WordPress user accounts. This metadata is stored in your WordPress database and subject to your site's privacy policy.

WordPress user IDs and usernames are recorded in changelogs to maintain an audit trail. This is standard administrative logging practice.

The plugin does not:
* Make external API calls
* Track user behavior
* Set cookies for visitors
* Collect analytics
* Share data with third parties

= License =

This plugin is licensed under the GNU General Public License v2 or later.

You are free to:
* Use the plugin for any purpose
* Study and modify the plugin
* Distribute copies of the plugin
* Distribute modified versions of the plugin

Under the following conditions:
* Preserve copyright and license notices
* Share modifications under the same license
* Provide source code with distributions

For the full license text, see https://www.gnu.org/licenses/gpl-2.0.html

== Credits ==

Developed by Mountain View Provisions LLC
Markdown parsing by PHP Parsedown (https://github.com/erusev/parsedown)
Icons from WordPress Dashicons
