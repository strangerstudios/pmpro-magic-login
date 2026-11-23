<?php
/**
 * Core passwordless magic login functionality.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue styles and scripts on login pages.
 *
 * Assets are enqueued early on recognized login pages and on demand for forms
 * rendered elsewhere, such as PMPro login widgets.
 *
 * @since 1.0
 *
 * @param bool $force Whether to enqueue assets outside a recognized login page.
 * @return void
 */
function pmpro_ml_enqueue_scripts( $force = false ) {
	if ( ! $force && function_exists( 'pmpro_is_login_page' ) && ! pmpro_is_login_page() ) {
		return;
	}

	if ( wp_script_is( 'pmpro-magic-login', 'enqueued' ) ) {
		return;
	}

	wp_enqueue_style(
		'pmpro-magic-login',
		PMPRO_MAGIC_LOGIN_URL . 'css/pmpro-magic-login.css',
		array(),
		PMPRO_MAGIC_LOGIN_VERSION
	);

	wp_enqueue_script(
		'pmpro-magic-login',
		PMPRO_MAGIC_LOGIN_URL . 'js/pmpro-magic-login.js',
		array( 'jquery' ),
		PMPRO_MAGIC_LOGIN_VERSION,
		true
	);

	$login_url = add_query_arg( array( 'action' => 'pmpro_magic_login' ), wp_login_url() );

	wp_localize_script( 'pmpro-magic-login', 'pmpro_magic_login_js', array( 'login_url' => $login_url ) );
}
add_action( 'login_enqueue_scripts', 'pmpro_ml_enqueue_scripts' );
add_action( 'wp_enqueue_scripts', 'pmpro_ml_enqueue_scripts' );

/**
 * Inject the "Email Me a Login Link" button into the login form.
 *
 * @since 1.0
 *
 * @return void
 */
function pmpro_ml_add_login_button() {
	pmpro_ml_enqueue_scripts( true );

	$button_class = 'pmpro_btn pmpro_btn-primary';
	if ( current_filter() === 'login_form' ) {
		$button_class = 'button button-primary button-hero';
	}

	$element_class = function_exists( 'pmpro_get_element_class' ) ? pmpro_get_element_class( $button_class ) : $button_class;
	?>
	<div id="pmpro-magic-login">
		<span class="pmpro-login-or-separator"></span>
		<button type="button" value="#" class="<?php echo esc_attr( $element_class ); ?>" id="pmpro-magic-login-button">
			<?php esc_html_e( 'Email Me a Login Link', 'pmpro-magic-login' ); ?>
		</button>
		<input type="hidden" name="pmpro_magic_login_nonce" id="pmpro_magic_login_nonce"
			value="<?php echo esc_attr( wp_create_nonce( 'pmpro_magic_login' ) ); ?>" />
	</div>
	<?php
}
add_action( 'login_form', 'pmpro_ml_add_login_button' );

/**
 * Inject the "Email Me a Login Link" button into forms built with wp_login_form().
 *
 * login_form_middle is a filter, so the button HTML must be returned rather
 * than echoed. Echoing here prints the button outside of the <form> element
 * and discards any content that other plugins added to the filter.
 *
 * @since 1.0.2
 *
 * @param string $content Content to display inside the login form. Default empty.
 * @return string $content Content to display inside the login form.
 */
function pmpro_ml_login_form_middle( $content ) {
	ob_start();
	pmpro_ml_add_login_button();
	return $content . ob_get_clean();
}
add_filter( 'login_form_middle', 'pmpro_ml_login_form_middle' );

/**
 * Handle the "pmpro_magic_login" action on wp-login.php.
 *
 * Validates the form, sends the email, and shows a confirmation message.
 * 
 * This uses the dynamic login_form_{$action} hook to show the message.
 *
 * @since 1.0
 *
 * @return void
 */
