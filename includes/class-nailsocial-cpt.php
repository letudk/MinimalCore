<?php
/**
 * Handles Custom Post Types and Taxonomies
 */

if (!defined('ABSPATH')) {
    exit;
}

class NailSocial_CPT {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('init', [$this, 'register_meta_fields']);
        
        // User Profile Hooks
        add_action('show_user_profile', [$this, 'add_custom_user_fields']);
        add_action('edit_user_profile', [$this, 'add_custom_user_fields']);
        add_action('personal_options_update', [$this, 'save_custom_user_fields']);
        add_action('personal_options_update', [$this, 'save_custom_user_fields']);
        add_action('edit_user_profile_update', [$this, 'save_custom_user_fields']);

        // Meta Box Hooks
        add_action('add_meta_boxes', [$this, 'add_custom_meta_boxes']);
        add_action('save_post', [$this, 'save_custom_meta_boxes']);
    }

    public function add_custom_meta_boxes() {
        add_meta_box('nail_art_details', 'Nail Art Details', [$this, 'render_nail_art_meta_box'], 'nail_art', 'normal', 'high');
        add_meta_box('salon_details', 'Salon Details', [$this, 'render_salon_meta_box'], 'salon', 'normal', 'high');
        add_meta_box('reel_details', 'Reel Details', [$this, 'render_reel_meta_box'], 'reel', 'normal', 'high');
        add_meta_box('collection_details', 'Collection Details', [$this, 'render_collection_meta_box'], 'collection', 'normal', 'high');
        add_meta_box('plan_details', 'Plan Details', [$this, 'render_plan_meta_box'], 'subscription_plan', 'normal', 'high');
    }

    public function render_nail_art_meta_box($post) {
        wp_nonce_field('nailsocial_save_meta', 'nailsocial_meta_nonce');
        $likes = get_post_meta($post->ID, 'likes_count', true) ?: 0;
        $views = get_post_meta($post->ID, 'views_count', true) ?: 0;
        $comments = get_post_meta($post->ID, 'comments_count', true) ?: 0;
        $location = get_post_meta($post->ID, 'location', true);
        ?>
        <div style="display: flex; gap: 20px;">
            <p><label>Likes:</label><br><input type="number" name="ns_likes" value="<?php echo esc_attr($likes); ?>"></p>
            <p><label>Views:</label><br><input type="number" name="ns_views" value="<?php echo esc_attr($views); ?>"></p>
            <p><label>Comments:</label><br><input type="number" name="ns_comments" value="<?php echo esc_attr($comments); ?>"></p>
        </div>
        <p><label>Location:</label><br><input type="text" name="ns_location" value="<?php echo esc_attr($location); ?>" class="large-text"></p>
        <?php
    }

    public function render_salon_meta_box($post) {
        wp_nonce_field('nailsocial_save_meta', 'nailsocial_meta_nonce');
        $address = get_post_meta($post->ID, 'address', true);
        $level = get_post_meta($post->ID, 'level', true);
        $rating = get_post_meta($post->ID, 'rating', true);
        $reviews = get_post_meta($post->ID, 'reviews_count', true) ?: 0;
        ?>
        <p><label>Address:</label><br><input type="text" name="salon_address" value="<?php echo esc_attr($address); ?>" class="large-text"></p>
        <div style="display: flex; gap: 20px;">
            <p><label>Rating (0-5):</label><br><input type="number" step="0.1" name="salon_rating" value="<?php echo esc_attr($rating); ?>"></p>
            <p><label>Reviews Count:</label><br><input type="number" name="salon_reviews" value="<?php echo esc_attr($reviews); ?>"></p>
        </div>
        <p>
            <label>Subscription Level:</label><br>
            <select name="salon_level">
                <option value="Free" <?php selected($level, 'Free'); ?>>Free</option>
                <option value="Pro" <?php selected($level, 'Pro'); ?>>Pro</option>
                <option value="Elite" <?php selected($level, 'Elite'); ?>>Elite</option>
            </select>
        </p>
        <?php
    }

    public function render_reel_meta_box($post) {
        wp_nonce_field('nailsocial_save_meta', 'nailsocial_meta_nonce');
        $video_url = get_post_meta($post->ID, 'video_url', true);
        $music = get_post_meta($post->ID, 'music_title', true);
        $likes = get_post_meta($post->ID, 'likes_count', true) ?: 0;
        $views = get_post_meta($post->ID, 'views_count', true) ?: 0;
        ?>
        <p><label>Video URL (.mp4):</label><br><input type="text" name="reel_video_url" value="<?php echo esc_attr($video_url); ?>" class="large-text"></p>
        <p><label>Music Title:</label><br><input type="text" name="reel_music_title" value="<?php echo esc_attr($music); ?>" class="large-text"></p>
        <div style="display: flex; gap: 20px;">
            <p><label>Likes:</label><br><input type="number" name="ns_likes" value="<?php echo esc_attr($likes); ?>"></p>
            <p><label>Views:</label><br><input type="number" name="ns_views" value="<?php echo esc_attr($views); ?>"></p>
        </div>
        <?php
    }

    public function render_collection_meta_box($post) {
        wp_nonce_field('nailsocial_save_meta', 'nailsocial_meta_nonce');
        $extra = get_post_meta($post->ID, 'extra_info', true);
        $gallery = get_post_meta($post->ID, 'gallery_urls', true);
        ?>
        <p><label>Subtitle / Extra Info:</label><br><input type="text" name="col_extra" value="<?php echo esc_attr($extra); ?>" class="large-text"></p>
        <p><label>Gallery URLs (JSON or comma-separated):</label><br><textarea name="col_gallery" rows="3" class="large-text"><?php echo esc_textarea(is_array($gallery) ? json_encode($gallery) : $gallery); ?></textarea></p>
        <?php
    }

    public function render_plan_meta_box($post) {
        wp_nonce_field('nailsocial_save_meta', 'nailsocial_meta_nonce');
        $price = get_post_meta($post->ID, 'price', true);
        $paypal_id = get_post_meta($post->ID, 'paypal_plan_id', true);
        ?>
        <p><label>Price Display (e.g., $19.99):</label><br><input type="text" name="plan_price" value="<?php echo esc_attr($price); ?>"></p>
        <p><label>PayPal Plan ID:</label><br><input type="text" name="plan_paypal_id" value="<?php echo esc_attr($paypal_id); ?>" class="large-text"></p>
        <?php
    }

    public function save_custom_meta_boxes($post_id) {
        if (!isset($_POST['nailsocial_meta_nonce']) || !wp_verify_nonce($_POST['nailsocial_meta_nonce'], 'nailsocial_save_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // General Social Counts
        if (isset($_POST['ns_likes'])) update_post_meta($post_id, 'likes_count', sanitize_text_field($_POST['ns_likes']));
        if (isset($_POST['ns_views'])) update_post_meta($post_id, 'views_count', sanitize_text_field($_POST['ns_views']));
        if (isset($_POST['ns_comments'])) update_post_meta($post_id, 'comments_count', sanitize_text_field($_POST['ns_comments']));
        if (isset($_POST['ns_location'])) update_post_meta($post_id, 'location', sanitize_text_field($_POST['ns_location']));

        // Salon Specific
        if (isset($_POST['salon_address'])) update_post_meta($post_id, 'address', sanitize_text_field($_POST['salon_address']));
        if (isset($_POST['salon_level'])) update_post_meta($post_id, 'level', sanitize_text_field($_POST['salon_level']));
        if (isset($_POST['salon_rating'])) update_post_meta($post_id, 'rating', sanitize_text_field($_POST['salon_rating']));
        if (isset($_POST['salon_reviews'])) update_post_meta($post_id, 'reviews_count', sanitize_text_field($_POST['salon_reviews']));

        // Reel Specific
        if (isset($_POST['reel_video_url'])) update_post_meta($post_id, 'video_url', esc_url_raw($_POST['reel_video_url']));
        if (isset($_POST['reel_music_title'])) update_post_meta($post_id, 'music_title', sanitize_text_field($_POST['reel_music_title']));

        // Collection Specific
        if (isset($_POST['col_extra'])) update_post_meta($post_id, 'extra_info', sanitize_text_field($_POST['col_extra']));
        if (isset($_POST['col_gallery'])) {
            $gallery = $_POST['col_gallery'];
            if (strpos($gallery, '[') === 0) {
                update_post_meta($post_id, 'gallery_urls', json_decode(stripslashes($gallery), true));
            } else {
                update_post_meta($post_id, 'gallery_urls', array_map('trim', explode(',', $gallery)));
            }
        }

        // Plan Specific
        if (isset($_POST['plan_price'])) update_post_meta($post_id, 'price', sanitize_text_field($_POST['plan_price']));
        if (isset($_POST['plan_paypal_id'])) update_post_meta($post_id, 'paypal_plan_id', sanitize_text_field($_POST['plan_paypal_id']));
    }

    public function register_meta_fields() {
        // Salon Meta
        register_post_meta('salon', 'address', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('salon', 'level', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('salon', 'rating', ['type' => 'number', 'single' => true, 'show_in_rest' => true]);
        
        // Reel Meta
        register_post_meta('reel', 'video_url', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('reel', 'music_title', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        
        // Plan Meta
        register_post_meta('subscription_plan', 'price', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('subscription_plan', 'paypal_plan_id', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);

        // User Meta
        register_meta('user', 'handle', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_meta('user', 'bio', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_meta('user', 'location', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_meta('user', 'instagram', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_meta('user', 'tiktok', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_meta('user', 'avatar_url', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_meta('user', 'subscription_level', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    }

    public function add_custom_user_fields($user) {
        ?>
        <h3>NailSocial Profile Information</h3>
        <table class="form-table">
            <tr>
                <th><label for="handle">Username Handle</label></th>
                <td>
                    <input type="text" name="handle" id="handle" value="<?php echo esc_attr(get_user_meta($user->ID, 'handle', true)); ?>" class="regular-text" /><br />
                    <span class="description">e.g., @jane_nails</span>
                </td>
            </tr>
            <tr>
                <th><label for="bio">Bio</label></th>
                <td>
                    <textarea name="bio" id="bio" rows="5" cols="30"><?php echo esc_textarea(get_user_meta($user->ID, 'bio', true)); ?></textarea><br />
                    <span class="description">Short description about the artist.</span>
                </td>
            </tr>
            <tr>
                <th><label for="location">Location</label></th>
                <td>
                    <input type="text" name="location" id="location" value="<?php echo esc_attr(get_user_meta($user->ID, 'location', true)); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="instagram">Instagram Link</label></th>
                <td>
                    <input type="text" name="instagram" id="instagram" value="<?php echo esc_attr(get_user_meta($user->ID, 'instagram', true)); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="tiktok">TikTok Link</label></th>
                <td>
                    <input type="text" name="tiktok" id="tiktok" value="<?php echo esc_attr(get_user_meta($user->ID, 'tiktok', true)); ?>" class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_custom_user_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) return false;
        
        update_user_meta($user_id, 'handle', $_POST['handle']);
        update_user_meta($user_id, 'bio', $_POST['bio']);
        update_user_meta($user_id, 'location', $_POST['location']);
        update_user_meta($user_id, 'instagram', $_POST['instagram']);
        update_user_meta($user_id, 'tiktok', $_POST['tiktok']);
    }

    public function register_post_types() {
        // Register Salons
        register_post_type('salon', [
            'labels' => ['name' => 'Salons', 'singular_name' => 'Salon'],
            'public' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_in_menu' => 'nailsocial-core-settings',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'menu_icon' => 'dashicons-store',
            'has_archive' => true,
            'rewrite' => ['slug' => 'salons'],
        ]);

        register_post_type('salon_service', [
            'labels' => ['name' => 'Salon Services', 'singular_name' => 'Salon Service'],
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_in_menu' => 'nailsocial-core-settings',
            'supports' => ['title', 'custom-fields'],
            'menu_icon' => 'dashicons-hammer',
        ]);

        register_post_type('salon_booking', [
            'labels' => ['name' => 'Salon Bookings', 'singular_name' => 'Salon Booking'],
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_in_menu' => 'nailsocial-core-settings',
            'supports' => ['title', 'author', 'custom-fields'],
            'menu_icon' => 'dashicons-calendar-alt',
        ]);

        // Register Nail Art (Gallery Feed)
        register_post_type('nail_art', [
            'labels' => ['name' => 'Nail Arts', 'singular_name' => 'Nail Art'],
            'public' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_in_menu' => 'nailsocial-core-settings',
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'menu_icon' => 'dashicons-format-image',
        ]);

        // Register Reels (Videos)
        register_post_type('reel', [
            'labels' => ['name' => 'Reels', 'singular_name' => 'Reel'],
            'public' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_in_menu' => 'nailsocial-core-settings',
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'menu_icon' => 'dashicons-video-alt3',
        ]);

        // Register Collections
        register_post_type('collection', [
            'labels' => ['name' => 'Collections', 'singular_name' => 'Collection'],
            'public' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_in_menu' => 'nailsocial-core-settings',
            'supports' => ['title', 'thumbnail', 'custom-fields'],
            'menu_icon' => 'dashicons-images-alt2',
        ]);

        // Register Subscription Plans
        register_post_type('subscription_plan', [
            'labels' => ['name' => 'Subscription Plans', 'singular_name' => 'Plan'],
            'public' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_in_menu' => 'nailsocial-core-settings',
            'supports' => ['title', 'custom-fields'],
            'menu_icon' => 'dashicons-awards',
        ]);

        // Register Notifications (Private)
        register_post_type('notification', [
            'labels' => ['name' => 'Notifications', 'singular_name' => 'Notification'],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'nailsocial-core-settings',
            'supports' => ['title', 'author', 'custom-fields'],
            'menu_icon' => 'dashicons-bell',
        ]);

        // Register Payment Logs (Private)
        register_post_type('payment_log', [
            'labels' => ['name' => 'Payment Logs', 'singular_name' => 'Log'],
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => false,
            'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-media-text',
        ]);
    }

    public function register_taxonomies() {
        // Salon Categories
        register_taxonomy('salon_category', ['salon'], [
            'labels' => ['name' => 'Salon Categories', 'singular_name' => 'Category'],
            'hierarchical' => true,
            'show_in_rest' => true,
        ]);

        // Nail Art Styles
        register_taxonomy('nail_style', ['nail_art'], [
            'labels' => ['name' => 'Nail Styles', 'singular_name' => 'Style'],
            'hierarchical' => false,
            'show_in_rest' => true,
        ]);
    }
}
