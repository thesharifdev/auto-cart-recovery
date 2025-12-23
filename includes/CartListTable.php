<?php

namespace AutoCartRecovery;

/**
 * WP_List_Table implementation for abandoned carts.
 *
 * @package AutoCartRecovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Abandoned carts list table.
 */
class CartListTable extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'acr_cart',
				'plural'   => 'acr_carts',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'email'       => __( 'Email', 'auto-cart-recovery' ),
			'status'      => __( 'Status', 'auto-cart-recovery' ),
			'total'       => __( 'Cart Total', 'auto-cart-recovery' ),
			'updated'     => __( 'Last Updated', 'auto-cart-recovery' ),
			'emails_sent' => __( 'Emails Sent', 'auto-cart-recovery' ),
			'actions'     => __( 'Actions', 'auto-cart-recovery' ),
		);
	}

	/**
	 * Prepare items and headers.
	 */
	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		// Set up column headers (required for proper WP_List_Table rendering).
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$args = array(
			'post_type'      => \AutoCartRecovery::CPT_SLUG,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
		);

		$query = new \WP_Query( $args );

		$this->items = array();

		foreach ( $query->posts as $post ) {
			$this->items[] = array(
				'ID'          => $post->ID,
				'email'       => get_post_meta( $post->ID, '_acr_email', true ),
				'status'      => get_post_meta( $post->ID, '_acr_status', true ),
				'total'       => get_post_meta( $post->ID, '_acr_cart_total', true ),
				'updated'     => get_post_meta( $post->ID, '_acr_last_updated', true ),
				'emails_sent' => (int) get_post_meta( $post->ID, '_acr_email_count', true ),
			);
		}

		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => $query->max_num_pages,
			)
		);

		wp_reset_postdata();
	}

	/**
	 * Message shown when there are no carts.
	 */
	public function no_items() {
		esc_html_e( 'No abandoned carts found.', 'auto-cart-recovery' );
	}

	/**
	 * Default column rendering.
	 *
	 * @param array  $item        Item.
	 * @param string $column_name Column name.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'email':
				return esc_html( $item['email'] );
			case 'status':
				return esc_html( $item['status'] );
			case 'total':
				return wc_price( (float) $item['total'] );
			case 'updated':
				if ( ! empty( $item['updated'] ) ) {
					return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $item['updated'] ) );
				}
				return '';
			case 'emails_sent':
				return (int) $item['emails_sent'];
			case 'actions':
				$cart_id = (int) $item['ID'];
				$url     = wp_nonce_url(
					add_query_arg(
						array(
							'action'  => 'acr_send_email',
							'cart_id' => $cart_id,
						),
						admin_url( 'admin-post.php' )
					),
					'acr_send_email_' . $cart_id
				);

				$label = __( 'Send email', 'auto-cart-recovery' );

				return '<a href="' . esc_url( $url ) . '" class="button button-small">' . esc_html( $label ) . '</a>';
		}

		return '';
	}
}


