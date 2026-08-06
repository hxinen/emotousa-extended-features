<?php
/**
 * Developer tools, visual debuggers, and commented-out cron jobs.
 * 
 * @package EMotoUSAExtended
 */

defined( 'ABSPATH' ) || exit;

/**
 * Debug store location priority values on shop pages.
 * Only registered when both WP_DEBUG and WP_DEBUG_DISPLAY are true.
 * Safe to leave in codebase — never outputs on production.
 */
function mcmp_debug_location_sort() {
	if ( ! current_user_can( 'administrator' ) ) {
		return;
	}
	
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$location = mcmp_get_store_location( $product->get_id() );
	$config   = mcmp_get_store_location_config();
	$priority = isset( $config[$location] ) ? $config[$location]['priority'] : 99;

	echo '<div style="background:red;color:white;font-size:10px;z-index:9999;position:relative;padding:2px 5px;">';
	echo 'Raw: ' . esc_html( $location ) . ' | Priority: ' . esc_html( $priority );
	echo '</div>';
}
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
	add_action( 'woocommerce_after_shop_loop_item_title', 'mcmp_debug_location_sort', 1 );
}

/**
 * ============================================================================
 * WP ALL IMPORT SCHEDULED TASKS (COMMENTED OUT)
 * Uncomment add_action lines only when configuring automated imports.
 * ============================================================================
 */
/*
function mcmp_log_event( $message ) {
	if ( function_exists( 'wc_get_logger' ) ) {
		$logger = wc_get_logger();
		$logger->log( 'info', $message, array( 'source' => 'mcmp-action-scheduler' ) );
	}
}

// add_action( 'init', 'mcmp_schedule_import_export' );
function mcmp_schedule_import_export() {
	if ( function_exists( 'as_has_scheduled_action' ) ) {
		if ( false === as_has_scheduled_action( 'mcmp_trigger_import_export' ) ) {
			as_schedule_recurring_action( strtotime( '00:00:00' ), DAY_IN_SECONDS, 'mcmp_trigger_import_export' );
			mcmp_log_event( 'Scheduled action created.' );
		}
	}
}

// add_action( 'mcmp_trigger_import_export', 'mcmp_run_import_export' );
function mcmp_run_import_export() {
	mcmp_log_event( 'Import/Export action started.' );
	// Add logic to trigger WP All Import / WP All Export
}
*/
