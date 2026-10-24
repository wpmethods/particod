<?php
/**
 * Plugin Name: PartiCOD
 * Plugin URI:  https://wpmethods.com/
 * Description: Collect shipping, fixed or percentage advance payments through online or manual gateways, with the remaining balance due on delivery.
 * Version:     1.0.0
 * Author:      WP Methods
 * Author URI:  https://wpmethods.com/about
 * Text Domain: particod
 * Requires Plugins: woocommerce
 * Requires at least: 5.6
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 7.4
 *
 * @package PartiCOD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PARTICOD_VERSION', '1.0.0' );
define( 'PARTICOD_OPTION_KEY', 'particod_selected_shipping_methods' );
define( 'PARTICOD_TEXTS_KEY', 'particod_texts' );
define( 'PARTICOD_ADVANCE_COST_TYPE_KEY', 'particod_advance_cost_type' );
define( 'PARTICOD_CUSTOM_ADVANCE_PRICE_KEY', 'particod_custom_advance_price' );
require_once __DIR__ . '/includes/checkout-design.php';

/*
=========================================================
 * 0. Check WooCommerce is active
 * =========================================================
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>';
					esc_html_e( 'The "PartiCOD – Advance Payments for WooCommerce" plugin requires WooCommerce to be active.', 'particod' );
					echo '</p></div>';
				}
			);
		}
	}
);

/*
=========================================================
 * 1. Default customizable texts
 * =========================================================
 */
/**
 * Get default texts.
 *
 * @return array Result value.
 */
function particod_get_default_texts() {
	return array(
		'notice_heading'         => __( 'Payment Plan', 'particod' ),
		'notice_message'         => __( 'Pay {advance_amount} now using your preferred payment method. Pay the remaining {due_amount} on delivery.', 'particod' ),
		'order_review_heading'   => __( 'Payable Now', 'particod' ),
		'full_order_total_label' => __( 'Full order total', 'particod' ),
		'due_on_delivery_label'  => __( 'Due on delivery', 'particod' ),
		'order_note'             => __( 'Advance payment of {advance_amount} confirmed. Collect {due_amount} as COD upon delivery.', 'particod' ),
		'customer_advance_line'  => __( 'Advance payment: {advance_amount}.', 'particod' ),
		'customer_due_line'      => __( 'Pay {due_amount} on delivery (Cash on Delivery).', 'particod' ),
		'admin_advance_label'    => __( 'Advance Payment', 'particod' ),
		'admin_collected_label'  => __( 'Due collected', 'particod' ),
		'admin_due_label'        => __( 'Due (COD - Collectible on Delivery)', 'particod' ),
		'column_label'           => __( 'Due (COD)', 'particod' ),
	);
}

/**
 * Get texts.
 *
 * @return array Result value.
 */
