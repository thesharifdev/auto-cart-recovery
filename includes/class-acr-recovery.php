<?php
/**
 * Recovery link handling for Auto Cart Recovery.
 *
 * @package Auto_Cart_Recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles recovery links and restoring carts.
 */
class ACR_Recovery {

	/**
	 * Register rewrite rule for pretty recovery URLs.
	 */
	public static function register_rewrite() {
		add_rewrite_rule(
			'acr/recover/([^/]+)/?$',
			'index.php?' . Auto_Cart_Recovery::RECOVERY_QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars Vars.
	 *
	 * @return array
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = Auto_Cart_Recovery::RECOVERY_QUERY_VAR;
		return $vars;
	}

	/**
	 * Handle recovery request on template_redirect.
	 */
	public static function handle_recovery_request() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$token = get_query_var( Auto_Cart_Recovery::RECOVERY_QUERY_VAR );

		if ( ! $token ) {
			return;
		}

		$token = sanitize_text_field( wp_unslash( $token ) );

		$cart_id = self::get_cart_by_token( $token );

		if ( ! $cart_id ) {
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}

		$settings = ACR_Helpers::get_settings();

		// Optional expiry check.
		$created = (int) get_post_meta( $cart_id, '_acr_token_created', true );

		if ( $created && ! empty( $settings['coupon_expiry_days'] ) ) {
			$expires = $created + ( (int) $settings['coupon_expiry_days'] * DAY_IN_SECONDS );

			if ( current_time( 'timestamp' ) > $expires ) {
				wp_safe_redirect( wc_get_cart_url() );
				exit;
			}
		}

		// Restore cart.
		self::restore_cart( $cart_id );

		// Apply coupon if exists.
		$coupon_id = (int) get_post_meta( $cart_id, '_acr_coupon_id', true );

		if ( $coupon_id ) {
			$coupon_code = get_post_field( 'post_title', $coupon_id );

			if ( $coupon_code ) {
				WC()->cart->apply_coupon( $coupon_code );
			}
		}

		// Mark recovered status (final order association is done via thankyou hook).
		update_post_meta( $cart_id, '_acr_status', $settings['status_abandoned'] );
		update_post_meta( $cart_id, '_acr_recovered_via_link', 1 );

		/**
		 * Fired when a recovery link is used.
		 *
		 * @param int $cart_id Cart post ID.
		 */
		do_action( 'acr_recovery_link_used', $cart_id );

		// Redirect to cart.
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}

	/**
	 * Get cart post ID by token.
	 *
	 * @param string $token Token.
	 *
	 * @return int
	 */
	protected static function get_cart_by_token( $token ) {
		$query = new WP_Query(
			array(
				'post_type'      => Auto_Cart_Recovery::CPT_SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_acr_token',
						'value' => $token,
					),
				),
			)
		);

		if ( $query->have_posts() ) {
			return (int) $query->posts[0];
		}

		return 0;
	}

	/**
	 * Restore WooCommerce cart from saved meta.
	 *
	 * @param int $cart_id Cart post ID.
	 */
	protected static function restore_cart( $cart_id ) {
		$items_json = get_post_meta( $cart_id, '_acr_cart_items', true );

		if ( ! $items_json ) {
			return;
		}

		$items = json_decode( $items_json, true );

		if ( empty( $items ) || ! is_array( $items ) ) {
			return;
		}

		WC()->cart->empty_cart();

		foreach ( $items as $item ) {
			$product_id   = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$variation_id = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
			$quantity     = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
			$variation    = isset( $item['variation'] ) ? (array) $item['variation'] : array();
			$cart_data    = isset( $item['cart_item_data'] ) ? (array) $item['cart_item_data'] : array();

			if ( $product_id ) {
				WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_data );
			}
		}
	}
}


