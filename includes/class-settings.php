<?php

// check if called directly
if( !defined( 'ABSPATH' ) ){ exit; }


if( !class_exists('WPBM_Settings') ){

	class WPBM_Settings {

		private $options;

		/**
		 * Constructor
		 */
		public function __construct(){
			// intentionally left blank
		}


		/**
		 * Init function that adds functions to hooks
		 */
		public function init(){
			// register the options page
			add_action( 'admin_menu', array( $this, 'register_options_page' ) );
			// register the settings
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}


		public function register_options_page(){
			add_options_page(
				__( 'Blogmesh Settings', 'wp-blogmesh'),
				__( 'Blogmesh', 'wp-blogmesh'),
				'manage_options',
				'blogmesh-settings',
				array( $this, 'settings_page' )
			);
		}


		public function register_settings(){
			register_setting(
				'blogmesh_option_group', // Option group
				'blogmesh_settings', // Option name
				array( $this, 'sanitize' ) // Sanitize
			);

			add_settings_section(
				'blogmesh_section_security', // ID
				__( 'Security settings', 'wp-blogmesh' ), // Title
				array( $this, 'section_info_security' ), // Callback
				'blogmesh-settings' // Page
			);  

			add_settings_section(
				'blogmesh_section_main', // ID
				__( 'Blogmesh settings', 'wp-blogmesh' ), // Title
				array( $this, 'section_info_main' ), // Callback
				'blogmesh-settings' // Page
			);  

			add_settings_field(
				'enable_remote_subscribe', // ID
				__( 'Enable remote subscriptions', 'wp-blogmesh' ), // Title 
				array( $this, 'enable_remote_subscribe_callback' ), // Callback
				'blogmesh-settings', // Page
				'blogmesh_section_main' // Section           
			);

			add_settings_field(
				'enable_debug', // ID
				__( 'Debug mode', 'wp-blogmesh' ), // Title 
				array( $this, 'enable_debug_callback' ), // Callback
				'blogmesh-settings', // Page
				'blogmesh_section_main' // Section           
			);

		}


		public function settings_page(){
			// Set class property
			$this->options = get_option( 'blogmesh_settings' );
			
			echo '<div class="wrap">';
			echo '<h1>' . __( 'Blogmesh Settings', 'wp-blogmesh' ) . '</h1>';
			echo '<form method="post" action="options.php">';
			// This prints out all hidden setting fields
			settings_fields( 'blogmesh_option_group' );
			do_settings_sections( 'blogmesh-settings' );
			submit_button();
			echo '</form>';
			echo '</div>';
		}


		public function section_info_security(){
			// generate a security key if one is not already present
			$security_key = get_option( 'blogmesh_security_key' );
			if( !$security_key ){
				$security_key = $this->generate_api_secret();
				update_option( 'blogmesh_security_key', $security_key );
			}
			// render the API URL and security key, this section has no actual setting fields
			$styles = 'display: inline-block; min-width: 300px; font-size: 1.5em; background-color: #fff; border: 1px solid #999; color: #000; padding: 0.5em 0.75em;';
			echo '<div style="border: 1px solid #ccc; padding: 0 1.5em 1.5em 1.5em; background-color: rgba(255,255,255,0.5);">';
			echo '<h3>' . __( 'API URL' , 'wp-blogmesh' ) . '</h3>';
			echo '<input type="text" readonly size="50" style="' . $styles . '" value="' . get_rest_url( null, '/blogmesh/v1/', ( is_ssl() ? 'https' : 'http' ) ) . '"></input>';
			echo '<h3>' . __( 'API Secret' , 'wp-blogmesh' ) . '</h3>';
			echo '<input type="text" readonly size="50" style="' . $styles . '" value="' . $security_key . '"></input>';
			echo '<p>' . __( 'These values are used by external applications like browser extensions to manage your subscriptions. Keep these secure.', 'wp-blogmesh' ) . '</p>';
			echo '</div>';
		}


		public function section_info_main(){
			echo __( 'Please set your preferences below.', 'wp-blogmesh' );
		}


		public function enable_remote_subscribe_callback(){
			printf(
				'<input type="checkbox" id="enable_remote_subscribe" name="blogmesh_settings[enable_remote_subscribe]" value="true" %s />',
				checked( $this->options['enable_remote_subscribe'], true, false )
			);
		}


		public function enable_debug_callback(){
			printf(
				'<input type="checkbox" id="enable_debug" name="blogmesh_settings[enable_debug]" value="true" %s />',
				checked( $this->options['enable_debug'], true, false )
			);
		}


		public function sanitize( $input ){
			$new_input = array();
			
			// convert the enable_remote_subscribe value to boolean
			$new_input['enable_remote_subscribe'] = ( $input['enable_remote_subscribe'] == 'true' );
			// convert the enable_debug value to boolean
			$new_input['enable_debug'] = ( $input['enable_debug'] == 'true' );

			return $new_input;
		}


		public function generate_api_secret(){
			// try PHP7's new random_bytes function
			if( function_exists('random_bytes') ){
				return bin2hex( random_bytes( 16 ));
			}
			// try mcrypt
			if( function_exists('mcrypt_create_iv') ){
				return bin2hex( mcrypt_create_iv( 16, MCRYPT_DEV_URANDOM ) );
			}
			// older PHP5 solution, less secure
			if( function_exists( 'openssl_random_pseudo_bytes' ) ){
				return bin2hex( openssl_random_pseudo_bytes( 16 ) );
			}
			// last resort, very weak
			return md5( mt_rand() );
		}

	}

	// instantiate the class
	$wpbm_settings = new WPBM_Settings();
	$wpbm_settings->init();

}

?>