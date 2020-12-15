<?php if( !defined( 'ABSPATH' ) ) exit;

class KDEV_GLS_Update {
	private $current_version;
	private $plugin_info;
	
	public function __construct( $version ) {
		$this->current_version = $version;
		$this->plugin_info = (object)array(
			'id' => 'woocommerce_gls/woocommerce_gls.php',
			'slug' => 'woocommerce_gls'
		);

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'kdev_check_update' ));
		add_filter( 'plugins_api', array( $this, 'kdev_plugin_info' ), 20, 3);
		add_action( 'upgrader_process_complete', array( $this, 'kdev_after_update' ), 10, 2 );
		add_filter( 'upgrader_post_install', array( $this, 'upgrader_post_install' ), 10, 3 );
	}
	
	public function kdev_check_update( $transient )
	{		
		delete_transient( 'kdev_update_' . $this->plugin_info->slug );
		
		if (empty($transient->checked)) {
			return $transient;
		}
	 
		$remote = wp_remote_get( 'https://raw.githubusercontent.com/GeDox/WooCommerce-GLS/main/update/info.json?raw=true', array(
			'timeout' => 10,
			'headers' => array(
				'Accept' => 'application/json'
			) )
		);
			
		$remote = json_decode( $remote['body'] );
		
		$remote_version = $remote->version;
	 
		// If a newer version is available, add the update
		if (version_compare($this->current_version, $remote_version, '<')) {
			$obj = new stdClass();
			
			$obj->id = $this->plugin_info->id;
			$obj->slug = $this->plugin_info->slug;
			$obj->plugin = $this->plugin_info->id;
			$obj->new_version = $remote_version;
			$obj->url = 'https://github.com/GeDox/WooCommerce-GLS/archive/main.zip';
			$obj->package = 'https://github.com/GeDox/WooCommerce-GLS/archive/main.zip';;
			$transient->response[ 'woocommerce_gls/woocommerce_gls.php' ] = $obj;
		}
		
		return $transient;
	}

	public function kdev_plugin_info( $res, $action, $args ){
		// do nothing if this is not about getting plugin information
		if( 'plugin_information' !== $action ) {			
			return false;
		}
	 
		delete_transient( 'kdev_update_' . $this->plugin_info->slug );
		
		// do nothing if it is not our plugin
		if( $this->plugin_info->slug !== $args->slug ) {
			return false;
		}
	 
		// trying to get from cache first
		if( false == $remote = get_transient( 'kdev_update_' . $this->plugin_info->slug ) ) {
	 
			// info.json is the file with the actual plugin information on your server
			$remote = wp_remote_get( 'https://raw.githubusercontent.com/GeDox/WooCommerce-GLS/main/update/info.json?raw=true', array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json'
				) )
			);
	 
			if ( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && ! empty( $remote['body'] ) ) {
				set_transient( 'kdev_update_' . $this->plugin_info->slug, $remote, 43200 ); // 12 hours cache
			}
	 
		}
	 
		if( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && ! empty( $remote['body'] ) ) {
	 
			$remote = json_decode( $remote['body'] );
			$res = new stdClass();
	 
			$res->name = $remote->name;
			$res->slug = $this->plugin_info->slug;
			$res->version = $remote->version;
			$res->tested = $remote->tested;
			$res->requires = $remote->requires;
			$res->author = '<a href="https://sztosit.eu">SztosIT.eu</a>';
			$res->author_profile = 'https://github.com/GeDox/';
			$res->download_link = $remote->download_url;
			$res->trunk = $remote->download_url;
			$res->requires_php = $remote->requires_php;
			$res->last_updated = $remote->last_updated;
			$res->sections = array(
				'description' => $remote->sections->description,
				'installation' => $remote->sections->installation,
				'changelog' => $remote->sections->changelog
				// you can add your custom sections (tabs) here
			);
	 
			// in case you want the screenshots tab, use the following HTML format for its content:
			// <ol><li><a href="IMG_URL" target="_blank"><img src="IMG_URL" alt="CAPTION" /></a><p>CAPTION</p></li></ol>
			if( !empty( $remote->sections->screenshots ) ) {
				$res->sections['screenshots'] = $remote->sections->screenshots;
			}
	 
			$res->banners = array(
				'high' => 'https://github.com/GeDox/WooCommerce-GLS/blob/main/chrome_5JcAOvjb9H.png?raw=true'
			);
			return $res;
	 
		}
	 
		return false;
	}
	
	public function kdev_after_update( $upgrader_object, $options ) {
		if ( $options['action'] == 'update' && $options['type'] === 'plugin' )  {
			delete_transient( 'kdev_update_' . $this->plugin_info->slug );
		}
	}
	
	public function upgrader_post_install( $true, $hook_extra, $result ) {
		global $wp_filesystem;

		// Move & Activate
		$proper_destination = WP_PLUGIN_DIR.'/'.$this->plugin_info->slug;
		$wp_filesystem->move( $result['destination'], $proper_destination );
		$result['destination'] = $proper_destination;
		$activate = activate_plugin( WP_PLUGIN_DIR.'/'.$this->plugin_info->slug );

		// Output the update message
		$fail  = __( 'The plugin has been updated, but could not be reactivated. Please reactivate it manually.', 'github_plugin_updater' );
		$success = __( 'Plugin reactivated successfully.', 'github_plugin_updater' );
		echo is_wp_error( $activate ) ? $fail : $success;
		return $result;
	}
}