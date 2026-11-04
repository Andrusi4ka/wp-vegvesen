/**
 * "What's new" window after an update: opens by itself on the plugin's screens, from the
 * Dashboard notice otherwise. Closing it (or dismissing the notice) marks the version as seen.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.bvWhatsNew || {};

	$( function () {
		var $box    = $( '.bv-news' ).appendTo( document.body );
		var $notice = $( '.bv-news-notice' );
		var lastFocus = null;
		var sent = false;

		if ( ! $box.length ) {
			return;
		}

		function markSeen() {
			if ( sent ) {
				return;
			}
			sent = true;
			$.post( cfg.ajaxUrl, { action: cfg.action, _ajax_nonce: cfg.nonce } );
		}

		function open() {
			lastFocus = document.activeElement;
			$box.prop( 'hidden', false );
			$( document.body ).addClass( 'bv-news-open' );
			$box.find( '.bv-news__done' ).trigger( 'focus' );
		}

		function close() {
			$box.prop( 'hidden', true );
			$( document.body ).removeClass( 'bv-news-open' );
			$notice.remove();
			markSeen();
			if ( lastFocus && lastFocus.focus && document.body.contains( lastFocus ) ) {
				lastFocus.focus();
			}
		}

		$box.on( 'click', '.bv-news__done, .bv-news__close, .bv-news__backdrop', close );
		$( document ).on( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && ! $box.prop( 'hidden' ) ) {
				close();
			}
		} );
		$notice.on( 'click', '.bv-news-open', open );
		$notice.on( 'click', '.notice-dismiss', markSeen );

		if ( cfg.autoOpen ) {
			open();
		}
	} );
}( jQuery ) );
