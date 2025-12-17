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

        echo '<div class="' . esc_attr($notice_class) . '"><p>' . wp_kses_post($message) . $button_html . '</p></div>';
    });
    return;
}