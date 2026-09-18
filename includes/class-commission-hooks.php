<?php

/**
 * Commission Hooks Manager
 * Handles WordPress hooks for automatic commission recalculation and smartlink tracking
 */
class TWIVCO_Commission_Manager_Hooks {
    
    private $calculator;
    
    public function __construct() {
        $this->calculator = new TWIVCO_Commission_Manager_Calculator();
    }
    
    /**
     * Initialize all hooks
     */
    public function init() {
        add_action('woocommerce_update_product', array($this, 'handle_product_update'), 10, 1);
        add_action('woocommerce_new_product', array($this, 'handle_product_create'), 10, 1);

        add_action('updated_post_meta', array($this, 'handle_meta_update'), 10, 4);
        add_action('added_post_meta', array($this, 'handle_meta_update'), 10, 4);

        add_action('set_object_terms', array($this, 'handle_category_change'), 10, 6);

        add_action('admin_init', array($this, 'check_commission_rates_changes'));

        add_action('wp_ajax_twivco_commission_recalculate_product', array($this, 'ajax_recalculate_product'));
        add_action('wp_ajax_twivco_commission_recalculate_all', array($this, 'ajax_recalculate_all'));

        add_action('template_redirect', array($this, 'handle_smart_link_tracking'));

        add_action('woocommerce_checkout_order_processed', array($this, 'store_checkout_tracking'), 5, 3);
        add_action('woocommerce_new_order', array($this, 'store_checkout_tracking_fallback'), 5, 1);
        add_action('woocommerce_checkout_create_order', array($this, 'store_checkout_tracking_early'), 5, 2);
        add_action('woocommerce_thankyou', array($this, 'store_checkout_tracking_thankyou'), 5, 1);
    }
    
    /**
     * Handle product creation
     */
    public function handle_product_create($product_id) {
        $this->recalculate_product_commission($product_id, 'product_created');
    }
    
    /**
     * Handle product updates
     */
    public function handle_product_update($product_id) {
        $this->recalculate_product_commission($product_id, 'product_updated');
    }
    
    /**
     * Handle product meta updates (price changes)
     */
    public function handle_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
        if (get_post_type($post_id) !== 'product') {
            return;
        }
        
        $price_fields = array('_price', '_regular_price', '_sale_price');
        if (!in_array($meta_key, $price_fields)) {
            return;
        }
        
