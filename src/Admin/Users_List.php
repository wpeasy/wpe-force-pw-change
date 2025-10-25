<?php
/**
 * Users List Table Class
 *
 * @package WP_Easy\ForcePW_Change
 */

namespace WP_Easy\ForcePW_Change\Admin;

use WP_Easy\ForcePW_Change\Auth\Reset_Link;

defined('ABSPATH') || exit;

/**
 * Handles users list table modifications
 */
final class Users_List {
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
        // Add custom column
        add_filter('manage_users_columns', [__CLASS__, 'add_columns']);
        add_filter('manage_users_custom_column', [__CLASS__, 'render_column_content'], 10, 3);

        // Make column sortable
        add_filter('manage_users_sortable_columns', [__CLASS__, 'add_sortable_columns']);

        // Add row actions
        add_filter('user_row_actions', [__CLASS__, 'add_row_actions'], 10, 2);

        // Handle row actions
        add_action('admin_action_wpe_fpc_force', [__CLASS__, 'handle_force_action']);
        add_action('admin_action_wpe_fpc_revoke', [__CLASS__, 'handle_revoke_action']);

        // Add bulk actions
        add_filter('bulk_actions-users', [__CLASS__, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-users', [__CLASS__, 'handle_bulk_actions'], 10, 3);

        // Add admin notices for bulk actions
        add_action('admin_notices', [__CLASS__, 'show_bulk_action_notices']);
    }

    /**
     * Add custom columns to users list
     *
     * @param array<string, string> $columns Existing columns
     * @return array<string, string> Modified columns
     */
    public static function add_columns(array $columns): array {
        // Add column after 'email' column
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'email') {
                $new_columns['wpe_fpc_status'] = __('PW Reset Required', 'wp-easy-force-password-change');
            }
        }