function pmpro_ml_handle_wp_login_submission() {
	if ( pmpro_ml_process_form_submission() === false ) {
		// Show why the request failed instead of wp-login.php's "password field is empty" error.
		// Other errors, such as a CAPTCHA or lockout notice from another plugin, are kept.
		add_filter( 'wp_login_errors', function( $errors ) {
			$errors->remove( 'empty_username' );
			$errors->remove( 'empty_password' );
			$errors->add( 'pmpro_magic_login_failed', '<strong>' . esc_html__( 'Error:', 'pmpro-magic-login' ) . '</strong> ' . esc_html__( 'Your login link request could not be processed. Please try again.', 'pmpro-magic-login' ) );
			return $errors;
		} );
		return;
	}

	add_filter( 'login_message', function() {
		return sprintf(
			'<p class="message">%s <a href="%s">%s</a></p>',
			esc_html__( "If an account exists for this email address, you'll receive a login link shortly. This link will expire in 15 minutes.", 'pmpro-magic-login' ),
			esc_url( wp_login_url() ),
			esc_html__( 'Click here to go back to login.', 'pmpro-magic-login' )
		);
	} );

	add_action( 'login_head', function() {
		?>
		<style>
			#loginform,
			#nav,
			#backtoblog,
			#login_error {
				display: none !important;
			}
		</style>
		<?php
	} );
}
add_action( 'login_form_pmpro_magic_login', 'pmpro_ml_handle_wp_login_submission' );

/**
 * Process a login link request from a frontend login form.
 *
 * The button posts to wp_login_url(), which PMPro points at its own login page, so
 * the request arrives here rather than at wp-login.php. This covers PMPro's login
 * forms and any other form built with wp_login_form(). It runs on template_redirect,
 * before any output, so pmpro_ml_set_browser_cookie() can still send its cookie.
 * The result is stored for pmpro_ml_handle_frontend_login_submission() to show.
 *
 * @since 1.0.3
 *
 * @return void
 */
function pmpro_ml_process_frontend_login_submission() {
	global $pmpro_ml_frontend_link_sent;

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonce verified inside pmpro_ml_process_form_submission().
	if ( ! isset( $_REQUEST['action'] ) || 'pmpro_magic_login' !== $_REQUEST['action'] ) {
		return;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$pmpro_ml_frontend_link_sent = pmpro_ml_process_form_submission();
}
add_action( 'template_redirect', 'pmpro_ml_process_frontend_login_submission' );

/**
 * Show the confirmation message after a login link request from a frontend login form.
 *
 * Injects a JS confirmation message in place of the form. The request itself is
 * processed earlier by pmpro_ml_process_frontend_login_submission().
 *
 * @since 1.0
 * @since 1.0.3 Only prints the message. The request is processed on template_redirect.
 *
 * @return void
 */
function pmpro_ml_handle_frontend_login_submission() {
	global $pmpro_ml_frontend_link_sent;

	if ( false === $pmpro_ml_frontend_link_sent ) {
		?>
		<script>
		jQuery(document).ready(function($) {
			$('.pmpro_card_content').first().prepend(
				'<div role="alert" class="pmpro_message pmpro_error" id="pmpro_magic_login_error">' +
				'<?php echo esc_js( __( 'Your login link request could not be processed. Please try again.', 'pmpro-magic-login' ) ); ?>' +
				'</div>'
			);
		});
		</script>
		<?php
	} elseif ( ! empty( $pmpro_ml_frontend_link_sent ) ) {
		$login_url = function_exists( 'pmpro_login_url' ) ? pmpro_login_url() : wp_login_url();
		?>
		<script>
		jQuery(document).ready(function($) {
			$('#loginform').remove();
			$('#pmpro-magic-login').remove();
			$('.pmpro_card_actions').remove();
			$('.pmpro_card_content').prepend(
				'<div class="pmpro_message" id="pmpro_magic_login_confirmation">' +
				'<?php echo esc_js( __( "If an account exists for this email address, you'll receive a login link shortly. This link will expire in 15 minutes.", 'pmpro-magic-login' ) ); ?> ' +
				'<a href="<?php echo esc_url( $login_url ); ?>">' +
				'<?php echo esc_js( __( 'Click here to go back to login.', 'pmpro-magic-login' ) ); ?>' +
				'</a></div>'
			);
		});
		</script>
		<?php
	}
}
add_action( 'wp_footer', 'pmpro_ml_handle_frontend_login_submission' );

/**
 * Validate the nonce, look up the user, generate a token, and send the email.
 *
 * @since 1.0
 *
 * @return bool True on success (or duplicate or rate-limited request), false if the nonce is missing or invalid.
 */
function pmpro_ml_process_form_submission() {
	if ( ! isset( $_REQUEST['pmpro_magic_login_nonce'] ) ) {
		return false;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['pmpro_magic_login_nonce'] ) ), 'pmpro_magic_login' ) ) {
		return false;
	}

	$login_input = isset( $_REQUEST['log'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['log'] ) ) : '';

	if ( is_email( $login_input ) ) {
		$user = get_user_by( 'email', sanitize_email( $login_input ) );
	} else {
		$user = get_user_by( 'login', $login_input );
	}

	$user_id = $user ? $user->ID : 0;
	if ( $user_id <= 0 ) {
		// Return true to avoid leaking whether the account exists.
		return true;
	}

	$nonce_value = sanitize_text_field( wp_unslash( $_REQUEST['pmpro_magic_login_nonce'] ) );

	// Prevent duplicate sends within the same 5-minute window.
	if ( get_transient( 'pmpro_ml_sent_' . $user_id ) === $nonce_value ) {
		return true;
	}

	// Rate limit: one email per user per 5 minutes.
	// Return true so the member sees the usual message and it isn't revealed whether the account exists.
	if ( get_transient( 'pmpro_ml_last_sent_' . $user_id ) ) {
		return true;
	}
	set_transient( 'pmpro_ml_last_sent_' . $user_id, time(), 5 * MINUTE_IN_SECONDS );

	// Generate token and build login link.
	$login_token = pmpro_login_generate_login_token( $user_id );

	// Remember this browser so the link logs in without a click when opened here.
	pmpro_ml_set_browser_cookie( $user_id );
	$redirect_to = pmpro_ml_get_redirect_to();
	$login_link  = add_query_arg( 'pmpro_magic_login_token', $login_token, home_url() );
	if ( ! empty( $redirect_to ) ) {
		$login_link = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $login_link );
	}

	// Send email. This automatically supports older PMPro versions < 3.4.
	$email = new PMPro_Email_Template_Login_Link( $user, $login_link );
	$email->send();

	set_transient( 'pmpro_ml_sent_' . $user_id, $nonce_value, 5 * MINUTE_IN_SECONDS );

	return true;
}

