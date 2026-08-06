<?php
/**
 * Checkout OTP Verification Module.
 * Hooks into WooCommerce checkout to enforce email validation.
 * 
 * @package EMotoUSAExtended
 */

defined( 'ABSPATH' ) || exit;



/**
 * Handle AJAX request to generate and send the checkout OTP.
 */
function mcmp_ajax_send_checkout_otp() {
	check_ajax_referer( 'eow_nonce', 'nonce' );

	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'msg' => __( 'Invalid email address.', 'emotousa-extended-features' ) ) );
	}

	// generate_otp() handles IP locks, Rate Limiting, and Transient Creation.
	$result = mcmp_generate_otp( $email, 'checkout' );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'msg' => $result->get_error_message() ) );
	}

	if ( ! mcmp_send_otp_email( $email, $result, 'checkout' ) ) {
		mcmp_log_otp_event( 'send_fail', 'checkout', $email, 'wp_mail() rejected payload.' );
		wp_send_json_error( array( 'msg' => __( 'Server failed to send email. Please contact support.', 'emotousa-extended-features' ) ) );
	}

	$config = mcmp_get_otp_config();
	wp_send_json_success( array(
		'msg'      => __( 'Code sent! Check your inbox.', 'emotousa-extended-features' ),
		'cooldown' => $config['resend_cooldown'],
	) );
}
add_action( 'wp_ajax_eow_send_checkout_otp', 'mcmp_ajax_send_checkout_otp' );
add_action( 'wp_ajax_nopriv_eow_send_checkout_otp', 'mcmp_ajax_send_checkout_otp' );

/**
 * Handle AJAX request to verify the user's inputted code.
 */
function mcmp_ajax_verify_checkout_otp() {
	check_ajax_referer( 'eow_nonce', 'nonce' );

	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$code  = sanitize_text_field( wp_unslash( $_POST['otp'] ?? '' ) );

	$result = mcmp_verify_otp( $email, 'checkout', $code );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'msg' => $result->get_error_message() ) );
	}

	// Flag the WC session as successfully verified
	if ( function_exists( 'WC' ) && WC()->session ) {
		WC()->session->set( 'eow_verified', true );
		WC()->session->set( 'eow_verified_email', $email );
	}

	wp_send_json_success( array( 'msg' => __( 'Email verified successfully!', 'emotousa-extended-features' ) ) );
}
add_action( 'wp_ajax_eow_verify_checkout_otp', 'mcmp_ajax_verify_checkout_otp' );
add_action( 'wp_ajax_nopriv_eow_verify_checkout_otp', 'mcmp_ajax_verify_checkout_otp' );

/**
 * Stop WooCommerce from processing the order if the session is not verified.
 */
function mcmp_validate_checkout_session() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) { 
		return; 
	}
	
	if ( ! WC()->session->get( 'eow_verified' ) ) {
		wc_add_notice( __( 'Security Check: Please verify your email via OTP before placing your order.', 'emotousa-extended-features' ), 'error' );
	}
}
add_action( 'woocommerce_checkout_process', 'mcmp_validate_checkout_session' );


/**
 * Save OTP verification proof to order meta and clear the checkout session.
 * HPOS-compatible.
 *
 * @param int $order_id Order ID.
 */
function mcmp_clear_checkout_session_and_save_proof( $order_id ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	$email = WC()->session->get( 'eow_verified_email' );

	if ( $order_id && $email ) {
		$order = wc_get_order( $order_id );

		if ( $order ) {
			$order->update_meta_data( '_eow_verified_email', sanitize_email( $email ) );
			$order->update_meta_data( '_eow_verified_at', current_time( 'mysql' ) );
			$order->update_meta_data( '_eow_verified_ip', mcmp_get_client_ip() );
			$order->save();
		}
	}

	WC()->session->set( 'eow_verified', null );
	WC()->session->set( 'eow_verified_email', null );
}
add_action( 'woocommerce_checkout_order_processed', 'mcmp_clear_checkout_session_and_save_proof', 10, 1 );

/**
 * Add a "Chargeback Evidence" badge to the backend WooCommerce Order view.
 */
