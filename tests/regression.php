<?php
/** Standalone regression checks: php tests/regression.php. No database writes. */
if ( 'cli' !== PHP_SAPI ) { exit; }
define( 'ABSPATH', __DIR__ );
define( 'DB_NAME', 'regression-test' );
$options = array();
$hooks = array();
$orders = array();
$errors = array();
function add_action( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $name ][ $priority ][] = array( $callback, $args ); }
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { add_action( $name, $callback, $priority, $args ); }
function do_action( $name, ...$args ) {
	$callbacks = $GLOBALS['hooks'][ $name ] ?? array(); ksort( $callbacks );
	foreach ( $callbacks as $group ) { foreach ( $group as $entry ) { call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) ); } }
}
function apply_filters( $name, $value, ...$args ) {
	$callbacks = $GLOBALS['hooks'][ $name ] ?? array(); ksort( $callbacks );
	foreach ( $callbacks as $group ) { foreach ( $group as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) ); } }
	return $value;
}
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function __( $text, $domain = '' ) { return $text; }
function plugin_basename( $file ) { return basename( $file ); }
function wc_format_decimal( $number, $decimals = false ) { return number_format( (float) $number, false === $decimals ? 2 : $decimals, '.', '' ); }
function wc_get_price_decimals() { return 2; }
function wc_price( $number, $args = array() ) { return 'BDT ' . number_format( (float) $number, 2 ); }
function wp_parse_args( $saved, $defaults ) { return array_merge( $defaults, $saved ); }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#([a-f0-9]{3}){1,2}$/i', $value ) ? $value : null; }
function checked( $checked, $current = true, $display = true ) { $html = (string) $checked === (string) $current ? 'checked="checked"' : ''; if ( $display ) { echo $html; } return $html; }
function get_current_user_id() { return 7; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_html__( $value, $domain = '' ) { return esc_html( $value ); }
function esc_html_e( $value, $domain = '' ) { echo esc_html( $value ); }
function esc_attr_e( $value, $domain = '' ) { echo esc_attr( $value ); }
function wp_kses_post( $value ) { return $value; }
function wp_create_nonce( $action ) { return 'test-nonce'; }
function wp_generate_uuid4() { return '00000000-0000-4000-8000-000000000001'; }
function get_userdata( $id ) { return (object) array( 'display_name' => 'Test Staff' ); }
function get_date_from_gmt( $date, $format ) { return $date; }
function current_user_can( $capability, ...$args ) { return $GLOBALS['can_edit'] ?? true; }
class TestJsonResponse extends RuntimeException {
	public $success; public $data;
	public function __construct( $success, $data ) { $this->success = $success; $this->data = $data; }
}
function wp_send_json_error( $data, $status = 200 ) { throw new TestJsonResponse( false, $data ); }
function wp_send_json_success( $data ) { throw new TestJsonResponse( true, $data ); }
function check_ajax_referer( $action, $field ) { if ( 'test-nonce' !== ( $_POST[ $field ] ?? '' ) ) { wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 ); } }
class TestDatabase {
	public $prefix = 'test_'; public $available = true; public $released = 0;
	public function prepare( $query, ...$args ) { return $query; }
	public function get_var( $query ) { if ( false !== strpos( $query, 'RELEASE_LOCK' ) ) { $this->released++; return 1; } return $this->available ? 1 : 0; }
}
function wc_add_notice( $text, $type ) { $GLOBALS['errors'][] = $text; }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function is_checkout() { return true; }
function is_checkout_pay_page() { return ! empty( $GLOBALS['pay_page'] ); }
function absint( $value ) { return abs( (int) $value ); }
function get_query_var( $key ) { return 1; }
function is_order_received_page() { return false; }
function wc_get_order( $id ) { return $GLOBALS['orders'][ $id ] ?? false; }
function wc_get_chosen_shipping_method_for_package( $key, $package ) { return $GLOBALS['chosen_shipping']; }
function WC() { return $GLOBALS['wc']; }

