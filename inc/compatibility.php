<?php
/**
 * Third-party plugin compatibility and conflict resolution.
 * 
 * @package EMotoUSAExtended
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dequeue PayTomorrow scripts on checkout to prevent conflicts.
 */
function mcmp_remove_paytomorrow_script_checkout() {
	if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
		wp_dequeue_script( 'paytomorrow-js' ); // Replace with exact handle if different
	}
}
add_action( 'wp_enqueue_scripts', 'mcmp_remove_paytomorrow_script_checkout', 999 );
