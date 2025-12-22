<?php

namespace AutoCartRecovery;

/**
 * CPT and cart storage for Auto Cart Recovery.
 *
 * @package AutoCartRecovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles custom post type and meta for abandoned carts.
 */
class Cpt {

	/**
	 * Register CPT.
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Abandoned Carts', 'auto-cart-recovery' ),
			'singular_name'      => __( 'Abandoned Cart', 'auto-cart-recovery' ),
			'menu_name'          => __( 'Abandoned Carts', 'auto-cart-recovery' ),
			'name_admin_bar'     => __( 'Abandoned Cart', 'auto-cart-recovery' ),
			'add_new'            => __( 'Add New', 'auto-cart-recovery' ),
			'add_new_item'       => __( 'Add New Abandoned Cart', 'auto-cart-recovery' ),
			'new_item'           => __( 'New Abandoned Cart', 'auto-cart-recovery' ),
			'edit_item'          => __( 'Edit Abandoned Cart', 'auto-cart-recovery' ),
			'view_item'          => __( 'View Abandoned Cart', 'auto-cart-recovery' ),
			'all_items'          => __( 'Abandoned Carts', 'auto-cart-recovery' ),
			'search_items'       => __( 'Search Abandoned Carts', 'auto-cart-recovery' ),
			'not_found'          => __( 'No abandoned carts found.', 'auto-cart-recovery' ),
			'not_found_in_trash' => __( 'No abandoned carts found in Trash.', 'auto-cart-recovery' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'supports'           => array( 'title' ),
		);

		register_post_type( \AutoCartRecovery::CPT_SLUG, $args );
	}

	/**
	 * Maybe capture current WooCommerce cart as potential abandoned cart.
	 */
	public static function maybe_capture_cart() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$settings = Helpers::get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$cart = \WC()->cart;

		if ( ! $cart || $cart->is_empty() ) {
			return;
		}

		$cart_total = (float) $cart->get_total( 'edit' );

		if ( $cart_total < (float) $settings['min_cart_total'] ) {
			return;
		}

		// Identify customer.
		$user_id    = get_current_user_id();
		$session_id = \WC()->session ? \WC()->session->get_customer_id() : '';
		$email      = '';

		if ( is_user_logged_in() ) {
			$user  = wp_get_current_user();
			$email = ! empty( $user->user_email ) ? $user->user_email : '';
		} elseif ( ! empty( \WC()->customer ) ) {
			$email = \WC()->customer->get_billing_email();
		}

		if ( empty( $email ) && empty( $settings['include_guests'] ) ) {
			return;
		}

		$cart_contents = $cart->get_cart();

		// Normalize items to store minimal data via post meta.
		$items = array();

		foreach ( $cart_contents as $key => $item ) {
			$items[] = array(
				'product_id'        => isset( $item['product_id'] ) ? (int) $item['product_id'] : 0,
				'variation_id'      => isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0,
				'quantity'          => isset( $item['quantity'] ) ? (int) $item['quantity'] : 1,
				'variation'         => isset( $item['variation'] ) ? $item['variation'] : array(),
				'cart_item_data'    => isset( $item['cart_item_data'] ) ? $item['cart_item_data'] : array(),
			);
		}

		if ( empty( $items ) ) {
			return;
		}

		$now = current_time( 'timestamp' );

		// Either update existing cart for this user/session or create new.
		$existing_id = self::get_existing_cart_for_customer( $user_id, $session_id, $email );

		$args = array(
			'post_type'   => \AutoCartRecovery::CPT_SLUG,
			'post_status' => 'publish',
			'post_title'  => sprintf(
				/* translators: %s: customer identifier */
				__( 'Cart for %s', 'auto-cart-recovery' ),
				$email ? $email : ( $user_id ? 'User #' . $user_id : $session_id )
			),
		);

		if ( $existing_id ) {
			$args['ID'] = $existing_id;
			wp_update_post( $args );
			$cart_id = $existing_id;
		} else {
			$cart_id = wp_insert_post( $args );
		}

