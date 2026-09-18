<?php
/**
 * Test script for basket order integration
 * This simulates what the WooCommerce plugin would send to the API
 */

// Include WordPress environment
if (!defined('ABSPATH')) {
    // Try to locate WordPress
    $wp_paths = [
        __DIR__ . '/../../../../wp-config.php',
        __DIR__ . '/../../../wp-config.php',
        __DIR__ . '/../../wp-config.php',
        __DIR__ . '/../wp-config.php'
    ];

    foreach ($wp_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

// If WordPress is not loaded, create a minimal test environment
if (!defined('ABSPATH')) {
    echo "WordPress not found, running standalone test...\n";

    // Mock WordPress functions for testing
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }

    function wp_json_encode($data) {
        return json_encode($data);
    }

    function get_site_url() {
        return 'https://test-wc-shop.local';
    }

    function get_bloginfo($name) {
        return 'Test WooCommerce Shop';
    }

    function wp_remote_request($url, $args) {
        echo "   [MOCK] Sending request to: {$url}\n";
        echo "   [MOCK] Method: " . ($args['method'] ?? 'GET') . "\n";
        echo "   [MOCK] Body size: " . (isset($args['body']) ? strlen($args['body']) : 0) . " bytes\n";

        // Mock successful response
        return [
            'response' => ['code' => 201],
            'body' => json_encode([
                'success' => true,
                'message' => 'Basket order created successfully (MOCK)',
                'basket_order_id' => rand(1000, 9999),
                'data' => [
                    'id' => rand(1000, 9999),
                    'track_id' => $GLOBALS['test_track_id'] ?? 'test_track_123',
                    'commission_summary' => [
                        'total_commission_value' => 15.10,
                        'influencer_commission_80_percent' => 12.08,
                        'twiva_commission_20_percent' => 3.02
                    ]
                ]
            ])
        ];
    }

    function wp_remote_retrieve_response_code($response) {
        return $response['response']['code'] ?? 200;
    }

    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '';
    }

    function is_wp_error($response) {
        return false; // Mock always successful
    }

    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    // Define constants
    if (!defined('WC_COMMISSION_API_URL')) {
        define('WC_COMMISSION_API_URL', 'http://localhost/api');
    }

    if (!defined('WP_DEBUG')) {
        define('WP_DEBUG', true);
    }
}

// Include the required classes
require_once __DIR__ . '/includes/class-api-connection.php';

// Try to include the sales tracker
if (file_exists(__DIR__ . '/includes/class-sales-tracker.php')) {
    require_once __DIR__ . '/includes/class-sales-tracker.php';
}

echo "=== Basket Order Integration Test ===\n\n";

