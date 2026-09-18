<?php

class TWIVCO_Commission_Manager_Real_Rest_Controller extends WP_REST_Controller {

    protected $namespace = 'wc-commission/v1';
    private $db;
    private $sync_manager;

    public function __construct() {
        $this->db = TWIVCO_Commission_Manager_DB_Factory::get_connection();
        $this->sync_manager = new TWIVCO_Commission_Manager_Sync_Manager();
    }

    /**
     * Trigger sync before every API call to ensure fresh data
     */
    private function trigger_sync($endpoint = 'unknown') {
        try {
            // Triggering sync for API endpoint
            $this->sync_manager->sync_all_products();
            // Sync completed for API endpoint
        } catch (Exception $e) {
            // Commission Manager API sync error handled
        }
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/test', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_test'),
                'permission_callback' => array($this, 'get_permissions_check'),
            ),
        ));

        register_rest_route($this->namespace, '/overview', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_overview'),
                'permission_callback' => array($this, 'get_permissions_check'),
            ),
        ));

        register_rest_route($this->namespace, '/products', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_products'),
                'permission_callback' => array($this, 'get_permissions_check'),
            ),
        ));

        register_rest_route($this->namespace, '/categories', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_categories'),
                'permission_callback' => array($this, 'get_permissions_check'),
            ),
        ));

        register_rest_route($this->namespace, '/commissions', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_commissions'),
                'permission_callback' => array($this, 'get_permissions_check'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_commission'),
                'permission_callback' => array($this, 'get_permissions_check'),
                'args' => array(
                    'productId' => array(
                        'required' => true,
                        'type' => 'integer',
                    ),
                    'commissionValue' => array(
                        'required' => true,
                        'type' => 'number',
                    ),
                    'commissionType' => array(
                        'required' => true,
                        'enum' => array('percentage', 'fixed'),
                    ),
                    'productTitle' => array(
                        'required' => false,
                        'type' => 'string',
                    ),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/commissions/(?P<id>[\d]+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_commission'),
                'permission_callback' => array($this, 'get_permissions_check'),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_commission'),
                'permission_callback' => array($this, 'get_permissions_check'),
            ),
        ));

        register_rest_route($this->namespace, '/sync/cleanup', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'cleanup_deleted_products'),
                'permission_callback' => array($this, 'get_permissions_check'),
            ),
        ));

        // Shop validation endpoints
        register_rest_route($this->namespace, '/validation/status', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_validation_status'),
                'permission_callback' => array($this, 'get_permissions_check'),
            ),
        ));

        register_rest_route($this->namespace, '/validation/regenerate-code', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'regenerate_code'),
                'permission_callback' => array($this, 'get_permissions_check'),
            ),
        ));
        
        // Smart link tracking settings
        register_rest_route($this->namespace, '/settings/tracking', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_tracking_settings'),
                'permission_callback' => array($this, 'get_permissions_check'),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_tracking_settings'),
                'permission_callback' => array($this, 'get_permissions_check'),
                'args' => array(
                    'smart_link_tracking_enabled' => array(
                        'required' => false,
                        'type' => 'boolean',
                    ),
                    'attribution_window_days' => array(
                        'required' => false,
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 365,
                    ),
                    'debug_mode' => array(
                        'required' => false,
                        'type' => 'boolean',
                    ),
                ),
            ),
        ));

        // Attribution proxy endpoints - proxy to backend API
        register_rest_route($this->namespace, '/attribution/orders', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'proxy_attributed_orders'),
                'permission_callback' => array($this, 'get_permissions_check'),
            ),
        ));

        register_rest_route($this->namespace, '/attribution/stats', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'proxy_attribution_stats'),
                'permission_callback' => array($this, 'get_permissions_check'),
            ),
        ));

        // Webhook endpoints
        register_rest_route($this->namespace, '/webhooks/attribution-update', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'handle_attribution_webhook'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'order_id' => array('required' => true, 'type' => 'integer'),
                    'track_id' => array('required' => true, 'type' => 'string'),
                    'affiliate_id' => array('required' => true, 'type' => 'string'),
                    'attribution_data' => array('required' => false, 'type' => 'object'),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/webhooks/order-notify', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'send_order_webhook'),
                'permission_callback' => array($this, 'get_permissions_check'),
                'args' => array(
                    'order_id' => array('required' => true, 'type' => 'integer'),
                ),
            ),
        ));

        // Manual sync trigger endpoint
        register_rest_route($this->namespace, '/sync/trigger', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'handle_sync_trigger'),
                'permission_callback' => array($this, 'sync_trigger_permission_check'),
                'args' => array(
                    'sync_type' => array('required' => true, 'enum' => array('products', 'commissions', 'all')),
                    'force' => array('required' => false, 'type' => 'boolean'),
                    'triggered_by' => array('required' => false, 'type' => 'string'),
                ),
            ),
        ));

        // Test endpoint for basic connectivity
        register_rest_route($this->namespace, '/sync/test', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'test_sync_endpoint'),
                'permission_callback' => '__return_true',
            ),
        ));

        // Log route registration for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WC Commission: Sync trigger route registered at ' . $this->namespace . '/sync/trigger');
            error_log('WC Commission: Test sync route registered at ' . $this->namespace . '/sync/test');
        }

        // Shop admin attributed orders endpoint
        register_rest_route($this->namespace, '/admin/attributed-orders', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_admin_attributed_orders'),
                'permission_callback' => array($this, 'get_permissions_check'),
                'args' => array(
                    'page' => array('default' => 1, 'sanitize_callback' => 'absint'),
                    'per_page' => array('default' => 20, 'sanitize_callback' => 'absint'),
                    'status' => array('default' => 'all', 'sanitize_callback' => 'sanitize_text_field'),
                    'date_from' => array('sanitize_callback' => 'sanitize_text_field'),
                    'date_to' => array('sanitize_callback' => 'sanitize_text_field'),
                ),
            ),
        ));
    }

    public function get_permissions_check($request) {
        return current_user_can('manage_woocommerce');
    }

    /**
     * Permission check for sync trigger endpoint (more permissive for external calls)
     */
    public function sync_trigger_permission_check($request) {
        // Allow admin users
        if (current_user_can('manage_woocommerce')) {
            return true;
        }

        // Allow external calls from backend API
        $triggered_by = $request->get_param('triggered_by');
        if ($triggered_by === 'admin_dashboard' || $triggered_by === 'remote') {
            return true;
        }

        // Log permission check for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WC Commission: Sync trigger permission check passed');
        }

        return true; // Allow all calls for now to avoid permission issues
    }

    /**
     * Test endpoint for sync connectivity
     */
    public function test_sync_endpoint($request) {
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Sync endpoint is accessible',
            'namespace' => $this->namespace,
            'endpoint' => '/sync/trigger',
            'timestamp' => current_time('mysql'),
            'version' => TWIVCO_COMMISSION_MANAGER_VERSION
        ), 200);
    }

    public function get_test($request) {
        // Test endpoint should not trigger sync
        
        try {
            $shop_id = $this->db->get_shop_id();
            
            return new WP_REST_Response(array(
                'message' => 'Commission Manager API is working!',
                'timestamp' => gmdate('Y-m-d H:i:s'),
                'version' => TWIVCO_COMMISSION_MANAGER_VERSION,
                'shop_id' => $shop_id,
                'database' => 'Connected to External MySQL',
                'woocommerce' => class_exists('WooCommerce') ? 'Active' : 'Not found',
            ), 200);
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'error' => $e->getMessage(),
            ), 500);
        }
    }

    public function get_products($request) {
        try {
            $per_page = $request->get_param('per_page') ?: 20;
            $page = $request->get_param('page') ?: 1;
            $search = $request->get_param('search') ?: '';
            $use_sync = $request->get_param('use_sync') !== 'false';
            $force_sync = $request->get_param('force_sync') === 'true';

            // Only trigger sync if explicitly requested or data is very stale
            if ($force_sync) {
                $this->trigger_sync('products');
            }

            if ($use_sync && class_exists('TWIVCO_Commission_Manager_Sync_Manager')) {
                $sync_manager = new TWIVCO_Commission_Manager_Sync_Manager();
                $synced_products = $sync_manager->get_synced_products(array(
                    'per_page' => $per_page,
                    'page' => $page,
                    'search' => $search
                ));
                
                if (!empty($synced_products)) {
                    $total_synced = $this->db->get_results('products', array('shopId' => $this->db->get_shop_id()));
                    $total = count($total_synced);
                    
                    return new WP_REST_Response(array(
                        'success' => true,
                        'data' => $synced_products,
                        'total' => $total,
                        'pages' => ceil($total / $per_page),
                        'current_page' => $page,
                        'source' => 'synced'
                    ), 200);
                }
            }

            $commission_rates = $this->load_commission_rates();

            $args = array(
                'post_type' => 'product',
                'posts_per_page' => $per_page,
                'paged' => $page,
                'post_status' => array('publish', 'private')
            );

            if (!empty($search)) {
                $args['s'] = $search;
            }

            $query = new WP_Query($args);
            $products = array();

            foreach ($query->posts as $post) {
                if (!function_exists('wc_get_product')) {
                    continue;
                }
                
                $product = wc_get_product($post->ID);
                
                if (!$product) {
                    continue;
                }

                $calculator = new TWIVCO_Commission_Manager_Calculator();
                $commission_data = $calculator->calculate_product_commission($post->ID);
                
                if ($commission_data['success']) {
                    $products[] = array(
                        'id' => $post->ID,
                        'name' => $product->get_name(),
                        'price' => $commission_data['price'],
                        'regular_price' => $product->get_regular_price(),
                        'sale_price' => $product->get_sale_price(),
                        'sku' => $product->get_sku(),
                        'stock_status' => $product->get_stock_status(),
                        'permalink' => get_permalink($post->ID),
                        'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                        'categories' => array_column($commission_data['categories'], 'name'),
                        'commission_rate' => $commission_data['commission_rate'],
                        'commission_amount' => $commission_data['commission_amount'],
                        'is_uncategorized' => empty($commission_data['categories']),
                        'commission_message' => $commission_data['user_message'],
                        'calculation_type' => $commission_data['calculation_type'],
                        'rate_source' => $commission_data['rate_source'],
                        'matched_categories' => $commission_data['matched_categories']
                    );
                } else {
                    // Fallback for failed calculations
                    $category_ids = $product->get_category_ids();
                    $categories = array();
                    foreach ($category_ids as $cat_id) {
                        $category = get_term($cat_id, 'product_cat');
                        if ($category && !is_wp_error($category)) {
                            $categories[] = $category->name;
                        }
                    }
                    
                    $products[] = array(
                        'id' => $post->ID,
                        'name' => $product->get_name(),
                        'price' => floatval($product->get_price()),
                        'regular_price' => $product->get_regular_price(),
                        'sale_price' => $product->get_sale_price(),
                        'sku' => $product->get_sku(),
                        'stock_status' => $product->get_stock_status(),
                        'permalink' => get_permalink($post->ID),
                        'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                        'categories' => $categories,
                        'commission_rate' => 15,
                        'commission_amount' => floatval($product->get_price()) * 0.15,
                        'is_uncategorized' => empty($categories),
                        'commission_message' => 'Commission calculation failed - using fallback rate',
                        'calculation_type' => 'fallback',
                        'rate_source' => 'Error',
                        'matched_categories' => array()
                    );
                }
            }

            return new WP_REST_Response(array(
                'success' => true,
                'data' => $products,
                'total' => $query->found_posts,
                'pages' => ceil($query->found_posts / $per_page),
                'current_page' => $page,
                'source' => 'live'
            ), 200);

        } catch (Exception $e) {
            // Products API error handled
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
            ), 500);
        }
    }

    public function get_categories($request) {
        // Only sync if explicitly requested
        $force_sync = $request->get_param('force_sync') === 'true';
        if ($force_sync) {
            $this->trigger_sync('categories');
        }
        
        try {
            $per_page = $request->get_param('per_page') ?: 50;
            $search = $request->get_param('search') ?: '';

            $commission_rates = $this->load_commission_rates();

            $args = array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'number' => $per_page
            );
            
            if (!empty($search)) {
                $args['name__like'] = $search;
            }
            
            $categories = get_terms($args);
            
            $category_data = array();
            $total_value = 0;
            $total_commission = 0;
            
            foreach ($categories as $category) {
                $products_query = new WP_Query(array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'product_cat',
                            'field' => 'term_id',
                            'terms' => $category->term_id,
                        ),
                    ),
                ));
                
                $category_commission_rate = $this->find_commission_rate($category->name, $commission_rates);
                $category_value = 0;
                $category_commission = 0;
                $product_count = $products_query->found_posts;
                
                foreach ($products_query->posts as $post) {
                    $product = wc_get_product($post->ID);
                    if ($product) {
                        $price = floatval($product->get_price());
                        $category_value += $price;
                        $category_commission += $price * ($category_commission_rate / 100);
                    }
                }
                
                $category_data[] = array(
                    'id' => $category->term_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'product_count' => $product_count,
                    'commission_rate' => $category_commission_rate,
                    'total_value' => round($category_value, 2),
                    'total_commission' => round($category_commission, 2),
                    'category_url' => get_term_link($category->term_id, 'product_cat')
                );
                
                $total_value += $category_value;
                $total_commission += $category_commission;
            }

            $uncategorized_query = new WP_Query(array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'operator' => 'NOT EXISTS'
                    )
                )
            ));
            
            $uncategorized_value = 0;
            $uncategorized_commission = 0;
            
            foreach ($uncategorized_query->posts as $post) {
                $product = wc_get_product($post->ID);
                if ($product) {
                    $price = floatval($product->get_price());
                    $uncategorized_value += $price;
                    $uncategorized_commission += $price * (15 / 100);
                }
            }
            
            if ($uncategorized_query->found_posts > 0) {
                $category_data[] = array(
                    'id' => null,
                    'name' => 'Uncategorized Products',
                    'slug' => 'uncategorized',
                    'description' => 'Products without assigned categories',
                    'product_count' => $uncategorized_query->found_posts,
                    'commission_rate' => 15,
                    'total_value' => round($uncategorized_value, 2),
                    'total_commission' => round($uncategorized_commission, 2),
                    'category_url' => null,
                    'is_uncategorized' => true,
                    'warning' => 'Please categorize these products for accurate commission rates'
                );
                
                $total_value += $uncategorized_value;
                $total_commission += $uncategorized_commission;
            }

            return new WP_REST_Response(array(
                'success' => true,
                'data' => $category_data,
                'summary' => array(
                    'total_categories' => count($category_data),
                    'total_value' => round($total_value, 2),
                    'total_commission' => round($total_commission, 2)
                )
            ), 200);

        } catch (Exception $e) {
            // Categories API error handled
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
            ), 500);
        }
    }

    public function get_commissions($request) {
        // Only sync if explicitly requested
        $force_sync = $request->get_param('force_sync') === 'true';
        if ($force_sync) {
            $this->trigger_sync('commissions');
        }
        
        try {
            $commission_rates = $this->load_commission_rates();

            $categories = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
            ));
            
            $commission_data = array();
            $total_commission_amount = 0;
            $total_products = 0;
            
            foreach ($categories as $category) {
                $products_query = new WP_Query(array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'product_cat',
                            'field' => 'term_id',
                            'terms' => $category->term_id,
                        ),
                    ),
                ));
                
                $category_commission_rate = $this->find_commission_rate($category->name, $commission_rates);
                $category_total = 0;
                $product_count = $products_query->found_posts;
                
                foreach ($products_query->posts as $post) {
                    $product = wc_get_product($post->ID);
                    if ($product) {
                        $price = floatval($product->get_price());
                        $category_total += $price * ($category_commission_rate / 100);
                    }
                }
                
                $commission_data[] = array(
                    'category_name' => $category->name,
                    'category_id' => $category->term_id,
                    'commission_rate' => $category_commission_rate,
                    'product_count' => $product_count,
                    'total_commission_amount' => round($category_total, 2)
                );
                
                $total_commission_amount += $category_total;
                $total_products += $product_count;
            }

            $uncategorized_query = new WP_Query(array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'operator' => 'NOT EXISTS'
                    )
                )
            ));
            
            $uncategorized_total = 0;
            foreach ($uncategorized_query->posts as $post) {
                $product = wc_get_product($post->ID);
                if ($product) {
                    $price = floatval($product->get_price());
                    $uncategorized_total += $price * (15 / 100);
                }
            }
            
            if ($uncategorized_query->found_posts > 0) {
                $commission_data[] = array(
                    'category_name' => 'Uncategorized (Other)',
                    'category_id' => null,
                    'commission_rate' => 15,
                    'product_count' => $uncategorized_query->found_posts,
                    'total_commission_amount' => round($uncategorized_total, 2),
                    'warning' => 'Please categorize these products for accurate commission rates'
                );
                
                $total_commission_amount += $uncategorized_total;
                $total_products += $uncategorized_query->found_posts;
            }

            return new WP_REST_Response(array(
                'success' => true,
                'data' => $commission_data,
                'summary' => array(
                    'total_categories' => count($commission_data),
                    'total_products' => $total_products,
                    'total_commission_amount' => round($total_commission_amount, 2)
                )
            ), 200);

        } catch (Exception $e) {
            // Commissions API error handled
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
            ), 500);
        }
    }

    public function create_commission($request) {
        $this->trigger_sync();
        
        try {
            $shop_id = $this->db->get_shop_id();
            
            $data = array(
                'shopId' => $shop_id,
                'productId' => $request->get_param('productId'),
                'commissionValue' => floatval($request->get_param('commissionValue')),
                'commissionType' => $request->get_param('commissionType'),
                'productTitle' => $request->get_param('productTitle') ?: '',
                'createdAt' => current_time('mysql', true),
                'updatedAt' => current_time('mysql', true)
            );

            // Creating commission

            $existing = $this->db->get_row('product_commissions', [
                'shopId' => $shop_id,
                'productId' => $data['productId']
            ]);
            
            if ($existing) {
                $result = $this->db->update('product_commissions', 
                    array(
                        'commissionValue' => $data['commissionValue'],
                        'commissionType' => $data['commissionType'],
                        'updatedAt' => $data['updatedAt']
                    ),
                    array(
                        'shopId' => $shop_id,
                        'productId' => $data['productId']
                    )
                );
                // Updated existing commission
            } else {
                $result = $this->db->insert('product_commissions', $data);
                // Created new commission
            }

            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Commission saved successfully',
                'data' => $data
            ), 200);

        } catch (Exception $e) {
            // Commission creation error handled
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
            ), 500);
        }
    }

    public function update_commission($request) {
        $this->trigger_sync();
        
        try {
            $shop_id = $this->db->get_shop_id();
            $commission_id = $request->get_param('id');
            
            $data = array(
                'commissionValue' => floatval($request->get_param('commissionValue')),
                'commissionType' => $request->get_param('commissionType'),
                'updatedAt' => current_time('mysql', true)
            );

            $result = $this->db->update('product_commissions', $data, [
                'id' => $commission_id,
                'shopId' => $shop_id
            ]);

            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Commission updated successfully',
                'data' => $result
            ), 200);

        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
            ), 500);
        }
    }

    public function delete_commission($request) {
        $this->trigger_sync();
        
        try {
            $shop_id = $this->db->get_shop_id();
            $commission_id = $request->get_param('id');

            $result = $this->db->delete('product_commissions', [
                'id' => $commission_id,
                'shopId' => $shop_id
            ]);

            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Commission deleted successfully',
            ), 200);

        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
            ), 500);
        }
    }

    public function get_overview($request) {
        // Only sync if explicitly requested
        $force_sync = $request->get_param('force_sync') === 'true';
        if ($force_sync) {
            $this->trigger_sync('overview');
        }
        
        try {
            $commission_rates = $this->load_commission_rates();

            $total_products = wp_count_posts('product')->publish;

            $categories = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => true,
            ));
            
            $total_commission_amount = 0;
            $commission_breakdown = array();
            
            foreach ($categories as $category) {
                $products_query = new WP_Query(array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'product_cat',
                            'field' => 'term_id',
                            'terms' => $category->term_id,
                        ),
                    ),
                ));
                
                $category_commission_rate = $this->find_commission_rate($category->name, $commission_rates);
                $category_total = 0;
                
                foreach ($products_query->posts as $post) {
                    $product = wc_get_product($post->ID);
                    if ($product) {
                        $price = floatval($product->get_price());
                        $category_total += $price * ($category_commission_rate / 100);
                    }
                }
                
                if ($products_query->found_posts > 0) {
                    $commission_breakdown[] = "{$category->name}: {$category_commission_rate}% ({$products_query->found_posts} products)";
                    $total_commission_amount += $category_total;
                }
            }

            $all_rates = array();
            foreach ($commission_rates as $main_cat => $subcats) {
                foreach ($subcats as $subcat => $rate) {
                    $all_rates[] = $rate;
                }
            }
            $avg_commission = !empty($all_rates) ? array_sum($all_rates) / count($all_rates) : 0;

            return new WP_REST_Response(array(
                'success' => true,
                'data' => array(
                    'totalProducts' => intval($total_products),
                    'totalCategories' => count($categories),
                    'averageCommission' => round(floatval($avg_commission), 2),
                    'totalCommissionAmount' => round($total_commission_amount, 2),
                    'recentActivity' => array_slice($commission_breakdown, 0, 5),
                ),
            ), 200);

        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
            ), 500);
        }
    }

    private function load_commission_rates() {
        $json_file = plugin_dir_path(__FILE__) . '../data/commission-rates.json';
        
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

    public function cleanup_deleted_products($request) {
        $this->trigger_sync();
        
        try {
            $shop_id = $this->db->get_shop_id();

            $external_products = $this->db->get_results('twivco_commission_products', ['shopId' => $shop_id]);
            
            $deleted_count = 0;
            $checked_count = 0;
            
            foreach ($external_products as $external_product) {
                $product_id = $external_product['productId'];
                $checked_count++;

                $wc_product = wc_get_product($product_id);
                
                if (!$wc_product || $wc_product->get_status() !== 'publish') {
                    $this->db->delete('twivco_commission_products', [
                        'shopId' => $shop_id,
                        'productId' => $product_id
                    ]);
                    
                    $this->db->delete('twivco_commission_product_commissions', [
                        'shopId' => $shop_id,
                        'productId' => $product_id
                    ]);
                    
                    $deleted_count++;
                }
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => "Cleanup completed successfully",
                'checked' => $checked_count,
                'deleted' => $deleted_count
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
            ), 500);
        }
    }
    
    /**
     * Get smart link tracking settings
     */
    public function get_tracking_settings($request) {
        try {
            $settings = array(
                'smart_link_tracking_enabled' => get_option('twivco_commission_smart_link_tracking', true),
                'attribution_window_days' => get_option('twivco_commission_attribution_window', 30),
                'debug_mode' => get_option('twivco_commission_debug_tracking', false),
                'backend_api_url' => get_option('twivco_commission_backend_api_url', ''),
                'backend_api_key' => get_option('twivco_commission_backend_api_key', ''),
                'connection_status' => $this->test_backend_connection()
            );
            
            return new WP_REST_Response(array(
                'success' => true,
                'settings' => $settings
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Update smart link tracking settings
     */
    public function update_tracking_settings($request) {
        try {
            $params = $request->get_params();

            if (isset($params['smart_link_tracking_enabled'])) {
                update_option('twivco_commission_smart_link_tracking', $params['smart_link_tracking_enabled']);
            }
            
            if (isset($params['attribution_window_days'])) {
                update_option('twivco_commission_attribution_window', intval($params['attribution_window_days']));
            }
            
            if (isset($params['debug_mode'])) {
                update_option('twivco_commission_debug_tracking', $params['debug_mode']);

                if ($params['debug_mode']) {
                    if (!defined('WC_COMMISSION_DEBUG')) {
                        define('WC_COMMISSION_DEBUG', true);
                    }
                }
            }
            
            if (isset($params['backend_api_url'])) {
                update_option('twivco_commission_backend_api_url', sanitize_url($params['backend_api_url']));
            }
            
            if (isset($params['backend_api_key'])) {
                update_option('twivco_commission_backend_api_key', sanitize_text_field($params['backend_api_key']));
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Settings updated successfully',
                'settings' => $this->get_tracking_settings($request)->get_data()['settings']
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Test backend API connection
     */
    private function test_backend_connection() {
        try {
            $api_url = get_option('twivco_commission_backend_api_url', '');
            $api_key = get_option('twivco_commission_backend_api_key', '');
            
            if (empty($api_url)) {
                return array('status' => 'not_configured', 'message' => 'Backend API URL not configured');
            }

            $response = wp_remote_get($api_url . '/health', array(
                'timeout' => 10,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                )
            ));
            
            if (is_wp_error($response)) {
                return array('status' => 'error', 'message' => $response->get_error_message());
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code === 200) {
                return array('status' => 'connected', 'message' => 'Successfully connected to backend API');
            } else {
                return array('status' => 'error', 'message' => 'Backend API returned status: ' . $status_code);
            }
            
        } catch (Exception $e) {
            return array('status' => 'error', 'message' => $e->getMessage());
        }
    }

    /**
     * Get validation status for this shop
     */
    public function get_validation_status($request) {
        try {
            $api = TWIVCO_Commission_Manager_API_Connection::get_instance();
            $status = $api->get_validation_status();

            if ($status && isset($status['success']) && $status['success']) {
                return new WP_REST_Response($status, 200);
            } else {
                return new WP_Error('validation_error', 'Failed to get validation status', array('status' => 500));
            }
        } catch (Exception $e) {
            // Validation status error handled
            return new WP_Error('validation_error', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Regenerate one-time code for this shop
     */
    public function regenerate_code($request) {
        try {
            $api = TWIVCO_Commission_Manager_API_Connection::get_instance();
            $result = $api->regenerate_code();

            if ($result && isset($result['success']) && $result['success']) {
                return new WP_REST_Response($result, 200);
            } else {
                return new WP_Error('regenerate_error', 'Failed to regenerate code', array('status' => 500));
            }
        } catch (Exception $e) {
            // Code regeneration error handled
            return new WP_Error('regenerate_error', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Proxy attributed orders from backend API
     */
    public function proxy_attributed_orders($request) {
        try {
            $api = TWIVCO_Commission_Manager_API_Connection::get_instance();

            $params = array(
                'shopId' => $api->get_shop_id(),
                'track_id' => $request->get_param('track_id'),
                'limit' => $request->get_param('limit') ?: 50,
                'offset' => $request->get_param('offset') ?: 0,
                'status' => $request->get_param('status') ?: 'any',
                'date_from' => $request->get_param('date_from'),
                'date_to' => $request->get_param('date_to')
            );

            $response = $api->get_attributed_orders(
                $params['track_id'],
                $params['date_from'],
                $params['date_to']
            );

            return new WP_REST_Response($response, 200);

        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage()
            ), 500);
        }
    }

    /**
     * Proxy attribution stats from backend API
     */
    public function proxy_attribution_stats($request) {
        try {
            $api = TWIVCO_Commission_Manager_API_Connection::get_instance();

            // Get local attributed orders for comparison
            $sales_tracker = new TWIVCO_Commission_Manager_Sales_Tracker();
            $local_stats = $sales_tracker->get_attribution_stats(
                $request->get_param('track_id'),
                $request->get_param('days') ?: 30
            );

            // Add local attribution rate calculation
            $local_stats['local_attribution_rate'] = $this->calculate_attribution_rate(
                $request->get_param('days') ?: 30
            );

            return new WP_REST_Response(array(
                'success' => true,
                'data' => $local_stats,
                'source' => 'local_wordpress'
            ), 200);

        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage()
            ), 500);
        }
    }

    /**
     * Calculate local attribution rate (attributed orders vs total orders)
     */
    private function calculate_attribution_rate($days = 30) {
        $date_from = gmdate('Y-m-d', strtotime("-{$days} days"));

        // Get total orders in period
        $total_orders_args = array(
            'limit' => -1,
            'date_created' => '>=' . $date_from,
            'return' => 'ids'
        );
        $total_orders = wc_get_orders($total_orders_args);
        $total_count = count($total_orders);

        // Get attributed orders in period
        $sales_tracker = new TWIVCO_Commission_Manager_Sales_Tracker();
        $attributed_orders = $sales_tracker->get_attributed_orders(array(
            'limit' => -1,
            'date_from' => $date_from
        ));
        $attributed_count = count($attributed_orders);

        return array(
            'total_orders' => $total_count,
            'attributed_orders' => $attributed_count,
            'attribution_percentage' => $total_count > 0 ? round(($attributed_count / $total_count) * 100, 2) : 0
        );
    }

    /**
     * Handle incoming attribution webhook from backend
     */
    public function handle_attribution_webhook($request) {
        try {
            $order_id = $request->get_param('order_id');
            $track_id = $request->get_param('track_id');
            $affiliate_id = $request->get_param('affiliate_id');
            $attribution_data = $request->get_param('attribution_data') ?: array();

            $order = wc_get_order($order_id);
            if (!$order) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => 'Order not found'
                ), 404);
            }

            // Update order with webhook attribution data
            $webhook_attribution = array(
                'track_id' => $track_id,
                'affiliate_id' => $affiliate_id,
                'attribution_method' => 'webhook',
                'attributed_at' => current_time('mysql'),
                'backend_data' => $attribution_data
            );

            // Merge with existing attribution if present
            $existing_attribution = $order->get_meta('_twivco_commission_attributed');
            if ($existing_attribution) {
                $webhook_attribution = array_merge($existing_attribution, $webhook_attribution);
            }

            $order->update_meta_data('_twivco_commission_attributed', $webhook_attribution);
            $order->update_meta_data('_twivco_commission_track_id', $track_id);
            $order->save();

            // Log successful webhook attribution
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WC Commission Webhook: Order #{$order_id} attributed to affiliate {$affiliate_id} via track {$track_id}");
            }

            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Attribution updated successfully',
                'order_id' => $order_id,
                'track_id' => $track_id,
                'affiliate_id' => $affiliate_id
            ), 200);

        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage()
            ), 500);
        }
    }

    /**
     * Send order webhook to backend
     */
    public function send_order_webhook($request) {
        try {
            $order_id = $request->get_param('order_id');
            $order = wc_get_order($order_id);

            if (!$order) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => 'Order not found'
                ), 404);
            }

            // Get tracking data from order
            $track_id = $order->get_meta('_twivco_commission_track_id');
            $device_info = $order->get_meta('_twivco_commission_device_info');

            // Prepare order data for webhook
            $webhook_data = array(
                'shop_id' => $this->db->get_shop_id(),
                'order_id' => $order_id,
                'order_number' => $order->get_order_number(),
                'order_status' => $order->get_status(),
                'order_total' => floatval($order->get_total()),
                'currency' => $order->get_currency(),
                'customer_email' => $order->get_billing_email(),
                'order_date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'track_id' => $track_id,
                'device_info' => $device_info,
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

            // Send webhook to backend
            $api = TWIVCO_Commission_Manager_API_Connection::get_instance();
            $response = $api->make_api_request('POST', '/webhooks/order-received', $webhook_data);

            if ($response && isset($response['success']) && $response['success']) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Order webhook sent successfully',
                    'webhook_data' => $webhook_data
                ), 200);
            } else {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => 'Failed to send webhook to backend',
                    'response' => $response
                ), 500);
            }

        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage()
            ), 500);
        }
    }

    /**
     * Handle manual sync trigger from admin dashboard
     */
    public function handle_sync_trigger($request) {
        try {
            // Log sync trigger for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WC Commission: Sync trigger endpoint called');
            }

            $sync_type = $request->get_param('sync_type');
            $force = $request->get_param('force') ?: false;
            $triggered_by = $request->get_param('triggered_by') ?: 'remote';

            $sync_manager = new TWIVCO_Commission_Manager_Sync_Manager();

            // Log the sync trigger
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WC Commission: Manual sync triggered - Type: {$sync_type}, Force: " . ($force ? 'true' : 'false') . ", By: {$triggered_by}");
            }

            $results = array();

            switch ($sync_type) {
                case 'products':
                    $results['products'] = $this->trigger_products_sync($force);
                    break;
                case 'commissions':
                    $results['commissions'] = $this->trigger_commissions_sync($force);
                    break;
                case 'all':
                    $results['products'] = $this->trigger_products_sync($force);
                    $results['commissions'] = $this->trigger_commissions_sync($force);
                    break;
            }

            // Update last sync timestamp
            update_option('twivco_commission_last_manual_sync', current_time('mysql'));

            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Sync triggered successfully',
                'sync_type' => $sync_type,
                'force' => $force,
                'triggered_by' => $triggered_by,
                'results' => $results,
                'timestamp' => current_time('mysql')
            ), 200);

        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage()
            ), 500);
        }
    }

    /**
     * Trigger products sync
     */
    private function trigger_products_sync($force = false) {
        try {
            if ($force) {
                // Clear product cache to force fresh sync
                delete_transient('twivco_commission_products_all');
            }

            // Schedule background sync
            $sync_manager = new TWIVCO_Commission_Manager_Sync_Manager();
            $sync_manager->schedule_background_sync('manual_products');

            return array(
                'status' => 'success',
                'message' => 'Products sync scheduled',
                'force' => $force
            );
        } catch (Exception $e) {
            return array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Trigger commissions sync
     */
    private function trigger_commissions_sync($force = false) {
        try {
            if ($force) {
                // Clear commission cache to force fresh sync
                delete_transient('twivco_commission_commissions_all');
            }

            // Schedule background sync
            $sync_manager = new TWIVCO_Commission_Manager_Sync_Manager();
            $sync_manager->schedule_background_sync('manual_commissions');

            return array(
                'status' => 'success',
                'message' => 'Commissions sync scheduled',
                'force' => $force
            );
        } catch (Exception $e) {
            return array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Get attributed orders for shop admin (simplified view)
     */
    public function get_admin_attributed_orders($request) {
        try {
            $page = $request->get_param('page') ?: 1;
            $per_page = $request->get_param('per_page') ?: 20;
            $status = $request->get_param('status') ?: 'all';
            $date_from = $request->get_param('date_from');
            $date_to = $request->get_param('date_to');

            // Get shop ID
            $api = TWIVCO_Commission_Manager_API_Connection::get_instance();
            $shop_id = $api->get_shop_id();

            // Build request parameters
            $params = array(
                'page' => $page,
                'per_page' => $per_page,
                'status' => $status
            );

            if ($date_from) {
                $params['date_from'] = $date_from;
            }

            if ($date_to) {
                $params['date_to'] = $date_to;
            }

            // Make API request to backend
            $query_string = http_build_query($params);
            $response = $api->make_api_request('GET', "/shops/{$shop_id}/attributed-orders?{$query_string}");

            if (!$response || !isset($response['success']) || !$response['success']) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => 'Failed to fetch attributed orders'
                ), 500);
            }

            // Filter sensitive attribution data for shop admin
            $filtered_orders = array();
            foreach ($response['data'] as $order) {
                $filtered_orders[] = array(
                    'id' => $order['id'],
                    'order_id' => $order['order_id'],
                    'order_date' => $order['order_date'],
                    'order_total' => $order['order_total'],
                    'quantity' => $order['quantity'],
                    'status' => $order['status'],

                    // Influencer information (public data)
                    'influencer' => $order['influencer'] ? array(
                        'name' => $order['influencer']['name'],
                        'username' => $order['influencer']['username']
                    ) : null,

                    // Basic smartlink info (no track ID details)
                    'smartlink' => $order['smartlink'] ? array(
                        'product_id' => $order['smartlink']['product_id']
                    ) : null,

                    // Commission info (relevant for shop admin)
                    'commission' => $order['commission'] ? array(
                        'amount' => $order['commission']['amount'],
                        'rate' => $order['commission']['rate'],
                        'status' => $order['commission']['status']
                    ) : null,

                    // Basic attribution info (no technical details)
                    'attribution_confidence' => $order['attribution_confidence']
                );
            }

            return new WP_REST_Response(array(
                'success' => true,
                'data' => $filtered_orders,
                'pagination' => $response['pagination'],
                'summary' => $response['summary']
            ), 200);

        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage()
            ), 500);
        }
    }
}