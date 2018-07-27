<?php
/*
	Modifies a cached RSS post for display in the local timeline
*/


// check if called directly
if( !defined( 'ABSPATH' ) ){ exit; }

if( !class_exists('WPBM_Post_Adapter') ){

	class WPBM_Post_Adapter {

		private $cache_post_type_name = 'wpbm_rss_cache';


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
			// replace the permalink for cached external posts
			add_filter( 'post_type_link', array( $this, 'replace_post_link' ), 10, 2 );
			// replace the author for cached RSS items
			add_filter( 'the_author', array( $this, 'replace_author' ), 10, 1 );
			add_filter( 'the_author_posts_link', array( $this, 'replace_author_link' ), 10, 1 );
			// add the item image
			add_filter( 'the_content', array( $this, 'add_item_image' ), 10, 1 );
		}


		/**
		 * Replace the post permalink, so things like the title point to the original external content
		 *
		 * @param string $link The original link URL
		 * @param WP_Post $post The current post object
		 * @return string The modified URL
		 */
		public function replace_post_link( $link, $post ){
			// if the post is a cached RSS item, return the original URL
			if( get_post_type( $post ) == $this->cache_post_type_name ){
				$original_url = get_post_meta( $post->ID, '_wpbm_original_url', true );
				return $original_url;
			}
			return $link;
		}


		/**
		 * Replace the author display name for cached remote posts
		 *
		 * @param string $author_name Local user name (usually not the actual author)
		 * @return string The modified user display name
		 */
		public function replace_author( $author_name ){
			global $post;
			// if the post is a cached RSS item, return the original URL
			if( get_post_type( $post ) == $this->cache_post_type_name ){
				$original_author = get_post_meta( $post->ID, '_wpbm_original_author', true );
				return $original_author;
			}
			return $author_name;
		}


		/**
		 * Replace the author link for cached remote posts
		 *
		 * @param string $author_link Local user link (html)
		 * @return string Placeholder if the post is external (we don't really have a link to put here)
		 */
		public function replace_author_link( $author_link ){
			global $post;
			// if the post is a cached RSS item, return the original URL
			if( get_post_type( $post ) == $this->cache_post_type_name ){
				return __( '[external author]', 'wp-blogmesh' );
			}
			return $author_link;
		}


		/**
		 * Add the item's image to the content
		 *
		 * @param string $content The post content
		 * @return string The modified content
		 */
		public function add_item_image( $content ){
			global $post;
			$item_img = get_post_meta( $post->ID, '_wpbm_item_img', true );
			if( $item_img ){
				$content = '<p><img src="' . print_r($item_img,true) . '" class="alignnone" alt="" /></p>' . $content;
			}
			return $content;
		}

	}

	// instantiate the class
	$wpbm_post_adapter = new WPBM_Post_Adapter();
	$wpbm_post_adapter->init();

}

?>