require __DIR__ . '/../../woocommerce/includes/legacy/class-wc-legacy-cart.php';
class TestCart extends WC_Legacy_Cart {
	public function get_total( $context = 'view' ) { return apply_filters( 'woocommerce_cart_get_total', 1100.0 ); }
	public function get_totals() { return array( 'total' => 1100.0 ); }
	public function get_shipping_total() { return 90; }
	public function get_shipping_tax() { return 10; }
	public function get_shipping_packages() { return array(); }
}
class TestSession {
	private $data = array();
	public function get( $key, $default = null ) { return $this->data[ $key ] ?? $default; }
	public function set( $key, $value ) { $this->data[ $key ] = $value; }
}
class TestShipping {
	public function get_packages() { return array( array( 'rates' => array( 'flat_rate:1' => true ) ) ); }
}
class TestWoo {
	public $cart; public $session;
	public function __construct() { $this->cart = new TestCart(); $this->session = new TestSession(); }
	public function shipping() { return new TestShipping(); }
}
class TestShippingItem {
	public function get_method_id() { return 'flat_rate'; }
	public function get_instance_id() { return 1; }
}
class WC_Order {
	public $meta = array(); public $total = 1100; public $notes = array(); public $method = 'bkash'; public $status = 'pending';
	public function meta_exists( $key ) { return array_key_exists( $key, $this->meta ); }
	public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function delete_meta_data( $key ) { unset( $this->meta[ $key ] ); }
	public function get_items( $type ) { return array( new TestShippingItem() ); }
	public function get_shipping_total() { return 90; }
	public function get_shipping_tax() { return 10; }
	public function get_total( $context = 'view' ) { return 'edit' === $context ? $this->total : apply_filters( 'woocommerce_order_get_total', $this->total, $this ); }
	public function set_total( $value ) { $this->total = $value; }
	public function get_payment_method() { return $this->method; }
	public function get_currency() { return 'BDT'; }
	public function get_id() { return 2; }
	public function get_status() { return $this->status; }
	public function get_total_refunded() { return $this->meta['test_refunds'] ?? 0; }
	public function get_total_tax() { return 40; }
	public function is_paid() { return in_array( $this->status, array( 'processing', 'completed' ), true ); }
	public function add_order_note( $note ) { $this->notes[] = $note; }
	public function save() {}
}
$wc = new TestWoo();
$chosen_shipping = 'flat_rate:1';
require __DIR__ . '/../particod.php';
$checks = 0;
function expect( $actual, $expected, $label ) {
	$GLOBALS['checks']++;
	if ( is_numeric( $actual ) && is_numeric( $expected ) ? abs( $actual - $expected ) > 0.00001 : $actual !== $expected ) {
		throw new RuntimeException( $label . ': expected ' . json_encode( $expected ) . ', got ' . json_encode( $actual ) );
	}
}
$options[ PARTICOD_OPTION_KEY ] = array( 'flat_rate:1' );
expect( particod_get_checkout_position(), 'before_total', 'Summary defaults above order total' );
$options['particod_checkout_position'] = 'subtotal';
expect( particod_get_checkout_position(), 'before_total', 'Previous subtotal position adopts requested placement' );
$options['particod_checkout_position_updated'] = true;
expect( particod_get_checkout_position(), 'subtotal', 'Explicit admin position remains configurable' );
unset( $options['particod_checkout_position'], $options['particod_checkout_position_updated'] );
$options[ PARTICOD_CUSTOM_ADVANCE_PRICE_KEY ] = 150;
$options['particod_advance_percentage'] = 20;
foreach ( array( 'shipping_method' => 100, 'custom_price' => 150, 'percentage' => 220, 'both' => 250, 'shipping_percentage' => 320, 'combined' => 470, 'full_payment' => 1100 ) as $mode => $amount ) {
	expect( particod_calculate_advance_amount( 100, 1100, $mode ), $amount, $mode );
}
$options['particod_percentage_base'] = 'excluding_shipping';
expect( particod_calculate_advance_amount( 100, 1100, 'shipping_percentage' ), 300, 'Percentage excluding shipping tax' );
$options['particod_advance_percentage'] = 200;
expect( particod_calculate_advance_amount( 100, 1100, 'combined' ), 1100, 'Combined capped at full total' );
$options[ PARTICOD_CUSTOM_ADVANCE_PRICE_KEY ] = -10;
expect( particod_calculate_advance_amount( 100, 1100, 'custom_price' ), 0, 'Negative fixed amount' );
expect( particod_calculate_advance_amount( 100, 0, 'shipping_method' ), 0, 'Fully discounted order' );
$options['particod_advance_percentage'] = 12.345;
expect( particod_calculate_advance_amount( 0, 999.99, 'percentage' ), 123.45, 'Currency precision' );
$options['particod_advance_percentage'] = 20;
$options['particod_percentage_base'] = 'order_total';
$options[ PARTICOD_CUSTOM_ADVANCE_PRICE_KEY ] = 150;
$options['particod_payment_modes'] = array( 'shipping_method', 'percentage' );
particod_update_checkout_choice( 'particod_payment_mode=percentage&advance_amount=1' );
expect( particod_get_chosen_mode(), 'percentage', 'Enabled choice saved' );
expect( particod_calculate_advance_amount( 100, 1100 ), 220, 'Ignore client amount' );
particod_update_checkout_choice( 'particod_payment_mode=combined' );
expect( particod_get_chosen_mode(), 'percentage', 'Disabled choice ignored' );
$_POST['particod_payment_mode'] = 'combined';
do_action( 'woocommerce_checkout_process' );
expect( count( $errors ), 1, 'Reject tampered final checkout' );
unset( $_POST['particod_payment_mode'] );
expect( WC()->cart->total, 1100, 'Legacy cart total outside gateway' );
do_action( 'woocommerce_before_template_part', 'checkout/payment-method.php' );
expect( WC()->cart->total, 220, 'Actual WooCommerce legacy cart getter in gateway fields' );
expect( WC()->cart->get_total( 'edit' ), 220, 'Gateway numeric getter' );
expect( particod_get_cart_grand_total(), 1100, 'Full total retained while rendering' );
do_action( 'woocommerce_after_template_part', 'checkout/payment-method.php' );
expect( WC()->cart->total, 1100, 'Full cart total after payment fields' );
$gateways = array( 'cod' => true, 'bkash' => true, 'nagad' => true, 'stripe' => true );
expect( array_keys( particod_hide_cod_when_selected_shipping( $gateways ) ), array( 'bkash', 'nagad', 'stripe' ), 'All non-COD gateways' );
$options['particod_gateways'] = array( 'bkash', 'nagad' );
expect( array_keys( particod_hide_cod_when_selected_shipping( $gateways ) ), array( 'bkash', 'nagad' ), 'Multiple gateway sources' );
$options['particod_show_cod'] = 'yes';
expect( array_keys( particod_hide_cod_when_selected_shipping( $gateways ) ), array( 'cod', 'bkash', 'nagad' ), 'Optional COD remains alongside allowed advance gateways' );
$without_cod = $gateways; unset( $without_cod['cod'] );
expect( array_keys( particod_hide_cod_when_selected_shipping( $without_cod ) ), array( 'bkash', 'nagad' ), 'Do not force an unavailable COD gateway' );
particod_update_checkout_choice( 'payment_method=cod' );
expect( particod_is_cod_checkout_selected(), true, 'COD choice saved during checkout refresh' );
expect( particod_modify_cart_total_display( 'BDT 1,100.00' ), 'BDT 1,100.00', 'COD displays full checkout total' );
expect( particod_modify_order_review_heading( 'Total', 'Total', 'woocommerce' ), 'Total', 'COD retains normal Total label' );
$cod_order = new WC_Order(); $cod_order->method = 'cod';
particod_prepare_advance_order( $cod_order );
expect( $cod_order->get_total( 'edit' ), 1100, 'COD order stores full amount' );
expect( $cod_order->get_meta( '_particod_is_advance_order' ), '', 'COD order has no advance flag' );
particod_update_checkout_choice( 'payment_method=bkash' );
expect( particod_is_cod_checkout_selected(), false, 'Switching back to online restores advance flow' );
$options['particod_show_cod'] = 'no';
$order = new WC_Order(); $orders[1] = $order;
do_action( 'woocommerce_checkout_create_order', $order, array() );
expect( $order->get_total( 'edit' ), 220, 'Persist advance before gateway processing' );
expect( $order->get_meta( '_particod_original_total' ), 1100, 'Store full order value' );
expect( $order->get_meta( '_particod_due_amount' ), 880, 'Balance on delivery' );
$pay_page = true;
$options['particod_show_cod'] = 'yes';
expect( array_keys( particod_hide_cod_when_selected_shipping( $gateways ) ), array( 'bkash', 'nagad' ), 'Advance retry cannot switch unpaid balance to COD' );
$options['particod_show_cod'] = 'no';
do_action( 'woocommerce_before_template_part', 'checkout/payment-method.php' );
expect( WC()->cart->total, 220, 'Manual gateway retry uses saved advance' );
do_action( 'woocommerce_after_template_part', 'checkout/payment-method.php' );
$pay_page = false;
$order->status = 'on-hold';
do_action( 'woocommerce_order_status_changed', 1, 'pending', 'on-hold', $order );
expect( $order->get_total(), 220, 'On-hold callback amount unchanged' );
expect( $order->get_meta( '_particod_advance_paid' ), 'no', 'On-hold is not paid' );
expect( count( $order->notes ), 0, 'No paid note before verification' );
$order->status = 'processing';
do_action( 'woocommerce_order_status_changed', 1, 'on-hold', 'processing', $order );
do_action( 'woocommerce_payment_complete', 1 );
expect( $order->get_meta( '_particod_advance_paid' ), 'yes', 'Payment confirmed' );
expect( count( $order->notes ), 1, 'Confirmation note is idempotent' );
expect( $order->get_total( 'edit' ), 220, 'Persisted amount stays stable after confirmation' );
$rows = apply_filters( 'woocommerce_get_order_item_totals', array( 'order_total' => array( 'label' => 'Total', 'value' => '220' ) ), $order );
expect( $rows['particod_due']['value'], 'BDT 880.00', 'Receipt includes delivery balance' );
$order->total = 1100; $order->method = 'cod';
particod_prepare_advance_order( $order );
expect( $order->get_meta( '_particod_is_advance_order' ), '', 'Remove flags when reusing order for COD' );
$wc->session->set( 'particod_payment_mode', 'shipping_method' );
expect( particod_calculate_due_amount( 1100, 100 ), 1000, 'Shipping balance includes full totals' );
$chosen_shipping = 'flat_rate:2';
expect( array_keys( particod_hide_cod_when_selected_shipping( $gateways ) ), array_keys( $gateways ), 'Unselected shipping unaffected' );
$chosen_shipping = 'flat_rate:1';