/**
 * Generate a 30-character HMAC-SHA256 login token and store it in user meta.
 *
 * @since 1.0
 *
 * @param int $user_id WordPress user ID.
 * @return string The generated token, or empty string on failure.
 */
function pmpro_login_generate_login_token( $user_id ) {
	if ( $user_id <= 0 ) {
		return '';
	}

	$code          = wp_generate_password( 20, false );
	$login_token   = substr( hash_hmac( 'sha256', $code, (string) $user_id ), 0, 30 );
	$login_expires = time() + ( 15 * MINUTE_IN_SECONDS );

	update_user_meta( $user_id, 'pmpro_magic_login_token', $login_token );
	update_user_meta( $user_id, 'pmpro_magic_login_expires', $login_expires );

	return $login_token;
}

/**
 * Validate the login token, then either show the confirm page or log the user in.
 *
 * When the member opens the emailed link in the same browser they requested it from,
 * that browser carries the cookie set by pmpro_ml_set_browser_cookie() and the member
 * is logged in right away.
 *
 * Any other request that opens the link (a different browser or device, or an email
 * link scanner such as Microsoft Safe Links, which fetches every link in an email
 * before the member sees it) only gets a "Log In" button, and the token stays unused.
 * The token is used only when that button is submitted (a POST request). Scanners
 * don't carry the cookie or submit forms, so they can no longer use up the token.
 *
 * @since 1.0
 * @since 1.0.3 Opening the link without the requesting browser's cookie requires a button click (POST) to log in.
 *
 * @return void
 */
