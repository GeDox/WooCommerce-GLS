<?php if( !defined( 'ABSPATH' ) ) exit;

class KDEV_GLS_Ajax {
	private static $instance;
	private $metaInfo;
	
	public function __construct( $config, $soap, $order ) {
		if ( null == self::$instance ) {
			self::$instance = (object)array(
				'config' => $config,
				'soap' => $soap,
				'order' => $order
			);
		} 
		
		$this->metaInfo = array(
			'packNumber' => '_gls_pack_number',
			'trackNumber' => '_gls_track_number'
		);
	}
	
	public function kdev_gls_ajax() {
		$orderID = intval( $_POST['order_id'] );
		$nonce = $_POST['nonce'];
		$desc = $_POST['desc'];
		$num_pack = intval( $_POST['num_pack'] );
		$cod = $_POST['cod'];

		if ( !wp_verify_nonce( $nonce, 'kdev-order-gls' ) ) {
			die ( 'Busted!');
		}
		
		if( $num_pack < 1 ) {
			wp_send_json_error( array( 'msg' => 'Ilość paczek musi być większa lub równa 1.' ) );
		}
		
		$order = self::$instance->order->setOrderID( $orderID );
		
		if( !$order ) {
			wp_send_json_error( array( 'msg' => 'Wybrane zamówienie nie istnieje.' ) );
		}
		
		//if( get_post_meta( $orderID, '_gls_track_number', true ) != '' ) {
		//	wp_send_json_error( array( 'msg' => 'Wybrane zamówienie ma już przypisany numer listu przewozowego.' ) );
		//}
		
		if( !$order->is_paid() ) {
			wp_send_json_error( array( 'msg' => 'Wybrane zamówienie nie jest opłacone.' ) );
		}
		
		$address = self::$instance->order->prepareOrderData( $cod );
		
		if( isset( $address['error'] ) ) {
			return wp_send_json_error( array( 'msg' => $address['error'] ) );
		}
		
		$soap = self::$instance->soap;
			
		$soap->setLogin( 
			self::$instance->config->getSoapURL(),
			self::$instance->config->getSoapLogin(),
			self::$instance->config->getSoapPass()
		);
			
		$soap->setReceiver( 
			$address['firstLine'],
			$address['secondLine'],
			$address['thirdLine'],
			$address['country'],
			$address['zip'],
			$address['city'],
			$address['street'],
			$address['phone'],
			$address['email']
		);
			
		$soap->setOptions( 
			'',
			$desc,
			$address['ade']
		);
			
		for( $i=1; $i <= $num_pack; $i++ ) {
			$soap->setPackage();
		}
			
		$package = $soap->createPackage();
		
		self::$instance->order->updateOrderInfo( $package[ 'packageID' ], $package[ 'packageTRACK' ] );
			
		wp_send_json_success( array( 'msg' => 'Paczka została poprawnie stworzona.', 'package' => $package['package'], 'track' => $package[ 'packageTRACK' ] ) );
	}
}