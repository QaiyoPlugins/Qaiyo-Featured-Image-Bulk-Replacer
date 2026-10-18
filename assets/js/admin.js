/* global jQuery, wp, qfibr_data */
( function ( $ ) {
	'use strict';

	$( function () {
		var i18n        = qfibr_data.i18n;
		var $results    = $( '#qfibr-preview-results' );
		var $progress   = $( '#qfibr-progress' );
		var $fill       = $( '#qfibr-progress-fill' );
		var $label      = $( '#qfibr-progress-label' );
		var $attachment = $( '#qfibr_attachment_id' );
		var $imageUrl   = $( '#qfibr_image_url' );
		var $mediaPrev  = $( '#qfibr-media-preview' );
		var mediaFrame;
		var running     = false;

		// --- Media Library picker ------------------------------------------
		$( '#qfibr-media-btn' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( ! ( window.wp && wp.media ) ) {
				return;
			}
			if ( mediaFrame ) {
				mediaFrame.open();
				return;
			}
			mediaFrame = wp.media( {
				title:    i18n.media_title,
				button:   { text: i18n.media_button },
				library:  { type: 'image' },
				multiple: false
			} );
			mediaFrame.on( 'select', function () {
				var att = mediaFrame.state().get( 'selection' ).first().toJSON();
				$attachment.val( att.id );
				$imageUrl.val( att.url );
				var thumb = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
				$mediaPrev.html( '<img src="" alt="" />' );
				$mediaPrev.find( 'img' ).attr( 'src', thumb );
			} );
			mediaFrame.open();
		} );

		// If the URL is edited by hand, drop the resolved attachment ID so the
		// server re-resolves from the typed URL.
		$imageUrl.on( 'input', function () {
			$attachment.val( '0' );
			$mediaPrev.empty();
		} );

		// --- Collect the form criteria -------------------------------------
		function criteria() {
			return {
				attachment_id: $attachment.val() || '0',
				image_url:     $.trim( $imageUrl.val() ),
				before_date:   $.trim( $( '#qfibr_before_date' ).val() ),
				after_date:    $.trim( $( '#qfibr_after_date' ).val() ),
				post_type:     $( '#qfibr_post_type' ).val(),
				scope:         $( '#qfibr_scope' ).val(),
				category:      $( '#qfibr_category' ).val() || '0',
				alt_text:      $( '#qfibr_alt_text' ).val()
			};
		}

		function hasImageAndDate() {
			var c = criteria();
			return ( ( c.attachment_id && '0' !== c.attachment_id ) || c.image_url ) && c.before_date;
		}

		function lockButtons( lock ) {
			running = lock;
			$( '.qfibr-run, #qfibr-preview-btn' ).prop( 'disabled', lock );
		}

		// --- AJAX preview ---------------------------------------------------
		$( '#qfibr-preview-btn' ).on( 'click', function () {
			if ( running ) {
				return;
			}
			if ( ! hasImageAndDate() ) {
				window.alert( i18n.need_image_date );
				return;
			}

			$results.removeAttr( 'hidden' ).html( '<p class="qfibr-loading">' + i18n.previewing + '</p>' );

			$.post( qfibr_data.ajax_url, $.extend( { action: 'qfibr_preview', nonce: qfibr_data.nonce }, criteria() ) )
				.done( function ( res ) {
					if ( res && res.success && res.data && res.data.html ) {
						$results.html( res.data.html );
					} else {
						$results.html( '<p class="qfibr-error">' + msgOf( res ) + '</p>' );
					}
				} )
				.fail( function () {
					$results.html( '<p class="qfibr-error">' + i18n.preview_error + '</p>' );
				} );
		} );

		// --- Run (replace test/live, or restore) ---------------------------
		$( '.qfibr-run' ).on( 'click', function () {
			if ( running ) {
				return;
			}
			var job  = $( this ).data( 'job' );
			var mode = $( this ).data( 'mode' ) || '';

			if ( 'restore' === job ) {
				if ( ! window.confirm( i18n.confirm_restore ) ) {
					return;
				}
			} else {
				if ( ! hasImageAndDate() ) {
					window.alert( i18n.need_image_date );
					return;
				}
				if ( ! window.confirm( 'live' === mode ? i18n.confirm_live : i18n.confirm_test ) ) {
					return;
				}
			}

			startJob( job, mode );
		} );

		function startJob( job, mode ) {
			lockButtons( true );
			$results.attr( 'hidden', 'hidden' ).empty();
			showProgress( 0, 0, i18n.preparing );

			var data = $.extend( { action: 'qfibr_prepare', nonce: qfibr_data.nonce, job: job, mode: mode }, criteria() );

			$.post( qfibr_data.ajax_url, data )
				.done( function ( res ) {
					if ( ! res || ! res.success || ! res.data ) {
						return finishWithError( msgOf( res ) );
					}
					if ( ! res.data.token || 0 === res.data.total ) {
						finishProgress( i18n.nothing );
						lockButtons( false );
						return;
					}
					runBatch( res.data.token, res.data.total );
				} )
				.fail( function () {
					finishWithError( i18n.failed );
				} );
		}

		function runBatch( token, total ) {
			$.post( qfibr_data.ajax_url, { action: 'qfibr_batch', nonce: qfibr_data.nonce, token: token } )
				.done( function ( res ) {
					if ( ! res || ! res.success || ! res.data ) {
						return finishWithError( msgOf( res ) );
					}
					var d = res.data;
					showProgress( d.done, d.total, i18n.processing );

					if ( d.finished ) {
						// Reload so the restorable count and the "Last run" log refresh.
						finishProgress( i18n.processing + ' ' + d.done + ' / ' + d.total );
						window.setTimeout( function () {
							window.location.reload();
						}, 600 );
					} else {
						runBatch( token, total );
					}
				} )
				.fail( function () {
					finishWithError( i18n.failed );
				} );
		}

		// --- Progress UI ----------------------------------------------------
		function showProgress( done, total, text ) {
			$progress.removeAttr( 'hidden' );
			var pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
			$fill.css( 'width', pct + '%' );
			$label.text( total > 0 ? ( text + ' ' + done + ' / ' + total ) : text );
		}

		function finishProgress( text ) {
			$fill.css( 'width', '100%' );
			$label.text( text );
		}

		function finishWithError( msg ) {
			$progress.attr( 'hidden', 'hidden' );
			$results.removeAttr( 'hidden' ).html( '<p class="qfibr-error">' + msg + '</p>' );
			lockButtons( false );
		}

		function msgOf( res ) {
			return ( res && res.data && res.data.message ) ? res.data.message : i18n.failed;
		}
	} );
} )( jQuery );
