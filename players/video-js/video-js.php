<?php

add_action('wp_print_scripts', 'videojs_add_scripts');
function videojs_add_scripts(){
    wp_enqueue_script("videojs",  plugins_url('video.min.js', __FILE__ ), array(), "3.2.0", false);
    wp_enqueue_style("videojs",  plugins_url('video-js.min.css', __FILE__ ), array(), "3.2.0");
}

add_action('player_render', 'videojs_render');
function videojs_render($args){
    $options = get_option('player');
    
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
    
    if(isset($args['videos']['html5'])) {
        $src = $args['videos']['html5'][0]['src'];
    } else if(isset($args['videos']['flash'])) {
        $src = $args['videos']['flash'][0]['src'];
    }
    
    $attributes = '';
    if($args['controls'] != 'none') $attributes .= " controls";
    if($args['loop']) $attributes .= " loop";
    if($args['autoplay']) $attributes .= " autoplay";

	?><video <?php echo $attributes ?> class="video-js vjs-default-skin" id="player<?php echo $args['instance'] ?>" poster="<?php echo $args['videos'][0]['poster'] ?>" width="<?php echo $args['width'] ?>" height="<?php echo $args['height'] ?>">
	    <?php foreach(array('flash', 'html5') as $mode): ?>
	        <source src="<?php echo $args['videos'][0][$mode]['src'] ?>" type="video/<?php echo array_pop(explode('.', $args['videos'][0][$mode]['src'])) ?>">
	    <?php endforeach; ?>
	</video>
	
	<script>
        _V_("player<?php echo $args['instance'] ?>", { 
            techOrder : [<?php echo $modes ?>],
            flash:{swf: "<?php echo plugins_url('video-js.swf', __FILE__ ) ?>"}
        });
    </script><?php
}
?>
