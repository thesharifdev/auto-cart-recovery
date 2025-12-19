<?php
/**
 * Cron handling for Auto Cart Recovery.
 *
 * @package Auto_Cart_Recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles scheduling and processing abandoned carts.
 */
class ACR_Cron {

	/**
	 * Register custom schedules.
	 *
	 * @param array $schedules Schedules.
	 *
	 * @return array
	 */
	public static function add_custom_schedules( $schedules ) {
		if ( ! isset( $schedules['fifteen_minutes'] ) ) {
			$schedules['fifteen_minutes'] = array(
				'interval' => 15 * 60,
				'display'  => __( 'Every 15 minutes', 'auto-cart-recovery' ),
			);
		}

		return $schedules;
	}

	/**
	 * Process abandoned carts and send recovery emails.
	 */
	public static function process_abandoned_carts() {
		$settings = ACR_Helpers::get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$now           = current_time( 'timestamp' );
		$delay_seconds = (int) $settings['delay_minutes'] * 60;

		$meta_query = array(
			array(
				'key'   => '_acr_status',
				'value' => $settings['status_new'],
			),
			array(
				'key'     => '_acr_last_updated',
				'value'   => $now - $delay_seconds,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			),
		);

		$query = new WP_Query(
			array(
				'post_type'      => Auto_Cart_Recovery::CPT_SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					$meta_query[0],
					$meta_query[1],
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return;
		}

		foreach ( $query->posts as $cart_id ) {
			self::process_single_cart( $cart_id, $settings );
		}
	}

	/**
	 * Manually trigger processing for a single cart (used from admin UI).
	 *
	 * @param int $cart_id Cart post ID.
	 */
	public static function send_recovery_for_cart( $cart_id ) {
		$cart_id = absint( $cart_id );

		if ( ! $cart_id ) {
			return;
		}

		$settings = ACR_Helpers::get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		self::process_single_cart( $cart_id, $settings, true );
	}

	/**
	 * Process a single abandoned cart.
	 *
	 * @param int   $cart_id  Cart post ID.
	 * @param array $settings Settings.
	 * @param bool  $manual   Whether this is a manual send (ignores max_reminders).
	 */
	protected static function process_single_cart( $cart_id, $settings, $manual = false ) {
		$email = get_post_meta( $cart_id, '_acr_email', true );

		if ( ! $email || ! is_email( $email ) ) {
			update_post_meta( $cart_id, '_acr_status', $settings['status_cancelled'] );
			return;
		}

		$already_sent = (int) get_post_meta( $cart_id, '_acr_email_count', true );

		// For automatic cron sends, respect max_reminders and update status.
		if ( ! $manual && $already_sent >= (int) $settings['max_reminders'] ) {
			update_post_meta( $cart_id, '_acr_status', $settings['status_abandoned'] );
			return;
		}

		// Generate token and coupon.
		$token = ACR_Helpers::generate_token();
		$url   = ACR_Helpers::get_recovery_url( $token );

		$coupon_id = ACR_Emails::maybe_create_coupon_for_cart( $cart_id, $settings );

		if ( $coupon_id ) {
			update_post_meta( $cart_id, '_acr_coupon_id', $coupon_id );
		}

		update_post_meta( $cart_id, '_acr_token', $token );
		update_post_meta( $cart_id, '_acr_token_created', current_time( 'timestamp' ) );

		// Send email via wp_mail.
		$sent = ACR_Emails::send_recovery_email( $cart_id, $email, $url, $coupon_id, $settings );

		if ( $sent ) {
			update_post_meta( $cart_id, '_acr_email_count', $already_sent + 1 );
			update_post_meta( $cart_id, '_acr_last_email_sent', current_time( 'timestamp' ) );

			// As soon as at least one recovery email is sent, mark the cart as abandoned
			// so the status in the admin list reflects that it is now an abandoned cart.
			$current_status = get_post_meta( $cart_id, '_acr_status', true );
			if ( $current_status === $settings['status_new'] ) {
				update_post_meta( $cart_id, '_acr_status', $settings['status_abandoned'] );
			}

			/**
			 * Fired when a recovery email is successfully sent.
			 *
			 * @param int $cart_id Cart post ID.
			 */
			do_action( 'acr_recovery_email_sent', $cart_id );
		}
	}
}


