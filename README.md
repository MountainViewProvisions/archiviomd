# ArchivioMD

**ArchivioMD** is a document-focused WordPress plugin that centralizes management of site documentation, SEO and crawling files, and sitemaps into a single, professional admin interface.

It is designed for developers, site owners, and teams who want a clean, structured way to manage site-critical documents without unnecessary SEO bloat.

[![Version](https://img.shields.io/badge/version-1.7.0-667eea)](https://github.com/MountainViewProvisions/archiviomd/releases)
[![License](https://img.shields.io/badge/license-GPL%20v2-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B)](https://wordpress.org)
[![Add-on](https://img.shields.io/badge/add--on-ArchivioID-764ba2)](https://github.com/MountainViewProvisions/archivio-id)
[![Crypto](https://img.shields.io/badge/crypto-SHA256%20%7C%20SHA512%20%7C%20SHA3%20%7C%20BLAKE2b%20%7C%20BLAKE3%20%7C%20SHAKE-10b981)](https://github.com/MountainViewProvisions/archiviomd)
[![HMAC](https://img.shields.io/badge/HMAC-supported-667eea)](https://github.com/MountainViewProvisions/archiviomd)
[![Anchoring](https://img.shields.io/badge/anchoring-GitHub%20%7C%20GitLab%20%7C%20RFC3161%20%7C%20Rekor-1f2937)](https://github.com/MountainViewProvisions/archiviomd)

---

## Table of Contents

- [Features](#features)
  - [Centralized Documentation Management](#centralized-documentation-management)
  - [Custom Markdown Files](#custom-markdown-files)
  - [HTML Rendering](#html-rendering)
  - [Public Documentation Index](#public-documentation-index)
  - [Cryptographic Post & Page Verification](#-cryptographic-post--page-verification)
  - [Ed25519 Document Signing](#ed25519-document-signing)
  - [DSSE Envelope Mode](#dsse-envelope-mode)
  - [External Anchoring](#external-anchoring-remote-distribution-chain)
  - [RFC 3161 Trusted Timestamps](#rfc-3161-trusted-timestamps)
  - [Sigstore / Rekor Transparency Log](#sigstore--rekor-transparency-log)
  - [Compliance & Audit Tools](#compliance--audit-tools)
  - [SEO & Crawling Files](#seo--crawling-files)
  - [Sitemap Management](#sitemap-management)
  - [Professional Admin Interface](#professional-admin-interface)
- [Philosophy](#philosophy)
- [Installation](#installation)
- [Requirements](#requirements)
- [Security](#security)
- [File Serving and URL Structure](#file-serving-and-url-structure)
- [WP-CLI](#wp-cli)
- [Roadmap](#roadmap)
- [License](#license)
- [Author](#author)
- [Links](#links)
- [Support](#support)

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

**Standard Algorithms:**

| Algorithm | Output Size | Notes |
|---|---|---|
| SHA-256 | 256 bits | Default. General use, maximum compatibility |
| SHA-224 | 224 bits | Truncated SHA-256 |
| SHA-384 | 384 bits | Truncated SHA-512 |
| SHA-512 | 512 bits | High security requirements |
| SHA-512/224 | 224 bits | SHA-512 with truncation |
| SHA-512/256 | 256 bits | SHA-512 with truncation |
| SHA3-256 | 256 bits | NIST standard, modern security |
| SHA3-512 | 512 bits | Maximum security |
| BLAKE2b-512 | 512 bits | Very fast, high security |
| BLAKE2s-256 | 256 bits | Optimized for 32-bit platforms |
| SHA-256d | 256 bits | Double SHA-256 |
| RIPEMD-160 | 160 bits | Legacy compatibility |
| Whirlpool-512 | 512 bits | ISO/IEC 10118-3 standard |

**Extended Algorithms:**

| Algorithm | Output Size | Notes |
|---|---|---|
| BLAKE3-256 | 256 bits | Fastest. Requires PHP extension or pure-PHP fallback |
| SHAKE128-256 | 256 bits | SHA-3 XOF variant |
| SHAKE256-512 | 512 bits | SHA-3 XOF variant |
| GOST R 34.11-94 | 256 bits | Russian GOST standard |
| GOST R 34.11-94 (CryptoPro) | 256 bits | CryptoPro S-Box variant |

**Legacy Algorithms (not recommended for new installations):**

| Algorithm | Notes |
|---|---|
| MD5 | Cryptographically broken. Interoperability only |
| SHA-1 | Deprecated. Interoperability only |

All algorithms are supported in:
- Post/page hash generation
- Markdown file hash verification
- HTML rendering hash preservation

#### HMAC Mode

Add authentication to content verification:

```php
// Add to wp-config.php
define('ARCHIVIOMD_HMAC_KEY', 'your-secret-key');
```

Then enable in **Cryptographic Verification → Settings → Enable HMAC Mode**.

HMAC mode provides:

- **Content integrity** — proves content hasn't changed
- **Authenticity** — proves hash was created by the key holder
- **Tamper detection** — any modification invalidates the hash
- **Key-based verification** — offline verification requires the secret key

**Offline HMAC verification:**

```bash
echo -n "canonical_content" | openssl dgst -sha256 -hmac "YOUR_SECRET_KEY"
```

---

### Ed25519 Document Signing

Posts, pages, and media are signed automatically on save using PHP sodium (`ext-sodium`, standard since PHP 7.2).

```php
// Add to wp-config.php
define('ARCHIVIOMD_ED25519_PRIVATE_KEY', 'your-128-char-hex-private-key');
define('ARCHIVIOMD_ED25519_PUBLIC_KEY',  'your-64-char-hex-public-key');
```

- Private key never stored in the database
- Public key published at `/.well-known/ed25519-pubkey.txt` for independent third-party verification
- No WordPress dependency required to verify — standard sodium tooling works
- In-browser keypair generator included in the admin UI

**Canonical message format:**

```
mdsm-ed25519-v1\n{post_id}\n{title}\n{slug}\n{content}\n{date_gmt}
```

**Offline verification:**

```bash
# Fetch public key
curl https://yoursite.com/.well-known/ed25519-pubkey.txt

# Verify signature using sodium tooling
```

Signatures are stored in `_mdsm_ed25519_sig` (hex) and `_mdsm_ed25519_signed_at` (timestamp) post meta.

---

### DSSE Envelope Mode

Wraps Ed25519 signatures in a Dead Simple Signing Envelope per the [Sigstore DSSE specification](https://github.com/secure-systems-lab/dsse).

When enabled, every post and media signature is additionally wrapped in a structured JSON envelope stored in the `_mdsm_ed25519_dsse` post meta key. The bare hex signature in `_mdsm_ed25519_sig` is always preserved alongside, so all existing verifiers continue to work without migration.

**Envelope format:**

```json
{
  "payload": "<base64(canonical_msg)>",
  "payloadType": "application/vnd.archiviomd.document",
  "signatures": [
    {
      "keyid": "<sha256_hex(pubkey_bytes)>",
      "sig": "<base64(sig_bytes)>"
    }
  ]
}
```

Signing is over the DSSE Pre-Authentication Encoding (PAE):

```
DSSEv1 {len(payloadType)} {payloadType} {len(payload)} {payload}
```

PAE binding prevents cross-protocol signature confusion attacks — a bare Ed25519 signature over a document hash cannot be replayed against the DSSE PAE and vice versa.

The `keyid` field is the SHA-256 fingerprint of the raw public key bytes, allowing a verifier to match a signature to a key without embedding the full 32-byte public key in the envelope.

Enable DSSE in **Cryptographic Verification → Settings**, nested beneath the Ed25519 signing card. The toggle is disabled until Ed25519 signing is fully configured and active.

---

### External Anchoring (Remote Distribution Chain)

Distribute cryptographic integrity records to Git repositories for tamper-evident audit trails.

#### Supported Providers

- **GitHub** (public and private repositories)
- **GitLab** (public and private repositories, including self-hosted)

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

Multiple providers can run simultaneously on every anchor job. Each is tracked independently — failure or rate-limiting of one does not block the others.

#### Anchor Record Format

```json
{
  "document_id": "security.txt.md",
  "post_id": 123,
  "post_type": "post",
  "hash_algorithm": "sha256",
  "hash_value": "a3f5b8c2d9e1f4a7...",
  "hmac_value": "b7c6d8e2f1a4b7c6...",
  "author_id": 1,
  "timestamp": "2026-02-15T12:05:30Z",
  "plugin_version": "1.7.0",
  "integrity_mode": "hmac"
}
```

#### Configuration

**GitHub:**

1. Create a Personal Access Token with `repo` scope
2. Navigate to **External Anchoring** settings
3. Enter repository: `username/repo`
4. Enter branch: `main`
5. Paste token and save

**GitLab:**

1. Create a Personal Access Token with `api` scope
2. Navigate to **External Anchoring** settings
3. Enter repository: `username/project`
4. Enter GitLab URL (default: `https://gitlab.com`)
5. Paste token and save

#### Benefits

- **Tamper-evident** — Git commits prove when hashes were created
- **Distributed verification** — anyone can verify via Git history
- **Automatic backups** — integrity records preserved off-site
- **Audit compliance** — immutable chain for regulatory requirements
- **Public transparency** — optional public repository for trust

#### Git Chain Verification

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

---

### RFC 3161 Trusted Timestamps

Every anchor job can submit the content hash to an RFC 3161-compliant Time Stamp Authority (TSA). The TSA returns a signed `.tsr` token binding the hash to a specific point in time — independently verifiable offline without trusting the plugin or the site.

#### Built-in TSA Providers

| Provider | Notes |
|---|---|
| FreeTSA.org | Free public TSA |
| DigiCert | Commercial |
| GlobalSign | Commercial |
| Sectigo | Commercial |

Custom TSA endpoints are also supported.

#### Storage & Access

- `.tsr` and `.tsq` files stored locally in `uploads/meta-docs/tsr-timestamps/`
- Blocked from direct HTTP access via `.htaccess`
- Served via authenticated download handler
- Manifest JSON (`.manifest.json`) publicly accessible — documents the verification process

#### Offline Verification

```bash
openssl ts -verify -in response.tsr -queryfile request.tsq -CAfile tsa.crt
```

RFC 3161 and Git anchoring can run simultaneously on every anchor job.

---

### Sigstore / Rekor Transparency Log

Every anchor job can simultaneously submit a `hashedrekord v0.0.1` entry to the public Sigstore Rekor append-only transparency log (`rekor.sigstore.dev`).

Rekor entries are immutable and publicly verifiable by anyone without pre-trusting the signer's key. No account, no API key, no special tooling required for verification.

#### How Signing Works

- When site Ed25519 keys are configured (`ARCHIVIOMD_ED25519_PRIVATE_KEY` / `ARCHIVIOMD_ED25519_PUBLIC_KEY`), entries are signed with the long-lived site key. The public key fingerprint links to `/.well-known/ed25519-pubkey.txt` for independent verification.
- Without site keys, a per-submission ephemeral keypair is generated automatically via PHP Sodium. The content hash is still immutably logged — ephemeral keys give you a genuine Rekor entry even without long-lived site keys.

#### Embedded Provenance

Every Rekor entry includes `customProperties` metadata:

| Field | Value |
|---|---|
| `archiviomd.site_url` | Publishing site URL |
| `archiviomd.document_id` | Post/document ID |
| `archiviomd.post_type` | WordPress post type |
| `archiviomd.hash_algorithm` | Algorithm used |
| `archiviomd.plugin_version` | ArchivioMD version |
| `archiviomd.pubkey_fingerprint` | SHA-256 of public key bytes (or `ephemeral`) |
| `archiviomd.key_type` | `site-longterm` or `ephemeral` |
| `archiviomd.pubkey_url` | `/.well-known/ed25519-pubkey.txt` URL |

These fields are human-readable provenance metadata embedded in the Rekor entry body. They are not cryptographically verified by Rekor itself — their value is auditability and cross-reference capability.

#### Independent Verification

```bash
# Via rekor-cli
rekor-cli get --log-index <INDEX>
rekor-cli verify --artifact-hash sha256:<HASH> --log-index <INDEX>

# Via REST API
curl https://rekor.sigstore.dev/api/v1/log/entries?logIndex=<INDEX>
```

Or browse entries at [search.sigstore.dev](https://search.sigstore.dev).

#### Inline Verification

The Rekor Activity Log in the admin includes a live **Verify** button. Clicking it fetches the inclusion proof directly from the Rekor API without leaving the admin — showing UUID, log index, integrated timestamp, artifact hash, inclusion proof status, signed entry timestamp, tree size, and checkpoint hash.

#### Requirements

- PHP Sodium (`ext-sodium`) — standard since PHP 7.2
- PHP OpenSSL (`ext-openssl`)
- Outbound HTTPS to `rekor.sigstore.dev` on port 443

Rekor is optional and disabled by default. Can run simultaneously with GitHub, GitLab, and RFC 3161 on every anchor job.

---

### Compliance & Audit Tools

Located at **Tools → ArchivioMD**.

#### Signed Export Receipts

Every CSV, Compliance JSON, and Backup ZIP generates a companion `.sig.json` integrity receipt containing:

- SHA-256 hash of the exported file
- Export type, filename, generation timestamp (UTC)
- Site URL, plugin version, generating user ID

When Ed25519 Document Signing is configured, the receipt additionally includes a detached Ed25519 signature binding all fields — preventing replay against a different file or context. A **Download Signature** button appears inline after each successful export.

#### Metadata Export (CSV)

Export all document metadata for compliance audits. Includes UUID, filename, path, last-modified timestamp (UTC), SHA-256 checksum, changelog count, and full changelog entries.

#### Compliance JSON Export

Structured export of the complete evidence package as a single JSON file. Preserves full relationships between posts, hash history, anchor log entries, and inlined RFC 3161 TSR manifests. Suitable for legal evidence packages, compliance audits, and SIEM ingestion.

#### Backup & Restore

Create portable ZIP archives of all metadata and files. Restore operations require a mandatory dry-run analysis before execution. Restore is explicit and admin-confirmed.

#### Metadata Verification

Manual checksum verification against stored SHA-256 values. Reports ✓ VERIFIED, ✗ MISMATCH, or ⚠ MISSING FILE. Read-only — does not modify files or metadata.

#### Metadata Cleanup on Uninstall

Opt-in, disabled by default. Requires typing `DELETE METADATA` to confirm. Markdown files are never deleted regardless of this setting.

---

### SEO & Crawling Files

Manage essential crawling and indexing files from the same admin page:

- `robots.txt`
- `llms.txt` (AI and LLM crawling instructions)
- `ads.txt`
- `app-ads.txt`
- `sellers.json`
- `ai.txt`

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

---

## Installation

1. Download or clone this repository
2. Upload the plugin folder to `/wp-content/plugins/`
3. Activate **ArchivioMD** from the WordPress Plugins menu
4. Navigate to **Meta Documentation & SEO** in the WordPress admin dashboard
5. Go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules and enable file serving

> **Important:** The permalink flush is critical for the plugin to properly serve documentation files at their expected URLs. Without this step, requests for markdown and HTML files will return 404 errors.

---

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 6.0 |
| PHP | 7.4 |
| File permissions | Root-level write access (optional, recommended for `robots.txt`) |
| Capability | `manage_options` |
| ext-sodium | Required for Ed25519 signing and Rekor (standard since PHP 7.2) |
| ext-openssl | Required for Rekor |

---

## Security

ArchivioMD implements WordPress security best practices throughout:

- All AJAX requests are protected with WordPress nonces to prevent CSRF attacks
- Access to all plugin functionality requires the `manage_options` capability, restricting use to administrators
- All user input is sanitized using WordPress sanitization functions before processing or storage
- All output is escaped appropriately to prevent XSS vulnerabilities
- File operations validate filenames to prevent directory traversal attacks
- Proper file permissions are set on creation and updates
- Ed25519 private keys are stored exclusively in `wp-config.php` — never in the database
- `.tsr` and `.tsq` files are blocked from direct HTTP access

If you discover a security issue, please report it responsibly. See [`SECURITY.md`](SECURITY.md) for reporting guidelines.

---

## File Serving and URL Structure

ArchivioMD uses WordPress rewrite rules to serve documentation files at clean URLs:

| File type | URL |
|---|---|
| Markdown | `https://yoursite.com/filename.md` |
| HTML | `https://yoursite.com/filename.html` |
| robots.txt | `https://yoursite.com/robots.txt` |
| llms.txt | `https://yoursite.com/llms.txt` |
| ads.txt | `https://yoursite.com/ads.txt` |
| sellers.json | `https://yoursite.com/sellers.json` |
| Ed25519 public key | `https://yoursite.com/.well-known/ed25519-pubkey.txt` |
| Sitemap (small) | `https://yoursite.com/sitemap.xml` |
| Sitemap (large) | `https://yoursite.com/sitemap_index.xml` |

All files are served with appropriate content-type and caching headers. The plugin checks for physical files in the site root first, allowing manual overrides, and falls back to plugin-managed files when no physical file exists.

---

## WP-CLI

ArchivioMD includes WP-CLI commands for server-side and automated workflows:

```bash
# Process the anchor queue immediately (bypasses cron)
wp archiviomd process-queue

# Anchor a specific post by ID
wp archiviomd anchor-post <post_id>

# Verify a specific post's hash
wp archiviomd verify <post_id>

# Prune old anchor log entries
wp archiviomd prune-log
```

---

## Roadmap

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

Licensed under the **GNU General Public License v2.0 (GPL-2.0)** — the same license used by WordPress.

See [`LICENSE`](LICENSE) for full license text.

---

## Author

**Mountain View Provisions LLC**

---

## Links

- Plugin website: [mountainviewprovisions.com/ArchivioMD](https://mountainviewprovisions.com/ArchivioMD)
- GitHub: [github.com/mountainviewprovisions/archiviomd](https://github.com/mountainviewprovisions/archiviomd)
- WordPress.org: [wordpress.org/plugins/archiviomd](https://wordpress.org/plugins/archiviomd)

---

## Support

For questions, feature requests, or bug reports, please use the [GitHub issue tracker](https://github.com/MountainViewProvisions/archiviomd/issues). We welcome community feedback and contributions to make ArchivioMD more useful for documentation-focused WordPress sites.
