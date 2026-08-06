<?php
/**
 * Checkout flow interruptions, validations, and custom fields.
 * 
 * @package EMotoUSAExtended
 */

defined( 'ABSPATH' ) || exit;

/**
 * Check if the current cart contains any items matching a specific configuration key.
 * Shared helper — uses static cache to avoid scanning the cart twice per page load.
 *
 * @param string $config_key The boolean key to check in mcmp_get_store_location_config().
 *                           e.g., 'requires_preorder_checkbox' or 'strict_preorder_validation'.
 * @return bool True if any cart item matches the required condition.
 */
function mcmp_cart_has_location_type( $config_key ) {
	static $results = array();
	
	if ( isset( $results[ $config_key ] ) ) {
		return $results[ $config_key ];
	}
	
	if ( ! WC()->cart || WC()->cart->is_empty() ) {
		return $results[ $config_key ] = false;
	}

	$config = mcmp_get_store_location_config();

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$variation_id = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
		$store_loc    = mcmp_get_store_location( $variation_id );

		// If the store location exists in config and the specified key is true
		if ( isset( $config[ $store_loc ] ) && ! empty( $config[ $store_loc ][ $config_key ] ) ) {
			return $results[ $config_key ] = true;
		}
	}
	
	return $results[ $config_key ] = false;
}

/**
 * Add pre-order acknowledgement checkbox to checkout.
 * Only shown when cart contains items flagged with 'requires_preorder_checkbox'.
 */
function mcmp_add_preorder_checkbox() {
	if ( ! mcmp_cart_has_location_type( 'requires_preorder_checkbox' ) ) {
		return;
	}
	
	woocommerce_form_field( 'cus_pre_order', array(
		'type'     => 'checkbox',
		'class'    => array( 'input-checkbox' ),
		'label'    => __( 'I understand this is a pre-order with 7-10 business days ETA. Pickup from Kahului store upon arrival.', 'emotousa-extended' ),
		'required' => true,
	), WC()->checkout->get_value( 'cus_pre_order' ) );
}
add_action( 'woocommerce_review_order_before_submit', 'mcmp_add_preorder_checkbox' );

/**
 * Validate pre-order checkbox on checkout submission.
 * Enforced only for items flagged with 'strict_preorder_validation'.
 */
function mcmp_validate_preorder_checkbox() {
	if ( mcmp_cart_has_location_type( 'strict_preorder_validation' ) && empty( $_POST['cus_pre_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		wc_add_notice( __( 'You must agree to the pre-order conditions to proceed.', 'emotousa-extended' ), 'error' );
	}
}
add_action( 'woocommerce_checkout_process', 'mcmp_validate_preorder_checkbox' );

/**
 * Save pre-order acknowledgement to order meta using WooCommerce CRUD.
 * HPOS-compatible.
 *
 * @param WC_Order $order Order object.
 */
function mcmp_save_preorder_checkbox( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	if ( isset( $_POST['cus_pre_order'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$order->update_meta_data( 'cus_pre_order', 'yes' );
	}
}
add_action( 'woocommerce_checkout_create_order', 'mcmp_save_preorder_checkbox', 10, 1 );

/**
 * Display pre-order acknowledgement in admin order view.
 *
 * @param WC_Order $order Order object.
 */
function mcmp_display_preorder_info_admin( $order ) {
	if ( 'yes' !== $order->get_meta( 'cus_pre_order' ) ) {
		return;
	}
	
	echo '<p><strong>' . esc_html__( 'Additional Information:', 'emotousa-extended' ) . '</strong><br>';
	echo esc_html__( '7-10 business days ETA. Pickup from Kahului store upon arrival.', 'emotousa-extended' ) . '</p>';
}
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'mcmp_display_preorder_info_admin' );

/**
 * ============================================================================
 * SECURITY / VALIDATION
 * ============================================================================
 */

/**
 * Block checkout submission from requests with empty or unknown IP addresses.
 */
function mcmp_block_unknown_ip_orders() {
	$user_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	
	if ( empty( $user_ip ) || 'unknown' === strtolower( $user_ip ) ) {
		wc_add_notice( __( 'Orders from your location are not allowed.', 'emotousa-extended' ), 'error' );
	}
}
add_action( 'woocommerce_checkout_process', 'mcmp_block_unknown_ip_orders' );
