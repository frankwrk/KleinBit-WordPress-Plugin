<?php
namespace ResendWPMailer;

class Logger {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'resend_email_logs';
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
            status varchar(20) NOT NULL,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function log(string $to, string $subject, string $message, string $status, string $error = ''): void {
        global $wpdb;
        
        $wpdb->insert(
            $this->table_name,
            array(
                'email_to' => $to,
                'subject' => $subject,
                'message' => $message,
                'status' => $status,
                'error_message' => $error
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }
}