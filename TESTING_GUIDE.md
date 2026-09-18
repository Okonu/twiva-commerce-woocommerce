# WooCommerce Commission Manager - Testing Guide

## 🎯 What We've Completed

### ✅ Environment Setup
- Created `.env` file for database configuration
- Added environment loader class (`class-env-loader.php`)
- Updated main plugin to load environment variables automatically
- **Environment Variables Support**: Plugin now supports both wp-config.php constants and .env files

### ✅ Supabase References Removed
- Removed all Supabase references from codebase
- Updated to use "External MySQL" terminology
- Updated documentation (README.md, CHANGELOG.md)
- Database factory now supports local MySQL and external MySQL options

### ✅ JavaScript Refactoring
- Split monolithic `admin.js` into modular components:
  - `components/app.js` - Main application component
  - `components/overview-tab.js` - Overview tab component  
  - `components/products-tab.js` - Products tab component
  - `components/categories-tab.js` - Categories tab component
  - `components/commissions-tab.js` - Commissions tab component
  - `utils/styles.js` - CSS utilities and styling
- Updated `admin.js` to be main entry point that loads all modules
- **Modular Architecture**: Each component has single responsibility

### ✅ Missing Files Created
- `build/js/admin.asset.php` - WordPress dependencies configuration
- `build/css/admin.css` - Admin interface styling
- Environment configuration files (.env, .env.example)

### ✅ Plugin Deployed
- **Plugin Location**: `/home/okonu/Local Sites/commerce-test/app/public/wp-content/plugins/woocommerce-commission-manager/`
- All updated files copied to your Flywheel Local site
- Proper permissions set (755)

## 🧪 Testing Instructions

### 1. WordPress Admin Testing
Visit: **http://commerce-test.local/wp-admin/**

#### Steps:
1. **Activate Plugin**: Go to Plugins → Activate "WooCommerce Commission Manager"
2. **Access Admin**: WooCommerce → Commission Manager 
3. **Check JavaScript Loading**: Open browser dev tools, check for any console errors
4. **Test Tab Navigation**: Click through Overview, Products, Categories, Commissions tabs
5. **Verify Components**: Each tab should load its respective component without errors

### 2. Database Configuration Testing

#### Option A: Use Local MySQL (Default)
- Plugin will use your WordPress database by default
- No additional configuration needed

#### Option B: Use External MySQL (Optional)
1. **Create Database**:
   ```sql
   mysql -u root -p
   CREATE DATABASE wc_commission_manager;
   exit
   ```

2. **Configure in wp-config.php** (add before "/* That's all, stop editing! */"):
   ```php
   // Commission Manager External Database
   define('WC_COMMISSION_DB_HOST', 'localhost');
   define('WC_COMMISSION_DB_NAME', 'wc_commission_manager');
   define('WC_COMMISSION_DB_USER', 'root');  
   define('WC_COMMISSION_DB_PASS', 'your_password');
   define('WC_COMMISSION_DB_PORT', 3306);
   ```

3. **Or use .env file** (already created in plugin directory):
   ```
   WC_COMMISSION_DB_HOST=localhost
   WC_COMMISSION_DB_NAME=wc_commission_manager
   WC_COMMISSION_DB_USER=root
   WC_COMMISSION_DB_PASS=your_password
   ```

### 3. REST API Testing
Visit: **http://commerce-test.local/test-api.html**

This test page will verify:
- `/wp-json/wc-commission/v1/overview` - Overview statistics
- `/wp-json/wc-commission/v1/products` - Products with commission rates
- `/wp-json/wc-commission/v1/categories` - Product categories
- `/wp-json/wc-commission/v1/commissions` - Commission breakdown

### 4. Database Connection Testing

#### Check Plugin Status:
1. Go to WooCommerce → Commission Manager
2. Look for database connection status in browser console
3. Any connection issues will appear as JavaScript console errors

#### Manual Database Test:
```php
// Add this to wp-config.php temporarily for testing
define('WC_COMMISSION_DEBUG', true);
```
Then check WordPress error logs for detailed database connection info.

## 🔍 What to Look For

### ✅ Success Indicators:
- Plugin appears in WooCommerce menu
- Admin interface loads without JavaScript errors
- All 4 tabs (Overview, Products, Categories, Commissions) work
- API endpoints return JSON data (test via test-api.html)
- No PHP fatal errors in WordPress

### ❌ Potential Issues:
- **JavaScript Errors**: Check browser console for script loading issues
- **Permission Errors**: Files might need correct WordPress permissions
- **Database Errors**: Check if WooCommerce is active and has products
- **API 404 Errors**: REST routes might not be registered correctly

## 🛠 Troubleshooting

### Issue: JavaScript Components Not Loading
**Solution**: Check that all component files exist in `/build/js/components/`

### Issue: Database Connection Failed  
**Solution**: Verify database credentials in wp-config.php or .env file

### Issue: API Returns 404
**Solution**: Go to Settings → Permalinks and click "Save Changes" to flush rewrite rules

### Issue: No Products/Categories Showing
**Solution**: Make sure WooCommerce is active and has some products created

## 📁 File Structure
```
woocommerce-commission-manager/
├── .env                          # Environment variables
├── twiva-commerce.php  # Main plugin file
├── includes/
│   ├── class-env-loader.php      # Environment loader
│   ├── class-real-db-connection.php  # External MySQL connection
│   ├── class-mysql-db-connection.php # Local MySQL connection
│   ├── class-db-factory.php      # Database factory
│   └── class-real-rest-controller.php # REST API endpoints
├── build/
│   ├── js/
│   │   ├── admin.js              # Main entry point
│   │   ├── admin.asset.php       # WordPress dependencies
│   │   ├── components/           # React components
│   │   └── utils/                # Utilities
│   └── css/
│       └── admin.css             # Admin styles
└── data/
    └── commission-rates.json     # Commission rate configuration
```

## 🚀 Next Steps After Testing

1. **Verify Plugin Functionality**: Complete all testing steps above
2. **Add Sample Data**: Create some WooCommerce products and categories for testing
3. **Test Commission Calculations**: Verify commission rates are applied correctly
4. **Performance Testing**: Test with larger datasets
5. **Error Handling**: Test edge cases and error scenarios

---
**Testing URL**: http://commerce-test.local/wp-admin/
**API Test URL**: http://commerce-test.local/test-api.html