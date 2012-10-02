<?php

add_action('wp_print_scripts', 'videojs_add_scripts');
function videojs_add_scripts(){
    wp_enqueue_script("videojs",  plugins_url('video.min.js', __FILE__ ), array(), "3.2.0", false);
    wp_enqueue_style("videojs",  plugins_url('video-js.min.css', __FILE__ ), array(), "3.2.0");
}

add_action('video-js_video_render', 'videojs_render');
function videojs_render($args){
    global $mime_types;
    
    $options = get_option('player_video');
    
    // gérer les modes et leur priorité
    $modes = "";
    $mode = $options['mode'];
    for($i=1; $i<=2; $i++) {
        if($modes != "") $modes.=",\n";
        if(substr($mode, 0, 5) == "html5")
            $modes .= '"html5"';
        else if(substr($mode, 0, 5) == "flash")
            $modes .= '"flash"';
        $mode = substr($mode, 5);
        if($mode == "") break;
    }
    
    $attributes = '';
    if($args['controls'] != 'none') $attributes .= " controls";
    if($args['loop']) $attributes .= " loop";
    if($args['autoplay']) $attributes .= " autoplay";
    
    $poster = $args['videos'][0]['poster'];
    
    ?><video<?php echo $attributes ?> class="video-js vjs-default-skin" id="player<?php echo $args['instance'] ?>" poster="<?php echo $poster ?>" width="<?php echo $args['width'] ?>" height="<?php echo $args['height'] ?>">
        <?php foreach($args['videos'][0]['html5']['src'] as $video): ?>
            <source src="<?php echo $video['src'] ?>" <?php if($video['compat'] != 'none') echo 'type="'.$mime_types[$video['compat']].'"' ?>>
        <?php endforeach; ?>
    </video>
	
    <script>
        _V_("player<?php echo $args['instance'] ?>", { 
            techOrder : [<?php echo $modes ?>],
            flash:{swf: "<?php echo plugins_url('video-js.swf', __FILE__ ) ?>"}
        });
    </script><?php
}

add_filter('video-js_video_skins_list', 'videojs_skins_list');
function videojs_skins_list($list){
    // TODO : add skins here
    return $list;
}

add_filter('video-js_video_modes_list', 'videojs_modes_list');
function videojs_modes_list($list){
    $list["html5flash"] = __('HTML5 if possible, else Flash','1player');
    $list["flashhtml5"] = __('Flash if possible, else HTML5','1player');
    return $list;
}
?>
