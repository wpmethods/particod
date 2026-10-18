<?php
/**
 * Checkout choices, payment amounts and customer summaries.
 *
 * @package Wpmethods_Advance_Shipping_COD
 */

defined( 'ABSPATH' ) || exit;

/* Accept mode identifiers only; all amounts are calculated on the server. */
add_action( 'woocommerce_checkout_update_order_review', 'wascw_update_checkout_choice', 5 );
/**
 * Update checkout choice.
 *
 * @param string $posted_data posted data value.
 *
 * @return void No return value.
 */
function wascw_update_checkout_choice( $posted_data ) {
	parse_str( $posted_data, $data );
	if ( WC()->session && isset( $data['payment_method'] ) && is_string( $data['payment_method'] ) ) {
		WC()->session->set( 'chosen_payment_method', sanitize_key( $data['payment_method'] ) );
	}
	if ( WC()->session && isset( $data['wascw_payment_mode'] ) && is_string( $data['wascw_payment_mode'] ) ) {
		$mode = sanitize_key( $data['wascw_payment_mode'] );
		if ( in_array( $mode, wascw_get_enabled_modes(), true ) ) {
			WC()->session->set( 'wascw_payment_mode', $mode );
		}
	}
}

// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before running this action.
add_action(
	'woocommerce_checkout_process',
	function () {
		if ( isset( $_POST['wascw_payment_mode'] ) ) {
			$mode = is_string( $_POST['wascw_payment_mode'] ) ? sanitize_key( wp_unslash( $_POST['wascw_payment_mode'] ) ) : '';
			if ( ! in_array( $mode, wascw_get_enabled_modes(), true ) ) {
				wc_add_notice( __( 'Please select an available advance payment option.', 'wpmethods-advance-shipping-cod' ), 'error' );
			} elseif ( WC()->session ) {
				WC()->session->set( 'wascw_payment_mode', $mode );
			}
		}
	}
);

// phpcs:enable WordPress.Security.NonceVerification.Missing

add_action( 'woocommerce_after_checkout_validation', 'wascw_validate_gateway_plan', 10, 2 );
/**
 * Prevent an online gateway from collecting the full order for a zero-advance plan.
 *
 * @param array    $data   Validated checkout fields supplied by WooCommerce.
 * @param WP_Error $errors Checkout validation errors.
 * @return void
 */
function wascw_validate_gateway_plan( $data, $errors ) {
	if ( ! wascw_uses_gateway_plans() || ! WC()->cart || empty( $data['payment_method'] ) || ! wascw_gateway_allows_plan( $data['payment_method'] ) ) {
		return;
	}
	$total    = wascw_get_cart_grand_total();
	$shipping = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
	if ( $total > 0 && wascw_calculate_advance_amount( $shipping, $total ) <= 0 ) {
		$errors->add( 'wascw_zero_advance', __( 'No advance is required for this payment option. Choose Cash on Delivery, another payment option, or a different payment method.', 'wpmethods-advance-shipping-cod' ) );
	}
}

add_action( 'woocommerce_review_order_after_cart_contents', 'wascw_render_subtotal_notice' );
/**
 * Render subtotal notice.
 *
 * @return void No return value.
 */
function wascw_render_subtotal_notice() {
	if ( 'subtotal' === wascw_get_checkout_position() ) {
		// Core's subtotal follows this hook; the checkout script moves the row below it.
		echo '<tr class="wascw-summary-row"><td colspan="2">' . wascw_get_notice_html() . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The HTML builder escapes all dynamic attributes and content; checkout controls must be retained.
	}
}
add_action( 'woocommerce_review_order_before_order_total', 'wascw_render_total_notice' );
/**
 * Render total notice.
 *
 * @return void No return value.
 */
function wascw_render_total_notice() {
	if ( 'before_total' === wascw_get_checkout_position() ) {
		echo '<tr class="wascw-summary-row"><td colspan="2">' . wascw_get_notice_html() . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The HTML builder escapes all dynamic attributes and content; checkout controls must be retained.
	}
}
add_action( 'woocommerce_review_order_before_payment', 'wascw_render_notice_wrapper' );
/**
 * Render notice wrapper.
 *
 * @return void No return value.
 */
function wascw_render_notice_wrapper() {
	if ( 'before_payment' === wascw_get_checkout_position() ) {
		echo wascw_get_notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The HTML builder escapes all dynamic attributes and content; checkout controls must be retained.
	}
}
add_action( 'woocommerce_review_order_before_submit', 'wascw_render_payment_position_notice' );
/**
 * Render payment-area summaries inside WooCommerce's refreshed payment fragment.
 *
 * The checkout script places this wrapper beside the gateway list so themes do
 * not need a template override or a particular payment heading selector.
 *
 * @return void
 */
