<?php
/**
 * PayPal Integration Logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class NailSocial_PayPal {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', [$this, 'register_webhook_route']);
    }

    public function register_webhook_route() {
        register_rest_route('nailsocial/v1', '/paypal-webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle PayPal Webhooks
     */
    public function handle_webhook($request) {
        $payload = $request->get_json_params();
        $event_type = $payload['event_type'] ?? '';

        // Simple logging for now
        $this->log_transaction($payload);

        switch ($event_type) {
            case 'PAYMENT.SALE.COMPLETED':
            case 'BILLING.SUBSCRIPTION.CREATED':
                return $this->process_successful_payment($payload);
            
            case 'BILLING.SUBSCRIPTION.CANCELLED':
            case 'BILLING.SUBSCRIPTION.EXPIRED':
                return $this->process_cancellation($payload);

            default:
                return ['status' => 'ignored'];
        }
    }

    private function process_successful_payment($payload) {
        // Logic to find Salon by custom_id or user email and upgrade level
        // For now, return success
        return ['status' => 'success', 'message' => 'Payment processed'];
    }

    private function process_cancellation($payload) {
        // Downgrade salon level to 'Free'
        return ['status' => 'success', 'message' => 'Subscription updated'];
    }

    private function log_transaction($payload) {
        wp_insert_post([
            'post_type' => 'payment_log',
            'post_title' => 'PayPal Event: ' . ($payload['event_type'] ?? 'Unknown'),
            'post_content' => wp_json_encode($payload),
            'post_status' => 'private',
        ]);
    }
}
