<?php

// check if called directly
if( !defined( 'ABSPATH' ) ){ exit; }

if( !class_exists('WPBM_RSS_Cache_Manager') ){

	class WPBM_RSS_Cache_Manager {

		private $feeds = array();
		private $rss_cache = null;
		private $cache_transient_time = 15 * 60;
		private $cache_transient_name = 'wpbm_timeout_transient';

		/**
		 * Constructor
		 */
		public function __construct(){
			// intentionally left blank
		}


		/**
		 * Init function that adds functions to hooks
		 */
		public function init( $rss_cache ){
			// create a reference to the RSS Cache class instance
			$this->rss_cache = $rss_cache;
			// fetch friends' RSS feeds on init
			//add_action( 'init', array( $this, 'fetch_feeds' ), 10, 0 );
			// shorten the RSS cache time in WordPress considerably (is very long by default)
			add_filter( 'wp_feed_cache_transient_lifetime', function(){ return 1800; });
			// hook into post deletion to update the cache accordingly
			add_action( 'before_delete_post', array( $this, 'on_post_removed' ), 10 ,1 );
			add_action( 'trashed_post', array( $this, 'on_post_removed' ), 10 ,1 );
			// new friend? update the cache
			add_action( 'untrashed_post', array( $this, 'on_post_added' ), 9999 ,1 );
			add_action( "save_post", array( $this, 'on_post_added' ), 9999, 1 );
			// add a cron interval
			add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ), 10, 1 );
			// attach the update function to our own custom cron interval
			if ( !wp_next_scheduled( 'blogmesh_update_cache' ) ) {
				wp_schedule_event( time(), 'blogmesh_interval', 'blogmesh_update_cache' );
			}
			add_action( 'blogmesh_update_cache', array( $this, 'update_cache' ), 10, 0 );
			// add our pruning function to the daily cron interval
			if ( !wp_next_scheduled( 'blogmesh_prune_cache' ) ) {
				wp_schedule_event( time(), 'daily', 'blogmesh_prune_cache' );
			}
			add_action( 'blogmesh_prune_cache', array( $this, 'prune_cache' ), 10, 0 );
		}


		/**
		 * Function that kicks of the cache update
		 */
		public function update_cache(){
			$this->fetch_feeds();
		}


		/**
		 * Fetch all feeds for friends we're subscribed to
		 *
		 * Currently hooked into 'init', bit it uses a transient to make sure we're not doing this too often
		 */
		public function fetch_feeds(){
			// populate the feeds array
			$this->feeds = $this->get_all_feed_urls();
			// loop through teh feeds and tell the cache manager to get each one
			foreach( $this->feeds as $friend_id=>$feed ){
				$this->add_posts_from_feed( $feed, $friend_id );
			}
		}


		/**
		 * Get all feeds that need to be loaded
		 *
		 * The feeds are stored in an option for performance reasons, whenever a friend is added
		 */
		private function get_all_feed_urls(){
			$feeds = array();
			$args = array(
				'posts_per_page' => -1,
				'post_type'  => 'wpbm_friends',
				'post_status' => 'publish'
			);
			$friends = get_posts( $args );
			foreach( $friends as $friend ){
				$feeds[ $friend->ID ] = get_post_meta( $friend->ID, '_wpbm_feed_url', true );
			}
			return $feeds;
		}


		/**
		 * Add posts from a feed to the cache
		 *
		 * @param string $feed_url RSS feed URL
		 * @param int $friend_id The friend ID (post ID)
		 */
		public function add_posts_from_feed( $feed_url, $friend_id ){
			// create a SimplePie object for the feed
			$rss = fetch_feed( $feed_url );
			// check if error
			if( !is_wp_error( $rss ) ){
				// get the items from the feed
				$maxitems = $rss->get_item_quantity( 100 );
				$rss_items = $rss->get_items( 0, $maxitems );
				// loop through the feed's items
				foreach( $rss_items as $item ){
					// store a local copy of the item in the RSS cache
					$this->rss_cache->add_item( $item, $rss, $friend_id );
				}
			}
		}


		/**
		 * Remove all cached posts from a feed
		 *
		 * @param int $friend_id ID of the friend being removed
		 */
		public function remove_all_post_by_friend( $friend_id ){
			// get posts by this friend
			$posts = get_posts(
				array(
					'posts_per_page'   => -1,
					'post_type'        => 'wpbm_rss_cache',
					'meta_query' => array(
						array(
							'key'     => '_wpbm_friend_id',
							'value'   => $friend_id,
							'type'    => 'numeric',
							'compare' => '=',
						)
					)
				)
			);
			// loop through them and remove each post
			foreach( $posts as $p ){
				$this->rss_cache->remove_item( $p->ID );
			}
		}


		/**
		 * Event handler for when a friend is removed or trashed
		 */
		public function on_post_removed( $post_id ){
			// if the deleted post is not a friend, do nothing
			if( get_post_type( $post_id ) != 'wpbm_friends' ){
				return;
			}
			// remove all cached posts for the friend being removed
			$this->remove_all_post_by_friend( $post_id );
		}


		/**
		 * Event handler for when a friend is added
		 *
		 * @param int $post_id The new/restored/updated post's ID
		 */
		public function on_post_added( $post_id ){
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}
			// if the deleted post is not a friend, do nothing
			if( get_post_type( $post_id ) != 'wpbm_friends' ){
				return;
			}
			// get the friend's feed url
			$feed_url = get_post_meta( $post_id, '_wpbm_feed_url', true );
			if( $feed_url ){
				$this->add_posts_from_feed( $feed_url, $post_id );	
			}
		}


		/**
		 * Clear the transient that throttles feed loading
		 */
		public function clear_timeout(){
			delete_transient( $this->cache_transient_name );			
		}


		/**
		 * Create a custom WP-Cron interval to update the cache
		 *
		 * @param array $schedules The previously available cron intervals
		 * @return array The updated array of intervals
		 */
		public function add_cron_interval( $schedules ) {
			$schedules['blogmesh_interval'] = array(
				'interval' => 10*60,
				'display'  => esc_html__( 'Blogmesh cache update interval', 'wp-blogmesh' ),
			);
			return $schedules;
		}


		/**
		 * Clean up the local cache by deleting older items
		 * This helps keep our database tables manageable
		 */
		public function prune_cache(){
			// get all RSS cache items older than our treshold
			$args = array(
				'post_type' => 'wpbm_rss_cache',
				'date_query' => array(
					array(
						'column' => 'post_date_gmt',
						'before' => '1 year ago',
					)
				),
				'posts_per_page' => -1,
			);
			$items = get_posts( $args );
			// loop through the found items
			foreach( $items as $item ){
				// delete the item, skipping the trash
				wp_delete_post( $item->ID, true );
			}
		}

	}
}

?>