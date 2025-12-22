<?php
/**
 * Carts page view.
 *
 * @package AutoCartRecovery
 *
 * @var CartListTable $list_table The carts list table.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
