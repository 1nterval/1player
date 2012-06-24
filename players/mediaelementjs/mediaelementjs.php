<?php

add_action('wp_print_scripts', 'mediaelementjs_add_scripts');
function mediaelementjs_add_scripts(){
    $options = get_option('player');
    wp_enqueue_script("mediaelementjs",  plugins_url('mediaelement-and-player.min.js', __FILE__ ), array(), "2.9.1", false);
    wp_enqueue_style("mediaelementjs",  plugins_url('mediaelementplayer.css', __FILE__ ), array(), "2.9.1");
    wp_enqueue_style($options['skin'], plugins_url('skins/'.$options['skin'].'/mejs-skin.css', __FILE__ ), array("mediaelementjs"));
}

add_action('player_render', 'mediaelementjs_render');
function mediaelementjs_render($args){
    $options = get_option('player');
    
    if(isset($args['videos']['html5'])) {
        $src = $args['videos']['html5'][0]['src'];
        $poster = $args['videos']['html5'][0]['poster'];
    } else if(isset($args['videos']['flash'])) {
        $src = $args['videos']['flash'][0]['src'];
        $poster = $args['videos']['flash'][0]['poster'];
    }
    
    $attributes = '';
    if($args['controls'] != 'none') $attributes .= " controls";
    if($args['loop']) $attributes .= " loop";
    if($args['autoplay']) $attributes .= " autoplay";

	?><video <?php echo $attributes ?> class="<?php echo $options['skin'] ?>" id="player<?php echo $args['instance'] ?>" poster="<?php echo $poster ?>" width="<?php echo $args['width'] ?>" height="<?php echo $args['height'] ?>">
	    <source src="<?php echo $src ?>" type='video/mp4'>
	</video>
	<script>
        new MediaElementPlayer("#player<?php echo $args['instance'] ?>");
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

?>
