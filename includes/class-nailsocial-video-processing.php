<?php
/**
 * Handles background video processing jobs.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NailSocial_Video_Processing {
    const CRON_HOOK = 'nailsocial_process_video';

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action(self::CRON_HOOK, [$this, 'process_video'], 10, 1);
    }

    public function enqueue($video_id) {
        $video_id = absint($video_id);
        if ($video_id <= 0) {
            return;
        }

        wp_clear_scheduled_hook(self::CRON_HOOK, [$video_id]);
        wp_schedule_single_event(time() + 5, self::CRON_HOOK, [$video_id]);
    }

    public function process_video($video_id) {
        $video_id = absint($video_id);
        $reel = get_post($video_id);

        if (!$reel || $reel->post_type !== 'reel') {
            return;
        }

        $source_url = get_post_meta($video_id, 'playback_url', true) ?: get_post_meta($video_id, 'video_url', true);
        $storage_key = get_post_meta($video_id, 'storage_key', true);
        if ($source_url === '' || $storage_key === '') {
            update_post_meta($video_id, 'video_status', 'failed');
            update_post_meta($video_id, 'video_error', 'Missing playback URL or storage key');
            return;
        }

        $ffmpeg = $this->find_binary((string) get_option('nailsocial_ffmpeg_path', 'ffmpeg'), 'ffmpeg');
        $ffprobe = $this->find_binary((string) get_option('nailsocial_ffprobe_path', 'ffprobe'), 'ffprobe');
        if ($ffmpeg === '' || $ffprobe === '') {
            update_post_meta($video_id, 'video_status', 'failed');
            update_post_meta($video_id, 'video_error', 'ffmpeg/ffprobe not configured on server');
            return;
        }

        $tmp_dir = trailingslashit(get_temp_dir()) . 'nailsocial-video-' . $video_id . '-' . wp_generate_password(8, false);
        if (!wp_mkdir_p($tmp_dir)) {
            update_post_meta($video_id, 'video_status', 'failed');
            update_post_meta($video_id, 'video_error', 'Failed to create temp directory');
            return;
        }

        $thumbnail_path = trailingslashit($tmp_dir) . 'thumbnail.jpg';
        $metadata = $this->probe_video($ffprobe, $source_url);

        $thumbnail_command = sprintf(
            '%s -y -i %s -ss 00:00:01.000 -frames:v 1 -vf %s %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($source_url),
            escapeshellarg('thumbnail,scale=720:-1'),
            escapeshellarg($thumbnail_path)
        );

        $thumbnail_output = shell_exec($thumbnail_command);
        if (!file_exists($thumbnail_path)) {
            update_post_meta($video_id, 'video_status', 'failed');
            update_post_meta($video_id, 'video_error', 'Thumbnail generation failed: ' . wp_strip_all_tags((string) $thumbnail_output));
            $this->cleanup_temp_dir($tmp_dir);
            return;
        }

        $thumbnail_storage_key = preg_replace('#/raw/#', '/processed/', $storage_key);
        if ($thumbnail_storage_key === $storage_key) {
            $thumbnail_storage_key = 'videos/processed/' . gmdate('Y/m') . '/' . $video_id . '/thumbnail.jpg';
        } else {
            $thumbnail_storage_key = preg_replace('#-original\.[a-z0-9]+$#i', 'thumbnail.jpg', $thumbnail_storage_key);
        }

        $storage = NailSocial_Storage::get_instance();
        $uploaded = $storage->put_object_from_file($thumbnail_storage_key, $thumbnail_path, 'image/jpeg');
        if (is_wp_error($uploaded)) {
            update_post_meta($video_id, 'video_status', 'failed');
            update_post_meta($video_id, 'video_error', $uploaded->get_error_message());
            $this->cleanup_temp_dir($tmp_dir);
            return;
        }

        $thumbnail_url = $storage->get_public_url($thumbnail_storage_key);
        update_post_meta($video_id, 'thumbnail_url', $thumbnail_url);
        update_post_meta($video_id, 'video_status', 'ready');
        update_post_meta($video_id, 'video_error', '');
        update_post_meta($video_id, 'duration_seconds', $metadata['duration_seconds']);
        update_post_meta($video_id, 'video_width', $metadata['width']);
        update_post_meta($video_id, 'video_height', $metadata['height']);

        wp_update_post([
            'ID' => $video_id,
            'post_status' => 'publish',
        ]);

        $this->cleanup_temp_dir($tmp_dir);
    }

    private function find_binary($configured, $fallback) {
        $configured = trim($configured);
        if ($configured !== '') {
            return $configured;
        }

        return $fallback;
    }

    private function probe_video($ffprobe, $source_url) {
        $command = sprintf(
            '%s -v error -select_streams v:0 -show_entries stream=width,height:format=duration -of json %s 2>&1',
            escapeshellarg($ffprobe),
            escapeshellarg($source_url)
        );

        $output = shell_exec($command);
        $decoded = json_decode((string) $output, true);
        $width = 0;
        $height = 0;
        $duration = 0;

        if (is_array($decoded)) {
            if (!empty($decoded['streams'][0])) {
                $width = !empty($decoded['streams'][0]['width']) ? absint($decoded['streams'][0]['width']) : 0;
                $height = !empty($decoded['streams'][0]['height']) ? absint($decoded['streams'][0]['height']) : 0;
            }
            if (!empty($decoded['format']['duration'])) {
                $duration = round((float) $decoded['format']['duration'], 2);
            }
        }

        return [
            'duration_seconds' => $duration,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function cleanup_temp_dir($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob(trailingslashit($dir) . '*');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
        @rmdir($dir);
    }
}
