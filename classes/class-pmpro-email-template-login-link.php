<?php
/**
 * Email template for the one-time login link.
 *
 * Extends PMPro_Email_Template if available (requires PMPro 3.x+).
 * Falls back to a standalone implementation using wp_mail() for older versions.
 *
 * @since 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'PMPro_Email_Template' ) ) {

	/**
	 * Email template class for the one-time magic login link.
	 *
	 * @since 1.0
	 */
	class PMPro_Email_Template_Login_Link extends PMPro_Email_Template {

		/**
		 * The user to send the email to.
		 *
		 * @since 1.0
		 *
		 * @var WP_User
		 */
		protected $user;

		/**
		 * The one-time login link.
		 *
		 * @since 1.0
		 *
		 * @var string
		 */
		protected $login_link;

		/**
		 * Constructor.
		 *
		 * @since 1.0
		 *
		 * @param WP_User $user       The user object.
		 * @param string  $login_link The full login URL.
		 */
		public function __construct( WP_User $user, string $login_link ) {
			$this->user       = $user;
			$this->login_link = $login_link;
		}

		/**
		 * Get the template slug.
		 *
		 * @since 1.0
		 *
		 * @return string
		 */
		public static function get_template_slug() {
			return 'login_link';
		}

		/**
		 * Get the human-readable template name.
		 *
		 * @since 1.0
		 *
		 * @return string
		 */
		public static function get_template_name() {
			return esc_html__( 'Login Link', 'pmpro-magic-login' );
		}

		/**
		 * Get admin help text for this template.
		 *
		 * @since 1.0
		 *
		 * @return string
		 */
		public static function get_template_description() {
			return esc_html__( 'Sends a one-time login token for quick and secure sign-in. Please note the !!login_link!! variable is required in order for this email to work properly.', 'pmpro-magic-login' );
		}

		/**
		 * Get the default email subject.
		 *
		 * @since 1.0
		 *
		 * @return string
		 */
		public static function get_default_subject() {
			return __( 'Your Secure Login Link for !!sitename!!', 'pmpro-magic-login' );
		}

		/**
		 * Get the default email body.
		 *
		 * @since 1.0
		 *
		 * @return string
		 */
		public static function get_default_body() {
			return __( '<p>Click the secure link below to access your account:</p>

<p>!!login_link!!</p>

<p>This link can only be used once and will expire shortly.</p>', 'pmpro-magic-login' );
		}

		/**
		 * Get the template variables to replace in subject and body.
		 *
		 * @since 1.0
		 *
		 * @return array
		 */
		public function get_email_template_variables() {
			return array(
				'subject' => $this->get_default_subject(),
				'login_link' => $this->login_link,
				'sitename'   => get_bloginfo( 'name' ),
			);
		}

		/**
		 * Get template variable descriptions for the admin UI.
		 *
		 * @since 1.0
		 *
		 * @return array
		 */
		public static function get_email_template_variables_with_description() {
			return array(
				'!!login_link!!' => esc_html__( '(Required) The unique one-time login link.', 'pmpro-magic-login' ),
				'!!sitename!!'   => esc_html__( 'The name of the site.', 'pmpro-magic-login' ),
			);
		}

		/**
		 * Get the recipient email address.
		 *
		 * @since 1.0
		 *
		 * @return string
		 */
		public function get_recipient_email() {
			return $this->user->user_email;
		}

		/**
		 * Get the recipient display name.
		 *
		 * @since 1.0
		 *
		 * @return string
		 */
		public function get_recipient_name() {
			return $this->user->display_name;
		}

		/**
		 * Constructor args used when sending a test email from the admin.
		 *
		 * @since 1.0
		 *
		 * @return array
		 */
		public static function get_test_email_constructor_args() {
			global $current_user;
			if ( function_exists( 'pmpro_login_generate_login_token' ) ) {
				$login_token = pmpro_login_generate_login_token( $current_user->ID );
				$login_link  = add_query_arg( 'pmpro_magic_login_token', $login_token, home_url() );
			} else {
				$login_link = home_url();
			}
			return array( $current_user, $login_link );
		}
	} // End of class.

	/**
	 * Register the email template with PMPro.
	 *
	 * @since 1.0
	 *
	 * @param array $email_templates Registered email templates.
	 * @return array
	 */
	function pmpro_el_register_email_template( $email_templates ) {
		$email_templates['login_link'] = 'PMPro_Email_Template_Login_Link';
		return $email_templates;
	}
	add_filter( 'pmpro_email_templates', 'pmpro_el_register_email_template' );

} else {

	/**
	 * Fallback email class for PMPro < 3.4 using the legacy PMProEmail class.
	 *
	 * @since 1.0
	 */
	class PMPro_Email_Template_Login_Link {

		/**
		 * The user to send the email to.
		 *
		 * @var WP_User
		 */
		protected $user;

		/**
		 * The one-time login link.
		 *
		 * @var string
		 */
		protected $login_link;

		/**
		 * Constructor.
		 *
		 * @param WP_User $user       The user object.
		 * @param string  $login_link The full login URL.
		 */
		public function __construct( WP_User $user, string $login_link ) {
			$this->user       = $user;
			$this->login_link = $login_link;
		}

		/**
		 * Send the login link email using the legacy PMProEmail class.
		 *
		 * @return bool Whether the email was sent successfully.
		 */
		public function send() {
			$email           = new PMProEmail();
			$email->email    = $this->user->user_email;
			$email->subject  = __( 'Your Secure Login Link for !!sitename!!', 'pmpro-magic-login' );
			$email->template = 'login_link';
			$email->data     = array(
				'body'       => __( '<p>Click the secure link below to access your account:</p>

<p>!!login_link!!</p>

<p>This link can only be used once and will expire shortly.</p>', 'pmpro-magic-login' ),
				'login_link' => $this->login_link,
				'sitename'   => get_bloginfo( 'name' ),
				'name'       => $this->user->display_name,
			);
			return $email->sendEmail();
		}
	}

} // End of class check.