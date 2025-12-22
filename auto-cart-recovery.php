<?php
/**
 * Plugin Name:       Auto Cart Recovery
 * Description:       Recover abandoned WooCommerce carts.
 * Version:           1.0.0
 * Author:            Sharif
 * Text Domain:       auto-cart-recovery
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * @package AutoCartRecovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AutoCartRecovery\Cpt;
use AutoCartRecovery\Recovery;
use AutoCartRecovery\Cron;
use AutoCartRecovery\Admin;

if ( ! class_exists( 'AutoCartRecovery' ) ) {

	/**
	 * Main plugin class.
	 *
	 * Final singleton, bootstrap for all components.
	 */
	final class AutoCartRecovery {

		/**
		 * Singleton instance.
		 *
		 * @var AutoCartRecovery|null
		 */
		private static $instance = null;

		/**
		 * Plugin version.
		 */
		const VERSION = '1.0.0';

		/**
		 * Option key for settings.
		 */
		const OPTION_SETTINGS = 'acr_settings';

		/**
		 * CPT slug.
		 */
		const CPT_SLUG = 'acr_abandoned_cart';

		/**
		 * Cron hook.
		 */
		const CRON_HOOK = 'acr_process_abandoned_carts';

		/**
		 * Recovery query var.
		 */
		const RECOVERY_QUERY_VAR = 'acr_recover_token';

		/**
		 * Get singleton instance.
		 *
		 * @return AutoCartRecovery
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor.
		 *
		 * Private to enforce singleton.
		 */
		private function __construct() {
			$this->define_constants();
			$this->includes();
			$this->init_hooks();
		}

		/**
		 * Prevent cloning.
		 */
		private function __clone() {}

		/**
		 * Prevent unserialize.
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'auto-cart-recovery' ), self::VERSION );
		}

		/**
		 * Define plugin constants.
		 */
		private function define_constants() {
			if ( ! defined( 'ACR_VERSION' ) ) {
				define( 'ACR_VERSION', self::VERSION );
			}

			if ( ! defined( 'ACR_PLUGIN_FILE' ) ) {
				define( 'ACR_PLUGIN_FILE', __FILE__ );
			}

			if ( ! defined( 'ACR_PLUGIN_DIR' ) ) {
				define( 'ACR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}

			if ( ! defined( 'ACR_PLUGIN_URL' ) ) {
				define( 'ACR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			}
		}

		/**
		 * Init hooks.
		 */
		private function init_hooks() {
			add_action( 'init', array( 'CPT', 'register_post_type' ) );
			add_action( 'init', array( 'Recovery', 'register_rewrite' ) );
			add_filter( 'query_vars', array( 'Recovery', 'add_query_vars' ) );
			add_action( 'template_redirect', array( 'Recovery', 'handle_recovery_request' ) );

			// Cron.
			add_filter( 'cron_schedules', array( 'Cron', 'add_custom_schedules' ) );
			add_action( self::CRON_HOOK, array( 'Cron', 'process_abandoned_carts' ) );
			// WooCommerce cart hooks to track and store abandoned carts.
			add_action( 'woocommerce_cart_updated', array( 'CPT', 'maybe_capture_cart' ) );
			add_action( 'woocommerce_thankyou', array( 'CPT', 'mark_cart_recovered_on_order' ), 10, 1 );

			// Admin.
			if ( is_admin() ) {
				Admin::get_instance();
			}

			/**
			 * Fired once Auto Cart Recovery has been fully initialized.
			 *
			 * @since 1.0.0
			 */
			do_action( 'acr_loaded' );
		}
	}
}

/**
 * Helper function to retrieve main instance.
 *
 * @return Auto_Cart_Recovery
 */
function acr() {
	return AutoCartRecovery::get_instance();
}

// Bootstrap plugin.
acr();

/**
 * Activation hook.
 */
register_activation_hook( __FILE__, 'acr_activation' );

/**
 * On activation - register post type, rewrite rules, cron, options.
 */
function acr_activation() {
	// Register CPT and rewrite before flushing.
	CPT::register_post_type();
	Recovery::register_rewrite();

	flush_rewrite_rules();

	// Schedule cron.
	if ( ! wp_next_scheduled( AutoCartRecovery::CRON_HOOK ) ) {
		wp_schedule_event( time(), 'fifteen_minutes', AutoCartRecovery::CRON_HOOK );
	}

	// Set activation time and version.
	update_option( 'acr_activation_time', current_time( 'timestamp' ) );
	update_option( 'acr_plugin_version', AutoCartRecovery::VERSION );

	/**
	 * Fired after Auto Cart Recovery activation completes.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acr_activated' );
}

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, 'acr_deactivation' );

/**
 * On deactivation - clear cron and flush rules.
 */
function acr_deactivation() {
	$timestamp = wp_next_scheduled( AutoCartRecovery::CRON_HOOK );

	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, AutoCartRecovery::CRON_HOOK );
	}

	flush_rewrite_rules();

	/**
	 * Fired after Auto Cart Recovery deactivation completes.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acr_deactivated' );
}

/**
 * Uninstall hook.
 */
register_uninstall_hook( __FILE__, 'acr_uninstall' );

/**
 * On uninstall - clean up data.
 */
function acr_uninstall() {
	// Delete CPT posts.
	$carts = get_posts(
		array(
			'post_type'      => AutoCartRecovery::CPT_SLUG,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		)
	);

	foreach ( $carts as $cart_id ) {
		wp_delete_post( $cart_id, true );
	}

	// Optional: delete generated coupons (shop_coupon CPT) created by this plugin.
	/**
	 * Filter whether Auto Cart Recovery should delete generated coupons on uninstall.
	 *
	 * @param bool $delete_coupons Default false.
	 */
	$delete_coupons = apply_filters( 'acr_delete_coupons_on_uninstall', false );

	if ( $delete_coupons ) {
		$coupons = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_key'       => '_acr_coupon',
				'meta_value'     => 'yes',
			)
		);

		foreach ( $coupons as $coupon_id ) {
			wp_delete_post( $coupon_id, true );
		}
	}

	// Delete options.
	delete_option( 'acr_activation_time' );
	delete_option( 'acr_plugin_version' );
	delete_option( AutoCartRecovery::OPTION_SETTINGS );

	// Delete transients.
	delete_transient( 'acr_dashboard_stats' );

	/**
	 * Fired after Auto Cart Recovery uninstall cleanup.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acr_uninstalled' );
}


