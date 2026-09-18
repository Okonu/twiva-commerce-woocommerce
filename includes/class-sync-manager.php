<?php

class TWIVCO_Commission_Manager_Sync_Manager {

    private $db;
    private $shop_id;

    public function __construct() {
        $this->db = TWIVCO_Commission_Manager_DB_Factory::get_connection();
        $this->shop_id = $this->db->get_shop_id();
    }

    /**
     * Get sync status to avoid unnecessary syncs
     */
    public function should_sync() {
        $last_sync = get_transient('twivco_commission_last_full_sync');
        $force_sync = get_option('twivco_commission_force_sync_needed', false);

        // Force sync if explicitly needed or if last sync was more than 1 hour ago
        return $force_sync || !$last_sync || (time() - $last_sync) > 3600;
    }

    /**
     * Mark sync as completed
     */
    private function mark_sync_completed() {
        set_transient('twivco_commission_last_full_sync', time(), 3600);
        delete_option('twivco_commission_force_sync_needed');

        // Clear product cache when sync completes
        $this->clear_product_cache();
    }

    /**
     * Clear all product-related caches
     */
    private function clear_product_cache() {
        // Clear all commission product cache entries
        global $wpdb;

        $cache_keys = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_twivco_commission_products_%'"
        );

        foreach ($cache_keys as $cache_key) {
            $transient_name = str_replace('_transient_', '', $cache_key);
            delete_transient($transient_name);
        }
    }

    /**
     * Initialize sync hooks
     */
    public function init() {
        add_action('wp_loaded', array($this, 'maybe_sync_on_access'));
        add_action('admin_init', array($this, 'maybe_sync_on_access'));
        add_action('current_screen', array($this, 'maybe_sync_on_access'));

        add_action('wp_login', array($this, 'sync_on_admin_login'), 10, 2);

        add_action('woocommerce_update_product', array($this, 'handle_product_update'));
        add_action('woocommerce_new_product', array($this, 'handle_product_update'));
        add_action('save_post', array($this, 'handle_post_save'), 10, 2);

        add_action('delete_post', array($this, 'handle_product_deletion'));
        add_action('wp_trash_post', array($this, 'handle_product_deletion'));
        add_action('before_delete_post', array($this, 'handle_product_deletion'));
        add_action('trashed_post', array($this, 'handle_product_deletion'));
        add_action('untrashed_post', array($this, 'handle_product_update'));

        add_action('wp', array($this, 'schedule_periodic_sync'));
        add_action('twivco_commission_manager_periodic_sync', array($this, 'perform_periodic_sync'));

        add_action('load-edit.php', array($this, 'maybe_sync_on_product_list'));
        add_action('load-post.php', array($this, 'maybe_sync_on_product_edit'));
        add_action('load-post-new.php', array($this, 'maybe_sync_on_product_new'));

        add_action('save_post_product', array($this, 'sync_on_product_save'), 10, 1);
        add_action('woocommerce_update_product', array($this, 'sync_on_product_update'), 10, 1);
        add_action('woocommerce_new_product', array($this, 'sync_on_product_create'), 10, 1);
        add_action('before_delete_post', array($this, 'sync_on_product_delete'), 10, 1);

        add_action('edited_product_cat', array($this, 'sync_on_category_change'), 10, 1);
        add_action('created_product_cat', array($this, 'sync_on_category_change'), 10, 1);

        add_action('upgrader_process_complete', array($this, 'sync_on_plugin_update'), 10, 2);
    }

    public function maybe_sync_on_access() {
        if (!is_admin() || !class_exists('WooCommerce')) {
            return;
        }

        // Check user capabilities for admin operations
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Only sync if we really need to and not too frequently
        if (!$this->should_sync()) {
            return;
        }

        // Check if we're on commission manager page
        $is_commission_page = isset($_GET['page']) &&
            sanitize_text_field(wp_unslash($_GET['page'])) === 'twiva-commerce';

        // Force sync is needed or we're on the commission manager page
        $force_sync_needed = get_option('twivco_commission_force_sync_needed', false);

        if ($force_sync_needed || $is_commission_page) {
            // Schedule background sync instead of blocking the UI
            $this->schedule_background_sync('admin_access');
        }
    }

    public function maybe_sync_on_product_list() {
        global $pagenow;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, no nonce needed
        if ($pagenow === 'edit.php' && isset($_GET['post_type']) && sanitize_text_field(wp_unslash($_GET['post_type'])) === 'product') {
            try {
                // Sync triggered from product list page
                $this->sync_all_products();
            } catch (Exception $e) {
                // Product list sync error handled
            }
        }
    }

    public function maybe_sync_on_product_edit() {
        global $pagenow;

        // Check user capabilities before processing
        if (!current_user_can('edit_products')) {
            return;
        }

        if ($pagenow === 'post.php' && isset($_GET['post']) && get_post_type(absint($_GET['post'])) === 'product') {
            try {
                // Sync triggered from product edit page
                $this->sync_all_products();
            } catch (Exception $e) {
                // Product edit sync error handled
            }
        }
    }

    public function maybe_sync_on_product_new() {
        global $pagenow;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, no nonce needed
        if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && sanitize_text_field(wp_unslash($_GET['post_type'])) === 'product') {
            try {
                // Sync triggered from new product page
                $this->sync_all_products();
            } catch (Exception $e) {
                // New product sync error handled
            }
        }
    }

    public function sync_on_admin_login($user_login, $user) {
        if (!user_can($user, 'manage_woocommerce')) {
            return;
        }

        wp_schedule_single_event(time() + 30, 'twivco_commission_manager_login_sync');
        add_action('twivco_commission_manager_login_sync', array($this, 'sync_all_products'));
    }

    public function schedule_periodic_sync() {
        if (!wp_next_scheduled('twivco_commission_manager_periodic_sync')) {
            wp_schedule_event(time(), 'daily', 'twivco_commission_manager_periodic_sync');
        }
    }

    public function perform_periodic_sync() {
        $this->sync_all_products();
    }

    /**
     * Schedule background sync job
     */
    public function schedule_background_sync($trigger = 'manual') {
        if (!wp_next_scheduled('twivco_commission_background_sync')) {
            wp_schedule_single_event(time() + 10, 'twivco_commission_background_sync', array($trigger));
        }
    }

    /**
     * Optimized sync with batching and error handling
     */
    public function sync_all_products() {
        $start_time = microtime(true);

        // Register shop first
        try {
            $this->db->register_shop();
        } catch (Exception $e) {
            $logger = TWIVCO_Commission_Manager_Logger::get_instance();
            $logger->error('Shop registration failed', array(
                'error_message' => $e->getMessage(),
                'shop_id' => $this->shop_id,
                'api_url' => $this->db instanceof TWIVCO_Commission_Manager_API_Connection ?
                    $this->db->api_base_url : 'unknown'
            ));
            return false;
        }

        $sync_log_id = $this->log_sync_start('products');

        try {
            $commission_rates = $this->load_commission_rates();

            $processed = 0;
            $added = 0;
            $updated = 0;
            $batch_size = 50; // Process in smaller batches
            $offset = 0;

            do {
                $wc_products = wc_get_products(array(
                    'limit' => $batch_size,
                    'offset' => $offset,
                    'status' => 'publish'
                ));

                foreach ($wc_products as $product) {
                    $result = $this->sync_single_product($product->get_id(), $commission_rates);
                    if ($result) {
                        if ($result === 'added') {
                            $added++;
                        } elseif ($result === 'updated') {
                            $updated++;
                        }
                        $processed++;
                    }
                }

                $offset += $batch_size;

                // Prevent memory issues and timeouts
                if (memory_get_usage() > (128 * 1024 * 1024)) { // 128MB limit
                    break;
                }

            } while (count($wc_products) === $batch_size);

            $duration = (microtime(true) - $start_time) * 1000;

            $cleaned_up = $this->cleanup_deleted_products();

            $this->log_sync_completion($sync_log_id, $processed, $added, $updated, $duration);
            $this->mark_sync_completed();

            // Process any pending sales now that API is working
            $this->process_pending_sales();

            return true;

        } catch (Exception $e) {
            $duration = (microtime(true) - $start_time) * 1000;
            $this->log_sync_error($sync_log_id, $e->getMessage(), $duration);

            $logger = TWIVCO_Commission_Manager_Logger::get_instance();
            $logger->error('Product sync failed', array(
                'error_message' => $e->getMessage(),
                'duration_ms' => $duration,
                'processed_count' => $processed ?? 0,
                'memory_usage' => memory_get_usage(true)
            ));
            return false;
        }
    }

    public function sync_single_product($product_id, $commission_rates = null) {
        if (!$commission_rates) {
            $commission_rates = $this->load_commission_rates();
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }

        try {
            $shop_url = home_url();
            $shop_name = get_bloginfo('name') ?: 'WooCommerce Store';

            $category_ids = $product->get_category_ids();
            $categories = array();
            $category_urls = array();
            $commission_rate = 15;
            $is_uncategorized = empty($category_ids);
            
            foreach ($category_ids as $cat_id) {
                $category = get_term($cat_id, 'product_cat');
                if ($category && !is_wp_error($category)) {
                    $categories[] = $category->name;
                    $category_urls[] = array(
                        'name' => $category->name,
                        'url' => $shop_url . '/product-category/' . $this->sanitize_product_title($category->name)
                    );

                    $rate = $this->find_commission_rate($category->name, $commission_rates);
                    if ($rate > 0) {
                        $commission_rate = $rate;
                        break;
                    }
                }
            }

            $price = floatval($product->get_price());
            $commission_amount = $price * ($commission_rate / 100);

            // Get shop currency (default to KSH for Kenyan market)
            $shop_currency = get_woocommerce_currency();
            $currency = $shop_currency === 'USD' ? 'KSH' : $shop_currency; // Convert USD to KSH as default

            $external_product_data = array(
                'shopId' => $this->shop_id,
                'shopUrl' => $shop_url,
                'shopName' => $shop_name,
                'productId' => $product_id,
                'name' => $product->get_name(),
                'price' => $price,
                'regularPrice' => floatval($product->get_regular_price()),
                'salePrice' => $product->get_sale_price() ? floatval($product->get_sale_price()) : null,
                'sku' => $product->get_sku(),
                'stockStatus' => $product->get_stock_status(),
                'currency' => $currency, // Add currency field
                'permalink' => $shop_url . '/product/' . $this->sanitize_product_title($product->get_name()),
                'productUrl' => $shop_url . '/product/' . $this->sanitize_product_title($product->get_name()),
                'imageUrl' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: '',
                'categories' => json_encode($categories),
                'categoryUrls' => json_encode($category_urls),
                'commissionRate' => $commission_rate,
                'commissionAmount' => round($commission_amount, 2),
                'isUncategorized' => (int)$is_uncategorized,
                'lastSyncAt' => gmdate('Y-m-d H:i:s'),
                'updatedAt' => gmdate('Y-m-d H:i:s')
            );

            $existing = $this->db->get_row('twivco_commission_products', [
                'shopId' => $this->shop_id,
                'productId' => $product_id
            ]);

            if ($existing) {
                $this->db->update('twivco_commission_products', $external_product_data, [
                    'shopId' => $this->shop_id,
                    'productId' => $product_id
                ]);
                $sync_result = 'updated';
            } else {
                $external_product_data['createdAt'] = gmdate('Y-m-d H:i:s');
                $this->db->insert('twivco_commission_products', $external_product_data);
                $sync_result = 'added';
            }

            $commission_data = array(
                'shopId' => $this->shop_id,
                'productId' => $product_id,
                'commissionValue' => round($commission_amount, 2),
                'commissionType' => 'percentage',
                'commissionRate' => $commission_rate,
                'currency' => $currency, // Add currency field to commission data
                'productTitle' => $product->get_name(),
                'lastCalculated' => gmdate('Y-m-d H:i:s'),
                'createdAt' => gmdate('Y-m-d H:i:s'),
                'updatedAt' => gmdate('Y-m-d H:i:s')
            );
            
            $existing_commission = $this->db->get_row('twivco_commission_product_commissions', [
                'shopId' => $this->shop_id,
                'productId' => $product_id
            ]);
            
            if ($existing_commission) {
                $this->db->update('twivco_commission_product_commissions', $commission_data, [
                    'shopId' => $this->shop_id,
                    'productId' => $product_id
                ]);
            } else {
                $this->db->insert('twivco_commission_product_commissions', $commission_data);
            }
            
            return $sync_result;

        } catch (Exception $e) {
            // Error syncing product handled
            return false;
        }
    }

    public function handle_product_update($product_id) {
        try {
            // Mark this product for incremental sync
            $this->mark_product_for_sync($product_id, 'updated');

            // Schedule incremental sync if not already scheduled
            $this->schedule_incremental_sync();

        } catch (Exception $e) {
            $logger = TWIVCO_Commission_Manager_Logger::get_instance();
            $logger->error('Product update handling failed', array(
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ));
        }
    }

    public function handle_post_save($post_id, $post) {
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        try {
            // Handle post save called
            $this->sync_single_product($post_id);
        } catch (Exception $e) {
            // Handle post save error handled
        }
    }

    public function handle_product_deletion($post_id) {
        // Handle product deletion called
        
        if (get_post_type($post_id) !== 'product') {
            // Not a product, skipping deletion
            return;
        }

        try {
            // Attempting to delete product from external database
            
            $deleted_products = $this->db->delete('twivco_commission_products', [
                'shopId' => $this->shop_id,
                'productId' => $post_id
            ]);

            $deleted_commissions = $this->db->delete('twivco_commission_product_commissions', [
                'shopId' => $this->shop_id,
                'productId' => $post_id
            ]);

            // Successfully deleted product from external database

        } catch (Exception $e) {
            // Error deleting product from external database handled
        }
    }

    /**
     * Clean up products that were deleted from WooCommerce but still exist in external database
     */
    private function cleanup_deleted_products() {
        try {
            $external_products = $this->db->get_results('twivco_commission_products', ['shopId' => $this->shop_id]);
            
            $deleted_count = 0;
            
            foreach ($external_products as $external_product) {
                $product_id = $external_product['productId'];

                $wc_product = wc_get_product($product_id);
                
                if (!$wc_product || $wc_product->get_status() !== 'publish') {
                    $this->db->delete('twivco_commission_products', [
                        'shopId' => $this->shop_id,
                        'productId' => $product_id
                    ]);
                    
                    $this->db->delete('twivco_commission_product_commissions', [
                        'shopId' => $this->shop_id,
                        'productId' => $product_id
                    ]);
                    
                    $deleted_count++;
                    // Cleaned up deleted product from external database
                }
            }
            
            return $deleted_count;
            
        } catch (Exception $e) {
            // Error during cleanup of deleted products handled
            return 0;
        }
    }

    public function get_synced_products($args = array()) {
        $defaults = array(
            'per_page' => 25,
            'page' => 1,
            'search' => ''
        );

        $args = wp_parse_args($args, $defaults);

        // Check cache first
        $cache_key = 'twivco_commission_products_' . md5(serialize($args) . $this->shop_id);
        $cached_products = get_transient($cache_key);

        if ($cached_products !== false) {
            return $cached_products;
        }

        try {
            $api_products = $this->db->get_products(array('shopId' => $this->shop_id));

            if (empty($api_products)) {
                // Cache empty result for 5 minutes
                set_transient($cache_key, array(), 300);
                return array();
            }

            $api_commissions = $this->db->get_commissions(array('shopId' => $this->shop_id));

            $commission_lookup = array();
            foreach ($api_commissions as $commission) {
                if (isset($commission['productId'])) {
                    $commission_lookup[$commission['productId']] = $commission;
                }
            }
            
            $formatted_products = array();
            
            foreach ($api_products as $product) {
                // Handle both JSON string and array data formats
                $data = is_string($product['data']) ? json_decode($product['data'], true) : $product['data'];
                $data = is_array($data) ? $data : array();

                $commission = $commission_lookup[$product['productId']] ?? array();
                $commission_rate = isset($commission['commissionRate']) ? floatval($commission['commissionRate']) : 15;
                $commission_value = isset($commission['commissionValue']) ? floatval($commission['commissionValue']) : 0;

                $categories = array();
                if (isset($data['categories'])) {
                    $categories_json = is_string($data['categories']) ? json_decode($data['categories'], true) : $data['categories'];
                    $categories = is_array($categories_json) ? $categories_json : array();
                }

                $formatted_products[] = array(
                    'id' => $product['productId'],
                    'name' => $product['name'],
                    'price' => floatval($product['price']),
                    'regular_price' => isset($data['regularPrice']) ? floatval($data['regularPrice']) : floatval($product['price']),
                    'sale_price' => isset($data['salePrice']) ? floatval($data['salePrice']) : null,
                    'sku' => $data['sku'] ?? '',
                    'stock_status' => $data['stockStatus'] ?? 'instock',
                    'permalink' => $data['permalink'] ?? '',
                    'image' => $data['imageUrl'] ?? '',
                    'categories' => $categories,
                    'commission_rate' => $commission_rate,
                    'commission_amount' => $commission_value,
                    'is_uncategorized' => empty($categories) || (count($categories) === 1 && $categories[0] === 'Uncategorized'),
                    'commission_message' => "Commission: {$commission_rate}% = KSH " . number_format($commission_value, 2),
                    'calculation_type' => 'api_synced',
                    'rate_source' => 'External Database',
                    'matched_categories' => $categories,
                    'last_sync' => $data['lastSyncAt'] ?? '',
                    'created_at' => $product['created_at'] ?? '',
                    'updated_at' => $product['updated_at'] ?? ''
                );
            }

            if (!empty($args['search'])) {
                $search_term = strtolower($args['search']);
                $formatted_products = array_filter($formatted_products, function($product) use ($search_term) {
                    return strpos(strtolower($product['name']), $search_term) !== false ||
                           strpos(strtolower($product['sku']), $search_term) !== false;
                });
            }

            $total = count($formatted_products);
            $offset = ($args['page'] - 1) * $args['per_page'];
            $formatted_products = array_slice($formatted_products, $offset, $args['per_page']);

            // Cache result for 15 minutes
            set_transient($cache_key, $formatted_products, 900);

            return $formatted_products;
            
        } catch (Exception $e) {
            // Error getting synced products handled
            return array();
        }
    }

    private function load_commission_rates() {
        $json_file = TWIVCO_COMMISSION_MANAGER_PLUGIN_PATH . 'data/commission-rates.json';
        
        if (!file_exists($json_file)) {
            throw new Exception('Commission rates file not found');
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in commission rates file');
        }
        
        return $data['commission_rates'] ?? array();
    }

    private function find_commission_rate($category_name, $commission_rates) {
        foreach ($commission_rates as $main_category => $subcategories) {
            if (strtolower($main_category) === strtolower($category_name)) {
                return reset($subcategories);
            }
            
            foreach ($subcategories as $subcategory => $rate) {
                if (strtolower($subcategory) === strtolower($category_name)) {
                    return $rate;
                }
            }
        }

        foreach ($commission_rates as $main_category => $subcategories) {
            if (stripos($category_name, $main_category) !== false || stripos($main_category, $category_name) !== false) {
                return reset($subcategories);
            }
            
            foreach ($subcategories as $subcategory => $rate) {
                if (stripos($category_name, $subcategory) !== false || stripos($subcategory, $category_name) !== false) {
                    return $rate;
                }
            }
        }

        return $commission_rates['Other']['Other'] ?? 15;
    }

    private function log_sync_start($sync_type) {
        try {
            $log_data = array(
                'shopId' => $this->shop_id,
                'syncType' => $sync_type,
                'status' => 'started',
                'startedAt' => gmdate('Y-m-d H:i:s')
            );
            
            $result = $this->db->insert('sync_logs', $log_data);
            return $result['id'] ?? null;
            
        } catch (Exception $e) {
            // Error logging sync start handled
            return null;
        }
    }

    private function log_sync_completion($sync_log_id, $processed, $added, $updated, $duration) {
        if (!$sync_log_id) {
            return;
        }

        try {
            $this->db->update('sync_logs', array(
                'status' => 'completed',
                'recordsProcessed' => $processed,
                'recordsAdded' => $added,
                'recordsUpdated' => $updated,
                'completedAt' => gmdate('Y-m-d H:i:s'),
                'duration' => round($duration)
            ), array('id' => $sync_log_id));
            
        } catch (Exception $e) {
            // Error logging sync completion handled
        }
    }

    private function log_sync_error($sync_log_id, $error_message, $duration) {
        if (!$sync_log_id) {
            return;
        }

        try {
            $this->db->update('sync_logs', array(
                'status' => 'failed',
                'errorMessage' => $error_message,
                'completedAt' => gmdate('Y-m-d H:i:s'),
                'duration' => round($duration)
            ), array('id' => $sync_log_id));
            
        } catch (Exception $e) {
            // Error logging sync error handled
        }
    }

    public function get_sync_status() {
        try {
            $latest_sync = $this->db->get_row('sync_logs', array(
                'shopId' => $this->shop_id,
                'syncType' => 'products'
            ), array(
                'order_by' => 'startedAt DESC',
                'limit' => 1
            ));
            
            return $latest_sync;
            
        } catch (Exception $e) {
            // Error getting sync status handled
            return null;
        }
    }

    /**
     * Simple title sanitization for product URLs
     */
    private function sanitize_product_title($title) {
        if (function_exists('sanitize_title')) {
            return sanitize_title($title);
        }

        $title = strtolower($title);
        $title = preg_replace('/[^a-z0-9\s-]/', '', $title);
        $title = preg_replace('/\s+/', '-', $title);
        $title = trim($title, '-');
        return $title;
    }
    
    /**
     * Sync when product is saved/updated
     */
    public function sync_on_product_save($post_id) {
        if (get_post_type($post_id) === 'product') {
            try {
                // Syncing product after save
                $this->sync_single_product($post_id);
            } catch (Exception $e) {
                // Error syncing product after save handled
            }
        }
    }
    
    /**
     * Sync when WooCommerce product is updated
     */
    public function sync_on_product_update($product_id) {
        try {
            // Syncing product after WooCommerce update
            $this->sync_single_product($product_id);
        } catch (Exception $e) {
            // Error syncing product after WooCommerce update handled
        }
    }
    
    /**
     * Sync when new WooCommerce product is created
     */
    public function sync_on_product_create($product_id) {
        try {
            // Syncing new product
            $this->sync_single_product($product_id);
        } catch (Exception $e) {
            // Error syncing new product handled
        }
    }
    
    /**
     * Handle product deletion
     */
    public function sync_on_product_delete($post_id) {
        if (get_post_type($post_id) === 'product') {
            try {
                // Deleting product from backend
                $this->db->delete('twivco_commission_products', array('productId' => $post_id));
            } catch (Exception $e) {
                // Error deleting product from backend handled
            }
        }
    }
    
    /**
     * Sync when product categories are changed
     */
    public function sync_on_category_change($term_id) {
        try {
            // Category changed, syncing affected products
            $products = get_objects_in_term($term_id, 'product_cat');
            foreach ($products as $product_id) {
                $this->sync_single_product($product_id);
            }
        } catch (Exception $e) {
            // Error syncing products for category handled
        }
    }
    
    /**
     * Sync on plugin updates
     */
    public function sync_on_plugin_update($upgrader, $hook_extra) {
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === plugin_basename(TWIVCO_COMMISSION_MANAGER_PLUGIN_FILE)) {
            try {
                // Plugin updated, triggering full sync
                wp_schedule_single_event(time() + 30, 'twivco_commission_post_update_sync');
                add_action('twivco_commission_post_update_sync', array($this, 'sync_all_products'));
            } catch (Exception $e) {
                // Error scheduling post-update sync handled
            }
        }
    }

    /**
     * Process pending sales from sales tracker
     */
    private function process_pending_sales() {
        if (class_exists('TWIVCO_Commission_Manager_Sales_Tracker')) {
            $sales_tracker = new TWIVCO_Commission_Manager_Sales_Tracker();
            $sales_tracker->process_pending_sales();
        }
    }

    /**
     * Mark product for incremental sync
     */
    private function mark_product_for_sync($product_id, $action = 'updated') {
        $pending_syncs = get_option('twivco_commission_pending_syncs', array());

        $pending_syncs[$product_id] = array(
            'action' => $action,
            'timestamp' => time(),
            'priority' => $action === 'deleted' ? 1 : ($action === 'created' ? 2 : 3)
        );

        // Keep only last 500 products to prevent unlimited growth
        if (count($pending_syncs) > 500) {
            // Sort by timestamp and keep newest
            uasort($pending_syncs, function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });
            $pending_syncs = array_slice($pending_syncs, 0, 500, true);
        }

        update_option('twivco_commission_pending_syncs', $pending_syncs, false);
    }

    /**
     * Schedule incremental sync
     */
    private function schedule_incremental_sync() {
        if (!wp_next_scheduled('twivco_commission_incremental_sync')) {
            wp_schedule_single_event(time() + 30, 'twivco_commission_incremental_sync');
        }
    }

    /**
     * Perform incremental sync of marked products
     */
    public function perform_incremental_sync() {
        $pending_syncs = get_option('twivco_commission_pending_syncs', array());

        if (empty($pending_syncs)) {
            return 0;
        }

        // Sort by priority (deleted first, then created, then updated)
        uasort($pending_syncs, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        $processed = 0;
        $max_batch = 50; // Process maximum 50 products per run

        try {
            $commission_rates = $this->load_commission_rates();
            $this->db->register_shop();

            foreach ($pending_syncs as $product_id => $sync_data) {
                if ($processed >= $max_batch) {
                    break;
                }

                if ($sync_data['action'] === 'deleted') {
                    $this->handle_product_deletion($product_id);
                } else {
                    $this->sync_single_product($product_id, $commission_rates);
                }

                $processed++;
                unset($pending_syncs[$product_id]);
            }

            // Update remaining pending syncs
            update_option('twivco_commission_pending_syncs', $pending_syncs, false);

            // Clear cache since products were updated
            if ($processed > 0) {
                $this->clear_product_cache();
            }

            $logger = TWIVCO_Commission_Manager_Logger::get_instance();
            $logger->info('Incremental sync completed', array(
                'processed' => $processed,
                'remaining' => count($pending_syncs)
            ));

            // Schedule another run if there are more products to sync
            if (!empty($pending_syncs)) {
                $this->schedule_incremental_sync();
            }

        } catch (Exception $e) {
            $logger = TWIVCO_Commission_Manager_Logger::get_instance();
            $logger->error('Incremental sync failed', array(
                'error' => $e->getMessage(),
                'processed' => $processed
            ));
        }

        return $processed;
    }
}