function pmpro_ml_authenticate_via_token() {
	if ( is_user_logged_in() ) {
		return;
	}

	$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

	// phpcs:disable WordPress.Security.NonceVerification -- magic login tokens are self-authenticating; a nonce is not applicable here.
	if ( 'POST' === $request_method && isset( $_POST['pmpro_magic_login_token'] ) ) {
		$is_confirmed = true;
		$token        = sanitize_text_field( wp_unslash( $_POST['pmpro_magic_login_token'] ) );
	} elseif ( isset( $_GET['pmpro_magic_login_token'] ) ) {
		$is_confirmed = false; // Set to true below when this is the browser that requested the link.
		$token        = sanitize_text_field( wp_unslash( $_GET['pmpro_magic_login_token'] ) );
	} else {
		return;
	}
	// phpcs:enable WordPress.Security.NonceVerification

	$user_query = new WP_User_Query( array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- querying by indexed token meta is required for token-based auth.
		'meta_key'    => 'pmpro_magic_login_token', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => $token, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'number'      => 1,
		'count_total' => false,
	) );

	$users = $user_query->get_results();
	if ( empty( $users ) ) {
		wp_die( esc_html__( 'Invalid login link.', 'pmpro-magic-login' ), '', array( 'response' => 403 ) );
	}

	$user    = $users[0];
	$user_id = $user->ID;

	$expires = (int) get_user_meta( $user_id, 'pmpro_magic_login_expires', true );
	if ( time() > $expires ) {
		delete_user_meta( $user_id, 'pmpro_magic_login_token' );
		delete_user_meta( $user_id, 'pmpro_magic_login_expires' );
		delete_user_meta( $user_id, 'pmpro_magic_login_browser' );
		delete_transient( 'pmpro_ml_last_sent_' . $user_id );
		wp_die( esc_html__( 'Login link has expired. Please request a new one.', 'pmpro-magic-login' ), '', array( 'response' => 410 ) );
	}

	// The browser that requested the link logs in on a plain GET, with no click.
	if ( ! $is_confirmed && 'GET' === $request_method && pmpro_ml_browser_cookie_matches( $user_id ) ) {
		$is_confirmed = true;
	}

	// Any other request that opens the link only shows the confirm button. The token is not used yet.
	if ( ! $is_confirmed ) {
		pmpro_ml_show_confirm_page( $token, pmpro_ml_get_redirect_to() );
	}

	// Authenticate the user.
	wp_set_auth_cookie( $user_id, true, is_ssl() );

	// Clean up the used token and the browser cookie.
	delete_user_meta( $user_id, 'pmpro_magic_login_token' );
	delete_user_meta( $user_id, 'pmpro_magic_login_expires' );
	delete_user_meta( $user_id, 'pmpro_magic_login_browser' );
	pmpro_ml_clear_browser_cookie();
	delete_transient( 'pmpro_ml_sent_' . $user_id );
	delete_transient( 'pmpro_ml_last_sent_' . $user_id );

	// Fire the standard wp_login action so 2FA/reCAPTCHA plugins can intercept.
	do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentionally firing the core wp_login action as part of the authentication flow.

	// Determine redirect destination.
	$redirect_to = pmpro_ml_get_redirect_to();

	if ( function_exists( 'pmpro_login_redirect' ) ) {
		$redirect_url = pmpro_login_redirect( $redirect_to, '', $user );
	} elseif ( ! empty( $redirect_to ) ) {
		$redirect_url = $redirect_to;
	} else {
		$redirect_url = admin_url();
	}

	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'init', 'pmpro_ml_authenticate_via_token' );

/**
 * Show the "Log In" confirm page for a valid login link, then exit.
 *
 * Uses wp_die() with a 200 status so the page works with any theme, the same way
 * WordPress shows its own "Are you sure?" confirm pages. wp_die() also sends
 * no-cache headers, so page caches don't store the page.
 *
 * @since 1.0.3
 *
 * @param string $token       The login token from the link.
 * @param string $redirect_to Where to send the member after logging in, if set.
 * @return void
 */
