=== Twiva Commerce ===
Contributors: twiva
Site: https://twiva.com
Tags: woocommerce, commissions, affiliate, marketing, tracking
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 1.4.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Powerful commission tracking and affiliate management for WooCommerce stores. Connect your store to the Twiva Commerce platform.

== Description ==

Twiva Commerce is a professional WordPress plugin for managing category-based commission rates in WooCommerce stores. Track affiliate performance, manage commissions, and connect seamlessly to the Twiva Commerce platform.

= Key Features =

* **Category-Based Commissions**: Automatic commission calculation based on product categories
* **Real-Time Calculations**: Live commission amount calculations
* **Professional Dashboard**: Clean, intuitive interface for managing commissions
* **Comprehensive Statistics**: Overview of commission rates and totals per category
* **Product Management**: View products with their categories and commission rates
* **Smart Link Tracking**: Advanced affiliate tracking and attribution
* **API Integration**: Connect to external commission management systems

= Commission Rates Structure =

The plugin includes pre-configured commission rates based on industry standards:

* Phones & Tablets: 4%
* Electronics: 6-11%
* Fashion: 11%
* Beauty & Health: 12-13%
* Home & Living: 11%
* Sports & Outdoors: 11%
* Other/Uncategorized: 15%

== Installation ==

= Automatic Installation =

1. Log in to your WordPress dashboard
2. Navigate to Plugins → Add New
3. Search for "Twiva Commerce"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin" and select the ZIP file
4. Activate the plugin

= Configuration =

1. Go to WooCommerce → Twiva Commerce
2. Configure your commission rates
3. Connect to Twiva Commerce platform (optional)

== External services ==

This plugin connects to the Twiva Commerce API to synchronize commission data and provide affiliate tracking functionality.

= What the service is and what it is used for =

The Twiva Commerce API (https://commerce.twiva.com/api) is used for:
* Synchronizing product and commission data
* Processing affiliate tracking and attribution
* Recording sales and commission calculations
* Providing centralized reporting and analytics

= What data is sent and when =

The following data is sent to the Twiva Commerce API:
* Shop information (shop URL, name, platform) during initial registration
* Product data (ID, name, category, price) when products are created or updated
* Commission data (rates, calculations) when commissions are processed
* Order data (order ID, products, amounts) when sales are completed
* Tracking data (referral information, attribution data) for affiliate management

Data is sent:
* During plugin activation (shop registration)
* When products are created, updated, or deleted
* When orders are placed and completed
* When commission rates are modified
* During periodic synchronization processes

= Privacy and Terms =

This service is provided by Twiva Commerce:
* Terms of Service: https://twiva.com/terms
* Privacy Policy: https://twiva.com/privacy

By using this plugin, you acknowledge that data will be transmitted to the Twiva Commerce platform as described above. Please ensure compliance with applicable data protection regulations in your jurisdiction.

== Frequently Asked Questions ==

= Does this plugin work with WooCommerce? =

Yes, this plugin requires WooCommerce to be installed and activated.

= Can I customize commission rates? =

Yes, commission rates can be customized through the admin interface or by editing the commission rates configuration file.

= Does it support external databases? =

Yes, the plugin can connect to external databases for centralized commission management across multiple stores.

= Is my data secure when sent to the Twiva Commerce API? =

Yes, all data transmissions are secured using HTTPS encryption. Please review our privacy policy for detailed information about data handling and protection.

== Screenshots ==

1. Commission dashboard showing category-based rates and statistics
2. Product management interface with commission calculations
3. Smart link tracking and affiliate management
4. Settings and configuration panel

== Changelog ==

= 1.4.1 =
* WordPress.org compliance: Updated all prefixes from WC_ to TWIVCO_ for uniqueness
* Enhanced security: Added proper nonce validation and permission checks
* Improved code quality: Fixed variable escaping in inline scripts
* Documentation: Added comprehensive external service documentation
* Code cleanup: Removed development files and localhost references
* Security improvements and WordPress coding standards compliance

= 1.3.0 =
* Improved API integration
* Enhanced security and input validation
* Better error handling and logging
* WordPress 6.4 compatibility
* Code optimization and cleanup

= 1.2.0 =
* Added smart link tracking
* Improved commission calculations
* Enhanced dashboard interface
* Bug fixes and performance improvements

= 1.1.0 =
* Initial stable release
* Category-based commission system
* WooCommerce integration
* Basic reporting features

== Upgrade Notice ==

= 1.4.1 =
Important WordPress.org compliance update with unique prefixes, enhanced security, and improved code quality. Required for all users.

= 1.3.0 =
Major update with improved security, API enhancements, and WordPress 6.8 compatibility. Recommended for all users.

== Support ==

For support and documentation, visit [Twiva Commerce Support](https://twiva.com/support) or contact us at support@twiva.com.

== License ==

This plugin is licensed under the GPLv2 or later.