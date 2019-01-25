<?php
    $inline_css = '';
    if ($width_shortcode != 640) {
      $inline_css = ' style="width: ' . $width_shortcode . (!empty($suffix) ? $suffix : '') . ';"';
    }
    $video_title = '';
    if (!empty($showtitle)) {
        $video_title = '<' . $rrze_video_shortcode['titletag'] . '>' . $showtitle . '</' . $rrze_video_shortcode['titletag'] . '>';
    }
    $preview_image_title = $video_url['title'];
    $preview_image_src   = (!empty($thumbnail)) ? $thumbnail[0] : $preview_image;
    $preview_image_class = 'image' . $instance_id;
?>

<div class="rrze-video"<?php echo $inline_css;?>>
    <?php echo $video_title; ?>
    <div class="rrze-video-container rrze-video-id-<?php echo $instance_id; ?>">
        <a
            href="<?php echo $video_file; ?>"
            data-player-type="fauvideo"
            data-toggle="modal"
            data-box-id="<?php echo $instance_id; ?>"
            data-preview-image="<?php echo $preview_image ?>"
            data-video-url="<?php echo $video_file ?>"
            data-target="#videoModal<?php echo $instance_id; ?>"
        >
        <img src="<?php echo $preview_image_src; ?>" title="<?php echo $preview_image_title; ?>"  alt="Video aufrufen" class="<?php echo $preview_image_class; ?>" />
        <div class="middle" aria-hidden="true">
            <div class="play-button"><i class="fa fa-play-circle-o"></i></div>
        </div>
        </a>
    </div>
    <div class="modal fade rrze-video-modal" id="videoModal<?php echo $instance_id; ?>" role="dialog" data-backdrop="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="close-modal" data-dismiss="modal">
                        <i class="fa fa-times" aria-hidden="true"></i>
                    </div>
                    <?php echo ( ! empty( $modaltitle ) ) ? '<h2 class="modal-title">' . $modaltitle . '</h2>' : '';  ?>
                </div><!-- .modal-header -->
                <div class="modal-body">
                    <div class="videocontent<?php echo $instance_id; ?>">
                        <div class="player">
                            <img src="<?php echo $preview_image ?>" alt="<?php echo $video_url['title'] ?>" />
                        </div>
                    </div>
                </div><!-- .modal-body -->
                <div class="modal-footer">
                    <p class="description"><?php _e('Beschreibung:'); ?> <?php echo (!empty($description)) ? $description : __('Keine Angaben') ?></p>
                     <?php if($rrze_video_shortcode['showinfo'] == '1') { ?>
                        <dl>
                            <dt class="meta_heading"><?php _e('Autor:'); ?></dt>
                            <dd class="meta_content"><?php echo $author; ?></dd>
                            <dt class="meta_heading"><?php _e('Quelle:'); ?></dt>
                            <dd class="meta_content"><a href="<?php echo $video_file; ?>"><?php _e('Download:'); ?></a></dd>
                            <dt class="meta_heading"><?php _e('Copyright:'); ?></dt>
                            <dd class="meta_content"><?php echo $copyright; ?></dd>
                        </dl>
                    <?php } ?>
                </div><!-- .modal-footer -->
            </div><!-- .modal-content -->
        </div><!-- .modal-dialog -->
    </div><!-- .modal -->
</div><!-- .rrze-video -->