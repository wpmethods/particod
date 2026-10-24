<?php
/**
 * Order collection ledger and native sales analytics.
 *
 * @package PartiCOD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Determine whether the advance was confirmed.
 *
 * @param WC_Order $order Order to inspect.
 *
 * @return bool Whether the advance was confirmed.
 */
function particod_advance_is_confirmed( $order ) {
	$flag = $order->get_meta( '_particod_advance_paid' );
	return 'yes' === $flag || ( '' === $flag && $order->is_paid() );
}

/**
 * Get collections.
 *
 * @param WC_Order $order order value.
 *
 * @return array Result value.
 */
function particod_get_collections( $order ) {
	$entries = $order->get_meta( '_particod_collections' );
	return is_array( $entries ) ? $entries : array();
}

/**
 * Get due collected.
 *
 * @param WC_Order $order order value.
 *
 * @return float Result value.
 */
function particod_get_due_collected( $order ) {
	$amount = 0;
	foreach ( particod_get_collections( $order ) as $entry ) {
		$amount += (float) $entry['amount'];
	}
	return (float) wc_format_decimal( max( 0, $amount ), wc_get_price_decimals() );
}

/**
 * Get outstanding due.
 *
 * @param WC_Order $order order value.
 *
 * @return float Result value.
 */
function particod_get_outstanding_due( $order ) {
	return (float) wc_format_decimal( max( 0, (float) $order->get_meta( '_particod_due_amount' ) - particod_get_due_collected( $order ) ), wc_get_price_decimals() );
}

add_action( 'woocommerce_admin_order_totals_after_tax', 'particod_render_admin_payment_totals', PHP_INT_MAX );
/**
 * Show the sale value and actual receipts without changing the gateway charge.
 *
 * @param int $order_id Order identifier.
 * @return void
 */
function particod_render_admin_payment_totals( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( '_particod_is_advance_order' ) ) {
		return;
	}
	echo particod_get_admin_payment_totals( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The builder escapes labels and formats monetary values.
}

/**
 * Build order-item totals for both the initial screen and collection responses.
 *
 * @param WC_Order $order Advance order.
 * @return string Escaped table rows.
 */
function particod_get_admin_payment_totals( $order ) {
	$texts     = particod_get_texts();
	$advance   = particod_advance_is_confirmed( $order ) ? (float) $order->get_meta( '_particod_advance_amount' ) : 0;
	$collected = particod_get_due_collected( $order );
	$currency  = array( 'currency' => $order->get_currency() );
	$rows      = array(
		'order-total'   => array( __( 'Order Total:', 'particod' ), (float) $order->get_meta( '_particod_original_total' ) ),
		'advance-paid'  => array( $texts['admin_advance_label'], $advance ),
		'due-collected' => array( $texts['admin_collected_label'], $collected ),
		'paid'          => array( __( 'Paid:', 'particod' ), $advance + $collected ),
		'outstanding'   => array( $texts['admin_due_label'], particod_get_outstanding_due( $order ) ),
	);
	$html      = '';
	foreach ( $rows as $key => $row ) {
		$html .= '<tr class="particod-admin-payment-total particod-admin-' . esc_attr( $key ) . '"><td class="label">' . esc_html( rtrim( $row[0], ": \t\n\r\0\x0B" ) . ':' ) . '</td><td width="1%"></td><td class="total">' . wp_kses_post( wc_price( $row[1], $currency ) ) . '</td></tr>';
	}
	if ( (float) $order->get_total_refunded() > 0 ) {
		$html .= '<tr class="particod-admin-payment-total"><td colspan="3"><p class="description">' . esc_html__( 'Paid shows recorded receipts before refunds. Net Payment and refund limits below apply to the gateway advance only. Reconcile delivery refunds separately.', 'particod' ) . '</p></td></tr>';
	}
	// Core's next row repeats the gateway charge as Order Total. Hide only that row.
	return $html . '<tr class="particod-admin-payment-total particod-admin-total-anchor" hidden><td colspan="3"></td></tr>';
}

// Admin item/shipping edits recalculate the sale value, preserving the agreed gateway advance.
add_action( 'woocommerce_order_after_calculate_totals', 'particod_update_full_order_value', 20, 2 );
/**
 * Update full order value.
 *
 * @param bool     $and_taxes and taxes value.
 * @param WC_Order $order order value.
 *
 * @return void No return value.
 */
