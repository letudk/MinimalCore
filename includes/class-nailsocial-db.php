<?php
/**
 * Handles custom database tables and migrations.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NailSocial_DB {
    const SCHEMA_VERSION = '1.0.0';
    const VERSION_OPTION = 'nailsocial_db_schema_version';

    public static function install_or_upgrade() {
        self::create_tables();
        self::migrate_legacy_reviews();
        update_option(self::VERSION_OPTION, self::SCHEMA_VERSION, false);
    }

    public static function maybe_upgrade() {
        $installed = (string) get_option(self::VERSION_OPTION, '');
        if ($installed !== self::SCHEMA_VERSION) {
            self::install_or_upgrade();
        }
    }

    public static function get_reviews_table() {
        global $wpdb;
        return $wpdb->prefix . 'nailsocial_reviews';
    }

    public static function get_review_stats_table() {
        global $wpdb;
        return $wpdb->prefix . 'nailsocial_review_stats';
    }

    public static function get_comments_table() {
        global $wpdb;
        return $wpdb->prefix . 'nailsocial_comments';
    }

    private static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $reviews_table = self::get_reviews_table();
        $review_stats_table = self::get_review_stats_table();
        $comments_table = self::get_comments_table();

        $sql_reviews = "CREATE TABLE {$reviews_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            legacy_post_id BIGINT(20) UNSIGNED NULL,
            salon_id BIGINT(20) UNSIGNED NOT NULL,
            appointment_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            rating DECIMAL(4,2) NOT NULL DEFAULT 0.00,
            comment LONGTEXT NULL,
            photos LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'published',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY appointment_id (appointment_id),
            KEY salon_status_created (salon_id, status, created_at),
            KEY user_created (user_id, created_at),
            KEY legacy_post_id (legacy_post_id)
        ) {$charset_collate};";

        $sql_review_stats = "CREATE TABLE {$review_stats_table} (
            salon_id BIGINT(20) UNSIGNED NOT NULL,
            review_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            rating_sum DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            rating_avg DECIMAL(4,2) NOT NULL DEFAULT 0.00,
            last_review_at DATETIME NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (salon_id)
        ) {$charset_collate};";

        $sql_comments = "CREATE TABLE {$comments_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type VARCHAR(32) NOT NULL,
            entity_id BIGINT(20) UNSIGNED NOT NULL,
            parent_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            body LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'published',
            like_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            reply_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY entity_status_created (entity_type, entity_id, status, created_at),
            KEY parent_status_created (parent_id, status, created_at),
            KEY user_created (user_id, created_at)
        ) {$charset_collate};";

        dbDelta($sql_reviews);
        dbDelta($sql_review_stats);
        dbDelta($sql_comments);
    }

    private static function migrate_legacy_reviews() {
        global $wpdb;

        $reviews_table = self::get_reviews_table();
        $stats_table = self::get_review_stats_table();

        $legacy_reviews = get_posts([
            'post_type' => 'salon_review',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'ASC',
        ]);

        if (empty($legacy_reviews)) {
            return;
        }

        foreach ($legacy_reviews as $review) {
            $legacy_post_id = (int) $review->ID;
            $exists = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$reviews_table} WHERE legacy_post_id = %d LIMIT 1",
                $legacy_post_id
            ));

            if ($exists > 0) {
                continue;
            }

            $salon_id = (int) get_post_meta($legacy_post_id, 'salon_id', true);
            $appointment_id = (int) get_post_meta($legacy_post_id, 'appointment_id', true);
            $user_id = (int) get_post_meta($legacy_post_id, 'user_id', true);
            $rating = (float) get_post_meta($legacy_post_id, 'rating', true);
            $photos = get_post_meta($legacy_post_id, 'photos', true);
            $status = (string) get_post_meta($legacy_post_id, 'status', true);
            $created_at = get_post_time('Y-m-d H:i:s', true, $legacy_post_id);
            $updated_at = get_post_modified_time('Y-m-d H:i:s', true, $legacy_post_id);

            if ($salon_id <= 0 || $appointment_id <= 0 || $user_id <= 0) {
                continue;
            }

            $wpdb->insert($reviews_table, [
                'legacy_post_id' => $legacy_post_id,
                'salon_id' => $salon_id,
                'appointment_id' => $appointment_id,
                'user_id' => $user_id,
                'rating' => $rating,
                'comment' => $review->post_content,
                'photos' => wp_json_encode(is_array($photos) ? $photos : []),
                'status' => $status ?: 'published',
                'created_at' => $created_at ?: current_time('mysql', true),
                'updated_at' => $updated_at ?: current_time('mysql', true),
            ], [
                '%d',
                '%d',
                '%d',
                '%d',
                '%f',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]);
        }

        $salon_stats = $wpdb->get_results(
            "SELECT salon_id, COUNT(*) AS review_count, COALESCE(SUM(rating), 0) AS rating_sum, MAX(created_at) AS last_review_at
             FROM {$reviews_table}
             WHERE status = 'published'
             GROUP BY salon_id",
            ARRAY_A
        );

        foreach ($salon_stats as $stat) {
            $review_count = (int) $stat['review_count'];
            $rating_sum = (float) $stat['rating_sum'];
            $rating_avg = $review_count > 0 ? round($rating_sum / $review_count, 2) : 0;

            $wpdb->replace($stats_table, [
                'salon_id' => (int) $stat['salon_id'],
                'review_count' => $review_count,
                'rating_sum' => $rating_sum,
                'rating_avg' => $rating_avg,
                'last_review_at' => $stat['last_review_at'],
                'updated_at' => current_time('mysql', true),
            ], [
                '%d',
                '%d',
                '%f',
                '%f',
                '%s',
                '%s',
            ]);

            update_post_meta((int) $stat['salon_id'], 'rating', $rating_avg);
            update_post_meta((int) $stat['salon_id'], 'reviews_count', $review_count);
        }
    }
}
