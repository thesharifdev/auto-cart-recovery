<?php
/**
 * Dashboard page view.
 *
 * @package AutoCartRecovery
 *
 * @var array $stats Dashboard statistics.
 * @var string $amount_formatted Formatted recovered amount.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Auto Cart Recovery', 'auto-cart-recovery' ); ?></h1>

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
