<?php

namespace Auto_Cart_Recovery;

use Auto_Cart_Recovery\Utils\Singleton;

defined('ABSPATH') || exit;

/**
 * Auto Cart Recovery primary operation.
 */
class Plugin_Core
{

    use Singleton;

    private $table_name;
    /** @var Database_Manager */
    private $db;
    /** @var Cart_Tracker */
    private $tracker;
    /** @var Email_Manager */
    private $emailer;
    /** @var Cart_Recovery */
    private $recovery;
    /** @var Admin_Interface */
    private $admin;

    /**
     * Initialize the plugin core functionalities.
     * 
     * @return void
     */
    public function init()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'acr_abandoned_carts';

        // Instantiate components
        $this->db = new Database_Manager();
        $this->tracker = new Cart_Tracker($this->db);
        $this->emailer = new Email_Manager($this->db);
        $this->recovery = new Cart_Recovery($this->db, $this->emailer);
        $this->admin = new Admin_Interface($this->db);

        // Activation / deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Database table check on admin init
        add_action('admin_init', array($this, 'check_database_table'));

        // Admin hooks via Admin_Interface
        $this->admin->register_hooks();

        // Cart tracking hooks
        add_action('init', array($this->tracker, 'init_tracking'));
        // add_action('woocommerce_add_to_cart', array($this->tracker, 'track_cart'), 10, 6);
        // add_action('woocommerce_cart_item_removed', array($this->tracker, 'track_cart'), 10);
        // add_action('woocommerce_update_cart_action_cart_updated', array($this->tracker, 'track_cart'), 10);
        add_action('woocommerce_checkout_update_order_review', array($this->tracker, 'capture_checkout_email'));

        // Order completion -> mark recovered
        add_action('woocommerce_thankyou', array($this->recovery, 'mark_cart_recovered_on_order'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this->recovery, 'mark_cart_recovered_on_order'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this->recovery, 'mark_cart_recovered_on_order'), 10, 1);

        // Schedule check
        add_action('acr_check_abandoned_carts', array($this->recovery, 'check_and_send_recovery_emails'));

        // Recovery link handling (delegated to Cart_Recovery)
        add_action('template_redirect', array($this->recovery, 'handle_recovery_link'));
    }

    /**
     * Get or create session ID
     *
     * @return string
     */
    private function get_session_id()
    {
        if (isset($_COOKIE['acr_session_id'])) {
            return sanitize_text_field($_COOKIE['acr_session_id']);
        }

        $session_id = wp_generate_password(32, false);
        setcookie('acr_session_id', $session_id, time() + (86400 * 30), '/');
        // Make cookie available during this request (helps with AJAX and immediate hooks)
        $_COOKIE['acr_session_id'] = $session_id;
        return $session_id;
    }

    /**
     * Database table creation for Auto Cart Recovery plugin.
     *
     * @return void
     */
    private function create_database_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            cart_data longtext NOT NULL,
            cart_total decimal(10,2) DEFAULT 0.00,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            status varchar(50) DEFAULT 'active',
            recovery_sent tinyint(1) DEFAULT 0,
            recovery_sent_at datetime DEFAULT NULL,
            recovered tinyint(1) DEFAULT 0,
            recovery_token varchar(255) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY email (email),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create database table and schedule
     *
     * @return void
     */
    public function activate()
    {
        // Create database table
        $this->create_database_table();

        // Schedule cron job
        if (!wp_next_scheduled('acr_check_abandoned_carts')) {
            wp_schedule_event(time(), 'hourly', 'acr_check_abandoned_carts');
        }
    }

    /**
     * Clear scheduled hook on deactivation.
     *
     * @return void
     */
    public function deactivate()
    {
        wp_clear_scheduled_hook('acr_check_abandoned_carts');
    }

    /**
     * Database table check and creation if missing.
     *
     * @return void
     */
    public function check_database_table()
    {
        global $wpdb;

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");

        if ($table_exists !== $this->table_name) {
            // Table doesn't exist, create it
            $this->create_database_table();

            // Show admin notice
            add_action('admin_notices', function () {
                echo wp_kses_post('<div class="notice notice-success is-dismissible">');
                echo wp_kses_post('<p><strong>Auto Cart Recovery:</strong> Database table created successfully!</p>');
                echo wp_kses_post('</div>');
            });
        }
    }
}
