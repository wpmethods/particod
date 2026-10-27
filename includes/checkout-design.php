<?php
/**
 * Checkout appearance settings and preview.
 *
 * @package PartiCOD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get design defaults.
 *
 * @return array Result value.
 */
function particod_get_design_defaults() {
	return array(
		'primary_color'    => '#2563eb',
		'background_color' => '#f8fafc',
		'border_color'     => '#dbe4ef',
		'text_color'       => '#1e293b',
		'heading_color'    => '#0f172a',
		'muted_color'      => '#475569',
		'border_style'     => 'solid',
		'border_width'     => 1,
		'border_radius'    => 12,
		'padding_top'      => 20,
		'padding_right'    => 20,
		'padding_bottom'   => 20,
		'padding_left'     => 20,
		'margin_top'       => 16,
		'margin_right'     => 13,
		'margin_bottom'    => 16,
		'margin_left'      => 13,
	);
}

/**
 * Get the allowed numeric design ranges.
 *
 * @return array Minimum and maximum pixel values keyed by setting.
 */
function particod_get_design_numeric_limits() {
	$limits = array(
		'border_width'  => array( 0, 100 ),
		'border_radius' => array( 0, 100 ),
	);
	foreach ( array( 'padding', 'margin' ) as $type ) {
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$limits[ $type . '_' . $side ] = array( 0, 200 );
		}
	}
	return $limits;
}

/**
 * Sanitize design settings.
 *
 * @param array $input input value.
 *
 * @return array Result value.
 */
function particod_sanitize_design_settings( $input ) {
	$defaults = particod_get_design_defaults();
	$clean    = $defaults;
	$input    = is_array( $input ) ? $input : array();
	$limits   = particod_get_design_numeric_limits();
	foreach ( $defaults as $key => $default ) {
		if ( ! isset( $input[ $key ] ) || ! ( is_string( $input[ $key ] ) || is_int( $input[ $key ] ) || is_float( $input[ $key ] ) ) ) {
			continue;
		}
		$value = (string) $input[ $key ];
		if ( false !== strpos( $key, '_color' ) ) {
			$color         = sanitize_hex_color( $value );
			$clean[ $key ] = $color ? $color : $default;
		} elseif ( 'border_style' === $key ) {
			$clean[ $key ] = in_array( $value, array( 'none', 'solid', 'dashed', 'dotted', 'double' ), true ) ? $value : $default;
		} elseif ( isset( $limits[ $key ] ) && preg_match( '/\A-?[0-9]+(?:\.[0-9]+)?\z/', $value ) && is_finite( (float) $value ) ) {
			$number        = round( max( $limits[ $key ][0], min( $limits[ $key ][1], (float) $value ) ) * 2 ) / 2;
			$clean[ $key ] = floor( $number ) === (float) $number ? (int) $number : $number;
		}
	}
	return $clean;
}

// Enforce the same allowlist for every Options API write, including integrations.
add_filter( 'sanitize_option_particod_checkout_design', 'particod_sanitize_design_settings' );

add_action( 'admin_init', 'particod_migrate_design_settings' );
/**
 * Remove obsolete settings on upgrade while retaining supported appearance values.
 *
 * This internal migration uses stored data only; it does not process a request.
 *
 * @return void
 */
function particod_migrate_design_settings() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$saved = get_option( 'particod_checkout_design', array() );
	if ( is_array( $saved ) && array_diff_key( $saved, particod_get_design_defaults() ) ) {
		update_option( 'particod_checkout_design', particod_sanitize_design_settings( $saved ) );
	}
}

/**
 * Get design settings.
 *
 * @return array Result value.
 */
function particod_get_design_settings() {
	return particod_sanitize_design_settings( get_option( 'particod_checkout_design', array() ) );
}

/**
 * Get design css.
 *
 * @return string Result value.
 */
function particod_get_design_css() {
	$design       = particod_get_design_settings();
	$declarations = array();
	foreach ( array(
		'primary_color'    => 'primary',
		'background_color' => 'background',
		'border_color'     => 'border-color',
		'text_color'       => 'text',
		'heading_color'    => 'heading',
		'muted_color'      => 'muted',
	) as $key => $variable ) {
		$declarations[] = '--particod-' . $variable . ':' . $design[ $key ];
	}
	foreach ( array( 'border_width', 'border_radius' ) as $key ) {
		$declarations[] = '--particod-' . str_replace( '_', '-', $key ) . ':' . $design[ $key ] . 'px';
	}
	$declarations[] = '--particod-border-style:' . $design['border_style'];
	foreach ( array( 'padding', 'margin' ) as $type ) {
		$sides = array();
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$sides[] = $design[ $type . '_' . $side ] . 'px';
		}
		$declarations[] = '--particod-' . $type . ':' . implode( ' ', $sides );
	}
	// Selectors, properties and units are defined by the plugin only.
	return '#particod-notice-wrap{' . implode( ';', $declarations ) . ';}' . "\n" . '.particod-gateway-cost{--particod-primary:' . $design['primary_color'] . ';}';
}

/**
 * Handle design save.
 *
 * @return string|void Saved notice, or no value when not submitted.
 */
