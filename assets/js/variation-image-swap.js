/**
 * Swap the shop/archive loop product image to match the selected
 * variation swatch, instead of showing the featured image.
 *
 * Listens to WooCommerce's native `found_variation` / `reset_data`
 * events on `.variations_form`, so it works with Blocksy's button,
 * colour, or image swatches without depending on Blocksy's internal
 * markup or class names.
 *
 * @package EMotoUSAExtended
 */
( function ( $ ) {
	'use strict';

	function getCardImage( $form ) {
		var $card = $form.closest( '.product, li.product' );
		if ( ! $card.length ) {
			return $();
		}
		return $card.find( 'img.wp-post-image, img.attachment-woocommerce_thumbnail' ).first();
	}

	$( document.body ).on( 'found_variation.wc-variation-form', 'form.variations_form', function ( event, variation ) {
		if ( ! variation || ! variation.image || ! variation.image.src ) {
			return;
		}

		var $img = getCardImage( $( this ) );
		if ( ! $img.length ) {
			return;
		}

		if ( ! $img.data( 'mcmp-original-src' ) ) {
			$img.data( 'mcmp-original-src', $img.attr( 'src' ) );
			$img.data( 'mcmp-original-srcset', $img.attr( 'srcset' ) || '' );
			$img.data( 'mcmp-original-sizes', $img.attr( 'sizes' ) || '' );
		}

		$img.attr( 'src', variation.image.src );

		if ( variation.image.srcset ) {
			$img.attr( 'srcset', variation.image.srcset );
		}
		if ( variation.image.sizes ) {
			$img.attr( 'sizes', variation.image.sizes );
		}
	} );

	$( document.body ).on( 'reset_data.wc-variation-form', 'form.variations_form', function () {
		var $img = getCardImage( $( this ) );
		if ( ! $img.length || ! $img.data( 'mcmp-original-src' ) ) {
			return;
		}

		$img.attr( 'src', $img.data( 'mcmp-original-src' ) );

		if ( $img.data( 'mcmp-original-srcset' ) ) {
			$img.attr( 'srcset', $img.data( 'mcmp-original-srcset' ) );
		}
		if ( $img.data( 'mcmp-original-sizes' ) ) {
			$img.attr( 'sizes', $img.data( 'mcmp-original-sizes' ) );
		}
	} );
} )( jQuery );
