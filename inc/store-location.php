<?php
/**
 * Background inventory logic, sync loops, and SQL sorting modifiers.
 * 
 * @package EMotoUSAExtended
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sync parent product store location from its in-stock variations.
 */
function mcmp_update_parent_store_location( $product_id ) {
	$product = wc_get_product( $product_id );

	if ( ! $product || ! $product->is_type( 'variable' ) ) {
		return;
	}

	$config        = mcmp_get_store_location_config();
	$best_priority = PHP_INT_MAX;
	$best_location = '';

	foreach ( $product->get_children() as $variation_id ) {
		$stock = (int) get_post_meta( $variation_id, '_stock', true );
		if ( $stock <= 0 ) {
			continue;
		}

		$loc = get_post_meta( $variation_id, '_store_location_field', true );
		
		if ( empty( $loc ) || ! isset( $config[ $loc ] ) ) {
			$loc = 'In Maui Stock';
		}

		$priority = $config[ $loc ]['priority'];
		
		if ( $priority < $best_priority ) {
			$best_priority = $priority;
			$best_location = $loc;
		}

		if ( 1 === $best_priority ) {
			break;
		}
	}

	$final_location = ! empty( $best_location ) ? $best_location : 'Out of stock';
	update_post_meta( $product_id, '_store_location_field', sanitize_text_field( $final_location ) );
}
add_action( 'woocommerce_update_product', 'mcmp_update_parent_store_location' );

/**
 * Inject store location priority sorting into SQL query clauses.
 */
function mcmp_store_location_posts_clauses( $clauses, $query ) {
	global $wpdb;

	if ( is_admin() ) {
		return $clauses;
	}

	$is_product_archive = $query->is_main_query() && $query->is_post_type_archive( 'product' );
	$is_product_tax     = $query->is_main_query() && $query->is_tax( array( 'product_cat', 'product_tag' ) );
	$is_shortcode       = (bool) $query->get( 'mcmp_location_sort' );

	if ( ! $is_product_archive && ! $is_product_tax && ! $is_shortcode ) {
		return $clauses;
	}

	// ========================================================================
	// NEW: BYPASS LOGIC FOR EXPLICIT SORTING (e.g. Price, Popularity)
	// ========================================================================
	if ( $query->is_main_query() && isset( $_GET['orderby'] ) ) {
		$get_orderby     = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
		$default_orderby = apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby', 'menu_order' ) );
		
		// If user explicitly picked a sort option that IS NOT "Default" and IS NOT "Random",
		// we bypass location prioritization and let WooCommerce sort normally.
		if ( $get_orderby !== $default_orderby && $get_orderby !== 'rand' && $get_orderby !== 'menu_order' ) {
			return $clauses;
		}
	}

	// For shortcodes, allow bypass if shortcode args dictate an explicit sort (like price)
	if ( $is_shortcode && $query->get( 'mcmp_bypass_location_sort' ) ) {
		return $clauses;
	}
	// ========================================================================

	$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS mcmp_locmeta
		ON  mcmp_locmeta.post_id  = {$wpdb->posts}.ID
		AND mcmp_locmeta.meta_key = '_store_location_field'";

	if ( empty( $clauses['groupby'] ) ) {
		$clauses['groupby'] = "{$wpdb->posts}.ID";
	}

	$config   = mcmp_get_store_location_config();
	$case_sql = "CASE COALESCE( mcmp_locmeta.meta_value, 'In Maui Stock' ) ";
	
	foreach ( $config as $loc_key => $loc_data ) {
		$case_sql .= $wpdb->prepare( "WHEN %s THEN %d ", $loc_key, $loc_data['priority'] );
	}
	$case_sql .= "ELSE 99 END ASC";

	// If the existing orderby is RAND(), this creates: "ORDER BY CASE... ASC, RAND()"
	// This naturally groups by Location Priority first, then randomizes within the groups!
	$existing_orderby   = ! empty( $clauses['orderby'] ) ? ', ' . $clauses['orderby'] : '';
	$clauses['orderby'] = $case_sql . $existing_orderby;

	return $clauses;
}
add_filter( 'posts_clauses', 'mcmp_store_location_posts_clauses', 999, 2 );

/**
 * Apply store location sorting to WooCommerce [products] shortcodes.
 */
function mcmp_shortcode_store_location_sorting( $query_args, $attributes, $type ) {
	$query_args['suppress_filters']   = false;
	$query_args['mcmp_location_sort'] = true;
	
	// If shortcode uses an explicit sort like orderby="price" or orderby="popularity", 
	// flag it so the posts_clauses function knows to bypass location grouping.
	if ( ! empty( $attributes['orderby'] ) ) {
		$allowed_priority_sorts = array( 'menu_order', 'title', 'date', 'rand', 'ID', 'name', '' );
		if ( ! in_array( $attributes['orderby'], $allowed_priority_sorts, true ) ) {
			$query_args['mcmp_bypass_location_sort'] = true;
		}
	}

	return $query_args;
}
add_filter( 'woocommerce_shortcode_products_query', 'mcmp_shortcode_store_location_sorting', 999, 3 );

/**
 * Add store location to variation data available on frontend JS.
 */
function mcmp_add_store_location_to_variation_data( $data, $product, $variation ) {
	$loc_key = $variation->get_meta( '_store_location_field' );
	$config  = mcmp_get_store_location_config();
	
	if ( empty( $loc_key ) || ! isset( $config[ $loc_key ] ) ) {
		$loc_key = 'In Maui Stock';
	}
	
	$data['_store_location_field'] = $config[ $loc_key ]['label'];
	return $data;
}
add_filter( 'woocommerce_available_variation', 'mcmp_add_store_location_to_variation_data', 10, 3 );

/**
 * Display store location in cart line items.
 */
function mcmp_display_store_location_in_cart( $item_data, $cart_item ) {
	$variation_id = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
	$store_loc    = mcmp_get_store_location( $variation_id );
	$config       = mcmp_get_store_location_config();

	$item_data[] = array(
		'name'  => __( 'Store Location', 'emotousa-extended-features' ),
		'value' => sanitize_text_field( $config[ $store_loc ]['label'] ),
	);
	
	return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'mcmp_display_store_location_in_cart', 10, 2 );