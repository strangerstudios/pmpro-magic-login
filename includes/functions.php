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
 * @since 1.0
 *
 * @return void
 */
function pmpro_ml_enqueue_scripts() {
	if ( function_exists( 'pmpro_is_login_page' ) && ! pmpro_is_login_page() ) {
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
add_action( 'login_form_middle', 'pmpro_ml_add_login_button' );

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
 * Handle form submission from the PMPro frontend login form.
 *
 * Injects a JS confirmation message in place of the form.
 *
 * @since 1.0
 *
 * @return void
 */
function pmpro_ml_handle_frontend_login_submission() {
	if ( isset( $_REQUEST['pmpro_login_form_used'] ) && pmpro_ml_process_form_submission() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified inside pmpro_ml_process_form_submission()
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
 * @return bool True on success (or duplicate request), false otherwise.
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
	if ( get_transient( 'pmpro_ml_last_sent_' . $user_id ) ) {
		return false;
	}
	set_transient( 'pmpro_ml_last_sent_' . $user_id, time(), 5 * MINUTE_IN_SECONDS );

	// Generate token and build login link.
	$login_token = pmpro_login_generate_login_token( $user_id );
	$redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
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
 * Validate the token from the URL, log the user in, and redirect.
 *
 * @since 1.0
 *
 * @return void
 */
function pmpro_ml_authenticate_via_token() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- magic login tokens are self-authenticating; a nonce is not applicable here.
	if ( ! isset( $_GET['pmpro_magic_login_token'] ) || is_user_logged_in() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token is validated below against stored user meta.
	$token = sanitize_text_field( wp_unslash( $_GET['pmpro_magic_login_token'] ) );

	$user_query = new WP_User_Query( array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- querying by indexed token meta is required for token-based auth.
		'meta_key'    => 'pmpro_magic_login_token', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => $token, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'number'      => 1,
		'count_total' => false,
	) );

	$users = $user_query->get_results();
	if ( empty( $users ) ) {
		wp_die( esc_html__( 'Invalid login link.', 'pmpro-magic-login' ) );
	}

	$user    = $users[0];
	$user_id = $user->ID;

	$expires = (int) get_user_meta( $user_id, 'pmpro_magic_login_expires', true );
	if ( time() > $expires ) {
		delete_user_meta( $user_id, 'pmpro_magic_login_token' );
		delete_user_meta( $user_id, 'pmpro_magic_login_expires' );
		delete_transient( 'pmpro_ml_last_sent_' . $user_id );
		wp_die( esc_html__( 'Login link has expired. Please request a new one.', 'pmpro-magic-login' ) );
	}

	// Authenticate the user.
	wp_set_auth_cookie( $user_id, true, is_ssl() );

	// Clean up the used token.
	delete_user_meta( $user_id, 'pmpro_magic_login_token' );
	delete_user_meta( $user_id, 'pmpro_magic_login_expires' );
	delete_transient( 'pmpro_ml_sent_' . $user_id );
	delete_transient( 'pmpro_ml_last_sent_' . $user_id );

	// Fire the standard wp_login action so 2FA/reCAPTCHA plugins can intercept.
	do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentionally firing the core wp_login action as part of the authentication flow.

	// Determine redirect destination.
	$redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect_to is a standard WP parameter; token verification above authenticates the request.

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
		delete_transient( 'pmpro_ml_sent_' . $uid );
		delete_transient( 'pmpro_ml_last_sent_' . $uid );
	}
}
add_action( 'pmpro_schedule_quarter_hourly', 'pmpro_ml_cleanup_expired_tokens' );