function particod_update_full_order_value( $and_taxes, $order ) {
	if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( '_particod_is_advance_order' ) ) {
		return;
	}
	$full    = (float) $order->get_total( 'edit' );
	$advance = (float) $order->get_meta( '_particod_advance_amount' );
	$order->update_meta_data( '_particod_original_total', $full );
	$order->update_meta_data( '_particod_due_amount', max( 0, $full - $advance ) );
	$order->update_meta_data( '_particod_outstanding_due', particod_get_outstanding_due( $order ) );
	$order->set_total( $advance );
}

/**
 * Get collection methods.
 *
 * @return array Result value.
 */
function particod_get_collection_methods() {
	return array(
		'cash'    => __( 'Cash', 'particod' ),
		'courier' => __( 'Courier settlement', 'particod' ),
		'bkash'   => __( 'bKash', 'particod' ),
		'nagad'   => __( 'Nagad', 'particod' ),
		'bank'    => __( 'Bank transfer', 'particod' ),
		'other'   => __( 'Other', 'particod' ),
	);
}

/**
 * Add collection entry.
 *
 * @param WC_Order $order order value.
 * @param float    $amount amount value.
 * @param string   $method method value.
 * @param string   $reference reference value.
 * @param string   $request_id request id value.
 * @param string   $reverse_id reverse id value.
 *
 * @return bool Result value.
 * @throws InvalidArgumentException When collection input or order state is invalid.
 */
function particod_add_collection_entry( $order, $amount, $method, $reference, $request_id, $reverse_id = '' ) {
	if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( '_particod_is_advance_order' ) ) {
		throw new InvalidArgumentException( esc_html__( 'This order does not use advance payments.', 'particod' ) );
	}
	if ( ! preg_match( '/^[a-zA-Z0-9-]{16,64}$/', $request_id ) ) {
		throw new InvalidArgumentException( esc_html__( 'Invalid collection request. Reload the order and try again.', 'particod' ) );
	}
	$entries = particod_get_collections( $order );
	foreach ( $entries as $entry ) {
		if ( $request_id === $entry['id'] ) {
			return false;
		}
	}
	if ( $reverse_id ) {
		$original = null;
		foreach ( $entries as $entry ) {
			if ( $reverse_id === $entry['id'] && (float) $entry['amount'] > 0 ) {
				$original = $entry;
			}
			if ( isset( $entry['reverses'] ) && $reverse_id === $entry['reverses'] ) {
				throw new InvalidArgumentException( esc_html__( 'This collection has already been reversed.', 'particod' ) );
			}
		}
		if ( ! $original ) {
			throw new InvalidArgumentException( esc_html__( 'Collection entry not found.', 'particod' ) );
		}
		$amount    = - (float) $original['amount'];
		$method    = $original['method'];
		$reference = $original['reference'];
	} else {
		if ( ! particod_advance_is_confirmed( $order ) ) {
			throw new InvalidArgumentException( esc_html__( 'Verify the advance payment before recording delivery collections.', 'particod' ) );
		}
		if ( in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) || (float) $order->get_total_refunded() > 0 ) {
			throw new InvalidArgumentException( esc_html__( 'This order is cancelled, failed or has refunds. Reconcile it before recording further collections.', 'particod' ) );
		}
		$amount = (float) wc_format_decimal( $amount, wc_get_price_decimals() );
		if ( ! is_finite( $amount ) || $amount <= 0 || $amount > particod_get_outstanding_due( $order ) ) {
			throw new InvalidArgumentException( esc_html__( 'Enter an amount greater than zero and no more than the outstanding balance.', 'particod' ) );
		}
		if ( ! array_key_exists( $method, particod_get_collection_methods() ) ) {
			throw new InvalidArgumentException( esc_html__( 'Choose a valid collection method.', 'particod' ) );
		}
	}
	$reference = substr( sanitize_text_field( $reference ), 0, 200 );
	$entries[] = array(
		'id'          => $request_id,
		'amount'      => $amount,
		'method'      => $method,
		'reference'   => sanitize_text_field( $reference ),
		'recorded_at' => gmdate( 'Y-m-d H:i:s' ),
		'user_id'     => get_current_user_id(),
		'reverses'    => $reverse_id,
	);
	$order->update_meta_data( '_particod_collections', $entries );
	$order->update_meta_data( '_particod_due_collected', particod_get_due_collected( $order ) );
	$order->update_meta_data( '_particod_outstanding_due', particod_get_outstanding_due( $order ) );
	$order->add_order_note(
		sprintf(
			$reverse_id ? /* translators: %1$s: Collection amount, %2$s: collection method, %3$s: receipt reference, %4$s: remaining balance. */
			__( 'Delivery collection reversed: %1$s. Method: %2$s. Reference: %3$s. Outstanding: %4$s.', 'particod' ) : /* translators: %1$s: Collection amount, %2$s: collection method, %3$s: receipt reference, %4$s: remaining balance. */
			__( 'Delivery collection recorded: %1$s. Method: %2$s. Reference: %3$s. Outstanding: %4$s.', 'particod' ),
			wp_strip_all_tags( wc_price( abs( $amount ), array( 'currency' => $order->get_currency() ) ) ),
			particod_get_collection_methods()[ $method ],
			sanitize_text_field( $reference ),
			wp_strip_all_tags( wc_price( particod_get_outstanding_due( $order ), array( 'currency' => $order->get_currency() ) ) )
		)
	);
	// Never mutate order/gateway totals, fulfillment status or the gateway transaction ID.
	$order->save();
	return true;
}

