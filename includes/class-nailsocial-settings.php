<?php
/**
 * Handles Global Plugin Settings (Ads, PayPal)
 */

if (!defined('ABSPATH')) {
    exit;
}

class NailSocial_Settings {
    private static $instance = null;

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
        // Main Menu
        add_menu_page(
            'NailSocial Settings',
            'NailSocial',
            'manage_options',
            'nailsocial-core-settings',
            [$this, 'render_settings_page'],
            'dashicons-admin-generic',
            30
        );
        // Add a submenu with the SAME slug as parent to make it the default first item
        add_submenu_page(
            'nailsocial-core-settings',
            'General Settings',
            'Settings',
            'manage_options',
            'nailsocial-core-settings',
            [$this, 'render_settings_page']
        );
        // Fallback under Settings
        add_options_page(
            'NailSocial Config',
            'NailSocial',
            'manage_options',
            'nailsocial-core-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        // General
        register_setting('nailsocial_settings', 'nailsocial_env');

        // PayPal
        register_setting('nailsocial_settings', 'nailsocial_paypal_client_id');
        register_setting('nailsocial_settings', 'nailsocial_paypal_secret');
        register_setting('nailsocial_settings', 'nailsocial_paypal_webhook_id');
        
        // Ads
        register_setting('nailsocial_settings', 'nailsocial_adsense_id');
        register_setting('nailsocial_settings', 'nailsocial_ad_header');
        register_setting('nailsocial_settings', 'nailsocial_ad_feed');
        register_setting('nailsocial_settings', 'nailsocial_ad_sidebar');
        register_setting('nailsocial_settings', 'nailsocial_ad_content');

        // Skeleton
        register_setting('nailsocial_settings', 'nailsocial_skeleton_enabled');
        register_setting('nailsocial_settings', 'nailsocial_skeleton_type');

        // Bank Info
        register_setting('nailsocial_settings', 'nailsocial_bank_name');
        register_setting('nailsocial_settings', 'nailsocial_bank_account');
        register_setting('nailsocial_settings', 'nailsocial_bank_holder');
    }

    public function render_settings_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1>NailSocial Core Settings</h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=nailsocial-core-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">General</a>
                <a href="?page=nailsocial-core-settings&tab=ads" class="nav-tab <?php echo $active_tab == 'ads' ? 'nav-tab-active' : ''; ?>">Ads Management</a>
                <a href="?page=nailsocial-core-settings&tab=skeleton" class="nav-tab <?php echo $active_tab == 'skeleton' ? 'nav-tab-active' : ''; ?>">Skeleton Loader</a>
                <a href="?page=nailsocial-core-settings&tab=paypal" class="nav-tab <?php echo $active_tab == 'paypal' ? 'nav-tab-active' : ''; ?>">PayPal Config</a>
                <a href="?page=nailsocial-core-settings&tab=bank" class="nav-tab <?php echo $active_tab == 'bank' ? 'nav-tab-active' : ''; ?>">Payment Info (Bank)</a>
            </h2>

            <form method="post" action="options.php">
                <?php
                settings_fields('nailsocial_settings');
                
                if ($active_tab == 'general') : ?>
                    <h2>General Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Site Environment</th>
                            <td>
                                <select name="nailsocial_env">
                                    <option value="development" <?php selected(get_option('nailsocial_env'), 'development'); ?>>Development</option>
                                    <option value="production" <?php selected(get_option('nailsocial_env'), 'production'); ?>>Production</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                <?php elseif ($active_tab == 'ads') : ?>
                    <h2>AdSense & Ad Slots</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">AdSense Client ID (ca-pub-xxx)</th>
                            <td><input type="text" name="nailsocial_adsense_id" value="<?php echo esc_attr(get_option('nailsocial_adsense_id')); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">Global Header Ad</th>
                            <td><textarea name="nailsocial_ad_header" rows="5" class="large-text"><?php echo esc_textarea(get_option('nailsocial_ad_header')); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row">Feed Inline Ad</th>
                            <td><textarea name="nailsocial_ad_feed" rows="5" class="large-text"><?php echo esc_textarea(get_option('nailsocial_ad_feed')); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row">Sidebar Sticky Ad</th>
                            <td><textarea name="nailsocial_ad_sidebar" rows="5" class="large-text"><?php echo esc_textarea(get_option('nailsocial_ad_sidebar')); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row">Content Inline Ad</th>
                            <td><textarea name="nailsocial_ad_content" rows="5" class="large-text"><?php echo esc_textarea(get_option('nailsocial_ad_content')); ?></textarea></td>
                        </tr>
                    </table>
                <?php elseif ($active_tab == 'skeleton') : ?>
                    <h2>Skeleton Loader Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Skeleton Loader</th>
                            <td>
                                <input type="checkbox" name="nailsocial_skeleton_enabled" value="1" <?php checked(get_option('nailsocial_skeleton_enabled'), '1'); ?>>
                                <p class="description">Show skeleton loader while content is fetching on frontend.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Skeleton Type</th>
                            <td>
                                <select name="nailsocial_skeleton_type">
                                    <option value="pulse" <?php selected(get_option('nailsocial_skeleton_type'), 'pulse'); ?>>Pulse Animation</option>
                                    <option value="shimmer" <?php selected(get_option('nailsocial_skeleton_type'), 'shimmer'); ?>>Shimmer Effect</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                <?php elseif ($active_tab == 'paypal') : ?>
                    <h2>PayPal Configuration</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">PayPal Client ID</th>
                            <td><input type="text" name="nailsocial_paypal_client_id" value="<?php echo esc_attr(get_option('nailsocial_paypal_client_id')); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">PayPal Secret</th>
                            <td><input type="password" name="nailsocial_paypal_secret" value="<?php echo esc_attr(get_option('nailsocial_paypal_secret')); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">Webhook ID</th>
                            <td><input type="text" name="nailsocial_paypal_webhook_id" value="<?php echo esc_attr(get_option('nailsocial_paypal_webhook_id')); ?>" class="regular-text"></td>
                        </tr>
                    </table>
                <?php elseif ($active_tab == 'bank') : ?>
                    <h2>Bank Payment Information</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Bank Name</th>
                            <td><input type="text" name="nailsocial_bank_name" value="<?php echo esc_attr(get_option('nailsocial_bank_name')); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">Account Number</th>
                            <td><input type="text" name="nailsocial_bank_account" value="<?php echo esc_attr(get_option('nailsocial_bank_account')); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">Account Holder</th>
                            <td><input type="text" name="nailsocial_bank_holder" value="<?php echo esc_attr(get_option('nailsocial_bank_holder')); ?>" class="regular-text"></td>
                        </tr>
                    </table>
                <?php endif; ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
