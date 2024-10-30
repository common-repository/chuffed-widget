<?php
/*
Plugin Name:  Chuffed Donation Widget
Plugin URI:   http://ignite.digitalignition.net/articlesexamples/chuffed-donation-widget
Description:  Easily add a widget for your chuffed campaign
Author:       Greg Tangey
Author URI:   http://ignite.digitalignition.net/
Version:      0.2.1
*/

/*  Copyright 2015  Greg Tangey  (email : greg@digitalignition.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class ChuffedWidget extends WP_Widget {

  const USER_AGENT = "WordPress Chuffed Widget v0.2";

	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			'chuffed_widget', // Base ID
			__( 'Chuffed Campaign', 'text_domain' ), // Name
			array( 'description' => __( 'A chuffed.org campaign widget', 'text_domain' ), ) // Args
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
    $campaign_id = $instance['campaign_id'];
    $campaign_slug = $instance['slug'];

    if( ! empty($campaign_id) || !empty($campaign_slug)) {

      if( empty($campaign_id)) {
        $campaign_id = $this->getCampaignIdFromSlug($campaign_slug);
      }

      $chuffedData = $this->getChuffedData($campaign_id);
      $targetAmount = intval($chuffedData['data']['camp_amount']);
      $collectedAmount = intval($chuffedData['data']['camp_amount_collected']);
      $slug = $chuffedData['data']['slug'];
      $title = $chuffedData['data']['title'];

      echo $args['before_widget'];
      if ( ! empty( $instance['title'] ) ) {
        echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ). $args['after_title'];
      }

      $this->renderChuffed($targetAmount,$collectedAmount,$slug,$title);

      echo $args['after_widget'];
    }
    else {
      echo __("A Campaign ID is not set in the widgets setting", "text_domain");
    }
	}

  public function getCampaignIdFromSlug($slug) {
    $transName = "chuffed-widget-slug-lookup-$slug";
    $cacheTime = 60; // minutes

    delete_transient($transName);

    if( false == ($campaignId = get_transient($transName))) {
      $get_args = array(
        'user-agent' => self::USER_AGENT,
      );

      $html = wp_remote_get("https://chuffed.org/project/$slug",$get_args);

      $response_code    = wp_remote_retrieve_response_code( $html );
      $response_message = wp_remote_retrieve_response_message( $html );


      if ( 200 != $response_code && ! empty( $response_message ) ) {
        $err = $response_message;
      } elseif ( 200 != $response_code ) {
        $err = "Uknown err";
      } else {
        $chuffedData = wp_remote_retrieve_body( $html );

        $doc = new DOMDocument;
        @$doc->loadHTML($chuffedData);

        $xp  = new DOMXPath($doc);
        $tag = $xp->query("//iframe[(contains(@src, '/iframe/'))]");

        if(sizeof($tag)==1) {
          $url = explode("/",$tag[0]->attributes->getNamedItem('src')->value);
          $campaignId = $url[2];
          set_transient($transName, $campaignId, 60 * $cacheTime);
        }
      }
    }
    return $campaignId;
  }

  public function getChuffedData($campaign_id) {
    $transName = "chuffed-widget-$campaign_id";
    $cacheTime = 30; // minutes
    delete_transient($transName);
    if(false === ($chuffedData = get_transient($transName) ) ){
      $get_args = array(
        'user-agent' => self::USER_AGENT
      );
      $json = wp_remote_get("https://chuffed.org/api/v1/campaign/$campaign_id",$get_args);

      // Check the    response code
      $response_code    = wp_remote_retrieve_response_code( $json );
      $response_message = wp_remote_retrieve_response_message( $json );
      // phpinfo();
      // var_dump($json);exit;

      if ( 200 != $response_code && ! empty( $response_message ) ) {
        $err = $response_message;
      } elseif ( 200 != $response_code ) {
        $err = "Uknown err";
      } else {
        $chuffedData = wp_remote_retrieve_body( $json );
      }

      $chuffedData = json_decode($chuffedData, true);
      set_transient($transName, $chuffedData, 60 * $cacheTime);
    }

    return $chuffedData;
  }

  public function renderChuffed($targetAmount,$collectedAmount,$slug,$title,$is_widget=true) {
    $percWidth = intval(($collectedAmount/$targetAmount)*100);
    if($is_widget) {
      $positon = "position: relative;";
    } else {
      $position = "";
    }
    ?>
    <a style="text-decoration: none" href="https://chuffed.org/project/<?php echo $slug; ?>">
      <div style="<?php echo $position; ?>">
          <h1><?php echo $title; ?></h1>
          <div style="width: 100%;height:15px;background-color: #F9F9F9 !important;">
              <div style="width: <?php echo $percWidth;?>%; height: 15px;background-color: #28ab60 !important;"></div>
          </div>
          <h2 style="font-size: 50px;margin-bottom: 0;padding-bottom: 0;line-height: 56px;">
              $<span><?php echo $collectedAmount; ?></span>
          </h2>
          <p style="color:#9b9b9b;"><?php echo __("Raised of", "text_domain"); ?>
              $<span><?php echo $targetAmount; ?></span>
          </p>
      </div>
    </a>
    <?php
  }

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
    $title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Chuffed Campaign', 'text_domain' );
    $campaign_id = ! empty( $instance['campaign_id'] ) ? $instance['campaign_id'] : '';
    $slug = ! empty( $instance['slug'] ) ? $instance['slug'] : '';

		?>
		<p>
    <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
    <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		<label for="<?php echo $this->get_field_id( 'campaign_id' ); ?>"><?php _e( 'Campaign ID:' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'campaign_id' ); ?>" name="<?php echo $this->get_field_name( 'campaign_id' ); ?>" type="text" value="<?php echo esc_attr( $campaign_id ); ?>">
    <label for="<?php echo $this->get_field_id( 'slug' ); ?>"><?php _e( 'Campaign Slug:' ); ?></label>
    <input class="widefat" id="<?php echo $this->get_field_id( 'slug' ); ?>" name="<?php echo $this->get_field_name( 'slug' ); ?>" type="text" value="<?php echo esc_attr( $slug ); ?>">
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
		$instance['campaign_id'] = ( ! empty( $new_instance['campaign_id'] ) ) ? strip_tags( $new_instance['campaign_id'] ) : '';
    $instance['slug'] = ( ! empty( $new_instance['slug'] ) ) ? strip_tags( $new_instance['slug'] ) : '';
    $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';

		return $instance;
	}

}

function register_chuffed_widget() {
    register_widget( 'ChuffedWidget' );
}

function chuffed_shortcode($attributes) {
  $chuffed_widget = new ChuffedWidget();

  $shortcodeData = shortcode_atts( array(
    'campaign_id' => '',
    'slug' => ''
  ), $attributes );

  if( ! empty($shortcodeData['campaign_id']) || !empty($shortcodeData['slug'])) {
    if( empty($shortcodeData['campaign_id'])) {
      $campaign_id = $chuffed_widget->getCampaignIdFromSlug($shortcodeData['slug']);
    } else {
      $campaign_id = $shortcodeData['campaign_id'];
    }
    $chuffedData = $chuffed_widget->getChuffedData($campaign_id);
    $targetAmount = intval($chuffedData['data']['camp_amount']);
    $collectedAmount = intval($chuffedData['data']['camp_amount_collected']);
    $slug = $chuffedData['data']['slug'];
    $title = $chuffedData['data']['title'];

    $chuffed_widget->renderChuffed($targetAmount,$collectedAmount,$slug,$title,false);
  }
}

add_action( 'widgets_init', 'register_chuffed_widget' );
add_shortcode( 'chuffed', 'chuffed_shortcode' );
