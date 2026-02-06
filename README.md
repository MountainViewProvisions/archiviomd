# ArchivioMD

**ArchivioMD** is a document-focused WordPress plugin that centralizes management of site documentation, SEO and crawling files, and sitemaps into a single, professional admin interface.

It is designed for developers, site owners, and teams who want a clean, structured way to manage site-critical documents without unnecessary SEO bloat.

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

All documents are editable directly from the WordPress admin interface.

---

### SEO & Crawling Files
Manage essential crawling and indexing files from the same admin page:

- `robots.txt`
- `llms.txt` (optional AI / LLM instructions)

Files are stored in the site root when permissions allow, with a plugin-directory fallback when necessary.

---

### Sitemap Management
ArchivioMD supports sitemap generation for both small and large sites:

- **Small sites:** Single `sitemap.xml`
- **Large sites:** Multiple sitemaps with a `sitemap_index.xml`

Features include:
- Manual sitemap generation
- Optional automatic regeneration on content updates
- Direct links to generated sitemap files

---

### Professional Admin Interface
- Single unified admin page: **Meta Documentation & SEO**
- Clean, responsive, and easy-to-navigate layout
- Grouped sections for large document sets
- Built-in editor with save and delete functionality
- Visible file locations and copyable public links

---

## Philosophy

ArchivioMD is intentionally **document-first**.

It does not attempt to replace full SEO suites or marketing tools.  
Instead, it focuses on providing a clear, maintainable, and transparent way to manage the files and documentation that define how a site operates, communicates, and is indexed.

---

## Installation

1. Download or clone this repository.
2. Upload the plugin folder to `/wp-content/plugins/`.
3. Activate **ArchivioMD** from the WordPress Plugins menu.
4. Navigate to **Meta Documentation & SEO** in the WordPress admin dashboard.

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Proper file system permissions for root-level file storage (optional but recommended)

---

## Security

- Uses WordPress nonces and capability checks (`manage_options`)
- Sanitizes all input and output
- Ensures proper file permissions on creation and updates

If you discover a security issue, please report it responsibly.  
(See `security.md` once available.)

---

## Roadmap (High-Level)

- Additional document types and templates
- Optional social metadata and verification files
- Enhanced sitemap controls
- UI refinements and accessibility improvements

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
