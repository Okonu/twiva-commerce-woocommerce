<?php

class TWIVCO_Commission_Manager_DB_Factory {

    public static function get_connection() {
        // Always use API connection
        return TWIVCO_Commission_Manager_API_Connection::get_instance();
    }

    public static function get_status() {
        try {
            $connection = self::get_connection();
            $stats = method_exists($connection, 'get_stats') ? $connection->get_stats() : array();

            return array(
                'connection_type' => 'API',
                'connection_status' => 'connected',
                'stats' => $stats
            );
        } catch (Exception $e) {
            return array(
                'connection_type' => 'API',
                'connection_status' => 'failed',
                'error' => esc_html($e->getMessage())
            );
        }
    }

    public static function test_connection() {
        try {
            $connection = self::get_connection();
            return $connection->test_connection();
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'API connection test failed: ' . esc_html($e->getMessage())
            );
        }
    }
}