		if ( is_wp_error( $cart_id ) || ! $cart_id ) {
			return;
		}

		update_post_meta( $cart_id, '_acr_user_id', $user_id );
		update_post_meta( $cart_id, '_acr_session_id', sanitize_text_field( $session_id ) );
		update_post_meta( $cart_id, '_acr_email', sanitize_email( $email ) );
		update_post_meta( $cart_id, '_acr_cart_items', wp_json_encode( $items ) );
		update_post_meta( $cart_id, '_acr_cart_total', $cart_total );
		update_post_meta( $cart_id, '_acr_last_updated', $now );

		if ( ! get_post_meta( $cart_id, '_acr_status', true ) ) {
			update_post_meta( $cart_id, '_acr_status', $settings['status_new'] );
		}

		// Ensure a coupon exists for this cart at capture time.
		if ( ! get_post_meta( $cart_id, '_acr_coupon_id', true ) ) {
			$coupon_id = Emails::maybe_create_coupon_for_cart( $cart_id, $settings );

			if ( $coupon_id ) {
				update_post_meta( $cart_id, '_acr_coupon_id', $coupon_id );
			}
		}

		/**
		 * Fired when a cart is captured or updated.
		 *
		 * @param int   $cart_id Cart post ID.
		 * @param array $items   Items array.
		 */
		do_action( 'acr_cart_captured', $cart_id, $items );
	}

	/**
	 * Find existing cart for a given customer (user/session/email).
	 *
	 * @param int    $user_id    User ID.
	 * @param string $session_id Session ID.
	 * @param string $email      Email.
	 *
	 * @return int Cart post ID or 0.
	 */
	protected static function get_existing_cart_for_customer( $user_id, $session_id, $email ) {
		$meta_query = array( 'relation' => 'OR' );

		if ( $user_id ) {
			$meta_query[] = array(
				'key'   => '_acr_user_id',
				'value' => $user_id,
			);
		}

		if ( $session_id ) {
			$meta_query[] = array(
				'key'   => '_acr_session_id',
				'value' => $session_id,
			);
		}

		if ( $email ) {
			$meta_query[] = array(
				'key'   => '_acr_email',
				'value' => $email,
			);
		}

		// If we have no identifying meta conditions (e.g. guest with no email/session),
		// do not run a meta_query with only a relation, just bail out and create a new cart.
		if ( count( $meta_query ) <= 1 ) {
			return 0;
		}

		$query = new \WP_Query(
			array(
				'post_type'      => \AutoCartRecovery::CPT_SLUG,
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'meta_query'     => $meta_query,
				'fields'         => 'ids',
			)
		);

		if ( $query->have_posts() ) {
			return (int) $query->posts[0];
		}

		return 0;
	}

	/**
	 * Mark cart as recovered when an order is placed.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function mark_cart_recovered_on_order( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
		 return;
		}

		$email = $order->get_billing_email();

		if ( ! $email ) {
			return;
		}

		$query = new \WP_Query(
			array(
				'post_type'      => \AutoCartRecovery::CPT_SLUG,
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_acr_email',
						'value' => $email,
					),
				),
				'orderby'        => 'meta_value_num',
				'meta_key'       => '_acr_last_updated',
				'order'          => 'DESC',
			)
		);

		if ( ! $query->have_posts() ) {
			return;
		}

		$cart_id = (int) $query->posts[0];

		update_post_meta( $cart_id, '_acr_status', ACR_Helpers::get_settings()['status_recovered'] );
		update_post_meta( $cart_id, '_acr_recovered_order_id', $order_id );

		/**
		 * Fired when an abandoned cart is marked as recovered.
		 *
		 * @param int $cart_id  Cart post ID.
		 * @param int $order_id Order ID.
		 */
		do_action( 'acr_cart_recovered', $cart_id, $order_id );
	}
}