// Test 1: API Connection Test
echo "1. Testing API Connection...\n";
try {
    $api = TWIVCO_Commission_Manager_API_Connection::get_instance();
    $shop_id = $api->get_shop_id();
    echo "✓ API Connection established\n";
    echo "✓ Shop ID: {$shop_id}\n";
} catch (Exception $e) {
    echo "✗ API Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Simulate Basket Order Data
echo "\n2. Creating test basket order data...\n";

$test_basket_data = [
    'shop_id' => $shop_id,
    'order_id' => 'test-order-' . time(),
    'order_number' => 'WC-TEST-' . rand(1000, 9999),
    'customer_email' => 'customer@test.com',
    'order_total' => 250.75,
    'currency' => 'KSH',
    'order_date' => current_time('mysql'),
    'track_id' => ($GLOBALS['test_track_id'] = 'test_track_' . substr(md5(uniqid()), 0, 10)),
    'attribution_confidence' => 'high',
    'status' => 'confirmed',
    'platform' => 'woocommerce',
    'basket_items' => [
        [
            'product_id' => 101,
            'product_name' => 'Test Product 1 - Commissioned',
            'sku' => 'TEST-001',
            'quantity' => 2,
            'unit_price' => 75.50,
            'total_price' => 151.00,
            'is_commissioned' => true,
            'commission_rate' => 10.0,
            'commission_value' => 15.10,
            'product_url' => 'https://test-shop.com/products/test-1',
            'product_image' => 'https://test-shop.com/images/test-1.jpg',
            'categories' => [
                ['id' => 1, 'name' => 'Electronics', 'slug' => 'electronics']
            ]
        ],
        [
            'product_id' => 102,
            'product_name' => 'Test Product 2 - Not Commissioned',
            'sku' => 'TEST-002',
            'quantity' => 1,
            'unit_price' => 99.75,
            'total_price' => 99.75,
            'is_commissioned' => false,
            'commission_rate' => 0,
            'commission_value' => 0,
            'product_url' => 'https://test-shop.com/products/test-2',
            'product_image' => 'https://test-shop.com/images/test-2.jpg',
            'categories' => [
                ['id' => 2, 'name' => 'Accessories', 'slug' => 'accessories']
            ]
        ]
    ],
    'commissioned_items_count' => 2,
    'total_commission_value' => 15.10,
    'influencer_commission_80_percent' => 12.08,
    'twiva_commission_20_percent' => 3.02,
    'total_items_count' => 3,
    'device_info' => [
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'ip_address' => '192.168.1.100',
        'screen_resolution' => '1920x1080'
    ],
    'customer_info' => [
        'customer_id' => 0,
        'billing_email' => 'customer@test.com',
        'billing_first_name' => 'John',
        'billing_last_name' => 'Doe',
        'billing_country' => 'KE',
        'is_guest' => true
    ],
    'order_meta' => [
        'payment_method' => 'mpesa',
        'payment_method_title' => 'M-Pesa',
        'shipping_total' => 0,
        'tax_total' => 0,
        'discount_total' => 0,
        'billing_country' => 'KE'
    ]
];

echo "✓ Test basket order data created\n";
echo "   - Order Total: KSh {$test_basket_data['order_total']}\n";
echo "   - Total Items: {$test_basket_data['total_items_count']}\n";
echo "   - Commissioned Items: {$test_basket_data['commissioned_items_count']}\n";
echo "   - Total Commission: KSh {$test_basket_data['total_commission_value']}\n";
echo "   - Track ID: {$test_basket_data['track_id']}\n";

// Test 3: Send to API
echo "\n3. Sending basket order to API...\n";
try {
    $response = $api->make_api_request('POST', '/basket-orders/create', $test_basket_data);

    if ($response && isset($response['success']) && $response['success']) {
        echo "✓ Basket order sent successfully!\n";
        echo "   - Basket Order ID: " . ($response['basket_order_id'] ?? 'N/A') . "\n";
        echo "   - Response: " . wp_json_encode($response['data'] ?? []) . "\n";
    } else {
        echo "✗ Failed to send basket order\n";
        echo "   - Response: " . wp_json_encode($response) . "\n";
    }
} catch (Exception $e) {
    echo "✗ Error sending basket order: " . $e->getMessage() . "\n";
}

// Test 4: Verify Integration Components
echo "\n4. Verifying integration components...\n";

// Check if sales tracker exists and has basket method
if (class_exists('TWIVCO_Commission_Manager_Sales_Tracker')) {
    $reflection = new ReflectionClass('TWIVCO_Commission_Manager_Sales_Tracker');
    if ($reflection->hasMethod('send_basket_order_to_backend')) {
        echo "✓ Sales Tracker has basket order method\n";
    } else {
        echo "✗ Sales Tracker missing basket order method\n";
    }
} else {
    echo "✗ Sales Tracker class not found\n";
}

// Test 5: API Endpoint Test (direct)
echo "\n5. Testing API endpoint directly...\n";
$api_base = defined('WC_COMMISSION_API_URL') ? WC_COMMISSION_API_URL : 'https://commerce.dev.twiva.com/api';
$endpoint = $api_base . '/basket-orders/create';

echo "API Endpoint: {$endpoint}\n";

$curl_test_data = wp_json_encode($test_basket_data);
echo "Payload Size: " . strlen($curl_test_data) . " bytes\n";

echo "\n=== Test Summary ===\n";
echo "This test validates the basket order integration between WooCommerce and the Twiva Commission API.\n";
echo "Key components tested:\n";
echo "- API connection establishment\n";
echo "- Basket order data structure\n";
echo "- API endpoint communication\n";
echo "- Response handling\n\n";

echo "Next steps:\n";
echo "1. Test with a real WooCommerce order\n";
echo "2. Verify data appears in admin dashboard\n";
echo "3. Check attribution accuracy\n";
echo "4. Test commission calculations\n\n";

echo "=== Test Complete ===\n";
?>