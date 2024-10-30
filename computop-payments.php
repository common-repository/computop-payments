<?php
/**
 * Plugin Name: Computop Payments
 * Plugin URI:
 * Description: Official Computop Plugin
 * Author: Computop
 * Author URI: https://www.computop.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Version: 1.0.1
 * Requires at least: 4.5
 * Tested up to: 6.5
 * WC requires at least: 6.0
 * WC tested up to: 8.9
 * Text Domain: computop-payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COMPUTOP_VERSION', '1.0.1' );
define( 'COMPUTOP_PLUGIN_TYPE_STRING', 'Computop Payments' );
define( 'COMPUTOP_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'COMPUTOP_PLUGIN_PATH', __DIR__ . '/' );
define( 'COMPUTOP_PLUGIN_NAME', 'computop-payments' );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'computop-payments', false, basename( __DIR__ ) . '/languages' );
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="error"><p><strong>' . esc_html( __( 'Computop requires WooCommerce to be installed and active.', 'computop-payments' ) ) . '</strong></p></div>';
				}
			);
			return;
		}
		require_once COMPUTOP_PLUGIN_PATH . 'vendor/autoload.php';

		$computop = \ComputopPayments\Main::getInstance();
		$computop->init();
	}
);
