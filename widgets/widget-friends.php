<?php

// check if called directly
if( !defined( 'ABSPATH' ) ){ exit; }

/**
 * Adds widget
 */
class WPBM_Widget_Friends extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			'wpbm_widget_friends',
			esc_html__( 'Blogmesh Friends', 'wp-blogmesh' ),
			array(
				'description' => esc_html__( 'Display your Blogmesh friends', 'wp-blogmesh' )
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
		$display_logged_out = ( $instance['display_logged_out'] == true );
		// skip displaying the widget is not logged in and not set to display when logged out
		if( !is_user_logged_in() && !$display_logged_out ){
			return;
		}
		// start output of the widget
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}
		$friends = get_posts(
			array(
				'posts_per_page' => -1,
				'post_type'  => 'wpbm_friends',
				'post_status' => 'publish'
			)
		);
		if( !empty( $friends ) ){
			echo '<ul>';
			foreach( $friends as $friend ){
				$site_url = get_post_meta( $friend->ID, '_wpbm_site_url', true );
				$feed_url = get_post_meta( $friend->ID, '_wpbm_feed_url', true );
				echo '<li>';
				if( !empty( $site_url ) && 'https://' != $site_url ){
					echo '<a href="' . $site_url . '">' . $friend->post_title . '</a>';
				} else {
					echo $friend->post_title;
				}
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
		$title = ! empty( $instance['title'] ) ? $instance['title'] : esc_html__( 'New title', 'wp-blogmesh' );
		$display_logged_out = ( $instance['display_logged_out'] == true );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Title:', 'wp-blogmesh' ); ?></label> 
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
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
		$instance['display_logged_out'] = isset( $new_instance['display_logged_out'] ) && ( $new_instance['display_logged_out'] == 'true' );
		return $instance;
	}

}


// register the widget
function register_wpbm_friends_widget() {
    register_widget( 'WPBM_Widget_Friends' );
}
add_action( 'widgets_init', 'register_wpbm_friends_widget' );

?>