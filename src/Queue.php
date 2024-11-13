<?php
namespace ResendWPMailer;

class Queue {
    private $table_name;
    private $logger;

    public function __construct(Logger $logger) {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'resend_email_queue';
        $this->logger = $logger;
        $this->maybe_create_table();
    }

    private function maybe_create_table(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email_to varchar(100) NOT NULL,
            subject varchar(255) NOT NULL,
            message longtext NOT NULL,
            attempts int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function add(string $to, string $subject, string $message): bool {
        global $wpdb;
        
        return $wpdb->insert(
            $this->table_name,
            array(
                'email_to' => $to,
                'subject' => $subject,
                'message' => $message,
                'status' => 'pending'
            ),
            array('%s', '%s', '%s', '%s')
        );
    }

    public function get_pending_emails(int $limit = 10): array {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE status = 'pending' 
            AND attempts < 3 
            ORDER BY created_at ASC 
            LIMIT %d",
            $limit
        ));
    }
}