function wascw_render_payment_position_notice() {
	if ( in_array( wascw_get_checkout_position(), array( 'after_payment_title', 'after_payment_methods' ), true ) ) {
		echo wascw_get_notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The HTML builder escapes all dynamic attributes and content; checkout controls must be retained.
	}
}

add_filter( 'woocommerce_update_order_review_fragments', 'wascw_add_notice_fragment' );
/**
 * Add notice fragment.
 *
 * @param array $fragments fragments value.
 *
 * @return array Result value.
 */
function wascw_add_notice_fragment( $fragments ) {
	if ( 'before_payment' === wascw_get_checkout_position() ) {
		$fragments['#wascw-notice-wrap'] = wascw_get_notice_html();
	}
	return $fragments;
}

add_filter( 'woocommerce_gateway_title', 'wascw_gateway_payable_title', 20, 2 );
/**
 * Show the plan's payable amount beside supported gateway titles at checkout.
 *
 * @param string $title      Existing gateway title.
 * @param string $gateway_id Gateway identifier.
 * @return string Gateway title with its payable amount when applicable.
 */
function wascw_gateway_payable_title( $title, $gateway_id ) {
	if ( ! wascw_uses_gateway_plans() || ! is_checkout() || is_checkout_pay_page() || is_order_received_page() || ! WC()->cart || ! wascw_gateway_allows_plan( $gateway_id ) ) {
		return $title;
	}
	$shipping = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
	$amount   = wascw_calculate_advance_amount( $shipping, wascw_get_cart_grand_total() );
	if ( $amount <= 0 ) {
		return $title;
	}
	/* translators: %s: Formatted amount payable through the gateway. */
	$cost = sprintf( __( 'Pay now: %s', 'wpmethods-advance-shipping-cod' ), wc_price( $amount ) );
	return $title . ' <span class="wascw-gateway-cost">' . wp_kses_post( $cost ) . '</span>';
}
/**
 * Get notice html.
 *
 * @return string Result value.
 */
function wascw_get_notice_html() {
	ob_start();
	echo '<div id="wascw-notice-wrap" data-position="' . esc_attr( wascw_get_checkout_position() ) . '" data-allow-cod="' . ( wascw_should_show_cod() ? 'true' : 'false' ) . '" data-gateway-plans="' . ( wascw_uses_gateway_plans() ? 'true' : 'false' ) . '" data-selected-gateway="' . esc_attr( WC()->session ? WC()->session->get( 'chosen_payment_method', '' ) : '' ) . '">';
	if ( WC()->cart && wascw_is_payment_plan_active() ) {
		$texts    = wascw_get_texts();
		$shipping = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
		$total    = wascw_get_cart_grand_total();
		$cod      = wascw_is_cod_checkout_selected();
		$advance  = $cod ? 0 : wascw_calculate_advance_amount( $shipping, $total );
		$due      = max( 0, $total - $advance );
		echo '<section class="wascw-payment-card" aria-labelledby="wascw-payment-heading">';
		echo '<h3 id="wascw-payment-heading">' . esc_html( $cod ? __( 'Cash on Delivery', 'wpmethods-advance-shipping-cod' ) : $texts['notice_heading'] ) . '</h3>';
		$modes  = wascw_get_enabled_modes();
		$labels = wascw_get_checkout_mode_labels();
		if ( count( $modes ) > 1 && ! $cod ) {
			echo '<fieldset class="wascw-payment-options"><legend class="screen-reader-text">' . esc_html__( 'Advance payment option', 'wpmethods-advance-shipping-cod' ) . '</legend>';
			foreach ( $modes as $mode ) {
				$amount = wascw_calculate_advance_amount( $shipping, $total, $mode );
				echo '<label class="wascw-payment-option"><input type="radio" name="wascw_payment_mode" value="' . esc_attr( $mode ) . '" ' . checked( wascw_get_chosen_mode(), $mode, false ) . ' /><span>' . esc_html( $labels[ $mode ] ) . '</span><strong>' . wp_kses_post( wc_price( $amount ) ) . '</strong></label>';
			}
			echo '</fieldset>';
		}
		echo '<dl class="wascw-payment-breakdown"><div><dt>' . esc_html( $texts['full_order_total_label'] ) . '</dt><dd>' . wp_kses_post( wc_price( $total ) ) . '</dd></div><div class="wascw-pay-now"><dt>' . esc_html( $texts['order_review_heading'] ) . '</dt><dd>' . wp_kses_post( wc_price( $advance ) ) . '</dd></div><div><dt>' . esc_html( $texts['due_on_delivery_label'] ) . '</dt><dd>' . wp_kses_post( wc_price( $due ) ) . '</dd></div></dl>';
		if ( $advance > 0 ) {
			$message = 'full_payment' === wascw_get_chosen_mode() ? __( 'Pay {advance_amount} now using your preferred payment method. No payment is due on delivery.', 'wpmethods-advance-shipping-cod' ) : $texts['notice_message'];
			echo '<p class="wascw-payment-message">' . wp_kses_post(
				wascw_format_text(
					$message,
					array(
						'shipping_amount' => wc_price( $shipping ),
						'advance_amount'  => wc_price( $advance ),
						'due_amount'      => wc_price( $due ),
					)
				)
			) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'No advance is required for this option. Pay the full amount on delivery using Cash on Delivery.', 'wpmethods-advance-shipping-cod' ) . '</p>';
		}
		echo '</section>';
	}
	echo '</div>';
	return ob_get_clean();
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
			wp_enqueue_style( 'wascw-checkout', plugin_dir_url( __DIR__ . '/../wpmethods-advance-shipping-cod.php' ) . 'assets/css/wascw-checkout.css', array(), WASCW_VERSION );
			wp_add_inline_style( 'wascw-checkout', wascw_get_design_css() );
			wp_enqueue_script( 'wascw-checkout', plugin_dir_url( __DIR__ . '/../wpmethods-advance-shipping-cod.php' ) . 'assets/js/wascw-checkout.js', array( 'jquery', 'wc-checkout' ), WASCW_VERSION, true );
		}
	}
);