function particod_get_texts() {
	$saved = get_option( PARTICOD_TEXTS_KEY, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	$defaults = particod_get_default_texts();
	// Refresh previously saved default wording while preserving merchant customization.
	$legacy = array(
		'notice_heading'        => 'Advance Shipping Payment',
		'notice_message'        => 'Cash on Delivery is not available for this shipping method. Please pay the shipping charge of {shipping_amount} online now. The remaining product price of {due_amount} will be collected as Cash on Delivery at the time of delivery.',
		'order_note'            => 'Advance shipping charge of {advance_amount} has been paid online. The product price of {due_amount} must be collected as COD upon delivery.',
		'customer_advance_line' => 'Shipping charge of {advance_amount} has been paid online in advance.',
		'customer_due_line'     => 'The product price of {due_amount} must be paid in cash (Cash on Delivery) at the time of delivery.',
		'admin_advance_label'   => 'Advance Paid (Shipping)',
	);
	foreach ( $legacy as $key => $value ) {
		if ( isset( $saved[ $key ] ) && $value === $saved[ $key ] ) {
			$saved[ $key ] = $defaults[ $key ];
		}
	}
	return wp_parse_args( $saved, $defaults );
}

/**
 * Get advance cost type.
 *
 * @return string Result value.
 */
function particod_get_advance_cost_type() {
	$cost_type = get_option( PARTICOD_ADVANCE_COST_TYPE_KEY, 'shipping_method' );
	$allowed   = array_keys( particod_get_payment_modes() );

	if ( ! in_array( $cost_type, $allowed, true ) ) {
		return 'shipping_method';
	}

	return $cost_type;
}

/**
 * Get custom advance price.
 *
 * @return float Result value.
 */
function particod_get_custom_advance_price() {
	$price = get_option( PARTICOD_CUSTOM_ADVANCE_PRICE_KEY, '' );
	if ( ! is_numeric( $price ) ) {
		return 0;
	}
	return max( 0, (float) $price );
}

/**
 * Get payment modes.
 *
 * @return array Result value.
 */
function particod_get_payment_modes() {
	return array(
		'shipping_method'     => __( 'Shipping cost only', 'particod' ),
		'percentage'          => __( 'Percentage', 'particod' ),
		'custom_price'        => __( 'Fixed amount', 'particod' ),
		'both'                => __( 'Shipping + fixed amount', 'particod' ),
		'shipping_percentage' => __( 'Shipping + percentage', 'particod' ),
		'combined'            => __( 'Shipping + percentage + fixed amount', 'particod' ),
		'full_payment'        => __( 'Full Payment', 'particod' ),
	);
}

/**
 * Get enabled modes.
 *
 * @return array Result value.
 */
function particod_get_enabled_modes() {
	$modes = array_values( array_intersect( (array) get_option( 'particod_payment_modes', array( particod_get_advance_cost_type() ) ), array_keys( particod_get_payment_modes() ) ) );
	return $modes ? $modes : array( particod_get_advance_cost_type() );
}

/**
 * Get custom mode labels.
 *
 * @return array Result value.
 */
function particod_get_custom_mode_labels() {
	$saved  = get_option( 'particod_payment_mode_labels', array() );
	$labels = array();
	foreach ( particod_get_payment_modes() as $mode => $default ) {
		$labels[ $mode ] = is_array( $saved ) && isset( $saved[ $mode ] ) && is_string( $saved[ $mode ] ) ? sanitize_text_field( $saved[ $mode ] ) : '';
	}
	return $labels;
}

/**
 * Get checkout mode labels.
 *
 * @return array Result value.
 */
function particod_get_checkout_mode_labels() {
	$labels     = particod_get_payment_modes();
	$custom     = particod_get_custom_mode_labels();
	$percentage = max( 0, min( 100, (float) get_option( 'particod_advance_percentage', 20 ) ) );
	foreach ( $labels as $mode => $label ) {
		if ( '' !== $custom[ $mode ] ) {
			$labels[ $mode ] = str_replace( '{percentage}', (string) $percentage, $custom[ $mode ] );
		} elseif ( in_array( $mode, array( 'percentage', 'shipping_percentage', 'combined' ), true ) ) {
			$labels[ $mode ] .= ' (' . $percentage . '%)';
		}
	}
	return $labels;
}

/**
 * Get chosen mode.
 *
 * @return string Result value.
 */
function particod_get_chosen_mode() {
	$modes  = particod_get_enabled_modes();
	$chosen = function_exists( 'WC' ) && WC()->session ? WC()->session->get( 'particod_payment_mode' ) : '';
	return in_array( $chosen, $modes, true ) ? $chosen : ( in_array( particod_get_advance_cost_type(), $modes, true ) ? particod_get_advance_cost_type() : $modes[0] );
}

/**
 * Calculate advance amount.
 *
 * @param float       $shipping_total shipping total value.
 * @param float|null  $grand_total grand total value.
 * @param string|null $mode mode value.
 *
 * @return float Result value.
 */
function particod_calculate_advance_amount( $shipping_total = 0, $grand_total = null, $mode = null ) {
	$shipping_total = max( 0, (float) $shipping_total );
	if ( null === $grand_total ) {
		$grand_total = function_exists( 'WC' ) && WC()->cart ? particod_get_cart_grand_total() : $shipping_total;
	}
	$grand_total = max( 0, (float) $grand_total );
	$mode        = null === $mode ? particod_get_chosen_mode() : $mode;
	if ( 'full_payment' === $mode ) {
		return (float) wc_format_decimal( $grand_total, wc_get_price_decimals() );
	}
	$percentage = max( 0, min( 100, (float) get_option( 'particod_advance_percentage', 20 ) ) );
	$base       = 'excluding_shipping' === get_option( 'particod_percentage_base', 'order_total' ) ? max( 0, $grand_total - $shipping_total ) : $grand_total;
	$amount     = 0;
	if ( in_array( $mode, array( 'shipping_method', 'both', 'shipping_percentage', 'combined' ), true ) ) {
		$amount += $shipping_total;
	}
	if ( in_array( $mode, array( 'custom_price', 'both', 'combined' ), true ) ) {
		$amount += particod_get_custom_advance_price();
	}
	if ( in_array( $mode, array( 'percentage', 'shipping_percentage', 'combined' ), true ) ) {
		$amount += $base * $percentage / 100;
	}
	return (float) wc_format_decimal( min( $grand_total, max( 0, $amount ) ), wc_get_price_decimals() );
}

/**
 * Get cart grand total.
 *
 * @return float Result value.
 */
function particod_get_cart_grand_total() {
	// Read unfiltered totals to avoid recursion while gateway payment fields are rendered.
	$totals = WC()->cart->get_totals();
	return isset( $totals['total'] ) ? (float) $totals['total'] : 0;
}

/**
 * Calculate due amount.
 *
 * @param float|null $grand_total grand total value.
 * @param float      $shipping_total shipping total value.
 *
 * @return float Result value.
 */
function particod_calculate_due_amount( $grand_total, $shipping_total = 0 ) {
	$grand_total    = (float) $grand_total;
	$advance_amount = particod_calculate_advance_amount( $shipping_total, $grand_total );
	return max( 0, $grand_total - $advance_amount );
}

/**
 * Format text.
 *
 * @param string $text text value.
 * @param array  $replacements replacements value.
 *
 * @return string Result value.
 */
function particod_format_text( $text, $replacements ) {
	foreach ( $replacements as $key => $value ) {
		$text = str_replace( '{' . $key . '}', $value, $text );
	}
	return $text;
}

/*
=========================================================
 * 2. Admin settings pages
 * =========================================================
 */
add_action( 'admin_menu', 'particod_add_admin_menu' );
/**
 * Add admin menu.
 *
 * @return void No return value.
 */
function particod_add_admin_menu() {
	add_submenu_page(
		'woocommerce',
		__( 'Advance Shipping & COD', 'particod' ),
		__( 'Advance Shipping & COD', 'particod' ),
		'manage_woocommerce',
		'particod-settings',
		'particod_render_settings_page'
	);
}

add_action( 'admin_enqueue_scripts', 'particod_enqueue_admin_assets' );
/**
 * Enqueue admin assets.
 *
 * @param string $hook_suffix hook suffix value.
 *
 * @return void No return value.
 */
function particod_enqueue_admin_assets( $hook_suffix ) {
	if ( 'woocommerce_page_particod-settings' !== $hook_suffix ) {
		return;
	}

	$plugin_url = plugin_dir_url( __FILE__ );
	wp_enqueue_style( 'particod-admin-css', $plugin_url . 'assets/css/particod-admin.css', array(), PARTICOD_VERSION );
	wp_enqueue_script( 'particod-admin-js', $plugin_url . 'assets/js/particod-admin.js', array( 'jquery' ), PARTICOD_VERSION, true );
	if ( 'design' === particod_get_settings_tab() ) {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'particod-design-preview', $plugin_url . 'assets/css/particod-checkout.css', array(), PARTICOD_VERSION );
		wp_enqueue_script( 'particod-design-admin', $plugin_url . 'assets/js/particod-design-admin.js', array( 'jquery', 'wp-color-picker' ), PARTICOD_VERSION, true );
		$editor_settings = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
		wp_localize_script( 'particod-design-admin', 'particodDesignEditor', array( 'settings' => $editor_settings ) );
	}
}

/**
 * Read the view-only settings tab identifier.
 *
 * @return string Selected settings tab.
 */
function particod_get_settings_tab() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This query chooses a read-only view and does not save settings.
	$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	return 'design' === $tab ? 'design' : 'settings';
}

/**
 * Get all shipping methods.
 *
 * @return array Result value.
 */
function particod_get_all_shipping_methods() {
	$all = array();

	if ( class_exists( 'WC_Shipping_Zones' ) ) {
		$zones = WC_Shipping_Zones::get_zones();
		foreach ( $zones as $zone_data ) {
			$zone_name = ! empty( $zone_data['zone_name'] ) ? $zone_data['zone_name'] : __( '(Unnamed)', 'particod' );
			if ( ! empty( $zone_data['shipping_methods'] ) ) {
				foreach ( $zone_data['shipping_methods'] as $method ) {
					$all[] = array(
						'rate_id' => $method->get_rate_id(),
						'zone'    => $zone_name,
						'title'   => $method->get_title() ? $method->get_title() : $method->method_title,
						'enabled' => 'yes' === $method->enabled,
					);
				}
			}
		}
	}

	// Include shipping methods from the fallback shipping zone.
	if ( class_exists( 'WC_Shipping_Zone' ) ) {
		$zone0    = new WC_Shipping_Zone( 0 );
		$methods0 = $zone0->get_shipping_methods( true );
		foreach ( $methods0 as $method ) {
			$all[] = array(
				'rate_id' => $method->get_rate_id(),
				'zone'    => __( 'Rest of the World', 'particod' ),
				'title'   => $method->get_title() ? $method->get_title() : $method->method_title,
				'enabled' => 'yes' === $method->enabled,
			);
		}
	}

	return $all;
}

/**
 * Get checkout positions.
 *
 * @return array Result value.
 */
function particod_get_checkout_positions() {
	return array(
		'before_total'          => __( 'Above order total (default)', 'particod' ),
		'subtotal'              => __( 'Below subtotal', 'particod' ),
		'before_payment'        => __( 'Above payment methods', 'particod' ),
		'after_payment_title'   => __( 'Below payment title', 'particod' ),
		'after_payment_methods' => __( 'Below payment methods', 'particod' ),
	);
}

