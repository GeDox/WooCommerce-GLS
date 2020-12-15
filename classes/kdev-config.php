<?php if( !defined( 'ABSPATH' ) ) exit;

class KDEV_GLS_Config {
	private $configName = 'kdev_gls_config';
	private $config = array();
	
	public function __construct() {
		$this->setConfigValue( 'soap', 'SOAP URL', 'https://adeplus.gls-poland.com/adeplus/pm1/ade_webapi2.php?wsdl' );
		$this->setConfigValue( 'login', 'Login GLS', 'Login GLS API' );
		$this->setConfigValue( 'pass', 'Hasło GLS', 'Hasło GLS API' );
		$this->setConfigValue( 'shipping', '', '' );
	}
	
	public function addPage() {
		add_options_page( 'Ustawienia GLS', 'Ustawienia GLS', 'manage_options', 'updateGLSSetingsIndexPage', array( $this, 'updateGLSSetingsIndexPage' ) );
	}
	
	public function updateGLSSetingsIndexPage() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		} ?>

		<h2>Ustawienia GLS</h2>
		
		<form method="post" action="options.php">
			<?php settings_fields( $this->configName ); ?>

			<table class="form-table">
				<?php foreach( $this->config as $key => $config ) { if( $key === 'shipping' ) continue; ?>
				<tr valign="top">
					<th scope="row"><?php echo $config[ 'title' ]; ?></th>
					<td><input type="text" size="80" name="<?php echo $key; ?>" value="<?php echo $this->getConfigValue( $key ); ?>" /></td>
				</tr>
				<?php } ?>
				<tr valign="top">
					<th scope="row">Obsługiwane metody wysyłki</th>
					<td>
						<?php $shippingMethod = $this->getConfigValue( 'shipping' );?>
						
						<table class="wc-shipping-zones widefat">
							<thead>
								<tr>
									<th class="wc-shipping-zone-sort" width="1%"></th>
									<th class="wc-shipping-zone-name">Nazwa strefy</th>
									<th class="wc-shipping-zone-region">Włączony</th>
								</tr>
							</thead>
							<tbody class="wc-shipping-zone-rows">
								<?php foreach( WC_Shipping_Zones::get_zones()[1]['shipping_methods'] as $zone ) { ?>
								<tr data-id="<?php echo $zone->instance_id ?>">
									<td class="wc-shipping-zone-sort" width="1%"></td>
									<td class="wc-shipping-zone-name">
										<a href="admin.php?page=wc-settings&tab=shipping&instance_id=<?php echo $zone->instance_id ?>" target="_blank"><?php echo $zone->title ?></a>
									</td>
									<td class="wc-shipping-zone-method-enabled">
										<input type="checkbox" name="shipping[<?php echo $zone->instance_id ?>]" value="1" <?php checked(1, $shippingMethod[ $zone->instance_id ]); ?> />
									</td>
								</tr>
								<?php } ?>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<p class="submit">
							<input type="submit" class="button-primary" value="<?php _e('Zapisz zmiany') ?>" />
						</p>
					</td>
				</tr>
			</table>
        </form>
		<?php
	}
	
	public function getSoapURL() {
		return $this->getConfigValue( 'soap' );
	}
	
	public function getSoapLogin() {
		return $this->getConfigValue( 'login' );
	}
	
	public function getSoapPass() {
		return $this->getConfigValue( 'pass' );
	}
	
	public function getShippingMethods() {
		return $this->getConfigValue( 'shipping' );
	}
	
	public function prepareSettings() {
		foreach( $this->config as $key => $config ) {
			register_setting( $this->configName, $key );
			add_option( $key, $config['default'], "", "yes" );
		}
	}
	
	private function getConfigValue( $key ) {
		return get_option( $key );
	}
	
	private function setConfigValue( $varKey, $title, $default='' ) {
		$this->config[ $varKey ] = array( 'title' => $title, 'default' => $default );
	}
}