// Exercise the installed gateway implementations with their real private fee calculators.
if ( file_exists( __DIR__ . '/../../bangladeshi-payments-mobile/includes/class-bkash-gateway.php' ) ) {
	class WC_Payment_Gateway {}
	require __DIR__ . '/../../bangladeshi-payments-mobile/includes/class-bkash-gateway.php';
	require __DIR__ . '/../../bangladeshi-payments-mobile/includes/class-nagad-gateway.php';
	$wc->session->set( 'particod_payment_mode', 'percentage' );
	do_action( 'woocommerce_before_template_part', 'checkout/payment-method.php' );
	foreach ( array( 'WC_Gateway_Bkash' => 'bkash', 'WC_Gateway_Nagad' => 'nagad' ) as $class => $id ) {
		$reflection = new ReflectionClass( $class );
		$gateway = $reflection->newInstanceWithoutConstructor();
		$gateway->{ 'apply_' . $id . '_charge' } = true;
		$gateway->{ $id . '_charge' } = 2;
		$method = $reflection->getMethod( 'calculate_total_payment' );
		$method->setAccessible( true );
		expect( $method->invoke( $gateway ), '224.40 BDT', $id . ' fee calculated on advance, not full cart' );
		$fees = $reflection->getMethod( 'calculate_' . $id . '_fees' );
		$fees->setAccessible( true );
		$fee_value = $fees->invoke( $gateway );
		expect( (float) $fee_value, 4.40, $id . ' advance fee amount' );
	}
	do_action( 'woocommerce_after_template_part', 'checkout/payment-method.php' );
}
$options['particod_payment_modes'] = array( 'custom_price' ); $options[ PARTICOD_CUSTOM_ADVANCE_PRICE_KEY ] = 0;
expect( array_keys( particod_hide_cod_when_selected_shipping( $gateways ) ), array( 'cod' ), 'Zero advance uses COD only' );

