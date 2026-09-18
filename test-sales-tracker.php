<?php
/**
 * Test script to manually trigger sales tracker for order #127
 */

// Since this is a WordPress plugin, we need to trigger it through WordPress
// Let's create a simple test URL to trigger order processing

echo "Test Sales Tracker for Order #127\n";
echo "==================================\n\n";

// Simulate what should happen:
echo "1. Order #127 was placed through smart link olA4NZI1S5\n";
echo "2. Tracking cookie should have been set\n";
echo "3. Order status: Processing (should trigger sales tracker)\n";
echo "4. Sales tracker should record sale to backend API\n\n";

echo "To test manually:\n";
echo "1. Visit: http://localhost:10013/wp-admin/admin.php?page=twiva-commerce\n";
echo "2. Check if any errors appear\n";
echo "3. Look for order tracking data\n\n";

echo "Expected API call:\n";
echo "POST http://127.0.0.1:8000/api/sales/record\n";
echo "{\n";
echo "  'shopId': 'shop_c2bf6071a5dff9bd5b58278b9c50dc97',\n";
echo "  'orderId': 127,\n";
echo "  'trackingId': 'olA4NZI1S5',\n";
echo "  'totalAmount': 249.00\n";
echo "}\n";