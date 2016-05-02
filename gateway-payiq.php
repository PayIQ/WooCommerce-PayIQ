<?php
/*
Plugin Name: WooCommerce PayIQ Gateway
Plugin URI: http://woocommerce.com
Description: Provides a <a href="http://payiq.se/" target="_blank">PayIQ</a> gateway for WooCommerce.
Version: 1.0.0
Author: AngryCreative
Author URI: http://angrycreative.se
*/

/*
Requirements:
WordPress 4.0+
WooCommerce 2.2+
PHP 5.3+
*/

add_action( 'plugins_loaded', 'init_wc_gateway_payiq' );


function init_wc_gateway_payiq() {

	//require 'vendor/autoload.php';

	// If the WooCommerce payment gateway class is not available, do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	/**
	 * Localisation
	 */
	load_plugin_textdomain( 'woocommerce-gateway-payiq', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	/*
     * Constants
     */
	// Plugin Folder Path
	if ( ! defined( 'WC_PAYIQ_PLUGIN_DIR' ) ) {
		define( 'WC_PAYIQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	}
	if ( ! defined( 'WC_PAYIQ_PLUGIN_BASENAME' ) ) {
		define( 'WC_PAYIQ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
	}
	// Plugin Folder URL
	if ( ! defined( 'WC_PAYIQ_PLUGIN_URL' ) ) {
		define( 'WC_PAYIQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	}

	//Load and instantiate base plugin class
	require_once 'classes/class-payiq.php';

	new PayIQ();

	//Load and register gateway in WooCommerce
	require_once 'classes/class-payiq-gateway.php';

	add_filter( 'woocommerce_payment_gateways', function( $methods ) {
		$methods[] = 'WC_Gateway_PayIQ';
		//$methods[] = 'WC_Gateway_PayIQ_CC';
		//$methods[] = 'WC_Gateway_PayIQ_Bank';

		return $methods;
	} );

}