// Due ledger and native Analytics must reconcile without changing gateway charges.
$options['particod_payment_modes'] = array( 'percentage' );
$wc->session->set( 'particod_payment_mode', 'percentage' );
$ledger_order = new WC_Order(); $orders[2] = $ledger_order;
particod_prepare_advance_order( $ledger_order );
function expect_rejected( $callback, $label ) {
	try { $callback(); } catch ( InvalidArgumentException $error ) { expect( true, true, $label ); return; }
	throw new RuntimeException( 'Expected rejection: ' . $label );
}
expect_rejected( function () use ( $ledger_order ) { particod_add_collection_entry( $ledger_order, 100, 'cash', '', 'collection-pending-0001' ); }, 'Unverified advance cannot receive delivery collections' );
particod_confirm_advance_payment( 2 );
expect( particod_add_collection_entry( $ledger_order, 300, 'courier', 'settlement-100', 'collection-request-0001' ), true, 'Partial collection accepted' );
expect( particod_get_due_collected( $ledger_order ), 300, 'Collected amount' );
expect( particod_get_outstanding_due( $ledger_order ), 580, 'Partial outstanding balance' );
expect( particod_add_collection_entry( $ledger_order, 300, 'courier', 'settlement-100', 'collection-request-0001' ), false, 'Duplicate request is idempotent' );
expect( count( particod_get_collections( $ledger_order ) ), 1, 'Duplicate does not add ledger entry' );
expect( $ledger_order->get_total(), 220, 'Collection does not increase gateway charge' );
expect_rejected( function () use ( $ledger_order ) { particod_add_collection_entry( $ledger_order, 581, 'cash', '', 'collection-overpay-0001' ); }, 'Overcollection rejected' );
expect_rejected( function () use ( $ledger_order ) { particod_add_collection_entry( $ledger_order, -10, 'cash', '', 'collection-negative-01' ); }, 'Negative collection rejected' );
expect_rejected( function () use ( $ledger_order ) { particod_add_collection_entry( $ledger_order, 100, 'invalid', '', 'collection-badmethod-01' ); }, 'Unknown collection method rejected' );
particod_add_collection_entry( $ledger_order, 580, 'cash', '', 'collection-request-0002' );
expect( particod_get_outstanding_due( $ledger_order ), 0, 'Full balance settled' );
particod_add_collection_entry( $ledger_order, 0, '', '', 'collection-reversal-01', 'collection-request-0001' );
expect( particod_get_outstanding_due( $ledger_order ), 300, 'Reversal restores exact outstanding amount' );
expect( count( particod_get_collections( $ledger_order ) ), 3, 'Reversal preserves collection history' );
expect_rejected( function () use ( $ledger_order ) { particod_add_collection_entry( $ledger_order, 0, '', '', 'collection-reversal-02', 'collection-request-0001' ); }, 'Cannot reverse twice' );
$ledger_order->status = 'completed';
do_action( 'woocommerce_order_status_changed', 2, 'processing', 'completed', $ledger_order );
expect( particod_get_outstanding_due( $ledger_order ), 300, 'Completed status does not collect balance' );
$ledger_order->meta['test_refunds'] = 10;
expect_rejected( function () use ( $ledger_order ) { particod_add_collection_entry( $ledger_order, 300, 'cash', '', 'collection-refund-test' ); }, 'Refunded order requires reconciliation' );
unset( $ledger_order->meta['test_refunds'] );
$stats = apply_filters( 'woocommerce_analytics_update_order_stats_data', array( 'total_sales' => 220, 'net_total' => 90, 'tax_total' => 40, 'shipping_total' => 90 ), $ledger_order );
expect( $stats['total_sales'], 1100, 'Analytics full order value' );
expect( $stats['net_total'], 970, 'Analytics net revenue uses full value less tax and shipping' );
expect( $stats['tax_total'], 40, 'Analytics tax unchanged' );
expect( $stats['shipping_total'], 90, 'Analytics shipping unchanged' );
particod_add_collection_entry( $ledger_order, 300, 'bank', 'receipt-2', 'collection-request-0003' );
expect( particod_get_outstanding_due( $ledger_order ), 0, 'Collect after corrected entry' );
$stats_after = particod_full_value_analytics( $stats, $ledger_order );
expect( $stats_after['total_sales'], 1100, 'Collections do not create duplicate sales' );
expect( $ledger_order->get_total( 'edit' ), 220, 'Stored gateway total unchanged after full settlement' );

