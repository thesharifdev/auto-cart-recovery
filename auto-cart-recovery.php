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

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p><strong>Auto Cart Recovery</strong> requires WooCommerce to be installed and active.</p></div>';
    });
    return;
}