/** Scope numeric cart totals to gateway fields, including legacy cart->total reads.
 * Template hooks fire during AJAX too. Stored cart/session totals remain intact.
 */
add_action(
	'woocommerce_before_template_part',
	function ( $template_name, $template_path = '', $located = '', $args = array() ) {
		if ( 'checkout/payment-method.php' === $template_name ) {
			$GLOBALS['wascw_rendering_gateway']    = true;
			$GLOBALS['wascw_rendering_gateway_id'] = isset( $args['gateway']->id ) ? $args['gateway']->id : '';
		}
	},
	10,
	4
);
add_action(
	'woocommerce_after_template_part',
	function ( $template_name ) {
		if ( 'checkout/payment-method.php' === $template_name ) {
			$GLOBALS['wascw_rendering_gateway']    = false;
			$GLOBALS['wascw_rendering_gateway_id'] = '';
		}
	}
);
add_filter(
	'woocommerce_cart_get_total',
	function ( $total ) {
		if ( empty( $GLOBALS['wascw_rendering_gateway'] ) ) {
			return $total;
		}
		if ( is_checkout_pay_page() ) {
			$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
			return $order && 'yes' === $order->get_meta( '_wascw_is_advance_order' ) ? (float) $order->get_meta( '_wascw_advance_amount' ) : $total;
		}
		$uses_plan = wascw_is_payment_plan_active();
		if ( wascw_uses_gateway_plans() ) {
			$gateway_id = ! empty( $GLOBALS['wascw_rendering_gateway_id'] ) ? $GLOBALS['wascw_rendering_gateway_id'] : ( WC()->session ? WC()->session->get( 'chosen_payment_method', '' ) : '' );
			$uses_plan  = wascw_gateway_allows_plan( $gateway_id );
		}
		if ( $uses_plan ) {
			$shipping = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
			$advance  = wascw_calculate_advance_amount( $shipping, wascw_get_cart_grand_total() );
			return $advance > 0 ? $advance : $total;
		}
		return $total;
	},
	999
);

/* Persist the advance before the first order save so every gateway reads it. */
add_action( 'woocommerce_checkout_create_order', 'wascw_prepare_advance_order', 999, 1 );
/**
 * Persist the selected advance and original order value before checkout saves.
 *
 * @param WC_Order $order Order to prepare.
 * @return void
 */
function wascw_prepare_advance_order( $order ) {
	// WooCommerce can reuse an unpaid order after the shipping choice changes.
	foreach ( array( '_wascw_is_advance_order', '_wascw_advance_amount', '_wascw_due_amount', '_wascw_original_total', '_wascw_payment_mode', '_wascw_advance_paid', '_wascw_note_added' ) as $key ) {
		$order->delete_meta_data( $key );
	}
	$uses_plan = wascw_uses_gateway_plans() ? wascw_gateway_allows_plan( $order->get_payment_method() ) : wascw_order_uses_selected_shipping( $order );
	if ( ! $uses_plan || 'cod' === $order->get_payment_method() ) {
		return;
	}
	$shipping = (float) $order->get_shipping_total() + (float) $order->get_shipping_tax();
	$total    = (float) $order->get_total( 'edit' );
	$advance  = wascw_calculate_advance_amount( $shipping, $total );
	if ( $advance <= 0 ) {
		return;
	}
	$order->update_meta_data( '_wascw_is_advance_order', 'yes' );
	$order->update_meta_data( '_wascw_advance_amount', $advance );
	$order->update_meta_data( '_wascw_due_amount', max( 0, $total - $advance ) );
	$order->update_meta_data( '_wascw_original_total', $total );
	$order->update_meta_data( '_wascw_payment_mode', wascw_get_chosen_mode() );
	$order->update_meta_data( '_wascw_advance_paid', 'no' );
	$order->set_total( $advance );
}