function mcmp_display_otp_verification_on_order( $order ) {
	$verified_email = $order->get_meta( '_eow_verified_email' );
	$verified_time  = $order->get_meta( '_eow_verified_at' );
	$verified_ip    = $order->get_meta( '_eow_verified_ip' );

	if ( ! $verified_email ) {
		return;
	}

	echo '<div class="order_data_column" style="width:100%;margin-top:20px;">';
	echo '<h4>' . esc_html__( 'Security & Fraud Prevention', 'emotousa-extended-features' ) . '</h4>';
	echo '<p style="color: green; font-weight: bold;">✓ Email Authenticated (OTP)</p>';
	echo '<p><strong>' . esc_html__( 'Verified Email:', 'emotousa-extended-features' ) . '</strong> ' . esc_html( $verified_email ) . '<br>';
	echo '<strong>' . esc_html__( 'Timestamp:', 'emotousa-extended-features' ) . '</strong> ' . esc_html( $verified_time ) . '<br>';
	echo '<strong>' . esc_html__( 'Client IP:', 'emotousa-extended-features' ) . '</strong> ' . esc_html( $verified_ip ) . '</p>';
	echo '</div>';
}
add_action( 'woocommerce_admin_order_data_after_order_details', 'mcmp_display_otp_verification_on_order' );

/**
 * Render the OTP Verification Modal on the Checkout Page.
 * UX optimized for mobile auto-fill and proactive error prevention.
 */
 add_action( 'woocommerce_after_checkout_form', 'mcmp_render_checkout_overlay' );
function mcmp_render_checkout_overlay() {
	if ( ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) { 
		return; 
	}
	
	$config    = mcmp_get_otp_config();
	if ( ! $config['enable_checkout'] ) {
		return;
	}

	$logged_in  = is_user_logged_in();
	$user_email = $logged_in ? esc_attr( wp_get_current_user()->user_email ) : '';
	$otp_len    = $config['otp_length'];
	$expiry     = $config['otp_expiry_mins'];
	?>
	<div id="eow-overlay" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="eow-title">
		<div class="eow-modal">
			
			<!-- Step 1: Email Entry -->
			<div class="eow-step" id="eow-step-email" <?php echo $logged_in ? 'style="display:none"' : ''; ?>>
				<div class="eow-icon">✉️</div>
				<h2 id="eow-title" class="eow-heading"><?php esc_html_e( 'Verify your email', 'emotousa-extended-features' ); ?></h2>
				<p class="eow-subtext"><?php esc_html_e( 'Enter your email address to receive a one-time verification code.', 'emotousa-extended-features' ); ?></p>
				<div class="eow-input-row">
					<input type="email" id="eow-email-input" autocomplete="email" placeholder="<?php esc_attr_e( 'your@email.com', 'emotousa-extended-features' ); ?>" value="<?php echo $user_email; ?>">
				</div>
				<div class="eow-error" id="eow-email-error"></div>
				<button type="button" class="eow-btn eow-btn-primary" id="eow-send-btn"><?php esc_html_e( 'Send Code', 'emotousa-extended-features' ); ?></button>
			</div>

			<!-- Step 2: OTP Entry (Highly Optimized UX) -->
			<div class="eow-step" id="eow-step-otp" style="display:none;">
				<div class="eow-icon">🔐</div>
				<h2 class="eow-heading"><?php esc_html_e( 'Verify Your Email', 'emotousa-extended-features' ); ?></h2>
				
				<!-- Dynamic Email Display -->
				<p class="eow-subtext" style="margin-bottom: 5px;"><?php esc_html_e( "We've sent a {$otp_len}-digit verification code to:", 'emotousa-extended-features' ); ?></p>
				<p id="eow-otp-subtext" style="color: #00b894; font-weight: bold; font-size: 16px; margin-top: 0; margin-bottom: 20px;">
					<!-- JS will inject the target email here -->
				</p>
				
				<!-- Hidden individual digits to satisfy the old JS logic -->
				<div id="eow-digits" style="display:none;">
					<?php for ( $i = 0; $i < $otp_len; $i++ ) : ?>
						<input type="hidden" class="eow-digit">
					<?php endfor; ?>
				</div>

				<!-- Single visual input for flawless mobile auto-fill -->
				<div class="eow-input-row" style="justify-content:center; margin-bottom: 8px;">
					<input type="text" id="mcmp-visual-otp" inputmode="numeric" pattern="[0-9]*" maxlength="<?php echo esc_attr( $otp_len ); ?>" autocomplete="one-time-code" placeholder="000000" style="width: 100%; text-align: center; letter-spacing: 12px; font-size: 24px; font-weight: bold; padding: 12px; border: 2px solid #00b894; border-radius: 8px; transition: all 0.3s ease;">
				</div>
				
				<!-- Expiry Warning -->
				<p style="font-size: 12px; color: #a0aec0; text-align: center; margin-top: 0; margin-bottom: 20px; font-style: italic;">
					<?php printf( esc_html__( 'Code expires in %d minutes', 'emotousa-extended-features' ), $expiry ); ?>
				</p>
				
				<div class="eow-error" id="eow-otp-error"></div>
				
				<!-- Main Action Button -->
				<button type="button" class="eow-btn eow-btn-primary" id="eow-verify-btn" style="width: 100%; font-size: 16px; padding: 14px;"><?php esc_html_e( 'Verify Email ❯', 'emotousa-extended-features' ); ?></button>
				
				<!-- Resend Link -->
				<div style="text-align: center; margin-top: 15px;">
					<button type="button" id="eow-resend-btn" style="background: none; border: none; color: #00b894; cursor: pointer; font-size: 14px; text-decoration: underline;" disabled><?php esc_html_e( "Didn't receive the code? Resend", 'emotousa-extended-features' ); ?></button>
				</div>
				
				<?php if ( ! $logged_in ) : ?>
					<div class="eow-footer-link" style="margin-top:5px; text-align:center;">
						<a href="#" id="eow-back-btn" style="color: #636e72; font-size: 13px;"><?php esc_html_e( 'Change email address', 'emotousa-extended-features' ); ?></a>
					</div>
				<?php endif; ?>

				<!-- Spam Folder Warning Banner -->
				<div style="background-color: #fff9c4; border: 1px solid #fdcb6e; border-radius: 8px; padding: 15px; margin-top: 25px; text-align: center;">
					<div style="font-size: 20px; margin-bottom: 5px;">📁</div>
					<strong style="color: #b33939; font-size: 14px; display: block; margin-bottom: 5px;"><?php esc_html_e( 'Can\'t find the email?', 'emotousa-extended-features' ); ?></strong>
					<p style="color: #2d3436; font-size: 13px; margin: 0; line-height: 1.4;">
						<?php esc_html_e( 'Check your ', 'emotousa-extended-features' ); ?><strong><?php esc_html_e( 'Spam', 'emotousa-extended-features' ); ?></strong><?php esc_html_e( ' or ', 'emotousa-extended-features' ); ?><strong><?php esc_html_e( 'Junk', 'emotousa-extended-features' ); ?></strong><?php esc_html_e( ' folder. Sometimes our emails land there by mistake.', 'emotousa-extended-features' ); ?>
					</p>
				</div>
			</div>

		</div>
	</div>
	
	<!-- Micro-script to sync our new beautiful single input with the old JS logic -->
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const visualInput = document.getElementById('mcmp-visual-otp');
			const hiddenInputs = document.querySelectorAll('#eow-digits .eow-digit');
			
			if(visualInput && hiddenInputs.length > 0) {
				visualInput.addEventListener('input', function(e) {
					// Strip non-numbers
					let val = this.value.replace(/[^0-9]/g, '');
					this.value = val;
					
					// Map each number typed into the hidden inputs so eow-otp.js can read them
					for(let i = 0; i < hiddenInputs.length; i++) {
						hiddenInputs[i].value = val.charAt(i) || '';
					}
				});
			}
		});
	</script>
	<?php
}

