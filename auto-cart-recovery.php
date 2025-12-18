<?php
/**
 * Plugin Name: Auto Cart Recovery
 * Plugin URI: #
 * Description: Automatically track and recover abandoned carts with email notifications
 * Version: 1.0.0
 * Author: Sharif
 * Author URI: https://thesharif.dev
 * Text Domain: auto-cart-recovery
 * Requires at least: 5.8
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load the singleton trait
require_once plugin_dir_path(__FILE__) . 'utils/singleton.php';

use Auto_Cart_Recovery\Utils\Singleton;

/**
 * Final class for auto cart recovery plugin
 * 
 * This class will play the main role for manage abandoned cart operation
 */
final class Auto_Cart_Recovery {
    use Singleton;

    /**
     * Constructor method for invoking necessary methods
     */
    protected function __construct() {
        if (!$this->check_woocommerce()) {
            return;
        }
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Check if WooCommerce is installed and active
     */
    private function check_woocommerce() {
        $woocommerce_plugin = 'woocommerce/woocommerce.php';
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
        $woocommerce_installed = file_exists(WP_PLUGIN_DIR . '/' . $woocommerce_plugin);
        $woocommerce_active = in_array($woocommerce_plugin, $active_plugins);

        if (!$woocommerce_active) {
            add_action('admin_notices', function() use ($woocommerce_installed) {
                
                $notice_class = 'error';
                $message = '<strong>Auto Cart Recovery</strong> requires WooCommerce to be installed and active.';
                $button_html = '';

                if ($woocommerce_installed) {
                    // WooCommerce is installed but not active - show activate button
                    $activate_url = wp_nonce_url(
                        add_query_arg('action', 'activate', admin_url('plugins.php?plugin=woocommerce/woocommerce.php')),
                        'activate-plugin_woocommerce/woocommerce.php'
                    );
                    $button_html = '<a href="' . esc_url($activate_url) . '" class="button button-primary" style="margin-left: 10px;">Activate WooCommerce</a>';
                } else {
                    // WooCommerce is not installed - show install button
                    $install_url = wp_nonce_url(
                        self_admin_url('update.php?action=install-plugin&plugin=woocommerce'),
                        'install-plugin_woocommerce'
                    );
                    $button_html = '<a href="' . esc_url($install_url) . '" class="button button-primary" style="margin-left: 10px;">Install WooCommerce</a>';
                }

                $allowed_html = array(
                    'div' => array('class' => true),
                    'p' => array(),
                    'a' => array('href' => true, 'class' => true),
                    'strong' => array(),
                );
                $output = '<div class="' . esc_attr($notice_class) . '"><p>' . $message . $button_html . '</p></div>';
                echo wp_kses($output, $allowed_html);
            });
            return false;
        }
        return true;
    }

    /**
     * Define necessary constants
     */
    private function define_constants() {
        define('ACR_VERSION', '1.0.0');
        define('ACR_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('ACR_PLUGIN_URL', plugin_dir_url(__FILE__));
    }

    /**
     * Include necessary files
     */
    private function includes() {
        require_once ACR_PLUGIN_DIR . 'includes/class-plugin-core.php';
    }

    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init_components'));
    }

    public function init_components() {
        Auto_Cart_Recovery\Plugin_Core::instance()->init();
    }
}

// Initialize the plugin
Auto_Cart_Recovery::instance();