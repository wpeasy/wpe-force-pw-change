=== WP Easy Force Password Change ===
Contributors: yourname
Tags: password, security, users, reset, authentication
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.0.1-alpha
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enforce password change workflow for users created programmatically or via WP Admin.

== Description ==

WP Easy Force Password Change allows administrators to enforce a password reset workflow for users. When a user is flagged for password change, they are intercepted at login and redirected to the WordPress password reset flow.

**Key Features:**

* Force password reset for individual users or bulk users
* Non-expiring reset links (until password is changed)
* Email notifications with reset links
* User list column showing reset status
* Row actions and bulk actions for easy management
* Automatic flagging of programmatically created users
* Compatible with multisite installations

**Perfect for:**

* Sites that create users programmatically
* Administrators who set initial passwords
* Security-conscious sites requiring password changes
* Organizations with compliance requirements

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wp-easy-force-password-change/`
2. Run `composer install` in the plugin directory to install dependencies
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to Users to start managing password reset requirements

== Frequently Asked Questions ==

= How do I force a user to reset their password? =

1. Go to Users > All Users
2. Click "Force PW Reset" on the user's row, or
3. Edit the user's profile and click "Force Password Reset"

= Can I force multiple users at once? =

Yes! Select multiple users on the Users page and choose "Force Password Reset" from the bulk actions dropdown.

= How long is the reset link valid? =

Reset links remain valid until the user successfully changes their password. This ensures users can reset their password at their convenience.

= Will users be notified? =

Yes, when you force a password reset, you can choose to send an email notification with the reset link.

= What happens when a user tries to log in? =

If a user is flagged for password reset, they are automatically redirected to the WordPress password reset page upon login attempt.

= Can I revoke a password reset requirement? =

Yes, you can revoke the requirement from the user's profile or from the Users list table.

= Does this work with multisite? =

Yes, the plugin is fully compatible with WordPress multisite installations.

== Screenshots ==

1. User profile section showing password reset controls
2. Users list table with password reset status column
3. Bulk actions for forcing password resets
4. Email notification with reset link

== Changelog ==

= 0.0.1-alpha =
* Initial alpha release
* Core authentication interception
* Non-expiring reset links
* Admin UI for user profiles
* Users list table integration
* Bulk actions support
* Email notifications
* Auto-flagging for new users

== Upgrade Notice ==

= 0.0.1-alpha =
Initial alpha release. Please test thoroughly before using in production.

== Developer Notes ==

**Hooks & Filters:**

* `wpe_fpc_email_subject` - Filter email subject
* `wpe_fpc_email_headers` - Filter email headers
* `wpe_fpc_email_template` - Filter email HTML template

**User Meta Keys:**

* `wpe_fpc_must_change_pw` - Boolean flag indicating reset required
* `wpe_fpc_last_issued` - Timestamp of last reset link generation
* `wpe_fpc_reason` - Optional reason for reset requirement

**Constants:**

* `WPE_FPC_VERSION` - Plugin version
* `WPE_FPC_PLUGIN_PATH` - Plugin directory path
* `WPE_FPC_PLUGIN_URL` - Plugin URL

== Privacy ==

This plugin stores the following user meta:
* Password reset requirement flag
* Timestamp of last reset link generation
* Optional reason for reset

This data is deleted when the user successfully resets their password.

== Support ==

For support, please visit the plugin repository or contact the developer.
