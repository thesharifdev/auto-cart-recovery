<?php

namespace AutoCartRecovery;

/**
 * Admin UI for Auto Cart Recovery.
 *
 * @package AutoCartRecovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin pages, settings, and notices.
 */
class Admin {

	/**
	 * Singleton instance.
	 *
	 * @var Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return ACR_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_acr_send_email', array( $this, 'handle_manual_email' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );

		// Keep dashboard stats fresh when carts/emails change.
		add_action( 'acr_cart_captured', array( $this, 'flush_dashboard_cache' ) );
		add_action( 'acr_cart_recovered', array( $this, 'flush_dashboard_cache' ) );
		add_action( 'acr_recovery_email_sent', array( $this, 'flush_dashboard_cache' ) );
	}

	/**
	 * Register top-level and sub menus.
	 */
	public function register_menu() {
		$cap = Helpers::get_manage_capability();

		add_menu_page(
			__( 'Auto Cart Recovery', 'auto-cart-recovery' ),
			__( 'Cart Recovery', 'auto-cart-recovery' ),
			$cap,
			'acr-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-cart',
			56
		);

		add_submenu_page(
			'acr-dashboard',
			__( 'Abandoned Carts', 'auto-cart-recovery' ),
			__( 'Abandoned Carts', 'auto-cart-recovery' ),
			$cap,
			'acr-carts',
			array( $this, 'render_carts_page' )
		);

		add_submenu_page(
			'acr-dashboard',
			__( 'Settings', 'auto-cart-recovery' ),
			__( 'Settings', 'auto-cart-recovery' ),
			$cap,
			'acr-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'acr_settings_group',
			\AutoCartRecovery::OPTION_SETTINGS,
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Flush cached dashboard stats transient.
	 */
	public function flush_dashboard_cache() {
		delete_transient( 'acr_dashboard_stats' );
	}

	/**
	 * Enqueue admin styles on ACR pages only.
	 */
	public function enqueue_admin_styles() {
		$screen = get_current_screen();

		// Check if we're on an ACR admin page
		if ( $screen && strpos( $screen->id, 'acr-' ) !== false ) {
			wp_enqueue_style(
				'acr-admin-styles',
				ACR_PLUGIN_URL . 'assets/css/style.css',
				array(),
				\AutoCartRecovery::VERSION
			);
		}
	}
	
	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input.
	 *
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$output = array();

		$output['enabled']            = ! empty( $input['enabled'] ) ? 1 : 0;
		$output['delay_minutes']      = isset( $input['delay_minutes'] ) ? absint( $input['delay_minutes'] ) : 30;
		$output['discount_type']      = in_array( $input['discount_type'] ?? 'percent', array( 'percent', 'fixed_cart' ), true ) ? $input['discount_type'] : 'percent';
		$output['discount_amount']    = isset( $input['discount_amount'] ) ? floatval( $input['discount_amount'] ) : 0;
		$output['coupon_expiry_days'] = isset( $input['coupon_expiry_days'] ) ? absint( $input['coupon_expiry_days'] ) : 7;
		$output['email_subject']      = sanitize_text_field( $input['email_subject'] ?? '' );
		$output['email_heading']      = sanitize_text_field( $input['email_heading'] ?? '' );
		$output['email_body']         = wp_kses_post( $input['email_body'] ?? '' );
		$output['from_name']          = sanitize_text_field( $input['from_name'] ?? '' );
		$output['from_email']         = sanitize_email( $input['from_email'] ?? '' );
		$output['min_cart_total']     = isset( $input['min_cart_total'] ) ? floatval( $input['min_cart_total'] ) : 0;
		$output['include_guests']     = ! empty( $input['include_guests'] ) ? 1 : 0;

		return $output;
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( Helpers::get_manage_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'auto-cart-recovery' ) );
		}

		$stats = get_transient( 'acr_dashboard_stats' );

		if ( false === $stats ) {
			$stats = $this->calculate_stats();
			set_transient( 'acr_dashboard_stats', $stats, 5 * MINUTE_IN_SECONDS );
		}

		$recovered_amount = isset( $stats['recovered_amount'] ) ? (float) $stats['recovered_amount'] : 0;
		$amount_formatted = function_exists( 'wc_price' ) ? wc_price( $recovered_amount ) : number_format_i18n( $recovered_amount, 2 );

		require_once __DIR__ . '/views/dashboard.php';
	}

	/**
	 * Calculate dashboard stats using WP_Query.
	 *
	 * @return array
	 */
	protected function calculate_stats() {
		$settings = Helpers::get_settings();

		$total = new \WP_Query(
			array(
				'post_type'      => \AutoCartRecovery::CPT_SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		$recovered_query = new \WP_Query(
			array(
				'post_type'      => \AutoCartRecovery::CPT_SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_acr_status',
						'value' => $settings['status_recovered'],
					),
				),
			)
		);

		$emails = new \WP_Query(
			array(
				'post_type'      => \AutoCartRecovery::CPT_SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_acr_email_count',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$email_count = 0;
		$recovered_amount = 0;

		foreach ( $recovered_query->posts as $cart_id ) {
			$order_id = (int) get_post_meta( $cart_id, '_acr_recovered_order_id', true );

			if ( $order_id && function_exists( 'wc_get_order' ) ) {
				$order = \wc_get_order( $order_id );

				if ( $order ) {
					$recovered_amount += (float) $order->get_total();
				}
			}
		}

		foreach ( $emails->posts as $cart_id ) {
			$email_count += (int) get_post_meta( $cart_id, '_acr_email_count', true );
		}

		return array(
			'total'            => (int) $total->found_posts,
			'recovered'        => (int) $recovered_query->found_posts,
			'recovered_amount' => $recovered_amount,
			'emails'           => $email_count,
		);
	}

	/**
	 * Render carts page with WP_List_Table.
	 */
	public function render_carts_page() {
		if ( ! current_user_can( Helpers::get_manage_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'auto-cart-recovery' ) );
		}

		$list_table = new CartListTable();
		$list_table->prepare_items();

		require_once __DIR__ . '/views/abandoned-card-list.php';
	}

	/**
	 * Handle manual send email action from list table.
	 */
	public function handle_manual_email() {
		if ( ! current_user_can( Helpers::get_manage_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'auto-cart-recovery' ) );
		}

		$cart_id = isset( $_GET['cart_id'] ) ? absint( $_GET['cart_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $cart_id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=acr-carts' ) );
			exit;
		}

		check_admin_referer( 'acr_send_email_' . $cart_id );

		Cron::send_recovery_for_cart( $cart_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'acr-carts',
					'acr_email_sent' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( Helpers::get_manage_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'auto-cart-recovery' ) );
		}

		$settings = Helpers::get_settings();

		require_once __DIR__ . '/views/settings.php';
	}
}


