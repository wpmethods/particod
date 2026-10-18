<?php
/**
 * Remove plugin data only when the store has opted in to uninstall cleanup.
 *
 * @package Wpmethods_Advance_Shipping_COD
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean the current site's data without deleting WooCommerce orders or tables.
 *
 * @return void
 */
function wascw_uninstall_site_data() {
	if ( 'yes' !== get_option( 'wascw_delete_data_on_uninstall', 'no' ) ) {
		return;
	}

	global $wpdb;
	$wascw_options = array(
		'wascw_selected_shipping_methods',
		'wascw_texts',
		'wascw_advance_cost_type',
		'wascw_custom_advance_price',
		'wascw_payment_modes',
		'wascw_payment_mode_labels',
		'wascw_advance_percentage',
		'wascw_percentage_base',
		'wascw_checkout_position',
		'wascw_checkout_position_updated',
		'wascw_gateways',
		'wascw_gateway_plans',
		'wascw_show_cod',
		'wascw_checkout_design',
		'wascw_delete_data_on_uninstall',
	);

	// Escape underscores so similarly named metadata belonging to others cannot match.
	$wascw_meta_pattern = $wpdb->esc_like( '_wascw_' ) . '%';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Uninstall must also work when WooCommerce is inactive; WordPress has no HPOS metadata deletion API.
	$wascw_meta_keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wascw_meta_pattern ) );
	foreach ( $wascw_meta_keys as $wascw_meta_key ) {
		delete_metadata( 'post', 0, $wascw_meta_key, '', true );
	}

	$wascw_hpos_table = $wpdb->prefix . 'wc_orders_meta';
	if ( $wascw_hpos_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wascw_hpos_table ) ) ) ) {
		$wascw_escaped_hpos_table = esc_sql( $wascw_hpos_table );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- SQL-escaped identifier uses WordPress's trusted prefix and a fixed suffix; supports WordPress versions before identifier placeholders.
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wascw_escaped_hpos_table}` WHERE meta_key LIKE %s", $wascw_meta_pattern ) );
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery

	if ( class_exists( 'WC_Cache_Helper' ) ) {
		WC_Cache_Helper::invalidate_cache_group( 'orders' );
	}
	if ( function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore' ) ) {
		$wascw_store = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::class );
		if ( is_callable( array( $wascw_store, 'clear_all_cached_data' ) ) ) {
			$wascw_store->clear_all_cached_data();
		}
	}

	foreach ( $wascw_options as $wascw_option ) {
		delete_option( $wascw_option );
	}
}

if ( is_multisite() ) {
	$wascw_offset = 0;
	do {
		$wascw_site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $wascw_offset,
			)
		);
		foreach ( $wascw_site_ids as $wascw_site_id ) {
			switch_to_blog( $wascw_site_id );
			wascw_uninstall_site_data();
			restore_current_blog();
		}
		$wascw_offset    += 100;
		$wascw_site_count = count( $wascw_site_ids );
	} while ( 100 === $wascw_site_count );
} else {
	wascw_uninstall_site_data();
}
