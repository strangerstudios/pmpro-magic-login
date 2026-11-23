=== Paid Memberships Pro - Magic Login ===
Contributors: strangerstudios
Tags: paid memberships pro, pmpro, magic link, passwordless login
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds passwordless login via secure email links to Paid Memberships Pro.

== Description ==

PMPro Magic Login extends Paid Memberships Pro to allow members to log in using a one-time secure link sent to their email address, eliminating the need for passwords.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/pmpro-magic-login` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Paid Memberships Pro must be installed and activated.

== Changelog ==

= 1.0.4 - 2026-11-23 =
* SECURITY: Login Link test emails no longer include a working login link for the admin sending the test. #6 (@dparker1005)
* SECURITY: Fixed an issue where an empty or malformed login token could log a visitor in as any user with a pending login link. #10 (@dparker1005, @kta1kri)
* BUG FIX: Fixed the "Email Me a Login Link" button doing nothing on login forms not built by PMPro when a PMPro Log In page is set. #7 (@dparker1005)
* BUG FIX: Login link requests that fail now show a message instead of silently reloading the login form. Requesting another link within 5 minutes shows the usual confirmation without sending a second email. #8 (@dparker1005)
* BUG FIX: Members who request a login link from wp-login.php are no longer sent to the WordPress dashboard after logging in. #9 (@dparker1005)

= 1.0.3 - 2026-11-19 =
* BUG FIX: Invalid, used, or expired login links now return a 4xx status instead of a 500 error. #4 (@sarah-savage)
* BUG FIX: Email link scanners such as Microsoft Safe Links no longer use up login links. Opening a link in a different browser or device now shows a "Log In" button to finish logging in. #5 (@andrewlimaza)

= 1.0.2 - 2026-09-11 =
* BUG FIX: The "Email Me a Login Link" button is now rendered inside login forms built with `wp_login_form()`, such as the PMPro login page and login widget. The button previously printed above the form and discarded content other plugins added to the `login_form_middle` filter. #3 (@dparker1005)

= 1.0.1 - 2026-07-21 =
* BUG FIX: Fixed the Magic Login button in the PMPro Login Widget by loading required assets when the widget is displayed. #2 (@andrewlimaza, @dparker1005)

= 1.0 =
* Initial release.
