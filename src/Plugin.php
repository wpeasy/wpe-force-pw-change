<?php
/**
 * Main Plugin Bootstrap Class
 *
 * @package WP_Easy\ForcePW_Change
 */

namespace WP_Easy\ForcePW_Change;

defined('ABSPATH') || exit;

/**
 * Main plugin initialization class
 */
final class Plugin {
    /**
     * Initialize the plugin
     *
     * @return void
     */
    public static function init(): void {
        // Initialize Auth components
        Auth\Interceptor::init();
        Auth\Reset_Link::init();

        // Initialize Admin components
        if (is_admin()) {
            Admin\Admin_UI::init();
            Admin\Users_List::init();
            Admin\Settings::init();
        }

        // Enqueue assets
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public static function enqueue_admin_assets(string $hook): void {
        // Only load on user pages
        if (!in_array($hook, ['users.php', 'user-edit.php', 'profile.php', 'user-new.php'], true)) {
            return;
        }

        // Enqueue admin CSS
        wp_enqueue_style(
            'wpe-fpc-admin',
            WPE_FPC_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WPE_FPC_VERSION
        );

        // Enqueue admin JS
        wp_enqueue_script(
            'wpe-fpc-admin',
            WPE_FPC_PLUGIN_URL . 'assets/js/admin.js',
            [],
            WPE_FPC_VERSION,
            true
        );

        // Localize script with translations and data
        wp_localize_script('wpe-fpc-admin', 'wpeFpcData', [
            'nonce' => wp_create_nonce('wpe_fpc_admin'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'strings' => [
                'copySuccess' => __('Reset URL copied to clipboard!', 'wp-easy-force-password-change'),
                'copyError' => __('Failed to copy URL. Please copy manually.', 'wp-easy-force-password-change'),
                'confirmBulk' => __('Are you sure you want to force password reset for the selected users?', 'wp-easy-force-password-change'),
            ],
        ]);
    }
}
