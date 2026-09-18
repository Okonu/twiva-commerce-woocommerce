<?php

/**
 * Environment Variables Loader for WooCommerce Commission Manager
 * Loads environment variables from .env file if it exists
 */
class TWIVCO_Commission_Manager_Env_Loader {
    
    private static $loaded = false;
    
    /**
     * Load environment variables from .env file
     */
    public static function load() {
        if (self::$loaded) {
            return;
        }
        
        $env_file = TWIVCO_COMMISSION_MANAGER_PLUGIN_PATH . '.env';
        
        if (!file_exists($env_file)) {
            self::$loaded = true;
            return;
        }
        
        try {
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            if ($lines === false) {
                self::$loaded = true;
                return;
            }
            
            foreach ($lines as $line) {
                $line = trim($line);

                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }

                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);

                    if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                        (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                        $value = substr($value, 1, -1);
                    }

                    if (!defined($key)) {
                        define($key, $value);
                        
                        if (defined('WC_COMMISSION_DEBUG') && WC_COMMISSION_DEBUG) {
                        }
                    }
                }
            }
            
            self::$loaded = true;
            
            if (defined('WC_COMMISSION_DEBUG') && WC_COMMISSION_DEBUG) {
            }
            
        } catch (Exception $e) {
            self::$loaded = true;
        }
    }
    
    /**
     * Get environment variable with fallback
     * 
     * @param string $key The environment variable key
     * @param mixed $default Default value if not found
     * @return mixed The environment variable value or default
     */
    public static function get($key, $default = null) {
        if (defined($key)) {
            return constant($key);
        }
        
        return $default;
    }
    
    /**
     * Check if environment is in debug mode
     * 
     * @return bool
     */
    public static function is_debug() {
        return self::get('WC_COMMISSION_DEBUG', false) === 'true' || 
               self::get('WC_COMMISSION_DEBUG', false) === true;
    }
    
    /**
     * Get database configuration from environment
     * 
     * @return array Database configuration array
     */
    public static function get_db_config() {
        return array(
            'host' => self::get('WC_COMMISSION_DB_HOST', ''),
            'name' => self::get('WC_COMMISSION_DB_NAME', ''),
            'user' => self::get('WC_COMMISSION_DB_USER', ''),
            'pass' => self::get('WC_COMMISSION_DB_PASS', ''),
            'port' => self::get('WC_COMMISSION_DB_PORT', 3306)
        );
    }
}