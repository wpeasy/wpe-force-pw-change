# Changelog

All notable changes to WP Easy Force Password Change will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.0.2-alpha] - 2025-10-26

### Added
- Tabbed layout for Settings page (General, Style, Email Template tabs)
- JavaScript-based tab switching with no page reloads
- CSS custom properties (variables) for easy color scheme customization
- Disable default WordPress new user email option
- Filter hooks to prevent default new user notifications when custom template is enabled

### Changed
- Settings page reorganized into three logical tabs
- CSS editor height increased to 70vh for better editing experience
- Default CSS now uses CSS custom properties for all color values
- General settings (toggles) moved to dedicated General tab
- Style editor isolated in Style tab
- Email template editor isolated in Email Template tab

### Technical
- Tab navigation with `.wpe-fpc-tab-button` and `.wpe-fpc-tab-content` classes
- Active tab management via `wpe-fpc-tab-active` class
- CodeMirror 6 editor height updated to 70vh
- Fallback textarea min-height updated to 70vh
- Color variables prefixed with `--_login-` for scoping

## [0.0.1-alpha] - 2025-10-25

### Added
- Initial plugin implementation
- Force password change workflow for flagged users
- Non-expiring password reset links (10 years)
- Login interception redirecting to password reset page
- Settings page with:
  - Enable/Disable toggle
  - CodeMirror 6 CSS editor with Dracula theme for password reset page styling
  - WYSIWYG email template editor with placeholder support
  - Click-to-copy placeholders for dynamic content
- Admin UI for managing password reset requirements:
  - Edit User page integration with Force/Revoke buttons
  - Generate & Copy reset link functionality
  - Send reset email option
- Users list table enhancements:
  - "PW Reset Required" column with status badges
  - Row actions (Force PW Reset / Revoke PW Reset)
  - Bulk actions for multiple users
- Auto-flag functionality:
  - New users created programmatically
  - Users when admin sets/changes password
- Email notifications with customizable HTML templates
- Placeholder system for email personalization:
  - User data: login, email, display name, first name, last name
  - Site data: name, URL, admin email
  - Reset data: URL, reason
- PSR-4 autoloading via Composer
- WordPress translation ready (text domain: wp-easy-force-password-change)

### Fixed
- Login intercept now generates fresh reset keys on each attempt (prevents "invalid link" errors)
- CSS quote escaping in sanitization (preserves quotes in selectors)
- Force PW Reset from users list table now works correctly via admin_action hooks
- Email template and CSS properly handle WordPress magic quotes with wp_unslash()
- Meta value consistency using integer (1) instead of boolean (true)

### Technical
- Password reset expiration extended to 10 years for flagged users
- Flag automatically cleared on successful password reset
- Form-based actions converted to link/button-based to prevent nested form issues
- Custom CSS output on login page (action=rp only)
- Minimal sanitization for email templates (allows HTML, strips scripts)
- Settings auto-save for CSS editor (1-second debounce)
- Manual save button for email template editor

### Security
- Nonce validation on all admin actions and AJAX endpoints
- Capability checks (manage_options, edit_user)
- wp_safe_redirect for all redirects
- Sanitization of all user inputs
- HTML allowed in email templates via wp_kses with controlled tags
