<?php

add_action('wp_print_scripts', 'mediaelementjs_add_scripts');
function mediaelementjs_add_scripts(){
    $options = get_option('player');
    wp_enqueue_script("mediaelementjs",  plugins_url('mediaelement-and-player.min.js', __FILE__ ), array(), "2.9.1", false);
    wp_enqueue_style("mediaelementjs",  plugins_url('mediaelementplayer.css', __FILE__ ), array(), "2.9.1");
    if($options['skin'] != 'none') 
        wp_enqueue_style($options['skin'], plugins_url('skins/'.$options['skin'].'/mejs-skin.css', __FILE__ ), array("mediaelementjs"));
}

add_action('player_render', 'mediaelementjs_render');
function mediaelementjs_render($args){
    $options = get_option('player');
    
    $attributes = "";
    if($args['controls'] != 'none') $attributes .= " controls";
    if($args['loop']) $attributes .= " loop";
    if($args['autoplay']) $attributes .= " autoplay";

	?><video <?php echo $attributes ?> <?php if($options['skin'] != "none") echo 'class="'.$options['skin'].'"' ?> id="player<?php echo $args['instance'] ?>" poster="<?php echo $args['videos'][0]['poster'] ?>" width="<?php echo $args['width'] ?>" height="<?php echo $args['height'] ?>">
	    <?php foreach(array('flash', 'html5') as $mode): ?>
	        <source src="<?php echo $args['videos'][0][$mode]['src'] ?>" type="video/<?php echo array_pop(explode('.', $args['videos'][0][$mode]['src'])) ?>">
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
	    
        var mejs_player = new MediaElementPlayer("#player<?php echo $args['instance'] ?>", args);
        
        <?php if($options['controls'] == "none") : /* supprimer la barre de controle */ ?>
            mejs_player.disableControls();
        <?php endif; ?>
    </script><?php
}

add_filter('1player_skins_list', 'mediaelementjs_skins_list');
function mediaelementjs_skins_list($list){
    foreach (scandir(plugin_dir_path(__FILE__).'skins') as $dir) {
        $fulldir = plugin_dir_path(__FILE__).'skins/'.$dir;
        if($dir != "." && $dir != ".." && is_dir($fulldir)) {
            $list[$dir] = $dir;
        }
    }
    
    return $list;
}

add_filter('1player_skins_description', 'mediaelementjs_skins_description');
function mediaelementjs_skins_description($desc){
    return sprintf(__('Skins are located in %1$s folder', '1player'), '<code>/wp-content/plugins/1player/players/mediaelementjs/skins</code>');
}

add_filter('1player_controls_positions_list', 'mediaelementjs_controls_positions_list');
function mediaelementjs_controls_positions_list($list){
    $list["fixed"] = __("Fixed", "1player");
    return $list;
}

?>
