<?php
/**
 * Settings Page Class
 *
 * @package WP_Easy\ForcePW_Change
 */

namespace WP_Easy\ForcePW_Change\Admin;

defined('ABSPATH') || exit;

/**
 * Handles plugin settings page
 */
final class Settings {
    /**
     * Option key for plugin enabled status
     */
    private const OPTION_ENABLED = 'wpe_fpc_enabled';

    /**
     * Option key for custom CSS
     */
    private const OPTION_CUSTOM_CSS = 'wpe_fpc_custom_css';

    /**
     * Option key for user-defined default CSS
     */
    private const OPTION_USER_DEFAULT_CSS = 'wpe_fpc_user_default_css';

    /**
     * Option key for email template
     */
    private const OPTION_EMAIL_TEMPLATE = 'wpe_fpc_email_template';

    /**
     * Option key for disabling default new user email
     */
    private const OPTION_DISABLE_DEFAULT_EMAIL = 'wpe_fpc_disable_default_email';

    /**
     * Initialize hooks
     *
     * @return void
     */
    public static function init(): void {
        // Add admin menu
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);

        // Register settings
        add_action('admin_init', [__CLASS__, 'register_settings']);

        // Enqueue settings page assets
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_settings_assets']);

        // AJAX handlers
        add_action('wp_ajax_wpe_fpc_save_settings', [__CLASS__, 'ajax_save_settings']);
        add_action('wp_ajax_wpe_fpc_reset_css', [__CLASS__, 'ajax_reset_css']);
        add_action('wp_ajax_wpe_fpc_save_as_default', [__CLASS__, 'ajax_save_as_default']);
        add_action('wp_ajax_wpe_fpc_save_email_template', [__CLASS__, 'ajax_save_email_template']);
    }

    /**
     * Add settings menu page
     *
     * @return void
     */
    public static function add_menu_page(): void {
        add_options_page(
            __('Force Password Change Settings', 'wp-easy-force-password-change'),
            __('Force PW Change', 'wp-easy-force-password-change'),
            'manage_options',
            'wpe-force-pw-change',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Register settings
     *
     * @return void
     */
    public static function register_settings(): void {
        register_setting('wpe_fpc_settings', self::OPTION_ENABLED, [
            'type'              => 'boolean',
            'default'           => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);

        register_setting('wpe_fpc_settings', self::OPTION_CUSTOM_CSS, [
            'type'              => 'string',
            'default'           => self::get_default_css(),
            'sanitize_callback' => [__CLASS__, 'sanitize_css'],
        ]);

        register_setting('wpe_fpc_settings', self::OPTION_EMAIL_TEMPLATE, [
            'type'              => 'string',
            'default'           => self::get_default_email_template(),
            'sanitize_callback' => [__CLASS__, 'sanitize_email_template'],
        ]);

        register_setting('wpe_fpc_settings', self::OPTION_DISABLE_DEFAULT_EMAIL, [
            'type'              => 'boolean',
            'default'           => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
    }

    /**
     * Sanitize CSS
     *
     * @param string $css CSS input
     * @return string Sanitized CSS
     */
    public static function sanitize_css(string $css): string {
        // Remove any HTML tags, particularly script/style tags for security
        // But preserve quotes and special characters needed for CSS
        $css = strip_tags($css);
        return $css;
    }

    /**
     * Sanitize email template
     *
     * @param string $template Email template HTML
     * @return string Sanitized template
     */
    public static function sanitize_email_template(string $template): string {
        // Minimal sanitization - only strip script tags and other dangerous elements
        // Allow all HTML needed for email templates
        $allowed_tags = wp_kses_allowed_html('post');

        // Add additional tags commonly used in email templates
        $allowed_tags['style'] = [];
        $allowed_tags['table'] = ['style' => [], 'width' => [], 'border' => [], 'cellpadding' => [], 'cellspacing' => []];
        $allowed_tags['tr'] = ['style' => []];
        $allowed_tags['td'] = ['style' => [], 'colspan' => [], 'rowspan' => [], 'width' => []];
        $allowed_tags['th'] = ['style' => [], 'colspan' => [], 'rowspan' => [], 'width' => []];
        $allowed_tags['thead'] = ['style' => []];
        $allowed_tags['tbody'] = ['style' => []];
        $allowed_tags['tfoot'] = ['style' => []];

        // Allow style attributes on all elements
        foreach ($allowed_tags as $tag => &$attributes) {
            if (!isset($attributes['style'])) {
                $attributes['style'] = [];
            }
        }

        return wp_kses($template, $allowed_tags);
    }

    /**
     * Get default CSS for password reset page
     *
     * @return string Default CSS
     */
    public static function get_default_css(): string {
        return <<<CSS
body.login.login-action-rp {
    /* Colors */
    --_login-bg-gradient-start: #667eea;
    --_login-bg-gradient-end: #764ba2;

    --_login-color-primary: #667eea;
    --_login-color-secondary: #764ba2;
    --_login-color-accent: #00a0d2;

    --_login-color-white: #fff;
    --_login-color-light: #f0f0f0;
    --_login-color-dark: #333;
    --_login-color-text: #444;

    --_login-color-warning-bg: #fff3cd;
    --_login-color-warning-border: #ffc107;
    --_login-color-warning-text: #856404;

    --_login-color-weak-bg: #f8d7da;
    --_login-color-weak-border: #f5c6cb;
    --_login-color-weak-text: #721c24;

    --_login-color-good-bg: #d4edda;
    --_login-color-good-border: #c3e6cb;
    --_login-color-good-text: #155724;

    --_login-color-strong-bg: #d1ecf1;
    --_login-color-strong-border: #bee5eb;
    --_login-color-strong-text: #0c5460;

    background: linear-gradient(135deg, var(--_login-bg-gradient-start) 0%, var(--_login-bg-gradient-end) 100%);

    #login {
        padding-top: 5vh;

        h1 {
            a {
                background-size: contain;
                width: 320px;
                height: 120px;
                margin-bottom: 25px;
                transition: transform 0.3s ease;

                &:hover {
                    transform: scale(1.05);
                }
            }
        }

        .message {
            border-left: 4px solid var(--_login-color-accent);
            background: var(--_login-color-white);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;

            strong {
                color: var(--_login-color-accent);
                font-size: 1.1em;
            }

            p {
                margin: 10px 0 0;
                color: var(--_login-color-text);
                line-height: 1.6;
            }
        }

        form {
            background: var(--_login-color-white);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            padding: 30px;
            margin-top: 20px;

            .user-pass-wrap {
                margin-bottom: 20px;

                label {
                    font-weight: 600;
                    color: var(--_login-color-dark);
                    margin-bottom: 8px;
                    display: block;
                }

                input[type="password"] {
                    border: 2px solid #e0e0e0;
                    border-radius: 6px;
                    padding: 12px 15px;
                    font-size: 16px;
                    transition: all 0.3s ease;

                    &:focus {
                        border-color: var(--_login-color-primary);
                        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                        outline: none;
                    }
                }

                .button.wp-hide-pw {
                    background: transparent;
                    border: none;
                    color: var(--_login-color-primary);

                    &:hover {
                        color: var(--_login-color-secondary);
                    }
                }
            }

            .pw-weak {
                background: var(--_login-color-warning-bg);
                border-left: 4px solid var(--_login-color-warning-border);
                padding: 12px;
                margin: 15px 0;
                border-radius: 4px;

                label {
                    color: var(--_login-color-warning-text);
                }
            }

            .submit {
                text-align: center;
                margin-top: 25px;

                .button.button-primary {
                    background: linear-gradient(135deg, var(--_login-color-primary) 0%, var(--_login-color-secondary) 100%);
                    border: none;
                    border-radius: 6px;
                    padding: 12px 40px;
                    font-size: 16px;
                    font-weight: 600;
                    text-shadow: none;
                    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                    transition: all 0.3s ease;

                    &:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
                    }

                    &:active {
                        transform: translateY(0);
                    }
                }
            }
        }

        #pass-strength-result {
            border-radius: 6px;
            margin-top: 10px;
            padding: 10px;
            text-align: center;
            font-weight: 600;

            &.short {
                background-color: var(--_login-color-weak-bg);
                border-color: var(--_login-color-weak-border);
                color: var(--_login-color-weak-text);
            }

            &.bad {
                background-color: var(--_login-color-weak-bg);
                border-color: var(--_login-color-weak-border);
                color: var(--_login-color-weak-text);
            }

            &.good {
                background-color: var(--_login-color-good-bg);
                border-color: var(--_login-color-good-border);
                color: var(--_login-color-good-text);
            }

            &.strong {
                background-color: var(--_login-color-strong-bg);
                border-color: var(--_login-color-strong-border);
                color: var(--_login-color-strong-text);
            }
        }

        #backtoblog {
            a {
                color: var(--_login-color-white);
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
                transition: all 0.3s ease;

                &:hover {
                    color: var(--_login-color-light);
                    text-decoration: underline;
                }
            }
        }
    }

    #nav {
        text-align: center;

        a {
            color: var(--_login-color-white);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;

            &:hover {
                color: var(--_login-color-light);
            }
        }
    }

    .privacy-policy-page-link {
        a {
            color: var(--_login-color-white);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);

            &:hover {
                color: var(--_login-color-light);
            }
        }
    }
}
CSS;
    }

    /**
     * Check if plugin is enabled
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_option(self::OPTION_ENABLED, true);
    }

    /**
     * Check if default new user email should be disabled
     *
     * @return bool
     */
    public static function is_default_email_disabled(): bool {
        return (bool) get_option(self::OPTION_DISABLE_DEFAULT_EMAIL, true);
    }

    /**
     * Get custom CSS
     *
     * @return string
     */
    public static function get_custom_css(): string {
        return (string) get_option(self::OPTION_CUSTOM_CSS, self::get_default_css());
    }

    /**
     * Get default email template
     *
     * @return string Default email template with placeholders
     */
    public static function get_default_email_template(): string {
        return <<<HTML
<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f4f4f4; padding: 20px; border-radius: 5px;">
        <h2 style="color: #0073aa; margin-top: 0;">Hello {{user_display_name}},</h2>

        <p>A password reset has been requested for your account. You must reset your password before you can sign in.</p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{reset_url}}" style="display: inline-block; background-color: #0073aa; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 3px; font-weight: bold;">Reset Your Password</a>
        </div>

        <p style="font-size: 12px; color: #666;">
            If the button above doesn't work, copy and paste this link into your browser:<br>
            <a href="{{reset_url}}" style="color: #0073aa; word-break: break-all;">{{reset_url}}</a>
        </p>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <p style="font-size: 12px; color: #666; margin: 0;">
                <strong>Security Note:</strong> This reset link will remain valid until you change your password. If you did not expect this email, please contact your site administrator immediately.
            </p>
        </div>
    </div>

    <p style="font-size: 12px; color: #999; margin-top: 20px; text-align: center;">
        This email was sent from {{site_name}}
    </p>
