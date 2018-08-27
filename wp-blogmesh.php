<?php
/*
Plugin Name: WP Blogmesh
Plugin URI: http://www.roytanck.com
Description: Transforms your blog into a social network
Version: 0.8
Author: Roy Tanck
Text Domain: wp-blogmesh
Domain Path: /languages
*/

// check if called directly
if( !defined( 'ABSPATH' ) ){ exit; }

// require some other stuff
require_once( 'includes/class-rss-cache.php' );
require_once( 'includes/class-rss-cache-manager.php' );
require_once( 'includes/class-settings.php' );
require_once( 'includes/class-rest-api.php' );
require_once( 'includes/cpt-friends.php' );
require_once( 'includes/class-post-adapter.php' );

// include the plugin's widgets
require_once( 'widgets/widget-friends.php' );
require_once( 'widgets/widget-friends-posts.php' );


// create the plugin's main class
if( !class_exists('WP_Blogmesh') ){

	class WP_Blogmesh {

		private $rss_cache = null;
		private $rss_cache_manager = null;
		private $cache_post_type_name = 'wpbm_rss_cache';

		/**
		 * Constructor
		 */
		public function __construct(){
			// nothing
		}

		/**
		 * Add hooks in the init() function
		 */
		public function init(){
			// instantiate the RSS cache class
			$this->rss_cache = new WPBM_RSS_Cache();
			$this->rss_cache->init();
			// instantiate the RSS cache class
			$this->rss_cache_manager = new WPBM_RSS_Cache_Manager();
			$this->rss_cache_manager->init( $this->rss_cache );
			// add friends' items into the main query when logged in
			add_action( 'pre_get_posts', array( $this, 'insert_rss_items' ) );
			// append soem useful info to friends' posts
			add_filter( 'the_content', array( $this, 'add_social_info' ) );
			// add our CSS to the footer
			add_action( 'wp_footer', array( $this, 'add_page_css' ) );
			// add our CSS to the footer
			add_action( 'wp_head', array( $this, 'insert_meta_tags' ) );
			// hook into the_editor_content to insert content for the re-post
			add_filter( 'the_editor_content', array( $this, 'prepare_reblog_content' ), 10, 1 );
		}


		/**
		 * When the user is logged in, add the RSS cache post type to the main query on the home page,
		 * effectively making it a social timeline. Is hooked into 'pre_get_posts'.
		 *
		 * @param WP_Query @query The query WordPress is about to run
		 * @return WP_Query The modified query with the post type added
		 */
		public function insert_rss_items( $query ){
			if( is_user_logged_in() && $query->is_home() && $query->is_main_query() ){
				$query->set('post_type', array( 'post', $this->cache_post_type_name ) );
			}
		}


		/**
		 * Add information below each item that is not from the current site (RSS cached items)
		 *
		 * This will be the main UI for the plugin on the front-end of the website
		 *
		 * @param string $content The post's content
		 * @return string string The contentn with our info appended
		 */
		public function add_social_info( $content ){
			global $post;
			$html = '';
			if( $post && isset( $post->post_type ) && $this->cache_post_type_name == $post->post_type ){
				$html .= '<div class="wpbm-info">';
				$html .= '<div class="wpbm-author-info">';
				$feed_img_url = get_post_meta( $post->ID, '_wpbm_feed_img', true );
				if( !empty( $feed_img_url ) ){
					$html .= '<img src="' . $feed_img_url . '" class="wpbm-feed-img" />';
				}
				$html .= __( 'From: ', 'wp-blogmesh' ) . get_post_meta( $post->ID, '_wpbm_feed_name', true );
				$html .= ' - ';
				$html .= __( 'Author: ', 'wp-blogmesh' ) . get_post_meta( $post->ID, '_wpbm_original_author', true );
				$html .= '</div>';
				$original_url = get_post_meta( $post->ID, '_wpbm_original_url', true );
				if( !empty( $original_url ) ){
					$html .= '<a class="wpbm-button" href="' . $original_url . '" target="_blank">' . __( 'View original post', 'wp-blogmesh' ) . '</a>';
					$html .= '<a class="wpbm-button" href="' . admin_url('post-new.php') . '?blogmesh_reblog=' . $post->ID . '&post_title=REBLOG: ' . $post->post_title . '">' . __( 'Re-blog', 'wp-blogmesh' ) . '</a>';
				}
				$html .= '</div>';
			}

			return $content . $html;
		}


		/**
		 * Added some CSS code into the page
		 */
		public function add_page_css(){
			if( is_user_logged_in() && !is_admin() ){
				$html = '<style>';
				$html .= '.wpbm-info { margin-top: 1em; padding: 1em 0; border-top: 1px dashed #ccc; }';
				$html .= '.wpbm-author-info { margin-bottom: 1em; }';
				$html .= '.wpbm-author-info .wpbm-feed-img { margin-right: 1em; max-width: 120px; height: auto; }';
				$html .= '.wpbm-button { display: inline-block; padding: 0.2em 0.75em; margin-right: 10px; background-color: #09f; border-radius: 4px; color: #fff !important; font-weight: bold; }';
				$html .= '.wpbm-button:hover { background-color: #000; color: #fff; }';
				$html .= '</style>';
				echo $html;				
			}
		}


		/**
		 * Add meta tags to the site's <head> section to indicate this is a blogmesh site
		 */
		public function insert_meta_tags(){
			if( !is_admin() ){
				$tags = array(
					'blogmesh:name' => sanitize_text_field( get_bloginfo('name') ),
					'blogmesh:feed_url' => get_feed_link(),
					'blogmesh:home_url' => get_home_url(),
				);
				foreach( $tags as $name => $content ){
					echo '<meta name="' . $name . '" content="' . $content . '" />';
				}
			}
		}


		/**
		 * Prepare content based on a cached post, to be used in a re-blog
		 *
		 * @param string $content The default content (likely empty)
		 * @return string An HTML snippet with a quote from the original post
		 */
		public function prepare_reblog_content( $content ){
			// check if the reblog url parameter is set
			if( !isset( $_GET['blogmesh_reblog'] ) || !is_numeric( $_GET['blogmesh_reblog'] ) ){
				return $content;
			}
			// get the post ID for the cached post we're about to re-blog
			$reblog_id = intval( $_GET['blogmesh_reblog'] );
			// check if the ID is a valid cached post
			if( empty( $reblog_id ) || get_post_type( $reblog_id ) != 'wpbm_rss_cache' ){
				return $content;
			}
			// gather data we'll need
			$reblog_post = get_post( $reblog_id );
			$reblog_author = get_post_meta( $reblog_id, '_wpbm_original_author', true );
			$reblog_url = get_post_meta( $reblog_id, '_wpbm_original_url', true );
			$reblog_img = get_post_meta( $reblog_id, '_wpbm_item_img', true );
			// assemble the HTML to send to the editor
			$html = '<blockquote>';
			if( !empty( $reblog_img ) ){
				$html .= '<p><img src="' . $reblog_img . '" alt="" /><p>';
			}
			$html .= $reblog_post->post_content;
			$html .= '</blockquote>';
			$html .= '<a href="' . $reblog_url . '">' . $reblog_author . '</a>';
			$html .= '<cite>';
			$html .= '</cite>';
			// return the content
			return $html;
		}

	}

	$wp_blogmesh = new WP_Blogmesh();
	$wp_blogmesh->init();

}



/**
 * Clean up on deactivation
 */
function wpbm_deactivation() {
	$timestamp = wp_next_scheduled( 'blogmesh_update_cache' );
	wp_unschedule_event( $timestamp, 'blogmesh_update_cache' );
	$timestamp = wp_next_scheduled( 'blogmesh_prune_cache' );
	wp_unschedule_event( $timestamp, 'blogmesh_prune_cache' );}

// add activation/deactivation hooks
register_deactivation_hook( __FILE__, 'wpbm_deactivation' );


?>