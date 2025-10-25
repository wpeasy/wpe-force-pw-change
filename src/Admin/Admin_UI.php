<?php
/**
 * Admin UI Class
 *
 * @package WP_Easy\ForcePW_Change
 */

namespace WP_Easy\ForcePW_Change\Admin;

use WP_Easy\ForcePW_Change\Auth\Reset_Link;

defined('ABSPATH') || exit;

/**
 * Handles admin UI for user edit screen
 */
final class Admin_UI {
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
        // Add section to user profile pages
        add_action('show_user_profile', [__CLASS__, 'render_profile_section']);
        add_action('edit_user_profile', [__CLASS__, 'render_profile_section']);

        // Handle form submissions
        add_action('admin_post_wpe_fpc_force_reset', [__CLASS__, 'handle_force_reset']);
        add_action('admin_post_wpe_fpc_revoke_reset', [__CLASS__, 'handle_revoke_reset']);

        // AJAX handlers
        add_action('wp_ajax_wpe_fpc_get_reset_url', [__CLASS__, 'ajax_get_reset_url']);

        // Add admin notices
        add_action('admin_notices', [__CLASS__, 'show_admin_notices']);
    }

    /**
     * Render profile section
     *
     * @param \WP_User $user User object
     * @return void
     */
    public static function render_profile_section(\WP_User $user): void {
        // Check permissions
        if (!current_user_can('edit_users') && get_current_user_id() !== $user->ID) {
            return;
        }

        // Don't show on own profile unless you're an admin editing your own profile
        if (get_current_user_id() === $user->ID && !current_user_can('edit_users')) {
            return;
        }

        $must_change = (bool) get_user_meta($user->ID, self::META_MUST_CHANGE, true);
        $last_issued = (int) get_user_meta($user->ID, self::META_LAST_ISSUED, true);

        ?>
        <div class="wpe-fpc-profile-section">
            <h2><?php esc_html_e('Force Password Change', 'wp-easy-force-password-change'); ?></h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Password Reset Status', 'wp-easy-force-password-change'); ?></th>
                    <td>
                        <?php if ($must_change): ?>
                            <span class="wpe-fpc-status wpe-fpc-status-active">
                                <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                                <?php esc_html_e('Password reset required', 'wp-easy-force-password-change'); ?>
                            </span>

                            <?php if ($last_issued): ?>
                                <p class="description">
                                    <?php
                                    printf(
                                        /* translators: %s: Time since last issued */
                                        esc_html__('Last issued: %s ago', 'wp-easy-force-password-change'),
                                        esc_html(human_time_diff($last_issued, time()))
                                    );
                                    ?>
                                </p>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="wpe-fpc-status wpe-fpc-status-inactive">
                                <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                <?php esc_html_e('No password reset required', 'wp-easy-force-password-change'); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>

                <?php if (current_user_can('edit_users')): ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Actions', 'wp-easy-force-password-change'); ?></th>
                        <td>
                            <?php if ($must_change): ?>
                                <!-- Revoke Reset -->
                                <?php
                                $revoke_url = add_query_arg([
                                    'action' => 'wpe_fpc_revoke_reset',
                                    'user_id' => $user->ID,
                                    '_wpnonce' => wp_create_nonce('wpe_fpc_revoke_reset_' . $user->ID),
                                ], admin_url('admin-post.php'));
                                ?>
                                <a href="<?php echo esc_url($revoke_url); ?>" class="button" style="margin-right: 10px;">
                                    <?php esc_html_e('Revoke Password Reset Requirement', 'wp-easy-force-password-change'); ?>
                                </a>

                                <!-- Generate & Copy Link -->
                                <button type="button" class="button" id="wpe-fpc-generate-link" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                    <?php esc_html_e('Generate & Copy Reset Link', 'wp-easy-force-password-change'); ?>
                                </button>

                                <!-- Send Email -->
                                <?php
                                $send_email_url = add_query_arg([
                                    'action' => 'wpe_fpc_force_reset',
                                    'user_id' => $user->ID,
                                    'send_email' => '1',
                                    'skip_flag' => '1',
                                    '_wpnonce' => wp_create_nonce('wpe_fpc_force_reset_' . $user->ID),
                                ], admin_url('admin-post.php'));
                                ?>
                                <a href="<?php echo esc_url($send_email_url); ?>" class="button" style="margin-left: 10px;">
                                    <?php esc_html_e('Send Reset Email', 'wp-easy-force-password-change'); ?>
                                </a>

                                <div id="wpe-fpc-reset-url-container" style="margin-top: 10px; display: none;">
                                    <input type="text" id="wpe-fpc-reset-url" readonly class="regular-text" style="width: 100%; max-width: 500px;">
                                </div>

                            <?php else: ?>
                                <!-- Force Reset -->
                                <div style="margin-bottom: 10px;">
                                    <label style="display: inline-block; margin-right: 10px;">
                                        <input type="checkbox" id="wpe-fpc-send-email-checkbox" checked>
                                        <?php esc_html_e('Send email notification', 'wp-easy-force-password-change'); ?>
                                    </label>
                                </div>

                                <button type="button" class="button button-primary" id="wpe-fpc-force-reset-btn" data-user-id="<?php echo esc_attr($user->ID); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('wpe_fpc_force_reset_' . $user->ID)); ?>">
                                    <?php esc_html_e('Force Password Reset', 'wp-easy-force-password-change'); ?>
                                </button>
                            <?php endif; ?>

                            <p class="description">
                                <?php esc_html_e('When forced, the user will be redirected to reset their password on next login attempt.', 'wp-easy-force-password-change'); ?>
                            </p>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php
    }

    /**
     * Handle force reset form submission
     *
     * @return void
     */
    public static function handle_force_reset(): void {
        // Get user ID from GET or POST
        $user_id = isset($_REQUEST['user_id']) ? absint($_REQUEST['user_id']) : 0;
        if (!$user_id) {
            wp_die(esc_html__('Invalid user ID.', 'wp-easy-force-password-change'));
        }

        // Verify nonce (check both GET and POST)
        $nonce_verified = false;
        if (isset($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'wpe_fpc_force_reset_' . $user_id)) {
            $nonce_verified = true;
        } elseif (isset($_POST['wpe_fpc_nonce']) && wp_verify_nonce($_POST['wpe_fpc_nonce'], 'wpe_fpc_force_reset_' . $user_id)) {
            $nonce_verified = true;
        }

        if (!$nonce_verified) {
            wp_die(esc_html__('Security check failed.', 'wp-easy-force-password-change'));
        }

        // Check permissions
        if (!current_user_can('edit_user', $user_id)) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'wp-easy-force-password-change'));
        }

        // Get user
        $user = get_userdata($user_id);
        if (!$user) {
            wp_die(esc_html__('User not found.', 'wp-easy-force-password-change'));
        }

        // Set flag (unless skip_flag is set)
        $skip_flag = isset($_REQUEST['skip_flag']) && $_REQUEST['skip_flag'];
        if (!$skip_flag) {
            update_user_meta($user_id, self::META_MUST_CHANGE, 1);
        }

        // Generate reset link
        $reset_url = Reset_Link::generate_reset_link($user);

        $messages = [];

        if ($reset_url) {
            $messages[] = __('Password reset has been flagged.', 'wp-easy-force-password-change');

            // Send email if requested
            if (isset($_REQUEST['send_email']) && $_REQUEST['send_email']) {
                $sent = Reset_Link::send_reset_email($user, $reset_url);
                if ($sent) {
                    $messages[] = __('Reset email sent successfully.', 'wp-easy-force-password-change');
                } else {
                    $messages[] = __('Warning: Email could not be sent.', 'wp-easy-force-password-change');
                }
            }
        } else {
            $messages[] = __('Error generating reset link.', 'wp-easy-force-password-change');
        }

        // Redirect back with message
        $redirect_url = add_query_arg(
            [
                'user_id'          => $user_id,
                'wpe_fpc_message'  => urlencode(implode(' ', $messages)),
            ],
            admin_url('user-edit.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Handle revoke reset form submission
     *
     * @return void
     */
    public static function handle_revoke_reset(): void {
        // Get user ID from GET or POST
        $user_id = isset($_REQUEST['user_id']) ? absint($_REQUEST['user_id']) : 0;
        if (!$user_id) {
            wp_die(esc_html__('Invalid user ID.', 'wp-easy-force-password-change'));
        }

        // Verify nonce (check both GET and POST)
        $nonce_verified = false;
        if (isset($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'wpe_fpc_revoke_reset_' . $user_id)) {
            $nonce_verified = true;
        } elseif (isset($_POST['wpe_fpc_nonce']) && wp_verify_nonce($_POST['wpe_fpc_nonce'], 'wpe_fpc_revoke_reset_' . $user_id)) {
            $nonce_verified = true;
        }

        if (!$nonce_verified) {
            wp_die(esc_html__('Security check failed.', 'wp-easy-force-password-change'));
        }

        // Check permissions
        if (!current_user_can('edit_user', $user_id)) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'wp-easy-force-password-change'));
        }

        // Clear flags
        Reset_Link::clear_flag_on_reset($user_id);

        // Redirect back with message
        $redirect_url = add_query_arg(
            [
                'user_id'         => $user_id,
                'wpe_fpc_message' => urlencode(__('Password reset requirement has been revoked.', 'wp-easy-force-password-change')),
            ],
            admin_url('user-edit.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Show admin notices
     *
     * @return void
     */
    public static function show_admin_notices(): void {
        if (isset($_GET['wpe_fpc_message'])) {
            $message = sanitize_text_field(urldecode($_GET['wpe_fpc_message']));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }

    /**
     * AJAX handler to get reset URL for a user
     *
     * @return void
     */
    public static function ajax_get_reset_url(): void {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpe_fpc_admin')) {
            wp_send_json_error(['message' => __('Security check failed.', 'wp-easy-force-password-change')]);
        }

        // Get user ID
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        if (!$user_id) {
            wp_send_json_error(['message' => __('Invalid user ID.', 'wp-easy-force-password-change')]);
        }

        // Check permissions
        if (!current_user_can('edit_user', $user_id)) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'wp-easy-force-password-change')]);
        }

        // Get user
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(['message' => __('User not found.', 'wp-easy-force-password-change')]);
        }

        // Generate reset link
        $reset_url = Reset_Link::generate_reset_link($user);

        if ($reset_url) {
            wp_send_json_success(['reset_url' => $reset_url]);
        } else {
            wp_send_json_error(['message' => __('Failed to generate reset link.', 'wp-easy-force-password-change')]);
        }
    }
}
