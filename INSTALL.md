# Installation Guide - WooCommerce Commission Manager

Simple installation guide for the WooCommerce Commission Manager plugin.

## Pre-Installation Requirements

### System Requirements
- **WordPress**: 5.8 or higher
- **WooCommerce**: 6.0 or higher  
- **PHP**: 7.4 or higher

### Important Notes
- **No database setup required by users**
- **All data is stored securely in our external database**
- **No configuration needed - works immediately after activation**

## Installation Steps

### Step 1: Install Plugin

**Method A: WordPress Admin (Recommended)**
1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin"
4. Choose the ZIP file and click "Install Now"
5. Click "Activate Plugin"

**Method B: FTP/File Manager**
1. Extract the ZIP file
2. Upload the `woocommerce-commission-manager` folder to `/wp-content/plugins/`
3. Go to WordPress Admin → Plugins
4. Find "WooCommerce Commission Manager" and click "Activate"

### Step 2: Verify Installation

1. Go to WordPress Admin → WooCommerce → Commission Manager
2. You should see four tabs: Overview, Products, Categories, Commissions
3. The plugin will automatically sync your products in the background

**That's it! No database configuration required.**

## How It Works

### Automatic Setup
- **Instant Activation**: Plugin works immediately after installation
- **Background Sync**: Products automatically sync to our secure database
- **Multi-Tenant**: Each WordPress site gets a unique identifier
- **Zero Configuration**: No database credentials or setup needed

### Data Storage
- **External Database**: All data stored in our secure external MySQL database
- **Automatic Tables**: Database tables created automatically
- **Unique Shop IDs**: Each WordPress site gets a unique identifier based on site URL
- **Secure Storage**: All data encrypted and securely stored

### Commission Rates
The plugin includes pre-configured commission rates:

| Category | Commission Rate |
|----------|----------------|
| Phones & Tablets | 4% |
| Electronics | 5-11% (varies by subcategory) |
| Fashion | 11% |
| Beauty & Health | 11-14% |
| Home & Living | 8-12% |
| Sports & Outdoors | 11% |
| Groceries | 11-15% |
| Other/Uncategorized | 15% |

*Full rate table available in the Categories tab*

## Features Available Immediately

### Overview Tab
- Total commission statistics
- Product count summaries  
- Commission value totals in KSH

### Products Tab
- List all WooCommerce products
- Automatic commission calculations
- Direct links to public product pages
- Category-based commission rates

### Categories Tab
- View all product categories
- Commission rates per category
- Product counts and total values
- Links to public category pages

### Commission Setup Tab
- Detailed commission breakdown
- Category-based rate structure
- Summary statistics and totals

## Automatic Synchronization

The plugin automatically keeps data synchronized:

### Sync Triggers
- **Plugin Access**: When you visit the Commission Manager page
- **Product Updates**: When products are modified in WooCommerce
- **Daily Sync**: Automatic background sync every 24 hours
- **Admin Login**: When WooCommerce administrators log in

### Multi-Site Support
- **Unique Shop IDs**: Each WordPress site gets a unique identifier
- **Isolated Data**: Your data is separated from other users
- **Scalable**: Supports unlimited WordPress installations

## Troubleshooting

### Plugin not appearing in WooCommerce menu
- Verify WooCommerce is active
- Check plugin is activated in Plugins page
- Ensure you have WooCommerce management permissions

### "No products found"
- Confirm WooCommerce products exist and are published
- Check products have been assigned to categories
- Wait a few minutes for initial sync to complete

### Commission rates not displaying
- Products may need to be categorized properly
- Visit the Categories tab to see available commission rates
- Uncategorized products use the default 15% rate

### General Issues
- Try deactivating and reactivating the plugin
- Clear any caching plugins
- Check WordPress error logs in wp-content/debug.log

## Support

For technical support:
- Check that WooCommerce is active and working
- Ensure your products are properly categorized
- Visit all tabs to verify plugin functionality
- Contact plugin support if issues persist

## Privacy & Data

### Data Collection
- **Product Information**: Names, prices, categories, SKUs
- **Commission Calculations**: Rates and amounts
- **Site Information**: WordPress site URL for unique identification
- **No Personal Data**: No customer data or personal information collected

### Data Security
- **External Storage**: Data stored in secure external database
- **Encrypted Connections**: All data transmission encrypted
- **Access Control**: Only your WordPress site can access your data
- **No Sharing**: Your data is never shared with other users

### Data Retention
- **Active Storage**: Data stored while plugin is active
- **Automatic Cleanup**: Data removed if plugin is uninstalled
- **Backup Available**: Contact support for data export if needed

## Developer Information

### Database Structure
- **Multi-Tenant**: Supports unlimited WordPress sites
- **External MySQL**: Centralized database controlled by plugin developer
- **Automatic Scaling**: Database handles growth automatically
- **Performance Optimized**: Fast queries with proper indexing

### Technical Details
- **Shop ID Generation**: Unique hash based on site URL and blog ID
- **API Endpoints**: Custom WordPress REST API for frontend
- **Background Processing**: WordPress cron for synchronization
- **Error Handling**: Comprehensive logging and error recovery

This plugin is designed for simplicity - no complex setup, no database configuration, just install and use!