// Keep gateway amounts stable through on-hold, verification callbacks and retries.
add_filter( 'woocommerce_order_get_total', 'wascw_override_total_for_payment_gateway', 999, 2 );
/**
 * Override total for payment gateway.
 *
 * @param float|string $total total value.
 * @param WC_Order     $order order value.
 *
 * @return array Result value.
 */
function wascw_override_total_for_payment_gateway( $total, $order ) {
	if ( $order instanceof WC_Order && 'yes' === $order->get_meta( '_wascw_is_advance_order' ) ) {
		$advance = $order->get_meta( '_wascw_advance_amount' );
		if ( '' !== $advance && null !== $advance ) {
			return (float) $advance;
		}
	}
	return $total;
}

add_action( 'woocommerce_payment_complete', 'wascw_confirm_advance_payment' );
/**
 * Confirm advance payment.
 *
 * @param int $order_id order id value.
 *
 * @return void No return value.
 */
function wascw_confirm_advance_payment( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || 'yes' !== $order->get_meta( '_wascw_is_advance_order' ) || 'yes' === $order->get_meta( '_wascw_advance_paid' ) ) {
		return;
	}
	$texts = wascw_get_texts();
	$order->update_meta_data( '_wascw_advance_paid', 'yes' );
	$order->add_order_note(
		wp_strip_all_tags(
			wascw_format_text(
				$texts['order_note'],
				array(
					'advance_amount' => wc_price( $order->get_meta( '_wascw_advance_amount' ), array( 'currency' => $order->get_currency() ) ),
					'due_amount'     => wc_price( $order->get_meta( '_wascw_due_amount' ), array( 'currency' => $order->get_currency() ) ),
				)
			)
		)
	);
	$order->update_meta_data( '_wascw_note_added', 'yes' );
	$order->save();
}
add_action(
	'woocommerce_order_status_changed',
	function ( $order_id, $from, $to ) {
		if ( in_array( $to, array( 'processing', 'completed' ), true ) ) {
			wascw_confirm_advance_payment( $order_id );
		}
	},
	20,
	3
);

/**
 * Get advance status label.
 *
 * @param WC_Order $order order value.
 *
 * @return string Result value.
 */
function wascw_get_advance_status_label( $order ) {
	$confirmed = wascw_advance_is_confirmed( $order );
	if ( 'full_payment' === $order->get_meta( '_wascw_payment_mode' ) ) {
		return $confirmed ? __( 'Payment confirmed', 'wpmethods-advance-shipping-cod' ) : __( 'Payment awaiting confirmation', 'wpmethods-advance-shipping-cod' );
	}
	return $confirmed ? __( 'Advance payment confirmed', 'wpmethods-advance-shipping-cod' ) : __( 'Advance payment awaiting confirmation', 'wpmethods-advance-shipping-cod' );
}

add_filter(
	'woocommerce_get_order_item_totals',
	function ( $rows, $order ) {
		if ( 'yes' !== $order->get_meta( '_wascw_is_advance_order' ) ) {
			return $rows;
		}
		$currency = array( 'currency' => $order->get_currency() );
		if ( isset( $rows['order_total'] ) ) {
			$rows['order_total']['label'] = __( 'Advance payment:', 'wpmethods-advance-shipping-cod' );
			$rows['order_total']['value'] = wc_price( $order->get_meta( '_wascw_advance_amount' ), $currency );
		}
		$rows['wascw_full_total'] = array(
			'label' => __( 'Full order total:', 'wpmethods-advance-shipping-cod' ),
			'value' => wc_price( $order->get_meta( '_wascw_original_total' ), $currency ),
		);
		if ( wascw_get_due_collected( $order ) > 0 ) {
			$rows['wascw_collected'] = array(
				'label' => __( 'Delivery payments collected:', 'wpmethods-advance-shipping-cod' ),
				'value' => wc_price( wascw_get_due_collected( $order ), $currency ),
			);
		}
		$rows['wascw_due'] = array(
			'label' => __( 'Outstanding on delivery:', 'wpmethods-advance-shipping-cod' ),
			'value' => wc_price( wascw_get_outstanding_due( $order ), $currency ),
		);
		return $rows;
	},
	10,
	2
);
