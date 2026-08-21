<?php
/**
 * Swap the shop/archive loop product image to match the selected
 * variation swatch (Blocksy), instead of falling back to the
 * product's featured image.
 *
 * @package EMotoUSAExtended
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue the loop variation-image swap script on shop/archive/taxonomy pages.
 * Relies on WooCommerce's native `found_variation` / `reset_data` events fired
 * on `.variations_form`, so it works regardless of Blocksy's swatch markup
 * (buttons, colour, or image swatches) since Blocksy builds on top of WC's
 * core variation form fields rather than replacing the event system.
 */
function mcmp_enqueue_variation_image_swap_script() {
	if ( ! ( is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy() ) ) {
		return;
	}

	wp_enqueue_script(
		'mcmp-variation-image-swap',
		EMUSAEF_URL . 'assets/js/variation-image-swap.js',
		array( 'jquery', 'wc-add-to-cart-variation' ),
		EMUSAEF_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'mcmp_enqueue_variation_image_swap_script' );
