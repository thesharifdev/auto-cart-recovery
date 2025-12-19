<?php
/**
 * Plugin Name:       Auto Cart Recovery
 * Description:       Recover abandoned WooCommerce carts using native WordPress systems (CPT, cron, transients, wp_mail, and WooCommerce coupons).
 * Version:           1.0.0
 * Author:            Your Name
 * Text Domain:       auto-cart-recovery
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * @package Auto_Cart_Recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Auto_Cart_Recovery' ) ) {

	/**
	 * Main plugin class.
	 *
	 * Final singleton, bootstrap for all components.
	 */
	final class Auto_Cart_Recovery {

		/**
		 * Singleton instance.
		 *
		 * @var Auto_Cart_Recovery|null
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
		 * @return Auto_Cart_Recovery
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
         * Include required files.
         */
		private function includes() {
			require_once ACR_PLUGIN_DIR . 'includes/class-acr-cpt.php';
			require_once ACR_PLUGIN_DIR . 'includes/class-acr-cron.php';
			require_once ACR_PLUGIN_DIR . 'includes/class-acr-recovery.php';
			require_once ACR_PLUGIN_DIR . 'includes/class-acr-admin.php';
			require_once ACR_PLUGIN_DIR . 'includes/class-acr-emails.php';
			require_once ACR_PLUGIN_DIR . 'includes/class-acr-list-table.php';
			require_once ACR_PLUGIN_DIR . 'includes/class-acr-helpers.php';
		}

		/**
		 * Init hooks.
		 */
		private function init_hooks() {
			add_action( 'init', array( 'ACR_CPT', 'register_post_type' ) );
			add_action( 'init', array( 'ACR_Recovery', 'register_rewrite' ) );
			add_filter( 'query_vars', array( 'ACR_Recovery', 'add_query_vars' ) );
			add_action( 'template_redirect', array( 'ACR_Recovery', 'handle_recovery_request' ) );

			// Cron.
			add_filter( 'cron_schedules', array( 'ACR_Cron', 'add_custom_schedules' ) );
			add_action( self::CRON_HOOK, array( 'ACR_Cron', 'process_abandoned_carts' ) );

			// WooCommerce cart hooks to track and store abandoned carts.
			add_action( 'woocommerce_cart_updated', array( 'ACR_CPT', 'maybe_capture_cart' ) );
			add_action( 'woocommerce_thankyou', array( 'ACR_CPT', 'mark_cart_recovered_on_order' ), 10, 1 );

			// Admin.
			if ( is_admin() ) {
				ACR_Admin::get_instance();
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
	return Auto_Cart_Recovery::get_instance();
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
	ACR_CPT::register_post_type();
	ACR_Recovery::register_rewrite();

	flush_rewrite_rules();

	// Schedule cron.
	if ( ! wp_next_scheduled( Auto_Cart_Recovery::CRON_HOOK ) ) {
		wp_schedule_event( time(), 'fifteen_minutes', Auto_Cart_Recovery::CRON_HOOK );
	}

	// Set activation time and version.
	update_option( 'acr_activation_time', current_time( 'timestamp' ) );
	update_option( 'acr_plugin_version', Auto_Cart_Recovery::VERSION );

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
	$timestamp = wp_next_scheduled( Auto_Cart_Recovery::CRON_HOOK );

	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, Auto_Cart_Recovery::CRON_HOOK );
	}

	flush_rewrite_rules();

	/**
	 * Fired after Auto Cart Recovery deactivation completes.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acr_deactivated' );
}