/**
 * Get checkout position.
 *
 * @return string Result value.
 */
function particod_get_checkout_position() {
	$position = get_option( 'particod_checkout_position', 'before_total' );
	// Move the previous subtotal placement to the requested default until explicitly saved again.
	if ( 'subtotal' === $position && ! get_option( 'particod_checkout_position_updated', false ) ) {
		return 'before_total';
	}
	return array_key_exists( $position, particod_get_checkout_positions() ) ? $position : 'before_total';
}

/**
 * Render workflow settings.
 *
 * @return void No return value.
 */
function particod_render_workflow_settings() {
	$custom_labels = particod_get_custom_mode_labels();
	?>
	<tr><th><?php esc_html_e( 'Payment options', 'particod' ); ?></th><td>
		<p class="description particod-options-help"><?php esc_html_e( 'Enable the options customers can use. Choose one default from the enabled options.', 'particod' ); ?></p>
		<div class="particod-options-scroll" role="region" aria-label="<?php esc_attr_e( 'Payment options', 'particod' ); ?>" tabindex="0"><table class="particod-options-table">
			<thead><tr><th scope="col"><?php esc_html_e( 'Enable', 'particod' ); ?></th><th scope="col"><?php esc_html_e( 'Advance payment', 'particod' ); ?></th><th scope="col"><?php esc_html_e( 'Checkout label', 'particod' ); ?></th><th scope="col"><?php esc_html_e( 'Default', 'particod' ); ?></th></tr></thead>
			<tbody><?php foreach ( particod_get_payment_modes() as $mode => $label ) : ?>
			<tr>
				<td><input type="checkbox" id="particod-enable-<?php echo esc_attr( $mode ); ?>" name="particod_payment_modes[]" value="<?php echo esc_attr( $mode ); ?>" <?php checked( in_array( $mode, particod_get_enabled_modes(), true ) ); ?> aria-label="
				<?php
				echo esc_attr(
					sprintf( /* translators: %s: Payment calculation option name. */
						__( 'Enable %s', 'particod' ),
						$label
					)
				);
				?>
															" /></td>
				<td><label for="particod-enable-<?php echo esc_attr( $mode ); ?>"><?php echo esc_html( $label ); ?></label></td>
				<td><input class="particod-mode-label" type="text" name="particod_mode_labels[<?php echo esc_attr( $mode ); ?>]" value="<?php echo esc_attr( $custom_labels[ $mode ] ); ?>" placeholder="<?php echo esc_attr( $label ); ?>" maxlength="120" aria-label="
				<?php
				echo esc_attr(
					sprintf( /* translators: %s: Payment calculation option name. */
						__( 'Checkout label for %s', 'particod' ),
						$label
					)
				);
				?>
																						" /></td>
				<td data-label="<?php esc_attr_e( 'Default', 'particod' ); ?>"><input type="radio" name="particod_advance_cost_type" value="<?php echo esc_attr( $mode ); ?>" <?php checked( particod_get_advance_cost_type(), $mode ); ?> aria-label="
				<?php
				echo esc_attr(
					sprintf( /* translators: %s: Payment calculation option name. */
						__( 'Use %s as default', 'particod' ),
						$label
					)
				);
				?>
																				" /></td>
			</tr>
			<?php endforeach; ?></tbody>
		</table></div>
		<p class="description" id="particod-choice-help"><?php esc_html_e( 'One enabled option: applied automatically. Multiple enabled options: customers choose at checkout.', 'particod' ); ?></p>
		<p class="description"><?php esc_html_e( 'Rename options using Checkout label. Leave blank for the original name. Use {percentage}% to show the configured percentage in your label.', 'particod' ); ?></p>
	</td></tr>
	<tr class="particod-fixed-setting"><th><label for="particod_custom_advance_price"><?php esc_html_e( 'Fixed advance amount', 'particod' ); ?></label></th><td>
		<input type="number" step="0.01" min="0" class="regular-text" id="particod_custom_advance_price" name="particod_custom_advance_price" value="<?php echo esc_attr( particod_get_custom_advance_price() ); ?>" />
		<p class="description"><?php esc_html_e( 'Amount in your store currency. Added to shipping when a combined option is enabled.', 'particod' ); ?></p>
	</td></tr>
	<tr class="particod-percentage-setting">
		<th><label for="particod_advance_percentage"><?php esc_html_e( 'Advance percentage', 'particod' ); ?></label></th>
		<td>
				<div class="particod-input-suffix"><input id="particod_advance_percentage" name="particod_advance_percentage" type="number" min="0" max="100" step="0.01" value="<?php echo esc_attr( get_option( 'particod_advance_percentage', 20 ) ); ?>" /><span aria-hidden="true">%</span></div>
				<p class="description"><?php esc_html_e( 'Percentage collected in advance. Combined options add shipping or a fixed amount. The advance cannot exceed the order total.', 'particod' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="particod_checkout_position"><?php esc_html_e( 'Checkout position', 'particod' ); ?></label></th>
		<td>
				<select id="particod_checkout_position" name="particod_checkout_position">
				<?php foreach ( particod_get_checkout_positions() as $position => $label ) : ?>
				<option value="<?php echo esc_attr( $position ); ?>" <?php selected( particod_get_checkout_position(), $position ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Uses the classic WooCommerce checkout, including compatible CartFlows checkout templates.', 'particod' ); ?></p>
		</td>
	</tr>
	<tr class="particod-percentage-setting"><th><label for="particod_percentage_base"><?php esc_html_e( 'Calculate percentage of', 'particod' ); ?></label></th><td>
		<select id="particod_percentage_base" name="particod_percentage_base">
			<option value="order_total" <?php selected( get_option( 'particod_percentage_base', 'order_total' ), 'order_total' ); ?>><?php esc_html_e( 'Full order total after discounts, including tax and fees', 'particod' ); ?></option>
			<option value="excluding_shipping" <?php selected( get_option( 'particod_percentage_base', 'order_total' ), 'excluding_shipping' ); ?>><?php esc_html_e( 'Order total excluding shipping and shipping tax', 'particod' ); ?></option>
		</select>
	</td></tr>
	<tr><th><?php esc_html_e( 'Cash on Delivery', 'particod' ); ?></th><td>
		<label><input type="checkbox" name="particod_show_cod" value="yes" <?php checked( particod_should_show_cod() ); ?> /> <?php esc_html_e( 'Allow full payment on delivery', 'particod' ); ?></label>
		<p class="description"><?php esc_html_e( 'Show COD alongside advance payment gateways. Customers choosing COD pay the full order amount on delivery, with no advance. COD must be enabled and available in WooCommerce payment settings.', 'particod' ); ?></p>
	</td></tr>
	<tr><th><?php esc_html_e( 'Payment gateway plans', 'particod' ); ?></th><td>
		<label><input type="checkbox" id="particod_gateway_plans" name="particod_gateway_plans" value="yes" <?php checked( particod_uses_gateway_plans() ); ?> /> <?php esc_html_e( 'Show payment options and payable cost with allowed gateways', 'particod' ); ?></label>
		<p class="description"><?php esc_html_e( 'Use the gateways selected below instead of shipping method rules. Selecting an allowed gateway shows the payment options; other online gateways collect the full order total. Leave all gateways unchecked to apply to every online gateway.', 'particod' ); ?></p>
	</td></tr>
	<tr><th><?php esc_html_e( 'Advance payment gateways', 'particod' ); ?></th><td><fieldset>
		<?php
		foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) :
			if ( 'cod' === $id ) {
				continue;
			}
			?>
		<label style="display:block;margin-bottom:8px"><input type="checkbox" name="particod_gateways[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, (array) get_option( 'particod_gateways', array() ), true ) ); ?> /> <?php echo esc_html( $gateway->get_title() ); ?> <code><?php echo esc_html( $id ); ?></code></label>
		<?php endforeach; ?>
		<p class="description"><?php esc_html_e( 'Leave all unchecked to allow every enabled non-COD gateway. Select several to offer multiple payment methods. Gateway availability rules still apply.', 'particod' ); ?></p>
	</fieldset></td></tr>

	<?php
}

/**
 * Render settings page.
 *
 * @return void No return value.
 */
function particod_render_settings_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$design_notice = particod_handle_design_save();
	$save_notices  = $design_notice ? array( $design_notice ) : array();
	$active_tab    = particod_get_settings_tab();

	/* ---- Save shipping method selection ---- */
	if ( isset( $_POST['particod_save'] ) && check_admin_referer( 'particod_save_settings', 'particod_nonce' ) ) {
		$selected             = isset( $_POST['particod_methods'] ) && is_array( $_POST['particod_methods'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['particod_methods'] ) ) : array();
		$advance_cost_type    = isset( $_POST['particod_advance_cost_type'] ) ? sanitize_key( wp_unslash( $_POST['particod_advance_cost_type'] ) ) : 'shipping_method';
		$custom_advance_price = isset( $_POST['particod_custom_advance_price'] ) ? sanitize_text_field( wp_unslash( $_POST['particod_custom_advance_price'] ) ) : '';

		if ( ! array_key_exists( $advance_cost_type, particod_get_payment_modes() ) ) {
			$advance_cost_type = 'shipping_method';
		}

		update_option( PARTICOD_OPTION_KEY, $selected );
		update_option( PARTICOD_CUSTOM_ADVANCE_PRICE_KEY, max( 0, (float) wc_format_decimal( $custom_advance_price ) ) );
		$modes = isset( $_POST['particod_payment_modes'] ) && is_array( $_POST['particod_payment_modes'] ) ? array_intersect( array_map( 'sanitize_key', wp_unslash( $_POST['particod_payment_modes'] ) ), array_keys( particod_get_payment_modes() ) ) : array();
		if ( ! $modes ) {
			$modes = array( $advance_cost_type );
		} elseif ( ! in_array( $advance_cost_type, $modes, true ) ) {
			$advance_cost_type = reset( $modes );
		}
		update_option( PARTICOD_ADVANCE_COST_TYPE_KEY, $advance_cost_type );
		update_option( 'particod_payment_modes', array_values( array_unique( $modes ) ) );
		if ( isset( $_POST['particod_mode_labels'] ) && is_array( $_POST['particod_mode_labels'] ) ) {
			$posted_labels = map_deep( wp_unslash( $_POST['particod_mode_labels'] ), 'sanitize_text_field' );
			$mode_labels   = array();
			foreach ( particod_get_payment_modes() as $mode => $label ) {
				$mode_labels[ $mode ] = isset( $posted_labels[ $mode ] ) && is_string( $posted_labels[ $mode ] ) ? sanitize_text_field( $posted_labels[ $mode ] ) : '';
			}
			update_option( 'particod_payment_mode_labels', $mode_labels );
		}
		update_option( 'particod_advance_percentage', isset( $_POST['particod_advance_percentage'] ) ? max( 0, min( 100, (float) wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['particod_advance_percentage'] ) ) ) ) ) : 20 );
		update_option( 'particod_percentage_base', isset( $_POST['particod_percentage_base'] ) && 'excluding_shipping' === $_POST['particod_percentage_base'] ? 'excluding_shipping' : 'order_total' );
		$position = isset( $_POST['particod_checkout_position'] ) ? sanitize_key( wp_unslash( $_POST['particod_checkout_position'] ) ) : 'before_total';
		update_option( 'particod_checkout_position', array_key_exists( $position, particod_get_checkout_positions() ) ? $position : 'before_total' );
		update_option( 'particod_checkout_position_updated', true );
		$gateway_ids = isset( $_POST['particod_gateways'] ) && is_array( $_POST['particod_gateways'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['particod_gateways'] ) ) : array();
		update_option( 'particod_gateways', array_values( array_diff( $gateway_ids, array( 'cod' ) ) ) );
		update_option( 'particod_show_cod', isset( $_POST['particod_show_cod'] ) && 'yes' === $_POST['particod_show_cod'] ? 'yes' : 'no' );
		update_option( 'particod_gateway_plans', isset( $_POST['particod_gateway_plans'] ) && 'yes' === $_POST['particod_gateway_plans'] ? 'yes' : 'no' );
		update_option( 'particod_delete_data_on_uninstall', isset( $_POST['particod_delete_data_on_uninstall'] ) && 'yes' === $_POST['particod_delete_data_on_uninstall'] ? 'yes' : 'no' );
		$save_notices[] = __( 'Shipping method and advance cost settings saved successfully.', 'particod' );
	}

	/* ---- Save customizable texts ---- */
	if ( isset( $_POST['particod_reset_texts'] ) && check_admin_referer( 'particod_save_texts', 'particod_texts_nonce' ) ) {
		update_option( PARTICOD_TEXTS_KEY, particod_get_default_texts() );
		$save_notices[] = __( 'Text settings reset to defaults successfully.', 'particod' );
	} elseif ( isset( $_POST['particod_save_texts'] ) && check_admin_referer( 'particod_save_texts', 'particod_texts_nonce' ) ) {
		$defaults = particod_get_default_texts();
		$texts    = array();
		foreach ( $defaults as $key => $default_value ) {
			if ( isset( $_POST['particod_text'][ $key ] ) ) {
				$texts[ $key ] = sanitize_textarea_field( wp_unslash( $_POST['particod_text'][ $key ] ) );
			} else {
				$texts[ $key ] = $default_value;
			}
		}
		update_option( PARTICOD_TEXTS_KEY, $texts );
		$save_notices[] = __( 'Text settings saved successfully.', 'particod' );
	}

	$selected = get_option( PARTICOD_OPTION_KEY, array() );
	$methods  = particod_get_all_shipping_methods();
	$texts    = particod_get_texts();
	?>
	<div class="wrap particod-settings-wrap">

		<header class="particod-hero">
			<div class="particod-brand">
				<img class="particod-brand-logo" src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'assets/img/icon.webp' ); ?>" alt="" width="80" height="80" />
				<div class="particod-brand-copy">
					<div class="particod-brand-title"><h1><?php esc_html_e( 'PartiCOD', 'particod' ); ?></h1><span class="particod-version"><?php echo esc_html( 'v' . PARTICOD_VERSION ); ?></span></div>
					<p class="particod-tagline"><?php esc_html_e( 'Advance payments. Clear delivery balances.', 'particod' ); ?></p>
					<p class="particod-hero-description"><?php esc_html_e( 'Set up payment plans, choose when they apply, and make checkout your own.', 'particod' ); ?></p>
				</div>
			</div>
			<div class="particod-header-context"><span class="dashicons dashicons-cart" aria-hidden="true"></span><?php esc_html_e( 'For WooCommerce', 'particod' ); ?></div>
		</header>

		<?php foreach ( $save_notices as $message ) : ?>
		<div class="particod-save-notice" role="status"><p><?php echo esc_html( $message ); ?></p></div>
		<?php endforeach; ?>
		<nav class="particod-settings-tabs" aria-label="<?php esc_attr_e( 'PartiCOD settings', 'particod' ); ?>">
			<a class="particod-settings-tab <?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=particod-settings' ) ); ?>" aria-current="<?php echo 'settings' === $active_tab ? 'page' : 'false'; ?>">
				<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span><span><strong><?php esc_html_e( 'Payment settings', 'particod' ); ?></strong><small><?php esc_html_e( 'Plans, gateways & shipping', 'particod' ); ?></small></span>
			</a>
			<a class="particod-settings-tab <?php echo 'design' === $active_tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=particod-settings&tab=design' ) ); ?>" aria-current="<?php echo 'design' === $active_tab ? 'page' : 'false'; ?>">
				<span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span><span><strong><?php esc_html_e( 'Checkout design', 'particod' ); ?></strong><small><?php esc_html_e( 'Colors, layout & preview', 'particod' ); ?></small></span>
			</a>
		</nav>
		<?php
		if ( 'design' === $active_tab ) :
			particod_render_checkout_design_tab(); else :
				?>
		<div class="particod-settings-grid">
			<div class="particod-main-column">
				<div class="particod-card">
					<div class="particod-card-heading"><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span><div><h2><?php esc_html_e( 'Payment settings', 'particod' ); ?></h2><p class="description"><?php esc_html_e( 'Choose what customers pay now and how the remaining balance is collected.', 'particod' ); ?></p></div></div>
					<form method="post">
									<?php wp_nonce_field( 'particod_save_settings', 'particod_nonce' ); ?>
						<table class="form-table" style="max-width:900px;">
										<?php particod_render_workflow_settings(); ?>
						</table>

						<div class="particod-shipping-section">
							<h3 style="margin-bottom:8px;"><?php esc_html_e( 'Shipping Methods', 'particod' ); ?></h3>
							<p style="margin-top:0; color:#64748b;">
											<?php esc_html_e( 'Select the shipping methods that should trigger the advance payment flow at checkout.', 'particod' ); ?>
							</p>
										<?php if ( empty( $methods ) ) : ?>
								<div class="notice notice-warning"><p>
											<?php esc_html_e( 'No shipping methods found. Please set up shipping zones/methods first under WooCommerce → Settings → Shipping.', 'particod' ); ?>
								</p></div>
							<?php else : ?>
								<div class="particod-table-scroll"><table class="particod-table">
									<thead>
										<tr>
											<th style="width:40px;"></th>
											<th><?php esc_html_e( 'Zone', 'particod' ); ?></th>
											<th><?php esc_html_e( 'Shipping Method', 'particod' ); ?></th>
											<th><?php esc_html_e( 'Status', 'particod' ); ?></th>
											<th><?php esc_html_e( 'Rate ID', 'particod' ); ?></th>
										</tr>
									</thead>
									<tbody>
									<?php foreach ( $methods as $m ) : ?>
										<tr>
											<td>
												<input type="checkbox" name="particod_methods[]"
													value="<?php echo esc_attr( $m['rate_id'] ); ?>"
													<?php checked( in_array( $m['rate_id'], $selected, true ) ); ?> />
											</td>
											<td><?php echo esc_html( $m['zone'] ); ?></td>
											<td><?php echo esc_html( $m['title'] ); ?></td>
											<td>
												<?php if ( $m['enabled'] ) : ?>
													<span class="particod-badge particod-badge--enabled">● <?php esc_html_e( 'Enabled', 'particod' ); ?></span>
												<?php else : ?>
													<span class="particod-badge particod-badge--disabled">● <?php esc_html_e( 'Disabled', 'particod' ); ?></span>
												<?php endif; ?>
											</td>
											<td><code><?php echo esc_html( $m['rate_id'] ); ?></code></td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table></div>
							<?php endif; ?>
						</div>

						<section class="particod-danger-zone" aria-labelledby="particod-danger-heading">
							<h3 id="particod-danger-heading"><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php esc_html_e( 'Danger zone', 'particod' ); ?></h3>
							<label for="particod-delete-data"><input type="checkbox" id="particod-delete-data" name="particod_delete_data_on_uninstall" value="yes" <?php checked( 'yes', get_option( 'particod_delete_data_on_uninstall', 'no' ) ); ?> aria-describedby="particod-delete-data-description" /> <?php esc_html_e( 'Delete plugin data on uninstall', 'particod' ); ?></label>
							<p class="description" id="particod-delete-data-description"><?php esc_html_e( 'Only when you delete this plugin: permanently remove its settings and order payment/collection metadata, including collection history and the stored full order value. Keep unchecked to retain this data. Deactivation never deletes data. This plugin has no separate database tables; WooCommerce orders, stored order totals, order notes and shared tables are not deleted.', 'particod' ); ?></p>
						</section>

						<p class="particod-form-actions">
							<button type="submit" name="particod_save" class="button button-primary">
											<?php esc_html_e( 'Save Settings', 'particod' ); ?>
							</button>
						</p>
					</form>
				</div>

			</div>

			<aside class="particod-sidebar">
				<div class="particod-card particod-texts-card">
					<div class="particod-card-heading"><span class="dashicons dashicons-editor-textcolor" aria-hidden="true"></span><div><h2><?php esc_html_e( 'Messages & labels', 'particod' ); ?></h2><p class="description"><?php esc_html_e( 'Your store, your words.', 'particod' ); ?></p></div></div>
					<p class="description" style="margin-top:0;">
									<?php esc_html_e( 'Personalize the wording customers and staff see. Expand a section to edit its messages.', 'particod' ); ?>
					</p>
					<form method="post">
									<?php wp_nonce_field( 'particod_save_texts', 'particod_texts_nonce' ); ?>
						<details class="particod-text-section" open>
							<summary><span><?php esc_html_e( 'Checkout messages', 'particod' ); ?></span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></summary>
							<table class="form-table"><tbody>
							<tr>
								<th><label for="particod_notice_heading"><?php esc_html_e( 'Checkout Notice Heading', 'particod' ); ?></label></th>
								<td><input type="text" class="regular-text" id="particod_notice_heading" name="particod_text[notice_heading]" value="<?php echo esc_attr( $texts['notice_heading'] ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="particod_notice_message"><?php esc_html_e( 'Checkout Notice Message', 'particod' ); ?></label><br /><small><?php esc_html_e( 'Placeholders: {advance_amount}, {shipping_amount}, {due_amount}', 'particod' ); ?></small></th>
								<td><textarea class="large-text" rows="3" id="particod_notice_message" name="particod_text[notice_message]"><?php echo esc_textarea( $texts['notice_message'] ); ?></textarea></td>
							</tr>
							<tr>
								<th><label for="particod_order_review_heading"><?php esc_html_e( 'Checkout "Total" Label Override', 'particod' ); ?></label></th>
								<td><input type="text" class="regular-text" id="particod_order_review_heading" name="particod_text[order_review_heading]" value="<?php echo esc_attr( $texts['order_review_heading'] ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="particod_full_order_total_label"><?php esc_html_e( 'Full Order Total Label (Checkout & Admin)', 'particod' ); ?></label></th>
								<td><input type="text" class="regular-text" id="particod_full_order_total_label" name="particod_text[full_order_total_label]" value="<?php echo esc_attr( $texts['full_order_total_label'] ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="particod_due_on_delivery_label"><?php esc_html_e( 'Checkout Due on Delivery Label', 'particod' ); ?></label></th>
								<td><input type="text" class="regular-text" id="particod_due_on_delivery_label" name="particod_text[due_on_delivery_label]" value="<?php echo esc_attr( $texts['due_on_delivery_label'] ); ?>" /></td>
							</tr>
							</tbody></table>
						</details>
						<details class="particod-text-section">
							<summary><span><?php esc_html_e( 'Orders & emails', 'particod' ); ?></span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></summary>
							<table class="form-table"><tbody>
							<tr>
								<th><label for="particod_order_note"><?php esc_html_e( 'Order Note (added after payment)', 'particod' ); ?></label><br /><small><?php esc_html_e( 'Placeholders: {advance_amount}, {due_amount}', 'particod' ); ?></small></th>
								<td><textarea class="large-text" rows="2" id="particod_order_note" name="particod_text[order_note]"><?php echo esc_textarea( $texts['order_note'] ); ?></textarea></td>
							</tr>
							<tr>
								<th><label for="particod_customer_advance_line"><?php esc_html_e( 'Customer Order View — Advance Line', 'particod' ); ?></label><br /><small><?php esc_html_e( 'Placeholder: {advance_amount}', 'particod' ); ?></small></th>
								<td><input type="text" class="large-text" id="particod_customer_advance_line" name="particod_text[customer_advance_line]" value="<?php echo esc_attr( $texts['customer_advance_line'] ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="particod_customer_due_line"><?php esc_html_e( 'Customer Order View — Due Line', 'particod' ); ?></label><br /><small><?php esc_html_e( 'Placeholder: {due_amount}', 'particod' ); ?></small></th>
								<td><input type="text" class="large-text" id="particod_customer_due_line" name="particod_text[customer_due_line]" value="<?php echo esc_attr( $texts['customer_due_line'] ); ?>" /></td>
							</tr>
							</tbody></table>
						</details>
						<details class="particod-text-section">
							<summary><span><?php esc_html_e( 'Admin labels', 'particod' ); ?></span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></summary>
							<table class="form-table"><tbody>
							<tr>
								<th><label for="particod_admin_advance_label"><?php esc_html_e( 'Admin Order Page — Advance Label', 'particod' ); ?></label></th>
								<td><input type="text" class="regular-text" id="particod_admin_advance_label" name="particod_text[admin_advance_label]" value="<?php echo esc_attr( $texts['admin_advance_label'] ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="particod_admin_collected_label"><?php esc_html_e( 'Admin Order Page — Collected Label', 'particod' ); ?></label></th>
								<td><input type="text" class="regular-text" id="particod_admin_collected_label" name="particod_text[admin_collected_label]" value="<?php echo esc_attr( $texts['admin_collected_label'] ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="particod_admin_due_label"><?php esc_html_e( 'Admin Order Page — Due Label', 'particod' ); ?></label></th>
								<td><input type="text" class="regular-text" id="particod_admin_due_label" name="particod_text[admin_due_label]" value="<?php echo esc_attr( $texts['admin_due_label'] ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="particod_column_label"><?php esc_html_e( 'Orders List Column Label', 'particod' ); ?></label></th>
								<td><input type="text" class="regular-text" id="particod_column_label" name="particod_text[column_label]" value="<?php echo esc_attr( $texts['column_label'] ); ?>" /></td>
							</tr>
							</tbody></table>
						</details>
						<p class="particod-form-actions"><button type="submit" name="particod_save_texts" class="button button-primary">
											<?php esc_html_e( 'Save Texts', 'particod' ); ?>
							</button>
							<button type="submit" name="particod_reset_texts" class="button" data-confirm="<?php esc_attr_e( 'Reset all customized texts to their defaults? Your custom text changes will be lost.', 'particod' ); ?>">
								<?php esc_html_e( 'Reset Text', 'particod' ); ?>
							</button>
						</p>
					</form>
				</div>
				<div class="particod-card">
					<h3><?php esc_html_e( 'Support & Community', 'particod' ); ?></h3>
					<div class="particod-support-list">
						<a href="https://buymeacoffee.com/ajharrashed" target="_blank" rel="noopener noreferrer">☕ <?php esc_html_e( 'Buy Me a Coffee', 'particod' ); ?></a>
						<a href="https://youtube.com/techtriggerbd" target="_blank" rel="noopener noreferrer">▶ <?php esc_html_e( 'YouTube Channel', 'particod' ); ?></a>
						<a href="https://www.facebook.com/techtriggerr" target="_blank" rel="noopener noreferrer">📘 <?php esc_html_e( 'Facebook Page', 'particod' ); ?></a>
						<a href="https://www.wpmethods.com" target="_blank" rel="noopener noreferrer">🌐 <?php esc_html_e( 'Website', 'particod' ); ?></a>
					</div>
				</div>

				<div class="particod-card">
					<h3><?php esc_html_e( 'Quick Tips', 'particod' ); ?></h3>
					<ul class="particod-tip-list">
						<li><?php esc_html_e( 'Use By Shipping Method for normal shipping-based advance payments.', 'particod' ); ?></li>
						<li><?php esc_html_e( 'Use By Custom Price for a fixed advance amount.', 'particod' ); ?></li>
						<li><?php esc_html_e( 'Use Both to combine shipping cost with a custom amount.', 'particod' ); ?></li>
					</ul>
					<p class="particod-credit"><?php esc_html_e( 'Developed by', 'particod' ); ?> <a href="https://www.wpmethods.com" target="_blank" rel="noopener noreferrer">WP Methods</a></p>
				</div>
			</aside>
		</div>
					<?php endif; ?>
	</div>
	<?php
}

