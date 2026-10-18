<?php
/**
 * Load Plugin Check runtime dependencies for this CLI process only.
 * Does not activate or deactivate plugins in the site's saved options.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

WP_CLI::add_wp_hook(
	'option_active_plugins',
	static function () {
		return array(
			'woocommerce/woocommerce.php',
			'plugin-check/plugin.php',
			basename( dirname( __DIR__ ) ) . '/wpmethods-advance-shipping-cod.php',
		);
	}
);

require dirname( __DIR__, 2 ) . '/plugin-check/cli.php';