// Admin totals must distinguish the sale value, confirmed receipts and gateway charge.
function expect_admin_totals( $order, $full, $advance, $collected, $paid, $due ) {
	$html = particod_get_admin_payment_totals( $order );
	foreach ( array( 'order-total' => $full, 'advance-paid' => $advance, 'due-collected' => $collected, 'paid' => $paid, 'outstanding' => $due ) as $key => $amount ) {
		preg_match( '/class="particod-admin-payment-total particod-admin-' . $key . '">(.*?)<\/tr>/s', $html, $match );
		expect( isset( $match[1] ) && false !== strpos( strip_tags( $match[1] ), wc_price( $amount ) ), true, 'Admin ' . $key . ' reconciles to ' . $amount );
	}
}
expect_admin_totals( $ledger_order, 1100, 220, 880, 1100, 0 );
$display_order = new WC_Order();
$display_order->total = 80;
$display_order->meta = array( '_particod_is_advance_order' => 'yes', '_particod_original_total' => 202, '_particod_advance_amount' => 80, '_particod_due_amount' => 122, '_particod_advance_paid' => 'no' );
expect_admin_totals( $display_order, 202, 0, 0, 0, 122 );
$display_order->meta['_particod_advance_paid'] = 'yes';
expect_admin_totals( $display_order, 202, 80, 0, 80, 122 );
particod_add_collection_entry( $display_order, 50, 'cash', '', 'display-partial-0001' );
expect_admin_totals( $display_order, 202, 80, 50, 130, 72 );
particod_add_collection_entry( $display_order, 72, 'courier', '', 'display-settled-0001' );
expect_admin_totals( $display_order, 202, 80, 122, 202, 0 );
particod_add_collection_entry( $display_order, 0, '', '', 'display-reverse-0001', 'display-partial-0001' );
expect_admin_totals( $display_order, 202, 80, 72, 152, 50 );
expect( $display_order->get_total(), 80, 'Display correction preserves gateway charge after reversal' );
expect( $display_order->get_status(), 'pending', 'Collection display does not change fulfillment status' );
$display_order->meta['test_refunds'] = 10;
expect_admin_totals( $display_order, 202, 80, 72, 152, 50 );
expect( strpos( particod_get_admin_payment_totals( $display_order ), 'before refunds' ) !== false, true, 'Refunded orders explain gross receipts and gateway refund scope' );
$orders[4] = new WC_Order();
ob_start(); do_action( 'woocommerce_admin_order_totals_after_tax', 4 ); $ordinary_totals = ob_get_clean();
expect( $ordinary_totals, '', 'Ordinary order totals are not replaced' );
ob_start(); do_action( 'woocommerce_admin_order_totals_after_tax', 999 ); $missing_totals = ob_get_clean();
expect( $missing_totals, '', 'Missing order does not render payment totals' );
$orders[5] = $display_order;
ob_start(); do_action( 'woocommerce_admin_order_totals_after_tax', 5 ); $rendered_totals = ob_get_clean();
expect( $rendered_totals, particod_get_admin_payment_totals( $display_order ), 'Admin hook renders ledger totals on page loads and item reloads' );
$receipt = apply_filters( 'woocommerce_get_order_item_totals', array( 'order_total' => array( 'label' => '', 'value' => '' ) ), $ledger_order );
expect( $receipt['particod_due']['value'], 'BDT 0.00', 'Customer receipt updates remaining balance' );
expect( $receipt['particod_collected']['value'], 'BDT 880.00', 'Customer receipt includes collected balance' );
$ordinary = new WC_Order();
expect( particod_full_value_analytics( array( 'total_sales' => 1100 ), $ordinary ), array( 'total_sales' => 1100 ), 'Ordinary order analytics unaffected' );
$edited_order = new WC_Order();
particod_prepare_advance_order( $edited_order );
$edited_order->total = 1200;
do_action( 'woocommerce_order_after_calculate_totals', true, $edited_order );
expect( $edited_order->get_meta( '_particod_original_total' ), 1200, 'Admin recalculation updates full sale value' );
expect( particod_get_outstanding_due( $edited_order ), 980, 'Admin item edits update delivery balance' );
expect( $edited_order->get_total( 'edit' ), 220, 'Admin recalculation preserves agreed gateway amount' );

// AJAX security and lock lifecycle checks without touching a real database.
$wpdb = new TestDatabase();
$api_order = new WC_Order(); $orders[3] = $api_order;
particod_prepare_advance_order( $api_order ); $api_order->update_meta_data( '_particod_advance_paid', 'yes' );
$_POST = array( 'order_id' => 3, 'nonce' => 'test-nonce', 'operation' => 'record', 'amount' => 100, 'method' => 'cash', 'reference' => 'receipt-api', 'request_id' => 'collection-api-request1' );
function api_response() {
	try { particod_ajax_order_collection(); } catch ( TestJsonResponse $response ) { return $response; }
	throw new RuntimeException( 'AJAX did not respond' );
}
$can_edit = false;
expect( api_response()->success, false, 'Collection endpoint rejects unauthorized staff' );
expect( count( particod_get_collections( $api_order ) ), 0, 'Unauthorized request does not write' );
$can_edit = true; $_POST['nonce'] = 'bad';
expect( api_response()->success, false, 'Collection endpoint rejects invalid nonce' );
$_POST['nonce'] = 'test-nonce'; $wpdb->available = false;
expect( api_response()->success, false, 'Concurrent collection rejected while lock held' );
expect( count( particod_get_collections( $api_order ) ), 0, 'Lock rejection does not write' );
$wpdb->available = true;
$response = api_response();
expect( $response->success, true, 'Authorized AJAX collection succeeds' );
expect( $response->data['totals_html'], particod_get_admin_payment_totals( $api_order ), 'AJAX returns current totals for immediate display updates' );
expect( particod_get_outstanding_due( $api_order ), 780, 'AJAX updates exact balance' );
expect( $wpdb->released, 1, 'Successful request releases database lock' );
expect( api_response()->success, true, 'AJAX retry safely succeeds' );
expect( count( particod_get_collections( $api_order ) ), 1, 'AJAX retry does not duplicate collection' );
$_POST['request_id'] = 'collection-api-request2'; $_POST['amount'] = 1000;
expect( api_response()->success, false, 'Endpoint rejects overcollection' );
expect( $wpdb->released, 3, 'Validation failure releases database lock' );
$_POST['amount'] = array( 100 );
expect( api_response()->success, false, 'Malformed request rejected before acquiring lock' );

