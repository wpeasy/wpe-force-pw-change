<?php
/**
 * Login Interceptor Class
 *
 * @package WP_Easy\ForcePW_Change
 */

namespace WP_Easy\ForcePW_Change\Auth;

use WP_Easy\ForcePW_Change\Admin;

defined('ABSPATH') || exit;

/**
 * Handles login interception for users flagged for password change
 */
final class Interceptor {
    /**
     * Meta key for force password change flag
     */
    private const META_MUST_CHANGE = 'wpe_fpc_must_change_pw';

    /**
     * Meta key for last issued timestamp
     */
    private const META_LAST_ISSUED = 'wpe_fpc_last_issued';

    /**
     * Initialize hooks
     *
     * @return void
     */
    public static function init(): void {
        // Intercept authentication with priority 30 (after WordPress core)
        add_filter('authenticate', [__CLASS__, 'intercept_login'], 30, 2);

        // Add login message
        add_filter('login_message', [__CLASS__, 'add_login_message']);

        // Auto-flag on user registration
        add_action('user_register', [__CLASS__, 'auto_flag_new_user']);

        // Auto-flag when admin sets password
        add_action('profile_update', [__CLASS__, 'auto_flag_on_profile_update'], 10, 2);
    }

    /**
     * Intercept login attempt for flagged users
     *
     * @param \WP_User|\WP_Error|null $user     User object or error
     * @param string                   $username Username or email
     * @return \WP_User|\WP_Error|null
     */
    public static function intercept_login($user, string $username) {
        // Check if plugin is enabled
        if (class_exists('WP_Easy\\ForcePW_Change\\Admin\\Settings') && !Admin\Settings::is_enabled()) {
            return $user;
        }

        // Only process if we have a valid WP_User
        if (!($user instanceof \WP_User)) {
            return $user;
        }

        // Check if user must change password
        $must_change = get_user_meta($user->ID, self::META_MUST_CHANGE, true);
        if (!$must_change) {
            return $user;
        }

        // Always generate a fresh reset key and redirect
        // This ensures the key is always valid and follows the spec
        self::_redirect_to_reset($user);

        // This should never be reached due to exit in redirect, but return error as fallback
        return new \WP_Error(
            'password_reset_required',
            __('You must reset your password before logging in.', 'wp-easy-force-password-change')
        );
    }

    /**
     * Redirect user to password reset flow
     *
     * @param \WP_User $user User object
     * @return void (exits)
     */
    private static function _redirect_to_reset(\WP_User $user): void {
        // Generate fresh password reset key
        $key = get_password_reset_key($user);

        if (is_wp_error($key)) {
            // If key generation failed, return error
            wp_die(
                esc_html__('Unable to generate password reset link. Please contact an administrator.', 'wp-easy-force-password-change'),
                esc_html__('Password Reset Error', 'wp-easy-force-password-change'),
                ['response' => 500]
            );
        }

        // Update last issued timestamp
        update_user_meta($user->ID, self::META_LAST_ISSUED, time());

        // Construct reset password URL
        $reset_url = add_query_arg(
            [
                'action' => 'rp',
                'key'    => rawurlencode($key),
                'login'  => rawurlencode($user->user_login),
            ],
            wp_login_url()
        );

        // Redirect to reset password page
        wp_safe_redirect($reset_url);
        exit;
    }

    /**
     * Add login message for flagged users
     *
     * @param string $message Login message
     * @return string
     */
    public static function add_login_message(string $message): string {
        // Check if this is a password reset redirect
        if (isset($_GET['action']) && $_GET['action'] === 'rp' && isset($_GET['key']) && isset($_GET['login'])) {
            $custom_message = '<div class="message">';
            $custom_message .= '<p><strong>' . esc_html__('Password Reset Required', 'wp-easy-force-password-change') . '</strong></p>';
            $custom_message .= '<p>' . esc_html__('Your account requires a password change before you can sign in. Please enter your new password below.', 'wp-easy-force-password-change') . '</p>';
            $custom_message .= '</div>';

            return $custom_message . $message;
        }

        return $message;
    }

    /**
     * Auto-flag newly registered users
     *
     * @param int $user_id User ID
     * @return void
     */
    public static function auto_flag_new_user(int $user_id): void {
        // Flag user for password change
        update_user_meta($user_id, self::META_MUST_CHANGE, 1);

        // Optionally send email
        $user = get_userdata($user_id);
        if ($user) {
            Reset_Link::send_reset_email($user);
        }
    }

    /**
     * Auto-flag when admin updates user profile with new password
     *
     * @param int      $user_id       User ID
     * @param \WP_User $old_user_data Old user data
     * @return void
     */
    public static function auto_flag_on_profile_update(int $user_id, \WP_User $old_user_data): void {
        // Only flag if an admin is editing another user's profile
        if (!is_admin() || !current_user_can('edit_users')) {
            return;
        }

        // Check if password was changed
        $new_user_data = get_userdata($user_id);
        if (!$new_user_data) {
            return;
        }

        // If password hash changed, flag the user
        if ($new_user_data->user_pass !== $old_user_data->user_pass) {
            // Don't flag if admin is editing their own profile
            if (get_current_user_id() === $user_id) {
                return;
            }

            update_user_meta($user_id, self::META_MUST_CHANGE, 1);
        }
    }
}