/*
=========================================================
 * 3. Helper functions
 * =========================================================
 */
/**
 * Get selected methods.
 *
 * @return array Result value.
 */
function particod_get_selected_methods() {
	return (array) get_option( PARTICOD_OPTION_KEY, array() );
}

/**
 * Get chosen shipping method ids.
 *
 * @return array Result value.
 */
function particod_get_chosen_shipping_method_ids() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array();
	}

	$chosen_ids = array();

	// IMPORTANT: WC()->cart->get_shipping_packages() only returns RAW package
	// data (cart items + destination) — it never contains a 'rates' key. The
	// packages that actually contain calculated rates (which is what
	// wc_get_chosen_shipping_method_for_package() needs) live in
	// WC()->shipping()->get_packages(), which WooCommerce populates earlier in
	// the checkout template render (wc_cart_totals_shipping_html()). Using
	// get_shipping_packages() here meant $package['rates'] was never set, so
	// this always fell back to an empty session and the notice never showed
	// — even on first page load.
	$packages = array();
	if ( function_exists( 'WC' ) && WC()->shipping() && method_exists( WC()->shipping(), 'get_packages' ) ) {
		$packages = WC()->shipping()->get_packages();
	}

	// Safety net: if rates haven't been calculated yet for any reason
	// (e.g. hook fired very early), calculate them now.
	if ( empty( $packages ) && WC()->shipping() ) {
		$packages = WC()->shipping()->calculate_shipping( WC()->cart->get_shipping_packages() );
	}

	foreach ( $packages as $package_key => $package ) {
		$method_id = '';

		if ( function_exists( 'wc_get_chosen_shipping_method_for_package' )
			&& isset( $package['rates'] ) && is_array( $package['rates'] ) ) {
			$method_id = wc_get_chosen_shipping_method_for_package( $package_key, $package );
		} else {
			$session_methods = WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array();
			$method_id       = isset( $session_methods[ $package_key ] ) ? $session_methods[ $package_key ] : '';
		}

		if ( ! empty( $method_id ) ) {
			$chosen_ids[] = $method_id;
		}
	}

	return $chosen_ids;
}

