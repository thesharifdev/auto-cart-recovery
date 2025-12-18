<?php

namespace Auto_Cart_Recovery;

defined('ABSPATH') || exit;

/**
 * Cart Recovery - Handles scheduled checks and recovery link processing.
 */
class Cart_Recovery
{
    private $database_manager;
    private $email_manager;

    public function __construct(Database_Manager $database_manager, Email_Manager $email_manager)
    {
        $this->database_manager = $database_manager;
        $this->email_manager = $email_manager;
    }

    /**
     * Run scheduled check and send recovery emails.
     * 
     * @return void
     */
    public function check_and_send_recovery_emails()
    {
        $abandoned_time = apply_filters('acr_abandoned_time_threshold', '-2 minutes');
        $time_threshold = date('Y-m-d H:i:s', strtotime($abandoned_time));

        $carts = $this->database_manager->get_pending_recovery_carts($time_threshold);

        foreach ($carts as $cart) {
            $this->email_manager->send_recovery_email($cart);
        }
    }

    /**
     * Handle recovery link and restore cart.
     * 
     * @return void
     */
    public function handle_recovery_link()
    {
        if (isset($_GET['acr_recover']) && isset($_GET['session'])) {
            $token = sanitize_text_field($_GET['acr_recover']);
            $session = sanitize_text_field($_GET['session']);

            $cart_record = $this->database_manager->get_cart_by_token($token, $session);

            if ($cart_record && $cart_record->status === 'active') {
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

    /**
     * Mark cart recovered by session when an order completes.
     * 
     * @param int $order_id Order ID
     * @return void
     */
    public function mark_cart_recovered_on_order($order_id)
    {
        $session_id = isset($_COOKIE['acr_session_id']) ? sanitize_text_field($_COOKIE['acr_session_id']) : null;

        if ($session_id) {
            $this->database_manager->mark_cart_recovered_by_session($session_id);
        }
    }
}
