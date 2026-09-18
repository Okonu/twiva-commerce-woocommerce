<?php

/**
 * Enhanced logging class for WC Commission Manager
 */
class TWIVCO_Commission_Manager_Logger {

    private static $instance = null;
    private $log_enabled = true;
    private $log_level = 'error'; // error, warning, info, debug

    private function __construct() {
        $this->log_enabled = get_option('twivco_commission_logging_enabled', true);
        $this->log_level = get_option('twivco_commission_log_level', 'error');
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log error messages
     */
    public function error($message, $context = array()) {
        $this->log('error', $message, $context);
    }

    /**
     * Log warning messages
     */
    public function warning($message, $context = array()) {
        $this->log('warning', $message, $context);
    }

    /**
     * Log info messages
     */
    public function info($message, $context = array()) {
        $this->log('info', $message, $context);
    }

    /**
     * Log debug messages
     */
    public function debug($message, $context = array()) {
        $this->log('debug', $message, $context);
    }

    /**
     * Main logging method
     */
    private function log($level, $message, $context = array()) {
        if (!$this->log_enabled || !$this->should_log_level($level)) {
            return;
        }

        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        );

        // Log to WordPress debug.log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[WC Commission %s] %s %s',
                $log_entry['level'],
                $log_entry['message'],
                !empty($context) ? '- Context: ' . wp_json_encode($context) : ''
            ));
        }

        // Store in database for admin viewing
        $this->store_log_entry($log_entry);

        // Send critical errors to external API if needed
        if ($level === 'error') {
            $this->maybe_send_error_to_api($log_entry);
        }
    }

    /**
     * Check if we should log this level
     */
    private function should_log_level($level) {
        $levels = array('error' => 0, 'warning' => 1, 'info' => 2, 'debug' => 3);
        $current_level = $levels[$this->log_level] ?? 0;
        $message_level = $levels[$level] ?? 0;

        return $message_level <= $current_level;
    }

    /**
     * Store log entry in WordPress options (last 100 entries)
     */
    private function store_log_entry($log_entry) {
        $logs = get_option('twivco_commission_logs', array());

        // Keep only last 100 entries
        if (count($logs) >= 100) {
            $logs = array_slice($logs, -99);
        }

        $logs[] = $log_entry;
        update_option('twivco_commission_logs', $logs, false);
    }

    /**
     * Send critical errors to external API for monitoring
     */
    private function maybe_send_error_to_api($log_entry) {
        // Only send API connection errors and sync failures
        if (strpos($log_entry['message'], 'API') === false &&
            strpos($log_entry['message'], 'sync') === false) {
            return;
        }

        try {
            $api = TWIVCO_Commission_Manager_API_Connection::get_instance();
            $api->make_api_request('POST', '/logs/error', array(
                'shopId' => $api->get_shop_id(),
                'error' => $log_entry,
                'wordpress_version' => get_bloginfo('version'),
                'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : 'unknown',
                'plugin_version' => TWIVCO_COMMISSION_MANAGER_VERSION
            ));
        } catch (Exception $e) {
            // Don't create infinite loops - just log to PHP error log
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WC Commission: Failed to send error to API - ' . $e->getMessage());
            }
        }
    }

    /**
     * Get recent logs for admin display
     */
    public function get_recent_logs($limit = 50) {
        $logs = get_option('twivco_commission_logs', array());
        return array_slice($logs, -$limit);
    }

    /**
     * Clear all stored logs
     */
    public function clear_logs() {
        delete_option('twivco_commission_logs');
    }

    /**
     * Log API request/response for debugging
     */
    public function log_api_call($method, $endpoint, $request_data, $response_data, $duration) {
        $this->debug('API Call', array(
            'method' => $method,
            'endpoint' => $endpoint,
            'request_size' => strlen(wp_json_encode($request_data)),
            'response_size' => strlen(wp_json_encode($response_data)),
            'duration_ms' => round($duration * 1000, 2),
            'success' => !isset($response_data['error'])
        ));
    }
}