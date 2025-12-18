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
        add_action('woocommerce_add_to_cart', array($this->tracker, 'track_cart_on_add'), 10, 6);
        add_action('woocommerce_cart_item_removed', array($this->tracker, 'track_cart'), 10);
        add_action('woocommerce_update_cart_action_cart_updated', array($this->tracker, 'track_cart'), 10);
        add_action('woocommerce_checkout_update_order_review', array($this->tracker, 'capture_checkout_email'));

        // Order completion -> mark recovered
        add_action('woocommerce_thankyou', array($this->recovery, 'mark_cart_recovered_on_order'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this->recovery, 'mark_cart_recovered_on_order'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this->recovery, 'mark_cart_recovered_on_order'), 10, 1);

        // Schedule check
        add_action('acr_check_abandoned_carts', array($this->recovery, 'check_and_send_recovery_emails'));

        // Recovery link
        add_action('template_redirect', array($this->recovery, 'handle_recovery_link'));
    }

    /**
     * Add admin menu for the plugin.
     * 
     * @return void
     */
    public function add_admin_menu()
    {
        // Deprecated: Admin menus are now registered in Admin_Interface
    }

    /**
     * Enqueue admin styles.
     * 
     * @return void
     */
    public function enqueue_admin_styles($hook_suffix)
    {
        // Deprecated: admin styles are handled by Admin_Interface
    }

    /**
     * Admin page rendering.
     * 
     * @return void
     */
    // Admin page rendering is handled by Admin_Interface

    /**
     * Get or create session ID
     * 
     * @return void
     */
    private function get_session_id() {
        if (isset($_COOKIE['acr_session_id'])) {
            return sanitize_text_field($_COOKIE['acr_session_id']);
        }
        
        $session_id = wp_generate_password(32, false);
        setcookie('acr_session_id', $session_id, time() + (86400 * 30), '/');
        return $session_id;
    }

    /**
     * Cart tracking
     * 
     * @return void
     */
    public function cart_tracking() {
        // Track cart for logged-in users
        if (is_user_logged_in() && !is_admin()) {
            add_action('wp_footer', array($this, 'track_cart_simple'));
        }
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
    
    /**
     * Track cart contents
     * 
     * @return void
     */
    public function track_cart($cart_item_key = null, $product_id = null, $quantity = null, $variation_id = null, $variation = null, $cart_item_data = null) {
        $this->track_cart_simple();
    }
    
    /**
     * Cart simple tracking.
     * 
     * @return void
     */
    public function track_cart_simple() {
        if (is_admin()) {
            return;
        }
        
        global $wpdb;
        
        $cart = WC()->cart;
        
        if (!$cart || $cart->is_empty()) {
            return;
        }
        
        $session_id = $this->get_session_id();
        $user_id = get_current_user_id();
        $email = $this->get_user_email();
        
        $cart_data = array(
            'cart_contents' => $cart->get_cart(),
            'cart_totals' => array(
                'subtotal' => $cart->get_subtotal(),
                'total' => $cart->get_total('raw')
            )
        );
        
        $cart_total = $cart->get_total('raw');
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE session_id = %s AND status = 'active'",
            $session_id
        ));
        
        $data = array(
            'cart_data' => serialize($cart_data),
            'cart_total' => $cart_total,
            'updated_at' => current_time('mysql')
        );
        
        if ($email) {
            $data['email'] = $email;
        }
        
        if ($user_id) {
            $data['user_id'] = $user_id;
        }
        
        if ($existing) {
            $wpdb->update(
                $this->table_name,
                $data,
                array('id' => $existing->id),
                array('%s', '%f', '%s'),
                array('%d')
            );
        } else {
            $data['session_id'] = $session_id;
            $data['created_at'] = current_time('mysql');
            $data['status'] = 'active';
            $data['recovery_token'] = wp_generate_password(32, false);
            
            $wpdb->insert(
                $this->table_name,
                $data,
                array('%s', '%s', '%f', '%s', '%s', '%s')
            );
        }
    }
    
    /**
     * Email form checkout capturing
     * 
     * @return void
     */
    public function capture_email_from_checkout($post_data) {
        parse_str($post_data, $data);
        
        if (isset($data['billing_email']) && is_email($data['billing_email'])) {
            global $wpdb;
            
            $session_id = $this->get_session_id();
            $email = sanitize_email($data['billing_email']);
            
            $wpdb->update(
                $this->table_name,
                array('email' => $email, 'updated_at' => current_time('mysql')),
                array('session_id' => $session_id, 'status' => 'active'),
                array('%s', '%s'),
                array('%s', '%s')
            );
        }
    }

    /**
     * Get user email.
     * 
     * @return string
     */
    private function get_user_email() {
        $user = wp_get_current_user();
        if ($user->ID) {
            return $user->user_email;
        }
        return null;
    }
    
    /**
     * Abandoned cart clearing
     * 
     * @return void
     */
    public function clear_abandoned_cart($order_id) {
        global $wpdb;
        
        $session_id = $this->get_session_id();
        
        $wpdb->update(
            $this->table_name,
            array(
                'status' => 'recovered',
                'recovered' => 1,
                'updated_at' => current_time('mysql')
            ),
            array('session_id' => $session_id),
            array('%s', '%d', '%s'),
            array('%s')
        );
    }
    
    /**
     * Checkout send and recovery email
     * 
     * @return void
     */
    public function check_and_send_recovery_emails() {
        global $wpdb;
        
        // Find carts abandoned for more than the threshold
        $abandoned_time = apply_filters('acr_abandoned_time_threshold', '-2 minutes');
        $time_threshold = date('Y-m-d H:i:s', strtotime($abandoned_time));
        
        $abandoned_carts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE status = 'active' 
            AND recovery_sent = 0 
            AND email IS NOT NULL 
            AND email != '' 
            AND updated_at < %s
            LIMIT 10",
            $time_threshold
        ));
        
        foreach ($abandoned_carts as $cart) {
            $this->send_recovery_email($cart);
        }
    }
        
    /**
     * Send recovery email
     * 
     * @return void
     */
    private function send_recovery_email($cart) {
        global $wpdb;
        
        if (!$cart->email) {
            return;
        }
        
        // Create coupon for recovery email
        $coupon_code = $this->create_recovery_coupon();
        
        $recovery_url = add_query_arg(array(
            'acr_recover' => $cart->recovery_token,
            'session' => $cart->session_id
        ), wc_get_cart_url());
        
        $cart_data = unserialize($cart->cart_data);
        $items_html = '';
        
        if (isset($cart_data['cart_contents'])) {
            foreach ($cart_data['cart_contents'] as $item) {
                $product = wc_get_product($item['product_id']);
                if ($product) {
                    $items_html .= sprintf(
                        '<li>%s - Quantity: %d - %s</li>',
                        $product->get_name(),
                        $item['quantity'],
                        wc_price($item['line_total'])
                    );
                }
            }
        }
        
        $subject = 'You left items in your cart!';
        $message = sprintf('
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                    <h2 style="color: #0073aa;">You left something behind!</h2>
                    <p>Hi there,</p>
                    <p>We noticed you left some items in your cart. Don\'t worry, we saved them for you!</p>
                    
                    <h3>Your Cart Items:</h3>
                    <ul style="list-style: none; padding: 0;">
                        %s
                    </ul>
                    
                    <p><strong>Total: %s</strong></p>
                    
                    <div style="margin: 30px 0; padding: 20px; background: #f0f6fc; border-left: 4px solid #00a32a; border-radius: 5px;">
                        <h3 style="color: #00a32a; margin-top: 0;">Special Offer: 20%% Off!</h3>
                        <p>Use coupon code: <strong style="font-size: 18px; color: #00a32a;">%s</strong></p>
                        <p style="font-size: 12px; color: #666;">Valid for 24 hours only</p>
                    </div>
                    
                    <div style="margin: 30px 0;">
                        <a href="%s" style="background-color: #0073aa; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">
                            Complete Your Purchase
                        </a>
                    </div>
                    
                    <p style="color: #666; font-size: 12px;">This link will expire in 7 days.</p>
                </div>
            </body>
            </html>
        ', $items_html, wc_price($cart->cart_total), $coupon_code, esc_url($recovery_url));
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $sent = wp_mail($cart->email, $subject, $message, $headers);
        
        if ($sent) {
            $wpdb->update(
                $this->table_name,
                array(
                    'recovery_sent' => 1,
                    'recovery_sent_at' => current_time('mysql')
                ),
                array('id' => $cart->id),
                array('%d', '%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Coupon creating method
     * 
     * @return string
     */
    private function create_recovery_coupon() {
        // Generate unique coupon code
        $coupon_code = 'RECOVER-' . strtoupper(wp_generate_password(8, false));
        
        // Check if coupon already exists
        $existing_coupon = get_page_by_title($coupon_code, OBJECT, 'shop_coupon');
        if ($existing_coupon) {
            return $coupon_code;
        }
        
        // Create new coupon
        $coupon_post = array(
            'post_title' => $coupon_code,
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'shop_coupon',
        );
        
        $coupon_id = wp_insert_post($coupon_post);
        
        if ($coupon_id) {
            // Set coupon meta data
            update_post_meta($coupon_id, 'discount_type', 'percent');
            update_post_meta($coupon_id, 'coupon_amount', '20');
            update_post_meta($coupon_id, 'individual_use', 'yes');
            update_post_meta($coupon_id, 'exclude_sale_items', 'no');
            update_post_meta($coupon_id, 'minimum_amount', '');
            update_post_meta($coupon_id, 'maximum_amount', '');
            update_post_meta($coupon_id, 'usage_limit', '');
            update_post_meta($coupon_id, 'usage_limit_per_user', '1');
            
            // Set expiry date to 1 day from now
            $expiry_date = date('Y-m-d', strtotime('+1 day'));
            update_post_meta($coupon_id, 'expiry_date', $expiry_date);
        }
        
        return $coupon_code;
    }
    
    /**
     * Handle recovery link
     * 
     * @return void
     */
    public function handle_recovery_link() {
        if (isset($_GET['acr_recover']) && isset($_GET['session'])) {
            global $wpdb;
            
            $token = sanitize_text_field($_GET['acr_recover']);
            $session = sanitize_text_field($_GET['session']);
            
            $cart_record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE recovery_token = %s AND session_id = %s",
                $token,
                $session
            ));
            
            if ($cart_record && $cart_record->status === 'active') {
                // Restore cart
                $cart_data = unserialize($cart_record->cart_data);
                
                if (isset($cart_data['cart_contents'])) {
                    WC()->cart->empty_cart();
                    
                    foreach ($cart_data['cart_contents'] as $item) {
                        WC()->cart->add_to_cart(
                            $item['product_id'],
                            $item['quantity'],
                            $item['variation_id'],
                            $item['variation'],
                            $item
                        );
                    }
                    
                    wc_add_notice('Your cart has been restored!', 'success');
                    wp_redirect(wc_get_cart_url());
                    exit;
                }
            }
        }
    }
}