        return $new_columns;
    }

    /**
     * Render custom column content
     *
     * @param string $output      Custom column output
     * @param string $column_name Column name
     * @param int    $user_id     User ID
     * @return string Column content
     */
    public static function render_column_content(string $output, string $column_name, int $user_id): string {
        if ($column_name !== 'wpe_fpc_status') {
            return $output;
        }

        $must_change = get_user_meta($user_id, self::META_MUST_CHANGE, true);
        $last_issued = (int) get_user_meta($user_id, self::META_LAST_ISSUED, true);

        if ($must_change) {
            $output = '<span class="wpe-fpc-badge wpe-fpc-badge-yes" style="display: inline-block; padding: 3px 8px; border-radius: 3px; background: #d63638; color: white; font-size: 11px; font-weight: 600;">';
            $output .= esc_html__('Yes', 'wp-easy-force-password-change');
            $output .= '</span>';

            if ($last_issued) {
                $output .= '<br><span style="font-size: 11px; color: #666;">';
                $output .= esc_html(human_time_diff($last_issued, time())) . ' ' . esc_html__('ago', 'wp-easy-force-password-change');
                $output .= '</span>';
            }
        } else {
            $output = '<span class="wpe-fpc-badge wpe-fpc-badge-no" style="display: inline-block; padding: 3px 8px; border-radius: 3px; background: #f0f0f1; color: #50575e; font-size: 11px;">';
            $output .= esc_html__('No', 'wp-easy-force-password-change');
            $output .= '</span>';
        }

        return $output;
    }

    /**
     * Add sortable columns
     *
     * @param array<string, string> $columns Sortable columns
     * @return array<string, string> Modified columns
     */
    public static function add_sortable_columns(array $columns): array {
        $columns['wpe_fpc_status'] = 'wpe_fpc_status';
        return $columns;
    }

    /**
     * Add row actions
     *
     * @param array<string, string> $actions Row actions
     * @param \WP_User              $user    User object
     * @return array<string, string> Modified actions
     */
    public static function add_row_actions(array $actions, \WP_User $user): array {
        // Check permissions
        if (!current_user_can('edit_user', $user->ID)) {
            return $actions;
        }

        $must_change = get_user_meta($user->ID, self::META_MUST_CHANGE, true);

        if ($must_change) {
            // Add revoke action
            $revoke_url = wp_nonce_url(
                add_query_arg(
                    [
                        'action'  => 'wpe_fpc_revoke',
                        'user_id' => $user->ID,
                    ],
                    admin_url('users.php')
                ),
                'wpe_fpc_revoke_' . $user->ID
            );

            $actions['wpe_fpc_revoke'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($revoke_url),
                esc_html__('Revoke PW Reset', 'wp-easy-force-password-change')
            );
        } else {
            // Add force reset action
            $force_url = wp_nonce_url(
                add_query_arg(
                    [
                        'action'  => 'wpe_fpc_force',
                        'user_id' => $user->ID,
                    ],
                    admin_url('users.php')
                ),
                'wpe_fpc_force_' . $user->ID
            );

            $actions['wpe_fpc_force'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($force_url),
                esc_html__('Force PW Reset', 'wp-easy-force-password-change')
            );
        }

        return $actions;
    }

    /**
     * Handle force password reset row action
     *
     * @return void
     */
    public static function handle_force_action(): void {
        // Get user ID
        $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
        if (!$user_id) {
            wp_die(esc_html__('Invalid user ID.', 'wp-easy-force-password-change'));
        }

        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wpe_fpc_force_' . $user_id)) {
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

        // Set flag
        update_user_meta($user_id, self::META_MUST_CHANGE, 1);

        // Generate reset link and send email
        $reset_url = Reset_Link::generate_reset_link($user);
        if ($reset_url) {
            Reset_Link::send_reset_email($user, $reset_url);
        }

        // Redirect back with success message
        wp_safe_redirect(add_query_arg('wpe_fpc_forced', 1, admin_url('users.php')));
        exit;
    }

    /**
     * Handle revoke password reset row action
     *
     * @return void
     */
    public static function handle_revoke_action(): void {
        // Get user ID
        $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
        if (!$user_id) {
            wp_die(esc_html__('Invalid user ID.', 'wp-easy-force-password-change'));
        }

        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wpe_fpc_revoke_' . $user_id)) {
            wp_die(esc_html__('Security check failed.', 'wp-easy-force-password-change'));
        }

        // Check permissions
        if (!current_user_can('edit_user', $user_id)) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'wp-easy-force-password-change'));
        }

        // Clear flag
        Reset_Link::clear_flag_on_reset($user_id);

        // Redirect back with success message
        wp_safe_redirect(add_query_arg('wpe_fpc_revoked', 1, admin_url('users.php')));
        exit;
    }

    /**
     * Add bulk actions
     *
     * @param array<string, string> $bulk_actions Existing bulk actions
     * @return array<string, string> Modified bulk actions
     */
    public static function add_bulk_actions(array $bulk_actions): array {
        $bulk_actions['wpe_fpc_force_bulk'] = __('Force Password Reset', 'wp-easy-force-password-change');
        $bulk_actions['wpe_fpc_revoke_bulk'] = __('Revoke Password Reset', 'wp-easy-force-password-change');

        return $bulk_actions;
    }

    /**
     * Handle bulk actions
     *
     * @param string $redirect_to Redirect URL
     * @param string $doaction    Action being performed
     * @param array  $user_ids    Selected user IDs
     * @return string Modified redirect URL
     */
    public static function handle_bulk_actions(string $redirect_to, string $doaction, array $user_ids): string {
        // Handle bulk action - Force
        if ($doaction === 'wpe_fpc_force_bulk') {
            $count = 0;

            foreach ($user_ids as $user_id) {
                $user_id = absint($user_id);

                // Check permissions
                if (!current_user_can('edit_user', $user_id)) {
                    continue;
                }

                // Get user
                $user = get_userdata($user_id);
                if (!$user) {
                    continue;
                }

                // Set flag
                update_user_meta($user_id, self::META_MUST_CHANGE, 1);

                // Generate reset link and send email
                $reset_url = Reset_Link::generate_reset_link($user);
                if ($reset_url) {
                    Reset_Link::send_reset_email($user, $reset_url);
                }

                $count++;
            }

            return add_query_arg('wpe_fpc_forced', $count, $redirect_to);
        }

        // Handle bulk action - Revoke
        if ($doaction === 'wpe_fpc_revoke_bulk') {
            $count = 0;

            foreach ($user_ids as $user_id) {
                $user_id = absint($user_id);

                // Check permissions
                if (!current_user_can('edit_user', $user_id)) {
                    continue;
                }

                // Clear flag
                Reset_Link::clear_flag_on_reset($user_id);
                $count++;
            }

            return add_query_arg('wpe_fpc_revoked', $count, $redirect_to);
        }

        return $redirect_to;
    }

    /**
     * Show bulk action notices
     *
     * @return void
     */
    public static function show_bulk_action_notices(): void {
        if (isset($_GET['wpe_fpc_forced'])) {
            $count = absint($_GET['wpe_fpc_forced']);
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html(
                    sprintf(
                        /* translators: %d: Number of users */
                        _n(
                            'Password reset forced for %d user.',
                            'Password reset forced for %d users.',
                            $count,
                            'wp-easy-force-password-change'
                        ),
                        $count
                    )
                )
            );
        }

        if (isset($_GET['wpe_fpc_revoked'])) {
            $count = absint($_GET['wpe_fpc_revoked']);
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html(
                    sprintf(
                        /* translators: %d: Number of users */
                        _n(
                            'Password reset revoked for %d user.',
                            'Password reset revoked for %d users.',
                            $count,
                            'wp-easy-force-password-change'
                        ),
                        $count
                    )
                )
            );
        }
    }
}
