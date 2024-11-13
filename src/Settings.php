<?php
namespace ResendWPMailer;

class Settings {
    private $options;
    private const OPTION_NAME = 'resend_wp_mailer_settings';

    public function __construct() {
        $this->options = get_option(self::OPTION_NAME, $this->get_default_options());
    }

    public function get_api_key(): string {
        return isset($this->options['api_key']) ? trim($this->options['api_key']) : '';
    }

    public function get_sender_name(): string {
        return $this->options['sender_name'] ?? get_bloginfo('name');
    }

    public function get_sender_email(): string {
        return $this->options['sender_email'] ?? get_bloginfo('admin_email');
    }

    public function get_email_type(): string {
        return $this->options['email_type'] ?? 'html';
    }

    public function get_rate_limit(): int {
        return (int)($this->options['rate_limit'] ?? 100);
    }

    public function is_logging_enabled(): bool {
        return (bool)($this->options['enable_logging'] ?? true);
    }

    public function is_analytics_enabled(): bool {
        return (bool)($this->options['enable_analytics'] ?? false);
    }

    public function get_all(): array {
        return $this->options;
    }

    private function get_default_options(): array {
        return array(
            'api_key' => '',
            'sender_name' => get_bloginfo('name'),
            'sender_email' => get_bloginfo('admin_email'),
            'enable_logging' => true,
            'enable_analytics' => false,
            'email_type' => 'html',
            'rate_limit' => 100
        );
    }

    public function save(array $settings): bool {
        $this->options = $settings;
        return update_option(self::OPTION_NAME, $settings);
    }
}