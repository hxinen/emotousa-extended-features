<?php
/**
 * Core security engine for Email OTP generation, rate limiting, and IP locking.
 * Replaces the monolithic EOW_Core class with lean procedural functions.
 * 
 * @package EMotoUSAExtended
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get the cleanest possible IP address for the current request.
 * Returns false if the IP is empty or unresolvable (Ghost IP).
 *
 * @return string|false
 */
function mcmp_get_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return empty( $ip ) || 'unknown' === strtolower( $ip ) ? false : $ip;
}

/**
 * Normalize an email address for rate limiting by stripping out +aliases.
 * e.g. "john+spam@gmail.com" becomes "john@gmail.com"
 *
 * @param string $email Raw email address.
 * @return string Normalized email.
 */
function mcmp_normalize_email_for_limits( $email ) {
	$email = strtolower( trim( $email ) );
	if ( strpos( $email, '+' ) !== false ) {
		list( $local, $domain ) = explode( '@', $email, 2 );
		$local = preg_replace( '/\+.*$/', '', $local );
		$email = $local . '@' . $domain;
	}
	return $email;
}

/**
 * Log OTP security events to the native WooCommerce logger.
 * Faster and safer than custom SQL tables.
 *
 * @param string $event   The event type (e.g., 'send_success', 'rate_limited', 'ip_locked').
 * @param string $purpose Where this occurred (checkout, login, register).
 * @param string $email   The email involved.
 * @param string $message Additional context.
 */
function mcmp_log_otp_event( $event, $purpose, $email, $message = '' ) {
	if ( ! function_exists( 'wc_get_logger' ) ) {
		return;
	}
	$logger = wc_get_logger();
	$ip     = mcmp_get_client_ip() ?: 'Ghost IP';
	$log    = sprintf( '[%s] Purpose: %s | Email: %s | IP: %s | Context: %s', strtoupper( $event ), $purpose, $email, $ip, $message );
	
	// Write to the woo-commerce standard log file "mcmp-otp-security"
	$logger->log( 'info', $log, array( 'source' => 'mcmp-otp-security' ) );
}

/**
 * Check if the current IP address is temporarily banned due to too many failures.
 *
 * @return int|false Seconds remaining on the lock, or false if not locked.
 */
function mcmp_is_ip_locked() {
	$ip = mcmp_get_client_ip();
	if ( ! $ip ) {
		return 3600; // Ghost IPs are permanently locked from this system.
	}

	$lock_key = 'mcmp_iplock_' . md5( $ip );
	$data     = get_transient( $lock_key );

	if ( ! $data || empty( $data['locked'] ) ) {
		return false;
	}

	$remaining = ( $data['until'] ?? 0 ) - time();
	return $remaining > 0 ? $remaining : false;
}

/**
 * Record a strike against an IP. If strikes exceed the limit, lock the IP.
 */
function mcmp_record_ip_strike() {
	$ip = mcmp_get_client_ip();
	if ( ! $ip ) {
		return;
	}

	$config   = mcmp_get_otp_config();
	$lock_key = 'mcmp_iplock_' . md5( $ip );
	$data     = get_transient( $lock_key ) ?: array( 'strikes' => 0, 'locked' => false, 'until' => 0 );

	if ( ! $data['locked'] ) {
		$data['strikes']++;
		if ( $data['strikes'] >= $config['ip_strike_limit'] ) {
			$data['locked'] = true;
			$data['until']  = time() + ( $config['ip_lock_mins'] * MINUTE_IN_SECONDS );
			mcmp_log_otp_event( 'ip_locked', 'security', 'N/A', 'Exceeded strike limit.' );
		}
		set_transient( $lock_key, $data, $config['ip_lock_mins'] * MINUTE_IN_SECONDS );
	}
}

/**
 * Generate a cryptographically secure numeric OTP and save it to a transient.
 *
 * @param string $email   The requested email.
 * @param string $purpose The flow (checkout, login).
 * @return string|WP_Error The OTP string, or WP_Error if rate limited.
 */
