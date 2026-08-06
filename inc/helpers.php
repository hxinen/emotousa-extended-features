<?php
/**
 * Helper functions and global configurations.
 * 
 * @package EMotoUSAExtended
 */

defined( 'ABSPATH' ) || exit;

/**
 * Master configuration for all store locations.
 * Serves as the single source of truth for sorting, display, and checkout rules.
 * 
 * IMPORTANT: The array keys exactly match the strings saved in the database.
 * Do not change the keys unless performing a database migration.
 *
 * @return array Store location configuration.
 */
function mcmp_get_store_location_config() {
	return array(
		'In Maui Stock' => array(
			'label'                      => __( 'In Maui Stock', 'emotousa-extended' ),
			'priority'                   => 1,
			'badge_class'                => 'loc-maui',
			'badge_text'                 => __( 'In Maui Stock', 'emotousa-extended' ),
			'tag_html'                   => '<span class="stock-tag in-stock">' . esc_html__( 'In Stock - Maui', 'emotousa-extended' ) . '</span>',
			'add_to_cart_text'           => __( 'Add to cart', 'emotousa-extended' ),
			'requires_preorder_checkbox' => false,
			'strict_preorder_validation' => false,
		),
		'Pre-Order with Store Pickup' => array(
			'label'                      => __( 'Pre-Order with Store Pickup', 'emotousa-extended' ),
			'priority'                   => 2,
			'badge_class'                => 'pre-order-store-pickup',
			'badge_text'                 => __( 'Pre-Order', 'emotousa-extended' ),
			'tag_html'                   => '<span class="stock-tag pre-order">' . esc_html__( 'Pre-Order', 'emotousa-extended' ) . '</span>',
			'add_to_cart_text'           => __( 'Pre Order Now!', 'emotousa-extended' ),
			'requires_preorder_checkbox' => true,
			'strict_preorder_validation' => false,
		),
		'Pre-order with Home Delivery' => array(
			'label'                      => __( 'Pre-order with Home Delivery', 'emotousa-extended' ),
			'priority'                   => 3,
			'badge_class'                => 'pre-order-store-pickup',
			'badge_text'                 => __( 'Pre-Order', 'emotousa-extended' ),
			'tag_html'                   => '<span class="stock-tag pre-order">' . esc_html__( 'Pre-Order', 'emotousa-extended' ) . '</span>',
			'add_to_cart_text'           => __( 'Pre Order Now!', 'emotousa-extended' ),
			'requires_preorder_checkbox' => true,
			'strict_preorder_validation' => true, // Fails checkout if not checked
		),
		'In CA Warehouse' => array(
			'label'                      => __( 'In CA Warehouse', 'emotousa-extended' ),
			'priority'                   => 4,
			'badge_class'                => 'loc-ca',
			'badge_text'                 => __( 'In CA Warehouse', 'emotousa-extended' ),
			'tag_html'                   => '<span class="stock-tag ca-warehouse">' . esc_html__( 'CA Warehouse', 'emotousa-extended' ) . '</span>',
			'add_to_cart_text'           => __( 'Add to cart', 'emotousa-extended' ),
			'requires_preorder_checkbox' => true,
			'strict_preorder_validation' => true, // Fails checkout if not checked
		),
	);
}

/**
 * Get the validated store location for a product or variation ID.
 * Defaults to 'In Maui Stock' when no meta value is stored or if value is invalid.
 * Uses a static cache to avoid repeated get_post_meta() calls per request.
 *
 * @param int $product_id Product or variation ID.
 * @return string Validated store location key.
 */
function mcmp_get_store_location( $product_id ) {
	static $cache = array();

	if ( isset( $cache[ $product_id ] ) ) {
		return $cache[ $product_id ];
	}

	$location = get_post_meta( $product_id, '_store_location_field', true );
	$config   = mcmp_get_store_location_config();

	// Fallback to Maui if empty or if the database contains an unknown string
	if ( empty( $location ) || ! array_key_exists( $location, $config ) ) {
		$location = 'In Maui Stock';
	}

	$cache[ $product_id ] = $location;
	return $location;
}


/**
 * Hardcoded security configuration for Email OTP authentication.
 * Values are locked in code to prevent accidental degradation of security posture.
 *
 * @return array OTP configuration settings.
 */
function mcmp_get_otp_config() {
	return array(
		'otp_length'       => 6,     // 6-digit code
		'otp_expiry_mins'  => 10,    // Code expires in 10 minutes
		'resend_cooldown'  => 60,    // User must wait 60 seconds before clicking resend
		'max_resends'      => 3,     // Max emails sent per 10 min window (stops email API spam)
		'max_guesses'      => 4,     // Max wrong guesses before the OTP is killed
		'ip_strike_limit'  => 6,     // Number of failures before the IP is banned
		'ip_lock_mins'     => 60,    // IP ban duration (1 hour)
		'enable_checkout'  => true,  // Master toggle for Checkout OTP
		'enable_login'     => true,  // Master toggle for WP Login OTP
		'enable_register'  => true,  // Master toggle for Registration OTP
	);
}