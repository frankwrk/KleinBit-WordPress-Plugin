<?php
/*
Plugin Name: Resend WP Mailer
Description: Replaces WordPress default mail function with Resend API
Version: 1.0.3.4
Author: SYNQ Studio
*/

if (!defined('ABSPATH')) {
    exit;
}

// Debug: Add error logging
error_log('Loading Resend WP Mailer plugin');

// Define plugin constants
define('RESEND_WP_MAILER_PATH', plugin_dir_path(__FILE__));
define('RESEND_WP_MAILER_FILE', __FILE__);

// Autoloader with debug
spl_autoload_register(function ($class) {
    $prefix = 'ResendWPMailer\\';
    $base_dir = plugin_dir_path(__FILE__) . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Debug: Log file path
    error_log("Attempting to load: {$file}");

    if (file_exists($file)) {
        require_once $file;
        error_log("Successfully loaded: {$file}");
    } else {
        error_log("File not found: {$file}");
    }
});

// Initialize plugin with debug
function init_resend_wp_mailer()
{
    try {
        error_log('Initializing Resend WP Mailer components');

        $settings = new ResendWPMailer\Settings();
        $logger = new ResendWPMailer\Logger();
        $api = new ResendWPMailer\Api($settings);
        $rate_limiter = new ResendWPMailer\RateLimiter($settings);
        $queue = new ResendWPMailer\Queue($logger);
        $mailer = new ResendWPMailer\Mailer($api, $logger, $rate_limiter, $queue, $settings);
        $admin = new ResendWPMailer\Admin($settings, $logger, $mailer);

        error_log('Successfully initialized all components');
    } catch (Exception $e) {
        error_log('Error initializing Resend WP Mailer: ' . $e->getMessage());
    }
}

add_action('plugins_loaded', 'init_resend_wp_mailer');
