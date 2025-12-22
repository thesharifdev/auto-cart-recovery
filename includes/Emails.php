<?php

namespace AutoCartRecovery;

/**
 * Email and coupon handling for Auto Cart Recovery.
 *
 * @package AutoCartRecovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles creation of WooCommerce coupons and sending recovery emails.
 */
class Emails {

	/**
	 * Maybe create WooCommerce coupon for a cart.
	 *
	 * @param int   $cart_id  Cart post ID.
	 * @param array $settings Settings.
	 *
	 * @return int Coupon post ID or 0.
	 */
	public static function maybe_create_coupon_for_cart( $cart_id, $settings ) {
		$existing_id = (int) get_post_meta( $cart_id, '_acr_coupon_id', true );

		if ( $existing_id ) {
			return $existing_id;
		}

		$discount_type   = ! empty( $settings['discount_type'] ) ? $settings['discount_type'] : 'percent';
		$discount_amount = isset( $settings['discount_amount'] ) ? (float) $settings['discount_amount'] : 0;

		// Ensure there is always a positive discount amount; fall back to default (20) if misconfigured.
		if ( $discount_amount <= 0 ) {
			$discount_amount = 20;
		}

		// Generate a coupon code, with a safe fallback if WooCommerce helper is not available.
		if ( function_exists( 'wc_generate_coupon_code' ) ) {
			$raw_code = wc_generate_coupon_code();
		} else {
			$raw_code = strtolower( wp_generate_password( 10, false, false ) );
		}

		$coupon_code = apply_filters( 'acr_coupon_code', $raw_code, $cart_id );

		$coupon_id = wp_insert_post(
			array(
				'post_title'  => $coupon_code,
				'post_status' => 'publish',
				'post_type'   => 'shop_coupon',
			)
		);

		if ( is_wp_error( $coupon_id ) || ! $coupon_id ) {
			return 0;
		}

		update_post_meta( $coupon_id, 'discount_type', $discount_type );
		update_post_meta( $coupon_id, 'coupon_amount', $discount_amount );
		update_post_meta( $coupon_id, 'individual_use', 'yes' );
		update_post_meta( $coupon_id, 'usage_limit', 1 );
		update_post_meta( $coupon_id, 'usage_limit_per_user', 1 );
		update_post_meta( $coupon_id, 'free_shipping', 'no' );
		update_post_meta( $coupon_id, '_acr_coupon', 'yes' );

		if ( ! empty( $settings['coupon_expiry_days'] ) ) {
			$expiry = current_time( 'timestamp' ) + ( (int) $settings['coupon_expiry_days'] * DAY_IN_SECONDS );
			update_post_meta( $coupon_id, 'date_expires', $expiry );
		}

		/**
		 * Fired when an Auto Cart Recovery coupon is created.
		 *
		 * @param int   $coupon_id Coupon ID.
		 * @param int   $cart_id   Cart post ID.
		 * @param array $settings  Settings.
		 */
		do_action( 'acr_coupon_created', $coupon_id, $cart_id, $settings );

		return $coupon_id;
	}

