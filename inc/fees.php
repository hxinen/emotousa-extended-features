<?php
/**
 * Custom cart fees and order surcharges.
 * 
 * @package EMotoUSAExtended
 */

defined( 'ABSPATH' ) || exit;

/**
 * ============================================================================
 * TORP PRODUCTS – CUSTOMS DUTY FEE (17%)
 * ============================================================================
 */

/**
 * Apply 17% customs duty surcharge for Torp-branded cart items.
 */
function mcmp_apply_torp_surcharge() {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}

	if ( ! WC()->cart || WC()->cart->is_empty() ) {
		return;
	}

	$torp_total = 0.0;
	
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( stripos( $cart_item['data']->get_name(), 'Torp' ) !== false ) {
			$torp_total += (float) $cart_item['line_total'];
		}
	}

	if ( $torp_total <= 0 ) {
		return;
	}

	$surcharge = round( $torp_total * 0.17, 2 );
	
	// Third parameter 'false' means this fee is not taxable
	WC()->cart->add_fee( __( 'Customs Duty (17%)', 'emotousa-extended' ), $surcharge, false );
}
//add_action( 'woocommerce_cart_calculate_fees', 'mcmp_apply_torp_surcharge' );

/**
 * Save Torp surcharge to order meta upon checkout completion.
 * Recalculates from order items to ensure accuracy and avoid stale session data.
 *
 * @param WC_Order $order Order object.
 */
function mcmp_save_torp_surcharge( $order ) {
	$torp_total = 0.0;
	
	foreach ( $order->get_items() as $item ) {
		if ( stripos( $item->get_name(), 'Torp' ) !== false ) {
			$torp_total += (float) $item->get_total();
		}
	}
	
	if ( $torp_total > 0 ) {
		$surcharge = round( $torp_total * 0.17, 2 );
		$order->update_meta_data( '_torp_surcharge', $surcharge );
	}
}
add_action( 'woocommerce_checkout_create_order', 'mcmp_save_torp_surcharge' );

/**
 * Display Torp surcharge row in admin order totals.
 *
 * @param int $order_id Order ID.
 */
function mcmp_display_torp_surcharge_admin( $order_id ) {
	$order     = wc_get_order( $order_id );
	$surcharge = $order ? $order->get_meta( '_torp_surcharge' ) : '';

	if ( ! $surcharge ) {
		return;
	}

	// Guard: Verify Torp items actually exist in the order before showing the meta.
	// This prevents displaying the fee if items were later removed or refunded.
	$has_torp = false;
	foreach ( $order->get_items() as $item ) {
		if ( stripos( $item->get_name(), 'Torp' ) !== false ) {
			$has_torp = true;
			break;
		}
	}

	if ( ! $has_torp ) {
		return;
	}
	?>
	<tr>
		<td class="label"><?php esc_html_e( 'Customs Duty (17%)', 'emotousa-extended' ); ?></td>
		<td width="1%"></td>
		<td class="total"><?php echo wp_kses_post( wc_price( $surcharge ) ); ?></td>
	</tr>
	<?php
}
add_action( 'woocommerce_admin_order_totals_after_shipping', 'mcmp_display_torp_surcharge_admin' );
