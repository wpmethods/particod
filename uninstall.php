<?php
/**
 * Remove plugin data only when the store has opted in to uninstall cleanup.
 *
 * @package PartiCOD
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}


/**
 * Clean the current site's data without deleting WooCommerce orders or tables.
 *
 * @return void
 */
function particod_uninstall_site_data() {
	if ( 'yes' !== get_option( 'particod_delete_data_on_uninstall', 'no' ) ) {
		return;
	}

	global $wpdb;
	$particod_options = array(
		'particod_selected_shipping_methods',
		'particod_texts',
		'particod_advance_cost_type',
		'particod_custom_advance_price',
		'particod_payment_modes',
		'particod_payment_mode_labels',
		'particod_advance_percentage',
		'particod_percentage_base',
		'particod_checkout_position',
		'particod_checkout_position_updated',
		'particod_gateways',
		'particod_gateway_plans',
		'particod_show_cod',
		'particod_checkout_design',
		'particod_delete_data_on_uninstall',
	);

	// Escape literal underscores so metadata belonging to other plugins cannot match.
	$particod_meta_pattern = $wpdb->esc_like( '_particod_' ) . '%';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Uninstall must work without WooCommerce; no WooCommerce-independent HPOS metadata deletion API exists.
	$particod_meta_keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $particod_meta_pattern ) );
	foreach ( $particod_meta_keys as $particod_meta_key ) {
		delete_metadata( 'post', 0, $particod_meta_key, '', true );
	}
	$particod_hpos_table = $wpdb->prefix . 'wc_orders_meta';
	if ( $particod_hpos_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $particod_hpos_table ) ) ) ) {
		$particod_escaped_hpos_table = esc_sql( $particod_hpos_table );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- SQL-escaped trusted prefix and fixed suffix support WordPress versions before identifier placeholders.
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$particod_escaped_hpos_table}` WHERE meta_key LIKE %s", $particod_meta_pattern ) );
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery
	if ( class_exists( 'WC_Cache_Helper' ) ) {
		WC_Cache_Helper::invalidate_cache_group( 'orders' );
	}
	if ( function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore' ) ) {
		$particod_store = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::class );
		if ( is_callable( array( $particod_store, 'clear_all_cached_data' ) ) ) {
			$particod_store->clear_all_cached_data();
		}
	}

	foreach ( $particod_options as $particod_option ) {
		delete_option( $particod_option );
	}
}

if ( is_multisite() ) {
	$particod_offset = 0;
	do {
		$particod_site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $particod_offset,
			)
		);
		foreach ( $particod_site_ids as $particod_site_id ) {
			switch_to_blog( $particod_site_id );
			particod_uninstall_site_data();
			restore_current_blog();
		}
		$particod_offset    += 100;
		$particod_site_count = count( $particod_site_ids );
	} while ( 100 === $particod_site_count );
} else {
	particod_uninstall_site_data();
}