/**
 * Is selected shipping chosen.
 *
 * @return bool Result value.
 */
function particod_is_selected_shipping_chosen() {
	$chosen   = particod_get_chosen_shipping_method_ids();
	$selected = particod_get_selected_methods();

	if ( empty( $chosen ) || empty( $selected ) ) {
		return false;
	}

	foreach ( $chosen as $method ) {
		if ( in_array( $method, $selected, true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Whether payment gateways replace shipping method activation rules.
 *
 * @return bool Whether gateway plans are enabled.
 */
function particod_uses_gateway_plans() {
	return 'yes' === get_option( 'particod_gateway_plans', 'no' );
}

/**
 * Check whether an online gateway supports the configured payment choices.
 *
 * @param string $gateway_id Payment gateway identifier.
 * @return bool Whether the gateway is allowed.
 */
function particod_gateway_allows_plan( $gateway_id ) {
	if ( ! is_string( $gateway_id ) || '' === $gateway_id || 'cod' === $gateway_id ) {
		return false;
	}
	$allowed = (array) get_option( 'particod_gateways', array() );
	return ! $allowed || in_array( $gateway_id, $allowed, true );
}

/**
 * Determine the active payment rule from the selected gateway or shipping method.
 *
 * @return bool Whether checkout uses the payment plan.
 */
function particod_is_payment_plan_active() {
	if ( ! particod_uses_gateway_plans() ) {
		return particod_is_selected_shipping_chosen();
	}
	$gateway = WC()->session ? WC()->session->get( 'chosen_payment_method', '' ) : '';
	return particod_gateway_allows_plan( $gateway );
}

/**
 * Order uses selected shipping.
 *
 * @param WC_Order $order order value.
 *
 * @return array Result value.
 */
function particod_order_uses_selected_shipping( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}
	$selected = particod_get_selected_methods();
	if ( empty( $selected ) ) {
		return false;
	}
	foreach ( $order->get_items( 'shipping' ) as $item ) {
		$rate_id = $item->get_method_id() . ':' . $item->get_instance_id();
		if ( in_array( $rate_id, $selected, true ) ) {
			return true;
		}
	}
	return false;
}

/*
=========================================================
 * 4. Hide COD when selected shipping method is chosen
 * =========================================================
 */
add_filter( 'woocommerce_available_payment_gateways', 'particod_hide_cod_when_selected_shipping' );
/**
 * Should show cod.
 *
 * @return bool Result value.
 */
function particod_should_show_cod() {
	return 'yes' === get_option( 'particod_show_cod', 'no' );
}

/**
 * Is cod checkout selected.
 *
 * @return bool Result value.
 */
function particod_is_cod_checkout_selected() {
	return particod_should_show_cod() && ! is_checkout_pay_page() && WC()->session && 'cod' === WC()->session->get( 'chosen_payment_method' );
}

/**
 * Hide cod when selected shipping.
 *
 * @param array $gateways gateways value.
 *
 * @return array Result value.
 */
function particod_hide_cod_when_selected_shipping( $gateways ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $gateways;
	}
	$advance_order = false;
	if ( is_checkout_pay_page() ) {
		$order         = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
		$advance_order = $order && 'yes' === $order->get_meta( '_particod_is_advance_order' );
		if ( ! $advance_order ) {
			return $gateways;
		}
	}
	if ( ! $advance_order && particod_uses_gateway_plans() && is_checkout() && WC()->cart ) {
		$shipping = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
		if ( particod_calculate_advance_amount( $shipping, particod_get_cart_grand_total() ) <= 0 ) {
			// Keep alternatives available so customers can leave a zero-advance choice.
			return $gateways;
		}
		if ( ! particod_should_show_cod() ) {
			unset( $gateways['cod'] );
		}
		return $gateways;
	}
	if ( $advance_order || ( is_checkout() && WC()->cart && particod_is_payment_plan_active() ) ) {
		if ( ! $advance_order ) {
			$shipping = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
			if ( particod_calculate_advance_amount( $shipping, particod_get_cart_grand_total() ) <= 0 ) {
				return isset( $gateways['cod'] ) ? array( 'cod' => $gateways['cod'] ) : array();
			}
		}
		$show_cod = ! $advance_order && particod_should_show_cod();
		if ( ! $show_cod ) {
			unset( $gateways['cod'] );
		}
		$allowed = (array) get_option( 'particod_gateways', array() );
		if ( $allowed ) {
			if ( $show_cod ) {
				$allowed[] = 'cod';
			}
			$gateways = array_intersect_key( $gateways, array_flip( $allowed ) );
		}
	}
	return $gateways;
}

require_once __DIR__ . '/includes/checkout.php';

require_once __DIR__ . '/includes/order-payments.php';

/*
=========================================================
 * 9. Show Advance / Due info on customer order view & emails
 * =========================================================
 */
add_action( 'woocommerce_order_details_after_order_table', 'particod_customer_order_advance_info' );
add_action( 'woocommerce_email_after_order_table', 'particod_customer_order_advance_info', 10, 3 );
/**
 * Customer order advance info.
 *
 * @param WC_Order $order         Order to display.
 * @param bool     $sent_to_admin Whether this is an administrative email.
 * @param bool     $plain_text    Whether the email uses plain text.
 *
 * @return void
 */
function particod_customer_order_advance_info( $order, $sent_to_admin = false, $plain_text = false ) {
	if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( '_particod_is_advance_order' ) ) {
		return;
	}
	$texts   = particod_get_texts();
	$advance = wc_price( $order->get_meta( '_particod_advance_amount' ), array( 'currency' => $order->get_currency() ) );
	$due     = wc_price( particod_get_outstanding_due( $order ), array( 'currency' => $order->get_currency() ) );

	$advance_line = particod_format_text( $texts['customer_advance_line'], array( 'advance_amount' => $advance ) );
	$due_line     = particod_format_text( $texts['customer_due_line'], array( 'due_amount' => $due ) );
	if ( particod_get_outstanding_due( $order ) <= 0 ) {
		$due_line = (float) $order->get_meta( '_particod_due_amount' ) <= 0 ? __( 'No payment is due on delivery.', 'particod' ) : __( 'Delivery balance fully collected.', 'particod' );
	}
	if ( $plain_text ) {
		echo "\n" . esc_html( particod_get_advance_status_label( $order ) ) . "\n";
		echo esc_html( wp_strip_all_tags( $advance_line ) ) . "\n";
		echo esc_html( wp_strip_all_tags( $due_line ) ) . "\n";
		if ( particod_get_due_collected( $order ) > 0 ) {
			echo esc_html__( 'Delivery payments collected:', 'particod' ) . ' ' . esc_html( wp_strip_all_tags( wc_price( particod_get_due_collected( $order ), array( 'currency' => $order->get_currency() ) ) ) ) . "\n";
		}
		return;
	}

	echo '<div style="margin:15px 0;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">';
	echo '<p>' . esc_html( particod_get_advance_status_label( $order ) ) . '</p>';
	echo '<p style="margin:4px 0;">' . wp_kses_post( $advance_line ) . '</p>';
	if ( particod_get_due_collected( $order ) > 0 ) {
		echo '<p>' . esc_html__( 'Delivery payments collected:', 'particod' ) . ' ' . wp_kses_post( wc_price( particod_get_due_collected( $order ), array( 'currency' => $order->get_currency() ) ) ) . '</p>';
	}
	echo '<p style="margin:4px 0;">' . wp_kses_post( $due_line ) . '</p>';
	echo '</div>';
}

/*
=========================================================
 * 10. Add "Due (COD)" column to the orders list (Classic + HPOS)
 * =========================================================
 */
add_filter( 'manage_edit-shop_order_columns', 'particod_add_due_column', 999 );
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'particod_add_due_column', 999 );
/**
 * Add due column.
 *
 * @param array $columns columns value.
 *
 * @return array Result value.
 */
