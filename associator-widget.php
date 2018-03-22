<?php
/*
Plugin Name: Associator
Plugin URI: http://associator.eu
Description: Allow to display related products.
Author: Tomasz Tarnawski
Version: 1.0
Author URI: http://ttarnawski.usermd.net
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Associator extends WP_Widget
{
    const BASE_URL = 'api.associator.eu';
    const VERSION = 'v1';

	function __construct()
	{
		parent::__construct(
				'woocommerce_associator',
				__( 'Associator', 'associator' ),
				array('description' => __( 'This plugin help display a list of customs products on your website.', 'associator' ))
				);
	}

	public function widget( $args, $instance ) {

        // Check if WooCommerce is active
        if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            return;
        }

	    // Get from arguments
        $beforeTitle = isset($args['before_title']) ? $args['before_title'] : '';
        $afterTitle = isset($args['after_title']) ? $args['after_title'] : '';
        $beforeWidget = isset($args['before_widget']) ? $args['before_widget'] : '';
        $afterWidget = isset($args['after_widget']) ? $args['after_widget'] : '';

	    // Get parameters
	    $apiKey = $instance['api_key'];
        $support = $instance['support'];
        $confidence = $instance['confidence'];
        $limit = $instance['limit'];

	    // Get products ID from card
        $cart = WC()->cart;
        $samples = array();

        if ( $cart->is_empty() ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item_key => $values ) {
            if ( $values['quantity'] > 0 ) {
                $samples[] = $values['product_id'];
            }
        }

	    // Get associated products from Associator API
        $parameters['api_key'] = $apiKey;
        $parameters['samples'] = json_encode($samples);
        $parameters['support'] = $support;
        $parameters['confidence'] = $confidence;
        $query = http_build_query($parameters);

        $url = sprintf('http://%s/%s/associations?%s', self::BASE_URL, self::VERSION, $query);
        $response = wp_remote_get($url);

        if ( !is_array( $response ) ) {
            return;
        }

        $body = json_decode($response['body'], true);
        if ($body['status'] !== "Success") {
            return;
        }

        $associations = $body['associations'];
        $associations = array_reduce($associations, 'array_merge', array());
        $associations = array_slice($associations, 0, $limit);

        if (empty($associations)) {
            return;
        }

		$title = apply_filters( 'widget_title', $instance['title']);
    	$query_args = array(
    		'post_status' 	 => 'publish',
    		'post_type' 	 => 'product',
    		'no_found_rows'  => 1,
    		'post__in'		 => $associations
    	);

        extract( $args );
		$r = new WP_Query( $query_args );
		if ( $r->have_posts() ) {
            echo $beforeWidget;

			if ( $title )
                echo $beforeTitle . $title . $afterTitle;

			echo '<ul class="product_list_widget">';
			while ( $r->have_posts()) {
				$r->the_post();
				global $product;
				 ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $product->id ) ); ?>" title="<?php echo esc_attr( $product->get_title() ); ?>">
							<?php echo $product->get_image(); ?>
							<?php echo $product->get_title(); ?>
						</a>
						<?php if ( ! empty( $show_rating ) ) echo $product->get_rating_html(); ?>
						<?php echo $product->get_price_html(); ?>
					</li>
				<?php
			}

			echo '</ul>';

            echo $afterWidget;
		}

		wp_reset_postdata();
	}

	public function form($instance){
		$title = (isset($instance['title'])) ? $instance['title'] : __( 'Polecamy', 'ndb' );
        $apiKey = $instance['api_key'];
        $support = $instance['support'] ? $instance['support'] : __( '20', 'ndb' );
        $confidence = $instance['confidence'] ? $instance['confidence'] : __( '20', 'ndb' );
        $limit = $instance['limit'] ? $instance['limit'] : __( '10', 'ndb' );
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Tytuł:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"  name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('apiKey'); ?>"><?php _e('Klucz aplikacji:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('api_key'); ?>"  name="<?php echo $this->get_field_name( 'api_key' ); ?>" type="text" value="<?php echo esc_attr( $apiKey ); ?>" />
		</p>
        <p>
            <label for="<?php echo $this->get_field_id('support'); ?>"><?php _e('Wsparcie:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('support'); ?>"  name="<?php echo $this->get_field_name( 'support' ); ?>" type="number" step="1" min="1" max="100" size="3" value="<?php echo esc_attr( $support ); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('confidence'); ?>"><?php _e('Zaufanie:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('confidence'); ?>"  name="<?php echo $this->get_field_name( 'confidence' ); ?>" type="number" step="1" min="1" max="100" size="3" value="<?php echo esc_attr( $confidence ); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Maksymalna ilość rekomendacji:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>"  name="<?php echo $this->get_field_name( 'limit' ); ?>" type="number" step="1" min="1" max="100" size="3" value="<?php echo esc_attr( $limit ); ?>" />
        </p>
		<?php

	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['api_key'] = ( ! empty( $new_instance['api_key'] ) ) ? strip_tags( $new_instance['api_key'] ) : '';
        $instance['support'] = ( ! empty( $new_instance['support'] ) ) ? strip_tags( $new_instance['support'] ) : '';
        $instance['confidence'] = ( ! empty( $new_instance['confidence'] ) ) ? strip_tags( $new_instance['confidence'] ) : '';
        $instance['limit'] = ( ! empty( $new_instance['limit'] ) ) ? strip_tags( $new_instance['limit'] ) : '';

		return $instance;
	}
}

function Associator_register_widgets() {
	register_widget( 'Associator' );
}

add_action( 'widgets_init', 'Associator_register_widgets' );

function mysite_woocommerce_order_status_completed( $order_id ) {
    echo "<script>console.log( 'Debug Objects: " . $order_id . "' );</script>";
}

add_action( 'woocommerce_order_status_pending', 'mysite_woocommerce_order_status_completed', 10, 1 );