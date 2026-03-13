<?php
/**
 * Handles Global Plugin Settings (Ads, PayPal)
 */

if (!defined('ABSPATH')) {
    exit;
}

class NailSocial_Settings {
    private static $instance = null;

    private $settings_tabs = [
        'general' => [
            'label' => 'General',
            'group' => 'nailsocial_settings_general',
            'options' => [
                'nailsocial_env' => 'sanitize_environment',
                'nailsocial_api_token' => 'sanitize_text_field',
                'nailsocial_service_user_id' => 'absint',
            ],
        ],
        'ads' => [
            'label' => 'Ads Management',
            'group' => 'nailsocial_settings_ads',
            'options' => [
                'nailsocial_adsense_id' => 'sanitize_text_field',
                'nailsocial_ad_header' => 'wp_kses_post',
                'nailsocial_ad_feed' => 'wp_kses_post',
                'nailsocial_ad_sidebar' => 'wp_kses_post',
                'nailsocial_ad_content' => 'wp_kses_post',
            ],
        ],
        'skeleton' => [
            'label' => 'Skeleton Loader',
            'group' => 'nailsocial_settings_skeleton',
            'options' => [
                'nailsocial_skeleton_enabled' => 'sanitize_checkbox',
                'nailsocial_skeleton_type' => 'sanitize_skeleton_type',
            ],
        ],
        'paypal' => [
            'label' => 'PayPal Config',
            'group' => 'nailsocial_settings_paypal',
            'options' => [
                'nailsocial_paypal_client_id' => 'sanitize_text_field',
                'nailsocial_paypal_secret' => 'sanitize_text_field',
                'nailsocial_paypal_webhook_id' => 'sanitize_text_field',
            ],
        ],
        'bank' => [
            'label' => 'Payment Info (Bank)',
            'group' => 'nailsocial_settings_bank',
            'options' => [
                'nailsocial_bank_name' => 'sanitize_text_field',
                'nailsocial_bank_account' => 'sanitize_text_field',
                'nailsocial_bank_holder' => 'sanitize_text_field',
            ],
        ],
    ];

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page'], 99);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_settings_page() {
        add_menu_page(
            'NailSocial Settings',
            'NailSocial',
            'manage_options',
            'nailsocial-core-settings',
            [$this, 'render_settings_page'],
            'dashicons-admin-generic',
            30
        );

        add_submenu_page(
            'nailsocial-core-settings',
            'General Settings',
            'Settings',
            'manage_options',
            'nailsocial-core-settings',
            [$this, 'render_settings_page']
        );

