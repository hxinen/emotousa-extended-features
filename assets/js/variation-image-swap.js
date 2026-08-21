/**
 * Swap the shop/archive loop product image to match the selected
 * variation swatch, instead of showing the featured image.
 *
 * v2 — fixes two issues found in production:
 * 1. AJAX product filters inject new .variations_form markup that
 *    WooCommerce never auto-initializes, so `found_variation` never
 *    fires. We watch the DOM and call .wc_variation_form() on any
 *    uninitialized form that appears.
 * 2. Lazysizes-style lazy loading stores the real URL in data-src /
 *    data-srcset, not src. If we only touch src/srcset, the lazyload
 *    library overwrites our swap with the stale data-src once the
 *    image scrolls into view. We now update both attribute pairs.
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

	function setImageSrc( $img, src, srcset, sizes ) {
		$img.attr( 'src', src );
		if ( $img.attr( 'data-src' ) !== undefined ) {
			$img.attr( 'data-src', src );
		}

		if ( srcset ) {
			$img.attr( 'srcset', srcset );
			if ( $img.attr( 'data-srcset' ) !== undefined ) {
				$img.attr( 'data-srcset', srcset );
			}
		}

		if ( sizes ) {
			$img.attr( 'sizes', sizes );
		}

		// If lazysizes already unveiled this image (class "lazyloaded"),
		// force it to re-check so the new src actually paints immediately.
		if ( $img.hasClass( 'lazyloaded' ) || $img.hasClass( 'lazyload' ) ) {
			$img.removeClass( 'lazyloaded' ).addClass( 'lazyload' );
			if ( window.lazySizes && typeof window.lazySizes.loader !== 'undefined' ) {
				window.lazySizes.loader.unveil( $img.get( 0 ) );
			} else {
				$img.trigger( 'lazybeforeunveil' );
			}
		}
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
			$img.data( 'mcmp-original-src', $img.attr( 'data-src' ) || $img.attr( 'src' ) );
			$img.data( 'mcmp-original-srcset', $img.attr( 'data-srcset' ) || $img.attr( 'srcset' ) || '' );
			$img.data( 'mcmp-original-sizes', $img.attr( 'sizes' ) || '' );
		}

		setImageSrc( $img, variation.image.src, variation.image.srcset, variation.image.sizes );
	} );

	$( document.body ).on( 'reset_data.wc-variation-form', 'form.variations_form', function () {
		var $img = getCardImage( $( this ) );
		if ( ! $img.length || ! $img.data( 'mcmp-original-src' ) ) {
			return;
		}

		setImageSrc(
			$img,
			$img.data( 'mcmp-original-src' ),
			$img.data( 'mcmp-original-srcset' ),
			$img.data( 'mcmp-original-sizes' )
		);
	} );

	/**
	 * Re-initialize WooCommerce's variation form on any .variations_form
	 * injected after the initial page load (AJAX product filters,
	 * infinite scroll, etc.), so found_variation can fire on them.
	 */
	function initUninitializedForms( $context ) {
		$context.find( 'form.variations_form' ).each( function () {
			var $form = $( this );
			if ( $form.data( 'mcmp-wc-variation-form-init' ) ) {
				return;
			}
			if ( typeof $form.wc_variation_form === 'function' ) {
				$form.wc_variation_form();
				$form.data( 'mcmp-wc-variation-form-init', true );
			}
		} );
	}

	$( function () {
		initUninitializedForms( $( document ) );

		if ( ! window.MutationObserver ) {
			return;
		}

		var loopContainer = document.querySelector( 'ul.products, .products' ) || document.body;

		var observer = new MutationObserver( function ( mutations ) {
			for ( var i = 0; i < mutations.length; i++ ) {
				if ( mutations[ i ].addedNodes && mutations[ i ].addedNodes.length ) {
					initUninitializedForms( $( loopContainer ) );
					return;
				}
			}
		} );

		observer.observe( loopContainer, { childList: true, subtree: true } );
	} );
} )( jQuery );
