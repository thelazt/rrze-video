<?php

namespace RRZE\PostVideo;

add_action( 'widgets_init', function(){
	register_widget( 'RRZE\PostVideo\RRZE_Video_Widget' );
});

add_action('wp_ajax_nopriv_get_js_player_action', 'RRZE\PostVideo\get_js_player_action');
add_action('wp_ajax_get_js_player_action'       , 'RRZE\PostVideo\get_js_player_action');

class RRZE_Video_Widget extends \WP_Widget
{

    /**
     * Sets up the widgets name etc
     */
    public function __construct() {
        $widget_ops = array(
            'classname' => 'video_widget',
            'description' => __('Zeigt Videos in der Sidebar oder im Footer an.'),
        );
        parent::__construct( 'video_widget', 'RRZE Video Widget', $widget_ops );

    }

    /**
     * Outputs the content of the widget
     *
     * @param array $args
     * @param array $instance
     */
    public function widget( $args, $instance ) {

        global $post;
        $helpers = new RRZE_Video_Functions();

        $plugin_settings        = get_option('rrze_video_plugin_options');
        $show_youtube_player    = ( !empty( $plugin_settings['youtube_activate_checkbox'] )) ? $plugin_settings['youtube_activate_checkbox'] : 0;

        // bad according to WP coding standards:
        extract( $args );

        echo $before_widget;

        $form_id                = ( !empty($instance['id']) )         ? $instance['id']         : '';
        $form_url               = ( !empty($instance['url']) )        ? $instance['url']        : '';
        $form_title             = ( !empty($instance['title']) )      ? $instance['title']      : '';
        $form_showtitle         = ( !empty($instance['showtitle']) )  ? $instance['showtitle']  : '';
        $width                  = ( !empty($instance['width']) )      ? $instance['width']      : 270;
        $height                 = ( !empty($instance['height']) )     ? $instance['height']     : 150;
        $meta                   = ( !empty($instance['meta']) )       ? $instance['meta']       : '';
        $taxonomy_genre         = ( !empty($instance['genre']) )      ? $instance['genre']      : '';
        $youtube_resolution     = ( !empty($instance['resolution']) ) ? $instance['resolution'] : '';

        $showtitle  = $form_title;

        $argumentsID = array(
            'post_type'         =>  'Video',
            'p'                 =>  $form_id,
            'posts_per_page'    =>  1,
            'orderby'           =>  'date',
            'order'             =>  'DESC',
        );

        $argumentsTaxonomy = array(
            'post_type' => 'Video',
            'posts_per_page' => 1,
            'orderby'   =>  'rand',
            'tax_query' => array(
                array(
                    'taxonomy' => 'genre',
                    'field'    => 'slug',
                    'terms'    => array( $taxonomy_genre ),
                ),
            ),
        );

        $instance_id = uniqid();

        if ( !empty( $form_url ) ) {

            $is_fau_video = $helpers->is_fau_video($form_url);

            $helpers->equeue_scripts();

            if ($is_fau_video) {
                // @@todo: General setting:
                $fau_video_url = 'https://www.video.uni-erlangen.de/services/oembed/?url=https://www.video.uni-erlangen.de';
                preg_match('/(clip|webplayer)\/id\/(\d+)/',$form_url,$matches);
                $oembed_url    = $fau_video_url . '/' . $matches[1] . '/id/' . $matches[2] . '&format=json';

                $remote_get = wp_safe_remote_get($oembed_url);
                if ( is_wp_error( $remote_get ) ) {
                    $error_string = $remote_get->get_error_message();
                    return '<div id="message" class="error"><p>' . $error_string . '</p></div>';
                } else {
                    $video_url      = json_decode(wp_remote_retrieve_body($remote_get), true);
                    $video_file     = $video_url['file'];
                    $preview_image  = $helpers->video_preview_image('',array('url'=>$form_url));
                    // @@todo: small + large size for image and preview?
                    $picture        = $preview_image;
                    //
                    $desc           = ''; // used where?
                    $orig_video_url = $form_url; // used where?
                    if ( empty( $form_title ) && $form_showtitle == 1 ) {
                        $showtitle  = $video_url['title'];
                        $modaltitle = $video_url['title'];
                    } else if( empty( $form_title ) && $form_showtitle == 0  ) {
                        $showtitle  = '';
                        $modaltitle = $video_url['title'];
                    } else if( !empty( $form_title ) && $form_showtitle == 1  )  {
                        $showtitle  = $form_title;
                        $modaltitle = $form_title;
                    } else {
                        $showtitle  = '';
                        $modaltitle = $form_title;
                    }
                    $author    = ($meta == 1) ? $video_url['author_name']   : '';
                    $copyright = ($meta == 1) ? $video_url['provider_name'] : '';

                    include(plugin_dir_path(__DIR__) . 'templates/rrze-video-widget-fau-template.php');

                }

            } else {

                // youtube or else ...
                $video_id       = $helpers->get_video_id_from_url($form_url);
                $orig_video_url = $form_url;
                $modaltitle     = $form_title;
                $preview_image  = $helpers->video_preview_image('',array('url'=>$form_url));

                include( plugin_dir_path( __DIR__ ) . 'templates/rrze-video-widget-youtube-template.php');

            }

        } else {

            // no url, so check for video post type:

            $widget_video   = $helpers->assign_wp_query_arguments($form_url, $form_id, $argumentsID, $argumentsTaxonomy);
            $genre_title    = self::get_video_title($form_url, $form_id);
            $single_title   = ( $genre_title ) ? $widget_video->posts[0]->post_title : '';

            if ( $widget_video->have_posts() ) {

                while ($widget_video->have_posts()) {

                    $widget_video->the_post();

                    $helpers->enqueue_scripts();

                    $url            = get_post_meta( $post->ID, 'url', true );
                    $orig_video_url = $helpers->get_video_id_from_url($url);
                    $thumbnail      = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'full' );
                    $is_fau_video   = $helpers->is_fau_video($url);

                    if ( $is_fau_video ) {

                        $showtitle          = get_the_title();
                        $desc               = get_post_meta( $post->ID, 'description', true );
                        $video_url          = $helpers->get_video_id_from_url($url);

                        // @@todo: General setting:
                        $fau_video_url = 'https://www.video.uni-erlangen.de/services/oembed/?url=https://www.video.uni-erlangen.de';
                        preg_match('/(clip|webplayer)\/id\/(\d+)/',$form_url,$matches);
                        $oembed_url    = $fau_video_url . '/' . $matches[1] . '/id/' . $matches[2] . '&format=json';
                        $remote_get    = wp_safe_remote_get($oembed_url);

                        if ( is_wp_error( $remote_get ) ) {
                            $error_string = $remote_get->get_error_message();
                            return '<div id="message" class="error"><p>' . $error_string . '</p></div>';
                        } else {
                            $video_url      = json_decode(wp_remote_retrieve_body($remote_get), true);
                            $video_file     = $video_url['file'];
                            $preview_image  = $helpers->video_preview_image('',array('url'=>$form_url));
                            // @@todo: small + large size for image and preview?
                            $picture        = $preview_image;
                        }
                        if ( ! empty( $single_title ) ) {
                            $showtitle  = ( $form_showtitle == 1 ) ? $single_title: '';
                            $modaltitle = $single_title;
                        } else if ( empty( $form_title ) ) {
                            $showtitle  = ( $form_showtitle == 1 ) ? $video['title'] : '';
                            $modaltitle = $video['title'];
                        } else {
                            $showtitle  = ( $form_showtitle == 1 ) ? $form_title : '';
                            $modaltitle = $form_title;
                        }

                        $author    = ($meta == 1) ? $video['author_name'] : '';
                        $copyright = ($meta == 1) ? $video['provider_name'] : '';

                        include( plugin_dir_path( __DIR__ ) . 'templates/rrze-video-widget-fau-template.php');

                    } else {

                        // youtube etc video

                        $showtitle       = get_the_title();
                        $video_post_url  = get_post_meta( $post->ID, 'url', true );
                        $desc            = get_post_meta( $post->ID, 'description', true );
                        $youtube_title   = get_the_title();

                        $video_id        = $helpers->get_video_id_from_url($video_post_url);
                        $preview_image_opts = array(
                            'provider'   => 'youtube',
                            'id'         => $video_id,
                            'url'        => $form_url,
                            'resolution' => $youtube_resolution,
                            'thumbnail'  => $thumbnail
                        );
                        $preview_image   = $helpers->video_preview_image('',$preview_image_opts);

                        $orig_video_url = $video_post_url;

                        if ( empty( $form_title ) ) {
                            $showtitle  =  ( $form_showtitle == 1 ) ? $youtube_title : '';
                            $modaltitle =  $youtube_title;
                        } else {
                            $showtitle  = ( $form_showtitle == 1 ) ? $form_title : '';
                            $modaltitle = $form_title;
                        }

                        include( plugin_dir_path( __DIR__ ) . 'templates/rrze-video-widget-youtube-template.php');

                    }

                } // endwhile

                // add players js to footer:
                add_action('wp_footer', 'RRZE\PostVideo\js_player_ajax');

            } else {

                $no_posts = '<p>' . _e( 'Es wurden keine Videos gefunden!', 'rrze-video' ) . '</p>';
                echo $no_posts;

            }

            wp_reset_postdata();

            echo $after_widget;
        }
    }


    /**
     * Outputs the options form on admin
     *
     * @param array $instance The widget options
     */
    public function form( $instance ) {
        $title      = !empty( $instance['title'] )      ? $instance['title']      : '';
        $id         = !empty( $instance['id'] )         ? $instance['id']         : '';
        $url        = !empty( $instance['url'] )        ? $instance['url']        : '';
        $width      = !empty( $instance['width'] )      ? $instance['width']      : 270;
        $height     = !empty( $instance['height'] )     ? $instance['height']     : 150;
        $showtitle  = !empty( $instance['showtitle'])   ? $instance['showtitle']  : '';
        $meta       = !empty( $instance['meta'] )       ? $instance['meta']       : '';
        $genre      = !empty( $instance['genre'] )      ? $instance['genre']      : '';
        $resolution = !empty( $instance['resolution'] ) ? $instance['resolution'] : '';
        ?>

         <p>
        <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Titel:', 'rrze-video' ); ?></label>
        <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" placeholder="title" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
        <em><?php _e('Videotitel' ) ?></em>
        </p>

         <p>
        <label for="<?php echo esc_attr( $this->get_field_id( 'id' ) ); ?>"><?php esc_attr_e( 'ID:', 'rrze-video' ); ?></label>
        <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'id' ) ); ?>" placeholder="id" name="<?php echo esc_attr( $this->get_field_name( 'id' ) ); ?>" type="text" value="<?php echo esc_attr( $id ); ?>">
        <em><?php _e('Datensatz-ID' ) ?></em>
        </p>

        <p>
        <label for="<?php echo esc_attr( $this->get_field_id( 'url' ) ); ?>"><?php esc_attr_e( 'Url:', 'rrze-video' ); ?></label>
        <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'url' ) ); ?>" placeholder="url" name="<?php echo esc_attr( $this->get_field_name( 'url' ) ); ?>" type="text" value="<?php echo esc_attr( $url ); ?>">
        <em><?php _e('z. B. http://www.video.uni-erlangen.de/webplayer/id/13953') ?></em>
        </p>

        <p>
        <label for="<?php echo esc_attr( $this->get_field_id( 'width' ) ); ?>"><?php esc_attr_e( 'Breite:', 'rrze-video' ); ?></label>
        <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'width' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'width' ) ); ?>" type="text" value="<?php echo esc_attr( $width ); ?>">
        <em><?php _e('Breite des Vorschaubildes' ) ?></em>
        </p>

        <p>
        <label for="<?php echo esc_attr( $this->get_field_id( 'height' ) ); ?>"><?php esc_attr_e( 'Höhe:', 'rrze-video' ); ?></label>
        <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'height' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'height' ) ); ?>" type="text" value="<?php echo esc_attr( $height ); ?>">
        <em><?php _e('Höhe des Vorschaubildes' ) ?></em>
        </p>

        <p>
        <label for="<?php echo $this->get_field_id( 'showtitle' ); ?>"><?php _e('Zeige Widget Videotitel:' ) ?></label>
        <select class="widefat" id="<?php echo $this->get_field_id( 'showtitle' ); ?>" name="<?php echo $this->get_field_name( 'showtitle' ); ?>">
            <option value="1"<?php echo ( $showtitle == '1' ) ? 'selected' : ''; ?>>Ein</option>
            <option value="0"<?php echo ( $showtitle == '' )  ? 'selected' : ''; ?>>Aus</option>
        </select>
        </p>

        <p>
        <label for="<?php echo $this->get_field_id( 'meta' ); ?>"><?php _e('Zeige Metainformationen:' ) ?></label>
        <select class="widefat" id="<?php echo $this->get_field_id('meta'); ?>" name="<?php echo $this->get_field_name( 'meta' ); ?>">
            <option value="1"<?php echo ( $meta == '1' ) ? 'selected' : ''; ?>>Ein</option>
            <option value="0"<?php echo ( $meta == '' )  ? 'selected' : ''; ?>>Aus</option>
        </select>
        </p>

        <?php

        $terms = get_terms( array(
            'taxonomy' => 'genre',
            'hide_empty' => true,
        ) );

        ?>

        <p>
        <label for="<?php echo $this->get_field_id('genre'); ?>"><?php _e('Zufallsvideo nach Genre:'); ?></label>
        <select class="widefat" id="<?php echo $this->get_field_id('genre'); ?>" name="<?php echo $this->get_field_name('genre'); ?>">
        <?php

        $opts_select = 0;
        $opts_html   = '';
        foreach($terms as $term) {
            if ($term->name == $genre) {
                $selected = ' selected';
                $opts_select++;
            } else {
                $selected = '';
            }
            $opts_html .= '<option value="' . $term->name . '"' . $selected . '>' . $term->name . '</option>' . PHP_EOL;
        }
        $initial_selected = ($opts_select == 0) ? ' selected' : '';
        echo '<option value="0"' . $initial_selected . '>' . __('Genre auswählen') . '</option>' .PHP_EOL;
        echo $opts_html;

        ?>
        </select>
        </p>

        <p>
        <label for="<?php echo $this->get_field_id( 'resolution' ); ?>"><?php _e('Auflösung des Youtube-Bildes:' ) ?></label>
        <select class="widefat" id="<?php echo $this->get_field_id( 'resolution' ); ?>" name="<?php echo $this->get_field_name( 'resolution' ); ?>">
            <option value=""><?php _e('Auswählen') ?></option>
            <option value="1"<?php echo ( $resolution == '1' ) ? 'selected' : ''; ?>>maxresultion</option>
            <option value="2"<?php echo ( $resolution == '2' ) ? 'selected' : ''; ?>>default</option>
            <option value="3"<?php echo ( $resolution == '3' ) ? 'selected' : ''; ?>>hqdefault</option>
            <option value="4"<?php echo ( $resolution == '4' ) ? 'selected' : ''; ?>>mqdefault</option>
            <option value="5"<?php echo ( $resolution == '5' ) ? 'selected' : ''; ?>>sddefault</option>
        </select>
        </p>
        <?php
    }

   /*
     * Im Widget-Screen werden die alten Eingaben mit
     * den neuen Eingaben ersetzt und gespeichert.
     */
    public function update( $new_instance, $old_instance ) {

        $instance = $old_instance;
        $instance[ 'title' ]        = strip_tags( $new_instance[ 'title' ] );
        $instance[ 'id' ]           = strip_tags( $new_instance[ 'id' ] );
        $instance[ 'url' ]          = strip_tags( $new_instance[ 'url' ] );
        $instance[ 'width' ]        = strip_tags( $new_instance[ 'width' ] );
        $instance[ 'height' ]       = strip_tags( $new_instance[ 'height' ] );
        $instance[ 'showtitle' ]    = strip_tags( $new_instance[ 'showtitle' ] );
        $instance[ 'meta' ]         = strip_tags( $new_instance[ 'meta' ] );
        $instance[ 'genre' ]        = strip_tags( $new_instance[ 'genre' ] );
        $instance[ 'resolution' ]   = strip_tags( $new_instance[ 'resolution' ] );

        return $instance;
    }

    public static function get_video_title($url, $id) {
        if (!empty($id)) {
            return false;
        } elseif (!empty($url)) {
            return false;
        } else {
            return true;
        }
    }

    /*
    public function widget_mejs_ajax() { ?>
       <script type="text/javascript" >
	jQuery(document).ready(function($) {

            // $('a[href="#get_video_widget"]').click(function(){
            $('a[data-type="videothumb"]').click(function(){

                var id = $(this).attr('data-id');
                var poster = $(this).attr('data-preview-image');
                var video_file = $(this).attr('data-video-file');

                $.ajax({
                    url: videoajax.ajaxurl,
                    data: {
                        'action': 'widget_mejs_callback_action',
                        'id': id,
                        'poster': poster,
                        'video_file': video_file
                    },
                    success:function() {

                        var video = '<video class="player img-responsive center-block" width="640" height="360" poster="' + poster + '" controls="controls" preload="none">' +
                        '<source src="' + video_file + '" type="video/mp4" />' +
                        '</video>';

                        $(".videocontent" + id)
                            .html(video)
                            .find(".player")
                            .mediaelementplayer({
                            alwaysShowControls: true,
                                features: ['playpause','stop','current','progress','duration','volume','tracks','fullscreen'],
                        });

                    },
                    error: function(errorThrown){
                        window.alert(errorThrown);
                    }
                 });
             })

	});
	</script> <?php
    }

    public function widget_mejs_callback_action() {
    }

    public function widget_mejs_youtube_ajax() { ?>
	<script type="text/javascript" >
	jQuery(document).ready(function($) {

            $('a[href="#get_widget_mejs_youtube"]').click(function(){

                var youtube_id  = $(this).attr('data-youtube-id');
                var id      = $(this).attr('data-box-id');

                $.ajax({
                    url: videoajax.ajaxurl,
                    data: {
                        'action': 'widget_mejs_youtube_callback_action',
                        'youtube_id': youtube_id,
                        'id': id
                    },
                    success:function() {

                        var video = '<video class="player"  width="640" height="360" controls="controls" preload="none">' +
                        '<source src="https://www.youtube.com/watch?v=' + youtube_id + '" type="video/youtube" />' +
                        '</video>';

                        $(".videocontent" + id)
                            .html(video)
                            .find(".player")
                            .mediaelementplayer({
                            alwaysShowControls: true,
                                features: ['playpause','stop','current','progress','duration','volume','tracks','fullscreen'],
                        });


                    },
                    error: function(errorThrown){
                        window.alert(errorThrown);
                    }
                 });
             })


	});
	</script> <?php
    }

    public function widget_mejs_youtube_callback_action() {
    }

    public function widget_youtube_ajax() { ?>
	<script type="text/javascript" >
	jQuery(document).ready(function($) {

            $('a[href="#get_widget_youtube"]').click(function(){

                var youtube_id  = $(this).attr('data-youtube-id');
                var id      = $(this).attr('data-box-id');

                $.ajax({
                    url: videoajax.ajaxurl,
                    data: {
                        'action': 'widget_youtube_callback_action',
                        'youtube_id': youtube_id,
                        'id': id
                    },
                    success:function() {

                        var iframe = document.createElement("iframe");
                        iframe.setAttribute("frameborder", "0");
                        iframe.setAttribute("allowfullscreen", "");
                        iframe.setAttribute("src", "https://www.youtube.com/embed/" + youtube_id + "?rel=0&showinfo=0");

                        $(".embed-container" + id)
                            .html(iframe)
                            .find(".youtube-video")

                    },
                    error: function(errorThrown){
                        window.alert(errorThrown);
                    }
                });
            })
	});
	</script> <?php
    }
**
    public function enqueue_scripts()
    {
        wp_enqueue_script( 'rrze-main-js' );
        wp_enqueue_style( 'mediaelementplayercss' );
        wp_enqueue_script( 'mediaelementplayerjs' );
        wp_enqueue_script( 'rrze-video-js' );
        wp_enqueue_style( 'rrze-video-css' );
    }
/**
    public function widget_youtube_callback_action()
    {
    }
    */
}