// Reject malformed fields and invalid operations without changing the ledger.
$valid_request = array( 'order_id' => 3, 'nonce' => 'test-nonce', 'operation' => 'record', 'amount' => 1, 'method' => 'cash', 'reference' => 'security-test', 'request_id' => 'security-request-0001' );
foreach ( array( 'order_id', 'nonce', 'operation', 'amount', 'method', 'reference', 'request_id', 'entry_id' ) as $field ) {
	$_POST = $valid_request;
	$_POST[ $field ] = array( 'unexpected' );
	expect( api_response()->success, false, 'Array input rejected for ' . $field );
}
foreach ( array( array( 'operation' => 'delete' ), array( 'method' => 'invalid' ), array( 'request_id' => '../bad-request' ), array( 'operation' => 'reverse' ), array( 'amount' => -10 ), array( 'amount' => '1e999' ) ) as $invalid_fields ) {
	$_POST = array_merge( $valid_request, $invalid_fields );
	expect( api_response()->success, false, 'Invalid collection input rejected: ' . json_encode( $invalid_fields ) );
}
expect( count( particod_get_collections( $api_order ) ), 1, 'Rejected requests never append ledger entries' );
expect( particod_get_outstanding_due( $api_order ), 780, 'Rejected requests preserve the outstanding balance' );
$_POST = array();

// Style settings cannot inject declarations through bounded controls or escape style markup.
$design = particod_sanitize_design_settings( array( 'primary_color' => 'red;display:none', 'border_style' => 'solid;position:fixed', 'padding_top' => -20, 'margin_left' => 3000, 'border_width' => array( 5 ), 'custom_css' => '</style><script>alert(1)</script>' ) );
expect( $design['primary_color'], '#2563eb', 'Invalid design color rejected' );
expect( $design['border_style'], 'solid', 'Border declaration injection rejected' );
expect( $design['padding_top'], 0, 'Negative spacing bounded' );
expect( $design['margin_left'], 200, 'Excessive spacing bounded' );
expect( $design['border_width'], 1, 'Malformed design field rejected' );
expect( strpos( $design['custom_css'], '<' ), false, 'Custom CSS cannot close inline style tag' );
$options['particod_checkout_design'] = array( 'primary_color' => '#123456', 'padding_left' => 7.5, 'custom_css' => '#particod-notice-wrap .particod-payment-card { box-shadow: none; }' );
$css = particod_get_design_css();
expect( strpos( $css, '--particod-primary:#123456' ) !== false, true, 'Saved primary color applied' );
expect( strpos( $css, '--particod-padding:20px 20px 20px 7.5px' ) !== false, true, 'Four-sided saved spacing applied' );
expect( strpos( $css, 'box-shadow: none' ) > strpos( $css, '--particod-primary' ), true, 'Custom CSS appended after generated design' );
unset( $options['particod_checkout_design'] );

$options['particod_payment_modes'] = array( 'shipping_method', 'full_payment' );
particod_update_checkout_choice( 'particod_payment_mode=full_payment' );
expect( particod_get_chosen_mode(), 'full_payment', 'Full payment can be selected alongside partial options' );
$full_order = new WC_Order();
particod_prepare_advance_order( $full_order );
expect( $full_order->get_total( 'edit' ), 1100, 'Full payment gateway receives full order amount' );
expect( particod_get_outstanding_due( $full_order ), 0, 'Full payment leaves no delivery balance' );
expect( particod_get_advance_status_label( $full_order ), 'Payment awaiting confirmation', 'Full payment remains unconfirmed until verification' );
$options[ PARTICOD_ADVANCE_COST_TYPE_KEY ] = 'full_payment';
$wc->session->set( 'particod_payment_mode', '' );
expect( particod_get_chosen_mode(), 'full_payment', 'Full payment supports default selection' );