function particod_add_due_column( $columns ) {
	$texts = particod_get_texts();
	unset( $columns['particod_due'] );
	$ordered = array();
	foreach ( $columns as $key => $label ) {
		$ordered[ $key ] = $label;
		if ( 'order_status' === $key ) {
			$ordered['particod_due'] = esc_html( $texts['column_label'] );
		}
	}
	if ( ! isset( $ordered['particod_due'] ) ) {
		$ordered['particod_due'] = esc_html( $texts['column_label'] );
	}
	return $ordered;
}

add_action( 'manage_shop_order_posts_custom_column', 'particod_render_due_column', 10, 2 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'particod_render_due_column', 10, 2 );
/**
 * Render due column.
 *
 * @param string       $column column value.
 * @param WC_Order|int $order_or_id order or id value.
 *
 * @return void No return value.
 */
function particod_render_due_column( $column, $order_or_id ) {
	if ( 'particod_due' !== $column ) {
		return;
	}
	$order = is_a( $order_or_id, 'WC_Order' ) ? $order_or_id : wc_get_order( $order_or_id );
	if ( ! $order || 'yes' !== $order->get_meta( '_particod_is_advance_order' ) ) {
		echo '&mdash;';
		return;
	}
	$due = particod_get_outstanding_due( $order );
	if ( $due > 0 ) {
		echo '<span class="particod-due-badge" aria-label="' . esc_attr(
			sprintf( /* translators: %s: Formatted outstanding order amount. */
				__( 'Outstanding due: %s', 'particod' ),
				wp_strip_all_tags( wc_price( $due, array( 'currency' => $order->get_currency() ) ) )
			)
		) . '">' . wp_kses_post( wc_price( $due, array( 'currency' => $order->get_currency() ) ) ) . '</span>';
	} else {
		echo '<span class="particod-due-clear">' . wp_kses_post( wc_price( 0, array( 'currency' => $order->get_currency() ) ) ) . '</span>';
	}
}

