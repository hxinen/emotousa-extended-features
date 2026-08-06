<?php
/**
 * Frontend product display modifiers, tags, badges, and tabs.
 * 
 * @package EMotoUSAExtended
 */

defined( 'ABSPATH' ) || exit;

/**
 * ============================================================================
 * STORE LOCATION BADGES & TEXT
 * ============================================================================
 */

/**
 * Enqueue frontend styling for badges and tags.
 */
function mcmp_enqueue_frontend_styles() {
		wp_enqueue_style(
			'emotousa-extended-frontend',
			EMUSAEF_URL . 'assets/css/frontend.css',
			array(),
			EMUSAEF_VERSION
		);
}
add_action( 'wp_enqueue_scripts', 'mcmp_enqueue_frontend_styles' );

/**
 * Display store location badge on shop/archive loop.
 */
function mcmp_display_store_location_badge() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	if ( 'outofstock' === $product->get_stock_status() ) {
		echo '<span class="woocommerce"><span class="location-tag out-of-stock">' . esc_html__( 'Out of Stock', 'emotousa-extended' ) . '</span></span>';
		return;
	}

	$store_loc = mcmp_get_store_location( $product->get_id() );
	$config    = mcmp_get_store_location_config();
	$loc_data  = isset( $config[ $store_loc ] ) ? $config[ $store_loc ] : $config['In Maui Stock'];

	printf(
		'<span class="woocommerce"><span class="location-tag %s">%s</span></span>',
		esc_attr( $loc_data['badge_class'] ),
		esc_html( $loc_data['badge_text'] )
	);
}
add_action( 'woocommerce_before_shop_loop_item_title', 'mcmp_display_store_location_badge', 5 );

/**
 * Display store location tag on single product summary.
 */
function mcmp_display_store_location_tags() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	if ( 'outofstock' === $product->get_stock_status() ) {
		echo '<span class="stock-tag out-of-stock">' . esc_html__( 'Out of Stock', 'emotousa-extended' ) . '</span>';
		return;
	}

	$store_loc = '';
	if ( $product->is_type( 'variable' ) ) {
		// wc_get_product() uses WC's internal object cache — no extra DB queries after first load.
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation && $variation->get_stock_quantity() > 0 ) {
				$loc       = $variation->get_meta( '_store_location_field' );
				$store_loc = $loc;
				if ( 'In Maui Stock' === $loc ) {
					break; // Best possible priority; stop early.
				}
			}
		}
	} else {
		$store_loc = get_post_meta( $product->get_id(), '_store_location_field', true );
	}

	// Re-verify against helper to map the corrupted meta
	$store_loc = ( empty( $store_loc ) ) ? 'In Maui Stock' : $store_loc;
	$config    = mcmp_get_store_location_config();
	$loc_data  = isset( $config[ $store_loc ] ) ? $config[ $store_loc ] : $config['In Maui Stock'];

	// Output is pre-escaped in the helper array definition
	echo $loc_data['tag_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'woocommerce_single_product_summary', 'mcmp_display_store_location_tags', 6 );

/**
 * Customize "Add to Cart" button text based on store location.
 */
function mcmp_custom_add_to_cart_text( $text, $product ) {
	$store_loc = mcmp_get_store_location( $product->get_id() );
	$config    = mcmp_get_store_location_config();

	if ( isset( $config[ $store_loc ]['add_to_cart_text'] ) ) {
		return $config[ $store_loc ]['add_to_cart_text'];
	}

	return $text;
}
add_filter( 'woocommerce_product_single_add_to_cart_text', 'mcmp_custom_add_to_cart_text', 10, 2 );

/**
 * ============================================================================
 * PRODUCT TABS / VIDEO CONTENT
 * ============================================================================
 */

/**
 * Add a Video tab to the product page if an embedded YouTube URL is in the description.
 */
function mcmp_add_video_tab( $tabs ) {
	global $post;
	if ( ! $post ) {
		return $tabs;
	}
	if ( preg_match( '/\[embed\](.*?)\[\/embed\]/', $post->post_content, $matches ) ) {
		$tabs['video_tab'] = array(
			'title'    => __( 'Video', 'emotousa-extended' ),
			'priority' => 10,
			'callback' => 'mcmp_video_tab_content',
			'video_url'=> esc_url( $matches[1] ),
		);
	}
	return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'mcmp_add_video_tab' );

/**
 * Render video tab content with responsive 16:9 iframe.
 */
function mcmp_video_tab_content( $key, $tab ) {
	if ( empty( $tab['video_url'] ) ) {
		echo '<p>' . esc_html__( 'Video not available.', 'emotousa-extended' ) . '</p>';
		return;
	}
	$video_url = $tab['video_url'];
	$video_id  = '';
	if ( strpos( $video_url, 'youtu.be' ) !== false ) {
		$video_id = basename( wp_parse_url( $video_url, PHP_URL_PATH ) );
	} elseif ( strpos( $video_url, 'youtube.com' ) !== false ) {
		wp_parse_str( wp_parse_url( $video_url, PHP_URL_QUERY ), $query );
		$video_id = isset( $query['v'] ) ? $query['v'] : '';
	}

	if ( ! empty( $video_id ) ) {
		echo '<h3>' . esc_html__( 'Product Video', 'emotousa-extended' ) . '</h3>';
		echo '<div class="video-wrapper" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;">';
		printf(
			'<iframe style="position:absolute;top:0;left:0;width:100%%;height:100%%;" src="%s" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>',
			esc_url( 'https://www.youtube.com/embed/' . $video_id )
		);
		echo '</div>';
	} else {
		echo '<p>' . esc_html__( 'Video not available.', 'emotousa-extended' ) . '</p>';
	}
}

/**
 * Strip YouTube embeds from single-product descriptions.
 */
function mcmp_remove_youtube_from_description( $content ) {
	if ( ! is_product() ) {
		return $content;
	}
	$content = preg_replace( '/\[embed\]https?:\/\/(?:www\.)?(?:youtu\.be|youtube\.com)[^\[]*\[\/embed\]/i', '', $content );
	$content = preg_replace( '/^\s*https?:\/\/(?:www\.)?(?:youtu\.be\/\S+|youtube\.com\/watch\?\S+)\s*$/im', '', $content );
	$content = preg_replace( '/<iframe[^>]+src=["\']https?:\/\/(?:www\.)?youtube(?:-nocookie)?\.com\/embed.+?<\/iframe>/is', '', $content );
	return $content;
}
add_filter( 'the_content', 'mcmp_remove_youtube_from_description', 1 );

/**
 * ============================================================================
 * PRODUCT FAQ DISPLAY
 * ============================================================================
 */

/**
 * Display product FAQs from _product_faqs meta.
 */
function mcmp_display_product_faqs( $product_id ) {
	$faqs = get_post_meta( $product_id, '_product_faqs', true );
	if ( empty( $faqs ) || ! is_array( $faqs ) ) {
		return;
	}
	echo '<div class="product-faqs">';
	echo '<h2>' . esc_html__( 'Frequently Asked Questions', 'emotousa-extended' ) . '</h2>';
	echo '<div class="faq-list">';
	$i = 1;
	foreach ( $faqs as $faq ) {
		if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) {
			continue;
		}
		printf(
			'<div class="faq-item"><h4 class="faq-question">%d. %s</h4><div class="faq-answer">%s</div></div>',
			esc_html( $i ),
			esc_html( $faq['question'] ),
			wp_kses_post( wpautop( $faq['answer'] ) )
		);
		$i++;
	}
	echo '</div></div>';
}