// Gateway activation is independent of shipping, and does not discount other gateways.
$options[ PARTICOD_OPTION_KEY ] = array();
$chosen_shipping = 'flat_rate:2';
$options['particod_payment_modes'] = array( 'percentage', 'full_payment' );
$options[ PARTICOD_ADVANCE_COST_TYPE_KEY ] = 'percentage';
$options['particod_advance_percentage'] = 20;
$options['particod_percentage_base'] = 'order_total';
$options['particod_gateways'] = array( 'bkash', 'nagad' );
$wc->session->set( 'particod_payment_mode', 'percentage' );
particod_update_checkout_choice( 'payment_method=bkash' );
expect( particod_is_payment_plan_active(), false, 'Shipping rules still apply when gateway option is off' );
$options['particod_gateway_plans'] = 'yes';
expect( particod_is_payment_plan_active(), true, 'Allowed gateway activates plan with no selected shipping rules' );
expect( particod_modify_cart_total_display( 'BDT 1,100.00' ), 'BDT 220.00', 'Allowed gateway checkout amount' );
expect( strpos( particod_gateway_payable_title( 'bKash', 'bkash' ), 'BDT 220.00' ) !== false, true, 'Allowed gateway title shows payable amount' );
expect( particod_gateway_payable_title( 'Stripe', 'stripe' ), 'Stripe', 'Other gateway title unchanged' );
$notice = particod_get_notice_html();
expect( strpos( $notice, 'name="particod_payment_mode"' ) !== false, true, 'Checkout choices render without shipping condition' );
$gateway_order = new WC_Order();
particod_prepare_advance_order( $gateway_order );
expect( $gateway_order->get_total( 'edit' ), 220, 'Allowed gateway order captures correct plan amount' );
expect( $gateway_order->get_meta( '_particod_due_amount' ), 880, 'Gateway plan delivery balance' );
do_action( 'woocommerce_before_template_part', 'checkout/payment-method.php', '', '', array( 'gateway' => (object) array( 'id' => 'stripe' ) ) );
expect( WC()->cart->total, 1100, 'Unallowed gateway fields receive full amount even if an allowed gateway is selected' );
do_action( 'woocommerce_after_template_part', 'checkout/payment-method.php' );
do_action( 'woocommerce_before_template_part', 'checkout/payment-method.php', '', '', array( 'gateway' => (object) array( 'id' => 'nagad' ) ) );
expect( WC()->cart->total, 220, 'Allowed gateway fields receive advance' );
do_action( 'woocommerce_after_template_part', 'checkout/payment-method.php' );
particod_update_checkout_choice( 'payment_method=stripe' );
expect( particod_is_payment_plan_active(), false, 'Switching to an unallowed gateway disables plan' );
expect( particod_modify_cart_total_display( 'BDT 1,100.00' ), 'BDT 1,100.00', 'Other gateway checkout displays full amount' );
$other_order = new WC_Order(); $other_order->method = 'stripe';
particod_prepare_advance_order( $other_order );
expect( $other_order->get_total( 'edit' ), 1100, 'Other gateway order remains full value' );
expect( $other_order->get_meta( '_particod_is_advance_order' ), '', 'Other gateway has no advance flag' );
expect( array_keys( particod_hide_cod_when_selected_shipping( $gateways ) ), array( 'bkash', 'nagad', 'stripe' ), 'Other online gateways remain available in gateway mode' );
$options['particod_gateways'] = array();
expect( particod_is_payment_plan_active(), true, 'Empty gateway selection supports all enabled online gateways' );
particod_update_checkout_choice( 'payment_method=cod' );
expect( particod_is_payment_plan_active(), false, 'COD never receives a partial gateway plan' );
$options['particod_payment_modes'] = array( 'custom_price' );
$options[ PARTICOD_CUSTOM_ADVANCE_PRICE_KEY ] = 0;
$wc->session->set( 'particod_payment_mode', 'custom_price' );
expect( array_keys( particod_hide_cod_when_selected_shipping( $gateways ) ), array_keys( $gateways ), 'Zero gateway plan keeps alternatives available' );
class TestCheckoutErrors { public $items = array(); public function add( $code, $message ) { $this->items[ $code ] = $message; } }
$validation_errors = new TestCheckoutErrors();
particod_validate_gateway_plan( array( 'payment_method' => 'bkash' ), $validation_errors );
expect( isset( $validation_errors->items['particod_zero_advance'] ), true, 'Zero online advance cannot silently charge full amount' );
expect( particod_gateway_payable_title( 'bKash', 'bkash' ), 'bKash', 'Zero online advance does not advertise a payment amount' );
$options['particod_gateway_plans'] = 'no';
$options[ PARTICOD_OPTION_KEY ] = array( 'flat_rate:1' );
$chosen_shipping = 'flat_rate:1';
expect( particod_is_payment_plan_active(), true, 'Turning option off restores original shipping trigger' );

