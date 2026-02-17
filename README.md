# ArchivioMD

**ArchivioMD** is a document-focused WordPress plugin that centralizes management of site documentation, SEO and crawling files, and sitemaps into a single, professional admin interface.

It is designed for developers, site owners, and teams who want a clean, structured way to manage site-critical documents without unnecessary SEO bloat.

[![Version](https://img.shields.io/badge/version-1.5.9-667eea)](https://github.com/MountainViewProvisions/archiviomd/releases)
[![License](https://img.shields.io/badge/license-GPL%20v2-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B)](https://wordpress.org)
[![Add-on](https://img.shields.io/badge/add--on-ArchivioID-764ba2)](https://github.com/MountainViewProvisions/archivio-id)
[![Crypto](https://img.shields.io/badge/crypto-SHA256%20%7C%20SHA512%20%7C%20BLAKE2b-10b981)](https://github.com/MountainViewProvisions/archiviomd)
[![Anchoring](https://img.shields.io/badge/anchoring-GitHub%20%7C%20GitLab-1f2937)](https://github.com/MountainViewProvisions/archiviomd)

---

## Features

### Centralized Documentation Management

ArchivioMD provides a master list of Markdown (`.md`) documentation files, grouped by purpose, including:

- Project overview and development documentation
- Licensing and legal documents
- Security and vulnerability policies
- Privacy and data compliance files
- Governance, identity, and team documentation
- Supply chain and third-party service references

All documents are editable directly from the WordPress admin interface. Each category can be expanded or collapsed for easier navigation, and files display their current status (Active or Empty) along with their storage location.

---

### Custom Markdown Files

Beyond the predefined documentation templates, ArchivioMD allows you to create custom markdown files for any purpose. Custom files integrate seamlessly with the standard documentation set and support the same features, including HTML rendering and public index inclusion. This flexibility ensures the plugin adapts to your specific documentation needs rather than constraining you to a fixed set of file types.

---

### HTML Rendering

ArchivioMD can automatically generate HTML versions of any markdown file with a single click. HTML files are created alongside their markdown counterparts and are served through the same URL structure with a `.html` extension instead of `.md`. This dual-format approach allows you to provide documentation in both machine-readable markdown and browser-friendly HTML formats, making your documentation accessible to both developers and general audiences.

---

### Public Documentation Index

The plugin includes a dedicated public index feature that allows you to selectively publish documentation to site visitors. You can configure the index in two ways:

**Page Mode:** Display the index on any WordPress page of your choice. The plugin renders a structured listing of selected documents with their descriptions, organized by category. This approach integrates your documentation directly into your site's existing page structure.

**Shortcode Mode:** Use the `[mdsm_public_index]` shortcode to embed the documentation index anywhere in your content, providing maximum flexibility for documentation placement.

For each included document, you can customize the public-facing description independently from the internal description, allowing you to maintain different messaging for internal and external audiences. The index automatically adapts to your site's theme styling while maintaining a clean, professional appearance.

---
### 🔒 Cryptographic Post & Page Verification

#### Verification Badge System
- **Visual badges** on posts and pages showing integrity status
- **Three states**: ✓ Verified (green), ✗ Unverified (red), − Not Signed (gray)
- **Automatic display** below titles or content
- **Manual placement** via `[hash_verify]` shortcode
- **Downloadable verification files** for offline confirmation

#### Supported Hash Algorithms

**Standard Algorithms**:
- SHA-256 (default)
- SHA-512
- SHA3-256
- SHA3-512
- BLAKE2b

**Experimental Algorithms**:
- BLAKE3 (requires PHP extension)
- SHAKE128-256
- SHAKE256-512

  ## Hash Algorithm Comparison

| Algorithm | Speed | Output Size | Best For |
|-----------|-------|-------------|----------|
| SHA-256 | Fast | 256 bits | General use, maximum compatibility |
| SHA-512 | Fast | 512 bits | High security requirements |
| SHA3-256 | Medium | 256 bits | NIST standard, modern security |
| SHA3-512 | Medium | 512 bits | Maximum security |
| BLAKE2b | Very Fast | 512 bits | Performance-critical applications |
| BLAKE3 | Fastest | 256 bits | Experimental, Avaliable with provided extention |



All algorithms supported in both:
- Post/page hash generation
- Markdown file hash verification
- HTML rendering hash preservation

### HMAC Mode

1. Add `ARCHIVIOMD_HMAC_KEY` to wp-config.php
2. Enable in **Cryptographic Verification** settings
3. All new hashes use HMAC authentication

Add authentication to content verification:

```php
// Add to wp-config.php
define('ARCHIVIOMD_HMAC_KEY', 'your-secret-key');
```

HMAC mode provides:
- **Content integrity**: Proves content hasn't changed
- **Authenticity**: Proves hash was created by key holder
- **Tamper detection**: Any modification invalidates the hash
- **Key-based verification**: Offline verification requires secret key

Enable HMAC in **Cryptographic Verification** → **Settings** → **Enable HMAC Mode**

###  External Anchoring (Remote Distribution Chain)

Distribute cryptographic integrity records to Git repositories for tamper-evident audit trails.

#### Supported Providers
- **GitHub** (public and private repositories)
- **GitLab** (public and private repositories including self-hosted)

#### How It Works

1. Content is published or updated
2. Cryptographic hash is generated
3. JSON anchor record is created with:
   - Document/Post ID
   - Hash algorithm and value
   - HMAC value (if enabled)
   - Author ID
   - Timestamp
   - Plugin version
4. Record queued for distribution
5. WP-Cron pushes to GitHub/GitLab every 5 minutes
6. Git commit provides immutable timestamp
7. Creates tamper-evident chain of integrity records

#### Anchor Record Format

```json
{
  "document_id": "security.txt.md",
  "post_id": 123,
  "post_type": "post",
  "hash_algorithm": "sha256",
  "hash_value": "a3f5b8c2d9e1f4a7...",
  "hmac_value": "b7c6d8e2f1a4b7c6..." (if HMAC enabled),
  "author_id": 1,
  "timestamp": "2026-02-15T12:05:30Z",
  "plugin_version": "1.5.9",
  "integrity_mode": "hmac"
}
```

#### Configuration

**GitHub Setup**:
1. Create Personal Access Token with `repo` scope
2. Navigate to **External Anchoring** settings
3. Enter repository: `username/repo`
4. Enter branch: `main`
5. Paste token and save

**GitLab Setup**:
1. Create Personal Access Token with `api` scope
2. Navigate to **External Anchoring** settings
3. Enter repository: `username/project`
4. Enter GitLab URL (default: https://gitlab.com)
5. Paste token and save

#### Benefits

- **Tamper-evident**: Git commits prove when hashes were created
- **Distributed verification**: Anyone can verify via Git history
- **Automatic backups**: Integrity records preserved off-site
- **Audit compliance**: Immutable chain for regulatory requirements
- **Public transparency**: Optional public repository for trust

### HMAC Verification

```bash
# Requires secret key from wp-config.php
echo -n "canonical_content" | openssl dgst -sha256 -hmac "YOUR_SECRET_KEY"
```

### Git Chain Verification

```bash
# Clone anchor repository
git clone https://github.com/username/anchors.git
cd anchors

# View commit history
git log --oneline

# Check specific anchor
cat document_20260215_120530.json

# Verify timestamp with Git commit
git log --follow document_20260215_120530.json
```


### SEO & Crawling Files

Manage essential crawling and indexing files from the same admin page:

- `robots.txt`
- `llms.txt` (optional AI and LLM instructions)

Files are stored in the site root when permissions allow, with a plugin-directory fallback when necessary. The interface clearly indicates the current storage location for each file and displays warnings when files cannot be placed in the optimal location for search engine discovery.

---

### Sitemap Management

ArchivioMD supports sitemap generation for both small and large sites:

**Small sites:** Single `sitemap.xml` containing all URLs in one file, ideal for sites with fewer than 50,000 URLs.

**Large sites:** Multiple sitemaps organized by content type (posts, pages, custom post types) with a `sitemap_index.xml` that references them all, suitable for sites exceeding standard sitemap size limits.

Features include manual sitemap generation through a dedicated interface, optional automatic regeneration whenever content is added, updated, or deleted, and direct links to generated sitemap files with last-modified timestamps. The interface displays the current sitemap configuration and file count for easy monitoring.

---

### Professional Admin Interface

The plugin provides a single unified admin page titled **Meta Documentation & SEO** with a carefully designed user experience:

**Tab-based Navigation:** Four dedicated tabs separate Meta Documentation, SEO Files, Sitemaps, and Public Index configuration, keeping related functions grouped logically.

**Category Organization:** Documentation files are organized into collapsible categories based on their purpose, reducing visual clutter while maintaining easy access to all files.

**Search Functionality:** A prominent search bar allows instant filtering of files by name or description, making it easy to locate specific documents in large documentation sets.

**Inline Editing:** Click any Edit button to open a modal editor that loads the current file content, allows modification, and saves changes without leaving the admin page. The editor includes a reminder that saving empty content will delete the file.

**Status Indicators:** Each file card displays whether the file is Active (contains content) or Empty (not yet created), along with its current storage location on the server.

**Link Management:** For active files, the interface provides both View and Copy Link buttons for markdown files, plus View HTML and Copy HTML Link buttons when HTML versions have been generated. All links are absolute URLs ready for sharing or embedding.

**File Actions:** Generate HTML versions on demand, delete HTML files independently from their markdown source, and remove custom markdown file entries when no longer needed.

The interface is fully responsive and adapts to different screen sizes while maintaining functionality and readability.

---

## Philosophy

ArchivioMD is intentionally **document-first**.

It does not attempt to replace full SEO suites or marketing tools. Instead, it focuses on providing a clear, maintainable, and transparent way to manage the files and documentation that define how a site operates, communicates, and is indexed.

The plugin treats documentation as a first-class concern rather than an afterthought. By providing dedicated tools for document creation, organization, and publication, ArchivioMD encourages teams to maintain comprehensive, up-to-date documentation as part of their standard workflow rather than as a separate project that falls behind schedule.

## Installation

1. Download or clone this repository.
2. Upload the plugin folder to `/wp-content/plugins/`.
3. Activate **ArchivioMD** from the WordPress Plugins menu.
4. Navigate to **Meta Documentation & SEO** in the WordPress admin dashboard.
5. Go to **Settings â†’ Permalinks** and click **Save Changes** to flush rewrite rules and enable file serving.

The permalink flush is critical for the plugin to properly serve documentation files at their expected URLs. Without this step, requests for markdown and HTML files will return 404 errors.

---

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- Proper file system permissions for root-level file storage (optional but recommended for `robots.txt`)
- `manage_options` capability for accessing plugin features

---

## Security

ArchivioMD implements WordPress security best practices throughout:

- All AJAX requests are protected with WordPress nonces to prevent cross-site request forgery attacks.
- Access to all plugin functionality requires the `manage_options` capability, restricting use to administrators.
- All user input is sanitized using WordPress sanitization functions before processing or storage.
- All output is escaped appropriately to prevent cross-site scripting vulnerabilities.
- File operations validate filenames to prevent directory traversal attacks.
- Proper file permissions are set on creation and updates to maintain security.

If you discover a security issue, please report it responsibly. See `security.md` for reporting guidelines once available.

---

## File Serving and URL Structure

ArchivioMD uses WordPress rewrite rules to serve documentation files at clean URLs:

- Markdown files are accessible at `https://yoursite.com/filename.md`
- HTML files are accessible at `https://yoursite.com/filename.html`
- SEO files like `robots.txt` are accessible at `https://yoursite.com/robots.txt`
- Sitemaps are accessible at `https://yoursite.com/sitemap.xml` or `https://yoursite.com/sitemap_index.xml`

All files are served with appropriate content-type headers and caching headers for optimal performance. The plugin checks for physical files in the site root first, allowing manual overrides when needed, and falls back to plugin-managed files when no physical file exists.

---

## Roadmap

**Upcoming Enhancements:**

- Additional document types and templates for common documentation needs
- Optional social metadata and verification files for platform integration
- Enhanced sitemap controls including priority and change frequency settings
- UI refinements and accessibility improvements based on user feedback
- Export functionality for backing up documentation collections
- Bulk import capabilities for migrating existing documentation
- Version history tracking for documentation changes
- Collaborative editing features for team documentation workflows

---

## License

This plugin is licensed under the **GNU General Public License v2.0 (GPL-2.0)**, the same license used by WordPress.

See `license.md` for full license text.

---

## Author

**Mountain View Provisions LLC**

---

## Links

- Plugin Website: https://mountainviewprovisions.com/ArchivioMD
- GitHub Repository: https://github.com/mountainviewprovisions/archiviomd

---

## Support

For questions, feature requests, or bug reports, please use the GitHub repository's issue tracker. We welcome community feedback and contributions to make ArchivioMD more useful for documentation-focused WordPress sites.