</div>
HTML;
    }

    /**
     * Get email template
     *
     * @return string
     */
    public static function get_email_template(): string {
        return (string) get_option(self::OPTION_EMAIL_TEMPLATE, self::get_default_email_template());
    }

    /**
     * Enqueue settings page assets
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public static function enqueue_settings_assets(string $hook): void {
        // Only load on our settings page
        if ($hook !== 'settings_page_wpe-force-pw-change') {
            return;
        }

        // Dequeue WordPress's CodeMirror 5 to avoid conflicts
        wp_dequeue_script('code-editor');
        wp_dequeue_script('csslint');
        wp_dequeue_script('jshint');
        wp_dequeue_script('jsonlint');
        wp_dequeue_script('htmlhint');
        wp_dequeue_style('code-editor');

        // Enqueue settings CSS
        wp_enqueue_style(
            'wpe-fpc-settings',
            WPE_FPC_PLUGIN_URL . 'assets/css/settings.css',
            [],
            WPE_FPC_VERSION
        );

        // Enqueue settings JS as module
        wp_enqueue_script(
            'wpe-fpc-settings',
            WPE_FPC_PLUGIN_URL . 'assets/js/settings.js',
            [],
            WPE_FPC_VERSION,
            true
        );

        // Mark script as ES module
        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'wpe-fpc-settings') {
                return str_replace('<script ', '<script type="module" ', $tag);
            }
            return $tag;
        }, 10, 2);

        // Localize script
        wp_localize_script('wpe-fpc-settings', 'wpeFpcSettings', [
            'nonce'      => wp_create_nonce('wpe_fpc_settings'),
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'defaultCss' => self::get_default_css(),
            'strings'    => [
                'saved'                 => __('Settings saved', 'wp-easy-force-password-change'),
                'saveFailed'            => __('Failed to save settings', 'wp-easy-force-password-change'),
                'resetConfirm'          => __('Are you sure you want to reset the CSS to default? This cannot be undone.', 'wp-easy-force-password-change'),
                'resetSuccess'          => __('CSS reset to default', 'wp-easy-force-password-change'),
                'resetFailed'           => __('Failed to reset CSS', 'wp-easy-force-password-change'),
                'saveAsDefaultConfirm'  => __('Are you sure you want to save the current CSS as the new default? This will update what "Reset to Default" restores to.', 'wp-easy-force-password-change'),
                'saveAsDefaultSuccess'  => __('Current CSS saved as new default', 'wp-easy-force-password-change'),
                'saveAsDefaultFailed'   => __('Failed to save as default', 'wp-easy-force-password-change'),
                'emailTemplateSaved'    => __('Email template saved', 'wp-easy-force-password-change'),
                'emailTemplateFailed'   => __('Failed to save email template', 'wp-easy-force-password-change'),
                'placeholderCopied'     => __('Placeholder copied to clipboard', 'wp-easy-force-password-change'),
                'placeholderFailed'     => __('Failed to copy placeholder', 'wp-easy-force-password-change'),
            ],
        ]);
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public static function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $enabled                = self::is_enabled();
        $disable_default_email  = self::is_default_email_disabled();
        $custom_css             = self::get_custom_css();
        $email_template         = self::get_email_template();
        ?>
        <div class="wrap wpe-fpc-settings-wrap">
            <h1><?php esc_html_e('Force Password Change Settings', 'wp-easy-force-password-change'); ?></h1>

            <!-- Tab Navigation -->
            <nav class="wpe-fpc-tabs-nav">
                <button class="wpe-fpc-tab-button wpe-fpc-tab-active" data-tab="general">
                    <?php esc_html_e('General', 'wp-easy-force-password-change'); ?>
                </button>
                <button class="wpe-fpc-tab-button" data-tab="style">
                    <?php esc_html_e('Style', 'wp-easy-force-password-change'); ?>
                </button>
                <button class="wpe-fpc-tab-button" data-tab="email">
                    <?php esc_html_e('Email Template', 'wp-easy-force-password-change'); ?>
                </button>
            </nav>

            <div class="wpe-fpc-settings-container">
                <!-- General Tab -->
                <div class="wpe-fpc-tab-content wpe-fpc-tab-active" data-tab-content="general">
                    <div class="wpe-fpc-setting-card">
                        <div class="wpe-fpc-setting-header">
                            <h2><?php esc_html_e('Plugin Settings', 'wp-easy-force-password-change'); ?></h2>
                            <div class="wpe-fpc-save-indicator" id="wpe-fpc-save-indicator">
                                <span class="dashicons dashicons-saved"></span>
                                <span class="wpe-fpc-save-text"><?php esc_html_e('Saved', 'wp-easy-force-password-change'); ?></span>
                            </div>
                        </div>

                        <div class="wpe-fpc-setting-body">
                            <div class="wpe-fpc-toggle-setting">
                                <label class="wpe-fpc-toggle">
                                    <input
                                        type="checkbox"
                                        id="wpe-fpc-enabled"
                                        name="enabled"
                                        <?php checked($enabled, true); ?>
                                    >
                                    <span class="wpe-fpc-toggle-slider"></span>
                                </label>
                                <div class="wpe-fpc-toggle-label">
                                    <strong><?php esc_html_e('Enable Force Password Change', 'wp-easy-force-password-change'); ?></strong>
                                    <p class="description">
                                        <?php esc_html_e('When enabled, users flagged for password reset will be intercepted at login and redirected to the password reset page.', 'wp-easy-force-password-change'); ?>
                                    </p>
                                </div>
                            </div>

                            <div class="wpe-fpc-toggle-setting" style="margin-top: 20px;">
                                <label class="wpe-fpc-toggle">
                                    <input
                                        type="checkbox"
                                        id="wpe-fpc-disable-default-email"
                                        name="disable_default_email"
                                        <?php checked($disable_default_email, true); ?>
                                    >
                                    <span class="wpe-fpc-toggle-slider"></span>
                                </label>
                                <div class="wpe-fpc-toggle-label">
                                    <strong><?php esc_html_e('Disable Default New User Email', 'wp-easy-force-password-change'); ?></strong>
                                    <p class="description">
                                        <?php esc_html_e('When enabled, WordPress will not send the default new user notification email. Instead, our custom email template will be used.', 'wp-easy-force-password-change'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Style Tab -->
                <div class="wpe-fpc-tab-content" data-tab-content="style">
                    <div class="wpe-fpc-setting-card">
                        <div class="wpe-fpc-setting-header">
                            <h2><?php esc_html_e('Password Reset Page Styling', 'wp-easy-force-password-change'); ?></h2>
                            <div class="wpe-fpc-button-group">
                                <button
                                    type="button"
                                    class="button"
                                    id="wpe-fpc-save-as-default"
                                >
                                    <?php esc_html_e('Save as Default', 'wp-easy-force-password-change'); ?>
                                </button>
                                <button
                                    type="button"
                                    class="button"
                                    id="wpe-fpc-reset-css"
                                >
                                    <?php esc_html_e('Reset to Default', 'wp-easy-force-password-change'); ?>
                                </button>
                            </div>
                        </div>

                        <div class="wpe-fpc-setting-body">
                            <p class="description" style="margin-bottom: 15px;">
                                <?php esc_html_e('Customize the appearance of the password reset page. Changes are auto-saved and applied only to the password reset flow (login-action-rp).', 'wp-easy-force-password-change'); ?>
                            </p>

                            <div id="wpe-fpc-css-editor-container">
                                <textarea
                                    id="wpe-fpc-css-editor"
                                    name="custom_css"
                                    rows="20"
                                    style="width: 100%; font-family: monospace;"
                                ><?php echo esc_textarea($custom_css); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Template Tab -->
                <div class="wpe-fpc-tab-content" data-tab-content="email">
                    <div class="wpe-fpc-setting-card">
                        <div class="wpe-fpc-setting-header">
                            <h2><?php esc_html_e('Password Reset Email Template', 'wp-easy-force-password-change'); ?></h2>
                            <button
                                type="button"
                                class="button button-primary"
                                id="wpe-fpc-save-email-template"
                            >
                                <?php esc_html_e('Save Email Template', 'wp-easy-force-password-change'); ?>
                            </button>
                        </div>

                        <div class="wpe-fpc-setting-body">
                            <p class="description" style="margin-bottom: 15px;">
                                <?php esc_html_e('Customize the password reset email template. Use the placeholders below to insert dynamic content.', 'wp-easy-force-password-change'); ?>
                            </p>

                            <!-- Placeholders -->
                            <div class="wpe-fpc-placeholders" style="margin-bottom: 20px; padding: 15px; background: #2c2c2c; border: 1px solid #444; border-radius: 4px;">
                                <h4 style="margin-top: 0; color: #e0e0e0;"><?php esc_html_e('Available Placeholders (Click to Copy)', 'wp-easy-force-password-change'); ?></h4>

                                <div style="margin-bottom: 10px;">
                                    <strong style="color: #e0e0e0;"><?php esc_html_e('User:', 'wp-easy-force-password-change'); ?></strong><br>
                                    <button type="button" class="button button-small wpe-fpc-copy-placeholder" data-placeholder="{{user_login}}" style="margin: 2px;">{{user_login}}</button>
                                    <button type="button" class="button button-small wpe-fpc-copy-placeholder" data-placeholder="{{user_email}}" style="margin: 2px;">{{user_email}}</button>
                                    <button type="button" class="button button-small wpe-fpc-copy-placeholder" data-placeholder="{{user_display_name}}" style="margin: 2px;">{{user_display_name}}</button>
                                    <button type="button" class="button button-small wpe-fpc-copy-placeholder" data-placeholder="{{user_first_name}}" style="margin: 2px;">{{user_first_name}}</button>
                                    <button type="button" class="button button-small wpe-fpc-copy-placeholder" data-placeholder="{{user_last_name}}" style="margin: 2px;">{{user_last_name}}</button>
                                </div>

                                <div style="margin-bottom: 10px;">
                                    <strong style="color: #e0e0e0;"><?php esc_html_e('Site:', 'wp-easy-force-password-change'); ?></strong><br>
                                    <button type="button" class="button button-small wpe-fpc-copy-placeholder" data-placeholder="{{site_name}}" style="margin: 2px;">{{site_name}}</button>
                                    <button type="button" class="button button-small wpe-fpc-copy-placeholder" data-placeholder="{{site_url}}" style="margin: 2px;">{{site_url}}</button>
                                    <button type="button" class="button button-small wpe-fpc-copy-placeholder" data-placeholder="{{admin_email}}" style="margin: 2px;">{{admin_email}}</button>
                                </div>

                                <div>
                                    <strong style="color: #e0e0e0;"><?php esc_html_e('Reset:', 'wp-easy-force-password-change'); ?></strong><br>
                                    <button type="button" class="button button-small wpe-fpc-copy-placeholder" data-placeholder="{{reset_url}}" style="margin: 2px;">{{reset_url}}</button>
                                    <button type="button" class="button button-small wpe-fpc-copy-placeholder" data-placeholder="{{reason}}" style="margin: 2px;">{{reason}}</button>
                                </div>
                            </div>

                            <!-- WYSIWYG Editor -->
                            <?php
                            wp_editor(
                                $email_template,
                                'wpe_fpc_email_template',
                                [
                                    'textarea_name' => 'email_template',
                                    'textarea_rows' => 15,
                                    'teeny'         => false,
                                    'media_buttons' => false,
                                    'tinymce'       => [
                                        'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,backcolor,removeformat,code',
                                        'toolbar2' => '',
                                    ],
                                ]
                            );
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to save settings
     *
     * @return void
     */
    public static function ajax_save_settings(): void {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpe_fpc_settings')) {
            wp_send_json_error(['message' => __('Security check failed.', 'wp-easy-force-password-change')]);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'wp-easy-force-password-change')]);
        }

        // Get settings
        $enabled               = isset($_POST['enabled']) ? rest_sanitize_boolean($_POST['enabled']) : false;
        $disable_default_email = isset($_POST['disable_default_email']) ? rest_sanitize_boolean($_POST['disable_default_email']) : false;
        $custom_css            = isset($_POST['custom_css']) ? self::sanitize_css(wp_unslash($_POST['custom_css'])) : '';

        // Save settings
        update_option(self::OPTION_ENABLED, $enabled);
        update_option(self::OPTION_DISABLE_DEFAULT_EMAIL, $disable_default_email);
        update_option(self::OPTION_CUSTOM_CSS, $custom_css);

        wp_send_json_success([
            'message' => __('Settings saved successfully.', 'wp-easy-force-password-change'),
        ]);
    }

    /**
     * AJAX handler to reset CSS to default
     *
     * @return void
     */
    public static function ajax_reset_css(): void {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpe_fpc_settings')) {
            wp_send_json_error(['message' => __('Security check failed.', 'wp-easy-force-password-change')]);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'wp-easy-force-password-change')]);
        }

        // Reset CSS to default (user-defined default if exists, otherwise hardcoded default)
        $user_default = get_option(self::OPTION_USER_DEFAULT_CSS, '');
        $default_css = !empty($user_default) ? $user_default : self::get_default_css();
        update_option(self::OPTION_CUSTOM_CSS, $default_css);

        wp_send_json_success([
            'message' => __('CSS reset to default successfully.', 'wp-easy-force-password-change'),
            'css'     => $default_css,
        ]);
    }

    /**
     * AJAX handler to save current CSS as new default
     *
     * @return void
     */
    public static function ajax_save_as_default(): void {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpe_fpc_settings')) {
            wp_send_json_error(['message' => __('Security check failed.', 'wp-easy-force-password-change')]);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'wp-easy-force-password-change')]);
        }

        // Get and sanitize CSS
        $custom_css = isset($_POST['custom_css']) ? self::sanitize_css(wp_unslash($_POST['custom_css'])) : '';

        if (empty($custom_css)) {
            wp_send_json_error(['message' => __('CSS cannot be empty.', 'wp-easy-force-password-change')]);
        }

        // Save as user-defined default
        update_option(self::OPTION_USER_DEFAULT_CSS, $custom_css);

        wp_send_json_success([
            'message' => __('Current CSS saved as new default successfully.', 'wp-easy-force-password-change'),
        ]);
    }

    /**
     * AJAX handler to save email template
     *
     * @return void
     */
    public static function ajax_save_email_template(): void {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpe_fpc_settings')) {
            wp_send_json_error(['message' => __('Security check failed.', 'wp-easy-force-password-change')]);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'wp-easy-force-password-change')]);
        }

        // Get and sanitize email template
        $email_template = isset($_POST['email_template']) ? self::sanitize_email_template(wp_unslash($_POST['email_template'])) : '';

        if (empty($email_template)) {
            wp_send_json_error(['message' => __('Email template cannot be empty.', 'wp-easy-force-password-change')]);
        }

        // Save email template
        update_option(self::OPTION_EMAIL_TEMPLATE, $email_template);

        wp_send_json_success([
            'message' => __('Email template saved successfully.', 'wp-easy-force-password-change'),
        ]);
    }
}
