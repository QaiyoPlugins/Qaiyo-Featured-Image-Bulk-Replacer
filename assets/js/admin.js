/* global jQuery, wp, qfibr_data */
/**
 * Qaiyo Featured Image Bulk Replacer — admin screen.
 *
 * Accessibility: every result is announced through wp.a11y.speak, validation
 * errors are shown in a focusable alert and the offending field gets
 * aria-invalid, server messages are inserted as text (never as HTML), and the
 * progress bar keeps aria-valuenow in sync.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var i18n        = qfibr_data.i18n;
		var $form       = $( '#qfibr-form' );
		var $error      = $( '#qfibr-form-error' );
		var $results    = $( '#qfibr-preview-results' );
		var $progress   = $( '#qfibr-progress' );
		var $bar        = $progress.find( '[role="progressbar"]' );
		var $fill       = $( '#qfibr-progress-fill' );
		var $label      = $( '#qfibr-progress-label' );
		var $attachment = $( '#qfibr_attachment_id' );
		var $pool       = $( '#qfibr_attachment_ids' );
		var $imageUrl   = $( '#qfibr_image_url' );
		var $mediaPrev  = $( '#qfibr-media-preview' );
		var $mediaBtn   = $( '#qfibr-media-btn' );
		var frames      = {};
		var running     = false;

		function speak( text, politeness ) {
			if ( window.wp && wp.a11y && wp.a11y.speak ) {
				wp.a11y.speak( text, politeness || 'polite' );
			}
		}

		function format( template ) {
			var args = Array.prototype.slice.call( arguments, 1 );
			var i    = 0;
			return String( template ).replace( /%(\d+\$)?d/g, function ( match, pos ) {
				var index = pos ? parseInt( pos, 10 ) - 1 : i++;
				return String( args[ index ] );
			} );
		}

		function mode() {
			return $( 'input[name="qfibr_assign_mode"]:checked' ).val() || 'single';
		}

		// --- Errors -----------------------------------------------------------
		function clearError() {
			$error.attr( 'hidden', 'hidden' ).empty();
			$form.find( '[aria-invalid="true"]' ).removeAttr( 'aria-invalid' );
		}

		function showError( message, fieldId ) {
			$error.text( message ).removeAttr( 'hidden' );
			if ( fieldId ) {
				$( '#' + fieldId ).attr( 'aria-invalid', 'true' );
			}
			$error.trigger( 'focus' );
		}

		// --- Assignment mode ----------------------------------------------------
		function syncMode() {
			var random = 'random' === mode();
			$mediaBtn.find( '.qfibr-media-btn-label' ).text( random ? i18n.choose_many : i18n.choose_one );
			$( '.qfibr-url-field' ).toggle( ! random );
			$( '.qfibr-single-only' ).toggle( ! random );
			renderSelection( random ? poolIds() : ( '0' !== $attachment.val() ? [ $attachment.val() ] : [] ) );
		}

		$( 'input[name="qfibr_assign_mode"]' ).on( 'change', syncMode );

		function poolIds() {
			var raw = $.trim( $pool.val() );
			return raw ? raw.split( ',' ) : [];
		}

		var thumbs = {};

		function renderSelection( ids ) {
			$mediaPrev.empty();
			if ( ! ids.length ) {
				$mediaPrev.attr( 'hidden', 'hidden' );
				return;
			}
			$mediaPrev.removeAttr( 'hidden' );
			ids.forEach( function ( id ) {
				var $li  = $( '<li/>' );
				var $img = $( '<img/>', { src: thumbs[ id ] || '', alt: '' } );
				$li.append( $img );
				if ( 'random' === mode() ) {
					$( '<button type="button" class="qfibr-pool-remove"/>' )
						.attr( 'aria-label', i18n.remove_image )
						.attr( 'data-id', id )
						.append( '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>' )
						.appendTo( $li );
				}
				$mediaPrev.append( $li );
			} );
		}

		$mediaPrev.on( 'click', '.qfibr-pool-remove', function () {
			var id   = String( $( this ).data( 'id' ) );
			var left = poolIds().filter( function ( x ) {
				return x !== id;
			} );
			$pool.val( left.join( ',' ) );
			renderSelection( left );
			speak( format( i18n.selected, left.length ) );
			$mediaBtn.trigger( 'focus' );
		} );

		// --- Media Library picker ---------------------------------------------
		$mediaBtn.on( 'click', function ( e ) {
			e.preventDefault();
			if ( ! ( window.wp && wp.media ) ) {
				return;
			}
			var random = 'random' === mode();
			var key    = random ? 'many' : 'one';

			if ( ! frames[ key ] ) {
				frames[ key ] = wp.media( {
					title:    random ? i18n.media_title_many : i18n.media_title,
					button:   { text: random ? i18n.media_button_many : i18n.media_button },
					library:  { type: 'image' },
					multiple: random ? 'add' : false
				} );
				frames[ key ].on( 'select', function () {
					var picked = frames[ key ].state().get( 'selection' ).toJSON();
					picked.forEach( function ( att ) {
						thumbs[ att.id ] = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
					} );
					if ( 'many' === key ) {
						var ids = picked.map( function ( att ) {
							return String( att.id );
						} );
						$pool.val( ids.join( ',' ) );
						renderSelection( ids );
						speak( format( i18n.selected, ids.length ) );
					} else if ( picked.length ) {
						$attachment.val( picked[ 0 ].id );
						$imageUrl.val( picked[ 0 ].url );
						renderSelection( [ String( picked[ 0 ].id ) ] );
						speak( format( i18n.selected, 1 ) );
					}
					clearError();
				} );
				frames[ key ].on( 'close', function () {
					$mediaBtn.trigger( 'focus' );
				} );
			}
			frames[ key ].open();
		} );

		// If the URL is edited by hand, drop the resolved attachment ID so the
		// server re-resolves from the typed URL.
		$imageUrl.on( 'input', function () {
			$attachment.val( '0' );
			renderSelection( [] );
		} );

		// --- Criteria -----------------------------------------------------------
		function criteria() {
			var data = {
				assign_mode:    mode(),
				attachment_id:  $attachment.val() || '0',
				attachment_ids: $pool.val(),
				image_url:      $.trim( $imageUrl.val() ),
				before_date:    $.trim( $( '#qfibr_before_date' ).val() ),
				after_date:     $.trim( $( '#qfibr_after_date' ).val() ),
				post_type:      $( '#qfibr_post_type' ).val(),
				scope:          $( '#qfibr_scope' ).val(),
				category:       $( '#qfibr_category' ).val() || '0',
				tag:            $( '#qfibr_tag' ).val() || '0',
				tax_term:       $( '#qfibr_tax_term' ).val() || '',
				author:         $( '#qfibr_author' ).val() || '0',
				alt_text:       $( '#qfibr_alt_text' ).val()
			};
			// Add-on fields (e.g. Pro WooCommerce filters).
			$form.find( '[data-qfibr-extra]' ).each( function () {
				var $el = $( this );
				var val = $el.is( ':checkbox' ) ? ( $el.is( ':checked' ) ? '1' : '' ) : $el.val();
				data[ 'extra[' + $el.data( 'qfibr-extra' ) + ']' ] = val || '';
			} );
			return data;
		}

		// Expose for add-ons (Pro presets / background queue / stock photos).
		window.qfibrCriteria = criteria;

		/**
		 * Fill the image part of the form from an add-on.
		 *
		 * @param {string} assignMode 'single' or 'random'.
		 * @param {Array}  ids        Attachment IDs.
		 * @param {string} url        Image URL (single mode, optional).
		 */
		window.qfibrSetSelection = function ( assignMode, ids, url ) {
			ids = ( ids || [] ).map( String ).filter( function ( id ) {
				return id && '0' !== id;
			} );
			$( 'input[name="qfibr_assign_mode"][value="' + ( 'random' === assignMode ? 'random' : 'single' ) + '"]' ).prop( 'checked', true );
			if ( 'random' === assignMode ) {
				$pool.val( ids.join( ',' ) );
				$attachment.val( '0' );
			} else {
				$attachment.val( ids[ 0 ] || '0' );
				$imageUrl.val( url || '' );
				$pool.val( '' );
			}
			syncMode();
			// Fetch thumbnails we do not know yet, then redraw.
			if ( window.wp && wp.media && wp.media.attachment ) {
				ids.forEach( function ( id ) {
					if ( thumbs[ id ] ) {
						return;
					}
					wp.media.attachment( id ).fetch().done( function ( att ) {
						thumbs[ id ] = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
						syncMode();
					} );
				} );
			}
		};

		function validate() {
			clearError();
			var c = criteria();
			if ( 'random' === c.assign_mode ) {
				if ( poolIds().length < 2 ) {
					showError( i18n.need_pool, 'qfibr-media-btn' );
					return false;
				}
			} else if ( ( ! c.attachment_id || '0' === c.attachment_id ) && ! c.image_url ) {
				showError( i18n.need_image, 'qfibr_image_url' );
				return false;
			}
			if ( ! c.before_date ) {
				showError( i18n.need_date, 'qfibr_before_date' );
				return false;
			}
			return true;
		}

		function lockButtons( lock ) {
			running = lock;
			$( '.qfibr-run, #qfibr-preview-btn, #qfibr-orphan-scan' ).prop( 'disabled', lock );
			$( '.qfibr-wrap' ).attr( 'aria-busy', lock ? 'true' : 'false' );
		}

		function serverError( res, fallback ) {
			var msg   = ( res && res.data && res.data.message ) ? res.data.message : ( fallback || i18n.failed );
			var field = ( res && res.data && res.data.field ) ? res.data.field : '';
			return { message: msg, field: field };
		}

		// --- Preview --------------------------------------------------------------
		$( '#qfibr-preview-btn' ).on( 'click', function () {
			if ( running || ! validate() ) {
				return;
			}
			$results.removeAttr( 'hidden' ).empty().append( $( '<p class="qfibr-loading"/>' ).text( i18n.previewing ) );

			$.post( qfibr_data.ajax_url, $.extend( { action: 'qfibr_preview', nonce: qfibr_data.nonce }, criteria() ) )
				.done( function ( res ) {
					if ( res && res.success && res.data && res.data.html ) {
						// Server-built, escaped markup.
						$results.html( res.data.html );
						speak( i18n.preview_ready );
					} else {
						var err = serverError( res );
						$results.attr( 'hidden', 'hidden' ).empty();
						showError( err.message, err.field );
					}
				} )
				.fail( function () {
					$results.attr( 'hidden', 'hidden' ).empty();
					showError( i18n.preview_error );
				} );
		} );

		// --- Runs -----------------------------------------------------------------
		$( '.qfibr-wrap' ).on( 'click', '.qfibr-run', function () {
			if ( running ) {
				return;
			}
			var job  = $( this ).data( 'job' );
			var runMode = $( this ).data( 'mode' ) || '';

			if ( 'restore' === job ) {
				if ( ! window.confirm( i18n.confirm_restore ) ) {
					return;
				}
			} else if ( 'cleanup' === job ) {
				if ( ! window.confirm( i18n.confirm_cleanup ) ) {
					return;
				}
			} else {
				if ( ! validate() ) {
					return;
				}
				if ( ! window.confirm( 'live' === runMode ? i18n.confirm_live : i18n.confirm_test ) ) {
					return;
				}
			}

			startJob( job, runMode );
		} );

		function startJob( job, runMode ) {
			lockButtons( true );
			$results.attr( 'hidden', 'hidden' ).empty();
			showProgress( 0, 0, i18n.preparing );

			var data = { action: 'qfibr_prepare', nonce: qfibr_data.nonce, job: job, mode: runMode };
			if ( 'replace' === job ) {
				data = $.extend( data, criteria() );
			}

			$.post( qfibr_data.ajax_url, data )
				.done( function ( res ) {
					if ( ! res || ! res.success || ! res.data ) {
						var err = serverError( res );
						return finishWithError( err.message, err.field );
					}
					if ( ! res.data.token || 0 === res.data.total ) {
						finishProgress( i18n.nothing );
						lockButtons( false );
						return;
					}
					runBatch( res.data.token );
				} )
				.fail( function () {
					finishWithError( i18n.failed );
				} );
		}

		function runBatch( token ) {
			$.post( qfibr_data.ajax_url, { action: 'qfibr_batch', nonce: qfibr_data.nonce, token: token } )
				.done( function ( res ) {
					if ( ! res || ! res.success || ! res.data ) {
						return finishWithError( serverError( res ).message );
					}
					var d = res.data;
					showProgress( d.done, d.total, i18n.processing );

					if ( d.finished ) {
						var summary = format( i18n.finished, d.processed, d.skipped, d.errors );
						finishProgress( summary );
						speak( summary );
						// Reload so the restorable count and the "Last run" log refresh.
						window.setTimeout( function () {
							window.location.reload();
						}, 1500 );
					} else {
						runBatch( token );
					}
				} )
				.fail( function () {
					finishWithError( i18n.failed );
				} );
		}

		// --- Broken image scan ------------------------------------------------------
		$( '#qfibr-orphan-scan' ).on( 'click', function () {
			if ( running ) {
				return;
			}
			var $out = $( '#qfibr-orphan-results' );
			$out.empty().append( $( '<p class="qfibr-loading"/>' ).text( i18n.scanning ) );
			$.post( qfibr_data.ajax_url, { action: 'qfibr_orphan_scan', nonce: qfibr_data.nonce } )
				.done( function ( res ) {
					if ( res && res.success && res.data ) {
						$out.html( res.data.html );
						var total = parseInt( $out.find( '[data-total]' ).data( 'total' ), 10 ) || 0;
						$( '#qfibr-orphan-clean' ).prop( 'hidden', 0 === total );
						speak( $out.find( '.qfibr-summary' ).text() );
					} else {
						$out.empty().append( $( '<p class="qfibr-error" role="alert"/>' ).text( serverError( res ).message ) );
					}
				} )
				.fail( function () {
					$out.empty().append( $( '<p class="qfibr-error" role="alert"/>' ).text( i18n.failed ) );
				} );
		} );

		// --- Progress UI ----------------------------------------------------------
		function showProgress( done, total, text ) {
			$progress.removeAttr( 'hidden' );
			var pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
			$fill.css( 'width', pct + '%' );
			$bar.attr( 'aria-valuenow', pct );
			$label.text( total > 0 ? ( text + ' ' + done + ' / ' + total ) : text );
		}

		function finishProgress( text ) {
			$fill.css( 'width', '100%' );
			$bar.attr( 'aria-valuenow', 100 );
			$label.text( text );
		}

		function finishWithError( msg, field ) {
			$progress.attr( 'hidden', 'hidden' );
			lockButtons( false );
			showError( msg, field );
		}

		syncMode();
	} );
} )( jQuery );
