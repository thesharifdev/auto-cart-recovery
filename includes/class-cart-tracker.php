<?php

namespace Auto_Cart_Recovery;

defined('ABSPATH') || exit;

/**
 * Cart Tracker - Handles cart tracking and session management.
 */
class Cart_Tracker
{
    private $database_manager;
    private $tracking_in_progress = false; // Prevent multiple simultaneous tracking

    /**
     * Constructor
     * 
     * @param Database_Manager $database_manager Database manager instance
     */
    public function __construct(Database_Manager $database_manager)
    {
        $this->database_manager = $database_manager;
    }

    /**
     * Get or create session ID.
     * 
     * @return string
     */
    public function get_session_id()
    {
        if (isset($_COOKIE['acr_session_id'])) {
            return sanitize_text_field($_COOKIE['acr_session_id']);
        }

        $session_id = wp_generate_password(32, false);
        setcookie('acr_session_id', $session_id, time() + (86400 * 30), '/');
        // Ensure the cookie is available during this request (useful for AJAX flows)
        $_COOKIE['acr_session_id'] = $session_id;
        return $session_id;
    }

    /**
     * Initialize cart tracking.
     * 
     * @return void
     */
    public function init_tracking()
    {
        // Track cart for logged-in users on wp_footer
        if (is_user_logged_in() && !is_admin()) {
            add_action('wp_footer', array($this, 'track_cart'));
        }
    }

    /**
     * Track cart contents.
     * 
     * @return void
     */
    public function track_cart()
    {
        // Prevent duplicate tracking in the same request
        if ($this->tracking_in_progress) {
            return;
        }
        
        $this->tracking_in_progress = true;

        if (is_admin()) {
            $this->tracking_in_progress = false;
            return;
        }

        $cart = WC()->cart;

        if (!$cart || $cart->is_empty()) {
            $this->tracking_in_progress = false;
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

        // Check for existing cart by session_id ONLY (don't filter by status)
        $existing = $this->database_manager->get_cart_by_session_any_status($session_id);

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
            // Update existing cart
            // Only reset status if it was 'recovered' - keep 'active' as is
            if ($existing->status === 'recovered') {
                $data['status'] = 'active';
                $data['recovered'] = 0;
                // Generate new recovery token for the new abandonment
                $data['recovery_token'] = wp_generate_password(32, false);
                $data['recovery_sent'] = 0;
                $data['recovery_sent_at'] = null;
            }

            $this->database_manager->save_cart($data, array('id' => $existing->id), true);
        } else {
            // Create new cart record
            $data['session_id'] = $session_id;
            $data['created_at'] = current_time('mysql');
            $data['status'] = 'active';
            $data['recovery_token'] = wp_generate_password(32, false);

            $this->database_manager->save_cart($data);
        }

        $this->tracking_in_progress = false;
    }

    /**
     * Capture email from checkout.
     * 
     * @param string $post_data POST data
     * @return void
     */
    public function capture_checkout_email($post_data)
    {
        parse_str($post_data, $data);

        if (isset($data['billing_email']) && is_email($data['billing_email'])) {
            $session_id = $this->get_session_id();
            $email = sanitize_email($data['billing_email']);

            // Update existing cart with email
            $existing = $this->database_manager->get_cart_by_session_any_status($session_id);
            
            if ($existing) {
                $this->database_manager->save_cart(
                    array('email' => $email, 'updated_at' => current_time('mysql')),
                    array('id' => $existing->id),
                    true
                );
            }
        }
    }

    /**
     * Get current user email.
     * 
     * @return string|null
     */
    private function get_user_email()
    {
        $user = wp_get_current_user();
        return $user->ID ? $user->user_email : null;
    }
}