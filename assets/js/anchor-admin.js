/**
 * ArchivioMD External Anchoring — Admin JavaScript
 * @since 1.5.0
 */
/* global jQuery, mdsmAnchorData */
(function ($) {
	'use strict';

	var anchorData = window.mdsmAnchorData || {};
	var strings    = anchorData.strings || {};

	// ── Helpers ────────────────────────────────────────────────────────────────

	function showFeedback($el, message, type) {
		$el.removeClass('success error info')
		   .addClass(type)
		   .html(message)
		   .show();
	}

	function getFormData() {
		return {
			provider:       $('#mdsm-provider').val(),
			visibility:     $('#mdsm-visibility').val(),
			token:          $('#mdsm-token').val(),
			repo_owner:     $('#mdsm-repo-owner').val(),
			repo_name:      $('#mdsm-repo-name').val(),
			branch:         $('#mdsm-branch').val(),
			folder_path:    $('#mdsm-folder-path').val(),
			commit_message: $('#mdsm-commit-message').val()
		};
	}

	// ── Provider visibility toggle ────────────────────────────────────────────

	function toggleProviderFields() {
		var provider = $('#mdsm-provider').val();
		var $fields  = $('.mdsm-anchor-requires-provider');
		if (provider === 'none') {
			$fields.closest('tr').addClass('mdsm-hidden');
		} else {
			$fields.closest('tr').removeClass('mdsm-hidden');
		}
	}

	// ── Visibility warning ────────────────────────────────────────────────────

	function toggleVisibilityWarning() {
		var provider    = $('#mdsm-provider').val();
		var visibility  = $('#mdsm-visibility').val();
		var $warning    = $('#mdsm-visibility-warning');

		if (provider !== 'none' && visibility === 'public') {
			$warning.show();
		} else {
			$warning.hide();
		}
	}

	// ── Document Ready - Initialize all event handlers ───────────────────────

	$(function () {
		// ── Save settings ─────────────────────────────────────────────────────────

		$('#mdsm-anchor-save').on('click', function () {
		var $btn      = $(this);
		var $feedback = $('#mdsm-anchor-feedback');

		$btn.prop('disabled', true).text(strings.saving || 'Saving…');
		showFeedback($feedback, strings.saving || 'Saving…', 'info');

		var data = $.extend({}, getFormData(), {
			action: 'mdsm_anchor_save_settings',
			nonce:  anchorData.nonce
		});

		$.post(anchorData.ajaxUrl, data, function (response) {
			if (response.success) {
				showFeedback($feedback, response.data.message || strings.saved || 'Saved.', 'success');
				toggleVisibilityWarning();
				// If a new token was entered, replace placeholder.
				if ($('#mdsm-token').val()) {
					$('#mdsm-token').val('').attr('placeholder', '(token saved — enter new value to replace)');
					if ($('.mdsm-token-saved').length === 0) {
						$('#mdsm-token').after('<span class="mdsm-token-saved">✓ Token saved</span>');
					}
				}
			} else {
				showFeedback($feedback, (response.data && response.data.message) || strings.error || 'Error.', 'error');
			}
		})
		.fail(function () {
			showFeedback($feedback, strings.error || 'Error.', 'error');
		})
		.always(function () {
			$btn.prop('disabled', false).text('Save Settings');
		});
	});

	// ── Test connection ───────────────────────────────────────────────────────

	$('#mdsm-anchor-test').on('click', function () {
		var $btn    = $(this);
		var $result = $('#mdsm-test-result');

		$btn.prop('disabled', true).text(strings.testing || 'Testing connection…');
		$result.removeClass('test-success test-error').text(strings.testing || 'Testing connection…').show();

		var data = $.extend({}, getFormData(), {
			action: 'mdsm_anchor_test_connection',
			nonce:  anchorData.nonce
		});

		$.post(anchorData.ajaxUrl, data, function (response) {
			if (response.success) {
				$result.removeClass('test-error').addClass('test-success')
				       .html('<strong>✓ ' + escHtml(response.data.message) + '</strong>');
			} else {
				$result.removeClass('test-success').addClass('test-error')
				       .html('<strong>✗ ' + escHtml((response.data && response.data.message) || strings.error) + '</strong>');
			}
		})
		.fail(function () {
			$result.removeClass('test-success').addClass('test-error')
			       .text(strings.error || 'Connection test failed.');
		})
		.always(function () {
			$btn.prop('disabled', false).text('Test API Connection');
		});
	});

	// ── Clear queue ───────────────────────────────────────────────────────────

	$('#mdsm-anchor-clear-queue').on('click', function () {
		if (!confirm('Are you sure you want to clear all pending anchor jobs? This cannot be undone.')) {
			return;
		}

		var $btn      = $(this);
		var $feedback = $('#mdsm-queue-feedback');

		$btn.prop('disabled', true).text(strings.clearing || 'Clearing…');

		$.post(anchorData.ajaxUrl, {
			action: 'mdsm_anchor_clear_queue',
			nonce:  anchorData.nonce
		}, function (response) {
			if (response.success) {
				$('#mdsm-queue-count, #mdsm-queue-count-detail').text('0');
				$btn.prop('disabled', true);
				showFeedback($feedback, response.data.message || strings.queueCleared || 'Queue cleared.', 'success');
			} else {
				showFeedback($feedback, (response.data && response.data.message) || strings.error || 'Error.', 'error');
				$btn.prop('disabled', false).text('Clear Anchor Queue');
			}
		})
		.fail(function () {
			showFeedback($feedback, strings.error || 'Error.', 'error');
			$btn.prop('disabled', false).text('Clear Anchor Queue');
		})
		.always(function () {
			if (!$btn.prop('disabled')) {
				$btn.prop('disabled', false).text('Clear Anchor Queue');
			}
		});
	});

	// ── On change handlers ────────────────────────────────────────────────────

	$('#mdsm-provider').on('change', function () {
		toggleProviderFields();
		toggleVisibilityWarning();
	});

	$('#mdsm-visibility').on('change', function () {
		toggleVisibilityWarning();
	});

	// Utility: HTML escape
	function escHtml(str) {
		if (!str) { return ''; }
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	// Initialize on page load
	toggleProviderFields();
	toggleVisibilityWarning();
	}); // End document ready

}(jQuery));

// ── Activity Log ──────────────────────────────────────────────────────────────

(function ($) {
	'use strict';

	// Highlight the "All" badge on page load (cosmetic only — no AJAX table).
	$( function () {
		$( '.mdsm-log-badge[data-filter="all"]' ).addClass( 'active' );
	} );

}( jQuery ));
