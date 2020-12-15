<?php if( !defined( 'ABSPATH' ) ) exit;

class KDEV_GLS_Order {
	private static $instance;
	private $metaInfo;
	private $order;
	private $orderID;
	
	public function __construct( $config ) {
		if ( null == self::$instance ) {
			self::$instance = (object)array(
				'config' => $config
				//'soap' => $soap
			);
		}
		
		$this->metaInfo = array(
			'packNumber' => '_gls_pack_number',
			'trackNumber' => '_gls_track_number'
		);
	}
	
	public function kdev_add_meta_boxes( $post ) {
		$this->setOrderID( $post->ID );
		
		if( $this->isOrderGLS() ) {
			add_meta_box( 'kdev_other_fields', __('Wysyłka GLS','woocommerce'), array( $this, 'kdev_add_other_fields' ), 'shop_order', 'side', 'core' );
		}
	}
	
	public function kdev_add_other_fields() {
		$gls_track_number = $this->getOrderTrackNumber();
		$cod = $this->getOrderCOD();
		?>

		<div id="track-order-gls">
			<strong>Numer listu GLS:</strong> <a href="https://gls-group.eu/PL/pl/sledzenie-paczek?match=<?php echo $gls_track_number?>" id="track-order-gls" target="_blank"><?php echo $gls_track_number?></a>
		</div>
		
		<hr>
		<div class="options_group">
		<?php
			if( is_array( $cod ) && count( $cod ) ) {
				woocommerce_wp_text_input( array( 'id' => 'cod-order-gls', 'label' => __( 'Pobranie:', 'textdomain' ), 'value' => $cod[ 'cod_amount' ] ) );
			}
			
			woocommerce_wp_text_input( array( 'id' => 'packages-order-gls', 'label' => __( 'Ilość paczek:', 'textdomain' ), 'value' => '1' ) );
			woocommerce_wp_text_input( array( 'id' => 'reference-order-gls', 'label' => __( 'Opis paczki:', 'textdomain' ), 'value' => 'Opis zawartości' ) );
		?>
		</div>
			
		<button type="button" class="button-primary" id="send-order-gls">Wyślij kurierem GLS</button>
		
		<?php ?>
		<script type="text/javascript" >
		jQuery(document).ready(function($) {
			$('#send-order-gls').click(function(){
				var data = {
					'action': 'send-order-gls',
					'order_id': <?php echo $this->orderID; ?>,
					'nonce': '<?php echo wp_create_nonce('kdev-order-gls'); ?>',
					'desc': jQuery('#reference-order-gls').val() ?? '',
					'num_pack': jQuery('#packages-order-gls').val() ?? 1,
					'cod': jQuery('#cod-order-gls').val() ?? null
				};
				
				jQuery.post(ajaxurl, data, function(response) {
					alert( response.data.msg );
						
					$('#track-order-gls').attr( 'href', 'https://gls-group.eu/PL/pl/sledzenie-paczek?match=' + response.data.track ).html( response.data.track );
					
					const linkSource = 'data:application/pdf;base64,'+response.data.package;
					const downloadLink = document.createElement("a");
					const fileName = "GLS zamówienie #<?php echo $this->orderID; ?>.pdf";
						
					downloadLink.href = linkSource;
					downloadLink.download = fileName;
					downloadLink.click();
				});
			});
		});
		</script> <?php
	}
	
	public function prepareOrderData( $cod = 0 ) { 
		//$billingAddress = $order->has_billing_address();
		$shippingAddress = $this->order->has_shipping_address();
		
		$address = array();
		if( $shippingAddress ) {
			$company = $this->order->get_shipping_company();
			$firstname = $this->order->get_shipping_first_name();
			$lastname = $this->order->get_shipping_last_name();
			
			if( $company != '' ) {
				$address['firstLine'] = $company;
				$address['secondLine'] = $firstname . ' ' . $lastname;
			} else {
				$address['firstLine'] = $firstname . ' ' . $lastname;
				$address['secondLine'] = '';
			}
				
			$address['thirdLine'] = '';
			$address['country'] = $this->order->get_shipping_country();
			$address['zip'] = $this->order->get_shipping_postcode();
			$address['city'] = $this->order->get_shipping_city();
			$address['street'] = $this->order->get_shipping_address_1();
			$address['phone'] = $this->order->get_billing_phone();
			$address['email'] = $this->order->get_billing_email();
			$address['ade'] = $this->getOrderCOD( $cod );
		} else {
			$address['error'] = 'notShippingAddress';
		}
		
		return $address;
	}
	
	public function updateOrderInfo( $packValue, $trackValue ) {
		update_post_meta( $this->orderID, $this->metaInfo[ 'packNumber' ], $packValue );
		update_post_meta( $this->orderID, $this->metaInfo[ 'trackNumber' ], $trackValue );
	}
	
	public function setOrderID( $orderID ) {
		$this->orderID = $orderID;
		$this->order = wc_get_order( $orderID );
		
		return $this->order;
	}
	
	private function getOrderTrackNumber() {
		return $this->getOrderInfo( 'trackNumber' );
	}
	
	private function getOrderCOD( $cod = null ) {
		$paymentMethod = $this->order->get_payment_method();
		
		return ( $paymentMethod == 'cod' ? array( 'cod' => 1, 'cod_amount' => $cod ?? $this->order->get_total() ) : array() );
	}
	
	private function getOrderInfo( $type ) {
		return get_post_meta( $this->orderID, $this->metaInfo[ $type ], true ) ?? '';
	}
	
	private function isOrderGLS() {
		$oShipInstance = current( $this->order->get_shipping_methods() );
		$instance = $oShipInstance->get_instance_id();
		
		$eShipMethod = self::$instance->config->getShippingMethods();

		return isset( $eShipMethod[ $instance ] );
	}
}