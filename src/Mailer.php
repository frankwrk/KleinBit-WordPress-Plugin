<?php
namespace ResendWPMailer;

class Mailer {
    private $api;
    private $logger;
    private $rate_limiter;
    private $queue;
    private $settings;

    public function __construct(
        Api $api,
        Logger $logger,
        RateLimiter $rate_limiter,
        Queue $queue,
        Settings $settings
    ) {
        $this->api = $api;
        $this->logger = $logger;
        $this->rate_limiter = $rate_limiter;
        $this->queue = $queue;
        $this->settings = $settings;
    }

    public function send(array $args): bool {
        if ($this->rate_limiter->is_rate_limited()) {
            $this->queue->add($args['to'], $args['subject'], $args['message']);
            return true;
        }

        $result = $this->api->send_email($args);
        
        if ($result) {
            $this->logger->log($args['to'], $args['subject'], $args['message'], 'sent');
        } else {
            $this->logger->log($args['to'], $args['subject'], $args['message'], 'failed', 'API Error');
        }

        return $result;
    }

    public function send_test_email(string $to, string $subject, string $message): bool {
        return $this->send([
            'to' => $to,
            'subject' => $subject,
            'message' => $message
        ]);
    }
}