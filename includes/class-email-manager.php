<?php

namespace Auto_Cart_Recovery;

defined('ABSPATH') || exit;

/**
 * Email Manager - Handles recovery email creation and sending.
 */
class Email_Manager
{
    private $database_manager;

    public function __construct(Database_Manager $database_manager)
    {
        $this->database_manager = $database_manager;
    }

    /**
     * Send recovery email for a cart record.
     * 
     * @param object $cart Cart record
     * @return void
     */
    public function send_recovery_email($cart)
    {
        if (!$cart->email) {
            return;
        }

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
        $message = sprintf("
            <html>
            <body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333;\">
                <div style=\"max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;\">
                    <h2 style=\"color: #0073aa;\">You left something behind!</h2>
                    <p>Hi there,</p>
                    <p>We noticed you left some items in your cart. Don\\'t worry, we saved them for you!</p>
                    
                    <h3>Your Cart Items:</h3>
                    <ul style=\"list-style: none; padding: 0;\">
                        %s
                    </ul>
                    
                    <p><strong>Total: %s</strong></p>
                    
                    <div style=\"margin: 30px 0; padding: 20px; background: #f0f6fc; border-left: 4px solid #00a32a; border-radius: 5px;\">
                        <h3 style=\"color: #00a32a; margin-top: 0;\">Special Offer: 20%% Off!</h3>
                        <p>Use coupon code: <strong style=\"font-size: 18px; color: #00a32a;\">%s</strong></p>
                        <p style=\"font-size: 12px; color: #666;\">Valid for 24 hours only</p>
                    </div>
                    
                    <div style=\"margin: 30px 0;\">
                        <a href=\"%s\" style=\"background-color: #0073aa; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;\">
                            Complete Your Purchase
                        </a>
                    </div>
                    
                    <p style=\"color: #666; font-size: 12px;\">This link will expire in 7 days.</p>
                </div>
            </body>
            </html>
        ", $items_html, wc_price($cart->cart_total), $coupon_code, esc_url($recovery_url));

        $headers = array('Content-Type: text/html; charset=UTF-8');

        $sent = wp_mail($cart->email, $subject, $message, $headers);

        if ($sent) {
            $this->database_manager->mark_recovery_email_sent($cart->id);
        }
    }

    /**
     * Create a recovery coupon code and post.
     * 
     * @return string Coupon code
     */
    private function create_recovery_coupon()
    {
        $coupon_code = 'RECOVER-' . strtoupper(wp_generate_password(8, false));

        $existing_coupon = get_page_by_title($coupon_code, OBJECT, 'shop_coupon');
        if ($existing_coupon) {
            return $coupon_code;
        }

        $coupon_post = array(
            'post_title' => $coupon_code,
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'shop_coupon',
        );

        $coupon_id = wp_insert_post($coupon_post);

        if ($coupon_id) {
            update_post_meta($coupon_id, 'discount_type', 'percent');
            update_post_meta($coupon_id, 'coupon_amount', '20');
            update_post_meta($coupon_id, 'individual_use', 'yes');
            update_post_meta($coupon_id, 'exclude_sale_items', 'no');
            update_post_meta($coupon_id, 'minimum_amount', '');
            update_post_meta($coupon_id, 'maximum_amount', '');
            update_post_meta($coupon_id, 'usage_limit', '');
            update_post_meta($coupon_id, 'usage_limit_per_user', '1');

            $expiry_date = date('Y-m-d', strtotime('+1 day'));
            update_post_meta($coupon_id, 'expiry_date', $expiry_date);
        }

        return $coupon_code;
    }
}
