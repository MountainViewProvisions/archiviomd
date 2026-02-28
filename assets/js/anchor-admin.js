/**
 * ArchivioMD External Anchoring — Admin JavaScript
 * @since 1.5.0
 * @updated 1.6.0 — RFC 3161 TSA support
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

	function escHtml(str) {
		if (!str) { return ''; }
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	// Collect all form values — both Git and RFC 3161 fields.
	function getFormData() {
		return {
			provider:            $('#mdsm-provider').val() || '',
			// Git fields
			visibility:          $('#mdsm-visibility').val(),
			token:               $('#mdsm-token').val(),
			repo_owner:          $('#mdsm-repo-owner').val(),
			repo_name:           $('#mdsm-repo-name').val(),
			branch:              $('#mdsm-branch').val(),
			folder_path:         $('#mdsm-folder-path').val(),
			commit_message:      $('#mdsm-commit-message').val(),
			// RFC 3161 — checkbox is only present on the Trusted Timestamps page
			rfc3161_enabled:     $('#mdsm-rfc3161-enabled').is(':checked') ? '1' : ( $('#mdsm-rfc3161-enabled').length ? '' : undefined ),
			rfc3161_provider:    $('#mdsm-rfc3161-provider').val(),
			rfc3161_custom_url:  $('#mdsm-rfc3161-custom-url').val(),
			rfc3161_username:    $('#mdsm-rfc3161-username').val(),
			rfc3161_password:    $('#mdsm-rfc3161-password').val(),
			// Log management
			log_retention_days:  $('#mdsm-log-retention').val()
		};
	}

	// ── Provider visibility toggle ─────────────────────────────────────────────

	function toggleProviderFields() {
		var provider = $('#mdsm-provider').val();

		// RFC 3161 configuration rows are always visible on the Trusted Timestamps page
		// (they are controlled by the enable checkbox, not the git-provider dropdown).
		// On the Git Distribution page these rows do not exist, so this is a no-op there.
		if ( $('#mdsm-rfc3161-enabled').length ) {
			var rfc3161On = $('#mdsm-rfc3161-enabled').is(':checked');
			$('.mdsm-anchor-rfc3161-field').closest('tr').toggle(rfc3161On);
			if (rfc3161On) { toggleRFC3161SubProvider(); }
		}

		// Git rows — shown when a git provider is selected
		$('.mdsm-anchor-git-field').closest('tr').toggle(
			provider === 'github' || provider === 'gitlab'
		);
	}

	function toggleRFC3161SubProvider() {
		var sub = $('#mdsm-rfc3161-provider').val();

		// Custom URL field — only for "custom"
		$('.mdsm-rfc3161-custom-field').closest('tr').toggle(sub === 'custom');

		// Auth fields — hide for the four known public TSAs (they need no credentials)
		var publicProviders = ['freetsa', 'digicert', 'globalsign', 'sectigo'];
		var needsAuth = (publicProviders.indexOf(sub) === -1); // i.e. "custom"
		$('.mdsm-rfc3161-auth-field').closest('tr').toggle(needsAuth);

		// Update the description note from the data attribute
		var $option = $('#mdsm-rfc3161-provider option:selected');
		var notes   = $option.data('notes') || '';
		$('#mdsm-tsa-notes').text(notes);
	}

	// ── Visibility warning (Git only) ──────────────────────────────────────────

	function toggleVisibilityWarning() {
		var provider   = $('#mdsm-provider').val();
		var visibility = $('#mdsm-visibility').val();
		var $warning   = $('#mdsm-visibility-warning');

		if ((provider === 'github' || provider === 'gitlab') && visibility === 'public') {
			$warning.show();
		} else {
			$warning.hide();
		}
	}

	// ── Document Ready ────────────────────────────────────────────────────────

	$(function () {

		// ── Save settings ──────────────────────────────────────────────────────

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

					// If a new Git token was entered, replace the placeholder.
					if ($('#mdsm-token').val()) {
						$('#mdsm-token').val('').attr('placeholder', '(token saved — enter new value to replace)');
						if ($('.mdsm-token-saved').length === 0) {
							$('#mdsm-token').after('<span class="mdsm-token-saved">✓ Token saved</span>');
						}
					}

					// If a new TSA password was entered, replace the placeholder.
					if ($('#mdsm-rfc3161-password').val()) {
						$('#mdsm-rfc3161-password').val('').attr('placeholder', '(password saved — enter new value to replace)');
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

		// ── Test connection ────────────────────────────────────────────────────

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
				$btn.prop('disabled', false).text('Test Connection');
			});
		});

		// ── Clear queue ────────────────────────────────────────────────────────

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

		// ── On change handlers ─────────────────────────────────────────────────

		$('#mdsm-provider').on('change', function () {
			toggleProviderFields();
			toggleVisibilityWarning();
		});

		// RFC 3161 enable checkbox (Trusted Timestamps page only).
		$('#mdsm-rfc3161-enabled').on('change', function () {
			toggleProviderFields();
		});

		$('#mdsm-visibility').on('change', function () {
			toggleVisibilityWarning();
		});

		$('#mdsm-rfc3161-provider').on('change', function () {
			toggleRFC3161SubProvider();
		});

		// ── Initialise on page load ────────────────────────────────────────────

		toggleProviderFields();
		toggleVisibilityWarning();

		// ── Escape parent overflow clipping for the log table ─────────────────
		// WordPress sets overflow:hidden on #wpbody-content at <782px (common.css).
		// The card's border-radius also creates an implicit overflow clip.
		// The only guaranteed fix on mobile is to move the scroll wrapper outside
		// every constrained ancestor and re-attach it after the card, then keep
		// the pagination div inside the card with a pointer to the relocated table.
		(function relocateLogTable() {
			var $wrap = $('#mdsm-log-table-wrap');
			var $card = $wrap.closest('.mdsm-anchor-card');
			if (!$wrap.length || !$card.length) { return; }

			// Move the scroll wrapper to be a sibling AFTER the card.
			// This removes it from every overflow-clipping ancestor.
			$wrap.detach().insertAfter($card);

			// Give the relocated wrapper a clean full-width style.
			$wrap.css({
				'margin-left':  '0',
				'width':        '100%',
				'overflow-x':   'auto',
				'-webkit-overflow-scrolling': 'touch',
				'margin-bottom': '0',
				'border':       '1px solid #c3c4c7',
				'border-top':   'none',
				'background':   '#fff'
			});

			// Pull the pagination div out of the card too, put it after the table.
			var $pagination = $card.find('#mdsm-log-pagination');
			if ($pagination.length) {
				$pagination.detach().insertAfter($wrap).css({
					'padding': '10px 28px 0',
					'background': '#fff',
					'border': '1px solid #c3c4c7',
					'border-top': 'none',
					'margin-bottom': '24px'
				});
			}

			// Remove the bottom margin from the card since the table and
			// pagination now sit below it and provide their own spacing.
			$card.css('margin-bottom', '0');
		}());

		// ── Dismiss permanent failure notice ─────────────────────────────────
		$(document).on('click', '#mdsm-dismiss-fail-notice, #mdsm-perm-failure-notice .notice-dismiss', function() {
			$('#mdsm-perm-failure-notice').fadeOut(200);
			$.post(ajaxurl, {
				action: 'mdsm_anchor_dismiss_fail_notice',
				nonce:  mdsmAnchor.nonce
			});
		});

	}); // End document ready

}(jQuery));


// ── Activity Log ──────────────────────────────────────────────────────────────

(function ($) {
	'use strict';

	var anchorData    = window.mdsmAnchorData || {};
	var currentPage   = 1;
	var currentFilter = 'all';
	var totalPages    = 1;

	var statusColors = {
		anchored: '#00a32a',
		retry:    '#996800',
		failed:   '#d63638'
	};

	function statusBadge(status) {
		var color = statusColors[ status ] || '#50575e';
		return '<span style="display:inline-block;padding:1px 7px;border-radius:3px;font-size:11px;font-weight:600;color:#fff;background:' + color + ';">'
			+ escHtml( status.toUpperCase() )
			+ '</span>';
	}

	function escHtml(str) {
		if (!str) { return ''; }
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	// ── Load log page ────────────────────────────────────────────────────────

	function loadLog(page, filter) {
		var $tbody    = $('#mdsm-log-tbody');
		var $pageInfo = $('#mdsm-log-page-info');
		var $prev     = $('#mdsm-log-prev');
		var $next     = $('#mdsm-log-next');

		if (!$tbody.length) { return; }

		$tbody.html('<tr><td colspan="7" style="text-align:center;padding:16px;color:#666;">Loading\u2026</td></tr>');

		$.post(anchorData.ajaxUrl, {
			action:    'mdsm_anchor_get_log',
			nonce:     anchorData.nonce,
			page:      page,
			filter:    filter,
			log_scope: anchorData.logScope || 'all'
		}, function (response) {
			if (!response.success) {
				$tbody.html('<tr><td colspan="7" style="color:#d63638;padding:12px;">Error loading log.</td></tr>');
				return;
			}

			var data    = response.data;
			var entries = data.entries || [];
			totalPages  = data.pages || 1;

			if (entries.length === 0) {
				$tbody.html('<tr><td colspan="7" style="text-align:center;padding:16px;color:#888;">No log entries found.</td></tr>');
				$pageInfo.text('');
				$prev.prop('disabled', true);
				$next.prop('disabled', true);
				return;
			}

			var rows = '';
			$.each(entries, function (i, e) {
				var hashShort  = e.hash_value ? e.hash_value.substring(0, 16) + '\u2026' : '';
				var anchorCell = '';
				if (e.anchor_url) {
					anchorCell = '<a href="' + escHtml(e.anchor_url) + '" target="_blank" rel="noopener noreferrer" title="' + escHtml(e.anchor_url) + '">View</a>';
				}
				var td = 'style="padding:8px 14px;vertical-align:middle;border-bottom:1px solid #f0f0f1;"';
				var tdMono = 'style="padding:8px 14px;vertical-align:middle;border-bottom:1px solid #f0f0f1;font-family:monospace;font-size:11.5px;"';
				rows += '<tr>'
					+ '<td ' + td + '>' + escHtml(e.created_at) + ' UTC</td>'
					+ '<td ' + td + '>' + statusBadge(e.status) + '</td>'
					+ '<td ' + tdMono + '>' + escHtml(e.document_id) + '</td>'
					+ '<td ' + td + '>' + escHtml((e.provider || '').toUpperCase()) + '</td>'
					+ '<td ' + td + '>' + escHtml((e.hash_algorithm || '').toUpperCase()) + '</td>'
					+ '<td ' + tdMono + '>' + escHtml(hashShort) + '</td>'
					+ '<td ' + td + '>' + anchorCell + '</td>'
					+ '</tr>';
			});

			$tbody.html(rows);
			$pageInfo.text('Page ' + page + ' of ' + totalPages + ' (' + data.total + ' entries)');
			$prev.prop('disabled', page <= 1);
			$next.prop('disabled', page >= totalPages);
		})
		.fail(function () {
			$tbody.html('<tr><td colspan="7" style="color:#d63638;padding:12px;">Request failed.</td></tr>');
		});
	}

	// ── Filter badges ────────────────────────────────────────────────────────

	$(document).on('click', '.mdsm-log-badge', function () {
		$('.mdsm-log-badge').removeClass('active');
		$(this).addClass('active');
		currentFilter = $(this).data('filter') || 'all';
		currentPage   = 1;
		loadLog(currentPage, currentFilter);
	});

	// ── Pagination ───────────────────────────────────────────────────────────

	$(document).on('click', '#mdsm-log-prev', function () {
		if (currentPage > 1) { currentPage--; loadLog(currentPage, currentFilter); }
	});

	$(document).on('click', '#mdsm-log-next', function () {
		if (currentPage < totalPages) { currentPage++; loadLog(currentPage, currentFilter); }
	});

	// ── Clear log modal ──────────────────────────────────────────────────────

	$(document).on('click', '#mdsm-anchor-clear-log', function () {
		$('#mdsm-clear-log-confirm-input').val('');
		$('#mdsm-clear-log-confirm').prop('disabled', true).css('opacity', '.5');
		$('#mdsm-clear-log-modal-feedback').hide().text('');
		$('#mdsm-clear-log-modal').css('display', 'flex');
	});

	$(document).on('click', '#mdsm-clear-log-cancel', function () {
		$('#mdsm-clear-log-modal').hide();
	});

	$(document).on('click', '#mdsm-clear-log-modal', function (e) {
		if ($(e.target).is('#mdsm-clear-log-modal')) { $(this).hide(); }
	});

	$(document).on('input', '#mdsm-clear-log-confirm-input', function () {
		var matches = ($(this).val().trim().toUpperCase() === 'CLEAR LOG');
		$('#mdsm-clear-log-confirm').prop('disabled', !matches).css('opacity', matches ? '1' : '.5');
	});

	$(document).on('click', '#mdsm-clear-log-confirm', function () {
		var $btn      = $(this);
		var $feedback = $('#mdsm-clear-log-modal-feedback');
		var phrase    = $('#mdsm-clear-log-confirm-input').val().trim();

		$btn.prop('disabled', true).text('Clearing\u2026');
		$feedback.hide();

		$.post(anchorData.ajaxUrl, {
			action:       'mdsm_anchor_clear_log',
			nonce:        anchorData.nonce,
			confirmation: phrase
		}, function (response) {
			if (response.success) {
				$('#mdsm-clear-log-modal').hide();
				currentPage = 1; currentFilter = 'all';
				$('.mdsm-log-badge').removeClass('active');
				$('.mdsm-log-badge[data-filter="all"]').addClass('active');
				loadLog(1, 'all');
				$('.mdsm-log-badge strong').text('0');
				$('#mdsm-anchor-clear-log').prop('disabled', true);
				var $lf = $('#mdsm-log-feedback');
				$lf.removeClass('error').addClass('success').text(response.data.message || 'Log cleared.').show();
				setTimeout(function () { $lf.fadeOut(); }, 5000);
			} else {
				$feedback.removeClass('success').addClass('error')
					.text((response.data && response.data.message) || 'Error clearing log.').show();
				$btn.prop('disabled', false).text('Yes, Clear Log');
			}
		})
		.fail(function () {
			$feedback.addClass('error').text('Request failed.').show();
			$btn.prop('disabled', false).text('Yes, Clear Log');
		});
	});

	// ── Initialise ───────────────────────────────────────────────────────────

	$( function () {
		$('.mdsm-log-badge[data-filter="all"]').addClass('active');
		loadLog(1, 'all');
	});

}( jQuery ));
