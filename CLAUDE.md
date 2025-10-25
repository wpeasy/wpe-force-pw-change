# CLAUDE.md — WP Easy Force Password Change

## Plugin
- **Name:** WP Easy Force Password Change
- **Namespace:** `WP_Easy\ForcePW_Change`
- **Constants Prefix:** `WPE_FPC_`
- **Text Domain (derived):** `wp-easy-force-password-change`

## Purpose
Enforce a password change workflow for users who were created programmatically or via WP Admin (or whenever an admin explicitly flags a user). If a flagged user attempts to log in with the admin-set/programmatic password, they are intercepted and sent to the standard WordPress “Reset Password” screen (the `action=rp` flow). The reset link should not expire until the user has changed their password.

## Functionality

### Core Behaviours
1. **Force-Change Flag**
   - Store per-user meta: `wpe_fpc_must_change_pw` (boolean, default `false`).
   - When `true`, the user cannot complete authentication; instead they are redirected to the RP flow.
   - Clear the flag on successful password reset (`password_reset` action).

2. **Reset Link Handling (Non-Expiring Until Changed)**
   - On intercept, generate a fresh password reset key via `get_password_reset_key( $user )`.
   - Filter **`password_reset_expiration`** to return a very large TTL (e.g., `10 * YEAR_IN_SECONDS`) so the link effectively does not expire until changed.  
     - Note: We still rotate a new key at each intercepted attempt to minimize stale URL reuse.
   - Construct redirect URL:  
     `wp_login_url() . '?action=rp&key={KEY}&login={rawurlencode( $user->user_login )}'`
   - Optionally email the link when the flag is first set or when an admin requests it.

3. **Login Intercept**
   - Hook `authenticate` at priority > 20:
     - If `wpe_fpc_must_change_pw` is `true`:
       - **Do not** return a WP_User (prevents login).
       - Generate key, **redirect** to `action=rp` URL with a notice.
       - Optionally store `wpe_fpc_last_issued` timestamp and throttle re-issue (rate limiting).

4. **Clearing the Flag**
   - Hook `password_reset` (fires after the password is successfully changed).
   - Delete `wpe_fpc_must_change_pw` and related meta (`wpe_fpc_last_issued`, `wpe_fpc_reason`).

5. **Programmatic/Admin User Creation**
   - Hook `user_register` and `profile_update` (when admin sets/changes a user’s password in the admin).
   - For new users created programmatically or via profile screen where password is admin-defined, set `wpe_fpc_must_change_pw = true` and email/reset link.

### Admin (WP Admin) Functionality
- **Edit User Screen Button**
  - Adds a prominent **“Force Password Reset”** button.
  - Actions:
    - Sets `wpe_fpc_must_change_pw = true`
    - Optionally issues a new reset key and:
      - Shows **Copy Reset URL** (clipboard)
      - **Send Reset Email** (checkbox or secondary button)

- **Users List Table Buttons/Bulk Actions**
  - Per-row **“Force Reset”** action.
  - Bulk action: **“Force Password Reset”** for multiple selected users.
  - Optional column **“Must Change PW”** (Yes/No) and **“Last Issued”** timestamp.

- **Notices & Logs**
  - Admin notice confirming flags set / emails sent.
  - (Optional) Simple log in usermeta: `wpe_fpc_history` array (timestamp, admin_id, reason).

### Frontend / Auth Flow
- **Intercept Login**
  - If `wpe_fpc_must_change_pw` is true, block normal login and **redirect** to the WordPress Reset Password flow (`action=rp`) using a freshly generated key.
- **UX Messages**
  - Customize messages via `login_message` filter to display:
    - “Your account requires a password change before you can sign in.”
- **Localization**
  - All strings are translatable via the derived text domain.

### REST API (Optional)
- (Optional) `POST /wp-easy-fpc/v1/force` to flag users (admin-only, same-origin, nonce required).
- (Optional) `POST /wp-easy-fpc/v1/revoke` to clear flags (admin-only).

## Implementation Notes

### Key Hooks & Filters
- `authenticate` — intercept flagged users before login completes.
- `password_reset` — clear flag after successful reset.
- `user_register` — set flag for programmatically created users.
- `profile_update` — when admin changes/sets a password, set flag.
- `password_reset_expiration` — return large TTL to effectively “not expire”.
- `login_message` — inform user about required password change.
- `manage_users_columns`, `manage_users_custom_column` — list-table UI.
- `bulk_actions-users`, `handle_bulk_actions-users` — bulk flag handling.
- `show_user_profile`, `edit_user_profile` — add button on Edit User screen.
- `admin_post_*` or `wp_ajax_*` — handle admin button submissions securely.

### Pseudocode Sketches

**Intercept + Redirect**
```php
add_filter('authenticate', function($user, $username) {
    if ($user instanceof WP_User) {
        if (get_user_meta($user->ID, 'wpe_fpc_must_change_pw', true)) {
            $key = get_password_reset_key($user); // rotates key
            $url = wp_login_url() . '?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login);
            // Optionally throttle re-issue with a timestamp check
            wp_safe_redirect($url);
            exit;
        }
    }
    return $user;
}, 30, 2);
```

**Do-Not-Expire (Effectively)**
```php
add_filter('password_reset_expiration', function($expiration, $user_id) {
    return 10 * YEAR_IN_SECONDS; // ~10 years
}, 10, 2);
```

**Clear Flag After Reset**
```php
add_action('password_reset', function($user) {
    delete_user_meta($user->ID, 'wpe_fpc_must_change_pw');
    delete_user_meta($user->ID, 'wpe_fpc_last_issued');
    delete_user_meta($user->ID, 'wpe_fpc_reason');
});
```

