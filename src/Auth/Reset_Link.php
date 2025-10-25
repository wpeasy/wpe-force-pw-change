<?php
/**
 * Reset Link Handler Class
 *
 * @package WP_Easy\ForcePW_Change
 */

namespace WP_Easy\ForcePW_Change\Auth;

use WP_Easy\ForcePW_Change\Admin;

defined('ABSPATH') || exit;

/**
 * Handles password reset key generation, expiration, and emails
 */
final class Reset_Link {
    /**
     * Meta key for force password change flag
     */
    private const META_MUST_CHANGE = 'wpe_fpc_must_change_pw';

    /**
     * Meta key for last issued timestamp
     */
    private const META_LAST_ISSUED = 'wpe_fpc_last_issued';

    /**
     * Meta key for reason
     */
    private const META_REASON = 'wpe_fpc_reason';

    /**
     * Initialize hooks
     *
     * @return void
     */
    public static function init(): void {
        // Extend password reset expiration to effectively never expire
        add_filter('password_reset_expiration', [__CLASS__, 'extend_expiration'], 10, 2);

        // Clear flag after successful password reset
        add_action('password_reset', [__CLASS__, 'clear_flag_on_reset']);

        // Also clear on after_password_reset hook
        add_action('after_password_reset', [__CLASS__, 'clear_flag_on_reset']);

        // Output custom CSS on login page
        add_action('login_enqueue_scripts', [__CLASS__, 'output_custom_css']);
    }

    /**
     * Extend password reset expiration to 10 years
     *
     * @param int $expiration Default expiration in seconds
     * @param int $user_id    User ID (optional, added in WP 6.3+)
     * @return int Extended expiration
     */
    public static function extend_expiration(int $expiration, int $user_id = 0): int {
        // If no user_id provided (older WP versions), return default
        if (!$user_id) {
            return $expiration;
        }

        // Check if this user is flagged for password change
        $must_change = get_user_meta($user_id, self::META_MUST_CHANGE, true);

        if ($must_change) {
            // Return 10 years in seconds (effectively never expires)
            return 10 * YEAR_IN_SECONDS;
        }

        // Return default expiration for non-flagged users
        return $expiration;
    }

    /**
     * Clear flag after successful password reset
     *
     * @param \WP_User|int $user User object or ID
     * @return void
     */
    public static function clear_flag_on_reset($user): void {
        // Get user ID
        $user_id = $user instanceof \WP_User ? $user->ID : (int) $user;

        if (!$user_id) {
            return;
        }

        // Delete all related meta
        delete_user_meta($user_id, self::META_MUST_CHANGE);
        delete_user_meta($user_id, self::META_LAST_ISSUED);
        delete_user_meta($user_id, self::META_REASON);
    }

    /**
     * Generate reset link for a user
     *
     * @param \WP_User $user User object
     * @return string|false Reset URL or false on failure
     */
    public static function generate_reset_link(\WP_User $user) {
        // Generate password reset key
        $key = get_password_reset_key($user);

        if (is_wp_error($key)) {
            return false;
        }

        // Update last issued timestamp
        update_user_meta($user->ID, self::META_LAST_ISSUED, time());

        // Construct reset URL
        $reset_url = add_query_arg(
            [
                'action' => 'rp',
                'key'    => rawurlencode($key),
                'login'  => rawurlencode($user->user_login),
            ],
            wp_login_url()
        );

        return $reset_url;
    }

    /**
     * Send password reset email to user
     *
     * @param \WP_User $user         User object
     * @param string   $reset_url    Optional pre-generated reset URL
     * @param string   $reason       Optional reason for reset
     * @return bool Whether email was sent successfully
     */
    public static function send_reset_email(\WP_User $user, string $reset_url = '', string $reason = ''): bool {
        // Generate reset link if not provided
        if (empty($reset_url)) {
            $reset_url = self::generate_reset_link($user);
            if (!$reset_url) {
                return false;
            }
        }

        // Store reason if provided
        if (!empty($reason)) {
            update_user_meta($user->ID, self::META_REASON, sanitize_text_field($reason));
        }

        // Prepare email
        $to = $user->user_email;
        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        // Email subject
        $subject = sprintf(
            /* translators: %s: Site name */
            __('[%s] Password Reset Required', 'wp-easy-force-password-change'),
            $site_name
        );

        // Allow filtering subject
        $subject = apply_filters('wpe_fpc_email_subject', $subject, $user);

        // Email message
        $message = self::_get_email_template($user, $reset_url, $reason, $site_name);

        // Email headers
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Allow filtering headers
        $headers = apply_filters('wpe_fpc_email_headers', $headers, $user);

        // Send email
        $sent = wp_mail($to, $subject, $message, $headers);

        return $sent;
    }

