<?php
namespace ResendWPMailer;

class Cache {
    private const CACHE_GROUP = 'resend_wp_mailer';
    private const CACHE_EXPIRATION = 3600; // 1 hour

    public function set(string $key, $value): bool {
        return wp_cache_set($key, $value, self::CACHE_GROUP, self::CACHE_EXPIRATION);
    }

    public function get(string $key) {
        return wp_cache_get($key, self::CACHE_GROUP);
    }

    public function delete(string $key): bool {
        return wp_cache_delete($key, self::CACHE_GROUP);
    }
}