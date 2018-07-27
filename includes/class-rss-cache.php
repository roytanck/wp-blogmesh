<?php

// check if called directly
if( !defined( 'ABSPATH' ) ){ exit; }

if( !class_exists('WPBM_RSS_Cache') ){

	class WPBM_RSS_Cache {

		private $cache_post_type_name = 'wpbm_rss_cache';
		private $cache_id_meta_key = '_wpbm_rss_cache_id';


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
			// hook into 'init' to register the post type
			add_action( 'init', array( $this, 'register' ) );
			// trim post content
			add_filter( 'wpbm_filter_rss_content', array( $this, 'prepare_content' ), 10, 1 );

		}


		/**
		 * Register a new custom post type
		 */
		static function register(){

			$options = get_option( 'blogmesh_settings' );
			$show_ui = ( $options['enable_debug'] == true );

			$labels = array(
				'name'               => _x( 'RSS Cache', 'post type general name', 'wp-blogmesh' ),
				'singular_name'      => _x( 'RSS Item', 'post type singular name', 'wp-blogmesh' ),
				'menu_name'          => _x( 'RSS Cache', 'admin menu', 'wp-blogmesh' ),
				'name_admin_bar'     => _x( 'RSS Cache', 'add new on admin bar', 'wp-blogmesh' ),
				'add_new'            => _x( 'Add new', 'book', 'wp-blogmesh' ),
				'add_new_item'       => __( 'Add new item', 'wp-blogmesh' ),
				'new_item'           => __( 'New items', 'wp-blogmesh' ),
				'edit_item'          => __( 'Edit item', 'wp-blogmesh' ),
				'view_item'          => __( 'Display item', 'wp-blogmesh' ),
				'all_items'          => __( 'All items', 'wp-blogmesh' ),
				'search_items'       => __( 'Find items', 'wp-blogmesh' ),
				'parent_item_colon'  => __( 'Parent-item:', 'wp-blogmesh' ),
				'not_found'          => __( 'No items found.', 'wp-blogmesh' ),
				'not_found_in_trash' => __( 'No items found in trash.', 'wp-blogmesh' )
			);

			$args = array(
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => $show_ui,
				'show_in_menu'       => $show_ui,
				'query_var'          => false,
				'capability_type'    => 'post',
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => null,
				'show_in_rest'       => false,
				'supports'           => array( 'title', 'editor', 'author', 'excerpt' )
			);

			register_post_type( 'wpbm_rss_cache', $args );			
		}


		/**
		 * Add a single RSS item to the cache
		 *
		 * @param object $item SimplePie item containing the remote post's data
		 * @param object $feed SimplePie channel object, used to store feed info
		 */
		public function add_item( $item, $feed, $friend_id ){
			// convert the RSS item's date format to WordPress's
			$item_date = $item->get_local_date();
			$timestamp = strtotime( $item->get_local_date() );
			$formatted_date = date('Y-m-d H:i:s', $timestamp );
			// assemble a new post to insert
			$post_array = array(
				'post_content' => apply_filters( 'wpbm_filter_rss_content', $item->get_content() ),
				'post_title' => $item->get_title(),
				'post_excerpt' => $item->get_description(),
				'post_date' => $formatted_date,
				'post_status' => 'publish',
				'post_type' => $this->cache_post_type_name,
				'comment_status' => 'closed',
			);
			// check if this post exists already
			$existing = get_posts(
				array(
					'posts_per_page' => 1,
					'post_type'  => $this->cache_post_type_name,
					'meta_query' => array(
						array(
							'key'     => $this->cache_id_meta_key,
							'value'   => $item->get_link(),
							'compare' => '=',
						),
					)
				)
			);
			// if no existing post, insert the post as a cached item, and add some poast meta fields with info
			if( empty( $existing ) ){
				$post_id = wp_insert_post( $post_array );
				// add a unique identifier for this post
				update_post_meta( $post_id, $this->cache_id_meta_key, $item->get_link() );
				// store the item's source URL
				update_post_meta( $post_id, '_wpbm_original_url', $item->get_link() );
				// store the feed's title
				update_post_meta( $post_id, '_wpbm_feed_name', $feed->get_title() );
				// store the feed image
				update_post_meta( $post_id, '_wpbm_feed_img', $feed->get_image_url() );
				// store the item's author (cached copy will have the WP user as author)
				$author = $item->get_author();
				if( $author ){
					update_post_meta( $post_id, '_wpbm_original_author', $author->get_name() );
				}
				// store the friend ID, so we can use that to delete all posts from a friend when unfriending
				update_post_meta( $post_id, '_wpbm_friend_id', $friend_id );
			} else {
				// post exists in local cache, but may have been updated on remote site
				// So, let's update our local cached version
				$post_id = $post_array['ID'] = $existing[0]->ID;
				wp_update_post( $post_array );
			}
			// in both cases (new or updated), find the item's image and store it in post meta
			$item_img = $this->extract_item_image( $item );
			if( $item_img ){
				update_post_meta( $post_id, '_wpbm_item_img', $item_img );
			}
		}


		/**
		 * Attempt to find an image in the RSS item to use as a "featured image"
		 *
		 * @param object $item SimplePie item containing the remote post's data
		 * @return string|null Image URL if an image was found, null if not
		 */
		private function extract_item_image( $item ){
			$enclosures = $item->get_enclosures();
			$found = null;
			$types = array( 'image/jpeg', 'image/png', 'image/gif' );
			// we'll be trying to find the largest image
			$largest_surface_area = 0;
			// loop through the enclosures
			foreach( $enclosures as $enclosure ){
				// check if the enclosure is an image
				if( in_array( $enclosure->get_type(), $types ) ){
					// how large is this image?
					$surface_area = intval( $enclosure->get_width() ) * intval( $enclosure->get_height() );
					// if it's the largest yet, put it in $found
					if( $surface_area > $largest_surface_area ){
						$found = $enclosure->get_link();
					}
				}
			}
			// if no image found with sizes supplied, just go for the first image
			if( $found == null && !empty( $enclosures ) ){
				foreach( $enclosures as $enclosure ){
					if( in_array( $enclosure->get_type(), $types ) ){
						$found = $enclosure->get_link();
						break;
					}
				}
			}
			// still no luck? try images from the content
			if( $found == null && class_exists('DOMDocument') ){
				$dd = new DOMDocument();
				@$dd->loadHTML( $item->get_content() );
				$dd->preserveWhiteSpace = false;
				$images = $dd->getElementsByTagName('img');
				foreach( $images as $image ){
					$found = $image->getAttribute('src');
					break;
				}
			}
			// return the found image (or null if we found nothing)
			return $found;
		}


		/**
		 * Remove a single item from the cache
		 *
		 * @param int $post_id The post's ID
		 */
		public function remove_item( $post_id ){
			wp_delete_post( $post_id );
		}
		


		/**
		 * Shorten and potentially filter the content field from the RSS feed before caching the item
		 *
		 * @param string $content The content from the RSS feed
		 * @return string The modified content
		 */
		public function prepare_content( $content ){
			return wp_trim_words( $content, 100 );
			// from: https://stackoverflow.com/questions/36078264/i-want-to-allow-html-tag-when-use-the-wp-trim-words
			//return force_balance_tags( html_entity_decode( wp_trim_words( htmlentities( $content ), 100 ) ) );
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
	//$wpbm_rss_cache = new WPBM_RSS_Cache();
	//$wpbm_rss_cache->init();

}

// add activation/deactivation hooks
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
register_activation_hook( __FILE__, array( 'WPBM_RSS_Cache', 'install' ) );


?>