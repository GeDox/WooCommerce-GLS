<?php if( !defined( 'ABSPATH' ) ) exit;

class KDEV_GLS_Soap {
	protected $clientSoap;
	protected $sessionSoap;
	protected $authorizeSoap;
	protected $receiverSoap;
	
	public function __construct() {
		$this->receiverSoap = new stdClass();
		$this->receiverSoap->consign_prep_data = new stdClass();
		$this->receiverSoap->consign_prep_data->parcels = new stdClass();
		$this->receiverSoap->consign_prep_data->parcels->items = array();
	}
	
	public function createPackage() : array {
		$packageID = null; $packageMIME = null;
		
		$this->prepareSoapClient();
		try {
			// Logowanie
			$this->loginSoap();
			
			// Tworzenie paczki
			$packageID = $this->insertPackage();

			// Generowanie i pobieranie listu
			$packageMIME = $this->generatePackage( $packageID );

			// Pobieranie numeru listu przewozowego
			$packageTRACK = $this->getParcelTrackID( $packageID );

			// Wylogowanie
			$this->logoutSoap();
		} catch ( SoapFault $fault ) {
			var_dump( 'Code: ' . $fault->faultcode . ', FaultString: ' . $fault->faultstring );			
		}
		
		return array( 'packageID' => $packageID, 'packageTRACK' => $packageTRACK, 'package' => $packageMIME );
	}
	
	public function setLogin( string $url, string $uname, string $pass ) : void {
		$this->authorizeSoap = (object)array( 
			'url' => $url,
			'user_name' => $uname, 
			'user_password' => $pass
		);
	}
	
	public function setReceiver( string $firstLine, string $secondline='', string $thirdLine='', string $country, string $zipCode, string $city, string $street, string $phone, string $email ) : void {
		$this->prepareAndSetConsignData( array(
			'rname1' => $firstLine,
			'rname2' => $secondline,
			'rname3' => $thirdLine,
			'rcountry' => $country,
			'rzipcode' => $zipCode,
			'rcity' => $city,
			'rstreet' => $street,
			'rphone' => $phone,
			'rcontact' => $email
		));
	}
	
	public function setOptions( string $references, string $notes='', $ade=array() ) : void {
		$this->prepareAndSetConsignData( array(
			'references' => $references,
			'notes' => $notes,
			'srv_bool' => $ade
		));
	}
	
	public function setPackage( ) : void {
		$this->receiverSoap->consign_prep_data->parcels->items[] = array(
			'reference' => '',
			'weight' => '1.00'
		);
	}
	
	public function getSoapData( $client, string $data ) : string {
		$tempClient = $client->return;
		
		switch( $data ) {
			case 'session': break; //return $client->return->session;
			case 'packageID': $data = 'id'; break; //return $client->return->id;
			case 'packageLABEL': $data = 'labels'; break; //return $client->return->labels;
			case 'packageTRACK': { //return $client->return->parcels->items->number;
				$tempClient = $tempClient->parcels->items;
				
				if( is_array( $tempClient ) ) {
					$tempClient = $tempClient[0];
				}
				
				$data = 'number';
			} break;
		}
		
		return $tempClient->$data;
	}
	
	/*
		HELPERS
	*/
	private function prepareAndSetConsignData( array $data ) : void {
		$this->receiverSoap->consign_prep_data = (object)array_merge( (array)$this->receiverSoap->consign_prep_data, $data );
	}
	
	/*
		ADE GLS 
	*/
	private function prepareSoapClient() : void {
		$this->clientSoap = new SoapClient( $this->authorizeSoap->url, array( 'trace' => TRUE, 'cache_wsdl' => WSDL_CACHE_NONE ) );
	}
	
	private function loginSoap() : void {
		$temp = $this->clientSoap->adeLogin( $this->authorizeSoap );
		$this->sessionSoap = $this->getSoapData( $temp, 'session' );
	}
	
	private function logoutSoap() : void {
		$this->clientSoap->adeLogout( (object)array( 
			'session' => $this->sessionSoap 
		) );
	}
	
	private function insertPackage() : string {
		$this->receiverSoap->session = $this->sessionSoap;
		$temp = $this->clientSoap->adePreparingBox_Insert( $this->receiverSoap ); 
		
		return $this->getSoapData( $temp, 'packageID' );
	}
	
	private function generatePackage( $packageID ) {
		$temp = $this->clientSoap->adePreparingBox_GetConsignLabels( (object)array(
			'id' => $packageID,
			'session' => $this->sessionSoap,
			'mode' => 'roll_160x100_pdf'
		) );
		
		return $this->getSoapData( $temp, 'packageLABEL' );
	}
	
	private function getParcelTrackID( $packageID ) {
		$temp = $this->clientSoap->adePreparingBox_GetConsign( (object)array( 
			'session' => $this->sessionSoap, 
			'id' => $packageID 
		) );

		return $this->getSoapData( $temp, 'packageTRACK' );
	}
}