        $this->recalculate_product_commission($post_id, 'price_changed');
    }
    
    /**
     * Handle category changes
     */
    public function handle_category_change($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
        if ($taxonomy !== 'product_cat') {
            return;
        }
        
        if ($old_tt_ids === $tt_ids) {
            return;
        }
        
        $this->recalculate_product_commission($object_id, 'categories_changed');
    }
    
    /**
     * Recalculate commission for a specific product
     */
    private function recalculate_product_commission($product_id, $trigger) {
        try {
            $product = wc_get_product($product_id);
            if (!$product) {
                return false;
            }
            
            $result = $this->calculator->calculate_product_commission($product_id);
            
            if ($result && $result['success']) {
                do_action('twivco_commission_product_recalculated', $product_id, $result, $trigger);
                return $result;
            }
            
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if commission rates file has changed
     */
    public function check_commission_rates_changes() {
        $rates_file = TWIVCO_COMMISSION_MANAGER_PLUGIN_PATH . 'commission-rates.json';
        
        if (!file_exists($rates_file)) {
            return;
        }
        
        $last_modified = get_option('twivco_commission_rates_last_modified', 0);
        $current_modified = filemtime($rates_file);
        
        if ($current_modified > $last_modified) {
            update_option('twivco_commission_rates_last_modified', $current_modified);
            do_action('twivco_commission_rates_changed');
        }
    }
    
    /**
     * AJAX handler for single product recalculation
     */
    public function ajax_recalculate_product() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized', 'Error', array('response' => 403));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'twivco_commission_recalculate')) {
            wp_die('Invalid nonce', 'Error', array('response' => 400));
        }

        $product_id = isset($_POST['product_id']) ? intval(sanitize_text_field(wp_unslash($_POST['product_id']))) : 0;
        if (!$product_id) {
            wp_die('Invalid product ID', 'Error', array('response' => 400));
        }
        
        $result = $this->recalculate_product_commission($product_id, 'manual_ajax');
        
        if ($result && $result['success']) {
            wp_send_json_success(array(
                'message' => 'Commission recalculated successfully',
                'commission_rate' => $result['commission_rate'],
                'commission_amount' => $result['commission_amount'],
                'user_message' => $result['user_message']
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to recalculate commission',
                'error' => isset($result['error']) ? $result['error'] : 'Unknown error'
            ));
        }
    }
    
    /**
     * AJAX handler for bulk recalculation
     */
    public function ajax_recalculate_all() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized', 'Error', array('response' => 403));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'twivco_commission_recalculate')) {
            wp_die('Invalid nonce', 'Error', array('response' => 400));
        }

        wp_schedule_single_event(time() + 1, 'twivco_commission_recalculate_all_commissions');
        add_action('twivco_commission_recalculate_all_commissions', array($this, 'recalculate_all_commissions'));
        
        wp_send_json_success(array(
            'message' => 'Bulk commission recalculation started in background'
        ));
    }
    
    /**
     * Handle smart link tracking
     */
    public function handle_smart_link_tracking() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WC Commission: handle_smart_link_tracking called. Query params: ' . print_r($_GET, true));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public affiliate tracking, no auth needed
        if (isset($_GET['wc_track']) && !empty($_GET['wc_track'])) {
            $track_id = sanitize_text_field(wp_unslash($_GET['wc_track']));
            $device_info = $this->get_device_fingerprint();

            $tracking_data = array(
                'track_id' => $track_id,
                'timestamp' => time(),
                'device_info' => $device_info
            );

            setcookie(
                'twivco_commission_track', 
                json_encode($tracking_data), 
                time() + (30 * 24 * 60 * 60),
                '/',
                '',
                is_ssl(),
                true
            );

            if (WC()->session) {
                WC()->session->set('twivco_commission_track_id', $track_id);
                WC()->session->set('twivco_commission_track_timestamp', time());
            }

            $redirect_url = remove_query_arg('wc_track');
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Store tracking data early in checkout process
     */
    public function store_checkout_tracking_early($order, $data) {
        $this->store_tracking_data_in_order($order);
    }

    /**
     * Store tracking data on thank you page
     */
    public function store_checkout_tracking_thankyou($order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $this->store_tracking_data_in_order($order);
        }
    }

    /**
     * Store checkout tracking data in order meta
     */
    public function store_checkout_tracking($order_id, $posted_data = null, $order = null) {
        $order = $order ?: wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if ($order->get_meta('_twivco_commission_track_id')) {
            return;
        }

        $tracking_data = $this->get_session_tracking_data();
        
        if ($tracking_data) {
            $order->update_meta_data('_twivco_commission_track_id', $tracking_data['track_id']);

            $device_info = $this->get_device_fingerprint();
            $order->update_meta_data('_twivco_commission_device_info', $device_info);

            if (isset($_SERVER['HTTP_REFERER'])) {
                $order->update_meta_data('_twivco_commission_referrer', esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])));
            }
            
            $order->save();
        }
    }
    
    /**
     * Fallback method to store tracking data when order is created
     */
    public function store_checkout_tracking_fallback($order_id) {
        $this->store_checkout_tracking($order_id);
    }

    /**
     * Reusable method to store tracking data in order
     */
    private function store_tracking_data_in_order($order) {
        // Check if tracking data is already stored
        if ($order->get_meta('_twivco_commission_track_id')) {
            return true;
        }

        // Get tracking data from session/cookies with multiple attempts
        $tracking_data = $this->get_session_tracking_data();

        if (!$tracking_data) {
            // Try again with a delay to ensure cookies are available
            usleep(100000); // 100ms delay
            $tracking_data = $this->get_session_tracking_data();
        }

        if ($tracking_data) {
            // Store tracking ID in order meta
            $order->update_meta_data('_twivco_commission_track_id', $tracking_data['track_id']);
            $order->update_meta_data('_twivco_commission_track_timestamp', $tracking_data['timestamp']);
            $order->update_meta_data('_twivco_commission_track_source', $tracking_data['source']);
            $order->update_meta_data('_twivco_commission_track_stored_at', current_time('mysql'));

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WC Commission: Stored tracking data for order #{$order->get_id()}: {$tracking_data['track_id']} (source: {$tracking_data['source']})");
            }

            $order->save();
            return true;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("WC Commission: No tracking data available for order #{$order->get_id()}");
        }
        return false;
    }
    
    /**
     * Get tracking data from session or cookies
     */
    private function get_session_tracking_data() {
        // Check for tracking cookie
        if (isset($_COOKIE['twivco_commission_track'])) {
            $cookie_data = json_decode(sanitize_text_field(wp_unslash($_COOKIE['twivco_commission_track'])), true);
            if ($cookie_data && isset($cookie_data['track_id'])) {
                return array(
                    'track_id' => $cookie_data['track_id'],
                    'timestamp' => $cookie_data['timestamp'] ?? time(),
                    'source' => 'cookie'
                );
            }
        }
        
        // Check session data
        if (WC()->session && WC()->session->get('twivco_commission_track_id')) {
            return array(
                'track_id' => WC()->session->get('twivco_commission_track_id'),
                'timestamp' => WC()->session->get('twivco_commission_track_timestamp', time()),
                'source' => 'session'
            );
        }
        
        return false;
    }
    
    /**
     * Get device fingerprint for tracking
     */
    private function get_device_fingerprint() {
        return array(
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            'accept_language' => isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE'])) : '',
            'timestamp' => time()
        );
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$key]));
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
    }
    
    /**
     * Recalculate all commissions (background task)
     */
    public function recalculate_all_commissions() {
        $products = get_posts(array(
            'post_type' => 'product',
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ));
        
        foreach ($products as $product_id) {
            $this->recalculate_product_commission($product_id, 'bulk_recalculation');
        }
        
        do_action('twivco_commission_bulk_recalculation_complete', count($products));
    }
}