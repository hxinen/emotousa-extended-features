/**
 * E-MotoUSA Extended Features - Checkout OTP Logic
 * Refactored for Single-Input UI and performance.
 */
/* global EOW, jQuery */
(function ($) {
	'use strict';

	// Cache DOM nodes
	let $overlay, $stepEmail, $stepOtp, $emailInput, $sendBtn, $verifyBtn, $resendBtn, $backBtn, $visualOtp;

	function initRefs() {
		$overlay    = $('#eow-overlay');
		$stepEmail  = $('#eow-step-email');
		$stepOtp    = $('#eow-step-otp');
		$emailInput = $('#eow-email-input');
		$sendBtn    = $('#eow-send-btn');
		$verifyBtn  = $('#eow-verify-btn');
		$resendBtn  = $('#eow-resend-btn');
		$backBtn    = $('#eow-back-btn');
		$visualOtp  = $('#mcmp-visual-otp');
	}

	function showError(selector, msg) { $(selector).text(msg || EOW.i18n.generic_err); }
	function clearError(selector)     { $(selector).text(''); }
	function setBtnState($btn, label) { $btn.text(label).prop('disabled', /…/.test(label)); }

	function openOverlay() {
		$overlay.attr('aria-hidden', 'false').addClass('is-open');
		$('body').addClass('eow-locked');
	}

	function closeOverlay() {
		$overlay.attr('aria-hidden', 'true').removeClass('is-open');
		$('body').removeClass('eow-locked');
	}

	function showStep(step) {
		$stepEmail.hide();
		$stepOtp.hide();
		if (step === 'email') {
			$stepEmail.show();
			setTimeout(() => $emailInput.trigger('focus'), 60);
		} else {
			$stepOtp.show();
			setTimeout(() => $visualOtp.trigger('focus'), 60);
		}
	}

	function startCooldown(seconds) {
		let remaining = parseInt(seconds, 10);
		if (remaining <= 0) return;

		$resendBtn.prop('disabled', true);
		let origText = EOW.i18n.resending.replace('…', ''); // Base resend text
		
		let tick = setInterval(() => {
			remaining--;
			$resendBtn.text(`${origText} (${remaining}s)`);
			if (remaining <= 0) {
				clearInterval(tick);
				$resendBtn.text("Didn't receive the code? Resend").prop('disabled', false);
			}
		}, 1000);
		
		$resendBtn.text(`${origText} (${remaining}s)`);
	}

	function sendCheckoutOtp(targetEmail) {
		let origLabel = $sendBtn.data('orig') || $sendBtn.text();
		$sendBtn.data('orig', origLabel);
		setBtnState($sendBtn, EOW.i18n.sending);
		
		// If resending from step 2, show loading on that button instead
		if ($stepOtp.is(':visible')) {
			setBtnState($resendBtn, EOW.i18n.sending);
		}

		$.post(EOW.ajax, { action: 'eow_send_checkout_otp', email: targetEmail, nonce: EOW.nonce })
			.done(function (r) {
				if (r && r.success) {
					$('#eow-otp-subtext').text(targetEmail);
					$visualOtp.val(''); // Clear old code
					showStep('otp');
					if (r.data.cooldown) { startCooldown(r.data.cooldown); }
				} else {
					let msg = (r && r.data && r.data.msg) || EOW.i18n.generic_err;
					showError($stepOtp.is(':visible') ? '#eow-otp-error' : '#eow-email-error', msg);
				}
			})
			.fail(function () {
				showError($stepOtp.is(':visible') ? '#eow-otp-error' : '#eow-email-error', EOW.i18n.generic_err);
			})
			.always(function () {
				setBtnState($sendBtn, origLabel);
			});
	}

	function verifyCheckoutOtp(email, code) {
		let origLabel = $verifyBtn.data('orig') || $verifyBtn.text();
		$verifyBtn.data('orig', origLabel);
		setBtnState($verifyBtn, EOW.i18n.verifying);
		clearError('#eow-otp-error');

		$.post(EOW.ajax, { action: 'eow_verify_checkout_otp', email: email, otp: code, nonce: EOW.nonce })
			.done(function (r) {
				if (r && r.success) {
					EOW.checkout_verified = '1';
					// Auto-fill billing email quietly
					let $billingEmail = $('#billing_email');
					if ($billingEmail.length && !$billingEmail.val()) { 
						$billingEmail.val(email).trigger('change'); 
					}
					closeOverlay();
				} else {
					let msg = (r && r.data && r.data.msg) || EOW.i18n.generic_err;
					showError('#eow-otp-error', msg);
					$visualOtp.val('').trigger('focus'); // Clear and refocus on error
				}
			})
			.fail(function () {
				showError('#eow-otp-error', EOW.i18n.generic_err);
			})
			.always(function () {
				setBtnState($verifyBtn, origLabel);
			});
	}

	function gateCheckout() {
		if (EOW.is_checkout !== '1') return;
		if (!$overlay.length) return;
		if (EOW.checkout_verified === '1') return;

		openOverlay();

		if (EOW.logged_in === '1') {
			sendCheckoutOtp(EOW.user_email);
		} else {
			showStep('email');
		}

		/* Event Listeners */
		$sendBtn.on('click', function (e) {
			e.preventDefault();
			clearError('#eow-email-error');
			let email = $emailInput.val().trim();
			if (!email || !/\S+@\S+\.\S+/.test(email)) {
				showError('#eow-email-error', EOW.i18n.email_req);
				return;
			}
			sendCheckoutOtp(email);
		});

		$resendBtn.on('click', function(e) {
			e.preventDefault();
			let email = (EOW.logged_in === '1') ? EOW.user_email : $emailInput.val().trim();
			sendCheckoutOtp(email);
		});

		$verifyBtn.on('click', function (e) {
			e.preventDefault();
			let code = $visualOtp.val().trim();
			let email = (EOW.logged_in === '1') ? EOW.user_email : $emailInput.val().trim();
			
			if (code.length < parseInt(EOW.otp_len, 10)) {
				showError('#eow-otp-error', EOW.i18n.code_req);
				return;
			}
			verifyCheckoutOtp(email, code);
		});

		$backBtn.on('click', function (e) {
			e.preventDefault();
			showStep('email');
		});

		// Auto-verify when 6th digit is typed
		$visualOtp.on('input', function() {
			let val = $(this).val().replace(/[^0-9]/g, '');
			$(this).val(val); // Strict numeric filter
			
			if (val.length === parseInt(EOW.otp_len, 10)) {
				$verifyBtn.trigger('click');
			}
		});
	}

	$(function () {
		initRefs();
		gateCheckout();
	});

}(jQuery));