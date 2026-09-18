<?php

/**
 * Sales Tracker
 * Handles tracking of actual sales and commission calculations
 */
class TWIVCO_Commission_Manager_Sales_Tracker {
    
    private $shop_id;
    private $api;
    
    public function __construct() {
        try {
            $this->api = TWIVCO_Commission_Manager_API_Connection::get_instance();
            $this->shop_id = $this->api->get_shop_id();

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WC Commission: Sales Tracker initialized with shop ID: ' . ($this->shop_id ?: 'NULL'));
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WC Commission: Sales Tracker initialization failed: ' . $e->getMessage());
            }
            // Set defaults to prevent fatal errors
            $this->api = null;
            $this->shop_id = null;
        }
    }
    
    /**
     * Initialize sales tracking hooks
     */
    public function init() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WC Commission: Sales Tracker init() called - registering order hooks');
        }

        // Order status hooks - track ALL orders
        add_action('woocommerce_order_status_pending', array($this, 'track_order_sales'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this, 'track_order_sales'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this, 'track_order_sales'), 10, 1);
        add_action('woocommerce_order_status_on-hold', array($this, 'track_order_sales'), 10, 1);
        add_action('woocommerce_payment_complete', array($this, 'track_order_sales'), 10, 1);
        add_action('woocommerce_new_order', array($this, 'track_order_sales'), 10, 1);

        // Order status change tracking
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);

        // Refund handling
        add_action('woocommerce_order_refunded', array($this, 'handle_order_refund'), 10, 2);
        add_action('woocommerce_order_status_refunded', array($this, 'handle_order_status_refunded'), 10, 1);

        // Manual reprocessing hooks
        add_action('wp_ajax_twivco_commission_reprocess_order', array($this, 'ajax_reprocess_order'));
        add_action('wp_ajax_twivco_commission_reprocess_all_orders', array($this, 'ajax_reprocess_all_orders'));

        // Admin action to reprocess orders
        add_action('admin_post_twivco_commission_reprocess_orders', array($this, 'admin_reprocess_orders'));

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WC Commission: Sales Tracker hooks registered successfully');
        }
    }
    
    /**
     * Track sales for completed orders
     */
    public function track_order_sales($order_id) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("WC Commission: track_order_sales called for order #{$order_id}");
        }
        $this->process_order_for_sales_tracking($order_id, 'order_completed');
    }
    
    /**
     * Process order for sales tracking
     */
    private function process_order_for_sales_tracking($order_id, $trigger) {
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WC Commission: Processing order #{$order_id} for sales tracking (trigger: {$trigger})");
                error_log("WC Commission: Sales Tracker API available: " . ($this->api ? 'YES' : 'NO'));
                error_log("WC Commission: Sales Tracker Shop ID: " . ($this->shop_id ?: 'NULL'));
            }

            if (!$this->api || !$this->shop_id) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("WC Commission: API or Shop ID not available, attempting to reinitialize...");
                }

                // Try to reinitialize the API connection
                try {
                    $this->api = TWIVCO_Commission_Manager_API_Connection::get_instance();
                    $this->shop_id = $this->api->get_shop_id();

                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("WC Commission: Reinitialized API - Shop ID: " . ($this->shop_id ?: 'NULL'));
                    }
                } catch (Exception $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("WC Commission: Failed to reinitialize API: " . $e->getMessage());
                    }
                }

                // If still not available, skip processing
                if (!$this->api || !$this->shop_id) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("WC Commission: Cannot process order #{$order_id} - API or Shop ID still not available");
                    }
                    return;
                }
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("WC Commission: Order #{$order_id} not found");
                }
                return;
            }
            
            // Check if already processed
            if ($order->get_meta('_twivco_commission_processed')) {
                return;
            }
            
            // Get tracking data from order meta
            $track_id = $order->get_meta('_twivco_commission_track_id');
            
            if (!$track_id) {
                // Try to get from session/cookies as fallback
                $tracking_data = $this->get_session_tracking_data();
                if ($tracking_data) {
                    $track_id = $tracking_data['track_id'];
                    // Store in order meta for future reference
                    $order->update_meta_data('_twivco_commission_track_id', $track_id);
                    $order->save();
                }
            }

            if (!$track_id) {
                // ENHANCED: Try harder to find tracking data
                // Check if this order might be from a known smart link
                $customer_email = $order->get_billing_email();
                $order_total = $order->get_total();
                $order_date = $order->get_date_created();

                // For now, if no tracking found, try to match with recent smart link clicks
                $track_id = $this->attempt_smart_link_matching($order);

                if ($track_id) {
                    $order->update_meta_data('_twivco_commission_track_id', $track_id);
                    $order->update_meta_data('_twivco_commission_track_method', 'smart_matching');
                    $order->save();
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("WC Commission: Order #{$order_id} matched to tracking ID: {$track_id}");
                    }
                }
            }

            if (!$track_id) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("WC Commission: Order #{$order_id} has no tracking data available");
                }
                return; // No tracking data available
            }
            
            // Process each item in the order
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $quantity = $item->get_quantity();
                $total = $item->get_total();
                
                $this->record_sale($order, $item, $track_id, $trigger);
            }
            
            // Mark as processed
            $order->update_meta_data('_twivco_commission_processed', time());
            $order->save();

            // Send webhook to backend for enhanced attribution
            $this->send_order_webhook_to_backend($order, $track_id);

            // Send basket order data for complete order tracking
            $this->send_basket_order_to_backend($order, $track_id);

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WC Commission: Error processing order #{$order_id}: " . $e->getMessage());
            }
        }
    }

    /**
     * AJAX handler to reprocess a specific order
     */
    public function ajax_reprocess_order() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'twivco_commission_reprocess')) {
            wp_die('Security check failed');
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error('Invalid order ID');
        }

        try {
            // Force reprocess by removing processed flag
            $order = wc_get_order($order_id);
            if ($order) {
                $order->delete_meta_data('_twivco_commission_processed');
                $order->save();

                $this->process_order_for_sales_tracking($order_id, 'manual_reprocess');
                wp_send_json_success("Order #{$order_id} reprocessed successfully");
            } else {
                wp_send_json_error("Order #{$order_id} not found");
            }
        } catch (Exception $e) {
            wp_send_json_error("Failed to reprocess order: " . $e->getMessage());
        }
    }

    /**
     * AJAX handler to reprocess all recent orders
     */
    public function ajax_reprocess_all_orders() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }

        try {
            $processed = 0;
            $orders = wc_get_orders(array(
                'limit' => 50,
                'orderby' => 'date',
                'order' => 'DESC',
                'date_created' => '>' . (time() - (7 * 24 * 60 * 60)) // Last 7 days
            ));

            foreach ($orders as $order) {
                // Remove processed flag and reprocess
                $order->delete_meta_data('_twivco_commission_processed');
                $order->save();

                $this->process_order_for_sales_tracking($order->get_id(), 'bulk_reprocess');
                $processed++;
            }

            wp_send_json_success("Reprocessed {$processed} orders");
        } catch (Exception $e) {
            wp_send_json_error("Failed to reprocess orders: " . $e->getMessage());
        }
    }

    /**
     * Admin handler to reprocess orders via URL
     */
    public function admin_reprocess_orders() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin debugging function, nonce not required
        $order_ids = isset($_GET['order_ids']) ? explode(',', sanitize_text_field(wp_unslash($_GET['order_ids']))) : array();

        if (empty($order_ids)) {
            wp_die('No order IDs provided. Use: ?order_ids=123,124,125');
        }

        $processed = array();
        foreach ($order_ids as $order_id) {
            $order_id = intval($order_id);
            if ($order_id) {
                try {
                    $order = wc_get_order($order_id);
                    if ($order) {
                        $order->delete_meta_data('_twivco_commission_processed');
                        $order->save();

                        $this->process_order_for_sales_tracking($order_id, 'admin_reprocess');
                        $processed[] = $order_id;
                    }
                } catch (Exception $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("WC Commission: Failed to reprocess order #{$order_id}: " . $e->getMessage());
                    }
                }
            }
        }

        $admin_url = admin_url('admin.php?page=twiva-commerce&reprocessed=' . implode(',', $processed));
        wp_redirect($admin_url);
        exit;
    }

    /**
     * Attempt to match order with recent smart link clicks
     */
    private function attempt_smart_link_matching($order) {
        try {
            // Get order details
            $order_id = $order->get_id();
            $order_date = $order->get_date_created();
            $customer_email = $order->get_billing_email();

            // Get order product info to match with smart links
            $order_items = $order->get_items();
            $first_item = current($order_items);
            $product = $first_item ? $first_item->get_product() : null;

            // Try to find recent smart link clicks from backend and match by product + timing
            $api = TWIVCO_Commission_Manager_API_Connection::get_instance();
            $recent_clicks = $api->make_request('GET', '/link-clicks/recent', array(
                'shopId' => $this->shop_id,
                'hours' => 48 // Extended window
            ));

            if ($recent_clicks && !empty($recent_clicks['data'])) {
                $order_timestamp = $order_date->getTimestamp();

                foreach ($recent_clicks['data'] as $click) {
                    $click_timestamp = strtotime($click['clicked_at']);
                    $time_diff = $order_timestamp - $click_timestamp;

                    // Match by timing (within 24 hours) and validate with smart link product
                    if ($time_diff > 0 && $time_diff < (24 * 60 * 60)) {
                        // Get smart link details to verify product match
                        $smart_link = $api->make_request('GET', '/smartlinks/track/' . $click['track_id']);

                        if ($smart_link && !empty($smart_link['data'])) {
                            $link_product_id = $smart_link['data']['productId'] ?? null;

                            // If we have a product match or no specific product constraint
                            if (!$link_product_id || ($product && $product->get_id() == $link_product_id)) {
                                if (defined('WP_DEBUG') && WP_DEBUG) {
                                    error_log("WC Commission: Matched order #{$order_id} to tracking ID: {$click['track_id']} based on timing and product");
                                }
                                return $click['track_id'];
                            }
                        }
                    }
                }
            }

            return null;

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WC Commission: Error in smart link matching: " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Record sale in backend
     */
    private function record_sale($order, $item, $track_id, $trigger) {
        try {
            $product = $item->get_product();
            if (!$product) {
                return false;
            }
            
            // Get affiliate ID from tracking
            $affiliate_data = $this->get_affiliate_from_tracking($track_id);
            if (!$affiliate_data) {
                // Store the sale anyway for later processing when API is available
                $this->store_pending_sale($order, $item, $track_id, $trigger);
                return false;
            }
            
            // Backend expects specific format - get first item for now, we can enhance later
            $first_item = current($order->get_items());
            $product = $first_item ? $first_item->get_product() : null;

            $sale_data = array(
                'shopId' => $this->shop_id,
                'orderId' => $order->get_id(),
                'productId' => $product ? $product->get_id() : 0,
                'productName' => $product ? $product->get_name() : 'Mixed Products',
                'affiliateId' => $affiliate_data['affiliate_id'],
                'trackingId' => $track_id,
                'quantity' => $first_item ? $first_item->get_quantity() : 1,
                'unitPrice' => $first_item ? floatval($first_item->get_total() / $first_item->get_quantity()) : 0,
                'totalAmount' => floatval($order->get_total()),
                'customerEmail' => $order->get_billing_email(), // Backend expects actual email
                'orderStatus' => $order->get_status(),
                'saleDate' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'currency' => $order->get_currency()
            );
            
            // Send to backend API
            $response = $this->api->make_api_request('POST', '/sales/record', $sale_data);

            if ($response && isset($response['success']) && $response['success']) {
                // Store attribution success in order meta
                $order->update_meta_data('_twivco_commission_attributed', array(
                    'sales_attribution_id' => $response['salesAttribution']['id'] ?? null,
                    'commission_earning_id' => $response['commissionEarning']['id'] ?? null,
                    'track_id' => $track_id,
                    'affiliate_id' => $affiliate_data['affiliate_id'],
                    'smartlink_id' => $response['smartLink']['id'] ?? null,
                    'commission_amount' => $response['commissionEarning']['amount'] ?? 0,
                    'attributed_at' => current_time('mysql')
                ));
                $order->save();

                $logger = TWIVCO_Commission_Manager_Logger::get_instance();
                $logger->info('Order successfully attributed to smartlink', array(
                    'order_id' => $order->get_id(),
                    'track_id' => $track_id,
                    'affiliate_id' => $affiliate_data['affiliate_id'],
                    'commission_amount' => $response['commissionEarning']['amount'] ?? 0
                ));

                return true;
            }

            return false;
            
        } catch (Exception $e) {
            // Error recording sale handled
            return false;
        }
    }
    
    /**
     * Get affiliate data from tracking ID with caching and fallback
     */
    private function get_affiliate_from_tracking($track_id) {
        // Check cache first
        $cache_key = 'twivco_commission_affiliate_' . md5($track_id);
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        try {
            $response = $this->api->make_api_request('GET', '/smartlinks/track/' . $track_id);

            if ($response && isset($response['success']) && $response['success'] && isset($response['data'])) {
                $affiliate_data = array(
                    'affiliate_id' => $response['data']['influencerId'] ?? $response['data']['affiliateId'] ?? null,
                    'smartlink_id' => $response['data']['id'] ?? null
                );

                // Cache for 1 hour
                set_transient($cache_key, $affiliate_data, 3600);
                return $affiliate_data;
            }

            // Cache negative result for 5 minutes to avoid repeated API calls
            set_transient($cache_key, false, 300);
            return false;

        } catch (Exception $e) {
            $logger = TWIVCO_Commission_Manager_Logger::get_instance();
            $logger->warning('Failed to get affiliate data for tracking ID', array(
                'track_id' => $track_id,
                'error' => $e->getMessage()
            ));

            // Cache failure for 5 minutes
            set_transient($cache_key, false, 300);
            return false;
        }
    }
    
    /**
     * Handle order status changes
     */
    public function handle_order_status_change($order_id, $from_status, $to_status, $order) {
        // Only process certain status changes
        $trigger_statuses = array('pending', 'on-hold', 'processing');
        $target_statuses = array('completed', 'processing');
        
        if (in_array($from_status, $trigger_statuses) && in_array($to_status, $target_statuses)) {
            $this->process_order_for_sales_tracking($order_id, "status_change_{$from_status}_to_{$to_status}");
        }
        
        // Update order status in backend if already recorded
        if ($order->get_meta('_twivco_commission_processed')) {
            $this->update_order_status_in_backend($order_id, $from_status, $to_status);
        }
    }
    
    /**
     * Update order status in backend for attributed orders only
     */
    private function update_order_status_in_backend($order_id, $from_status, $to_status) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Only update status for attributed orders
        $attribution_data = $order->get_meta('_twivco_commission_attributed');
        if (!$attribution_data || !isset($attribution_data['track_id'])) {
            return;
        }

        try {
            $update_data = array(
                'shopId' => $this->shop_id,
                'orderId' => $order_id,
                'trackingId' => $attribution_data['track_id'],
                'attributionId' => $attribution_data['attribution_id'] ?? null,
                'fromStatus' => $from_status,
                'toStatus' => $to_status,
                'statusChangeDate' => current_time('mysql'),
                'orderTotal' => floatval($order->get_total())
            );

            $this->api->make_api_request('PUT', '/sales/update', $update_data);

            $logger = TWIVCO_Commission_Manager_Logger::get_instance();
            $logger->info('Updated attributed order status', array(
                'order_id' => $order_id,
                'track_id' => $attribution_data['track_id'],
                'from_status' => $from_status,
                'to_status' => $to_status
            ));

        } catch (Exception $e) {
            $logger = TWIVCO_Commission_Manager_Logger::get_instance();
            $logger->warning('Failed to update attributed order status', array(
                'order_id' => $order_id,
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Handle order refunds
     */
    public function handle_order_refund($order_id, $refund_id) {
        $this->process_refund($order_id, $refund_id, 'order_refunded');
    }
    
    /**
     * Handle refunded status
     */
    public function handle_order_status_refunded($order_id) {
        $this->process_refund($order_id, null, 'status_refunded');
    }
    
    /**
     * Process refund for attributed orders only
     */
    private function process_refund($order_id, $refund_id, $trigger) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Only process refunds for attributed orders
        $attribution_data = $order->get_meta('_twivco_commission_attributed');
        if (!$attribution_data || !isset($attribution_data['track_id'])) {
            return;
        }

        try {
            $refund_amount = 0;
            if ($refund_id) {
                $refund = wc_get_order($refund_id);
                $refund_amount = $refund ? abs(floatval($refund->get_total())) : 0;
            }

            $refund_data = array(
                'shopId' => $this->shop_id,
                'orderId' => $order_id,
                'trackingId' => $attribution_data['track_id'],
                'attributionId' => $attribution_data['attribution_id'] ?? null,
                'refundId' => $refund_id,
                'refundAmount' => $refund_amount,
                'refundDate' => current_time('mysql'),
                'trigger' => $trigger,
                'orderTotal' => floatval($order->get_total())
            );

            $this->api->make_api_request('PUT', '/sales/update', $refund_data);

            $logger = TWIVCO_Commission_Manager_Logger::get_instance();
            $logger->info('Processed refund for attributed order', array(
                'order_id' => $order_id,
                'track_id' => $attribution_data['track_id'],
                'refund_amount' => $refund_amount
            ));

        } catch (Exception $e) {
            $logger = TWIVCO_Commission_Manager_Logger::get_instance();
            $logger->warning('Failed to process refund for attributed order', array(
                'order_id' => $order_id,
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Get tracking data from session or cookies (fallback)
     */
    private function get_session_tracking_data() {
        // Check for tracking cookie
        if (isset($_COOKIE['twivco_commission_track'])) {
            $cookie_data = json_decode(sanitize_text_field(wp_unslash($_COOKIE['twivco_commission_track'])), true);
            if ($cookie_data && isset($cookie_data['track_id'])) {
                return array(
                    'track_id' => sanitize_text_field($cookie_data['track_id']),
                    'timestamp' => absint($cookie_data['timestamp'] ?? time()),
                    'source' => 'cookie'
                );
            }
        }
        
        // Check session data
        if (WC()->session && WC()->session->get('twivco_commission_track_id')) {
            return array(
                'track_id' => sanitize_text_field(WC()->session->get('twivco_commission_track_id')),
                'timestamp' => absint(WC()->session->get('twivco_commission_track_timestamp', time())),
                'source' => 'session'
            );
        }
        
        return false;
    }

    /**
     * Store sale for later processing when API becomes available
     */
    private function store_pending_sale($order, $item, $track_id, $trigger) {
        $pending_sales = get_option('twivco_commission_pending_sales', array());

        $sale_data = array(
            'order_id' => $order->get_id(),
            'track_id' => $track_id,
            'order_status' => $order->get_status(),
            'order_total' => floatval($order->get_total()),
            'order_date' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'customer_email_hash' => hash('sha256', $order->get_billing_email()),
            'currency' => $order->get_currency(),
            'trigger' => $trigger,
            'stored_at' => current_time('mysql')
        );

        $pending_sales[] = $sale_data;

        // Keep only last 1000 pending sales to prevent unlimited growth
        if (count($pending_sales) > 1000) {
            $pending_sales = array_slice($pending_sales, -1000);
        }

        update_option('twivco_commission_pending_sales', $pending_sales, false);

        $logger = TWIVCO_Commission_Manager_Logger::get_instance();
        $logger->info('Stored pending sale for later processing', array(
            'order_id' => $order->get_id(),
            'track_id' => $track_id,
            'pending_count' => count($pending_sales)
        ));
    }

    /**
     * Process pending sales when API becomes available
     */
    public function process_pending_sales() {
        $pending_sales = get_option('twivco_commission_pending_sales', array());

        if (empty($pending_sales)) {
            return 0;
        }

        $processed = 0;
        $remaining_sales = array();

        foreach ($pending_sales as $sale_data) {
            // Try to get affiliate data again
            $affiliate_data = $this->get_affiliate_from_tracking($sale_data['track_id']);

            if ($affiliate_data) {
                // We can now process this attribution
                $api_sale_data = array(
                    'shopId' => $this->shop_id,
                    'orderId' => $sale_data['order_id'],
                    'productId' => 0, // We'll need to get this from the order
                    'productName' => 'Mixed Products',
                    'trackingId' => $sale_data['track_id'],
                    'affiliateId' => $affiliate_data['affiliate_id'],
                    'quantity' => 1,
                    'unitPrice' => $sale_data['order_total'],
                    'totalAmount' => $sale_data['order_total'],
                    'customerEmail' => $sale_data['customer_email_hash'],
                    'orderStatus' => $sale_data['order_status'],
                    'saleDate' => $sale_data['order_date'],
                    'currency' => $sale_data['currency']
                );

                try {
                    $response = $this->api->make_api_request('POST', '/sales/record', $api_sale_data);
                    if ($response && isset($response['success']) && $response['success']) {
                        // Update the actual order with attribution data
                        $order = wc_get_order($sale_data['order_id']);
                        if ($order) {
                            $order->update_meta_data('_twivco_commission_attributed', array(
                                'sales_attribution_id' => $response['salesAttribution']['id'] ?? null,
                                'commission_earning_id' => $response['commissionEarning']['id'] ?? null,
                                'track_id' => $sale_data['track_id'],
                                'affiliate_id' => $affiliate_data['affiliate_id'],
                                'smartlink_id' => $response['smartLink']['id'] ?? null,
                                'commission_amount' => $response['commissionEarning']['amount'] ?? 0,
                                'attributed_at' => current_time('mysql')
                            ));
                            $order->save();
                        }

                        $processed++;
                        continue; // Successfully processed, don't add to remaining
                    }
                } catch (Exception $e) {
                    // API still not working, keep for later
                }
            }

            // Keep this sale for later processing
            $remaining_sales[] = $sale_data;
        }

        // Update the pending sales list
        update_option('twivco_commission_pending_sales', $remaining_sales, false);

        if ($processed > 0) {
            $logger = TWIVCO_Commission_Manager_Logger::get_instance();
            $logger->info('Processed pending sales', array(
                'processed' => $processed,
                'remaining' => count($remaining_sales)
            ));
        }

        return $processed;
    }

    /**
     * Get all attributed orders for reporting
     */
    public function get_attributed_orders($args = array()) {
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'status' => 'any',
            'track_id' => null,
            'date_from' => null,
            'date_to' => null
        );

        $args = wp_parse_args($args, $defaults);

        $meta_query = array(
            array(
                'key' => '_twivco_commission_attributed',
                'compare' => 'EXISTS'
            )
        );

        $order_args = array(
            'limit' => $args['limit'],
            'offset' => $args['offset'],
            'meta_query' => $meta_query,
            'return' => 'objects'
        );

        if ($args['status'] !== 'any') {
            $order_args['status'] = $args['status'];
        }

        if ($args['date_from']) {
            $order_args['date_created'] = '>=' . $args['date_from'];
        }

        if ($args['date_to']) {
            $order_args['date_created'] = '<=' . $args['date_to'];
        }

        $orders = wc_get_orders($order_args);
        $attributed_orders = array();

        foreach ($orders as $order) {
            $attribution_data = $order->get_meta('_twivco_commission_attributed');

            // Filter by track_id if specified
            if ($args['track_id'] && $attribution_data['track_id'] !== $args['track_id']) {
                continue;
            }

            $attributed_orders[] = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'status' => $order->get_status(),
                'total' => floatval($order->get_total()),
                'currency' => $order->get_currency(),
                'date_created' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'customer_email' => $order->get_billing_email(),
                'track_id' => $attribution_data['track_id'] ?? '',
                'affiliate_id' => $attribution_data['affiliate_id'] ?? '',
                'smartlink_id' => $attribution_data['smartlink_id'] ?? '',
                'attribution_id' => $attribution_data['attribution_id'] ?? '',
                'attributed_at' => $attribution_data['attributed_at'] ?? '',
                'items' => $this->get_order_items_summary($order)
            );
        }

        return $attributed_orders;
    }

    /**
     * Get order items summary
     */
    private function get_order_items_summary($order) {
        $items = array();

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => floatval($item->get_total()),
                'product_id' => $product ? $product->get_id() : 0,
                'sku' => $product ? $product->get_sku() : ''
            );
        }

        return $items;
    }

    /**
     * Get attribution statistics
     */
    public function get_attribution_stats($track_id = null, $days = 30) {
        $date_from = gmdate('Y-m-d', strtotime("-{$days} days"));

        $args = array(
            'limit' => -1,
            'date_from' => $date_from
        );

        if ($track_id) {
            $args['track_id'] = $track_id;
        }

        $attributed_orders = $this->get_attributed_orders($args);

        $stats = array(
            'total_orders' => count($attributed_orders),
            'total_value' => 0,
            'completed_orders' => 0,
            'completed_value' => 0,
            'pending_orders' => 0,
            'pending_value' => 0,
            'refunded_orders' => 0,
            'refunded_value' => 0,
            'by_status' => array()
        );

        foreach ($attributed_orders as $order) {
            $stats['total_value'] += $order['total'];

            if (!isset($stats['by_status'][$order['status']])) {
                $stats['by_status'][$order['status']] = array('count' => 0, 'value' => 0);
            }

            $stats['by_status'][$order['status']]['count']++;
            $stats['by_status'][$order['status']]['value'] += $order['total'];

            switch ($order['status']) {
                case 'completed':
                case 'processing':
                    $stats['completed_orders']++;
                    $stats['completed_value'] += $order['total'];
                    break;
                case 'pending':
                case 'on-hold':
                    $stats['pending_orders']++;
                    $stats['pending_value'] += $order['total'];
                    break;
                case 'refunded':
                case 'cancelled':
                    $stats['refunded_orders']++;
                    $stats['refunded_value'] += $order['total'];
                    break;
            }
        }

        return $stats;
    }

    /**
     * Send order webhook to backend for enhanced attribution
     */
    private function send_order_webhook_to_backend($order, $track_id) {
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WC Commission: Attempting to send order webhook for order #{$order->get_id()} with track_id: {$track_id}");
            }

            // Don't send webhook if no tracking data
            if (!$track_id) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("WC Commission: No track_id provided for order webhook");
                }
                return;
            }

            // Check if API is available
            if (!$this->api) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("WC Commission: API not available for order webhook");
                }
                return;
            }

            // Get device info
            $device_info = $order->get_meta('_twivco_commission_device_info');

            // Prepare webhook data
            $webhook_data = array(
                'shop_id' => $this->shop_id,
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'order_status' => $order->get_status(),
                'order_total' => floatval($order->get_total()),
                'currency' => $order->get_currency(),
                'customer_email_hash' => hash('sha256', $order->get_billing_email()),
                'order_date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'track_id' => $track_id,
                'device_info' => $device_info,
                'referrer' => $order->get_meta('_twivco_commission_referrer'),
                'items' => array()
            );

            // Add order items
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $webhook_data['items'][] = array(
                    'product_id' => $product ? $product->get_id() : 0,
                    'product_name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'price' => floatval($item->get_total()),
                    'sku' => $product ? $product->get_sku() : ''
                );
            }

            // Send to backend
            $response = $this->api->make_api_request('POST', '/webhooks/order-received', $webhook_data);

            if ($response && isset($response['success']) && $response['success']) {
                // Store webhook success in order meta
                $order->update_meta_data('_twivco_commission_webhook_sent', array(
                    'sent_at' => current_time('mysql'),
                    'webhook_data' => $webhook_data,
                    'response' => $response
                ));
                $order->save();

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("WC Commission: Order webhook sent successfully for order #{$order->get_id()}");
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("WC Commission: Failed to send order webhook for order #{$order->get_id()}");
                }
            }

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WC Commission: Error sending order webhook: " . $e->getMessage());
            }
        }
    }

    /**
     * Send complete basket order data to backend API for enhanced attribution
     */
    private function send_basket_order_to_backend($order, $track_id) {
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WC Commission: Attempting to send basket order data for order #{$order->get_id()} with track_id: {$track_id}");
            }

            // Don't send if no tracking data
            if (!$track_id) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("WC Commission: No track_id provided for basket order");
                }
                return;
            }

            // Check if API is available
            if (!$this->api) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("WC Commission: API not available for basket order");
                }
                return;
            }

            // Get device and attribution info
            $device_info = $order->get_meta('_twivco_commission_device_info');
            $track_method = $order->get_meta('_twivco_commission_track_method');

            // Determine attribution confidence based on tracking method
            $attribution_confidence = $this->determine_attribution_confidence($track_method, $device_info);

            // Prepare comprehensive basket order data
            $basket_order_data = array(
                'shop_id' => $this->shop_id,
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'customer_email' => $order->get_billing_email(),
                'order_total' => floatval($order->get_total()),
                'currency' => $order->get_currency() ?: 'KSH', // Default to KSH
                'order_date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'track_id' => $track_id,
                'attribution_confidence' => $attribution_confidence,
                'status' => $this->map_wc_status_to_basket_status($order->get_status()),
                'platform' => 'woocommerce',
                'device_info' => $device_info,
                'referrer' => $order->get_meta('_twivco_commission_referrer'),
                'basket_items' => array(),
                'customer_info' => $this->prepare_customer_info($order)
            );

            // Add all basket items with detailed information
            $total_items = 0;
            $commissioned_items_count = 0;
            $total_commission_value = 0;

            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                $product_id = $product ? $product->get_id() : 0;
                $item_total = floatval($item->get_total());
                $quantity = $item->get_quantity();

                // Check if this product is commissioned (has been synced to API)
                $is_commissioned = $this->is_product_commissioned($product_id);
                $commission_rate = 0;
                $commission_value = 0;

                if ($is_commissioned) {
                    // Get commission rate from product meta or API
                    $commission_rate = $this->get_product_commission_rate($product_id);
                    $commission_value = ($item_total * $commission_rate) / 100;
                    $commissioned_items_count += $quantity;
                    $total_commission_value += $commission_value;
                }

                $basket_item_data = array(
                    'product_id' => $product_id,
                    'product_name' => $item->get_name(),
                    'sku' => $product ? $product->get_sku() : '',
                    'quantity' => $quantity,
                    'unit_price' => floatval($item->get_total() / $quantity),
                    'total_price' => $item_total,
                    'is_commissioned' => $is_commissioned,
                    'commission_rate' => $commission_rate,
                    'commission_value' => $commission_value,
                    'product_url' => $product ? get_permalink($product_id) : '',
                    'product_image' => $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '',
                    'categories' => $product ? $this->get_product_categories($product) : array(),
                );

                $basket_order_data['basket_items'][] = $basket_item_data;
                $total_items += $quantity;
            }

            // Add commission summary
            $basket_order_data['commissioned_items_count'] = $commissioned_items_count;
            $basket_order_data['total_commission_value'] = $total_commission_value;
            $basket_order_data['influencer_commission_80_percent'] = $total_commission_value * 0.8;
            $basket_order_data['twiva_commission_20_percent'] = $total_commission_value * 0.2;
            $basket_order_data['total_items_count'] = $total_items;

            // Add order-level metadata
            $basket_order_data['order_meta'] = array(
                'payment_method' => $order->get_payment_method(),
                'payment_method_title' => $order->get_payment_method_title(),
                'shipping_total' => floatval($order->get_shipping_total()),
                'tax_total' => floatval($order->get_total_tax()),
                'discount_total' => floatval($order->get_discount_total()),
                'order_key' => $order->get_order_key(),
                'billing_country' => $order->get_billing_country(),
                'shipping_country' => $order->get_shipping_country(),
            );

            // Send to backend basket orders API
            $response = $this->api->make_api_request('POST', '/basket-orders/create', $basket_order_data);

            if ($response && isset($response['success']) && $response['success']) {
                // Store basket order success in order meta
                $order->update_meta_data('_twivco_commission_basket_order_sent', array(
                    'sent_at' => current_time('mysql'),
                    'basket_order_id' => isset($response['basket_order_id']) ? $response['basket_order_id'] : null,
                    'commission_summary' => array(
                        'total_commission' => $total_commission_value,
                        'influencer_80_percent' => $total_commission_value * 0.8,
                        'twiva_20_percent' => $total_commission_value * 0.2,
                        'commissioned_items' => $commissioned_items_count,
                        'total_items' => $total_items
                    ),
                    'response' => $response
                ));
                $order->save();

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("WC Commission: Basket order sent successfully for order #{$order->get_id()} - Commission: {$total_commission_value}, Items: {$commissioned_items_count}/{$total_items}");
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("WC Commission: Failed to send basket order for order #{$order->get_id()}. Response: " . wp_json_encode($response));
                }
            }

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WC Commission: Error sending basket order: " . $e->getMessage());
            }
        }
    }

    /**
     * Determine attribution confidence based on tracking method and device info
     */
    private function determine_attribution_confidence($track_method, $device_info) {
        if ($track_method === 'direct_link_click' && !empty($device_info)) {
            return 'high';
        } elseif ($track_method === 'smart_matching' && !empty($device_info)) {
            return 'medium';
        } elseif (!empty($track_method)) {
            return 'low';
        }
        return 'very_low';
    }

    /**
     * Map WooCommerce order status to basket order status
     */
    private function map_wc_status_to_basket_status($wc_status) {
        $status_map = array(
            'pending' => 'pending',
            'processing' => 'confirmed',
            'completed' => 'confirmed',
            'on-hold' => 'pending',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'failed' => 'cancelled'
        );

        return isset($status_map[$wc_status]) ? $status_map[$wc_status] : 'pending';
    }

    /**
     * Check if a product is commissioned (has been synced to API)
     */
    private function is_product_commissioned($product_id) {
        if (!$product_id) {
            return false;
        }

        // Check if product has been synced to commission API
        $synced_meta = get_post_meta($product_id, '_twivco_commission_synced', true);
        $commission_rate = get_post_meta($product_id, '_twivco_commission_rate', true);

        return !empty($synced_meta) && !empty($commission_rate);
    }

    /**
     * Get commission rate for a product
     */
    private function get_product_commission_rate($product_id) {
        if (!$product_id) {
            return 0;
        }

        $commission_rate = get_post_meta($product_id, '_twivco_commission_rate', true);
        return floatval($commission_rate);
    }

    /**
     * Prepare customer information for basket order
     */
    private function prepare_customer_info($order) {
        return array(
            'customer_id' => $order->get_customer_id(),
            'billing_email' => $order->get_billing_email(),
            'billing_phone' => $order->get_billing_phone(),
            'billing_first_name' => $order->get_billing_first_name(),
            'billing_last_name' => $order->get_billing_last_name(),
            'billing_company' => $order->get_billing_company(),
            'billing_address_1' => $order->get_billing_address_1(),
            'billing_city' => $order->get_billing_city(),
            'billing_state' => $order->get_billing_state(),
            'billing_postcode' => $order->get_billing_postcode(),
            'billing_country' => $order->get_billing_country(),
            'is_guest' => $order->get_customer_id() === 0
        );
    }

    /**
     * Get product categories for basket item
     */
    private function get_product_categories($product) {
        $categories = array();
        $category_terms = get_the_terms($product->get_id(), 'product_cat');

        if ($category_terms && !is_wp_error($category_terms)) {
            foreach ($category_terms as $term) {
                $categories[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug
                );
            }
        }

        return $categories;
    }
}