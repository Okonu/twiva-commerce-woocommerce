<?php

class
TWIVCO_Commission_Manager_API_Connection {
    
    private $api_base_url;
    private $shop_id;
    private static $instance = null;
    private $last_connection_test = null;
    private $connection_cache_ttl = 300;

    private function __construct() {
        $this->api_base_url = defined('WC_COMMISSION_API_URL') ?
            WC_COMMISSION_API_URL : 'https://commerce.twiva.com/api';
        $this->shop_id = $this->generate_shop_id();
    }
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function generate_shop_id() {
        $site_url = get_site_url();
        return 'shop_' . md5($site_url);
    }
    
    public function get_shop_id() {
        return $this->shop_id;
    }
    
    /**
     * Register shop with external API
     */
    public function register_shop() {
        $shop_data = array(
            'shopId' => $this->shop_id,
            'shopUrl' => get_site_url(),
            'shopName' => get_bloginfo('name'),
            'platform' => 'woocommerce',
            'registeredAt' => gmdate('Y-m-d H:i:s')
        );
        
        return $this->make_api_request('POST', '/shops/register', $shop_data);
    }
    
    /**
     * Save/update product via API
     */
    public function save_product($product_data) {
        $endpoint = '/products/sync';
        return $this->make_api_request('POST', $endpoint, $product_data);
    }
    
    /**
     * Save/update commission via API
     */
    public function save_commission($commission_data) {
        $endpoint = '/commissions/sync';
        return $this->make_api_request('POST', $endpoint, $commission_data);
    }
    
    /**
     * Delete product via API
     */
    public function delete_product($product_id) {
        $endpoint = '/products/delete';
        $data = array(
            'shopId' => $this->shop_id,
            'productId' => $product_id
        );
        return $this->make_api_request('DELETE', $endpoint, $data);
    }
    
    /**
     * Get products from API
     */
    public function get_products($params = array()) {
        $params['shopId'] = $this->shop_id;
        $endpoint = '/products?' . http_build_query($params);
        return $this->make_api_request('GET', $endpoint);
    }
    
    /**
     * Get commissions from API
     */
    public function get_commissions($params = array()) {
        $params['shopId'] = $this->shop_id;
        $endpoint = '/commissions?' . http_build_query($params);
        return $this->make_api_request('GET', $endpoint);
    }
    
    /**
     * Cleanup deleted products via API
     */
    public function cleanup_deleted_products($product_ids_to_keep) {
        $endpoint = '/products/cleanup';
        $data = array(
            'shopId' => $this->shop_id,
            'activeProductIds' => $product_ids_to_keep
        );
        return $this->make_api_request('POST', $endpoint, $data);
    }
    
    /**
     * Log sync operation via API
     */
    public function log_sync($log_data) {
        $log_data['shopId'] = $this->shop_id;
        $endpoint = '/sync/log';
        return $this->make_api_request('POST', $endpoint, $log_data);
    }
    
    /**
     * Make HTTP request to API
     */
    public function make_api_request($method, $endpoint, $data = null) {
        $url = $this->api_base_url . $endpoint;
        
        $args = array(
            'method' => $method,
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'WC-Commission-Manager/1.3.0',
                'X-Shop-ID' => $this->shop_id
            )
        );

        if ($data !== null && in_array($method, ['POST', 'PUT', 'DELETE'])) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . esc_html($response->get_error_message()));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        $parsed_response = json_decode($response_body, true);
        
        if ($response_code >= 200 && $response_code < 300) {
            return $parsed_response;
        } else {
            $error_message = isset($parsed_response['error']) ? $parsed_response['error'] : 'Unknown API error';
            throw new Exception('API request failed with status ' . esc_html($response_code) . ': ' . esc_html($error_message));
        }
    }
    
    /**
     * Test API connection with caching
     */
    public function test_connection() {
        if ($this->last_connection_test &&
            (time() - $this->last_connection_test['timestamp']) < $this->connection_cache_ttl) {
            return $this->last_connection_test['result'];
        }

        try {
            $endpoint = '/health';
            $response = $this->make_api_request('GET', $endpoint);
            $result = array('success' => true, 'response' => $response);
        } catch (Exception $e) {
            $result = array('success' => false, 'error' => $e->getMessage());
        }

        $this->last_connection_test = array(
            'timestamp' => time(),
            'result' => $result
        );

        return $result;
    }
    
    
    /**
     * Send order for attribution processing
     */
    public function send_order_for_attribution($order_data) {
        $endpoint = '/orders/attribution';
        return $this->make_api_request('POST', $endpoint, $order_data);
    }
    
    /**
     * Record a sale for commission tracking
     */
    public function record_sale($sale_data) {
        $endpoint = '/sales/record';
        return $this->make_api_request('POST', $endpoint, $sale_data);
    }
    
    /**
     * Get smartlink tracking data for a tracking ID
     */
    public function get_smartlink_data($track_id) {
        $endpoint = '/smartlinks/track/' . urlencode($track_id);
        return $this->make_api_request('GET', $endpoint);
    }
    
    /**
     * Update sale status (for refunds, cancellations, etc.)
     */
    public function update_sale_status($sale_data) {
        $endpoint = '/sales/update';
        return $this->make_api_request('PUT', $endpoint, $sale_data);
    }
    
    /**
     * Get attributed orders for reporting
     */
    public function get_attributed_orders($track_id = null, $start_date = null, $end_date = null) {
        $params = array('shopId' => $this->shop_id);
        if ($track_id) $params['trackId'] = $track_id;
        if ($start_date) $params['startDate'] = $start_date;
        if ($end_date) $params['endDate'] = $end_date;

        $endpoint = '/orders/attributed?' . http_build_query($params);
        return $this->make_api_request('GET', $endpoint);
    }

    public function insert($table, $data) {
        switch ($table) {
            case 'twivco_commission_products':
                return $this->save_product($data);
            case 'twivco_commission_product_commissions':
                return $this->save_commission($data);
            case 'sync_logs':
                return $this->log_sync($data);
            default:
                throw new Exception('Unknown table for API insert: ' . esc_html($table));
        }
    }
    
    public function update($table, $data, $where) {
        return $this->insert($table, array_merge($data, $where));
    }
    
    public function delete($table, $where) {
        switch ($table) {
            case 'twivco_commission_products':
            case 'twivco_commission_product_commissions':
                if (isset($where['productId'])) {
                    return $this->delete_product($where['productId']);
                }
                break;
        }
        throw new Exception('Delete operation not supported for table: ' . esc_html($table));
    }
    
    public function get_results($table, $where = array(), $options = array()) {
        switch ($table) {
            case 'twivco_commission_products':
                return $this->get_products($where);
            case 'twivco_commission_product_commissions':
                return $this->get_commissions($where);
            default:
                return array();
        }
    }

    public function get_row($table, $where = array(), $options = array()) {
        $results = $this->get_results($table, $where, array_merge($options, array('limit' => 1)));
        return (is_array($results) && isset($results[0])) ? $results[0] : null;
    }

    /**
     * Regenerate one-time code for shop
     */
    public function regenerate_code() {
        $endpoint = '/shops/regenerate-code';
        $data = array('shopId' => $this->shop_id);
        return $this->make_api_request('POST', $endpoint, $data);
    }

    /**
     * Get validation status for this shop
     */
    public function get_validation_status() {
        $endpoint = '/shops/' . urlencode($this->shop_id) . '/validation-status';
        return $this->make_api_request('GET', $endpoint);
    }

    /**
     * Get shop's one-time code from registration response
     */
    public function get_shop_code() {
        try {
            $status = $this->get_validation_status();
            if ($status && isset($status['success']) && $status['success']) {
                return $status;
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
}