function pmpro_ml_show_confirm_page( $token, $redirect_to = '' ) {
	$title = __( 'Log In', 'pmpro-magic-login' );

	$message  = '<h1>' . esc_html( $title ) . '</h1>';
	$message .= '<p>' . esc_html__( 'Click the button below to finish logging in.', 'pmpro-magic-login' ) . '</p>';
	$message .= '<form method="post" action="' . esc_url( home_url( '/' ) ) . '">';
	$message .= '<input type="hidden" name="pmpro_magic_login_token" value="' . esc_attr( $token ) . '" />';
	if ( ! empty( $redirect_to ) ) {
		$message .= '<input type="hidden" name="redirect_to" value="' . esc_attr( $redirect_to ) . '" />';
	}
	$message .= '<p><button type="submit" class="button button-large">' . esc_html( $title ) . '</button></p>';
	$message .= '</form>';

	wp_die( $message, esc_html( $title ), array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part of $message is escaped above.
}

/**
 * Get the redirect_to value from the current request.
 *
 * Used when building the login link, on the confirm page, and after logging in,
 * so all three read it the same way. The confirm page sends it back in a hidden
 * field, so it is read from $_REQUEST to cover both GET and POST.
 *
 * @since 1.0.3
 *
 * @return string The sanitized redirect URL, or an empty string if not set.
 */
function pmpro_ml_get_redirect_to() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- redirect_to is a standard WP parameter; callers verify the nonce or token.
	$redirect_to = ( ! empty( $_REQUEST['redirect_to'] ) && is_string( $_REQUEST['redirect_to'] ) ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	return $redirect_to;
}

/**
 * Remember the browser that requested a login link.
 *
 * Sets a random cookie in the requesting browser and stores a hash of it on the
 * user, so pmpro_ml_authenticate_via_token() can log the member in without a click
 * when they open the link in that same browser. The cookie expires with the link.
 *
 * Does nothing if output has already started, since the cookie can't be sent then.
 * The link still works; it just shows the confirm button.
 *
 * @since 1.0.3
 *
 * @param int $user_id WordPress user ID the login link was generated for.
 * @return void
 */
function pmpro_ml_set_browser_cookie( $user_id ) {
	if ( headers_sent() ) {
		delete_user_meta( $user_id, 'pmpro_magic_login_browser' );
		return;
	}

	$key     = bin2hex( random_bytes( 32 ) );
	$expires = (int) get_user_meta( $user_id, 'pmpro_magic_login_expires', true );

	update_user_meta( $user_id, 'pmpro_magic_login_browser', hash( 'sha256', $key ) );

	setcookie(
		PMPRO_MAGIC_LOGIN_BROWSER_COOKIE,
		$key,
		array(
			'expires'  => $expires,
			'path'     => COOKIEPATH,
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax', // Sent when the member clicks the link in their email.
		)
	);
}

/**
 * Check whether the current request comes from the browser that requested the user's login link.
 *
 * @since 1.0.3
 *
 * @param int $user_id WordPress user ID the login link belongs to.
 * @return bool
 */
function pmpro_ml_browser_cookie_matches( $user_id ) {
	if ( empty( $_COOKIE[ PMPRO_MAGIC_LOGIN_BROWSER_COOKIE ] ) ) {
		return false;
	}

	$stored_hash = (string) get_user_meta( $user_id, 'pmpro_magic_login_browser', true );
	if ( '' === $stored_hash ) {
		return false;
	}

	$key = sanitize_text_field( wp_unslash( $_COOKIE[ PMPRO_MAGIC_LOGIN_BROWSER_COOKIE ] ) );

	return hash_equals( $stored_hash, hash( 'sha256', $key ) );
}

/**
 * Remove the browser cookie once the login link has been used.
 *
 * @since 1.0.3
 *
 * @return void
 */
function pmpro_ml_clear_browser_cookie() {
	if ( headers_sent() || ! isset( $_COOKIE[ PMPRO_MAGIC_LOGIN_BROWSER_COOKIE ] ) ) {
		return;
	}

	setcookie(
		PMPRO_MAGIC_LOGIN_BROWSER_COOKIE,
		'',
		array(
			'expires'  => time() - YEAR_IN_SECONDS,
			'path'     => COOKIEPATH,
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
}

/**
 * Delete expired login tokens if there are any.
 *
 * Hooked to PMPro's quarter-hourly cron if available, otherwise uses the WP-Cron event registered on activation.
 *
 * @since 1.0
 *
 * @return void
 */
function pmpro_ml_cleanup_expired_tokens() {
	$user_query = new WP_User_Query( array(
		'meta_key'     => 'pmpro_magic_login_expires',
		'meta_value'   => time(),
		'meta_compare' => '<',
		'number'       => 50,
		'count_total'  => false,
	) );

	foreach ( $user_query->get_results() as $user ) {
		$uid = $user->ID;
		delete_user_meta( $uid, 'pmpro_magic_login_token' );
		delete_user_meta( $uid, 'pmpro_magic_login_expires' );
		delete_user_meta( $uid, 'pmpro_magic_login_browser' );
		delete_transient( 'pmpro_ml_sent_' . $uid );
		delete_transient( 'pmpro_ml_last_sent_' . $uid );
	}
}
add_action( 'pmpro_schedule_quarter_hourly', 'pmpro_ml_cleanup_expired_tokens' );