/*
=========================================================
 * 11. HPOS compatibility declaration
 * =========================================================
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
		}
	}
);

/*
=========================================================
 * 12. Add "Settings" link on the Plugins list page
 * =========================================================
 */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=particod-settings' ) . '">' . esc_html__( 'Settings', 'particod' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
);

/*
=========================================================
 * 13. Display the advance amount as the checkout total
 * =========================================================
 */

// Modify displayed cart total on checkout.
add_filter( 'woocommerce_cart_total', 'particod_modify_cart_total_display', 10, 1 );
/**
 * Modify cart total display.
 *
 * @param float|string $total total value.
 *
 * @return array Result value.
 */
function particod_modify_cart_total_display( $total ) {
	if ( is_checkout() && ! is_order_received_page() && ! is_checkout_pay_page() && ! particod_is_cod_checkout_selected() && particod_is_payment_plan_active() && WC()->cart ) {
		$shipping_total = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
		$advance_amount = particod_calculate_advance_amount( $shipping_total );
		if ( $advance_amount > 0 ) {
			return wc_price( $advance_amount );
		}
	}
	return $total;
}

// Modify the "Total" heading label at checkout.
add_filter( 'gettext', 'particod_modify_order_review_heading', 20, 3 );
/**
 * Modify order review heading.
 *
 * @param string $translated translated value.
 * @param string $original original value.
 * @param string $domain domain value.
 *
 * @return array Result value.
 */
function particod_modify_order_review_heading( $translated, $original, $domain ) {
	if ( function_exists( 'is_checkout' ) && 'Total' === $original && 'woocommerce' === $domain && is_checkout() && ! is_order_received_page() && ! is_checkout_pay_page() ) {
		if ( ! particod_is_cod_checkout_selected() && particod_is_payment_plan_active() ) {
			$texts      = particod_get_texts();
			$translated = esc_html( $texts['order_review_heading'] );
		}
	}
	return $translated;
}

// Also override the total on the "Pay for order" page.
add_action( 'woocommerce_before_pay_action', 'particod_set_order_pay_total' );
/**
 * Set order pay total.
 *
 * @param WC_Order $order order value.
 *
 * @return void No return value.
 */
function particod_set_order_pay_total( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}
	if ( 'yes' === $order->get_meta( '_particod_is_advance_order' ) ) {
		$status = $order->get_status();
		if ( in_array( $status, array( 'pending', 'failed' ), true ) ) {
			$advance = $order->get_meta( '_particod_advance_amount' );
			if ( '' !== $advance && null !== $advance && (float) $advance > 0 ) {
				$order->set_total( (float) $advance );
				$order->save();
			}
		}
	}
}