/** Native sales analytics use full order value; collection entries are not new sales. */
add_filter( 'woocommerce_analytics_update_order_stats_data', 'particod_full_value_analytics', 20, 2 );
/**
 * Full value analytics.
 *
 * @param array    $data data value.
 * @param WC_Order $order order value.
 *
 * @return array Result value.
 */
function particod_full_value_analytics( $data, $order ) {
	if ( $order instanceof WC_Order && 'yes' === $order->get_meta( '_particod_is_advance_order' ) && '' !== $order->get_meta( '_particod_original_total' ) ) {
		$full                = (float) $order->get_meta( '_particod_original_total' );
		$data['total_sales'] = $full;
		$data['net_total']   = $full - (float) $order->get_total_tax() - (float) $order->get_shipping_total();
	}
	return $data;
}

/**
 * Sync order analytics.
 *
 * @param int $order_id order id value.
 *
 * @return bool Result value.
 */
function particod_sync_order_analytics( $order_id ) {
	$class = '\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore';
	if ( ! class_exists( $class ) ) {
		return false;
	}
	$result = $class::sync_order( $order_id );
	if ( class_exists( '\Automattic\WooCommerce\Admin\API\Reports\Cache' ) ) {
		\Automattic\WooCommerce\Admin\API\Reports\Cache::invalidate();
	}
	return true === $result;
}

add_action( 'wp_ajax_particod_order_collection', 'particod_ajax_order_collection' );
/**
 * Handle authorized collection writes and analytics refresh requests.
 *
 * @return void No return value.
 * @throws InvalidArgumentException Caught internally to return a validation error.
 */