    /**
     * Get email template with placeholders replaced
     *
     * @param \WP_User $user      User object
     * @param string   $reset_url Reset URL
     * @param string   $reason    Reason for reset
     * @param string   $site_name Site name
     * @return string HTML email content
     */
    private static function _get_email_template(\WP_User $user, string $reset_url, string $reason, string $site_name): string {
        // Get template from settings
        if (class_exists('WP_Easy\\ForcePW_Change\\Admin\\Settings')) {
            $template = \WP_Easy\ForcePW_Change\Admin\Settings::get_email_template();
        } else {
            $template = self::_get_fallback_template();
        }

        // Build placeholders array
        $placeholders = [
            '{{user_login}}'        => $user->user_login,
            '{{user_email}}'        => $user->user_email,
            '{{user_display_name}}' => $user->display_name,
            '{{user_first_name}}'   => get_user_meta($user->ID, 'first_name', true),
            '{{user_last_name}}'    => get_user_meta($user->ID, 'last_name', true),
            '{{site_name}}'         => $site_name,
            '{{site_url}}'          => get_site_url(),
            '{{admin_email}}'       => get_option('admin_email'),
            '{{reset_url}}'         => $reset_url,
            '{{reason}}'            => $reason,
        ];

        // Replace placeholders
        $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);

        // Wrap in HTML structure if not already
        if (stripos($message, '<!DOCTYPE') === false && stripos($message, '<html') === false) {
            $message = '<!DOCTYPE html>'
                . '<html lang="en">'
                . '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
                . '<body>' . $message . '</body>'
                . '</html>';
        }

        // Allow filtering template
        return apply_filters('wpe_fpc_email_template', $message, $user, $reset_url, $reason);
    }

    /**
     * Get fallback email template if Settings class is not available
     *
     * @return string Fallback HTML template
     */
    private static function _get_fallback_template(): string {
        return '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">'
            . '<div style="background-color: #f4f4f4; padding: 20px; border-radius: 5px;">'
            . '<h2 style="color: #0073aa; margin-top: 0;">Hello {{user_display_name}},</h2>'
            . '<p>A password reset has been requested for your account. You must reset your password before you can sign in.</p>'
            . '<div style="text-align: center; margin: 30px 0;">'
            . '<a href="{{reset_url}}" style="display: inline-block; background-color: #0073aa; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 3px; font-weight: bold;">Reset Your Password</a>'
            . '</div>'
            . '<p style="font-size: 12px; color: #666;">If the button above doesn\'t work, copy and paste this link into your browser:<br>'
            . '<a href="{{reset_url}}" style="color: #0073aa; word-break: break-all;">{{reset_url}}</a></p>'
            . '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">'
            . '<p style="font-size: 12px; color: #666; margin: 0;"><strong>Security Note:</strong> This reset link will remain valid until you change your password. If you did not expect this email, please contact your site administrator immediately.</p>'
            . '</div>'
            . '</div>'
            . '<p style="font-size: 12px; color: #999; margin-top: 20px; text-align: center;">This email was sent from {{site_name}}</p>'
            . '</div>';
    }

    /**
     * Output custom CSS on login page for password reset
     *
     * @return void
     */
    public static function output_custom_css(): void {
        // Only output on password reset page
        if (!isset($_GET['action']) || $_GET['action'] !== 'rp') {
            return;
        }

        // Check if plugin is enabled
        if (!class_exists('WP_Easy\\ForcePW_Change\\Admin\\Settings')) {
            return;
        }

        if (!Admin\Settings::is_enabled()) {
            return;
        }

        // Get custom CSS
        $custom_css = Admin\Settings::get_custom_css();

        if (empty($custom_css)) {
            return;
        }

        // Output CSS (already sanitized when saved, no need to escape)
        echo '<style id="wpe-fpc-custom-css">' . "\n";
        echo $custom_css;
        echo "\n" . '</style>' . "\n";
    }
}