/**
 * Shortcode and action wrapper for FAQ display.
 */
function mcmp_display_product_faqs_wrapper() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	ob_start();
	mcmp_display_product_faqs( $product->get_id() );
	return ob_get_clean();
}
add_action( 'woocommerce_after_single_product_summary', 'mcmp_display_product_faqs_wrapper', 25 );
add_shortcode( 'product_faqs', 'mcmp_display_product_faqs_wrapper' );

/**
 * ============================================================================
 * SHORTCODES
 * ============================================================================
 */

/**
 * Product category hierarchy shortcode.
 * Usage: [mcmp_product_cat slug="category-slug" parent_html_tag="h3" html_tag="div"]
 */
function mcmp_product_cat_shortcode( $atts ) {
	$args = shortcode_atts( array(
		'parent_html_tag' => 'h3',
		'html_tag'        => 'div',
		'slug'            => '',
		'main_class'      => '',
		'parent_class'    => '',
		'child_class'     => '',
	), $atts );

	if ( empty( $args['slug'] ) ) {
		return '';
	}

	$parent_cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'slug' => $args['slug'] ) );
	if ( empty( $parent_cats ) || is_wp_error( $parent_cats ) ) {
		return '';
	}

	$parent_cat = reset( $parent_cats );
	ob_start();

	$parent_link = get_term_link( $parent_cat->term_id, 'product_cat' );
	if ( ! is_wp_error( $parent_link ) ) {
		printf( '<%1$s class="%2$s"><a href="%3$s">%4$s</a></%1$s>',
			esc_attr( $args['parent_html_tag'] ),
			esc_attr( $args['parent_class'] ),
			esc_url( $parent_link ),
			esc_html( $parent_cat->name )
		);
	}

	$child_cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => $parent_cat->term_id ) );
	if ( ! empty( $child_cats ) && ! is_wp_error( $child_cats ) ) {
		foreach ( $child_cats as $child_cat ) {
			$child_link = get_term_link( $child_cat->term_id, 'product_cat' );
			if ( ! is_wp_error( $child_link ) ) {
				printf( '<%1$s class="%2$s"><a href="%3$s">%4$s</a></%1$s>',
					esc_attr( $args['html_tag'] ),
					esc_attr( $args['child_class'] ),
					esc_url( $child_link ),
					esc_html( $child_cat->name )
				);
			}
		}
	}

	return ob_get_clean();
}
add_shortcode( 'mcmp_product_cat', 'mcmp_product_cat_shortcode' );

/**
 * Low-price product notice shortcode.
 * Usage: [low_price_product_html]
 */
function mcmp_low_price_product_html() {
	if ( ! is_product() ) {
		return '';
	}
	global $product;
	if ( ! $product || $product->get_price() > 6500 ) {
		return '';
	}
	ob_start();
	?>
	<div class="low-price-notice">
		<p><?php esc_html_e( 'Special pricing available for this product.', 'emotousa-extended' ); ?></p>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'low_price_product_html', 'mcmp_low_price_product_html' );

/**
 * Change variable product price format from "$min - $max" to "Starting From $min".
 * Applies universally to ALL product cards, leaving ONLY the single product page main price alone.
 */
function mcmp_format_variable_price_range_on_cards( $price, $from, $to ) {
	if ( is_product() && doing_action( 'woocommerce_single_product_summary' ) ) {
		return $price;
	}

	global $product;

	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
		return $price;
	}

	$raw_min = $product->get_variation_price( 'min', true );
	$formatted_min = wc_price( $raw_min );

	$custom_string = '<span class="start">' . __( 'Starting From', 'emotousa-extended-features' ) . '</span> ' . $formatted_min;

	$allowed_html = array(
		'span' => array(
			'class' => array(),
		),
		'bdi'  => array(),
	);

	return wp_kses( $custom_string, $allowed_html );
}
add_filter( 'woocommerce_format_price_range', 'mcmp_format_variable_price_range_on_cards', 10, 3 );
