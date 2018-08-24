<?php

// check if called directly
if( !defined( 'ABSPATH' ) ){ exit; }

if( !class_exists('WP_Blogmesh_CPT_Friends') ){

	class WP_Blogmesh_CPT_Friends {

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
			add_action( 'init', array( $this, 'register' ) );
			add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
			add_action( 'save_post', array( $this, 'save_meta_box' ) );
			add_filter( 'manage_wpbm_friends_posts_columns', array( $this, 'admin_columns' ) );
			add_action( 'manage_wpbm_friends_posts_custom_column', array( $this, 'admin_columns_content' ), 10, 2);
		}


		/**
		 * Register a new custom post type
		 */
		static function register(){
			$labels = array(
				'name'               => _x( 'Friends', 'post type general name', 'wp-blogmesh' ),
				'singular_name'      => _x( 'Friend', 'post type singular name', 'wp-blogmesh' ),
				'menu_name'          => _x( 'Friends', 'admin menu', 'wp-blogmesh' ),
				'name_admin_bar'     => _x( 'Friend', 'add new on admin bar', 'wp-blogmesh' ),
				'add_new'            => _x( 'Add new', 'book', 'wp-blogmesh' ),
				'add_new_item'       => __( 'Add new friend', 'wp-blogmesh' ),
				'new_item'           => __( 'New friend', 'wp-blogmesh' ),
				'edit_item'          => __( 'Edit friend', 'wp-blogmesh' ),
				'view_item'          => __( 'Display friend', 'wp-blogmesh' ),
				'all_items'          => __( 'All friends', 'wp-blogmesh' ),
				'search_items'       => __( 'Find friends', 'wp-blogmesh' ),
				'parent_item_colon'  => __( 'Parent:', 'wp-blogmesh' ),
				'not_found'          => __( 'No friends found.', 'wp-blogmesh' ),
				'not_found_in_trash' => __( 'No friends found in trash.', 'wp-blogmesh' )
			);

			$args = array(
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'query_var'          => false,
				/*'rewrite'            => array( 'slug' => 'documentatie' ),*/
				'capability_type'    => 'post',
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => null,
				'show_in_rest'       => false,
				'supports'           => array( 'title', 'editor' )
			);

			register_post_type( 'wpbm_friends', $args );			
		}


		/**
		 * Register a meta box to hold our settings for this post type
		 */
		public function register_meta_box(){
			add_meta_box( 'wpbm_metabox_friends', __( 'Friend settings', 'wp-blogmesh' ), array( $this, 'display_metabox' ), 'wpbm_friends' );
		}


		/**
		 * Render the meta box
		 *
		 * @param WP_Post $post The post object of the post being edited
		 */
		public function display_metabox( $post ){
			// get the currently stored RSS feed URL for this friend
			$feed_url = get_post_meta( $post->ID, '_wpbm_feed_url', true );
			if( empty( $feed_url ) ){
				$feed_url = 'https://';
			}

			echo '<p><Label for="wpbm_feed_url">' . __( 'RSS feed URL', 'wp-blogmesh' ) . '</label>';
			echo '<input style="text" name="wpbm_feed_url" id="wpbm_feed_url" class="code widefat" value="' . $feed_url . '" /></p>';

			wp_nonce_field( 'wpbm_save_metabox', 'wpbm_nonce_field' );
		}


		/**
		 * Saves the meta box's form fields, properly checking nonces and such
		 */
		public function save_meta_box( $post_id ){
			// security checks
			if ( ! isset( $_POST['wpbm_nonce_field'] ) ) { return; }
			if ( ! wp_verify_nonce( $_POST['wpbm_nonce_field'], 'wpbm_save_metabox' ) ) { return; }
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
			if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

			// sanitize and store the feed URL
			$feed_url = esc_url_raw( $_POST['wpbm_feed_url'] );
			update_post_meta( $post_id, '_wpbm_feed_url', $feed_url );
		}


		/**
		 * Register custom admin columns
		 *
		 * @param array $columns Array of currently registered columns
		 * @return array Modified araay of columns
		 */
		public function admin_columns( $columns ){
			unset( $columns['date'] );
			$columns['wpbm_feed_url'] = __( 'RSS Feed URL', 'wp-blogmesh' );
			$columns['date'] = __( 'Date' );
			return $columns;
		}


		/**
		 * Render the admin column's content
		 *
		 * @param string $column Column slug
		 * @param int $post_id Post id of the row's post
		 */
		public function admin_columns_content( $column, $post_id ){
			if ( 'wpbm_feed_url' === $column ) {
				$feed_url = get_post_meta( $post_id, '_wpbm_feed_url', true );
				echo '<a href="' . $feed_url . '" target="_blank">' . $feed_url . '</a>';
			}
		}


		/**
		 * Make sure the permalinks work properly by:
		 *
		 * 1. Making sure the CPT is registered by calling the register function
		 * 2. Flushing WordPress's rewrite rule cache
		 */
		static function install() {
			self::register();
			flush_rewrite_rules();
		}

	}

	// instantiate the plugin class
	$wp_blogmesh_cpt_friends = new WP_Blogmesh_CPT_Friends();
	$wp_blogmesh_cpt_friends->init();

}

// add activation/deactivation hooks
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
register_activation_hook( __FILE__, array( 'WP_Blogmesh_RSS_Cache', 'install' ) );


?>