**Auto-Flag on Admin/Programmatic Creation**
```php
add_action('user_register', function($user_id) {
    update_user_meta($user_id, 'wpe_fpc_must_change_pw', 1);
    // Optionally send email with key here
});
```

**Admin Button (Edit User)**
- Nonce-protected `admin_post_wpe_fpc_force` handler:
  - Sets meta, generates key, emails, redirects back with admin notice.
- Provide **Copy URL** UI using a readonly input + “Copy” button.

### Emails
- Template includes:
  - Greeting, why they’re receiving the reset link.
  - Reset button linking to the `action=rp` URL.
  - Security note: link remains valid until password is changed; if unexpected, contact admin.
- Use `wp_mail()` (headers for HTML email) or a mailer plugin if available.
- Respect site name/from headers; filterable via `wpe_fpc_email_headers` and `wpe_fpc_email_subject`.

## Code Style Guidelines

### General
1. All libraries are to be downloaded and served locally.

### PHP Conventions
1. **Namespace:** All classes use the defined namespace `WP_Easy\ForcePW_Change`.
2. **Loading:** Use PSR-4 autoloading with Composer.
3. **Class Structure:** Final classes with static methods for WordPress hooks.
4. **Security:** Always use `defined('ABSPATH') || exit;` at top of files.
5. **Sanitization:** Use proper escaping/sanitization (`sanitize_text_field`, `absint`, `esc_url_raw`, etc.).
6. **Nonces:** WordPress nonces for admin actions; custom nonce for REST routes.
7. **Constants:** Define paths/URLs:
   - `WPE_FPC_PLUGIN_PATH`
   - `WPE_FPC_PLUGIN_URL`
   - `WPE_FPC_VERSION`

### Method Patterns
- `init()`: Static method to register WordPress hooks.
- `render()`, `handle_*()`: Methods that output HTML or handle requests.
- Private helpers prefixed with `_`.
- Extensive parameter validation and type checking (PHPStan-friendly).

### Javascript Conventions
1. Download and serve all libraries locally.
2. Use AlpineJS where it makes sense; initialize via `alpine:init`/`init` events.
3. If using Svelte, use **Svelte 5**.
4. Use ES6 — **never** jQuery.
5. Admin UIs:
   - Tab switching with JS/CSS (no reloads).
   - Auto-save settings on change, no “Save” button.
   - Status indicator showing when settings are saved.

### CSS
1. Use `@layer` on all generated **frontend** CSS; never use `@layer` in Admin area.
2. Use nested CSS where it makes sense.
3. Prefer Container Queries over Media Queries.

## Security Practices
- Same-origin enforcement in REST API.
- Nonce validation on all endpoints and admin actions.
- Sanitization of all user inputs.
- Do not reveal whether a username/email exists on public endpoints (use generic messaging).
- Ensure redirects use `wp_safe_redirect`.

## WordPress Integration
- Follows WordPress coding standards.
- Uses WordPress APIs: Users, Settings API (if settings page added later), REST API (optional).
- Translation ready with the derived text domain.
- Compatible with **Multisite** (user meta works network-wide; test with network users).
- Plays nicely with custom login pages/plugins (use hooks/filters, not hardcoded URLs).

## Development Features
- Composer autoloading (PSR-4).
- Graceful fallbacks (if Alpine/Svelte not present, admin UI still works with plain PHP/HTML).
- Extensive error handling and validation.
- (Optional) Debug logging via `WPE_FPC_DEBUG` constant.

## Constants (Example)
```php
define('WPE_FPC_VERSION', '0.1.0');
define('WPE_FPC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WPE_FPC_PLUGIN_URL', plugin_dir_url(__FILE__));
```

## File Structure (Suggested)
```
/wp-easy-force-password-change
  /src
    /Admin
      Final class Admin_UI.php
      Final class Users_List.php
    /Auth
      Final class Interceptor.php
      Final class Reset_Link.php
    /Api (optional)
      Final class Routes.php
    Final class Plugin.php
  /assets
    /css admin.css
    /js  admin.js
  languages/wp-easy-force-password-change.pot
  wp-easy-force-password-change.php
  composer.json
  readme.txt
```

## Class Responsibilities (Suggested)
- `Plugin::init()` — bootstrap hooks, load textdomain.
- `Auth\Interceptor` — `authenticate` intercept, redirect logic, notices.
- `Auth\Reset_Link` — key generation, expiration filter, email composer.
- `Admin\Admin_UI` — Edit User button, action handlers, notices.
- `Admin\Users_List` — list column, row action, bulk actions.
- `Api\Routes` — optional REST endpoints with capability checks and nonces.

## Capabilities & Permissions
- Only users with `manage_options` or `edit_users` can force resets.
- REST endpoints check `current_user_can('edit_user', $user_id)` for target user.

## Testing Checklist
- Create user programmatically → flagged → attempt login → redirected to RP → reset → login OK.
- Admin creates user with password → flagged → RP flow works.
- Edit User “Force Reset” → link issued, copy/email, intercept works.
- Users list bulk flagging works.
- Multisite: super admin vs site admin behaviour tested.
- Compatibility with custom login URL plugins (still build RP link via `wp_login_url()`).

---
**Notes:**
- Using a very large `password_reset_expiration` retains compatibility with the native `action=rp` flow while effectively making links “non-expiring” until the password is changed.
- We rotate keys on each intercept to reduce stale link reuse and to respect any policy that might later reduce expiration.
