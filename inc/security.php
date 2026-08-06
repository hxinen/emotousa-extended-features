<?php
/**
 * E-MotoUSA: Defender IP-blocklist integration.
 *
 * - Adds a manual "Ban IP via Defender" order action.
 * - Blocks checkout for explicitly listed confirmed-fraud emails.
 * - Compatible with HPOS and legacy order storage.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Check whether Defender's IP blocklist action is available.
 *
 * @return bool
 */
function emoto_defender_can_blacklist_ips() {
	return (bool) has_action( 'wd_blacklist_this_ip' );
}

/**
 * Validate and block an IP address with Defender.
 *
 * @param string $ip IP address.
 * @return bool True when the blocklist action was dispatched.
 */
function emoto_defender_blacklist_ip( $ip ) {
	$ip = trim( (string) $ip );

	if ( ! emoto_defender_can_blacklist_ips() || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return false;
	}

	/**
	 * Important: Verify Defender/WordPress is receiving actual visitor IPs
	 * before using automatic blocks behind Cloudflare or another proxy.
	 */
	do_action( 'wd_blacklist_this_ip', $ip );

	return true;
}

/**
 * Add a manual Defender IP-ban action to the WooCommerce order editor.
 *
 * @param array $actions Order actions.
 * @return array
 */
function emoto_add_defender_ban_ip_action( $actions ) {
	if ( emoto_defender_can_blacklist_ips() ) {
		$actions['emoto_ban_ip_defender'] = __( 'Ban IP via Defender', 'emotousa-extended-features' );
	}

	return $actions;
}
add_filter( 'woocommerce_order_actions', 'emoto_add_defender_ban_ip_action' );

/**
 * Process manual Defender IP ban from an order action.
 *
 * @param WC_Order $order Order object.
 * @return void
 */
function emoto_process_defender_ban_ip( $order ) {
	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$ip_address = $order->get_customer_ip_address();

	if ( empty( $ip_address ) ) {
		$order->add_order_note(
			__( 'Defender IP ban was not applied: no customer IP was recorded for this order.', 'emotousa-extended-features' )
		);
		return;
	}

	if ( ! emoto_defender_can_blacklist_ips() ) {
		$order->add_order_note(
			__( 'Defender IP ban was not applied: Defender IP Banning is unavailable or inactive.', 'emotousa-extended-features' )
		);
		return;
	}

	if ( ! emoto_defender_blacklist_ip( $ip_address ) ) {
		$order->add_order_note(
			__( 'Defender IP ban was not applied: the recorded IP address is invalid.', 'emotousa-extended-features' )
		);
		return;
	}

	$order->add_order_note(
		sprintf(
			/* translators: %s: customer IP address. */
			__( 'Security: customer IP %s was submitted to the Defender blocklist.', 'emotousa-extended-features' ),
			$ip_address
		)
	);
}
add_action( 'woocommerce_order_action_emoto_ban_ip_defender', 'emoto_process_defender_ban_ip' );

/**
 * Block checkout for specifically confirmed fraudulent email addresses.
 *
 * This is for classic WooCommerce checkout only.
 *
 * @param array    $data   Submitted checkout data.
 * @param WP_Error $errors Checkout validation errors.
 * @return void
 */
function emoto_block_confirmed_fraud_email_at_checkout( $data, $errors ) {
	$banned_emails = array(
		'scammer1@example.com',
		'stolen_card_tester@gmail.com',
		'badguy5127@yahoo.com',
	);

	$banned_emails = array_map( 'sanitize_email', $banned_emails );
	$billing_email = isset( $data['billing_email'] )
		? sanitize_email( $data['billing_email'] )
		: '';

	if ( empty( $billing_email ) || ! in_array( $billing_email, $banned_emails, true ) ) {
		return;
	}

	$ip_address = WC_Geolocation::get_ip_address();

	if ( ! empty( $ip_address ) ) {
		emoto_defender_blacklist_ip( $ip_address );
	}

	$errors->add(
		'emoto_restricted_checkout',
		__( 'We cannot process this order. Please contact customer support if you believe this is an error.', 'emotousa-extended-features' )
	);
}
add_action( 'woocommerce_after_checkout_validation', 'emoto_block_confirmed_fraud_email_at_checkout', 10, 2 );