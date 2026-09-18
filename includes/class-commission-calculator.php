<?php

/**
 * Commission Calculator Class
 * Handles automatic commission calculation based on product categories and rates
 */
class TWIVCO_Commission_Manager_Calculator {
    
    private $commission_rates = null;
    private $db = null;
    
    public function __construct() {
        $this->db = TWIVCO_Commission_Manager_DB_Factory::get_connection();
    }
    
    /**
     * Load commission rates from JSON file
     */
    private function load_commission_rates() {
        if ($this->commission_rates !== null) {
            return $this->commission_rates;
        }
        
        $json_file = TWIVCO_COMMISSION_MANAGER_PLUGIN_PATH . 'data/commission-rates.json';
        
        if (!file_exists($json_file)) {
            return array();
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array();
        }
        
        $this->commission_rates = $data['commission_rates'] ?? array();
        return $this->commission_rates;
    }
    
    /**
     * Calculate commission for a product
     * 
     * @param int $product_id WooCommerce product ID
     * @return array Commission calculation result with metadata
     */
    public function calculate_product_commission($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return array(
                'success' => false,
                'error' => 'Product not found',
                'product_id' => $product_id
            );
        }
        
        $price = floatval($product->get_price());
        $category_ids = $product->get_category_ids();
        $commission_rates = $this->load_commission_rates();

        $categories = array();
        foreach ($category_ids as $cat_id) {
            $category = get_term($cat_id, 'product_cat');
            if ($category && !is_wp_error($category)) {
                $categories[] = array(
                    'id' => $cat_id,
                    'name' => $category->name,
                    'slug' => $category->slug
                );
            }
        }

        $rate_result = $this->determine_commission_rate($categories, $commission_rates);
        $commission_amount = $price * ($rate_result['rate'] / 100);
        
