<?php
/**
 * Plugin Name: NailSocial Core Backend
 * Description: Headless CMS backend for NailSocial Next.js project. Handles CPTs, REST API, and PayPal integration.
 * Version: 1.0.0
 * Author: Antigravity
 * Text Domain: nailsocial-core
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('NAILSOCIAL_CORE_VERSION', '1.0.0');
define('NAILSOCIAL_CORE_PATH', plugin_dir_path(__FILE__));
define('NAILSOCIAL_CORE_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class NailSocial_Core {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->register_hooks();
        $this->init_features();
    }

    public static function activate() {
        update_option('classic-editor-replace', 'classic');
        update_option('classic-editor-allow-users', 'disallow');
        NailSocial_DB::install_or_upgrade();
    }

    private function includes() {
        require_once NAILSOCIAL_CORE_PATH . 'includes/class-nailsocial-db.php';
        require_once NAILSOCIAL_CORE_PATH . 'includes/class-nailsocial-storage.php';
        require_once NAILSOCIAL_CORE_PATH . 'includes/class-nailsocial-video-processing.php';
        require_once NAILSOCIAL_CORE_PATH . 'includes/class-nailsocial-settings.php';
        require_once NAILSOCIAL_CORE_PATH . 'includes/class-nailsocial-cpt.php';
        require_once NAILSOCIAL_CORE_PATH . 'includes/class-nailsocial-api.php';
        require_once NAILSOCIAL_CORE_PATH . 'includes/class-nailsocial-paypal.php';
    }

    private function register_hooks() {
        add_filter('use_block_editor_for_post_type', [$this, 'disable_block_editor'], 10, 2);
        add_filter('use_widgets_block_editor', '__return_false');
        add_filter('gutenberg_use_widgets_block_editor', '__return_false');
    }

    public function disable_block_editor($use_block_editor, $post_type) {
        return false;
    }

    public function init_features() {
        NailSocial_DB::maybe_upgrade();
        // Initialize child classes
        NailSocial_Storage::get_instance();
        NailSocial_Video_Processing::get_instance();
        NailSocial_Settings::get_instance();
        NailSocial_CPT::get_instance();
        NailSocial_API::get_instance();
        NailSocial_PayPal::get_instance();
    }
}

register_activation_hook(__FILE__, ['NailSocial_Core', 'activate']);

// Instantiate
NailSocial_Core::get_instance();
