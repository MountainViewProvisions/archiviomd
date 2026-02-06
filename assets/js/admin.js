/**
 * Meta Documentation & SEO Manager - Admin JavaScript
 */

(function($) {
    'use strict';
    
    var MDSM = {
        currentFileType: null,
        currentFileName: null,
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;
            
            // Tab switching
            $('.mdsm-tab-button').on('click', function() {
                var tab = $(this).data('tab');
                self.switchTab(tab);
            });
            
            // Edit file button
            $(document).on('click', '.mdsm-edit-button', function() {
                var fileType = $(this).data('file-type');
                var fileName = $(this).data('file-name');
                self.openEditor(fileType, fileName);
            });
            
            // Save file button
            $('#mdsm-save-file').on('click', function() {
                self.saveFile();
            });
            
            // Modal close
            $('.mdsm-modal-close').on('click', function() {
                self.closeModal();
            });
            
            // Close modal on outside click
            $('#mdsm-editor-modal').on('click', function(e) {
                if (e.target.id === 'mdsm-editor-modal') {
                    self.closeModal();
                }
            });
            
            // Copy link button
            $(document).on('click', '.mdsm-copy-link', function() {
                var url = $(this).data('url');
                self.copyToClipboard(url);
            });
            
            // Generate sitemap button
            $('#generate-sitemap').on('click', function() {
                self.generateSitemap();
            });
            
            // Category collapse toggle
            $('.mdsm-category-header').on('click', function() {
                $(this).parent('.mdsm-category').toggleClass('collapsed');
            });
            
            // Search functionality
            $('#mdsm-search').on('input', function() {
                self.filterFiles($(this).val());
            });
            
            // ESC key to close modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeModal();
                }
            });
        },
        
        /**
         * Switch tab
         */
        switchTab: function(tab) {
            $('.mdsm-tab-button').removeClass('active');
            $('.mdsm-tab-button[data-tab="' + tab + '"]').addClass('active');
            
            $('.mdsm-tab-content').removeClass('active');
            $('#tab-' + tab).addClass('active');
        },
        
        /**
         * Open editor modal
         */
        openEditor: function(fileType, fileName) {
            var self = this;
            self.currentFileType = fileType;
            self.currentFileName = fileName;
            
            // Get file card to retrieve description and location
            var $card = $('.mdsm-file-card[data-filename="' + fileName + '"]');
            var description = $card.data('description');
            
            // Set modal title and description
            $('#mdsm-editor-title').text('Edit: ' + fileName);
            $('#mdsm-editor-description').text(description);
            
            // Load file content
            $.ajax({
                url: mdsmData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mdsm_get_file_content',
                    nonce: mdsmData.nonce,
                    file_type: fileType,
                    file_name: fileName
                },
                success: function(response) {
                    if (response.success) {
                        $('#mdsm-editor-textarea').val(response.data.content);
                        $('#mdsm-editor-location').html(
                            '<span class="dashicons dashicons-location"></span> ' + 
                            response.data.location
                        );
                    }
                }
            });
            
            // Show modal
            $('#mdsm-editor-modal').addClass('active');
            $('#mdsm-editor-textarea').focus();
        },
        
        /**
         * Close modal
         */
        closeModal: function() {
            $('#mdsm-editor-modal').removeClass('active');
            this.currentFileType = null;
            this.currentFileName = null;
        },
        
        /**
         * Save file
         */
        saveFile: function() {
            var self = this;
            var content = $('#mdsm-editor-textarea').val();
            var $button = $('#mdsm-save-file');
            
            // Check if content is empty
            if (content.trim() === '') {
                if (!confirm(mdsmData.strings.confirmDelete)) {
                    return;
                }
            }
            
            // Disable button
            $button.prop('disabled', true).text(mdsmData.strings.saving);
            
            // Save via AJAX
            $.ajax({
                url: mdsmData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mdsm_save_file',
                    nonce: mdsmData.nonce,
                    file_type: self.currentFileType,
                    file_name: self.currentFileName,
                    content: content
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast(mdsmData.strings.saved, 'success');
                        self.updateFileCard(self.currentFileType, self.currentFileName, response.data);
                        self.closeModal();
                        
                        // Update counts
                        self.updateFileCounts();
                    } else {
                        self.showToast(response.data.message || mdsmData.strings.error, 'error');
                    }
                },
                error: function() {
                    self.showToast(mdsmData.strings.error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save File');
                }
            });
        },
        
        /**
         * Update file card after save
         */
        updateFileCard: function(fileType, fileName, data) {
            var $card = $('.mdsm-file-card[data-filename="' + fileName + '"]');
            var $statusBadge = $card.find('.mdsm-status-badge');
            var $fileMeta = $card.find('.mdsm-file-meta');
            var $location = $card.find('.mdsm-file-location span:last-child');
            
            if (data.exists) {
                // File exists - update status and add view/copy links
                $statusBadge.removeClass('mdsm-status-empty').addClass('mdsm-status-exists').text('Active');
                $location.text(data.location);
                
                // Remove existing actions if any
                $fileMeta.find('.mdsm-file-actions').remove();
                
                // Add actions
                var actionsHtml = '<div class="mdsm-file-actions">' +
                    '<a href="' + data.url + '" target="_blank" class="mdsm-view-link">' +
                        '<span class="dashicons dashicons-external"></span> View' +
                    '</a>' +
                    '<button class="mdsm-copy-link" data-url="' + data.url + '">' +
                        '<span class="dashicons dashicons-admin-links"></span> Copy Link' +
                    '</button>' +
                '</div>';
                
                $fileMeta.append(actionsHtml);
            } else {
                // File deleted - update status and remove actions
                $statusBadge.removeClass('mdsm-status-exists').addClass('mdsm-status-empty').text('Empty');
                $fileMeta.find('.mdsm-file-actions').remove();
            }
        },
        
        /**
         * Generate sitemap
         */
        generateSitemap: function() {
            var self = this;
            var sitemapType = $('input[name="sitemap_type"]:checked').val();
            var autoUpdate = $('#auto_update_sitemap').is(':checked');
            var $button = $('#generate-sitemap');
            
            // Disable button
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + mdsmData.strings.generating);
            
            $.ajax({
                url: mdsmData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mdsm_generate_sitemap',
                    nonce: mdsmData.nonce,
                    sitemap_type: sitemapType,
                    auto_update: autoUpdate
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast(mdsmData.strings.generated, 'success');
                        
                        // Reload page to update sitemap status
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        self.showToast(response.data.message || mdsmData.strings.error, 'error');
                    }
                },
                error: function() {
                    self.showToast(mdsmData.strings.error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Generate Sitemap Now');
                }
            });
        },
        
        /**
         * Copy to clipboard
         */
        copyToClipboard: function(text) {
            var self = this;
            
            // Create temporary input
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(text).select();
            
            try {
                document.execCommand('copy');
                self.showToast(mdsmData.strings.copied, 'success');
            } catch (err) {
                self.showToast('Could not copy link', 'error');
            }
            
            $temp.remove();
        },
        
        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            var $toast = $('#mdsm-toast');
            
            $toast.removeClass('success error').addClass(type).text(message).addClass('active');
            
            setTimeout(function() {
                $toast.removeClass('active');
            }, 3000);
        },
        
        /**
         * Filter files based on search
         */
        filterFiles: function(query) {
            query = query.toLowerCase().trim();
            
            if (query === '') {
                $('.mdsm-file-card').removeClass('hidden');
                $('.mdsm-category').show();
                return;
            }
            
            $('.mdsm-file-card').each(function() {
                var fileName = $(this).data('filename').toLowerCase();
                var description = $(this).data('description').toLowerCase();
                
                if (fileName.includes(query) || description.includes(query)) {
                    $(this).removeClass('hidden');
                } else {
                    $(this).addClass('hidden');
                }
            });
            
            // Hide categories with no visible files
            $('.mdsm-category').each(function() {
                var visibleFiles = $(this).find('.mdsm-file-card:not(.hidden)').length;
                if (visibleFiles === 0) {
                    $(this).hide();
                } else {
                    $(this).show();
                }
            });
        },
        
        /**
         * Update file counts in badges
         */
        updateFileCounts: function() {
            var self = this;
            
            $.ajax({
                url: mdsmData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mdsm_get_file_counts',
                    nonce: mdsmData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.mdsm-tab-button[data-tab="meta-docs"] .mdsm-badge').text(
                            response.data.meta_exists + '/' + response.data.meta_total
                        );
                        $('.mdsm-tab-button[data-tab="seo-files"] .mdsm-badge').text(
                            response.data.seo_exists + '/' + response.data.seo_total
                        );
                    }
                }
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        MDSM.init();
    });
    
})(jQuery);
