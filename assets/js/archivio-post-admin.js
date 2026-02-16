/**
 * Archivio Post Admin JavaScript
 *
 * @package ArchivioMD
 * @since   1.2.0
 * @updated 1.5.7 – Sync with inline page script; add Algorithm/Mode columns, Refresh button, visibility auto-refresh
 */

(function($) {
    'use strict';

    // ── Audit log AJAX ──────────────────────────────────────────────────────

    function loadAuditLogs(page) {
        var $container = $('#audit-log-container');

        $.ajax({
            url:  archivioPostData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'archivio_post_get_audit_logs',
                nonce:  archivioPostData.nonce,
                page:   page || 1
            },
            success: function(response) {
                if (response.success) {
                    displayAuditLogs(response.data);
                } else {
                    $container.html(
                        '<div class="notice notice-error"><p>' +
                        (response.data && response.data.message ? response.data.message : archivioPostData.strings.error) +
                        '</p></div>'
                    );
                }
            },
            error: function() {
                $container.html(
                    '<div class="notice notice-error"><p>' +
                    archivioPostData.strings.error +
                    '</p></div>'
                );
            }
        });
    }

    function displayAuditLogs(data) {
        var html = '<table id="audit-log-table"><thead><tr>' +
            '<th>ID</th><th>Post ID</th><th>Type</th><th>Author ID</th>' +
            '<th>Algorithm</th><th>Mode</th><th>Event</th><th>Hash</th>' +
            '<th>Result</th><th>Timestamp</th>' +
            '</tr></thead><tbody>';

        if (!data.logs || data.logs.length === 0) {
            html += '<tr><td colspan="10" style="text-align:center;padding:40px;color:#666;">No audit logs found. Hashes will appear here when generated or verified.</td></tr>';
        } else {
            $.each(data.logs, function(i, log) {
                var eventClass = 'audit-log-event-' + log.event_type;

                // Derive algorithm and mode from packed hash prefix
                var algo, mode;
                if (log.hash.indexOf('hmac-') === 0) {
                    mode = 'HMAC';
                    algo = log.hash.split(':')[0].replace('hmac-', '').toUpperCase();
                } else if (log.hash.indexOf(':') > -1) {
                    mode = log.mode ? log.mode.charAt(0).toUpperCase() + log.mode.slice(1) : 'Standard';
                    algo = (log.algorithm || log.hash.split(':')[0]).toUpperCase();
                } else {
                    mode = 'Standard';
                    algo = (log.algorithm || 'sha256').toUpperCase();
                }
                algo = algo.replace('SHA3-', 'SHA3-').replace('BLAKE2B', 'BLAKE2b');

                var modeClass = (mode.toLowerCase() === 'hmac') ? 'audit-log-mode-hmac' : 'audit-log-mode-standard';

                // Show first 16 hex chars of the hash part only
                var hexPart = log.hash.indexOf(':') > -1 ? log.hash.split(':').slice(1).join(':') : log.hash;
                var hashDisplay = escapeHtml(hexPart.substring(0, 16)) + '&hellip;';

                // Build correct admin edit link based on post type
                var postType = log.post_type || 'post';
                var editLink = '?post=' + escapeHtml(String(log.post_id)) + '&action=edit';
                var typeLabel = postType.charAt(0).toUpperCase() + postType.slice(1).replace('_', ' ');
                var typeClass = 'audit-log-type-' + postType.replace(/[^a-z0-9]/gi, '-');

                html += '<tr>' +
                    '<td>' + escapeHtml(String(log.id)) + '</td>' +
                    '<td><a href="' + editLink + '" target="_blank">' + escapeHtml(String(log.post_id)) + '</a></td>' +
                    '<td class="' + typeClass + '">' + escapeHtml(typeLabel) + '</td>' +
                    '<td>' + escapeHtml(String(log.author_id)) + '</td>' +
                    '<td class="audit-log-algo">' + escapeHtml(algo) + '</td>' +
                    '<td class="' + modeClass + '">' + escapeHtml(mode) + '</td>' +
                    '<td class="' + eventClass + '">' + escapeHtml(log.event_type) + '</td>' +
                    '<td class="audit-log-hash" title="' + escapeHtml(log.hash) + '">' + hashDisplay + '</td>' +
                    '<td>' + escapeHtml(log.result) + '</td>' +
                    '<td>' + escapeHtml(log.timestamp) + '</td>' +
                    '</tr>';
            });
        }

        html += '</tbody></table>';
        $('#audit-log-container').html(html);

        // Pagination
        if (data.total_pages > 1) {
            var pagination = '<div style="margin-top:20px;">';
            if (data.page > 1) {
                pagination += '<button class="button" onclick="window.loadAuditLogsPage(' + (data.page - 1) + ')">« Previous</button>';
            }
            pagination += '<span style="margin:0 15px;">Page ' + data.page + ' of ' + data.total_pages + '</span>';
            if (data.page < data.total_pages) {
                pagination += '<button class="button" onclick="window.loadAuditLogsPage(' + (data.page + 1) + ')">Next »</button>';
            }
            pagination += '</div>';
            $('#audit-log-pagination').html(pagination);
        } else {
            $('#audit-log-pagination').html('');
        }
    }

    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // ── Settings form ───────────────────────────────────────────────────────

    function saveSettings() {
        var $btn    = $('#save-settings-btn');
        var $status = $('.archivio-post-save-status');
        
        var autoGenChecked = $('#auto-generate').is(':checked');

        $btn.prop('disabled', true).text(archivioPostData.strings.saving);
        $status.html('<span class="spinner is-active" style="float:none;"></span>');

        var postData = {
            action:           'archivio_post_save_settings',
            nonce:            archivioPostData.nonce,
            auto_generate:    autoGenChecked ? 'true' : 'false',
            show_badge:       $('#show-badge').is(':checked')       ? 'true' : 'false',
            show_badge_posts: $('#show-badge-posts').is(':checked') ? 'true' : 'false',
            show_badge_pages: $('#show-badge-pages').is(':checked') ? 'true' : 'false'
        };

        $.ajax({
            url:  archivioPostData.ajaxUrl,
            type: 'POST',
            data: postData,
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color:#0a7537;">✓ ' + response.data.message + '</span>');
                } else {
                    $status.html('<span style="color:#d73a49;">✗ ' + (response.data.message || archivioPostData.strings.error) + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $status.html('<span style="color:#d73a49;">✗ ' + archivioPostData.strings.error + '</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Save Settings');
                setTimeout(function() {
                    $status.fadeOut(function() { $(this).html('').show(); });
                }, 3000);
            }
        });
    }

    // ── Init ────────────────────────────────────────────────────────────────

    $(document).ready(function() {

        // Load audit logs on the audit tab
        if ($('#audit-log-container').length) {
            loadAuditLogs(1);
        }

        // Refresh button
        $(document).on('click', '#refresh-audit-log', function() {
            var $btn = $(this);
            $btn.prop('disabled', true);
            $btn.find('.dashicons').addClass('dashicons-update-spin');
            loadAuditLogs(1);
            setTimeout(function() {
                $btn.prop('disabled', false);
                $btn.find('.dashicons').removeClass('dashicons-update-spin');
            }, 800);
        });

        // Auto-refresh when browser tab regains focus
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && $('#audit-log-container').length) {
                loadAuditLogs(1);
            }
        });

        // Settings form
        $('#archivio-post-settings-form').on('submit', function(e) {
            e.preventDefault();
            saveSettings();
        });

    });

    // Expose for pagination onclick handlers
    window.loadAuditLogsPage = function(page) { loadAuditLogs(page); };

})(jQuery);
