/**
 * Archivio Post Frontend JavaScript
 *
 * @package ArchivioMD
 * @since 1.2.0
 */

(function($) {
    'use strict';
    
    /**
     * Handle verification file download
     */
    function handleDownload(postId) {
        var $button = $('.archivio-post-download[data-post-id="' + postId + '"]');
        var originalHtml = $button.html();
        
        // Show loading state
        $button.prop('disabled', true).html(
            '<svg class="spin" width="14" height="14" viewBox="0 0 16 16" fill="currentColor">' +
            '<path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zm0 14.5a6.5 6.5 0 1 1 0-13 6.5 6.5 0 0 1 0 13z" opacity=".3"/>' +
            '<path d="M8 0v3a5 5 0 0 1 0 10v3a8 8 0 0 0 0-16z"/>' +
            '</svg>'
        );
        
        $.ajax({
            url: archivioPostFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'archivio_post_download_verification',
                nonce: archivioPostFrontend.nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    // Create and trigger download
                    var blob = new Blob([response.data.content], { type: 'text/plain' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    // Show success feedback
                    showFeedback($button, 'success');
                } else {
                    console.error('Download failed:', response.data.message);
                    alert(response.data.message || archivioPostFrontend.strings.error);
                    showFeedback($button, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                alert(archivioPostFrontend.strings.error);
                showFeedback($button, 'error');
            },
            complete: function() {
                // Restore button
                setTimeout(function() {
                    $button.prop('disabled', false).html(originalHtml);
                }, 1000);
            }
        });
    }
    
    /**
     * Show visual feedback
     */
    function showFeedback($button, type) {
        var icon;
        if (type === 'success') {
            icon = '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">' +
                   '<path d="M13.78 4.22a.75.75 0 010 1.06l-7.25 7.25a.75.75 0 01-1.06 0L2.22 9.28a.75.75 0 011.06-1.06L6 10.94l6.72-6.72a.75.75 0 011.06 0z"/>' +
                   '</svg>';
        } else {
            icon = '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">' +
                   '<path d="M3.72 3.72a.75.75 0 011.06 0L8 6.94l3.22-3.22a.75.75 0 111.06 1.06L9.06 8l3.22 3.22a.75.75 0 11-1.06 1.06L8 9.06l-3.22 3.22a.75.75 0 01-1.06-1.06L6.94 8 3.72 4.78a.75.75 0 010-1.06z"/>' +
                   '</svg>';
        }
        $button.html(icon);
    }
    
    /**
     * Initialize
     */
    $(document).ready(function() {
        // Bind download button clicks
        $(document).on('click', '.archivio-post-download', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var postId = $(this).data('post-id');
            if (postId) {
                handleDownload(postId);
            }
        });
    });
    
})(jQuery);
