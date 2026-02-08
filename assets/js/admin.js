/**
 * Meta Documentation & SEO Manager - Admin JavaScript
 */

(function($) {
    'use strict';
    
    var MDSM = {
        currentFileType: null,
        currentFileName: null,
        creatingCustomFile: false,  // Flag to prevent multiple simultaneous requests
        
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
            
            console.log('MDSM: bindEvents() called');
            console.log('MDSM: Binding custom markdown button handler');
            
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
            $('.mdsm-category-header').on('click', function(e) {
                // Don't toggle if clicking on buttons or links within the content
                if (!$(e.target).closest('button, a').length) {
                    $(this).parent('.mdsm-category').toggleClass('collapsed');
                }
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
            
            // Generate HTML button
            $(document).on('click', '.mdsm-generate-html', function() {
                var fileType = $(this).data('file-type');
                var fileName = $(this).data('file-name');
                self.generateHtml(fileType, fileName, $(this));
            });
            
            // Copy HTML link button
            $(document).on('click', '.mdsm-copy-html-link', function() {
                var url = $(this).data('url');
                self.copyToClipboard(url);
            });
            
            // Add custom markdown button - ensure it fires even in collapsed state
            $(document).on('click', '#add-custom-markdown', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Custom Markdown button clicked');
                self.showCustomMarkdownPrompt();
                return false;
            });
            console.log('MDSM: Custom markdown button handler BOUND');
            console.log('MDSM: Checking if #add-custom-markdown exists:', $('#add-custom-markdown').length);
            console.log('MDSM: Button element:', $('#add-custom-markdown')[0]);
            
            // Delete custom file button
            $(document).on('click', '.mdsm-delete-custom-file', function() {
                var fileName = $(this).data('file-name');
                self.deleteCustomMarkdownFile(fileName, $(this));
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
                        '<span class="dashicons dashicons-external"></span> View MD' +
                    '</a>' +
                    '<button class="mdsm-copy-link" data-url="' + data.url + '" title="Copy MD Link">' +
                        '<span class="dashicons dashicons-admin-links"></span> Copy MD Link' +
                    '</button>';
                
                // Add HTML buttons if this is a meta file
                if (fileType === 'meta') {
                    if (data.html_generated && data.html_url) {
                        actionsHtml += '<a href="' + data.html_url + '" target="_blank" class="mdsm-view-link mdsm-html-link">' +
                            '<span class="dashicons dashicons-media-document"></span> View HTML' +
                        '</a>' +
                        '<button class="mdsm-copy-html-link" data-url="' + data.html_url + '" data-file-type="' + fileType + '" data-file-name="' + fileName + '" title="Copy HTML Link">' +
                            '<span class="dashicons dashicons-admin-links"></span> Copy HTML Link' +
                        '</button>';
                    } else {
                        actionsHtml += '<button class="mdsm-generate-html" data-file-type="' + fileType + '" data-file-name="' + fileName + '" title="Generate HTML Version">' +
                            '<span class="dashicons dashicons-media-code"></span> Generate HTML' +
                        '</button>';
                    }
                }
                
                actionsHtml += '</div>';
                
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
        },
        
        /**
         * Generate HTML file
         */
        generateHtml: function(fileType, fileName, $button) {
            var self = this;
            var originalHtml = $button.html();
            
            // Disable button and show loading
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + mdsmData.strings.generatingHtml);
            
            $.ajax({
                url: mdsmData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mdsm_generate_html',
                    nonce: mdsmData.nonce,
                    file_type: fileType,
                    file_name: fileName
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast(mdsmData.strings.htmlGenerated, 'success');
                        
                        // Replace button with HTML links
                        var $card = $('.mdsm-file-card[data-filename="' + fileName + '"]');
                        var $actions = $card.find('.mdsm-file-actions');
                        
                        // Remove generate button
                        $button.remove();
                        
                        // Add HTML view and copy buttons
                        var htmlButtons = '<a href="' + response.data.html_url + '" target="_blank" class="mdsm-view-link mdsm-html-link">' +
                            '<span class="dashicons dashicons-media-document"></span> View HTML' +
                        '</a>' +
                        '<button class="mdsm-copy-html-link" data-url="' + response.data.html_url + '" data-file-type="' + fileType + '" data-file-name="' + fileName + '" title="Copy HTML Link">' +
                            '<span class="dashicons dashicons-admin-links"></span> Copy HTML Link' +
                        '</button>';
                        
                        $actions.append(htmlButtons);
                    } else {
                        self.showToast(response.data.message || mdsmData.strings.error, 'error');
                        $button.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function() {
                    self.showToast(mdsmData.strings.error, 'error');
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        },
        
        /**
         * Show custom markdown file creation prompt
         */
        showCustomMarkdownPrompt: function() {
            var self = this;
            
            console.log('Custom Markdown: mdsmData =', mdsmData);
            console.log('Custom Markdown: ajaxUrl =', mdsmData.ajaxUrl);
            console.log('Custom Markdown: nonce =', mdsmData.nonce);
            
            // Prevent multiple simultaneous requests
            if (self.creatingCustomFile) {
                console.log('Custom Markdown: Already creating a file, ignoring duplicate request');
                return;
            }
            
            console.log('Custom Markdown: Prompt opened');
            
            var filename = prompt('Enter the filename for your custom markdown file:\n\n(It will automatically get a .md extension if not provided)');
            
            if (!filename) {
                console.log('Custom Markdown: User cancelled or entered empty filename');
                return; // User cancelled
            }
            
            filename = filename.trim();
            
            if (!filename) {
                console.log('Custom Markdown: Filename was empty after trim');
                self.showToast('Filename cannot be empty', 'error');
                return;
            }
            
            console.log('Custom Markdown: Filename entered:', filename);
            
            // Ask for optional description
            var description = prompt('Enter an optional description for this file:', 'Custom markdown documentation');
            
            if (description === null) {
                description = ''; // User cancelled description, but we continue
            }
            
            console.log('Custom Markdown: Description:', description);
            console.log('Custom Markdown: Sending AJAX request...');
            
            // Set flag to prevent duplicate requests
            self.creatingCustomFile = true;
            
            var ajaxData = {
                action: 'mdsm_create_custom_markdown',
                nonce: mdsmData.nonce,
                filename: filename,
                description: description
            };
            
            console.log('Custom Markdown: AJAX data =', ajaxData);
            console.log('Custom Markdown: AJAX URL =', mdsmData.ajaxUrl);
            
            // Create the file
            $.ajax({
                url: mdsmData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: ajaxData,
                success: function(response) {
                    console.log('Custom Markdown: AJAX success response:', response);
                    self.creatingCustomFile = false;  // Reset flag
                    
                    if (response.success) {
                        self.showToast(response.data.message, 'success');
                        // Reload page to show the new file
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        self.showToast(response.data.message || 'Failed to create custom markdown file', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Custom Markdown: AJAX error:', {xhr: xhr, status: status, error: error, responseText: xhr.responseText});
                    self.creatingCustomFile = false;  // Reset flag
                    self.showToast('Error creating custom markdown file. Check console for details.', 'error');
                }
            });
        },
        
        /**
         * Delete custom markdown file
         */
        deleteCustomMarkdownFile: function(fileName, $button) {
            var self = this;
            
            if (!confirm('Delete "' + fileName + '" from your custom markdown list?\n\nNote: This only removes the file from your custom list. If you have saved content for this file, you can delete it by editing the file and saving with empty content.')) {
                return;
            }
            
            var originalHtml = $button.html();
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span>');
            
            $.ajax({
                url: mdsmData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mdsm_delete_custom_markdown',
                    nonce: mdsmData.nonce,
                    filename: fileName
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast(response.data.message, 'success');
                        // Remove the card from the UI
                        $button.closest('.mdsm-custom-file-card').fadeOut(300, function() {
                            $(this).remove();
                            // Check if there are any custom files left
                            var $grid = $('.mdsm-category:has(.mdsm-custom-markdown-controls) .mdsm-file-grid');
                            if ($grid.find('.mdsm-custom-file-card').length === 0) {
                                $grid.html('<p class="mdsm-empty-message">No custom markdown files yet. Click "+ Custom Markdown" to create one.</p>');
                            }
                        });
                    } else {
                        self.showToast(response.data.message || 'Failed to delete custom markdown file', 'error');
                        $button.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function() {
                    self.showToast('Error deleting custom markdown file', 'error');
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        }
    };
    
    // Expose MDSM globally for other modules
    window.MDSM = MDSM;
    
    // Initialize on document ready
    $(document).ready(function() {
        MDSM.init();
    });
    
})(jQuery);

console.log('>>> BEFORE PUBLIC INDEX BLOCK <<<');

// Public Index functionality
(function($) {
    'use strict';
    
    console.log('=== PUBLIC INDEX JAVASCRIPT LOADED ===');
    
    $(document).ready(function() {
        console.log('=== PUBLIC INDEX DOCUMENT READY ===');
        console.log('mdsmData exists:', typeof mdsmData !== 'undefined');
        console.log('mdsmData:', typeof mdsmData !== 'undefined' ? mdsmData : 'UNDEFINED');
        console.log('Save button exists:', $('#save-public-index').length);
        console.log('Save button element:', $('#save-public-index')[0]);
        
        // Toggle between page and shortcode mode
        $('input[name="index_output_mode"]').on('change', function() {
            var mode = $(this).val();
            if (mode === 'page') {
                $('#mdsm-page-config').show();
                $('#mdsm-shortcode-info').hide();
            } else {
                $('#mdsm-page-config').hide();
                $('#mdsm-shortcode-info').show();
            }
        });
        
        // Trigger initial state on page load
        $('input[name="index_output_mode"]:checked').trigger('change');
        
        // Copy shortcode button
        $('.mdsm-copy-shortcode').on('click', function() {
            var shortcode = $(this).data('shortcode');
            
            // Create temporary input
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(shortcode).select();
            document.execCommand('copy');
            $temp.remove();
            
            // Show feedback
            var $button = $(this);
            var originalHtml = $button.html();
            $button.html('<span class="dashicons dashicons-yes"></span> Copied!');
            
            setTimeout(function() {
                $button.html(originalHtml);
            }, 2000);
        });
        
        // Save public index settings
        $('#save-public-index').on('click', function(e) {
            e.preventDefault();
            console.log('Save button clicked'); // Debug
            
            var $button = $(this);
            var originalHtml = $button.html();
            
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Saving...');
            
            // Get mode
            var mode = $('input[name="index_output_mode"]:checked').val();
            var enabled = mode === 'page' ? '1' : '0';
            
            console.log('Mode:', mode, 'Enabled:', enabled); // Debug
            
            // Get page ID
            var pageId = $('#index_page_id').val();
            
            console.log('Page ID:', pageId); // Debug
            
            // Validate page selection if page mode is enabled
            if (mode === 'page' && !pageId) {
                console.log('Validation failed: No page selected'); // Debug
                if (typeof window.MDSM !== 'undefined' && window.MDSM.showToast) {
                    window.MDSM.showToast('Please select a page to display the Public Index', 'error');
                } else {
                    alert('Please select a page to display the Public Index');
                }
                $button.prop('disabled', false).html(originalHtml);
                return;
            }
            
            // Get selected documents
            var publicDocs = {};
            $('input[name="public_docs[]"]:checked').each(function() {
                publicDocs[$(this).val()] = true;
            });
            
            console.log('Public docs:', publicDocs); // Debug
            
            // Get descriptions
            var descriptions = {};
            $('input[name^="doc_desc_"]').each(function() {
                var filename = $(this).attr('name').replace('doc_desc_', '');
                var value = $(this).val();
                if (value) {
                    descriptions[filename] = value;
                }
            });
            
            console.log('Descriptions:', descriptions); // Debug
            
            var ajaxData = {
                action: 'mdsm_save_public_index',
                nonce: mdsmData.nonce,
                enabled: enabled,
                page_id: pageId,
                public_docs: publicDocs,
                descriptions: descriptions
            };
            
            console.log('Sending AJAX request:', ajaxData); // Debug
            
            // Save via AJAX
            $.ajax({
                url: mdsmData.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    console.log('AJAX success response:', response); // Debug
                    if (response.success) {
                        if (typeof window.MDSM !== 'undefined' && window.MDSM.showToast) {
                            window.MDSM.showToast('Public index settings saved successfully!', 'success');
                        } else {
                            alert('Public index settings saved successfully!');
                        }
                        
                        // Reload after short delay to update UI
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        console.error('Save failed:', response.data); // Debug
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Error saving settings';
                        if (typeof window.MDSM !== 'undefined' && window.MDSM.showToast) {
                            window.MDSM.showToast(errorMsg, 'error');
                        } else {
                            alert(errorMsg);
                        }
                        $button.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {xhr: xhr, status: status, error: error, responseText: xhr.responseText}); // Debug
                    var errorMsg = 'Error saving settings. Check console for details.';
                    if (typeof window.MDSM !== 'undefined' && window.MDSM.showToast) {
                        window.MDSM.showToast(errorMsg, 'error');
                    } else {
                        alert(errorMsg);
                    }
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        });
    });
    
})(jQuery);
