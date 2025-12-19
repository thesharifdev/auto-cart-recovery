<?php
/**
 * Admin UI for Auto Cart Recovery.
 *
 * @package Auto_Cart_Recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin pages, settings, and notices.
 */
class ACR_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var ACR_Admin|null
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
	}

	/**
	 * Register top-level and sub menus.
	 */
	public function register_menu() {
		$cap = ACR_Helpers::get_manage_capability();

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
			Auto_Cart_Recovery::OPTION_SETTINGS,
			array( $this, 'sanitize_settings' )
		);
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
		$output['max_reminders']      = isset( $input['max_reminders'] ) ? absint( $input['max_reminders'] ) : 1;
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
		if ( ! current_user_can( ACR_Helpers::get_manage_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'auto-cart-recovery' ) );
		}

		$stats = get_transient( 'acr_dashboard_stats' );

		if ( false === $stats ) {
			$stats = $this->calculate_stats();
			set_transient( 'acr_dashboard_stats', $stats, 5 * MINUTE_IN_SECONDS );
		}

		$recovered_amount = isset( $stats['recovered_amount'] ) ? (float) $stats['recovered_amount'] : 0;
		$amount_formatted = function_exists( 'wc_price' ) ? wc_price( $recovered_amount ) : number_format_i18n( $recovered_amount, 2 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Auto Cart Recovery', 'auto-cart-recovery' ); ?></h1>

			<style>
				.acr-cards {
					display: flex;
					flex-wrap: wrap;
					gap: 16px;
					margin-top: 20px;
				}
				.acr-card {
					background: #fff;
					border: 1px solid #dcdcde;
					border-radius: 4px;
					padding: 16px 20px;
					min-width: 220px;
					box-shadow: 0 1px 1px rgba(0,0,0,0.04);
				}
				.acr-card-title {
					font-size: 13px;
					text-transform: uppercase;
					color: #50575e;
					margin: 0 0 8px;
					letter-spacing: .02em;
				}
				.acr-card-value {
					font-size: 22px;
					font-weight: 600;
					margin: 0;
				}
			</style>

			<div class="acr-cards">
				<div class="acr-card">
					<p class="acr-card-title"><?php esc_html_e( 'Total Abandoned Carts', 'auto-cart-recovery' ); ?></p>
					<p class="acr-card-value"><?php echo esc_html( $stats['total'] ); ?></p>
				</div>

				<div class="acr-card">
					<p class="acr-card-title"><?php esc_html_e( 'Recovered Carts', 'auto-cart-recovery' ); ?></p>
					<p class="acr-card-value"><?php echo esc_html( $stats['recovered'] ); ?></p>
				</div>

				<div class="acr-card">
					<p class="acr-card-title"><?php esc_html_e( 'Recovered Revenue', 'auto-cart-recovery' ); ?></p>
					<p class="acr-card-value"><?php echo wp_kses_post( $amount_formatted ); ?></p>
				</div>

				<div class="acr-card">
					<p class="acr-card-title"><?php esc_html_e( 'Emails Sent', 'auto-cart-recovery' ); ?></p>
					<p class="acr-card-value"><?php echo esc_html( $stats['emails'] ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Calculate dashboard stats using WP_Query.
	 *
	 * @return array
	 */
	protected function calculate_stats() {
		$settings = ACR_Helpers::get_settings();

		$total = new WP_Query(
			array(
				'post_type'      => Auto_Cart_Recovery::CPT_SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		$recovered_query = new WP_Query(
			array(
				'post_type'      => Auto_Cart_Recovery::CPT_SLUG,
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

		$emails = new WP_Query(
			array(
				'post_type'      => Auto_Cart_Recovery::CPT_SLUG,
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
				$order = wc_get_order( $order_id );

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
		if ( ! current_user_can( ACR_Helpers::get_manage_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'auto-cart-recovery' ) );
		}

		$list_table = new ACR_Abandoned_Cart_List_Table();
		$list_table->prepare_items();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Abandoned Carts', 'auto-cart-recovery' ); ?></h1>

			<?php if ( isset( $_GET['acr_email_sent'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Recovery email sent successfully.', 'auto-cart-recovery' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="get">
				<input type="hidden" name="page" value="acr-carts" />
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle manual send email action from list table.
	 */
	public function handle_manual_email() {
		if ( ! current_user_can( ACR_Helpers::get_manage_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'auto-cart-recovery' ) );
		}

		$cart_id = isset( $_GET['cart_id'] ) ? absint( $_GET['cart_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $cart_id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=acr-carts' ) );
			exit;
		}

		check_admin_referer( 'acr_send_email_' . $cart_id );

		ACR_Cron::send_recovery_for_cart( $cart_id );

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
		if ( ! current_user_can( ACR_Helpers::get_manage_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'auto-cart-recovery' ) );
		}

		$settings = ACR_Helpers::get_settings();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Auto Cart Recovery Settings', 'auto-cart-recovery' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'acr_settings_group' );
				?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Recovery', 'auto-cart-recovery' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Auto_Cart_Recovery::OPTION_SETTINGS ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
								<?php esc_html_e( 'Enable abandoned cart tracking and emails', 'auto-cart-recovery' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Delay before email (minutes)', 'auto-cart-recovery' ); ?></th>
						<td>
							<input type="number" min="5" name="<?php echo esc_attr( Auto_Cart_Recovery::OPTION_SETTINGS ); ?>[delay_minutes]" value="<?php echo esc_attr( $settings['delay_minutes'] ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Maximum reminders per cart', 'auto-cart-recovery' ); ?></th>
						<td>
							<input type="number" min="1" name="<?php echo esc_attr( Auto_Cart_Recovery::OPTION_SETTINGS ); ?>[max_reminders]" value="<?php echo esc_attr( $settings['max_reminders'] ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Discount type', 'auto-cart-recovery' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( Auto_Cart_Recovery::OPTION_SETTINGS ); ?>[discount_type]">
								<option value="percent" <?php selected( $settings['discount_type'], 'percent' ); ?>><?php esc_html_e( 'Percentage discount', 'auto-cart-recovery' ); ?></option>
								<option value="fixed_cart" <?php selected( $settings['discount_type'], 'fixed_cart' ); ?>><?php esc_html_e( 'Fixed cart discount', 'auto-cart-recovery' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Discount amount', 'auto-cart-recovery' ); ?></th>
						<td>
							<input type="number" step="0.01" min="0" name="<?php echo esc_attr( Auto_Cart_Recovery::OPTION_SETTINGS ); ?>[discount_amount]" value="<?php echo esc_attr( $settings['discount_amount'] ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Coupon expiry (days)', 'auto-cart-recovery' ); ?></th>
						<td>
							<input type="number" min="0" name="<?php echo esc_attr( Auto_Cart_Recovery::OPTION_SETTINGS ); ?>[coupon_expiry_days]" value="<?php echo esc_attr( $settings['coupon_expiry_days'] ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Minimum cart total', 'auto-cart-recovery' ); ?></th>
						<td>
							<input type="number" step="0.01" min="0" name="<?php echo esc_attr( Auto_Cart_Recovery::OPTION_SETTINGS ); ?>[min_cart_total]" value="<?php echo esc_attr( $settings['min_cart_total'] ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Include guests', 'auto-cart-recovery' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Auto_Cart_Recovery::OPTION_SETTINGS ); ?>[include_guests]" value="1" <?php checked( ! empty( $settings['include_guests'] ) ); ?> />
								<?php esc_html_e( 'Track and email guest customers', 'auto-cart-recovery' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'From name', 'auto-cart-recovery' ); ?></th>
						<td>
							<input type="text" class="regular-text" name="<?php echo esc_attr( Auto_Cart_Recovery::OPTION_SETTINGS ); ?>[from_name]" value="<?php echo esc_attr( $settings['from_name'] ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'From email', 'auto-cart-recovery' ); ?></th>
						<td>
							<input type="email" class="regular-text" name="<?php echo esc_attr( Auto_Cart_Recovery::OPTION_SETTINGS ); ?>[from_email]" value="<?php echo esc_attr( $settings['from_email'] ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Email subject', 'auto-cart-recovery' ); ?></th>
						<td>
							<input type="text" class="regular-text" name="<?php echo esc_attr( Auto_Cart_Recovery::OPTION_SETTINGS ); ?>[email_subject]" value="<?php echo esc_attr( $settings['email_subject'] ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Email heading', 'auto-cart-recovery' ); ?></th>
						<td>
							<input type="text" class="regular-text" name="<?php echo esc_attr( Auto_Cart_Recovery::OPTION_SETTINGS ); ?>[email_heading]" value="<?php echo esc_attr( $settings['email_heading'] ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Email body', 'auto-cart-recovery' ); ?></th>
						<td>
							<textarea name="<?php echo esc_attr( Auto_Cart_Recovery::OPTION_SETTINGS ); ?>[email_body]" rows="6" class="large-text"><?php echo esc_textarea( $settings['email_body'] ); ?></textarea>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}


