<?php
/**
 * Settings page view.
 *
 * @package AutoCartRecovery
 *
 * @var array $settings Current plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
						<input type="checkbox" name="<?php echo esc_attr( \AutoCartRecovery::OPTION_SETTINGS ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
						<?php esc_html_e( 'Enable abandoned cart tracking and emails', 'auto-cart-recovery' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Delay before email (minutes)', 'auto-cart-recovery' ); ?></th>
				<td>
					<input type="number" min="5" name="<?php echo esc_attr( \AutoCartRecovery::OPTION_SETTINGS ); ?>[delay_minutes]" value="<?php echo esc_attr( $settings['delay_minutes'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Discount type', 'auto-cart-recovery' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( \AutoCartRecovery::OPTION_SETTINGS ); ?>[discount_type]">
						<option value="percent" <?php selected( $settings['discount_type'], 'percent' ); ?>><?php esc_html_e( 'Percentage discount', 'auto-cart-recovery' ); ?></option>
						<option value="fixed_cart" <?php selected( $settings['discount_type'], 'fixed_cart' ); ?>><?php esc_html_e( 'Fixed cart discount', 'auto-cart-recovery' ); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Discount amount', 'auto-cart-recovery' ); ?></th>
				<td>
					<input type="number" step="0.01" min="0" name="<?php echo esc_attr( \AutoCartRecovery::OPTION_SETTINGS ); ?>[discount_amount]" value="<?php echo esc_attr( $settings['discount_amount'] ); ?>" />
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Coupon expiry (days)', 'auto-cart-recovery' ); ?></th>
				<td>
					<input type="number" min="0" name="<?php echo esc_attr( \AutoCartRecovery::OPTION_SETTINGS ); ?>[coupon_expiry_days]" value="<?php echo esc_attr( $settings['coupon_expiry_days'] ); ?>" />
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Minimum cart total', 'auto-cart-recovery' ); ?></th>
				<td>
					<input type="number" step="0.01" min="0" name="<?php echo esc_attr( \AutoCartRecovery::OPTION_SETTINGS ); ?>[min_cart_total]" value="<?php echo esc_attr( $settings['min_cart_total'] ); ?>" />
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Include guests', 'auto-cart-recovery' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( \AutoCartRecovery::OPTION_SETTINGS ); ?>[include_guests]" value="1" <?php checked( ! empty( $settings['include_guests'] ) ); ?> />
						<?php esc_html_e( 'Track and email guest customers', 'auto-cart-recovery' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'From name', 'auto-cart-recovery' ); ?></th>
				<td>
					<input type="text" class="regular-text" name="<?php echo esc_attr( \AutoCartRecovery::OPTION_SETTINGS ); ?>[from_name]" value="<?php echo esc_attr( $settings['from_name'] ); ?>" />
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'From email', 'auto-cart-recovery' ); ?></th>
				<td>
					<input type="email" class="regular-text" name="<?php echo esc_attr( \AutoCartRecovery::OPTION_SETTINGS ); ?>[from_email]" value="<?php echo esc_attr( $settings['from_email'] ); ?>" />
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Email subject', 'auto-cart-recovery' ); ?></th>
				<td>
					<input type="text" class="regular-text" name="<?php echo esc_attr( \AutoCartRecovery::OPTION_SETTINGS ); ?>[email_subject]" value="<?php echo esc_attr( $settings['email_subject'] ); ?>" />
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Email heading', 'auto-cart-recovery' ); ?></th>
				<td>
					<input type="text" class="regular-text" name="<?php echo esc_attr( \AutoCartRecovery::OPTION_SETTINGS ); ?>[email_heading]" value="<?php echo esc_attr( $settings['email_heading'] ); ?>" />
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Email body', 'auto-cart-recovery' ); ?></th>
				<td>
					<textarea name="<?php echo esc_attr( \AutoCartRecovery::OPTION_SETTINGS ); ?>[email_body]" rows="6" class="large-text"><?php echo esc_textarea( $settings['email_body'] ); ?></textarea>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