// Saved plugin texts must reach their intended screens without renaming core labels.
$saved_text_test_options = $options;
$saved_text_test_session = clone $wc->session;
$options[ PARTICOD_TEXTS_KEY ] = array(
	'notice_heading' => 'অগ্রিম পরিশোধ',
	'notice_message' => 'অগ্রিম {advance_amount}, বাকি {due_amount}, শিপিং {shipping_amount}',
	'order_review_heading' => 'এখন পরিশোধ',
	'full_order_total_label' => 'অর্ডারের মোট দাম',
	'due_on_delivery_label' => 'ডেলিভারিতে বাকি',
	'order_note' => 'নিশ্চিত {advance_amount}; আদায় {due_amount}',
	'customer_advance_line' => 'অগ্রিম: {advance_amount}',
	'customer_due_line' => 'বাকি: {due_amount}',
	'admin_advance_label' => 'অগ্রিম পেমেন্ট',
	'admin_collected_label' => 'বকেয়া আদায়',
	'admin_due_label' => 'বাকি (COD - Collectible on Delivery)',
	'column_label' => 'বাকি (COD)',
);
$text_order = new WC_Order(); $orders[6] = $text_order;
$text_order->total = 221;
$text_order->meta = array( '_particod_is_advance_order' => 'yes', '_particod_original_total' => 442, '_particod_advance_amount' => 221, '_particod_due_amount' => 221, '_particod_advance_paid' => 'no' );
particod_confirm_advance_payment( 6 );
expect( $text_order->notes[0], 'নিশ্চিত BDT 221.00; আদায় BDT 221.00', 'Custom payment note replaces both placeholders' );
$custom_totals = particod_get_admin_payment_totals( $text_order );
$custom_panel = particod_get_order_payment_panel( $text_order );
foreach ( array( 'admin_advance_label', 'admin_collected_label', 'admin_due_label' ) as $key ) {
	expect( strpos( $custom_totals, esc_html( $options[ PARTICOD_TEXTS_KEY ][ $key ] ) ) !== false, true, 'Saved ' . $key . ' appears in order-item summary' );
	expect( strpos( $custom_panel, esc_html( $options[ PARTICOD_TEXTS_KEY ][ $key ] ) ) !== false, true, 'Saved ' . $key . ' appears in payment panel' );
}
expect( strpos( $custom_panel, 'অর্ডারের মোট দাম' ) !== false, true, 'Full value metric uses saved full-order label' );
expect( strpos( $custom_totals, '>Order Total:</td>' ) !== false, true, 'Text settings retain Order Total label' );
expect( strpos( $custom_totals, '>Paid:</td>' ) !== false, true, 'Text settings retain Paid label' );
$core_columns = array( 'order_status' => 'Status', 'order_total' => 'Total' );
foreach ( array( 'manage_edit-shop_order_columns', 'manage_woocommerce_page_wc-orders_columns' ) as $hook ) {
	$custom_columns = apply_filters( $hook, $core_columns );
	expect( $custom_columns['particod_due'], 'বাকি (COD)', 'Custom due column label on ' . $hook );
	unset( $custom_columns['particod_due'] );
	expect( $custom_columns, $core_columns, 'Core order-list headings remain unchanged on ' . $hook );
}
particod_add_collection_entry( $text_order, 100, 'cash', '', 'custom-label-collect-01' );
foreach ( array( false, true ) as $plain_text ) {
	ob_start(); particod_customer_order_advance_info( $text_order, false, $plain_text ); $customer_text = ob_get_clean();
	expect( strpos( $customer_text, 'অগ্রিম: BDT 221.00' ) !== false, true, 'Customer advance text uses saved wording and placeholder' );
	expect( strpos( $customer_text, 'বাকি: BDT 121.00' ) !== false, true, 'Customer due text uses current outstanding balance' );
}
$options['particod_payment_modes'] = array( 'shipping_method' );
particod_update_checkout_choice( 'payment_method=bkash&particod_payment_mode=shipping_method' );
$custom_notice = particod_get_notice_html();
foreach ( array( 'অগ্রিম পরিশোধ', 'এখন পরিশোধ', 'অর্ডারের মোট দাম', 'ডেলিভারিতে বাকি', 'অগ্রিম BDT 100.00, বাকি BDT 1,000.00, শিপিং BDT 100.00' ) as $text ) {
	expect( strpos( $custom_notice, $text ) !== false, true, 'Custom checkout text renders: ' . $text );
}
$options[ PARTICOD_TEXTS_KEY ]['admin_advance_label'] = 'একই লেবেল';
$options[ PARTICOD_TEXTS_KEY ]['admin_collected_label'] = 'একই লেবেল';
$options[ PARTICOD_TEXTS_KEY ]['admin_due_label'] = 'একই লেবেল';
expect( substr_count( particod_get_order_payment_panel( $text_order ), '<div><span>' ), 4, 'Identical labels do not remove payment metrics' );
$options[ PARTICOD_TEXTS_KEY ]['admin_advance_label'] = '<script>alert(1)</script> & :';
$escaped_totals = particod_get_admin_payment_totals( $text_order );
expect( strpos( $escaped_totals, '<script>' ), false, 'Custom labels cannot inject script markup' );
expect( strpos( $escaped_totals, '&lt;script&gt;alert(1)&lt;/script&gt; &amp;:' ) !== false, true, 'Labels are escaped and get only one trailing colon' );
$options = $saved_text_test_options; $wc->session = $saved_text_test_session; unset( $orders[6] );

if ( in_array( '--preview', $argv, true ) ) {
	$panel = particod_get_order_payment_panel( $api_order );
	$css = file_get_contents( __DIR__ . '/../assets/css/particod-order-payments.css' );
	file_put_contents( __DIR__ . '/payment-panel-preview.html', '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Order payment panel preview</title><style>body{margin:0;background:#f0f0f1;font:13px Arial;color:#1e293b}main{max-width:1150px;margin:40px auto;background:white;border:1px solid #c3c4c7;border-radius:8px}.button{border:1px solid #2271b1;background:white;color:#2271b1;border-radius:4px;padding:8px 12px;cursor:pointer}.button-primary{background:#2271b1;color:white}input,select{box-sizing:border-box;padding:8px;border:1px solid #8c8f94;border-radius:4px}.widefat{width:100%;border-collapse:collapse;border:1px solid #e2e8f0;text-align:left}.button-link{border:0;background:none;color:#2271b1;cursor:pointer}.description{color:#64748b}' . $css . '</style><main>' . $panel . '</main></html>' );
}

echo "Passed $checks regression checks.\n";
