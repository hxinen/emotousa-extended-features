<?php
/**
 * Main plugin module loader.
 * 
 * @package EMotoUSAExtended
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load all plugin modules cleanly based on the current environment.
 */
function emusaef_bootstrap() {
	// Use the constant you defined in the main file
	$inc_dir = EMUSAEF_PATH . 'inc/';

	// 1. Core Helpers (Must load first)
	require_once $inc_dir . 'helpers.php';

	// 2. Global Business Logic
	require_once $inc_dir . 'product-meta.php';
	require_once $inc_dir . 'store-location.php';
	require_once $inc_dir . 'checkout.php';
	require_once $inc_dir . 'fees.php';
	require_once $inc_dir . 'product-content.php';
	require_once $inc_dir . 'compatibility.php';

	// Security & Auth Modules
	require_once $inc_dir . 'security.php';
	require_once $inc_dir . 'otp-core.php';
	require_once $inc_dir . 'checkout-auth.php';
	
	// 3. Admin-only Logic
	if ( is_admin() ) {
		require_once $inc_dir . 'admin.php';
		
		// Only load migration file into memory if actively requesting the migration
		//if ( isset( $_GET['blocksy_migrate_swatches'] ) ) {
		//	require_once $inc_dir . 'migration.php';
		//}
	}

	// 4. Developer Tools
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		require_once $inc_dir . 'dev.php';
	}
}
// Hooking to plugins_loaded ensures WC and WP Core are ready before files are required
add_action( 'plugins_loaded', 'emusaef_bootstrap' );