        return array(
            'success' => true,
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'price' => $price,
            'categories' => $categories,
            'commission_rate' => $rate_result['rate'],
            'commission_amount' => round($commission_amount, 2),
            'calculation_type' => $rate_result['type'],
            'rate_source' => $rate_result['source'],
            'user_message' => $rate_result['message'],
            'matched_categories' => $rate_result['matched_categories'],
            'last_calculated' => current_time('mysql', true)
        );
    }
    
    /**
     * Determine the commission rate for given categories
     * 
     * @param array $categories Product categories
     * @param array $commission_rates Available commission rates
     * @return array Rate determination result
     */
    private function determine_commission_rate($categories, $commission_rates) {
        // Handle uncategorized products
        if (empty($categories)) {
            return array(
                'rate' => 15,
                'type' => 'default',
                'source' => 'Other',
                'message' => 'Using default \'Other\' rate (15%) because product has no categories assigned. Please assign the correct category to this product for accurate commission rate.',
                'matched_categories' => array()
            );
        }

        $matched_rates = array();
        
        foreach ($categories as $category) {
            $rate = $this->find_commission_rate($category['name'], $commission_rates);
            if ($rate > 0) {
                $matched_rates[] = array(
                    'category' => $category['name'],
                    'rate' => $rate,
                    'source' => $this->find_rate_source($category['name'], $commission_rates)
                );
            }
        }

        if (empty($matched_rates)) {
            $category_names = array_column($categories, 'name');
            return array(
                'rate' => 15,
                'type' => 'default',
                'source' => 'Other',
                'message' => 'Using default \'Other\' rate (15%) because no matching commission rates found for categories: ' . implode(', ', $category_names) . '. Please check commission rate configuration.',
                'matched_categories' => array()
            );
        }

        if (count($matched_rates) > 1) {
            usort($matched_rates, function($a, $b) {
                return $b['rate'] - $a['rate'];
            });
            
            $highest_rate = $matched_rates[0];
            $rate_descriptions = array_map(function($match) {
                return $match['category'] . ' (' . $match['rate'] . '%)';
            }, $matched_rates);
            
            return array(
                'rate' => $highest_rate['rate'],
                'type' => 'multiple_categories',
                'source' => $highest_rate['source'],
                'message' => 'Using highest rate (' . $highest_rate['rate'] . '%) from multiple categories: ' . implode(', ', $rate_descriptions) . '.',
                'matched_categories' => $matched_rates
            );
        }

        $single_rate = $matched_rates[0];
        return array(
            'rate' => $single_rate['rate'],
            'type' => 'single_category',
            'source' => $single_rate['source'],
            'message' => 'Using commission rate (' . $single_rate['rate'] . '%) for category: ' . $single_rate['category'] . '.',
            'matched_categories' => $matched_rates
        );
    }
    
    /**
     * Find commission rate for a category name
     * 
     * @param string $category_name Category name to search for
     * @param array $commission_rates Commission rates data
     * @return float Commission rate percentage
     */
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

        return 0;
    }
    
    /**
     * Find the source (main category) for a commission rate
     * 
     * @param string $category_name Category name
     * @param array $commission_rates Commission rates data
     * @return string Source main category name
     */
    private function find_rate_source($category_name, $commission_rates) {
        foreach ($commission_rates as $main_category => $subcategories) {
            if (strtolower($main_category) === strtolower($category_name)) {
                return $main_category;
            }
            
            foreach ($subcategories as $subcategory => $rate) {
                if (strtolower($subcategory) === strtolower($category_name)) {
                    return $main_category;
                }
            }
        }
        
        return 'Other';
    }
    
    /**
     * Save calculated commission to database
     * 
     * @param array $calculation_result Result from calculate_product_commission
     * @return bool Success status
     */
    public function save_commission($calculation_result) {
        if (!$calculation_result['success']) {
            return false;
        }
        
        try {
            $shop_id = $this->db->get_shop_id();
            $product_id = $calculation_result['product_id'];
            
            $commission_data = array(
                'shopId' => $shop_id,
                'productId' => $product_id,
                'commissionValue' => $calculation_result['commission_amount'],
                'commissionType' => 'percentage',
                'commissionRate' => $calculation_result['commission_rate'],
                'productTitle' => $calculation_result['product_name'],
                'lastCalculated' => $calculation_result['last_calculated'],
                'updatedAt' => current_time('mysql', true)
            );

            $existing = $this->db->get_row('product_commissions', array(
                'shopId' => $shop_id,
                'productId' => $product_id
            ));
            
            if ($existing) {
                $result = $this->db->update('product_commissions', 
                    array(
                        'commissionValue' => $commission_data['commissionValue'],
                        'commissionRate' => $commission_data['commissionRate'],
                        'productTitle' => $commission_data['productTitle'],
                        'lastCalculated' => $commission_data['lastCalculated'],
                        'updatedAt' => $commission_data['updatedAt']
                    ),
                    array(
                        'shopId' => $shop_id,
                        'productId' => $product_id
                    )
                );
            } else {
                $commission_data['createdAt'] = current_time('mysql', true);
                $result = $this->db->insert('product_commissions', $commission_data);
            }
            
            return isset($result['affected_rows']) && $result['affected_rows'] > 0;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Calculate and save commission for a product
     * 
     * @param int $product_id WooCommerce product ID
     * @return array Calculation and save result
     */
    public function process_product_commission($product_id) {
        $calculation = $this->calculate_product_commission($product_id);
        
        if ($calculation['success']) {
            $saved = $this->save_commission($calculation);
            $calculation['saved'] = $saved;
            
            if (!$saved) {
                $calculation['save_error'] = 'Failed to save commission to database';
            }
        }
        
        return $calculation;
    }
    
    /**
     * Bulk process commissions for multiple products
     * 
     * @param array $product_ids Array of product IDs
     * @return array Processing results
     */
    public function bulk_process_commissions($product_ids) {
        $results = array(
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'details' => array()
        );
        
        foreach ($product_ids as $product_id) {
            $result = $this->process_product_commission($product_id);
            $results['details'][$product_id] = $result;
            $results['processed']++;
            
            if ($result['success'] && isset($result['saved']) && $result['saved']) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }
}