function mcmp_generate_otp( $email, $purpose ) {
	$ip = mcmp_get_client_ip();
	if ( ! $ip ) {
		return new WP_Error( 'ghost_ip', __( 'Security violation: Unresolvable IP address.', 'emotousa-extended-features' ) );
	}

	if ( $lock_time = mcmp_is_ip_locked() ) {
		return new WP_Error( 'ip_locked', sprintf( __( 'Too many attempts. Your IP is locked for %d minutes.', 'emotousa-extended-features' ), ceil( $lock_time / 60 ) ) );
	}

	$config    = mcmp_get_otp_config();
	$norm_mail = mcmp_normalize_email_for_limits( $email );
	$rate_key  = 'mcmp_rate_' . md5( $norm_mail . '|' . $purpose . '|' . $ip );
	$otp_key   = 'mcmp_otp_' . md5( $email . '|' . $purpose );

	// Rate Limit Check
	$rate_data = get_transient( $rate_key ) ?: array( 'count' => 0, 'last_sent' => 0 );
	
	if ( time() - $rate_data['last_sent'] < $config['resend_cooldown'] ) {
		return new WP_Error( 'cooldown', __( 'Please wait before requesting another code.', 'emotousa-extended-features' ) );
	}

	if ( $rate_data['count'] >= $config['max_resends'] ) {
		mcmp_record_ip_strike();
		mcmp_log_otp_event( 'rate_limited', $purpose, $email, 'Exceeded max resends.' );
		return new WP_Error( 'rate_limit', __( 'Maximum attempts reached for this window. Try again later.', 'emotousa-extended-features' ) );
	}

	// Generate mathematically secure OTP
	$otp = wp_rand( (int) pow( 10, $config['otp_length'] - 1 ), (int) pow( 10, $config['otp_length'] ) - 1 );

	// Save to Transient (Expires automatically)
	$otp_data = array(
		'code'     => $otp,
		'email'    => $email,
		'attempts' => 0,
		'expires'  => time() + ( $config['otp_expiry_mins'] * MINUTE_IN_SECONDS ),
	);
	set_transient( $otp_key, $otp_data, $config['otp_expiry_mins'] * MINUTE_IN_SECONDS );

	// Update Rate Limits
	$rate_data['count']++;
	$rate_data['last_sent'] = time();
	set_transient( $rate_key, $rate_data, 600 ); // Hardcoded 10 minute rolling window

	mcmp_log_otp_event( 'otp_generated', $purpose, $email );

	return (string) $otp;
}

/**
 * Verify an entered OTP against the saved transient.
 *
 * @param string $email   The email being verified.
 * @param string $purpose The flow (checkout, login).
 * @param string $code    The user's inputted code.
 * @return bool|WP_Error True if valid, WP_Error if invalid/expired.
 */
function mcmp_verify_otp( $email, $purpose, $code ) {
	if ( mcmp_is_ip_locked() ) {
		return new WP_Error( 'ip_locked', __( 'Your IP is currently locked.', 'emotousa-extended-features' ) );
	}

	$config  = mcmp_get_otp_config();
	$otp_key = 'mcmp_otp_' . md5( $email . '|' . $purpose );
	$data    = get_transient( $otp_key );

	if ( ! $data || time() > $data['expires'] || strtolower( trim( $email ) ) !== strtolower( trim( $data['email'] ) ) ) {
		return new WP_Error( 'expired', __( 'Code has expired or is invalid. Please request a new one.', 'emotousa-extended-features' ) );
	}

	if ( (string) $code === (string) $data['code'] ) {
		delete_transient( $otp_key ); // Destroy it immediately after successful use
		mcmp_log_otp_event( 'verify_success', $purpose, $email );
		return true;
	}

	// Failed Guess
	$data['attempts']++;
	mcmp_record_ip_strike();

	if ( $data['attempts'] >= $config['max_guesses'] ) {
		delete_transient( $otp_key ); // Kill it completely
		mcmp_log_otp_event( 'verify_failed_max', $purpose, $email, 'Max guesses reached. OTP destroyed.' );
		return new WP_Error( 'max_guesses', __( 'Too many incorrect attempts. Please request a new code.', 'emotousa-extended-features' ) );
	}

	set_transient( $otp_key, $data, $data['expires'] - time() );
	$remaining = $config['max_guesses'] - $data['attempts'];
	
	mcmp_log_otp_event( 'verify_failed', $purpose, $email, 'Wrong code entered.' );
	return new WP_Error( 'wrong_code', sprintf( __( 'Incorrect code. %d attempt(s) remaining.', 'emotousa-extended-features' ), $remaining ) );
}

/**
 * Send the HTML OTP email to the user.
 *
 * @param string $email   The recipient's email.
 * @param string $otp     The numeric code.
 * @param string $purpose The flow context (checkout, login).
 * @return bool True if mail was accepted for delivery.
 */
function mcmp_send_otp_email( $email, $otp, $purpose ) {
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subj = sprintf( '[%s] Your %s verification code', $site, ucfirst( $purpose ) );

	$digit_html = '';
	foreach ( str_split( (string) $otp ) as $d ) {
		$digit_html .= '<span style="display:inline-block;width:40px;height:48px;line-height:48px;text-align:center;border:2px solid #d1d5db;border-radius:8px;font-size:24px;font-weight:700;margin:0 4px;color:#1e3a8a;">' . esc_html( $d ) . '</span>';
	}

	$message = '
	<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;max-width:520px;margin:0 auto;background:#ffffff;padding:40px;border-radius:16px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
		<h1 style="color:#111827;font-size:24px;margin:0 0 16px 0;text-align:center;">Verify Your Request</h1>
		<p style="color:#4b5563;font-size:16px;line-height:24px;margin:0 0 32px 0;text-align:center;">Enter the code below to complete your ' . esc_html( $purpose ) . '.</p>
		<div style="text-align:center;margin-bottom:32px;">' . $digit_html . '</div>
		<p style="color:#6b7280;font-size:13px;text-align:center;margin:0;">This code expires in 10 minutes. If you did not request this, you can safely ignore this email.</p>
	</div>';

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	return wp_mail( $email, $subj, $message, $headers );
}