<?php
/**
 * Plugin Name: Paid Memberships Pro - Magic Login
 * Plugin URI:  https://www.paidmembershipspro.com/add-ons/magic-login/
 * Description: Adds passwordless login via secure email links to Paid Memberships Pro.
 * Version:     1.0
 * Author:      Paid Memberships Pro
 * Author URI:  https://www.paidmembershipspro.com/
 * License:     GPL-3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: pmpro-magic-login
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PMPRO_MAGIC_LOGIN_VERSION', '1.0' );
define( 'PMPRO_MAGIC_LOGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PMPRO_MAGIC_LOGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check that Paid Memberships Pro is active, then load plugin files.
 *
 * @since 1.0
 *
 * @return void
 */
function pmpro_ml_init() {
	if ( ! defined( 'PMPRO_VERSION' ) ) {
		add_action( 'admin_notices', 'pmpro_ml_missing_pmpro_notice' );
		return;
	}

	require_once PMPRO_MAGIC_LOGIN_DIR . 'classes/class-pmpro-email-template-login-link.php';
	require_once PMPRO_MAGIC_LOGIN_DIR . 'includes/functions.php';
}
add_action( 'plugins_loaded', 'pmpro_ml_init' );


/**
 * Show an admin notice if Paid Memberships Pro is missing.
 *
 * @since 1.0
 *
 * @return void
 */
function pmpro_ml_missing_pmpro_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'PMPro Magic Login requires Paid Memberships Pro to be installed and activated.', 'pmpro-magic-login' ); ?></p>
	</div>
	<?php
}