function particod_ajax_order_collection() {
	foreach ( array( 'order_id', 'nonce', 'operation', 'amount', 'method', 'reference', 'request_id', 'entry_id' ) as $key ) {
		if ( isset( $_POST[ $key ] ) && ! is_scalar( $_POST[ $key ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid collection request.', 'particod' ) ), 400 );
		}
	}
	$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_shop_order', $order_id ) ) {
		wp_send_json_error( array( 'message' => __( 'You cannot edit this order.', 'particod' ) ), 403 );
	}
	check_ajax_referer( 'particod_collection_' . $order_id, 'nonce' );
	global $wpdb;
	// MySQL/MariaDB connection lock serializes simultaneous collections for this order.
	$lock = 'particod_' . md5( DB_NAME . ':' . $wpdb->prefix . ':' . $order_id );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Connection-level advisory locks cannot be cached and prevent duplicate financial ledger writes.
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
		wp_send_json_error( array( 'message' => __( 'Another collection is being saved. Please try again.', 'particod' ) ), 409 );
	}
	$error        = '';
	$analytics_ok = false;
	try {
		$order = wc_get_order( $order_id );
		if ( ! $order || 'yes' !== $order->get_meta( '_particod_is_advance_order' ) ) {
			throw new InvalidArgumentException( esc_html__( 'Advance payment order not found.', 'particod' ) );
		}
		$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		if ( in_array( $operation, array( 'record', 'reverse' ), true ) ) {
			$amount     = isset( $_POST['amount'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['amount'] ) ) ) : 0;
			$method     = isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : '';
			$reference  = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
			$request_id = isset( $_POST['request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) : '';
			$reverse_id = 'reverse' === $operation && isset( $_POST['entry_id'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_id'] ) ) : '';
			if ( 'reverse' === $operation && ! $reverse_id ) {
				throw new InvalidArgumentException( esc_html__( 'Collection entry not found.', 'particod' ) );
			}
			particod_add_collection_entry( $order, $amount, $method, $reference, $request_id, $reverse_id );
		} elseif ( 'sync' !== $operation ) {
			throw new InvalidArgumentException( esc_html__( 'Invalid collection operation.', 'particod' ) );
		}
		try {
			$analytics_ok = particod_sync_order_analytics( $order_id );
		} catch ( Exception $analytics_error ) {
			$analytics_ok = false;
		}
	} catch ( Exception $exception ) {
		$error = $exception->getMessage();
	} finally {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Release the same uncached connection-level advisory lock.
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
	if ( $error ) {
		wp_send_json_error( array( 'message' => $error ), 400 );
	}
	wp_send_json_success(
		array(
			'html'        => particod_get_order_payment_panel( $order ),
			'totals_html' => particod_get_admin_payment_totals( $order ),
			'message'     => $analytics_ok ? __( 'Saved. Order analytics refreshed.', 'particod' ) : __( 'Saved. Analytics refresh is pending; use WooCommerce historical data import if needed.', 'particod' ),
		)
	);
}

add_action( 'woocommerce_admin_order_data_after_order_details', 'particod_render_order_payment_summary' );
/**
 * Render order payment summary.
 *
 * @param WC_Order $order order value.
 *
 * @return void No return value.
 */
function particod_render_order_payment_summary( $order ) {
	if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( '_particod_is_advance_order' ) ) {
		return;
	}
	echo '<div class="particod-order-payment-slot">' . particod_get_order_payment_panel( $order ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The panel builder escapes all dynamic data and returns required form controls.
}

add_action(
	'admin_enqueue_scripts',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'particod-order-payments', plugin_dir_url( __DIR__ . '/../particod.php' ) . 'assets/css/particod-order-payments.css', array(), PARTICOD_VERSION );
		wp_enqueue_script( 'particod-order-payments', plugin_dir_url( __DIR__ . '/../particod.php' ) . 'assets/js/particod-order-payments.js', array( 'jquery' ), PARTICOD_VERSION, true );
	}
);

/**
 * Get order payment panel.
 *
 * @param WC_Order $order order value.
 *
 * @return string Result value.
 */
function particod_get_order_payment_panel( $order ) {
	$texts        = particod_get_texts();
	$currency     = array( 'currency' => $order->get_currency() );
	$full         = (float) $order->get_meta( '_particod_original_total' );
	$advance      = particod_advance_is_confirmed( $order ) ? (float) $order->get_meta( '_particod_advance_amount' ) : 0;
	$collected    = particod_get_due_collected( $order );
	$due          = particod_get_outstanding_due( $order );
	$confirmed    = particod_advance_is_confirmed( $order );
	$has_refunds  = (float) $order->get_total_refunded() > 0;
	$status_label = $confirmed ? ( $due <= 0 ? __( 'Fully collected', 'particod' ) : __( 'Collection outstanding', 'particod' ) ) : __( 'Awaiting advance', 'particod' );
	if ( $has_refunds ) {
		$status_label = __( 'Refund reconciliation', 'particod' );
	}
	$can_record = particod_advance_is_confirmed( $order ) && $due > 0 && ! in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) && (float) $order->get_total_refunded() <= 0;
	$methods    = particod_get_collection_methods();
	$entries    = particod_get_collections( $order );
	$reversed   = array();
	foreach ( $entries as $entry ) {
		if ( ! empty( $entry['reverses'] ) ) {
			$reversed[] = $entry['reverses'];
		}
	}
	ob_start(); ?>
	<div class="particod-order-payments" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'particod_collection_' . $order->get_id() ) ); ?>" data-request-id="<?php echo esc_attr( wp_generate_uuid4() ); ?>" data-reverse-confirm="<?php esc_attr_e( 'Reverse this collection entry? The amount will return to the outstanding balance. This records a correction; it does not refund money.', 'particod' ); ?>" data-saving-text="<?php esc_attr_e( 'Saving...', 'particod' ); ?>" data-error-text="<?php esc_attr_e( 'Could not confirm the result. Retry this request or reload the order to check the ledger.', 'particod' ); ?>">
		<div class="particod-payment-top"><h3><?php esc_html_e( 'Payments', 'particod' ); ?></h3><span class="particod-payment-status <?php echo $due <= 0 && $confirmed && ! $has_refunds ? 'is-settled' : ''; ?>"><?php echo esc_html( $status_label ); ?></span></div>
		<div class="particod-payment-metrics">
		<?php
		foreach ( array(
			array( $texts['full_order_total_label'], $full ),
			array( $texts['admin_advance_label'], $advance ),
			array( $texts['admin_collected_label'], $collected ),
			array( $texts['admin_due_label'], $due ),
		) as list( $label, $amount ) ) :
			?>
			<div><span><?php echo esc_html( $label ); ?></span><strong><?php echo wp_kses_post( wc_price( $amount, $currency ) ); ?></strong></div>
		<?php endforeach; ?>
		</div>
		<?php
		if ( ! $confirmed ) :
			?>
			<p><?php esc_html_e( 'Advance awaiting verification:', 'particod' ); ?> <?php echo wp_kses_post( wc_price( $order->get_meta( '_particod_advance_amount' ), $currency ) ); ?></p><?php endif; ?>
		<?php
		if ( $has_refunds ) :
			?>
			<p class="particod-payment-warning"><?php esc_html_e( 'Collections show historical receipts. Refunds require manual reconciliation against online and delivery payments.', 'particod' ); ?></p><?php endif; ?>
		<?php
		if ( $advance + $collected > $full ) :
			?>
			<p class="particod-payment-warning"><?php esc_html_e( 'Recorded payments exceed the revised order value. Review the ledger and arrange any required refund separately.', 'particod' ); ?></p><?php endif; ?>
		<?php if ( $can_record ) : ?>
		<details class="particod-collection-editor"><summary><?php esc_html_e( 'Record collection', 'particod' ); ?></summary>
		<div class="particod-collection-fields">
			<label><?php esc_html_e( 'Amount collected', 'particod' ); ?><input class="particod-collection-amount" type="number" min="<?php echo esc_attr( pow( 10, -wc_get_price_decimals() ) ); ?>" max="<?php echo esc_attr( $due ); ?>" step="<?php echo esc_attr( pow( 10, -wc_get_price_decimals() ) ); ?>" value="<?php echo esc_attr( $due ); ?>" /></label>
			<label><?php esc_html_e( 'Collection method', 'particod' ); ?><select class="particod-collection-method">
			<?php
			foreach ( $methods as $method => $label ) :
				?>
				<option value="<?php echo esc_attr( $method ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label class="particod-reference-field"><?php esc_html_e( 'Reference / receipt', 'particod' ); ?><input class="particod-collection-reference" type="text" maxlength="200" placeholder="<?php esc_attr_e( 'Optional reference', 'particod' ); ?>" /></label>
			<button type="button" class="button button-primary particod-record-collection"><?php esc_html_e( 'Record collection', 'particod' ); ?></button>
		</div>
		<p class="description"><?php esc_html_e( 'Record money already received. Order status stays unchanged.', 'particod' ); ?></p>
		</details>
			<?php
		elseif ( $due > 0 ) :
			?>
			<p class="particod-payment-warning"><?php esc_html_e( 'Verify the advance first. Further collections are disabled for cancelled, failed or refunded orders; refund cases require manual reconciliation.', 'particod' ); ?></p><?php endif; ?>
		<?php if ( $entries ) : ?>
		<details class="particod-collection-history"><summary>
			<?php
			echo esc_html(
				sprintf( /* translators: %d: Number of recorded collection entries. */
					__( 'Collection history (%d)', 'particod' ),
					count( $entries )
				)
			);
			?>
															</summary><ul class="particod-collection-ledger">
			<?php
			foreach ( array_reverse( $entries ) as $entry ) :
				$user = get_userdata( $entry['user_id'] );
				?>
		<li><div class="particod-ledger-entry-top"><strong><?php echo wp_kses_post( wc_price( $entry['amount'], $currency ) ); ?></strong><span><?php echo esc_html( $methods[ $entry['method'] ] ?? $entry['method'] ); ?></span></div>
		<small><?php echo esc_html( get_date_from_gmt( $entry['recorded_at'], 'Y-m-d H:i' ) . ' · ' . ( $user ? $user->display_name : '#' . $entry['user_id'] ) ); ?></small>
				<?php
				if ( $entry['reference'] ) :
					?>
					<small><?php echo esc_html( $entry['reference'] ); ?></small><?php endif; ?>
				<?php
				if ( $entry['amount'] > 0 && ! in_array( $entry['id'], $reversed, true ) ) :
					?>
					<button type="button" class="button-link particod-reverse-collection" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>"><?php esc_html_e( 'Reverse entry', 'particod' ); ?></button>
					<?php
else :
					echo esc_html__( 'Reversed / correction', 'particod' );
endif;
?>
		</li><?php endforeach; ?>
		</ul></details><?php endif; ?>
		<details class="particod-accounting-details"><summary><?php esc_html_e( 'Analytics & help', 'particod' ); ?></summary><p class="particod-accounting-help"><?php esc_html_e( 'Sales analytics use full order value. Collections do not add new sales. Completing an order does not collect its balance.', 'particod' ); ?></p><button type="button" class="button particod-sync-analytics"><?php esc_html_e( 'Refresh order analytics', 'particod' ); ?></button></details>
		<div class="particod-payment-footer"><span class="particod-collection-feedback" role="status" aria-live="polite" tabindex="-1"></span></div>
	</div>
	<?php
	return ob_get_clean();
}
