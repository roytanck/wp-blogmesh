<?php

// check if called directly
if( !defined( 'ABSPATH' ) ){ exit; }


if( !class_exists('WPBM_REST_API') ){

	class WPBM_REST_API {

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
			// create a custom REST API endpoint for our subscribe action
			add_action( 'rest_api_init', array( $this, 'add_endpoints' ) );
		}


		/**
		 * Add the REST API endpoint for our plugin
		 */
		public function add_endpoints(){
			$settings = get_option( 'blogmesh_settings' );
			// check whether the REST API should be enabled
			if( isset( $settings['enable_remote_subscribe'] ) && $settings['enable_remote_subscribe'] == true ){
				// create a custom REST API endpoint for our subscribe action
				register_rest_route( 'blogmesh/v1', '/subscribe', array(
					'methods' => 'POST',
					'callback' => array( $this, 'subscribe' ),
				));
				// create an endpoint for the unsubscribe action
				register_rest_route( 'blogmesh/v1', '/unsubscribe', array(
					'methods' => 'POST',
					'callback' => array( $this, 'unsubscribe' ),
				));
				// create an endpoint to get a list of feeds currently subscribed to
				register_rest_route( 'blogmesh/v1', '/friends', array(
					'methods' => 'GET',
					'callback' => array( $this, 'get_friends' ),
				));
			}
		}


		/**
		 * REST API handler that adds a feed (friend) to follow
		 *
		 * @param WP_REST_Request $request WordPress REST request object representing the current API call
		 * @return WP_REST_Response | WP_Error "Succes" if succesful, error if not
		 */
		public function subscribe( WP_REST_Request $request ){
			// get values from the POST object
			$remote_hash = sanitize_text_field( $request->get_param( 'hash') );
			$name = sanitize_text_field( $request->get_param( 'name') );
			$feed_url = esc_url_raw( $request->get_param( 'feed_url') );
			$home_url = esc_url_raw( $request->get_param( 'home_url') );
			// get the local valid security key
			$security_key = get_option( 'blogmesh_security_key' );
			// abort if there's no local security key
			if( !$security_key ){
				return new WP_Error( 'no_local_security_key', __( 'No local security key to verify', 'wp-blogmesh' ), array( 'status' => 404 ) );
			}
			// create a hash from known values to check against the one provided in the POST request
			$hash_input = 'blogmesh_subscribe' . $security_key . $feed_url . stripslashes( $name );
			$local_hash = hash( 'sha512', $hash_input );
			// check if both hashes match
			if( $local_hash != $remote_hash ){
				return new WP_Error( 'invalid_security_key', __( 'Invalid security key', 'wp-blogmesh' ), array( 'status' => 403 ) );
			}
			// check if the minimal set of info is available
			if( empty( $name ) || empty( $feed_url ) ){
				return new WP_Error( 'invalid_feed_info', __( 'Feed name or URL invalid', 'wp-blogmesh' ), array( 'status' => 404 ) );
			}
			// create a new post
			$new_post = array(
				'post_title'    => $name,
				'post_content'  => '',
				'post_status'   => 'publish',
				'post_type'     => 'wpbm_friends'
			);
			$post_id = wp_insert_post( $new_post );
			// if inserting the new post somehow failed, report an error
			if( !$post_id || is_wp_error($post_id) ){
				echo __( 'Failed to create new friend', 'wp-blogmesh' );
				wp_die();
			}
			// store the new friend's URLs
			update_post_meta( $post_id, '_wpbm_site_url', $home_url );
			update_post_meta( $post_id, '_wpbm_feed_url', $feed_url );
			// prepare a response
			$data = 'Succes';
			$response = new WP_REST_Response( $data );
			return $response;
		}


		/**
		 * REST API handler that removes a feed from your friends
		 *
		 * @param WP_REST_Request $request WordPress REST request object representing the current API call
		 * @return WP_REST_Response | WP_Error "Succes" if succesful, error if not
		 */
		public function unsubscribe( WP_REST_Request $request ){
			// get values from the POST object
			$remote_hash = sanitize_text_field( $request->get_param( 'hash') );
			$feed_url = esc_url_raw( $request->get_param( 'feed_url') );
			// get the local valid security key
			$security_key = get_option( 'blogmesh_security_key' );
			// abort if there's no local security key
			if( !$security_key ){
				return new WP_Error( 'no_local_security_key', __( 'No local security key to verify', 'wp-blogmesh' ), array( 'status' => 404 ) );
			}
			// create a hash from known values to check against the one provided in the POST request
			$hash_input = 'blogmesh_unsubscribe' . $security_key . $feed_url;
			$local_hash = hash( 'sha512', $hash_input );
			// check if both hashes match
			if( $local_hash != $remote_hash ){
				return new WP_Error( 'invalid_security_key', __( 'Invalid security key', 'wp-blogmesh' ), array( 'status' => 403 ) );
			}
			// check if the minimal set of info is available
			if( empty( $feed_url ) ){
				return new WP_Error( 'invalid_feed_info', __( 'Feed name or URL invalid', 'wp-blogmesh' ), array( 'status' => 404 ) );
			}
			// get the friend by feed url
			$args = array(
				'post_type' => array( 'wpbm_friends' ),
				'post_status' => 'publish',
				'meta_query' => array(
					array(
						'key'     => '_wpbm_feed_url',
						'value'   => $feed_url,
						'compare' => '=',
					),
				),
			);
			$query = get_posts( $args );
			// loop through the friends
			foreach( $query as $friend ){
				// completely delete the friend post
				wp_delete_post( $friend->ID, true );
			}
			// prepare a response
			$data = 'Unfollowed';
			$response = new WP_REST_Response( $data );
			return $response;
		}


		/**
		 * REST API handler that returns a list of friends
		 *
		 * @param WP_REST_Request $request WordPress REST request object representing the current API call
		 * @return WP_REST_Response | WP_Error Array of feeds URLs if succesful, error if not
		 */
		public function get_friends( WP_REST_Request $request ){
			// get values from the POST object
			$remote_hash = sanitize_text_field( $request->get_param( 'hash') );
			// get the local valid security key
			$security_key = get_option( 'blogmesh_security_key' );
			// abort if there's no local security key
			if( !$security_key ){
				return new WP_Error( 'no_local_security_key', __( 'No local security key to verify', 'wp-blogmesh' ), array( 'status' => 404 ) );
			}
			// create a hash from known values to check against the one provided in the POST request
			$hash_input = 'blogmesh_friends' . $security_key;
			$local_hash = hash( 'sha512', $hash_input );
			// check if both hashes match
			if( $local_hash != $remote_hash ){
				return new WP_Error( 'invalid_security_key', __( 'Invalid security key', 'wp-blogmesh' ), array( 'status' => 403 ) );
			}
			// get the feeds using WP_Query
			$feeds = array();
			$args = array(
				'post_type' => array( 'wpbm_friends' ),
				'post_status' => 'publish',
				'posts_per_page' => -1
			);
			$query = get_posts( $args );
			foreach( $query as $friend ){
				$feed_url = get_post_meta( $friend->ID, '_wpbm_feed_url', true );
				$feeds[] = $feed_url;
			}
			// if inserting the new post somehow failed, report an error
			if( empty( $feeds ) ){
				return new WP_Error( 'no_friends_found', __( 'No friends found', 'wp-blogmesh' ), array( 'status' => 200 ) );
			}
			// prepare a response
			$data = $feeds;
			$response = new WP_REST_Response( $data );
			return $response;
		}


	}

	// instantiate the plugin class
	$wpbm_rest_api = new WPBM_REST_API();
	$wpbm_rest_api->init();

}

?>