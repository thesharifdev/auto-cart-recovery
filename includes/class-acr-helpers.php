<?php
/**
 * Common helpers for Auto Cart Recovery.
 *
 * @package Auto_Cart_Recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generic helpers.
 */
class ACR_Helpers {

	/**
	 * Get plugin settings with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			'enabled'                 => 1,
			'delay_minutes'           => 30,
			'max_reminders'           => 1,
			'discount_type'           => 'percent',
			'discount_amount'         => 20,
			'coupon_expiry_days'      => 1,
			'email_subject'           => __( 'You left something in your cart', 'auto-cart-recovery' ),
			'email_heading'           => __( 'Complete your purchase', 'auto-cart-recovery' ),
			'email_body'              => __( 'We saved your cart for you. Click the button below to restore your cart and apply your discount.', 'auto-cart-recovery' ),
			'from_name'               => get_bloginfo( 'name' ),
			'from_email'              => get_option( 'admin_email' ),
			'min_cart_total'          => 0,
			'include_guests'          => 1,
			'status_new'              => 'new',
			'status_abandoned'        => 'abandoned',
			'status_recovered'        => 'recovered',
			'status_cancelled'        => 'cancelled',
		);

		$settings = get_option( Auto_Cart_Recovery::OPTION_SETTINGS, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = wp_parse_args( $settings, $defaults );

		/**
		 * Filter Auto Cart Recovery settings.
		 *
		 * @param array $settings Settings array.
		 */
		return apply_filters( 'acr_get_settings', $settings );
	}

	/**
	 * Generate secure recovery token.
	 *
	 * @return string
	 */
	public static function generate_token() {
		return wp_generate_password( 32, false, false );
	}

	/**
	 * Build recovery URL from token.
	 *
	 * @param string $token Token.
	 *
	 * @return string
	 */
	public static function get_recovery_url( $token ) {
		$url = add_query_arg(
			array(
				Auto_Cart_Recovery::RECOVERY_QUERY_VAR => rawurlencode( $token ),
			),
			home_url( '/' )
		);

		/**
		 * Filter the recovery URL.
		 *
		 * @param string $url   Full URL.
		 * @param string $token Token.
		 */
		return apply_filters( 'acr_recovery_url', $url, $token );
	}

	/**
	 * Simple capability check for managing plugin.
	 *
	 * @return string
	 */
	public static function get_manage_capability() {
		/**
		 * Filter the capability used to manage Auto Cart Recovery.
		 *
		 * @param string $cap Capability.
		 */
		return apply_filters( 'acr_manage_capability', 'manage_woocommerce' );
	}
}


