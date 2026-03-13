<?php
/**
 * Handles S3-compatible storage configuration and signed URLs.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NailSocial_Storage {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function is_configured() {
        $settings = $this->get_settings();
        return $settings['endpoint'] !== ''
            && $settings['access_key'] !== ''
            && $settings['secret_key'] !== ''
            && $settings['bucket'] !== '';
    }

    public function get_settings() {
        return [
            'endpoint' => rtrim((string) get_option('nailsocial_storage_endpoint', ''), '/'),
            'access_key' => (string) get_option('nailsocial_storage_access_key', ''),
            'secret_key' => (string) get_option('nailsocial_storage_secret_key', ''),
            'bucket' => trim((string) get_option('nailsocial_storage_bucket', '')),
            'region' => trim((string) get_option('nailsocial_storage_region', '')) ?: 'us-east-1',
            'cdn_base_url' => rtrim((string) get_option('nailsocial_storage_cdn_base_url', ''), '/'),
            'use_path_style' => get_option('nailsocial_storage_use_path_style', '1') === '1',
            'upload_expiration' => max(60, absint(get_option('nailsocial_storage_upload_expiration', 900))),
        ];
    }

    public function build_video_storage_key($video_id, $filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $ext = $ext ?: 'mp4';
        return sprintf(
            'videos/raw/%s/%d-original.%s',
            gmdate('Y/m'),
            (int) $video_id,
            preg_replace('/[^a-z0-9]+/i', '', $ext)
        );
    }

    public function get_public_url($storage_key) {
        $settings = $this->get_settings();
        if ($settings['cdn_base_url'] !== '') {
            return $this->build_cdn_url($settings['cdn_base_url'], $storage_key);
        }

        return $this->build_object_url($storage_key);
    }

    public function create_presigned_put_url($storage_key, $mime_type = 'application/octet-stream', $expires = null) {
        $settings = $this->get_settings();
        if (!$this->is_configured()) {
            return new WP_Error('storage_not_configured', 'Storage settings are incomplete', ['status' => 500]);
        }

        $expires = $expires !== null ? max(60, absint($expires)) : $settings['upload_expiration'];
        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $host = $this->get_request_host();
        $canonical_uri = $this->get_canonical_uri($storage_key);
        $credential_scope = $date . '/' . $settings['region'] . '/s3/aws4_request';
        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $settings['access_key'] . '/' . $credential_scope,
            'X-Amz-Date' => $now,
            'X-Amz-Expires' => (string) $expires,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($query);

        $canonical_query = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $canonical_request = implode("\n", [
            'PUT',
            $canonical_uri,
            $canonical_query,
            'host:' . $host,
            '',
            'host',
            'UNSIGNED-PAYLOAD',
        ]);

        $string_to_sign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $now,
            $credential_scope,
            hash('sha256', $canonical_request),
        ]);

        $signature = hash_hmac(
            'sha256',
            $string_to_sign,
            $this->get_signature_key($settings['secret_key'], $date, $settings['region'], 's3')
        );

        return $this->build_request_base_url($storage_key) . '?' . $canonical_query . '&X-Amz-Signature=' . $signature;
    }

    public function object_exists($storage_key) {
        $settings = $this->get_settings();
        if (!$this->is_configured()) {
            return false;
        }

        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $host = $this->get_request_host();
        $canonical_uri = $this->get_canonical_uri($storage_key);
        $payload_hash = hash('sha256', '');
        $credential_scope = $date . '/' . $settings['region'] . '/s3/aws4_request';

        $canonical_headers = implode("\n", [
            'host:' . $host,
            'x-amz-content-sha256:' . $payload_hash,
            'x-amz-date:' . $now,
        ]) . "\n";

        $canonical_request = implode("\n", [
            'HEAD',
            $canonical_uri,
            '',
            $canonical_headers,
            'host;x-amz-content-sha256;x-amz-date',
            $payload_hash,
        ]);

        $string_to_sign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $now,
            $credential_scope,
            hash('sha256', $canonical_request),
        ]);

        $signature = hash_hmac(
            'sha256',
            $string_to_sign,
            $this->get_signature_key($settings['secret_key'], $date, $settings['region'], 's3')
        );

        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=host;x-amz-content-sha256;x-amz-date, Signature=%s',
            $settings['access_key'],
            $credential_scope,
            $signature
        );

        $response = wp_remote_request($this->build_request_base_url($storage_key), [
            'method' => 'HEAD',
            'timeout' => 15,
            'headers' => [
                'Host' => $host,
                'x-amz-date' => $now,
                'x-amz-content-sha256' => $payload_hash,
                'Authorization' => $authorization,
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        return $status >= 200 && $status < 300;
    }

    public function put_object_from_file($storage_key, $file_path, $content_type = 'application/octet-stream') {
        $settings = $this->get_settings();
        if (!$this->is_configured()) {
            return new WP_Error('storage_not_configured', 'Storage settings are incomplete', ['status' => 500]);
        }

        if (!file_exists($file_path)) {
            return new WP_Error('missing_file', 'File does not exist for upload', ['status' => 400]);
        }

        $body = file_get_contents($file_path);
        if ($body === false) {
            return new WP_Error('read_failed', 'Failed to read file for upload', ['status' => 500]);
        }

        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $host = $this->get_request_host();
        $canonical_uri = $this->get_canonical_uri($storage_key);
        $payload_hash = hash('sha256', $body);
        $credential_scope = $date . '/' . $settings['region'] . '/s3/aws4_request';

        $canonical_headers = implode("\n", [
            'content-type:' . trim($content_type),
            'host:' . $host,
            'x-amz-content-sha256:' . $payload_hash,
            'x-amz-date:' . $now,
        ]) . "\n";

        $canonical_request = implode("\n", [
            'PUT',
            $canonical_uri,
            '',
            $canonical_headers,
            'content-type;host;x-amz-content-sha256;x-amz-date',
            $payload_hash,
        ]);

        $string_to_sign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $now,
            $credential_scope,
            hash('sha256', $canonical_request),
        ]);

        $signature = hash_hmac(
            'sha256',
            $string_to_sign,
            $this->get_signature_key($settings['secret_key'], $date, $settings['region'], 's3')
        );

        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=content-type;host;x-amz-content-sha256;x-amz-date, Signature=%s',
            $settings['access_key'],
            $credential_scope,
            $signature
        );

        $response = wp_remote_request($this->build_request_base_url($storage_key), [
            'method' => 'PUT',
            'timeout' => 60,
            'headers' => [
                'Host' => $host,
                'Content-Type' => trim($content_type),
                'x-amz-date' => $now,
                'x-amz-content-sha256' => $payload_hash,
                'Authorization' => $authorization,
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return new WP_Error('storage_upload_failed', 'Storage upload failed with status ' . $status, ['status' => 500]);
        }

        return true;
    }

    private function build_request_base_url($storage_key) {
        return $this->build_object_url($storage_key);
    }

    private function build_cdn_url($base_url, $storage_key) {
        $normalized_base = rtrim((string) $base_url, '/');
        $parts = wp_parse_url($normalized_base);
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';

        if ($path === '') {
            $normalized_base .= '/api';
        }

        return $normalized_base . '/' . ltrim($storage_key, '/');
    }

    private function build_object_url($storage_key) {
        $settings = $this->get_settings();
        $endpoint_parts = wp_parse_url($settings['endpoint']);
        $scheme = isset($endpoint_parts['scheme']) ? $endpoint_parts['scheme'] : 'https';
        $host = isset($endpoint_parts['host']) ? $endpoint_parts['host'] : '';
        $port = isset($endpoint_parts['port']) ? ':' . $endpoint_parts['port'] : '';
        $path_prefix = isset($endpoint_parts['path']) ? rtrim($endpoint_parts['path'], '/') : '';
        $encoded_key = $this->encode_key($storage_key);

        if ($settings['use_path_style']) {
            return sprintf('%s://%s%s%s/%s/%s', $scheme, $host, $port, $path_prefix, $settings['bucket'], $encoded_key);
        }

        return sprintf('%s://%s.%s%s%s/%s', $scheme, $settings['bucket'], $host, $port, $path_prefix, $encoded_key);
    }

    private function get_request_host() {
        $settings = $this->get_settings();
        $parts = wp_parse_url($settings['endpoint']);
        $host = isset($parts['host']) ? $parts['host'] : '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        if ($settings['use_path_style']) {
            return $host . $port;
        }

        return $settings['bucket'] . '.' . $host . $port;
    }

    private function get_canonical_uri($storage_key) {
        $settings = $this->get_settings();
        $parts = wp_parse_url($settings['endpoint']);
        $path_prefix = isset($parts['path']) ? trim($parts['path'], '/') : '';
        $segments = [];
        if ($path_prefix !== '') {
            $segments[] = $path_prefix;
        }
        if ($settings['use_path_style']) {
            $segments[] = $settings['bucket'];
        }
        $segments[] = ltrim($storage_key, '/');

        return '/' . implode('/', array_map([$this, 'encode_key_segment'], explode('/', implode('/', $segments))));
    }

    private function encode_key($storage_key) {
        return implode('/', array_map([$this, 'encode_key_segment'], explode('/', ltrim($storage_key, '/'))));
    }

    private function encode_key_segment($segment) {
        return str_replace('%2F', '/', rawurlencode($segment));
    }

    private function get_signature_key($secret_key, $date_stamp, $region_name, $service_name) {
        $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $secret_key, true);
        $k_region = hash_hmac('sha256', $region_name, $k_date, true);
        $k_service = hash_hmac('sha256', $service_name, $k_region, true);
        return hash_hmac('sha256', 'aws4_request', $k_service, true);
    }
}