        add_options_page(
            'NailSocial Config',
            'NailSocial',
            'manage_options',
            'nailsocial-core-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        foreach ($this->settings_tabs as $tab) {
            foreach ($tab['options'] as $option_name => $sanitize_callback) {
                register_setting($tab['group'], $option_name, [
                    'type' => 'string',
                    'sanitize_callback' => [$this, $sanitize_callback],
                    'default' => $this->get_default_option_value($option_name),
                ]);
            }
        }
    }

    public function sanitize_environment($value) {
        return in_array($value, ['development', 'production'], true) ? $value : 'development';
    }

    public function sanitize_checkbox($value) {
        return !empty($value) ? '1' : '0';
    }

    public function sanitize_skeleton_type($value) {
        return in_array($value, ['pulse', 'shimmer'], true) ? $value : 'pulse';
    }

    private function get_default_option_value($option_name) {
        $defaults = [
            'nailsocial_env' => 'development',
            'nailsocial_api_token' => '',
            'nailsocial_service_user_id' => 0,
            'nailsocial_paypal_client_id' => '',
            'nailsocial_paypal_secret' => '',
            'nailsocial_paypal_webhook_id' => '',
            'nailsocial_adsense_id' => '',
            'nailsocial_ad_header' => '',
            'nailsocial_ad_feed' => '',
            'nailsocial_ad_sidebar' => '',
            'nailsocial_ad_content' => '',
            'nailsocial_skeleton_enabled' => '0',
            'nailsocial_skeleton_type' => 'pulse',
            'nailsocial_bank_name' => '',
            'nailsocial_bank_account' => '',
            'nailsocial_bank_holder' => '',
        ];

        return $defaults[$option_name] ?? '';
    }

    private function get_active_tab() {
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
        return isset($this->settings_tabs[$tab]) ? $tab : 'general';
    }

    public function render_settings_page() {
        $active_tab = $this->get_active_tab();
        $active_group = $this->settings_tabs[$active_tab]['group'];
        ?>
        <div class="wrap">
            <h1>NailSocial Core Settings</h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($this->settings_tabs as $tab_key => $tab_config) : ?>
                    <a href="?page=nailsocial-core-settings&tab=<?php echo esc_attr($tab_key); ?>" class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_config['label']); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <form method="post" action="options.php">
                <?php
                settings_fields($active_group);
                ?>
                <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr(admin_url('admin.php?page=nailsocial-core-settings&tab=' . $active_tab)); ?>">

                <?php if ($active_tab === 'general') : ?>
                    <h2>General Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Site Environment</th>
                            <td>
                                <select name="nailsocial_env">
                                    <option value="development" <?php selected(get_option('nailsocial_env', 'development'), 'development'); ?>>Development</option>
                                    <option value="production" <?php selected(get_option('nailsocial_env', 'development'), 'production'); ?>>Production</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Shared API Token</th>
                            <td>
                                <input type="text" name="nailsocial_api_token" value="<?php echo esc_attr(get_option('nailsocial_api_token', '')); ?>" class="regular-text">
                                <p class="description">Use the same value as <code>WP_API_TOKEN</code> in the Next.js app so protected write requests can be authenticated.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Service User ID</th>
                            <td>
                                <input type="number" min="1" name="nailsocial_service_user_id" value="<?php echo esc_attr((string) get_option('nailsocial_service_user_id', 0)); ?>" class="small-text">
                                <p class="description">Optional. Admin user ID used for token-based create/update requests when no logged-in WP user is present.</p>
                            </td>
                        </tr>
                    </table>
                <?php elseif ($active_tab === 'ads') : ?>
                    <h2>AdSense & Ad Slots</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">AdSense Client ID (ca-pub-xxx)</th>
                            <td><input type="text" name="nailsocial_adsense_id" value="<?php echo esc_attr(get_option('nailsocial_adsense_id', '')); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">Global Header Ad</th>
                            <td><textarea name="nailsocial_ad_header" rows="5" class="large-text"><?php echo esc_textarea(get_option('nailsocial_ad_header', '')); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row">Feed Inline Ad</th>
                            <td><textarea name="nailsocial_ad_feed" rows="5" class="large-text"><?php echo esc_textarea(get_option('nailsocial_ad_feed', '')); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row">Sidebar Sticky Ad</th>
                            <td><textarea name="nailsocial_ad_sidebar" rows="5" class="large-text"><?php echo esc_textarea(get_option('nailsocial_ad_sidebar', '')); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row">Content Inline Ad</th>
                            <td><textarea name="nailsocial_ad_content" rows="5" class="large-text"><?php echo esc_textarea(get_option('nailsocial_ad_content', '')); ?></textarea></td>
                        </tr>
                    </table>
                <?php elseif ($active_tab === 'skeleton') : ?>
                    <h2>Skeleton Loader Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Skeleton Loader</th>
                            <td>
                                <input type="hidden" name="nailsocial_skeleton_enabled" value="0">
                                <input type="checkbox" name="nailsocial_skeleton_enabled" value="1" <?php checked(get_option('nailsocial_skeleton_enabled', '0'), '1'); ?>>
                                <p class="description">Show skeleton loader while content is fetching on frontend.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Skeleton Type</th>
                            <td>
                                <select name="nailsocial_skeleton_type">
                                    <option value="pulse" <?php selected(get_option('nailsocial_skeleton_type', 'pulse'), 'pulse'); ?>>Pulse Animation</option>
                                    <option value="shimmer" <?php selected(get_option('nailsocial_skeleton_type', 'pulse'), 'shimmer'); ?>>Shimmer Effect</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                <?php elseif ($active_tab === 'paypal') : ?>
                    <h2>PayPal Configuration</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">PayPal Client ID</th>
                            <td><input type="text" name="nailsocial_paypal_client_id" value="<?php echo esc_attr(get_option('nailsocial_paypal_client_id', '')); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">PayPal Secret</th>
                            <td><input type="password" name="nailsocial_paypal_secret" value="<?php echo esc_attr(get_option('nailsocial_paypal_secret', '')); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">Webhook ID</th>
                            <td><input type="text" name="nailsocial_paypal_webhook_id" value="<?php echo esc_attr(get_option('nailsocial_paypal_webhook_id', '')); ?>" class="regular-text"></td>
                        </tr>
                    </table>
                <?php elseif ($active_tab === 'bank') : ?>
                    <h2>Bank Payment Information</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Bank Name</th>
                            <td><input type="text" name="nailsocial_bank_name" value="<?php echo esc_attr(get_option('nailsocial_bank_name', '')); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">Account Number</th>
                            <td><input type="text" name="nailsocial_bank_account" value="<?php echo esc_attr(get_option('nailsocial_bank_account', '')); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">Account Holder</th>
                            <td><input type="text" name="nailsocial_bank_holder" value="<?php echo esc_attr(get_option('nailsocial_bank_holder', '')); ?>" class="regular-text"></td>
                        </tr>
                    </table>
                <?php endif; ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