function particod_handle_design_save() {
	if ( ! isset( $_POST['particod_save_design'] ) && ! isset( $_POST['particod_reset_design'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'particod_save_design', 'particod_design_nonce' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The dedicated sanitizer allows only known colors, presets and bounded numbers.
	$design = isset( $_POST['particod_reset_design'] ) ? particod_get_design_defaults() : particod_sanitize_design_settings( isset( $_POST['particod_design'] ) ? wp_unslash( $_POST['particod_design'] ) : array() );
	update_option( 'particod_checkout_design', $design );
	return __( 'Checkout design saved.', 'particod' );
}

/**
 * Render checkout design tab.
 *
 * @return void No return value.
 */
function particod_render_checkout_design_tab() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$design = particod_get_design_settings();
	$texts  = particod_get_texts();
	$colors = array(
		'primary_color'    => __( 'Primary color', 'particod' ),
		'background_color' => __( 'Background color', 'particod' ),
		'border_color'     => __( 'Border color', 'particod' ),
		'text_color'       => __( 'Text color', 'particod' ),
		'heading_color'    => __( 'Heading color', 'particod' ),
		'muted_color'      => __( 'Description color', 'particod' ),
	);
	?>
	<div class="particod-design-layout">
		<div class="particod-card"><div class="particod-card-heading"><span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span><div><h2><?php esc_html_e( 'Checkout design', 'particod' ); ?></h2><p class="description"><?php esc_html_e( 'Match the advance payment box to your theme.', 'particod' ); ?></p></div></div>
		<form method="post">
			<?php wp_nonce_field( 'particod_save_design', 'particod_design_nonce' ); ?>
			<table class="form-table"><tbody>
			<?php foreach ( $colors as $key => $label ) : ?>
			<tr><th><label for="particod-design-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input type="text" class="particod-color-picker" id="particod-design-<?php echo esc_attr( $key ); ?>" name="particod_design[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $design[ $key ] ); ?>" data-design-key="<?php echo esc_attr( $key ); ?>" data-default-color="<?php echo esc_attr( particod_get_design_defaults()[ $key ] ); ?>" /></td></tr>
			<?php endforeach; ?>
			<tr><th><label for="particod-design-border_style"><?php esc_html_e( 'Border style', 'particod' ); ?></label></th><td><select id="particod-design-border_style" name="particod_design[border_style]" data-design-key="border_style">
			<?php
			foreach ( array(
				'none'   => __( 'None', 'particod' ),
				'solid'  => __( 'Solid', 'particod' ),
				'dashed' => __( 'Dashed', 'particod' ),
				'dotted' => __( 'Dotted', 'particod' ),
				'double' => __( 'Double', 'particod' ),
			) as $value => $label ) :
				?>
	<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $design['border_style'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
			<?php
			foreach ( array(
				'border_width'  => __( 'Border width', 'particod' ),
				'border_radius' => __( 'Corner radius', 'particod' ),
			) as $key => $label ) :
				?>
			<tr><th><label for="particod-design-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input type="number" min="0" max="100" step="0.5" class="small-text" id="particod-design-<?php echo esc_attr( $key ); ?>" name="particod_design[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $design[ $key ] ); ?>" data-design-key="<?php echo esc_attr( $key ); ?>" /> px</td></tr>
			<?php endforeach; ?>
			<?php
			foreach ( array(
				'padding' => __( 'Padding (inside)', 'particod' ),
				'margin'  => __( 'Margin (outside)', 'particod' ),
			) as $type => $label ) :
				?>
			<tr><th><?php echo esc_html( $label ); ?></th><td><div class="particod-spacing-fields">
				<?php
				foreach ( array(
					'top'    => __( 'Top', 'particod' ),
					'right'  => __( 'Right', 'particod' ),
					'bottom' => __( 'Bottom', 'particod' ),
					'left'   => __( 'Left', 'particod' ),
				) as $side => $side_label ) :
						$key = $type . '_' . $side;
					?>
			<label><?php echo esc_html( $side_label ); ?><input type="number" min="0" max="200" step="0.5" name="particod_design[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $design[ $key ] ); ?>" data-design-key="<?php echo esc_attr( $key ); ?>" /></label>
			<?php endforeach; ?></div><p class="description"><?php esc_html_e( 'All spacing values are in pixels.', 'particod' ); ?></p></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<p class="particod-form-actions"><button type="submit" class="button button-primary" name="particod_save_design"><?php esc_html_e( 'Save Design', 'particod' ); ?></button> <button type="submit" class="button" name="particod_reset_design"><?php esc_html_e( 'Reset to defaults', 'particod' ); ?></button></p>
		</form></div>
		<aside class="particod-card particod-design-preview"><div class="particod-card-heading"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><div><h2><?php esc_html_e( 'Live preview', 'particod' ); ?></h2><p class="description"><?php esc_html_e( 'Sample amounts. Changes appear as you edit.', 'particod' ); ?></p></div></div>
			<div class="particod-preview-canvas"><div id="particod-notice-wrap"><section class="particod-payment-card"><h3><?php echo esc_html( $texts['notice_heading'] ); ?></h3><dl class="particod-payment-breakdown"><div><dt><?php echo esc_html( $texts['full_order_total_label'] ); ?></dt><dd><?php echo wp_kses_post( wc_price( 1000 ) ); ?></dd></div><div class="particod-pay-now"><dt><?php echo esc_html( $texts['order_review_heading'] ); ?></dt><dd><?php echo wp_kses_post( wc_price( 200 ) ); ?></dd></div><div><dt><?php echo esc_html( $texts['due_on_delivery_label'] ); ?></dt><dd><?php echo wp_kses_post( wc_price( 800 ) ); ?></dd></div></dl><p class="particod-payment-message"><?php esc_html_e( 'Pay the advance now. Pay the remaining balance on delivery.', 'particod' ); ?></p></section></div></div>
		</aside>
	</div>
	<?php
}