	/**
	 * Send recovery email.
	 *
	 * @param int    $cart_id    Cart ID.
	 * @param string $email      Recipient email.
	 * @param string $url        Recovery URL.
	 * @param int    $coupon_id  Coupon ID (optional).
	 * @param array  $settings   Settings.
	 *
	 * @return bool
	 */
	public static function send_recovery_email( $cart_id, $email, $url, $coupon_id, $settings ) {
		$subject = isset( $settings['email_subject'] ) ? $settings['email_subject'] : __( 'You left something in your cart', 'auto-cart-recovery' );
		$heading = isset( $settings['email_heading'] ) ? $settings['email_heading'] : __( 'Complete your purchase', 'auto-cart-recovery' );
		$body    = isset( $settings['email_body'] ) ? $settings['email_body'] : __( 'We saved your cart for you. Click the button below to restore your cart and apply your discount.', 'auto-cart-recovery' );

		$coupon_message = '';
		$cart_summary   = '';

		if ( $coupon_id ) {
			$coupon_code    = get_post_field( 'post_title', $coupon_id );
			$discount_type  = get_post_meta( $coupon_id, 'discount_type', true );
			$discount_value = get_post_meta( $coupon_id, 'coupon_amount', true );

			if ( $coupon_code ) {
				if ( 'percent' === $discount_type ) {
					/* translators: %1$s: coupon code, %2$s: discount amount */
					$coupon_message = sprintf( __( 'Use coupon code <strong>%1$s</strong> to get <strong>%2$s%%</strong> off.', 'auto-cart-recovery' ), esc_html( $coupon_code ), esc_html( $discount_value ) );
				} else {
					/* translators: %1$s: coupon code, %2$s: discount amount */
					$coupon_message = sprintf( __( 'Use coupon code <strong>%1$s</strong> to get <strong>%2$s</strong> off.', 'auto-cart-recovery' ), esc_html( $coupon_code ), esc_html( wc_price( $discount_value ) ) );
				}
			}
		}

		// Build simple cart summary from stored meta.
		$items_json = get_post_meta( $cart_id, '_acr_cart_items', true );
		$total      = get_post_meta( $cart_id, '_acr_cart_total', true );

		if ( $items_json ) {
			$items = json_decode( $items_json, true );

			if ( is_array( $items ) && ! empty( $items ) ) {
				$cart_summary .= '<ul>';

				foreach ( $items as $item ) {
					$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
					$qty        = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
					$name       = '';

					if ( $product_id && function_exists( 'wc_get_product' ) ) {
						$product = wc_get_product( $product_id );

						if ( $product ) {
							$name = $product->get_name();
						}
					}

					if ( ! $name ) {
						$name = __( 'Product', 'auto-cart-recovery' );
					}

					$cart_summary .= '<li>' . esc_html( $name ) . ' &times; ' . esc_html( $qty ) . '</li>';
				}

				$cart_summary .= '</ul>';
			}
		}

		if ( $total ) {
			$total_formatted = function_exists( 'wc_price' ) ? wc_price( (float) $total ) : number_format_i18n( (float) $total, 2 );
			$cart_summary   .= '<p><strong>' . esc_html__( 'Cart total:', 'auto-cart-recovery' ) . '</strong> ' . wp_kses_post( $total_formatted ) . '</p>';
		}

		$button_label = __( 'Go to checkout', 'auto-cart-recovery' );

		// Basic HTML email content.
		$message  = '<html><body>';
		$message .= '<h2>' . esc_html( $heading ) . '</h2>';
		$message .= '<p>' . wp_kses_post( $body ) . '</p>';

		if ( $coupon_message ) {
			$message .= '<p>' . wp_kses_post( $coupon_message ) . '</p>';
		}

		if ( $cart_summary ) {
			$message .= $cart_summary;
		}

		$message .= '<p><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:10px 20px;background:#2271b1;color:#ffffff;text-decoration:none;border-radius:4px;">' . esc_html( $button_label ) . '</a></p>';

		$message .= '<p>' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
		$message .= '</body></html>';

		$headers = array();

		$from_name  = isset( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' );
		$from_email = isset( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );

		if ( $from_email ) {
			$headers[] = 'From: ' . sprintf( '%s <%s>', wp_specialchars_decode( $from_name ), $from_email );
		}

		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		/**
		 * Filter recovery email arguments before sending.
		 *
		 * @param array $args {
		 *      @type string $subject Email subject.
		 *      @type string $message HTML message.
		 *      @type array  $headers Headers.
		 * }
		 * @param int   $cart_id  Cart ID.
		 * @param int   $coupon_id Coupon ID.
		 */
		$filtered = apply_filters(
			'acr_recovery_email_args',
			array(
				'subject' => $subject,
				'message' => $message,
				'headers' => $headers,
			),
			$cart_id,
			$coupon_id
		);

		return wp_mail( $email, $filtered['subject'], $filtered['message'], $filtered['headers'] );
	}
}


