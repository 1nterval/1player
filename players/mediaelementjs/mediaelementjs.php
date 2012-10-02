<?php

add_action('wp_print_scripts', 'mediaelementjs_add_scripts');
function mediaelementjs_add_scripts(){
    $options = get_option('player_video');
    wp_enqueue_script("mediaelementjs",  plugins_url('mediaelement-and-player.min.js', __FILE__ ), array('jquery'), "2.9.5", false);
    wp_enqueue_style("mediaelementjs",  plugins_url('mediaelementplayer.css', __FILE__ ), array(), "2.9.5");
    if($options['skin'] != 'none') 
        wp_enqueue_style($options['skin'], plugins_url('skins/'.$options['skin'].'/mejs-skin.css', __FILE__ ), array("mediaelementjs"));
}

add_action('mediaelementjs_video_render', 'mediaelementjs_render');
function mediaelementjs_render($args){
    global $mime_types;
    $options = get_option('player_video');
    
    $attributes = "";
    if($args['controls'] != 'none') $attributes .= " controls";
    if($args['loop']) $attributes .= " loop";
    if($args['autoplay']) $attributes .= " autoplay";

    ?><video <?php echo $attributes ?> <?php if($options['skin'] != "none") echo 'class="'.$options['skin'].'"' ?> id="player<?php echo $args['instance'] ?>" poster="<?php echo $args['videos'][0]['poster'] ?>" width="<?php echo $args['width'] ?>" height="<?php echo $args['height'] ?>">
        <?php foreach($args['videos'][0]['html5']['src'] as $video): ?>
            <source src="<?php echo $video['src'] ?>" <?php if($video['compat'] != 'none') echo 'type="'.$mime_types[$video['compat']].'"' ?>>
        <?php endforeach; ?>
    </video>
	
	<script>
	    var args = {};
	    
	    <?php if($options['controls'] == "fixed") : /* ne pas cacher la barre de controle */ ?>
	        args.alwaysShowControls = true;
	    <?php endif; ?>
	    
	    <?php switch($options['mode']){
	        case "html5": $mode = "native"; break;
	        case "flashhtml5":
	        case "flash": $mode = "shim"; break;
	        case "html5flash": $mode = "auto"; break;
	    } ?>
	    
        <?php if($options['mode'] == "flashhtml5") : /* forcer le mode flash seulement si flash est installé */ ?>
            if(navigator.mimeTypes == undefined || navigator.mimeTypes["application/x-shockwave-flash"] != undefined)
        <?php endif; ?>
        args.mode = "<?php echo $mode ?>";
        args.pluginPath = "<?php echo plugins_url('/', __FILE__ ) ?>";
	    
        var mejs_player = new MediaElementPlayer("#player<?php echo $args['instance'] ?>", args);
        
        <?php if($options['controls'] == "none") : /* supprimer la barre de controle */ ?>
            mejs_player.disableControls();
        <?php endif; ?>
    </script><?php
}

add_filter('mediaelementjs_video_skins_list', 'mediaelementjs_skins_list');
function mediaelementjs_skins_list($list){
    foreach (scandir(plugin_dir_path(__FILE__).'skins') as $dir) {
        $fulldir = plugin_dir_path(__FILE__).'skins/'.$dir;
        if($dir != "." && $dir != ".." && is_dir($fulldir)) {
            $list[$dir] = $dir;
        }
    }
    
    return $list;
}

add_filter('mediaelementjs_video_skins_description', 'mediaelementjs_skins_description');
function mediaelementjs_skins_description($desc){
    return sprintf(__('Skins are located in %1$s folder', '1player'), '<code>/wp-content/plugins/1player/players/mediaelementjs/skins</code>');
}

add_filter('mediaelementjs_controls_positions_list', 'mediaelementjs_controls_positions_list');
function mediaelementjs_controls_positions_list($list){
    $list["fixed"] = __("Fixed", "1player");
    return $list;
}

add_filter('mediaelementjs_video_modes_list', 'mediaelementjs_modes_list');
function mediaelementjs_modes_list($list){
    $list["html5flash"] = __('HTML5 if possible, else Flash','1player');
    $list["flashhtml5"] = __('Flash if possible, else HTML5','1player');
    return $list;
}

?>
