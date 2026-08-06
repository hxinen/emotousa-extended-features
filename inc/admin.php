<?php
/**
 * Backend UI modifications, CSS enqueues, and privilege adjustments.
 * 
 * @package EMotoUSAExtended
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue plugin admin styles.
 * Replaces the old inline admin_head <style> injection.
 *
 * @param string $hook The current admin page.
 */
function mcmp_admin_enqueue_scripts( $hook ) {
	wp_enqueue_style( 
		'emotousa-extended-admin', 
		plugins_url( '../assets/css/admin.css', __FILE__ ), 
		array(), 
		'1.0.0' 
	);
}
add_action( 'admin_enqueue_scripts', 'mcmp_admin_enqueue_scripts' );

/**
 * Display dealer cost and suggested price in product admin General tab.
 */
function mcmp_display_additional_price_fields() {
	global $product_object;
	if ( ! $product_object instanceof WC_Product ) {
		return;
	}

	$product_id      = $product_object->get_id();
	$dealer_cost     = get_post_meta( $product_id, '_mcmp_dealer_cost', true );
	$suggested_price = get_post_meta( $product_id, '_mcmp_suggested_price', true );

	if ( $dealer_cost ) {
		echo '<p class="form-field"><strong>' . sprintf( 
			/* translators: %s: formatted price */
			esc_html__( 'Dealer cost: %s', 'emotousa-extended' ), 
			wp_kses_post( wc_price( $dealer_cost ) ) 
		) . '</strong></p>';
	}
	
	if ( $suggested_price ) {
		echo '<p class="form-field"><strong>' . sprintf( 
			/* translators: %s: formatted price */
			esc_html__( 'Suggested price: %s', 'emotousa-extended' ), 
			wp_kses_post( wc_price( $suggested_price ) ) 
		) . '</strong></p>';
	}
}
add_action( 'woocommerce_product_options_pricing', 'mcmp_display_additional_price_fields' );

/**
 * Remove WooCommerce sync submenu items for shop managers.
 */
function mcmp_remove_admin_menu_items() {
	$current_user = wp_get_current_user();
	
	if ( $current_user && ! empty( $current_user->roles ) && 'shop_manager' === reset( $current_user->roles ) ) {
		remove_submenu_page( 'woocommerce', 'woo-product-sync-report' );
		remove_submenu_page( 'woocommerce', 'woo_product_sync' );
	}
}
add_action( 'admin_menu', 'mcmp_remove_admin_menu_items', 999 );

/**
 * Register a custom "Confirmed" order status.
 */
function mcmp_register_confirmed_order_status() {
	register_post_status( 'wc-confirmed', array(
		'label'                     => _x( 'Confirmed', 'Order status', 'emotousa-extended' ),
		'public'                    => true,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop(
			'Confirmed <span class="count">(%s)</span>',
			'Confirmed <span class="count">(%s)</span>',
			'emotousa-extended'
		),
	) );
}
add_action( 'init', 'mcmp_register_confirmed_order_status' );

/**
 * Insert "Confirmed" into the WooCommerce order status list, right after Processing.
 *
 * @param array $order_statuses Existing order statuses.
 * @return array Modified order statuses.
 */
function mcmp_add_confirmed_to_order_statuses( $order_statuses ) {
	$new_statuses = array();

	foreach ( $order_statuses as $key => $label ) {
		$new_statuses[ $key ] = $label;

		if ( 'wc-processing' === $key ) {
			$new_statuses['wc-confirmed'] = _x( 'Confirmed', 'Order status', 'emotousa-extended' );
		}
	}

	return $new_statuses;
}
add_filter( 'wc_order_statuses', 'mcmp_add_confirmed_to_order_statuses' );
