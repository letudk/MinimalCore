<?php
/**
 * Custom REST API Endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class NailSocial_API {
    private static $instance = null;
    private $namespace = 'nailsocial/v1';

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        // GET /ads
        register_rest_route($this->namespace, '/ads', [
            'methods' => 'GET',
            'callback' => [$this, 'get_ads'],
            'permission_callback' => '__return_true',
        ]);

        // GET /subscription-plans
        register_rest_route($this->namespace, '/subscription-plans', [
            'methods' => 'GET',
            'callback' => [$this, 'get_subscription_plans'],
            'permission_callback' => '__return_true',
        ]);

        // GET /salons
        register_rest_route($this->namespace, '/salons', [
            'methods' => 'GET',
            'callback' => [$this, 'get_salons'],
            'permission_callback' => '__return_true',
        ]);

        // GET /reels
        register_rest_route($this->namespace, '/reels', [
            'methods' => 'GET',
            'callback' => [$this, 'get_reels'],
            'permission_callback' => '__return_true',
        ]);

        // GET /reels/(?P<id>\d+)
        register_rest_route($this->namespace, '/reels/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_reel'],
            'permission_callback' => '__return_true',
        ]);

        // GET /collections
        register_rest_route($this->namespace, '/collections', [
            'methods' => 'GET',
            'callback' => [$this, 'get_collections'],
            'permission_callback' => '__return_true',
        ]);

        // POST /collections
        register_rest_route($this->namespace, '/collections', [
            'methods' => 'POST',
            'callback' => [$this, 'create_collection'],
            'permission_callback' => [$this, 'can_manage_content'],
        ]);

        // GET /posts
        register_rest_route($this->namespace, '/posts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_posts_feed'],
            'permission_callback' => '__return_true',
        ]);

        // POST /posts
        register_rest_route($this->namespace, '/posts', [
            'methods' => 'POST',
            'callback' => [$this, 'create_post'],
            'permission_callback' => [$this, 'can_manage_content'],
        ]);

        // GET /blogs
        register_rest_route($this->namespace, '/blogs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_blogs'],
            'permission_callback' => '__return_true',
        ]);

        // GET /blogs/(?P<id_or_slug>[\w-]+)
        register_rest_route($this->namespace, '/blogs/(?P<id_or_slug>[\w-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_blog'],
            'permission_callback' => '__return_true',
        ]);

        // GET /users/(?P<id_or_slug>[\w-]+)
        register_rest_route($this->namespace, '/users/(?P<id_or_slug>[\w-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_profile'],
            'permission_callback' => '__return_true',
        ]);

        // POST /users/(?P<id>\d+)
        register_rest_route($this->namespace, '/users/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'update_user_profile'],
            'permission_callback' => [$this, 'can_manage_content'],
        ]);

        // POST /social-auth
        register_rest_route($this->namespace, '/social-auth', [
            'methods' => 'POST',
            'callback' => [$this, 'social_auth'],
            'permission_callback' => '__return_true',
        ]);

        // POST /login
        register_rest_route($this->namespace, '/login', [
            'methods' => 'POST',
            'callback' => [$this, 'login'],
            'permission_callback' => '__return_true',
        ]);

        // POST /follow
        register_rest_route($this->namespace, '/follow', [
            'methods' => 'POST',
            'callback' => [$this, 'toggle_follow'],
            'permission_callback' => [$this, 'is_user_logged_in'],
        ]);

        // GET /notifications
        register_rest_route($this->namespace, '/notifications', [
            'methods' => 'GET',
            'callback' => [$this, 'get_notifications'],
            'permission_callback' => [$this, 'is_user_logged_in'],
        ]);

        // POST /notifications/read
        register_rest_route($this->namespace, '/notifications/read', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_notifications_read'],
            'permission_callback' => [$this, 'is_user_logged_in'],
        ]);

        // GET /salons/(?P<id_or_slug>[\w-]+)
        register_rest_route($this->namespace, '/salons/(?P<id_or_slug>[\w-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_salon'],
            'permission_callback' => '__return_true',
        ]);

        // POST /salons
        register_rest_route($this->namespace, '/salons', [
            'methods' => 'POST',
            'callback' => [$this, 'create_salon'],
            'permission_callback' => [$this, 'can_manage_content'],
        ]);

        // GET /reels/(?P<id>\d+)
        register_rest_route($this->namespace, '/reels/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_reel'],
            'permission_callback' => '__return_true',
        ]);

        // GET /posts/(?P<id>\d+)
        register_rest_route($this->namespace, '/posts/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_post'],
            'permission_callback' => '__return_true',
        ]);

        // GET /collections/(?P<id>\d+)
        register_rest_route($this->namespace, '/collections/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_collection'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function login($request) {
        $params = $request->get_json_params();
        $username = $params['email'];
        $password = $params['password'];

        if (empty($username) || empty($password)) {
            return new WP_Error('missing_params', 'Email and password are required', ['status' => 400]);
        }

        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            return new WP_Error('invalid_login', 'Invalid email or password', ['status' => 401]);
        }

        return [
            'id' => (string)$user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'role' => current($user->roles),
            'subscription_level' => get_user_meta($user->ID, 'subscription_level', true) ?: 'free',
        ];
    }

    public function is_user_logged_in() {
        return get_current_user_id() !== 0;
    }

    private function has_valid_api_token($request = null) {
        $configured = (string) get_option('nailsocial_api_token', '');
        if ($configured === '') return false;

        $header = '';
        if ($request && method_exists($request, 'get_header')) {
            $header = (string) $request->get_header('authorization');
            if (!$header) {
                $header = (string) $request->get_header('x-nailsocial-token');
            }
        }

        if (!$header && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = (string) $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (!$header && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (!$header) return false;

        $token = stripos($header, 'Bearer ') === 0 ? substr($header, 7) : $header;
        return hash_equals($configured, trim($token));
    }

    public function can_manage_content($request) {
        return $this->is_user_logged_in() || $this->has_valid_api_token($request);
    }

    private function get_service_user_id() {
        $configured = (int) get_option('nailsocial_service_user_id', 0);
        if ($configured > 0) return $configured;

        $admins = get_users([
            'role' => 'administrator',
            'number' => 1,
            'fields' => 'ID',
        ]);

        if (!empty($admins)) {
            return (int) $admins[0];
        }

        return 1;
    }

    private function assume_service_user_if_token($request) {
        if (get_current_user_id() !== 0) return get_current_user_id();
        if (!$this->has_valid_api_token($request)) return 0;

        $service_user_id = $this->get_service_user_id();
        if ($service_user_id > 0) {
            wp_set_current_user($service_user_id);
        }

        return get_current_user_id();
    }

    private function resolve_author_id($requested_author_id, $default_author_id) {
        $requested_author_id = (int) $requested_author_id;
        if ($requested_author_id > 0 && get_user_by('id', $requested_author_id)) {
            return $requested_author_id;
        }

        $default_author_id = (int) $default_author_id;
        if ($default_author_id > 0) {
            return $default_author_id;
        }

        return $this->get_service_user_id();
    }

    /**
     * Get global ad configurations
     */
    public function get_ads() {
        return [
            'global_header'  => get_option('nailsocial_ad_header', ''),
            'feed_inline'    => get_option('nailsocial_ad_feed', ''),
            'sidebar_sticky' => get_option('nailsocial_ad_sidebar', ''),
            'content_inline' => get_option('nailsocial_ad_content', ''),
        ];
    }

    /**
     * Get formatted subscription plans
     */
    public function get_subscription_plans() {
        $plans = get_posts(['post_type' => 'subscription_plan', 'numberposts' => -1]);
        $formatted = [];

        foreach ($plans as $post) {
            $formatted[] = [
                'id' => $post->post_name,
                'name' => $post->post_title,
                'price' => get_post_meta($post->ID, 'price', true) ?: '$0',
                'period' => get_post_meta($post->ID, 'period', true) ?: 'forever',
                'recommended' => get_post_meta($post->ID, 'recommended', true) === '1',
                'buttonText' => get_post_meta($post->ID, 'button_text', true) ?: 'Select Plan',
                'paypal_plan_id' => get_post_meta($post->ID, 'paypal_plan_id', true),
                'features' => $this->get_plan_features($post->ID),
            ];
        }

        return [
            'plans' => $formatted,
            'paypal_client_id' => get_option('nailsocial_paypal_client_id', ''),
        ];
    }

    private function get_plan_features($post_id) {
        // Implementation for ACF Repeater or simple list
        $features_raw = get_post_meta($post_id, 'features', true);
        if (is_array($features_raw)) return $features_raw;
        
        // Mock fallback if empty
        return [
            ['name' => 'Basic profile', 'included' => true],
            ['name' => 'Booking button', 'included' => false],
        ];
    }

    /**
     * Get formatted salons with sorting by level
     */
    public function get_salons($request) {
        $args = [
            'post_type' => 'salon',
            'posts_per_page' => 20,
            'meta_key' => 'level',
            'orderby' => 'meta_value',
            'order' => 'DESC', // Elite/Pro first
        ];

        if ($request['slug']) {
            $args['name'] = $request['slug'];
        }

        $salons = get_posts($args);
        $response = [];

        foreach ($salons as $salon) {
            $image = get_the_post_thumbnail_url($salon->ID, 'large');
            $owner_id = (int) $salon->post_author;
            $response[] = [
                'id' => (string)$salon->ID,
                'slug' => $salon->post_name,
                'name' => $salon->post_title,
                'owner_id' => (string) $owner_id,
                'owner' => get_the_author_meta('display_name', $owner_id),
                'level' => get_post_meta($salon->ID, 'level', true) ?: 'Free',
                'address' => get_post_meta($salon->ID, 'address', true),
                'city' => get_post_meta($salon->ID, 'city', true),
                'country' => get_post_meta($salon->ID, 'country', true),
                'rating' => (float)get_post_meta($salon->ID, 'rating', true) ?: 0,
                'reviews' => (int)get_post_meta($salon->ID, 'reviews_count', true) ?: 0,
                'image' => $image ?: (get_post_meta($salon->ID, 'cover_url', true) ?: ''),
                'logoUrl' => get_post_meta($salon->ID, 'logo_url', true) ?: '',
                'coverUrl' => get_post_meta($salon->ID, 'cover_url', true) ?: '',
                'phone' => get_post_meta($salon->ID, 'phone', true) ?: '',
                'website' => get_post_meta($salon->ID, 'website', true) ?: '',
                'category' => get_post_meta($salon->ID, 'category', true) ?: 'General',
                'description' => $salon->post_content,
            ];
        }

        return $response;
    }

    /**
     * Get formatted reels
     */
    public function get_reels() {
        $reels = get_posts(['post_type' => 'reel', 'posts_per_page' => 50, 'post_status' => 'publish']);
        $response = [];

        foreach ($reels as $reel) {
            $author_id = $reel->post_author;
            $image = get_the_post_thumbnail_url($reel->ID, 'large');
            $response[] = [
                'id' => $reel->ID,
                'image' => $image ?: '',
                'videoUrl' => get_post_meta($reel->ID, 'video_url', true),
                'description' => $reel->post_content,
                'author' => get_the_author_meta('display_name', $author_id),
                'music' => get_post_meta($reel->ID, 'music_title', true) ?: 'Original Audio',
                'views' => get_post_meta($reel->ID, 'views_count', true) ?: '0',
                'likes' => get_post_meta($reel->ID, 'likes_count', true) ?: '0',
                'comments' => get_post_meta($reel->ID, 'comments_count', true) ?: '0',
                'user' => [
                    'avatar' => get_user_meta($author_id, 'avatar_url', true) ?: get_avatar_url($author_id)
                ]
            ];
        }
        return $response;
    }

    /**
     * Get formatted collections
     */
    public function get_collections() {
        $cols = get_posts(['post_type' => 'collection', 'posts_per_page' => 20, 'post_status' => 'publish']);
        $response = [];

        foreach ($cols as $col) {
            $image = get_the_post_thumbnail_url($col->ID, 'large');
            $response[] = [
                'id' => $col->ID,
                'title' => $col->post_title,
                'curator' => get_the_author_meta('display_name', $col->post_author),
                'extra' => get_post_meta($col->ID, 'extra_info', true),
                'image' => $image ?: '',
                // Formats for Next.js Gallery
                'images' => $this->get_collection_images($col->ID),
            ];
        }
        return $response;
    }

    /**
     * Create a new collection
     */
    public function create_collection($request) {
        $params = $request->get_json_params();
        $title = $params['title'];
        $curator = $params['curator'];
        $image_url = $params['coverImageUrl'];

        if (empty($title)) {
            return new WP_Error('missing_title', 'Title is required', ['status' => 400]);
        }

        $requested_author_id = !empty($params['userId']) ? (int) $params['userId'] : 0;
        $this->assume_service_user_if_token($request);
        $post_author = $this->resolve_author_id($requested_author_id, get_current_user_id());

        $post_id = wp_insert_post([
            'post_type' => 'collection',
            'post_title' => $title,
            'post_status' => 'publish',
            'post_author' => $post_author,
        ]);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        update_post_meta($post_id, 'extra_info', 'Public Collection');
        if ($image_url) {
            // In a real app, we'd sideload the image, but for now we'll store the URL
            update_post_meta($post_id, 'cover_image_url', $image_url);
        }

        return [
            'success' => true,
            'id' => (string)$post_id,
            'collection' => $this->get_collection(['id' => $post_id]),
        ];
    }

    private function get_collection_images($post_id) {
        $gallery = get_post_meta($post_id, 'gallery_urls', true);
        if (is_array($gallery)) return $gallery;
        $image = get_the_post_thumbnail_url($post_id, 'large');
        return [['url' => $image ?: '', 'likes' => 0]];
    }

    /**
     * Get posts feed (Nail Art)
     */
    public function get_posts_feed($request) {
        $args = [
            'post_type' => 'nail_art',
            'posts_per_page' => 50,
            'post_status' => 'publish'
        ];

        if ($request['user_id']) {
            $args['author'] = $request['user_id'];
        }

        $posts = get_posts($args);
        $response = [];

        foreach ($posts as $post) {
            $author_id = $post->post_author;
            $video_url = get_post_meta($post->ID, 'video_url', true);
            $response[] = [
                'id' => (string)$post->ID,
                'type' => $video_url ? 'video' : 'image',
                'imageUrl' => get_the_post_thumbnail_url($post->ID, 'large') ?: (get_post_meta($post->ID, 'image_url', true) ?: ''),
                'videoUrl' => $video_url,
                'caption' => $post->post_content,
                'likes' => (int)get_post_meta($post->ID, 'likes_count', true) ?: 0,
                'comments' => (int)get_post_meta($post->ID, 'comments_count', true) ?: 0,
                'views' => (int)get_post_meta($post->ID, 'views_count', true) ?: 0,
                'location' => get_post_meta($post->ID, 'location', true),
                'user' => [
                    'name' => get_the_author_meta('display_name', $author_id),
                    'avatar' => get_user_meta($author_id, 'avatar_url', true) ?: get_avatar_url($author_id),
                    'handle' => get_user_meta($author_id, 'handle', true) ?: '@' . get_the_author_meta('user_login', $author_id),
                ],
            ];
        }

        return $response;
    }

    public function create_post($request) {
        $params = $request->get_json_params();
        $caption = isset($params['caption']) ? wp_kses_post($params['caption']) : '';
        $image_url = isset($params['imageUrl']) ? esc_url_raw($params['imageUrl']) : '';
        $video_url = isset($params['videoUrl']) ? esc_url_raw($params['videoUrl']) : '';
        $location = isset($params['location']) ? sanitize_text_field($params['location']) : '';

        if (!$image_url && !$video_url) {
            return new WP_Error('missing_media', 'An image or video URL is required', ['status' => 400]);
        }

        $requested_author_id = !empty($params['userId']) ? (int) $params['userId'] : 0;
        $this->assume_service_user_if_token($request);
        $author_id = $this->resolve_author_id($requested_author_id, get_current_user_id());

        $post_id = wp_insert_post([
            'post_type' => 'nail_art',
            'post_title' => wp_trim_words(wp_strip_all_tags($caption ?: 'NailSocial Post'), 8, ''),
            'post_content' => $caption,
            'post_status' => 'publish',
            'post_author' => $author_id,
        ]);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if ($image_url) update_post_meta($post_id, 'image_url', $image_url);
        if ($video_url) update_post_meta($post_id, 'video_url', $video_url);
        if ($location) update_post_meta($post_id, 'location', $location);

        return [
            'success' => true,
            'id' => (string) $post_id,
            'post' => $this->get_post(['id' => $post_id]),
        ];
    }

    /**
     * Get blog posts (Default WP Posts)
     */
    public function get_blogs($request) {
        $args = [
            'post_type' => 'post',
            'posts_per_page' => 10,
        ];

        $blogs = get_posts($args);
        $response = [];

        foreach ($blogs as $blog) {
            $categories = get_the_category($blog->ID);
            $response[] = [
                'id' => (string)$blog->ID,
                'title' => $blog->post_title,
                'excerpt' => $blog->post_excerpt ?: wp_trim_words($blog->post_content, 20),
                'image' => get_the_post_thumbnail_url($blog->ID, 'large') ?: '',
                'author' => get_the_author_meta('display_name', $blog->post_author),
                'date' => get_the_date('M d, Y', $blog->ID),
                'content' => $blog->post_content,
                'category' => !empty($categories) ? $categories[0]->name : 'Lifestyle',
            ];
        }

        return $response;
    }

    /**
     * Get full user profile
     */
    public function get_user_profile($request) {
        $id_or_slug = $request['id_or_slug'];
        
        if (is_numeric($id_or_slug)) {
            $user = get_user_by('id', $id_or_slug);
        } else {
            // Try by login or handle meta
            $user = get_user_by('login', $id_or_slug);
            if (!$user) {
                $users = get_users([
                    'meta_key' => 'handle',
                    'meta_value' => $id_or_slug,
                    'number' => 1
                ]);
                if (!empty($users)) $user = $users[0];
            }
        }

        if (!$user) return new WP_Error('no_user', 'User not found', ['status' => 404]);

        return [
            'id' => (string)$user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'handle' => get_user_meta($user->ID, 'handle', true) ?: '@' . $user->user_login,
            'avatar' => get_user_meta($user->ID, 'avatar_url', true) ?: get_avatar_url($user->ID),
            'image' => get_user_meta($user->ID, 'avatar_url', true) ?: get_avatar_url($user->ID),
            'bio' => get_user_meta($user->ID, 'bio', true),
            'location' => get_user_meta($user->ID, 'location', true),
            'instagram' => get_user_meta($user->ID, 'instagram', true),
            'tiktok' => get_user_meta($user->ID, 'tiktok', true),
            'role' => current($user->roles),
            'subscription_level' => get_user_meta($user->ID, 'subscription_level', true) ?: 'free',
            'followers_count' => count($this->get_user_ids_meta($user->ID, 'follower_ids')),
            'following_count' => count($this->get_user_ids_meta($user->ID, 'following_ids')),
            'is_following' => in_array(get_current_user_id(), $this->get_user_ids_meta($user->ID, 'follower_ids')),
        ];
    }

    public function update_user_profile($request) {
        $user_id = (int) $request['id'];
        $params = $request->get_json_params();

        if (!$user_id) {
            return new WP_Error('invalid_user', 'User ID is required', ['status' => 400]);
        }

        $this->assume_service_user_if_token($request);
        $current_user_id = get_current_user_id();
        if (!$this->has_valid_api_token($request) && $current_user_id !== $user_id && !current_user_can('edit_users')) {
            return new WP_Error('forbidden', 'You are not allowed to edit this user', ['status' => 403]);
        }

        $name = isset($params['name']) ? sanitize_text_field($params['name']) : '';
        if ($name === '') {
            return new WP_Error('missing_name', 'Name is required', ['status' => 400]);
        }

        $result = wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        if (array_key_exists('avatar', $params)) update_user_meta($user_id, 'avatar_url', esc_url_raw($params['avatar']));
        if (array_key_exists('bio', $params)) update_user_meta($user_id, 'bio', sanitize_textarea_field($params['bio']));
        if (array_key_exists('location', $params)) update_user_meta($user_id, 'location', sanitize_text_field($params['location']));
        if (array_key_exists('instagram', $params)) update_user_meta($user_id, 'instagram', sanitize_text_field($params['instagram']));
        if (array_key_exists('tiktok', $params)) update_user_meta($user_id, 'tiktok', sanitize_text_field($params['tiktok']));

        $response = new WP_REST_Request('GET', sprintf('/%s/users/%d', $this->namespace, $user_id));
        $response->set_param('id_or_slug', (string) $user_id);
        return $this->get_user_profile($response);
    }

    private function get_user_ids_meta($user_id, $key) {
        $data = get_user_meta($user_id, $key, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Follow/Unfollow toggle
     */
    public function toggle_follow($request) {
        $current_user_id = get_current_user_id();
        $target_user_id = $request['user_id'];

        if (!$target_user_id || $current_user_id == $target_user_id) {
            return new WP_Error('invalid_follow', 'Invalid user ID', ['status' => 400]);
        }

        $following = $this->get_user_ids_meta($current_user_id, 'following_ids');
        $followers = $this->get_user_ids_meta($target_user_id, 'follower_ids');

        if (in_array($target_user_id, $following)) {
            // Unfollow
            $following = array_diff($following, [$target_user_id]);
            $followers = array_diff($followers, [$current_user_id]);
            $action = 'unfollowed';
        } else {
            // Follow
            $following[] = $target_user_id;
            $followers[] = $current_user_id;
            $action = 'followed';

            // Create notification
            $this->create_notification($target_user_id, $current_user_id, 'follow');
        }

        update_user_meta($current_user_id, 'following_ids', array_values($following));
        update_user_meta($target_user_id, 'follower_ids', array_values($followers));

        return ['success' => true, 'action' => $action];
    }

    /**
     * Create notification entry
     */
    private function create_notification($recipient_id, $actor_id, $type, $item_id = 0) {
        $actor = get_userdata($actor_id);
        if (!$actor) return;
        
        $title = sprintf('%s started following you', $actor->display_name);

        $notif_id = wp_insert_post([
            'post_type' => 'notification',
            'post_title' => $title,
            'post_status' => 'publish',
            'post_author' => $actor_id,
        ]);

        update_post_meta($notif_id, 'recipient_id', $recipient_id);
        update_post_meta($notif_id, 'actor_id', $actor_id);
        update_post_meta($notif_id, 'notif_type', $type);
        update_post_meta($notif_id, 'item_id', $item_id);
        update_post_meta($notif_id, 'is_read', '0');
    }

    /**
     * Get user notifications
     */
    public function get_notifications() {
        $user_id = get_current_user_id();
        $posts = get_posts([
            'post_type' => 'notification',
            'numberposts' => 20,
            'meta_key' => 'recipient_id',
            'meta_value' => $user_id,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        $response = [];
        foreach ($posts as $p) {
            $actor_id = get_post_meta($p->ID, 'actor_id', true);
            $response[] = [
                'id' => $p->ID,
                'type' => get_post_meta($p->ID, 'notif_type', true),
                'title' => $p->post_title,
                'message' => $p->post_content,
                'time' => get_the_date('c', $p->ID),
                'isRead' => get_post_meta($p->ID, 'is_read', true) === '1',
                'actor' => [
                    'name' => get_the_author_meta('display_name', $actor_id),
                    'avatar' => get_user_meta($actor_id, 'avatar_url', true) ?: get_avatar_url($actor_id),
                ]
            ];
        }

        return $response;
    }

    /**
     * Mark notifications as read
     */
    public function mark_notifications_read($request) {
        $user_id = get_current_user_id();
        $notif_ids = $request['ids']; // Array or single ID

        if (empty($notif_ids)) {
            // Mark all for user
            $posts = get_posts([
                'post_type' => 'notification',
                'meta_key' => 'recipient_id',
                'meta_value' => $user_id,
                'meta_query' => [['key' => 'is_read', 'value' => '0']],
                'numberposts' => -1
            ]);
            foreach ($posts as $p) update_post_meta($p->ID, 'is_read', '1');
        } else {
            foreach ((array)$notif_ids as $id) {
                if (get_post_meta($id, 'recipient_id', true) == $user_id) {
                    update_post_meta($id, 'is_read', '1');
                }
            }
        }

        return ['success' => true];
    }

    /**
     * Authenticate or Create user from Social Login
     */
    public function social_auth($request) {
        $params = $request->get_json_params();
        $email = $params['email'];
        
        if (empty($email)) return new WP_Error('no_email', 'Email is required', ['status' => 400]);

        $user = get_user_by('email', $email);

        if (!$user) {
            $user_id = wp_insert_user([
                'user_login' => $params['name'] . '_' . time(),
                'user_email' => $email,
                'display_name' => $params['name'],
                'role' => 'subscriber',
            ]);
            $user = get_user_by('id', $user_id);
        }

        // Sync metadata
        update_user_meta($user->ID, 'social_provider', $params['provider']);
        update_user_meta($user->ID, 'social_provider_id', $params['provider_id']);
        if ($params['avatar']) update_user_meta($user->ID, 'avatar_url', $params['avatar']);

        return [
            'id' => (string)$user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'role' => current($user->roles),
            'subscription_level' => get_user_meta($user->ID, 'subscription_level', true) ?: 'free',
        ];
    }

    /**
     * Get single salon
     */
    public function get_salon($request) {
        $id_or_slug = $request['id_or_slug'];
        if (is_numeric($id_or_slug)) {
            $salon = get_post($id_or_slug);
        } else {
            $salons = get_posts([
                'post_type' => 'salon',
                'name' => $id_or_slug,
                'posts_per_page' => 1
            ]);
            $salon = !empty($salons) ? $salons[0] : null;
        }

        if (!$salon || $salon->post_type !== 'salon') {
            return new WP_Error('no_salon', 'Salon not found', ['status' => 404]);
        }

        $image = get_the_post_thumbnail_url($salon->ID, 'large');
        $owner_id = (int) $salon->post_author;
        return [
            'id' => (string)$salon->ID,
            'slug' => $salon->post_name,
            'name' => $salon->post_title,
            'owner_id' => (string) $owner_id,
            'owner' => get_the_author_meta('display_name', $owner_id),
            'level' => get_post_meta($salon->ID, 'level', true) ?: 'Free',
            'address' => get_post_meta($salon->ID, 'address', true),
            'city' => get_post_meta($salon->ID, 'city', true),
            'country' => get_post_meta($salon->ID, 'country', true),
            'rating' => (float)get_post_meta($salon->ID, 'rating', true) ?: 0,
            'reviews' => (int)get_post_meta($salon->ID, 'reviews_count', true) ?: 0,
            'image' => $image ?: (get_post_meta($salon->ID, 'cover_url', true) ?: ''),
            'logoUrl' => get_post_meta($salon->ID, 'logo_url', true) ?: '',
            'coverUrl' => get_post_meta($salon->ID, 'cover_url', true) ?: '',
            'phone' => get_post_meta($salon->ID, 'phone', true) ?: '',
            'website' => get_post_meta($salon->ID, 'website', true) ?: '',
            'category' => get_post_meta($salon->ID, 'category', true) ?: 'General',
            'description' => $salon->post_content,
        ];
    }

    public function create_salon($request) {
        $params = $request->get_json_params();
        $name = isset($params['name']) ? sanitize_text_field($params['name']) : '';
        if ($name === '') {
            return new WP_Error('missing_name', 'Salon name is required', ['status' => 400]);
        }

        $requested_author_id = !empty($params['userId']) ? (int) $params['userId'] : 0;
        $this->assume_service_user_if_token($request);
        $author_id = $this->resolve_author_id($requested_author_id, get_current_user_id());

        $post_id = wp_insert_post([
            'post_type' => 'salon',
            'post_title' => $name,
            'post_content' => isset($params['description']) ? wp_kses_post($params['description']) : '',
            'post_status' => 'publish',
            'post_author' => $author_id,
        ]);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $meta_map = [
            'address' => 'address',
            'city' => 'city',
            'country' => 'country',
            'category' => 'category',
            'level' => 'level',
            'phone' => 'phone',
            'website' => 'website',
            'logoUrl' => 'logo_url',
            'coverUrl' => 'cover_url',
            'image' => 'cover_url',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'openTime' => 'open_time',
            'closeTime' => 'close_time',
        ];

        foreach ($meta_map as $input_key => $meta_key) {
            if (!array_key_exists($input_key, $params)) continue;
            $value = $params[$input_key];
            if (in_array($meta_key, ['logo_url', 'cover_url'], true)) {
                update_post_meta($post_id, $meta_key, esc_url_raw($value));
            } else {
                update_post_meta($post_id, $meta_key, sanitize_text_field((string) $value));
            }
        }

        update_post_meta($post_id, 'rating', isset($params['rating']) ? (float) $params['rating'] : 0);
        update_post_meta($post_id, 'reviews_count', isset($params['reviews']) ? (int) $params['reviews'] : 0);
        update_post_meta($post_id, 'is_open', !empty($params['isOpen']) ? '1' : '0');

        return [
            'success' => true,
            'id' => (string) $post_id,
            'salon' => $this->get_salon(['id_or_slug' => (string) $post_id]),
        ];
    }

    /**
     * Get single reel
     */
    public function get_reel($request) {
        $id = $request['id'];
        $reel = get_post($id);

        if (!$reel || $reel->post_type !== 'reel') {
            return new WP_Error('no_reel', 'Reel not found', ['status' => 404]);
        }

        $author_id = $reel->post_author;
        return [
            'id' => $reel->ID,
            'image' => get_the_post_thumbnail_url($reel->ID, 'large') ?: '',
            'videoUrl' => get_post_meta($reel->ID, 'video_url', true),
            'description' => $reel->post_content,
            'author' => get_the_author_meta('display_name', $author_id),
            'music' => get_post_meta($reel->ID, 'music_title', true) ?: 'Original Audio',
            'views' => get_post_meta($reel->ID, 'views_count', true) ?: '0',
            'likes' => get_post_meta($reel->ID, 'likes_count', true) ?: '0',
            'comments' => get_post_meta($reel->ID, 'comments_count', true) ?: '0',
            'user' => [
                'avatar' => get_user_meta($author_id, 'avatar_url', true) ?: get_avatar_url($author_id)
            ]
        ];
    }

    /**
     * Get single nail art post
     */
    public function get_post($request) {
        $id = $request['id'];
        $post = get_post($id);

        if (!$post || $post->post_type !== 'nail_art') {
            return new WP_Error('no_post', 'Post not found', ['status' => 404]);
        }

        $author_id = $post->post_author;
        $video_url = get_post_meta($post->ID, 'video_url', true);
        return [
            'id' => (string)$post->ID,
            'type' => $video_url ? 'video' : 'image',
            'imageUrl' => get_the_post_thumbnail_url($post->ID, 'large') ?: (get_post_meta($post->ID, 'image_url', true) ?: ''),
            'videoUrl' => $video_url,
            'caption' => $post->post_content,
            'likes' => (int)get_post_meta($post->ID, 'likes_count', true) ?: 0,
            'comments' => (int)get_post_meta($post->ID, 'comments_count', true) ?: 0,
            'views' => (int)get_post_meta($post->ID, 'views_count', true) ?: 0,
            'location' => get_post_meta($post->ID, 'location', true),
            'user' => [
                'name' => get_the_author_meta('display_name', $author_id),
                'avatar' => get_user_meta($author_id, 'avatar_url', true) ?: get_avatar_url($author_id),
                'handle' => get_user_meta($author_id, 'handle', true) ?: '@' . get_the_author_meta('user_login', $author_id),
            ],
        ];
    }

    /**
     * Get single collection
     */
    public function get_collection($request) {
        $id = $request['id'];
        $col = get_post($id);

        if (!$col || $col->post_type !== 'collection') {
            return new WP_Error('no_collection', 'Collection not found', ['status' => 404]);
        }

        return [
            'id' => $col->ID,
            'title' => $col->post_title,
            'curator' => get_the_author_meta('display_name', $col->post_author),
            'extra' => get_post_meta($col->ID, 'extra_info', true),
            'image' => get_the_post_thumbnail_url($col->ID, 'large') ?: '',
            'images' => $this->get_collection_images($col->ID),
        ];
    }

    /**
     * Get single blog post
     */
    public function get_blog($request) {
        $id_or_slug = $request['id_or_slug'];
        if (is_numeric($id_or_slug)) {
            $blog = get_post($id_or_slug);
        } else {
            $blogs = get_posts([
                'post_type' => 'post',
                'name' => $id_or_slug,
                'posts_per_page' => 1
            ]);
            $blog = !empty($blogs) ? $blogs[0] : null;
        }

        if (!$blog || $blog->post_type !== 'post') {
            return new WP_Error('no_blog', 'Blog post not found', ['status' => 404]);
        }

        $categories = get_the_category($blog->ID);
        return [
            'id' => (string)$blog->ID,
            'title' => $blog->post_title,
            'excerpt' => $blog->post_excerpt ?: wp_trim_words($blog->post_content, 20),
            'image' => get_the_post_thumbnail_url($blog->ID, 'large') ?: '',
            'author' => get_the_author_meta('display_name', $blog->post_author),
            'date' => get_the_date('M d, Y', $blog->ID),
            'content' => $blog->post_content,
            'category' => !empty($categories) ? $categories[0]->name : 'Lifestyle',
        ];
    }
}
