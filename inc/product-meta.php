<?php
/**
 * WooCommerce Custom Product & Variation Meta Fields.
 * 
 * @package EMotoUSAExtended
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add custom fields to the WooCommerce product General tab.
 * Registers: _vin, _store_location_field, _mcmp_dealer_cost, _mcmp_suggested_price.
 */
function mcmp_add_product_general_fields() {
	global $product_object;
	if ( ! $product_object instanceof WC_Product ) {
		return;
	}

	$product_id = $product_object->get_id();
	$config     = mcmp_get_store_location_config();

	// Dynamically build location dropdown options from the centralized config
	$location_options = array( '' => __( '— Select location —', 'emotousa-extended' ) );
	foreach ( $config as $key => $data ) {
		$location_options[ $key ] = $data['label'];
	}

	// VIN field
	woocommerce_wp_text_input( array(
		'id'          => '_vin',
		'label'       => __( 'VIN', 'emotousa-extended' ),
		'desc_tip'    => true,
		'description' => __( 'Enter the Vehicle Identification Number (VIN) for this product.', 'emotousa-extended' ),
		'value'       => get_post_meta( $product_id, '_vin', true ),
	) );

	// Store Location (simple products only)
	woocommerce_wp_select( array(
		'id'            => '_store_location_field',
		'label'         => __( 'Store Location', 'emotousa-extended' ),
		'desc_tip'      => true,
		'description'   => __( 'For simple products: set the stock location manually. Variable products: auto-computed from in-stock variations on save.', 'emotousa-extended' ),
		'wrapper_class' => 'show_if_simple',
		'options'       => $location_options,
		'value'         => get_post_meta( $product_id, '_store_location_field', true ),
	) );

	// Dealer cost field
	woocommerce_wp_text_input( array(
		'id'                => '_mcmp_dealer_cost',
		'label'             => __( 'Dealer Cost ($)', 'emotousa-extended' ),
		'desc_tip'          => true,
		'description'       => __( 'Internal dealer cost. Not shown to customers.', 'emotousa-extended' ),
		'type'              => 'number',
		'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
		'value'             => get_post_meta( $product_id, '_mcmp_dealer_cost', true ),
	) );

	// Suggested price field
	woocommerce_wp_text_input( array(
		'id'                => '_mcmp_suggested_price',
		'label'             => __( 'Suggested Price ($)', 'emotousa-extended' ),
		'desc_tip'          => true,
		'description'       => __( 'Manufacturer or supplier suggested retail price.', 'emotousa-extended' ),
		'type'              => 'number',
		'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
		'value'             => get_post_meta( $product_id, '_mcmp_suggested_price', true ),
	) );
}
add_action( 'woocommerce_product_options_general_product_data', 'mcmp_add_product_general_fields' );

/**
 * Save custom product meta fields using batch pattern.
 * Note: woocommerce_process_product_meta fires after WC's own nonce/cap checks.
 *
 * @param int $product_id Product ID.
 */
function mcmp_save_product_general_fields( $product_id ) {
	if ( ! current_user_can( 'edit_post', $product_id ) ) {
		return;
	}

	$fields = array(
		'_vin'                  => 'sanitize_text_field',
		'_store_location_field' => 'sanitize_text_field',
		'_mcmp_dealer_cost'     => 'wc_format_decimal',
		'_mcmp_suggested_price' => 'wc_format_decimal',
	);

	foreach ( $fields as $key => $sanitize_cb ) {
		if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$value = call_user_func( $sanitize_cb, wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			
			if ( '' !== $value ) {
				update_post_meta( $product_id, $key, $value );
			} else {
				delete_post_meta( $product_id, $key );
			}
		}
	}
}
add_action( 'woocommerce_process_product_meta', 'mcmp_save_product_general_fields' );

/**
 * Add store location and VIN fields to product variation editor.
 * Fields are keyed by variation ID (not loop index) to match the save handler.
 *
 * @param int     $loop           Variation loop index.
 * @param array   $variation_data Variation data array.
 * @param WP_Post $variation      Variation post object.
 */
function mcmp_add_variation_fields( $loop, $variation_data, $variation ) {
	$variation_id = $variation->ID;
	$config       = mcmp_get_store_location_config();
	
	// Dynamically build location dropdown options
	$location_options = array();
	foreach ( $config as $key => $data ) {
		$location_options[ $key ] = $data['label'];
	}

	// Store Location dropdown for variation
	woocommerce_wp_select( array(
		'id'            => '_store_location_field[' . $variation_id . ']',
		'name'          => '_store_location_field[' . $variation_id . ']',
		'label'         => __( 'Store Location', 'emotousa-extended' ),
		'desc_tip'      => true,
		'description'   => __( 'Where is this variation available?', 'emotousa-extended' ),
		'wrapper_class' => 'form-row form-row-first',
		'options'       => $location_options,
		'value'         => get_post_meta( $variation_id, '_store_location_field', true ),
	) );

	// VIN field for variation
	woocommerce_wp_text_input( array(
		'id'            => '_vin[' . $variation_id . ']',
		'name'          => '_vin[' . $variation_id . ']',
		'label'         => __( 'VIN', 'emotousa-extended' ),
		'desc_tip'      => true,
		'description'   => __( 'Vehicle Identification Number for this variation.', 'emotousa-extended' ),
		'wrapper_class' => 'form-row form-row-last',
		'value'         => get_post_meta( $variation_id, '_vin', true ),
	) );
}
add_action( 'woocommerce_variation_options', 'mcmp_add_variation_fields', 20, 3 );

/**
 * Save store location and VIN for a variation using WC CRUD.
 * Note: woocommerce_save_product_variation fires after WC's own nonce checks.
 *
 * @param int $variation_id Variation post ID.
 */
function mcmp_save_variation_fields( $variation_id ) {
	if ( ! current_user_can( 'edit_post', $variation_id ) ) {
		return;
	}

	$variation = wc_get_product( $variation_id );
	if ( ! $variation ) {
		return;
	}

	// Process Store Location
	if ( isset( $_POST['_store_location_field'][ $variation_id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$location_val = sanitize_text_field( wp_unslash( $_POST['_store_location_field'][ $variation_id ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$variation->update_meta_data( '_store_location_field', $location_val ); 
	}

	// Process VIN
	if ( isset( $_POST['_vin'][ $variation_id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$vin_val = sanitize_text_field( wp_unslash( $_POST['_vin'][ $variation_id ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$variation->update_meta_data( '_vin', $vin_val );
	}

	$variation->save();
}
add_action( 'woocommerce_save_product_variation', 'mcmp_save_variation_fields' );
