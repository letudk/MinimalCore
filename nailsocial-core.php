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
        $this->init_features();
    }

    private function includes() {
        require_once NAILSOCIAL_CORE_PATH . 'includes/class-nailsocial-settings.php';
        require_once NAILSOCIAL_CORE_PATH . 'includes/class-nailsocial-cpt.php';
        require_once NAILSOCIAL_CORE_PATH . 'includes/class-nailsocial-api.php';
        require_once NAILSOCIAL_CORE_PATH . 'includes/class-nailsocial-paypal.php';
    }

    public function init_features() {
        // Initialize child classes
        NailSocial_Settings::get_instance();
        NailSocial_CPT::get_instance();
        NailSocial_API::get_instance();
        NailSocial_PayPal::get_instance();
    }
}

// Instantiate
NailSocial_Core::get_instance();
