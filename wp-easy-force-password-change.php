<?php
/**
 * Plugin Name: WP Easy Force Password Change
 * Plugin URI: https://github.com/yourusername/wp-easy-force-password-change
 * Description: Enforce password change workflow for users created programmatically or via WP Admin. Intercepts login and redirects to password reset flow until password is changed.
 * Version: 0.0.2-alpha
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-easy-force-password-change
 * Domain Path: /languages
 *
 * @package WP_Easy\ForcePW_Change
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('WPE_FPC_VERSION', '0.0.2-alpha');
define('WPE_FPC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WPE_FPC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPE_FPC_PLUGIN_FILE', __FILE__);

// Composer autoloader
if (file_exists(WPE_FPC_PLUGIN_PATH . 'vendor/autoload.php')) {
    require_once WPE_FPC_PLUGIN_PATH . 'vendor/autoload.php';
}

// Initialize plugin
add_action('plugins_loaded', function() {
    // Load text domain
    load_plugin_textdomain(
        'wp-easy-force-password-change',
        false,
        dirname(plugin_basename(WPE_FPC_PLUGIN_FILE)) . '/languages'
    );

    // Initialize plugin if autoloader is available
    if (class_exists('WP_Easy\\ForcePW_Change\\Plugin')) {
        \WP_Easy\ForcePW_Change\Plugin::init();
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('WP Easy Force Password Change: Please run "composer install" to install dependencies.', 'wp-easy-force-password-change');
            echo '</p></div>';
        });
    }
});

// Activation hook
register_activation_hook(__FILE__, function() {
    // Set default options if needed
    // Future: could add activation tasks here
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Cleanup if needed
    // Note: We don't remove user meta on deactivation to preserve data
});
