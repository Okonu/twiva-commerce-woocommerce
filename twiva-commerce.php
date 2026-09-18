<?php
/**
 * Plugin Name: Twiva Commerce
 * Plugin URI: https://twiva.com/commerce
 * Description: Powerful commission tracking and affiliate management for WooCommerce stores. Connect your store to the Twiva Commerce platform.
 * Version: 1.4.3
 * Author: Twiva
 * Author URI: https://twiva.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: twiva-commerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TWIVCO_COMMISSION_MANAGER_VERSION', '1.4.3');
define('TWIVCO_COMMISSION_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TWIVCO_COMMISSION_MANAGER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TWIVCO_COMMISSION_MANAGER_PLUGIN_FILE', __FILE__);
define('TWIVCO_COMMISSION_MANAGER_PLUGIN_BASENAME', plugin_basename(__FILE__));

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html(__('Twiva Commerce requires WooCommerce to be installed and activated.', 'twiva-commerce'));
        echo '</p></div>';
    });
    return;
}

class TWIVCO_Commission_Manager {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('before_woocommerce_init', array($this, 'declare_compatibility'));

        // Also register on init as fallback
        add_action('init', array($this, 'ensure_rest_routes'), 20);

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Additional hooks to catch activation state changes (these fire every time)
        add_action('activated_plugin', array($this, 'on_plugin_activated'), 10, 2);
        add_action('deactivated_plugin', array($this, 'on_plugin_deactivated'), 10, 2);

        // Also use the traditional activation hook as backup
        add_action('wp_loaded', array($this, 'check_plugin_just_activated'));
    }

    public function declare_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    public function init() {
        $this->includes();
    }

    /**
     * Load required classes (minimal version for activation)
     */
    private function load_required_classes() {
        require_once TWIVCO_COMMISSION_MANAGER_PLUGIN_PATH . 'includes/class-env-loader.php';
        TWIVCO_Commission_Manager_Env_Loader::load();

        require_once TWIVCO_COMMISSION_MANAGER_PLUGIN_PATH . 'includes/class-logger.php';
        require_once TWIVCO_COMMISSION_MANAGER_PLUGIN_PATH . 'includes/class-api-connection.php';
    }

    private function includes() {
        $this->load_required_classes();

        require_once TWIVCO_COMMISSION_MANAGER_PLUGIN_PATH . 'includes/class-db-factory.php';
        require_once TWIVCO_COMMISSION_MANAGER_PLUGIN_PATH . 'includes/class-commission-calculator.php';
        require_once TWIVCO_COMMISSION_MANAGER_PLUGIN_PATH . 'includes/class-commission-hooks.php';
        require_once TWIVCO_COMMISSION_MANAGER_PLUGIN_PATH . 'includes/class-real-rest-controller.php';
        require_once TWIVCO_COMMISSION_MANAGER_PLUGIN_PATH . 'includes/class-sync-manager.php';
        require_once TWIVCO_COMMISSION_MANAGER_PLUGIN_PATH . 'includes/class-sales-tracker.php';

        $sync_manager = new TWIVCO_Commission_Manager_Sync_Manager();
        $sync_manager->init();

        $hooks_manager = new TWIVCO_Commission_Manager_Hooks();
        $hooks_manager->init();

        // Initialize sales tracker with error handling
        try {
            $sales_tracker = new TWIVCO_Commission_Manager_Sales_Tracker();
            $sales_tracker->init();

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WC Commission: Sales tracker initialized successfully');
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WC Commission: Failed to initialize sales tracker: ' . $e->getMessage());
            }
        }

        add_action('wp_ajax_twivco_commission_sync_trigger', array($this, 'handle_sync_trigger'));


        add_action('twivco_commission_background_sync', array($this, 'handle_background_sync'));
        add_action('twivco_commission_incremental_sync', array($this, 'handle_incremental_sync'));

    }

    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'woocommerce_page_twiva-commerce') {
            return;
        }

        $asset_file_path = TWIVCO_COMMISSION_MANAGER_PLUGIN_PATH . 'build/js/admin.asset.php';
        $asset_file = file_exists($asset_file_path) ? include $asset_file_path : array();

        wp_enqueue_script(
            'twiva-commerce-admin',
            TWIVCO_COMMISSION_MANAGER_PLUGIN_URL . 'build/js/admin.js',
            $asset_file['dependencies'] ?? array('wp-element'),
            ($asset_file['version'] ?? TWIVCO_COMMISSION_MANAGER_VERSION) . '-' . time(),
            true
        );

        wp_enqueue_style(
            'twiva-commerce-admin',
            TWIVCO_COMMISSION_MANAGER_PLUGIN_URL . 'build/css/admin.css',
            array(),
            TWIVCO_COMMISSION_MANAGER_VERSION . '-' . time()
        );

        wp_localize_script('twiva-commerce-admin', 'twivco_commission_manager_admin', array(
            'api_url' => home_url('/wp-json/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'plugin_url' => TWIVCO_COMMISSION_MANAGER_PLUGIN_URL,
            'sync_trigger_url' => admin_url('admin-ajax.php?action=twivco_commission_sync_trigger'),
            'orders_endpoint' => home_url('/wp-json/wc-commission/v1/admin/attributed-orders'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
        ));

        // Add debug output and legacy compatibility
        wp_add_inline_script('twiva-commerce-admin', "
            console.log('Localization check:', typeof twivco_commission_manager_admin);
            if (typeof twivco_commission_manager_admin !== 'undefined') {
                console.log('Localization data:', twivco_commission_manager_admin);

                // Legacy compatibility: create alias for old variable name
                if (typeof wc_commission_manager_admin === 'undefined') {
                    window.wc_commission_manager_admin = twivco_commission_manager_admin;
                    console.log('Created legacy alias for wc_commission_manager_admin');
                }
            } else {
                console.error('twivco_commission_manager_admin is not defined!');
            }
        ", 'before');

        wp_add_inline_script('twiva-commerce-admin', "
            document.addEventListener('DOMContentLoaded', function() {
                console.log('Twiva Commerce: Setting up sync triggers');

                // Trigger sync when any tab is clicked
                document.addEventListener('click', function(e) {
                    if (e.target.matches('.nav-tab, .tab-link, [data-tab]')) {
                        console.log('Twiva Commerce: Tab switched, triggering sync');
                        fetch('" . esc_url_raw(admin_url('admin-ajax.php')) . "', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=twivco_commission_sync_trigger&tab=' + (e.target.dataset.tab || 'unknown') + '&nonce=" . esc_js(wp_create_nonce('twivco_commission_sync')) . "'
                        }).catch(function(error) {
                            console.log('Twiva Commerce: Sync trigger failed:', error);
                        });
                    }
                });

                // Also trigger sync on page load
                fetch('" . esc_url_raw(admin_url('admin-ajax.php')) . "', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=twivco_commission_sync_trigger&tab=page_load&nonce=" . esc_js(wp_create_nonce('twivco_commission_sync')) . "'
                }).catch(function(error) {
                    console.log('Twiva Commerce: Page load sync failed:', error);
                });
            });
        ");
    }

    public function admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Twiva Commerce', 'twiva-commerce'),
            __('Twiva Commerce', 'twiva-commerce'),
            'manage_woocommerce',
            'twiva-commerce',
            array($this, 'admin_page')
        );
    }

    public function admin_page() {
        echo '<div id="twiva-commerce-app"></div>';
    }

    public function register_rest_routes() {
        if (class_exists('TWIVCO_Commission_Manager_Real_Rest_Controller')) {
            try {
                $controller = new TWIVCO_Commission_Manager_Real_Rest_Controller();
                $controller->register_routes();

                // Log route registration for debugging
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('WC Commission: REST routes registered successfully');
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('WC Commission: Error registering REST routes: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Ensure REST routes are registered (fallback method)
     */
    public function ensure_rest_routes() {
        // Only register if we're in REST API context and routes aren't already registered
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $this->register_rest_routes();
        }
    }

    public function activate() {
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WC Commission: Plugin activate() method called');
            }

            // Set a flag to indicate we just got activated
            update_option('twivco_plugin_just_activated', time());

            // Schedule product sync
            if (class_exists('TWIVCO_Commission_Manager_Sync_Manager')) {
                wp_schedule_single_event(time() + 10, 'twivco_commission_activation_sync');
                add_action('twivco_commission_activation_sync', array($this, 'activation_sync_callback'));

                update_option('twivco_commission_force_sync_needed', true);
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WC Commission: Error in activate(): ' . $e->getMessage());
            }
        }

        // Ensure REST API is available and flush rewrite rules
        flush_rewrite_rules();

        // Log activation for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WC Commission: Plugin activated, rewrite rules flushed');
        }
    }

    /**
     * Check if plugin was just activated (backup method)
     */
    public function check_plugin_just_activated() {
        $just_activated = get_option('twivco_plugin_just_activated');

        if ($just_activated) {
            // Clear the flag first
            delete_option('twivco_plugin_just_activated');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WC Commission: Plugin activation detected via wp_loaded hook');
            }

            // Send activation status as backup
            try {
                $this->load_required_classes();

                if (class_exists('TWIVCO_Commission_Manager_API_Connection')) {
                    $api = TWIVCO_Commission_Manager_API_Connection::get_instance();
                    $api->register_shop();

                    $this->send_plugin_status_webhook('activated');

                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('WC Commission: Backup activation status sent via wp_loaded');
                    }
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('WC Commission: Backup activation failed: ' . $e->getMessage());
                }
            }
        }
    }


    /**
     * Callback for activation sync
     */
    public function activation_sync_callback() {
        try {
            $sync_manager = new TWIVCO_Commission_Manager_Sync_Manager();
            $sync_manager->sync_all_products();
        } catch (Exception $e) {
        }
    }
    
    /**
     * Handle AJAX sync triggers from frontend tab switches
     */
    public function handle_sync_trigger() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'twivco_commission_sync')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }
        
        $tab = isset($_POST['tab']) ? sanitize_text_field(wp_unslash($_POST['tab'])) : 'unknown';
        
        try {
            $sync_manager = new TWIVCO_Commission_Manager_Sync_Manager();
            $sync_manager->sync_all_products();
            
            wp_send_json_success(array(
                'message' => 'Sync completed successfully',
                'tab' => $tab,
                'timestamp' => current_time('mysql')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Sync failed: ' . $e->getMessage(),
                'tab' => $tab
            ));
        }
    }

    /**
     * Handle background sync cron job
     */
    public function handle_background_sync($trigger = 'cron') {
        $sync_manager = new TWIVCO_Commission_Manager_Sync_Manager();
        $sync_manager->sync_all_products();
    }

    /**
     * Handle incremental sync cron job
     */
    public function handle_incremental_sync() {
        $sync_manager = new TWIVCO_Commission_Manager_Sync_Manager();
        $sync_manager->perform_incremental_sync();
    }

    public function deactivate() {
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WC Commission: Plugin deactivate() method called');
            }

            // Send plugin deactivation status to backend
            $this->send_plugin_status_webhook('deactivated');
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WC Commission: Error in deactivate(): ' . $e->getMessage());
            }
        }

        wp_clear_scheduled_hook('twivco_commission_background_sync');
        wp_clear_scheduled_hook('twivco_commission_incremental_sync');
        wp_clear_scheduled_hook('twivco_commission_manager_periodic_sync');
        flush_rewrite_rules();
    }

    /**
     * Send plugin status webhook to backend
     */
    private function send_plugin_status_webhook($status) {
        try {
            if (!class_exists('TWIVCO_Commission_Manager_API_Connection')) {
                return;
            }

            $api = TWIVCO_Commission_Manager_API_Connection::get_instance();
            $shop_id = $api->get_shop_id();

            $webhook_data = array(
                'status' => $status,
                'plugin_version' => TWIVCO_COMMISSION_MANAGER_VERSION,
                'timestamp' => current_time('mysql'),
                'site_url' => get_site_url(),
                'wp_version' => get_bloginfo('version'),
                'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'unknown'
            );

            // Use API connection to send status update
            $response = $api->make_api_request('POST', "/shops/{$shop_id}/plugin-status", $webhook_data);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                if ($response && isset($response['success']) && $response['success']) {
                    error_log("WC Commission: Plugin status '{$status}' sent successfully");
                } else {
                    error_log("WC Commission: Failed to send plugin status '{$status}'");
                }
            }

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WC Commission: Error sending plugin status webhook: " . $e->getMessage());
            }
        }
    }


    /**
     * Hook for when any plugin is activated - catches our plugin too
     * This hook fires EVERY TIME the plugin is activated, including repeated activations
     */
    public function on_plugin_activated($plugin, $network_wide) {
        if ($plugin === TWIVCO_COMMISSION_MANAGER_PLUGIN_BASENAME) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WC Commission: Plugin activated via activated_plugin hook - ' . date('Y-m-d H:i:s'));
            }

            // Clear the just_activated flag since this hook is more reliable
            delete_option('twivco_plugin_just_activated');

            // Send activation status
            try {
                // Ensure required classes are loaded
                $this->load_required_classes();

                if (class_exists('TWIVCO_Commission_Manager_API_Connection')) {
                    $api = TWIVCO_Commission_Manager_API_Connection::get_instance();

                    // Register shop first if not already done
                    $api->register_shop();

                    // Send activation status
                    $this->send_plugin_status_webhook('activated');

                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('WC Commission: ✅ Activation status webhook sent successfully via activated_plugin hook');
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('WC Commission: ❌ API Connection class not available during activation hook');
                    }
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('WC Commission: ❌ Error sending activation status: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Hook for when any plugin is deactivated - catches our plugin too
     * This hook fires EVERY TIME the plugin is deactivated, including repeated deactivations
     */
    public function on_plugin_deactivated($plugin, $network_wide) {
        if ($plugin === TWIVCO_COMMISSION_MANAGER_PLUGIN_BASENAME) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WC Commission: Plugin deactivated via deactivated_plugin hook - ' . date('Y-m-d H:i:s'));
            }

            // Send deactivation status
            try {
                $this->send_plugin_status_webhook('deactivated');

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('WC Commission: ✅ Deactivation status webhook sent successfully via deactivated_plugin hook');
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('WC Commission: ❌ Error sending deactivation status: ' . $e->getMessage());
                }
            }
        }
    }

}

new TWIVCO_Commission_Manager();