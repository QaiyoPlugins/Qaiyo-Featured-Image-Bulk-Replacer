/* global jQuery, wp, qfibrList */
/**
 * Featured image column on the post lists: change or remove in place.
 *
 * Keyboard and screen reader: the controls are real buttons with a name that
 * includes the post title, the result is announced with wp.a11y.speak, and
 * focus returns to the same row's button after the cell is redrawn.
 */
( function ( $ ) {
	'use strict';

	var frame = null;
	var target = null;

	function speak( text ) {
		if ( window.wp && wp.a11y && wp.a11y.speak ) {
			wp.a11y.speak( text, 'polite' );
		}
	}

	function redraw( postId, html, focusSelector ) {
		var $cell = $( '.qfibr-col[data-post="' + postId + '"]' );
		var $new  = $( html );
		$cell.replaceWith( $new );
		var $focus = $new.find( focusSelector );
		( $focus.length ? $focus : $new.find( '.qfibr-col-set' ) ).trigger( 'focus' );
	}

	function send( action, postId, extra, done ) {
		$.post( qfibrList.ajaxUrl, $.extend( { action: action, nonce: qfibrList.nonce, post_id: postId }, extra || {} ) )
			.done( function ( res ) {
				if ( res && res.success && res.data && res.data.html ) {
					done( res.data.html );
				} else {
					var msg = ( res && res.data && res.data.message ) ? res.data.message : qfibrList.i18n.failed;
					speak( msg );
					window.alert( msg );
				}
			} )
			.fail( function () {
				speak( qfibrList.i18n.failed );
				window.alert( qfibrList.i18n.failed );
			} );
	}

	$( document ).on( 'click', '.qfibr-col-set', function ( e ) {
		e.preventDefault();
		if ( ! ( window.wp && wp.media ) ) {
			return;
		}
		target = $( this ).closest( '.qfibr-col' ).data( 'post' );

		if ( ! frame ) {
			frame = wp.media( {
				title:    qfibrList.i18n.title,
				button:   { text: qfibrList.i18n.button },
				library:  { type: 'image' },
				multiple: false
			} );
			frame.on( 'select', function () {
				var att    = frame.state().get( 'selection' ).first().toJSON();
				var postId = target;
				send( 'qfibr_list_set', postId, { attachment_id: att.id }, function ( html ) {
					redraw( postId, html, '.qfibr-col-set' );
					speak( qfibrList.i18n.set );
				} );
			} );
			frame.on( 'close', function () {
				$( '.qfibr-col[data-post="' + target + '"] .qfibr-col-set' ).trigger( 'focus' );
			} );
		}
		frame.open();
	} );

	$( document ).on( 'click', '.qfibr-col-remove', function ( e ) {
		e.preventDefault();
		var postId = $( this ).closest( '.qfibr-col' ).data( 'post' );
		if ( ! window.confirm( qfibrList.i18n.confirm ) ) {
			return;
		}
		send( 'qfibr_list_remove', postId, {}, function ( html ) {
			redraw( postId, html, '.qfibr-col-set' );
			speak( qfibrList.i18n.removed );
		} );
	} );
} )( jQuery );
