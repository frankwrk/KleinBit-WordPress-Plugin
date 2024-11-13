<?php

namespace ResendWPMailer;

class Admin
{
    private $settings;
    private $logger;
    private $mailer;
    private const OPTION_GROUP = 'resend_wp_mailer_options';
    private const OPTION_NAME = 'resend_wp_mailer_settings';
    private const SCRIPT_VERSION = '1.0.0';

    public function __construct(Settings $settings, Logger $logger, Mailer $mailer)
    {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->mailer = $mailer;

        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_send_test_email', [$this, 'handle_test_email']);

        // REST API endpoints for future React integration
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function add_admin_menu(): void
    {
        $parent_slug = 'resend-wp-mailer';

        add_menu_page(
            'Resend WP Mailer',
            'Resend Mailer',
            'manage_options',
            $parent_slug,
            [$this, 'render_app_container'], // Future React container
            'dashicons-email',
            100
        );

        // Add submenu pages
        $submenus = [
            'settings' => [
                'title' => 'Settings',
                'capability' => 'manage_options',
                'callback' => [$this, 'render_settings_page']
            ],
            'stats' => [
                'title' => 'Statistics',
                'capability' => 'manage_options',
                'callback' => [$this, 'render_stats_page']
            ],
            'logs' => [
                'title' => 'Email Logs',
                'capability' => 'manage_options',
                'callback' => [$this, 'render_logs_page']
            ]
        ];

        foreach ($submenus as $slug => $submenu) {
            add_submenu_page(
                $parent_slug,
                $submenu['title'],
                $submenu['title'],
                $submenu['capability'],
                "{$parent_slug}-{$slug}",
                $submenu['callback']
            );
        }
    }

    public function register_settings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings']
            ]
        );

        add_settings_section(
            'resend_wp_mailer_main',
            'Main Settings',
            function () {
                echo '<p>Configure your Resend API settings below.</p>';
            },
            'resend-wp-mailer'
        );

        // API Key Field
        add_settings_field(
            'api_key',
            'API Key',
            function () {
                $options = get_option(self::OPTION_NAME);
                printf(
                    '<input type="password" id="api_key" name="%s[api_key]" value="%s" class="regular-text">',
                    self::OPTION_NAME,
                    esc_attr($options['api_key'] ?? '')
                );
            },
            'resend-wp-mailer',
            'resend_wp_mailer_main'
        );

        // Sender Name Field
        add_settings_field(
            'sender_name',
            'Sender Name',
            function () {
                $options = get_option(self::OPTION_NAME);
                printf(
                    '<input type="text" id="sender_name" name="%s[sender_name]" value="%s" class="regular-text">',
                    self::OPTION_NAME,
                    esc_attr($options['sender_name'] ?? '')
                );
            },
            'resend-wp-mailer',
            'resend_wp_mailer_main'
        );

        // Sender Email Field
        add_settings_field(
            'sender_email',
            'Sender Email',
            function () {
                $options = get_option(self::OPTION_NAME);
                printf(
                    '<input type="email" id="sender_email" name="%s[sender_email]" value="%s" class="regular-text">',
                    self::OPTION_NAME,
                    esc_attr($options['sender_email'] ?? '')
                );
            },
            'resend-wp-mailer',
            'resend_wp_mailer_main'
        );

        // Email Type Field
        add_settings_field(
            'email_type',
            'Email Type',
            function () {
                $options = get_option(self::OPTION_NAME);
                $current = $options['email_type'] ?? 'html';
?>
            <select id="email_type" name="<?php echo self::OPTION_NAME; ?>[email_type]">
                <option value="html" <?php selected($current, 'html'); ?>>HTML</option>
                <option value="text" <?php selected($current, 'text'); ?>>Plain Text</option>
            </select>
        <?php
            },
            'resend-wp-mailer',
            'resend_wp_mailer_main'
        );

        // Enable Logging Field
        add_settings_field(
            'enable_logging',
            'Enable Logging',
            function () {
                $options = get_option(self::OPTION_NAME);
                printf(
                    '<input type="checkbox" id="enable_logging" name="%s[enable_logging]" %s>',
                    self::OPTION_NAME,
                    checked(isset($options['enable_logging']) && $options['enable_logging'], true, false)
                );
            },
            'resend-wp-mailer',
            'resend_wp_mailer_main'
        );
    }

    public function enqueue_admin_assets($hook): void
    {
        if (!$this->is_plugin_page($hook)) {
            return;
        }

        wp_enqueue_style(
            'resend-wp-mailer-admin',
            plugins_url('assets/css/admin-css.css', RESEND_WP_MAILER_FILE),
            [],
            self::SCRIPT_VERSION
        );

        wp_enqueue_script(
            'resend-wp-mailer-admin',
            plugins_url('assets/js/admin-js.js', RESEND_WP_MAILER_FILE),
            ['jquery', 'wp-api'],
            self::SCRIPT_VERSION,
            true
        );

        wp_localize_script('resend-wp-mailer-admin', 'resendWPMailer', [
            'apiNonce' => wp_create_nonce('wp_rest'),
            'apiUrl' => rest_url('resend-wp-mailer/v1'),
            'adminUrl' => admin_url(),
            'pluginUrl' => plugins_url('', RESEND_WP_MAILER_FILE)
        ]);
    }

    public function register_rest_routes(): void
    {
        register_rest_route('resend-wp-mailer/v1', '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'api_get_settings'],
            'permission_callback' => [$this, 'api_permissions_check']
        ]);

        register_rest_route('resend-wp-mailer/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'api_get_stats'],
            'permission_callback' => [$this, 'api_permissions_check']
        ]);

        register_rest_route('resend-wp-mailer/v1', '/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'api_get_logs'],
            'permission_callback' => [$this, 'api_permissions_check']
        ]);
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php
            if (isset($_GET['settings-updated'])) {
                add_settings_error(
                    'resend_wp_mailer_messages',
                    'resend_wp_mailer_message',
                    'Settings Saved',
                    'updated'
                );
            }
            settings_errors('resend_wp_mailer_messages');
            ?>

            <form action="options.php" method="post">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections('resend-wp-mailer');
                submit_button('Save Settings');
                ?>
            </form>

            <hr>

            <h2>Send Test Email</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('resend_test_email'); ?>
                <input type="hidden" name="action" value="send_test_email">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="test_email">To:</label></th>
                        <td><input type="email" name="test_email" id="test_email" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="test_subject">Subject:</label></th>
                        <td><input type="text" name="test_subject" id="test_subject" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="test_message">Message:</label></th>
                        <td><textarea name="test_message" id="test_message" rows="5" class="regular-text" required></textarea>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Send Test Email', 'secondary'); ?>
            </form>
        </div>
    <?php
    }

    public function render_stats_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $stats = $this->get_email_stats();
        $daily_stats = $this->get_daily_stats();
    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="resend-stats-cards">
                <div class="resend-stat-card">
                    <h3>Total Emails</h3>
                    <div class="stat-number"><?php echo esc_html($stats['total']); ?></div>
                </div>
                <div class="resend-stat-card">
                    <h3>Successful</h3>
                    <div class="stat-number success"><?php echo esc_html($stats['sent']); ?></div>
                </div>
                <div class="resend-stat-card">
                    <h3>Failed</h3>
                    <div class="stat-number error"><?php echo esc_html($stats['failed']); ?></div>
                </div>
                <div class="resend-stat-card">
                    <h3>Success Rate</h3>
                    <div class="stat-number">
                        <?php echo esc_html(
                            $stats['total'] > 0
                                ? round(($stats['sent'] / $stats['total']) * 100, 1)
                                : 0
                        ); ?>%
                    </div>
                </div>
            </div>

            <div class="resend-stats-chart">
                <h2>Last 30 Days Email Activity</h2>
                <canvas id="emailStatsChart"></canvas>
            </div>
        </div>
    <?php
    }

    public function render_logs_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'resend_email_logs';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $total_pages = ceil($total_items / $per_page);
    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>To</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html(wp_date('Y-m-d H:i:s', strtotime($log->created_at))); ?></td>
                                <td><?php echo esc_html($log->email_to); ?></td>
                                <td><?php echo esc_html($log->subject); ?></td>
                                <td><?php echo esc_html($log->status); ?></td>
                                <td><?php echo esc_html($log->error_message); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No logs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $page
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
<?php
    }

    public function handle_test_email(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('resend_test_email');

        $to = sanitize_email($_POST['test_email']);
        $subject = sanitize_text_field($_POST['test_subject']);
        $message = wp_kses_post($_POST['test_message']);

        $result = $this->mailer->send_test_email($to, $subject, $message);

        wp_redirect(add_query_arg(
            array(
                'page' => 'resend-wp-mailer',
                'test-email-sent' => $result ? '1' : '0'
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    public function sanitize_settings($input): array
    {
        $sanitized = [];

        $sanitized['api_key'] = sanitize_text_field($input['api_key'] ?? '');
        $sanitized['sender_name'] = sanitize_text_field($input['sender_name'] ?? '');
        $sanitized['sender_email'] = sanitize_email($input['sender_email'] ?? '');
        $sanitized['email_type'] = in_array($input['email_type'], ['html', 'text']) ? $input['email_type'] : 'html';
        $sanitized['enable_logging'] = isset($input['enable_logging']);
        $sanitized['enable_analytics'] = isset($input['enable_analytics']);

        return $sanitized;
    }

    // API Methods
    public function api_permissions_check(): bool
    {
        return current_user_can('manage_options');
    }

    public function api_get_settings(): \WP_REST_Response
    {
        return new \WP_REST_Response($this->settings->get_all());
    }

    public function api_get_stats(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'overview' => $this->get_email_stats(),
            'daily' => $this->get_daily_stats()
        ]);
    }

    public function api_get_logs(\WP_REST_Request $request): \WP_REST_Response
    {
        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 20;

        global $wpdb;
        $table_name = $wpdb->prefix . 'resend_email_logs';
        $offset = ($page - 1) * $per_page;

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        return new \WP_REST_Response([
            'logs' => $logs,
            'total' => (int)$total,
            'pages' => ceil($total / $per_page)
        ]);
    }

    // Utility Methods
    private function is_plugin_page(string $hook): bool
    {
        return strpos($hook, 'resend-wp-mailer') !== false;
    }

    private function get_email_stats(): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'resend_email_logs';

        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM {$table_name}
        ", ARRAY_A);

        return [
            'total' => (int)($stats['total'] ?? 0),
            'sent' => (int)($stats['sent'] ?? 0),
            'failed' => (int)($stats['failed'] ?? 0)
        ];
    }

    private function get_daily_stats(): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'resend_email_logs';

        return $wpdb->get_results("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM {$table_name}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ", ARRAY_A);
    }

    public function render_app_container(): void
    {
        echo '<div id="resend-wp-mailer-app"></div>';
    }
}
