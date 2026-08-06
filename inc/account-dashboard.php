<?php
/**
 * Account dashboard features.
 *
 * @package E-MotoUSA_Extended_Features
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register account dashboard shortcodes.
 */
function emusaef_register_account_dashboard_shortcodes() {
	add_shortcode( 'emoto_recent_orders', 'emusaef_render_recent_orders_shortcode' );
}
add_action( 'init', 'emusaef_register_account_dashboard_shortcodes' );

/**
 * Render recent orders for the current logged-in user.
 *
 * Usage: [emoto_recent_orders limit="4"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function emusaef_render_recent_orders_shortcode( $atts = array() ) {
	if ( ! is_user_logged_in() || ! function_exists( 'wc_get_orders' ) ) {
		return '';
	}

	$atts = shortcode_atts(
		array(
			'limit' => 4,
		),
		$atts,
		'emoto_recent_orders'
	);

	$customer_id = get_current_user_id();
	$limit       = max( 1, absint( $atts['limit'] ) );

	$orders = wc_get_orders(
		array(
			'customer_id' => $customer_id,
			'limit'       => $limit,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'return'      => 'objects',
			'status'      => array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed' ),
		)
	);

	$orders_url = function_exists( 'wc_get_account_endpoint_url' )
		? wc_get_account_endpoint_url( 'orders' )
		: wc_get_page_permalink( 'myaccount' );

	ob_start();
	?>
	<div class="emusaef-recent-orders">
		<div class="emusaef-recent-orders__header">
			<div>
				<h3 class="emusaef-recent-orders__title">Recent Orders</h3>
				<p class="emusaef-recent-orders__subtitle">Your latest purchases and order updates.</p>
			</div>

			<a class="emusaef-recent-orders__all" href="<?php echo esc_url( $orders_url ); ?>">
				View all orders
			</a>
		</div>

		<?php if ( empty( $orders ) ) : ?>
			<div class="emusaef-recent-orders__empty">
				<p>No orders yet.</p>
			</div>
		<?php else : ?>
			<div class="emusaef-recent-orders__grid">
				<?php foreach ( $orders as $order ) : ?>
					<?php
					$order_number = $order->get_order_number();
					$order_url    = $order->get_view_order_url();
					$order_date   = $order->get_date_created()
						? $order->get_date_created()->date_i18n( get_option( 'date_format' ) )
						: '';
					$order_total  = $order->get_formatted_order_total();
					$order_status = wc_get_order_status_name( $order->get_status() );
					$status_slug  = sanitize_html_class( $order->get_status() );
					?>
					<article class="emusaef-order-card">
						<div class="emusaef-order-card__top">
							<div>
								<span class="emusaef-order-card__eyebrow">Order</span>
								<h4 class="emusaef-order-card__number">#<?php echo esc_html( $order_number ); ?></h4>
							</div>

							<span class="emusaef-order-card__status status-<?php echo esc_attr( $status_slug ); ?>">
								<?php echo esc_html( $order_status ); ?>
							</span>
						</div>

						<div class="emusaef-order-card__meta">
							<div>
								<span class="emusaef-order-card__eyebrow">Date</span>
								<strong><?php echo esc_html( $order_date ); ?></strong>
							</div>

							<div>
								<span class="emusaef-order-card__eyebrow">Total</span>
								<strong><?php echo wp_kses_post( $order_total ); ?></strong>
							</div>
						</div>

						<a class="emusaef-order-card__link" href="<?php echo esc_url( $order_url ); ?>">
							View order
						</a>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php

	return ob_get_clean();
}