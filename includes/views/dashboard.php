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
