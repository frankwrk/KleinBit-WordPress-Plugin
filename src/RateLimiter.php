<?php
namespace ResendWPMailer;

class RateLimiter {
    private $settings;
    private $table_name;

    public function __construct(Settings $settings) {
        global $wpdb;
        $this->settings = $settings;
        $this->table_name = $wpdb->prefix . 'resend_email_logs';
    }

    public function is_rate_limited(): bool {
        $rate_limit = $this->settings->get_rate_limit();
        if (empty($rate_limit)) {
            return false;
        }

        global $wpdb;
        $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $sent_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE status = 'sent' AND created_at >= %s",
            $hour_ago
        ));

        return (int)$sent_count >= $rate_limit;
    }
}