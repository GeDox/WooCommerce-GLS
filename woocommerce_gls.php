<?php if( !defined( 'ABSPATH' ) ) exit;
/*
Plugin Name: WooCommerce GLS
Description: Wtyczka dodaje możliwość stworzenia listu przewozowego, bezpośrednio z poziomu zamówienia.
Version: 0.9.2
Author: Przemysław Kozłowski
Author URI: https://sztosit.eu
License: GPL2
*/

class KDEV_GLS {
	private static $instance;

	public static function get_instance() {
		if ( null != self::$instance ) {
			return self::$instance->KDEV_GLS_MAIN;
		}
		
		require( 'classes/kdev-gls.php' );
		require( 'classes/kdev-ajax.php' );
		require( 'classes/kdev-config.php' );
		require( 'classes/kdev-order.php' );
			
		self::$instance = (object)array(
			'KDEV_GLS_MAIN' => new KDEV_GLS(),
			'KDEV_GLS_CONF' => new KDEV_GLS_Config(),
			'KDEV_GLS_SOAP' => new KDEV_GLS_Soap()
		);
		
		self::$instance->KDEV_GLS_ORDER = new KDEV_GLS_Order( self::$instance->KDEV_GLS_CONF );
		self::$instance->KDEV_GLS_AJAX = new KDEV_GLS_Ajax( self::$instance->KDEV_GLS_CONF, self::$instance->KDEV_GLS_SOAP, self::$instance->KDEV_GLS_ORDER );

		add_action( 'admin_init', array( self::$instance->KDEV_GLS_CONF, 'prepareSettings' ) );
		add_action( 'admin_menu', array( self::$instance->KDEV_GLS_CONF, 'addPage' ) );
		
		add_action( 'add_meta_boxes_shop_order', array( self::$instance->KDEV_GLS_ORDER, 'kdev_add_meta_boxes' ) );
		add_action( 'wp_ajax_send-order-gls', array( self::$instance->KDEV_GLS_AJAX, 'kdev_gls_ajax' ) );

		return self::$instance->KDEV_GLS_MAIN;
	}
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	add_action( 'plugins_loaded', array( 'KDEV_GLS', 'get_instance' ) );
	
	require( 'classes/kdev-update.php' );
	$update = new KDEV_GLS_Update( '0.9.2' );
}