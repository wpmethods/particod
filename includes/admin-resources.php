<?php
/**
 * Optional WP Methods resources at the bottom of every PartiCOD settings sidebar.
 *
 * @package PartiCOD
 */

defined( 'ABSPATH' ) || exit;

$particod_products        = array(
	array(
		'name'        => 'Refmora',
		'url'         => 'https://wordpress.org/plugins/refmora/',
		'image'       => 'refmora.gif',
		'description' => __( 'Add a referral and affiliate program to WooCommerce.', 'particod' ),
		'type'        => __( 'Free plugin', 'particod' ),
		'source'      => 'WordPress.org',
	),
	array(
		'name'        => 'Order List Table',
		'url'         => 'https://wordpress.org/plugins/order-list-table-elementor-widget',
		'image'       => 'order-list-table.gif',
		'description' => __( 'Display WooCommerce orders and products in customizable tables.', 'particod' ),
		'type'        => __( 'Free plugin', 'particod' ),
		'source'      => 'WordPress.org',
	),
	array(
		'name'        => 'Social Chat Floating Icons',
		'url'         => 'https://wordpress.org/plugins/wpmethods-social-chat-floating-icons/',
		'image'       => 'social-chat.gif',
		'description' => __( 'Help visitors contact you through their favorite chat apps.', 'particod' ),
		'type'        => __( 'Free plugin', 'particod' ),
		'source'      => 'WordPress.org',
	),
	array(
		'name'        => 'Click Shop',
		'url'         => 'https://wpmethods.com/product/click-shop-wordpress-landing-page-type-ecommerce-theme/',
		'image'       => 'click-shop.webp',
		'description' => __( 'Create an ecommerce storefront with WooCommerce.', 'particod' ),
		'type'        => __( 'Paid theme', 'particod' ),
		'source'      => 'WPMethods.com',
	),
);
$particod_community_links = array(
	array(
		'url'   => 'https://buymeacoffee.com/ajharrashed',
		'icon'  => 'coffee',
		'label' => __( 'Buy me a coffee', 'particod' ),
		'class' => 'particod-resource-coffee',
	),
	array(
		'url'   => 'https://www.youtube.com/@wpmethods',
		'icon'  => 'video-alt3',
		'label' => __( 'YouTube - WP Methods', 'particod' ),
		'class' => 'particod-resource-community-link',
	),
	array(
		'url'   => 'https://wpmethods.com',
		'icon'  => 'admin-home',
		'label' => __( 'Visit WP Methods', 'particod' ),
		'class' => 'particod-resource-community-link',
	),
);
?>
<details class="particod-sidebar-resources" open>
	<summary class="particod-resources-summary"><?php esc_html_e( 'Other Products', 'particod' ); ?></summary>
	<div class="particod-sidebar-resource-content">
	<section class="particod-resource-card particod-resource-products" aria-labelledby="particod-products-heading">
		<h2 id="particod-products-heading"><?php esc_html_e( 'Other Products', 'particod' ); ?></h2>
		<p><?php esc_html_e( 'Optional tools from WP Methods.', 'particod' ); ?></p>
		<ul class="particod-resource-product-list">
			<?php foreach ( $particod_products as $particod_product ) : ?>
				<li>
					<a class="particod-resource-product-link" href="<?php echo esc_url( $particod_product['url'] ); ?>" target="_blank" rel="noopener noreferrer">
						<?php if ( $particod_product['image'] ) : ?>
							<img class="particod-resource-product-image" src="<?php echo esc_url( plugins_url( 'assets/img/' . $particod_product['image'], dirname( __DIR__ ) . '/particod.php' ) ); ?>" width="40" height="40" alt="" loading="lazy" decoding="async">
						<?php else : ?>
							<span class="particod-resource-product-image particod-resource-product-icon dashicons dashicons-editor-table" aria-hidden="true"></span>
						<?php endif; ?>
						<span class="particod-resource-product-copy">
							<strong><?php echo esc_html( $particod_product['name'] ); ?></strong>
							<span class="particod-resource-product-description"><?php echo esc_html( $particod_product['description'] ); ?></span>
							<span class="particod-resource-product-meta"><span class="particod-resource-product-type"><?php echo esc_html( $particod_product['type'] ); ?></span><span><?php echo esc_html( $particod_product['source'] ); ?></span></span>
						</span>
						<span class="dashicons dashicons-external particod-resource-link-arrow" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'particod' ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<section class="particod-resource-card particod-resource-community" aria-labelledby="particod-community-heading">
		<p class="particod-resource-eyebrow"><?php esc_html_e( 'MADE WITH CARE', 'particod' ); ?></p>
		<h2 id="particod-community-heading"><?php esc_html_e( 'Support & Connect', 'particod' ); ?></h2>
		<p><?php esc_html_e( 'Enjoying PartiCOD? Support the creator or explore more tutorials and tools.', 'particod' ); ?></p>
		<?php foreach ( $particod_community_links as $particod_link ) : ?>
			<a class="<?php echo esc_attr( $particod_link['class'] ); ?>" href="<?php echo esc_url( $particod_link['url'] ); ?>" target="_blank" rel="noopener noreferrer">
				<span class="dashicons dashicons-<?php echo esc_attr( $particod_link['icon'] ); ?>" aria-hidden="true"></span>
				<span><?php echo esc_html( $particod_link['label'] ); ?></span>
				<span class="dashicons dashicons-external particod-resource-link-arrow" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'particod' ); ?></span>
			</a>
		<?php endforeach; ?>
	</section>
	</div>
</details>