/**
 * Enqueue frontend CSS/JS only on WooCommerce pages to save bandwidth.
 */
function mcmp_enqueue_checkout_otp_assets() {
	if ( ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}

	$config    = mcmp_get_otp_config();
	$logged_in = is_user_logged_in();
	$verified  = ( function_exists( 'WC' ) && WC()->session && WC()->session->get( 'eow_verified' ) ) ? '1' : '0';

	// Assuming you place your provided CSS/JS files into the assets folder of this plugin
	wp_enqueue_style( 'emotousa-otp-frontend', EMUSAEF_URL . 'assets/css/eow-frontend.css', array(), EMUSAEF_VERSION );
	wp_enqueue_script( 'emotousa-otp-frontend', EMUSAEF_URL . 'assets/js/eow-otp.js', array( 'jquery' ), EMUSAEF_VERSION, true );

	wp_localize_script( 'emotousa-otp-frontend', 'EOW', array(
		'ajax'              => admin_url( 'admin-ajax.php' ),
		'nonce'             => wp_create_nonce( 'eow_nonce' ),
		'is_checkout'       => '1',
		'checkout_verified' => $verified,
		'logged_in'         => $logged_in ? '1' : '0',
		'user_email'        => $logged_in ? wp_get_current_user()->user_email : '',
		'otp_len'           => $config['otp_length'],
		'i18n'              => array(
			'sending'            => __( 'Sending…', 'emotousa-extended-features' ),
			'resending'          => __( 'Resending…', 'emotousa-extended-features' ),
			'verifying'          => __( 'Verifying…', 'emotousa-extended-features' ),
			'email_req'          => __( 'Please enter a valid email address.', 'emotousa-extended-features' ),
			'code_req'           => __( 'Please enter the complete verification code.', 'emotousa-extended-features' ),
			'generic_err'        => __( 'Something went wrong. Please try again.', 'emotousa-extended-features' ),
		),
	) );
}
add_action( 'wp_enqueue_scripts', 'mcmp_enqueue_checkout_otp_assets' );