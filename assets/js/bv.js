( function () {
	'use strict';

	var config = window.bvConfig || {};

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target.closest ? e.target.closest( '[data-bv-search]' ) : null;
		if ( ! form ) {
			return;
		}
		var input = form.querySelector( 'input[name="regnr"]' );
		var value = input.value.replace( /[\s-]+/g, '' ).toUpperCase();
		if ( ! value ) {
			e.preventDefault();
			return;
		}
		input.value = value;
	} );

	document.addEventListener( 'click', function ( e ) {
		if ( e.target.closest && e.target.closest( '[data-bv-print]' ) ) {
			window.print();
		}
	} );

	/* ---------- Scroll to the result after a search ---------- */

	function visible( el ) {
		if ( ! el || ! el.getClientRects().length ) {
			return false;
		}
		var s = window.getComputedStyle( el );
		return 'hidden' !== s.visibility && '0' !== s.opacity;
	}

	// Where to scroll: an element marked by the user, otherwise the plugin's own result blocks.
	function findTarget() {
		var selectors = [
			'.bv-scroll-target',
			'.bv-if-found, .bv-if-notfound, .bv-if-searched',
			'.bv-card, .bv-notice',
			'.bv-status, .bv-plate, .bv-logo, .bv-tiles, .bv-panel, .bv-image'
		];
		for ( var i = 0; i < selectors.length; i++ ) {
			var list = document.querySelectorAll( selectors[ i ] );
			for ( var j = 0; j < list.length; j++ ) {
				if ( visible( list[ j ] ) ) {
					return list[ j ];
				}
			}
		}
		return null;
	}

	// Height of sticky/fixed bars at the top (admin bar, sticky theme header), so they do not cover the result.
	function topOffset() {
		var manual = parseInt( config.offset, 10 );
		if ( ! isNaN( manual ) ) {
			return manual;
		}
		var max = 0;
		var nodes = document.querySelectorAll( 'body *' );
		for ( var i = 0; i < nodes.length && i < 4000; i++ ) {
			var el = nodes[ i ];
			var pos = window.getComputedStyle( el ).position;
			if ( 'fixed' !== pos && 'sticky' !== pos ) {
				continue;
			}
			var r = el.getBoundingClientRect();
			if ( r.top <= 1 && r.bottom > 0 && r.height < window.innerHeight / 3 && r.width > window.innerWidth / 2 ) {
				max = Math.max( max, r.bottom );
			}
		}
		return Math.round( max ) + 16;
	}

	function scrollToResult() {
		if ( ! config.scroll || window.location.hash ) {
			return;
		}
		var target = findTarget();
		if ( ! target ) {
			return;
		}
		var y = Math.max( 0, target.getBoundingClientRect().top + window.pageYOffset - topOffset() );
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var start = window.pageYOffset;
		window.scrollTo( { top: y, behavior: reduce ? 'auto' : 'smooth' } );

		// Safety net: if smooth scrolling did not move the page (interrupted or unsupported), jump directly.
		setTimeout( function () {
			if ( Math.abs( window.pageYOffset - start ) < 2 && Math.abs( y - start ) > 2 ) {
				window.scrollTo( 0, y );
			}
		}, 1000 );
	}

	if ( config.scroll ) {
		// Wait for the layout (full-height rows, images) to settle.
		if ( 'complete' === document.readyState ) {
			setTimeout( scrollToResult, 150 );
		} else {
			window.addEventListener( 'load', function () {
				setTimeout( scrollToResult, 150 );
			} );
		}
	}
} )();
