<?php
namespace ResendWPMailer;

class Api {
    private $settings;

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function send_email(array $args): bool {
        $api_key = $this->settings->get_api_key();
        
        if (empty($api_key)) {
            error_log('Resend API Key is missing or empty');
            return false;
        }

        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        );

        // Build the email body with both HTML and text versions
        $body = array(
            'from' => sprintf('%s <%s>', 
                $this->settings->get_sender_name(), 
                $this->settings->get_sender_email()
            ),
            'to' => $args['to'],
            'subject' => $args['subject'],
            'html' => $args['message'], // Original HTML message
            'text' => $this->convert_html_to_text($args['message']) // Plain text version
        );

        $response = wp_remote_post('https://api.resend.com/emails', array(
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'timeout' => 30,
            'data_format' => 'body'
        ));

        if (is_wp_error($response)) {
            error_log('Resend API Error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            error_log('Resend API Error: ' . $response_body);
            return false;
        }

        return true;
    }

    /**
     * Converts HTML content to plain text
     * 
     * @param string $html The HTML content to convert
     * @return string The plain text version
     */
    private function convert_html_to_text(string $html): string {
        // Remove style and script tags and their contents
        $html = preg_replace('/<(script|style)\b[^>]*>(.*?)<\/\1>/is', '', $html);
        
        // Convert common HTML elements to text equivalents
        $replacements = [
            '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i' => "\n\n$1\n\n",
            '/<br[^>]*>/i' => "\n",
            '/<p[^>]*>(.*?)<\/p>/i' => "\n$1\n",
            '/<div[^>]*>(.*?)<\/div>/i' => "\n$1\n",
            '/<li[^>]*>(.*?)<\/li>/i' => "* $1\n",
            '/<hr[^>]*>/i' => "\n-------------------------\n",
            '/<a[^>]*href=["\'](.*?)["\'][^>]*>(.*?)<\/a>/i' => "$2 ($1)",
            '/<b[^>]*>(.*?)<\/b>/i' => "*$1*",
            '/<strong[^>]*>(.*?)<\/strong>/i' => "*$1*",
            '/<i[^>]*>(.*?)<\/i>/i' => "_$1_",
            '/<em[^>]*>(.*?)<\/em>/i' => "_$1_",
            '/&nbsp;/i' => ' ',
            '/&amp;/i' => '&',
            '/&lt;/i' => '<',
            '/&gt;/i' => '>',
            '/&quot;/i' => '"',
            '/&apos;/i' => "'",
        ];

        // Apply all replacements
        $text = preg_replace(
            array_keys($replacements), 
            array_values($replacements), 
            $html
        );

        // Strip any remaining HTML tags
        $text = strip_tags($text);

        // Convert multiple spaces to single space
        $text = preg_replace('/\s+/', ' ', $text);

        // Convert multiple newlines to maximum of two
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);

        // Trim whitespace
        $text = trim($text);

        return $text;
    }
}