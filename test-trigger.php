<?php
/**
 * Manual test script to trigger sales tracking
 */

// Create a test URL that will trigger the order reprocessing
$test_url = 'http://localhost:10013/?wc_commission_test=reprocess_orders';

echo "Manual Sales Tracker Test\n";
echo "========================\n\n";

echo "To manually trigger sales tracking for orders #126 and #127:\n\n";

echo "1. Visit this URL in your browser:\n";
echo "   {$test_url}\n\n";

echo "2. Or trigger via WordPress admin:\n";
echo "   http://localhost:10013/wp-admin/admin.php?page=twiva-commerce&wc_commission_test=reprocess\n\n";

echo "3. Expected results:\n";
echo "   - Order #126 should be attributed to tracking ID: NXiXmEYawH\n";
echo "   - Order #127 should be attributed to tracking ID: olA4NZI1S5\n";
echo "   - Sales records should appear in backend API\n\n";

echo "4. Check logs for activity:\n";
echo "   tail -20 '/home/okonu/Local Sites/twiva/logs/php/error.log' | grep 'WC Commission'\n\n";

echo "5. Check backend for sales:\n";
echo "   cd /home/okonu/work/wc-commission-api && php artisan tinker\n";
echo "   App\\Models\\SalesAttribution::all()\n";