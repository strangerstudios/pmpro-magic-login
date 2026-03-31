jQuery( document ).ready( function( $ ) {

	// Move the magic login div to after the password submit button.
	if ( $( '#pmpro-magic-login' ).length ) {
		$( '#pmpro-magic-login' ).insertAfter( $( '#wp-submit' ).parent() );
	}

	$( '#pmpro-magic-login-button' ).on( 'click', function( e ) {
		e.preventDefault();

		// Username/email is required; password is not.
		var user = document.getElementById( 'user_login' );
		if ( user ) {
			user.required = true;
		}

		var pass = document.getElementById( 'user_pass' );
		if ( pass ) {
			pass.required = false;
		}

		var frm = document.getElementById( 'loginform' );
		if ( ! frm ) {
			return;
		}

		// Point the form to the magic login action handler.
		frm.action = pmpro_magic_login_js.login_url;

		if ( typeof frm.requestSubmit === 'function' ) {
			frm.requestSubmit();
			return;
		}

		// Fallback for older browsers.
		frm.submit();
	} );

} );
