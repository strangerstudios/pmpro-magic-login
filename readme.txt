=== Paid Memberships Pro - Magic Login ===
Contributors: strangerstudios
Tags: paid memberships pro, pmpro, magic link, passwordless login
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
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

= 1.0.2 - 2026-09-11 =
* BUG FIX: The "Email Me a Login Link" button is now rendered inside login forms built with `wp_login_form()`, such as the PMPro login page and login widget. The button previously printed above the form and discarded content other plugins added to the `login_form_middle` filter. #3 (@dparker1005)

= 1.0.1 - 2026-07-21 =
* BUG FIX: Fixed the Magic Login button in the PMPro Login Widget by loading required assets when the widget is displayed. #2 (@andrewlimaza, @dparker1005)

= 1.0 =
* Initial release.
