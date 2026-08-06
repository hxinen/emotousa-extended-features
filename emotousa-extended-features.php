<?php
/**
 * Plugin Name: E-MotoUSA Extended Features
 * Plugin URI:  https://e-motousa.com/
 * Description: Custom WooCommerce business logic, product enhancements, checkout rules, and admin tools for E-MotoUSA.
 * Version:     1.0.0
 * Author:      E-MotoUSA
 * Text Domain: emotousa-extended-features
 * 
 * @package EMotoUSAExtended
 */

defined( 'ABSPATH' ) || exit;

// Synced version numbers
define( 'EMUSAEF_VERSION', '1.0.0' );
define( 'EMUSAEF_FILE', __FILE__ );
define( 'EMUSAEF_PATH', plugin_dir_path( __FILE__ ) );
define( 'EMUSAEF_URL', plugin_dir_url( __FILE__ ) );
define( 'EMUSAEF_TEXT_DOMAIN', 'emotousa-extended-features' );

// Load the bootstrap file which contains the setup function
require_once EMUSAEF_PATH . 'inc/bootstrap.php';
