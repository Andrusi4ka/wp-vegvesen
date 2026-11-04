( function () {
	'use strict';

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
} )();
