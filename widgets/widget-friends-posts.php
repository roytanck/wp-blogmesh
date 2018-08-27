<?php

// check if called directly
if( !defined( 'ABSPATH' ) ){ exit; }

/**
 * Adds widget
 */
class WPBM_Widget_Friends_Posts extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			'wpbm_widget_friends_posts',
			esc_html__( "Blogmesh Friend's Posts", 'wp-blogmesh' ),
			array(
				'description' => esc_html__( 'Display your the latests posts by your Blogmesh friends', 'wp-blogmesh' )
			)
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		// get the settings we need
		$nrof_posts = ! empty( $instance['nrof_posts'] ) ? intval( $instance['nrof_posts'] ) : 10;
		$display_logged_out = ( $instance['display_logged_out'] == true );
		// skip displaying the widget is not logged in and not set to display when logged out
		if( !is_user_logged_in() && !$display_logged_out ){
			return;
		}
		// start the widget's output
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}
		$posts = get_posts(
			array(
				'posts_per_page' => $nrof_posts,
				'post_type'  => 'wpbm_rss_cache',
				'post_status' => 'publish'
			)
		);
		if( !empty( $posts ) ){
			echo '<ul>';
			foreach( $posts as $p ){
				$url = get_post_meta( $p->ID, '_wpbm_original_url', true );
				echo '<li>';
				echo '<a href="' . $url . '" target="_blank">' . $p->post_title . '</a>';
				echo '</li>';
			}
			echo '</ul>';
		}
		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$title = !empty( $instance['title'] ) ? $instance['title'] : esc_html__( 'New title', 'wp-blogmesh' );
		$nrof_posts = !empty( $instance['nrof_posts'] ) ? intval( $instance['nrof_posts'] ) : 10;
		$display_logged_out = ( $instance['display_logged_out'] == true );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Title:', 'wp-blogmesh' ); ?></label> 
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'nrof_posts' ) ); ?>"><?php esc_attr_e( 'Number of posts:', 'wp-blogmesh' ); ?></label> 
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'nrof_posts' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'nrof_posts' ) ); ?>" type="text" value="<?php echo esc_attr( $nrof_posts ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'display_logged_out' ) ); ?>"><?php esc_attr_e( 'Display when not logged in:', 'wp-blogmesh' ); ?></label> 
			<input id="<?php echo esc_attr( $this->get_field_id( 'display_logged_out' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'display_logged_out' ) ); ?>" type="checkbox" value="true" <?php checked( $display_logged_out, true ); ?>>
		</p>
		<?php 
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['nrof_posts'] = ( ! empty( $new_instance['nrof_posts'] ) ) ? intval( $new_instance['nrof_posts'] ) : 10;
		$instance['display_logged_out'] = isset( $new_instance['display_logged_out'] ) && ( $new_instance['display_logged_out'] == 'true' );
		return $instance;
	}

}


// register the widget
function register_wpbm_friends_widget_posts() {
    register_widget( 'WPBM_Widget_Friends_Posts' );
}
add_action( 'widgets_init', 'register_wpbm_friends_widget_posts' );

?>