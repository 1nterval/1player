<?php
/*
 * Plugin Name: 1Player
 * Author URI: http://www.1nterval.com
 * Description: Advanced HTML5 video and audio player
 * Author: Fabien Quatravaux
 * Version: 1.2
*/

add_action('init', 'player_init');
function player_init() {
    load_plugin_textdomain( '1player', false, basename(dirname(__FILE__)) );
    
    // labelisation des versions
    global $labels, $mime_types;
    $labels = array(
        'hd' => __('High quality', '1player'),
        'sd' => __('Low quality', '1player'),
        'h264' => __('MP4 h264', '1player'),
        'webm' => __('WebM', '1player'),
        'flv' => __('FLV', '1player'),
        'flash' => __('Flash', '1player'),
        'html5' => __('HTML5', '1player'),
        'mp3' => __('MP3', '1player'),
        'ogg' => __('OGG Vorbis', '1player'),
    );
    $mime_types = array(
        'h264' => 'video/mp4',
        'webm' => 'video/webm',
        'flv' => 'video/x-flv',
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
    );
    
    $options = get_option('player_video');
    if($options['script'] != '') {
        $file = plugin_dir_path(__FILE__).'players/'.$options['script'].'/'.$options['script'].'.php';
        if(is_file($file)) require_once($file);
    }
    $options = get_option('player_audio');
    if($options['script'] != '') {
        $file = plugin_dir_path(__FILE__).'players/'.$options['script'].'/'.$options['script'].'.php';
        if(is_file($file)) require_once($file);
    }
}

add_filter('upload_mimes', 'player_add_mime');
function player_add_mime($mimes){
    $mimes['webm'] = 'video/webm';
    return $mimes;
}

$options = get_option('player_video');
if(isset($options['width']) && isset($options['height'])){ 
    if($options['controls'] == 'over')
        add_image_size('video-large', $options['width'], $options['height'], true); // grand poster de la vidéo
    else 
        add_image_size('video-large', $options['width'], $options['height']-53, true);
}
    
// Ajouter l'extension webm à la liste des types vidéo
add_filter('ext2type', 'player_add_video_type');
function player_add_video_type($types){
    $types['video'][] = 'webm';
    return $types;
}

add_shortcode('video', 'player_video_shortcode');
function player_video_shortcode($atts) {
    static $instance = 0;
    $instance++;
    $options = get_option('player_video');
    
    extract(shortcode_atts(array(
        'id'        => '',
        'src'       => '',
        'poster'    => '',
        'width'     => $options['width'],
        'height'    => $options['height'],
        'skin'      => $options['skin'],
        'autoplay'  => false,
        'loop'      => false,
        'controls'  => $options['controls'],
        'order'     => 'ASC',
        'orderby'   => 'menu_order ID',
        'include'   => '',
        'title'     => '',
        'legend'    => '',
        'description' => '',
	), $atts));
	
	$videos = array();
	
    if($src != '') {
    
        // point to a source that is not linked to any attachment
        $videos[0] = array('poster' => $poster, 'title' => $title, 'legend' => $legend, 'description' => $description);
        if(preg_match('/flash/', $options['mode']))
            $videos[0]['flash']['src'] = $src;
        if(preg_match('/html5/', $options['mode']))
            $videos[0]['html5']['src'] = $src;
    
    } else {
    
        // a single attachment
        if($id != '' && player_is_video_attachment($id))
            $attachments = array($id => get_post($id));
            
        // a full playlist
        else if($include != ''){
            
            $include = preg_replace( '/[^0-9,]+/', '', $include );
	        $_attachments = get_posts( array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'video', 'order' => $order, 'orderby' => $orderby) );

	        $attachments = array();
	        foreach ( $_attachments as $key => $val ) {
		        $attachments[$val->ID] = $_attachments[$key];
	        }
            uasort($attachments , 'player_sort_attachments' );
        }
        
        if(!is_array($attachments)) return;
        
        foreach ( $attachments as $attachment ) {
            
            $metas = get_post_meta($attachment->ID, "1player", true);
            if($options['poster'] == 'attachment') {
                $image = wp_get_attachment_image_src($metas['poster'], 'video-large');
                $poster = $image[0];
            } else if($options['poster'] == 'post_thumbnail') {
                $image = wp_get_attachment_image_src(get_post_thumbnail_id($attachment->post_parent), 'video-large');
                $poster = $image[0];
            }
            
            $videos[]  = array('poster' => $poster, 'title' => addslashes($attachment->post_title), 'legend' => addslashes($attachment->post_excerpt), 'description' => addslashes($attachment->post_content));
            
            foreach(array('html5', 'flash') as $mode){
                
                // gestion flash vs. html5
                if(preg_match('/'.$mode.'/', $options['mode'])) {
                    unset($hd);
                    unset($src);
                    $src = player_find_source($attachment->ID, 'sd', $mode);
                    $hd = player_find_source($attachment->ID, 'hd', $mode);
                    if($src == $hd) {
                        unset($hd);
                    }
                    
                    $videos[sizeof($videos)-1][$mode]['src'] = $src;
                    if(isset($hd)) $videos[sizeof($videos)-1][$mode]['hd'] = $hd;
                }
                
            }
        }
    }
    
    $args = array(
        'videos'    => $videos, 
        'width'     => $width,
        'height'    => $height,
        'skin'      => $skin,
        'autoplay'  => $autoplay,
        'loop'      => $loop,
        'controls'  => $controls,
        'instance'  => "video$instance",
    );
	
	$action_done = false;
	if($options['script'] != '') {
        do_action($options['script'].'_video_render', $args);
        if(has_action($options['script'].'_video_render')) $action_done = true;
    }
    if(!$action_done) player_video_render($args);
}

// default player video rendering action
function player_video_render($args){
    global $mime_types;
    
    if(!isset($args['videos'][0]['html5'])) return;
    
    $attributes = '';
    if($args['controls'] != 'none') $attributes .= " controls";
    if($args['loop']) $attributes .= " loop";
    if($args['autoplay']) $attributes .= " autoplay";
    
    $poster = $args['videos'][0]['poster'];
    
    ?><video<?php echo $attributes ?> id="player<?php echo $args['instance'] ?>" poster="<?php echo $poster ?>" width="<?php echo $args['width'] ?>" height="<?php echo $args['height'] ?>">
        <?php foreach($args['videos'][0]['html5']['src'] as $video): ?>
            <source src="<?php echo $video['src'] ?>" <?php if($video['compat'] != 'none') echo 'type="'.$mime_types[$video['compat']].'"' ?>>
        <?php endforeach; ?>
    </video><?php
}

add_shortcode('audio', 'player_audio_shortcode');
function player_audio_shortcode($atts) {
    static $instance = 0;
    $instance++;
    $options = get_option('player_audio');
    
    extract(shortcode_atts(array(
        'id'        => '',
        'src'       => '',
        'width'     => $options['width'],
        'height'    => $options['height'],
        'skin'      => $options['skin'],
        'autoplay'  => false,
        'loop'      => false,
        'order'     => 'ASC',
        'orderby'   => 'menu_order ID',
        'include'   => '',
        'title'     => '',
        'legend'    => '',
        'description' => '',
	), $atts));
	
	$audios = array();
	
    if($src != '') {
    
        // point to a source that is not linked to any attachment
        $audio[0] = array('title' => $title, 'legend' => $legend, 'description' => $description);
        if(preg_match('/flash/', $options['mode']))
            $audios[0]['flash']['src'] = $src;
        if(preg_match('/html5/', $options['mode']))
            $audios[0]['html5']['src'] = $src;
    
    } else {
    
        // a single attachment
        if($id != '' && player_is_audio_attachment($id))
            $attachments = array($id => get_post($id));
        
        foreach ( $attachments as $attachment ) {
            
            $metas = get_post_meta($attachment->ID, "1player", true);
            
            $audios[]  = array('title' => addslashes($attachment->post_title), 'legend' => addslashes($attachment->post_excerpt), 'description' => addslashes($attachment->post_content));
            
            foreach(array('html5', 'flash') as $mode){
                
                // gestion flash vs. html5
                if(preg_match('/'.$mode.'/', $options['mode'])) {
                    unset($src);
                    $src = player_find_source($attachment->ID, 'sd', $mode);
                    if(!isset($src)) $src = player_find_source($attachment->ID, 'hd', $mode);
                    $audios[sizeof($audios)-1][$mode]['src'] = $src;
                }
                
            }
        }
    }
    
    $args = array(
        'audios'    => $audios, 
        'width'     => $width,
        'height'    => $height,
        'skin'      => $skin,
        'autoplay'  => $autoplay,
        'loop'      => $loop,
        'controls'  => $controls,
        'instance'  => "audio$instance",
    );
	
	$action_done = false;
	if($options['script'] != '') {
        do_action($options['script'].'_audio_render', $args);
        if(has_action($options['script'].'_audio_render')) $action_done = true;
    }
    if(!$action_done) player_audio_render($args);
}

// default player audio rendering action
function player_audio_render($args){
    $attributes = '';
    $attributes .= " controls";
    if($args['loop']) $attributes .= " loop";
    if($args['autoplay']) $attributes .= " autoplay";
    
    ?><audio<?php echo $attributes ?> id="player<?php echo $args['instance'] ?>" width="<?php echo $args['width'] ?>" height="<?php echo $args['height'] ?>">
        <?php foreach(array('flash', 'html5') as $mode): ?>
            <source src="<?php echo $args['audios'][0][$mode]['src'] ?>" type="audio/<?php echo array_pop(explode('.', $args['audios'][0][$mode]['src'])) ?>">
        <?php endforeach; ?>
    </audio><?php
}

function player_is_video_attachment($post_id){
    $post = get_post($post_id);
    return substr($post->post_mime_type, 0, 5) == 'video';
}

function player_is_audio_attachment($post_id){
    $post = get_post($post_id);
    return substr($post->post_mime_type, 0, 5) == 'audio';
}

function player_sort_attachments($a, $b){
    $parenta = get_post($a->post_parent);
    $parentb = get_post($b->post_parent);
    if($parenta->menu_order == $parentb->menu_order){
        if($parenta->menu_order < $parentb->menu_order) return -1;
        else return 1;
    } else if($parenta->menu_order < $parentb->menu_order) {
        return -1;
    } else {
        return 1;
    }
}

function player_find_source($attachment_id, $quality='sd', $compatibility='html5'){

    if($quality != 'hd') $quality = 'sd';
    if($compatibility != 'flash') $compatibility = 'html5';
    
    $metas = get_post_meta($attachment_id, "1player", true);
    $src = array();
    
    if(isset($metas['src']) && isset($metas['src'][$quality])){
        if(is_string($metas['src'][$quality]) && $metas['src'][$quality] != "") $src[] = array('src' => $metas['src'][$quality]);
        else {
            foreach($metas['src'][$quality] as $name => $format){
                if(is_string($format) && $format != "") $src[] = array('src' => $format, 'compat' => $name);
                else if(isset($format[$compatibility]) && $format[$compatibility] != "") {
                    $src[] = array('src' => $format[$compatibility], 'compat' => $name);
                }
            }
        }
    }
    
    // si aucune source SD n'est trouvée, chercher les sources HD
    if(sizeof($src) == 0 && $quality=='sd'){
        $quality='hd';
        if(isset($metas['src']) && isset($metas['src'][$quality])){
            if(is_string($metas['src'][$quality]) && $metas['src'][$quality] != "") $src[] = array('src' => $metas['src'][$quality]);
            else {
                foreach($metas['src'][$quality] as $name => $format){
                    if(is_string($format) && $format != "") $src[] = array('src' => $format, 'compat' => $name);
                    else if(isset($format[$compatibility]) && $format[$compatibility] != "") {
                        $src[] = array('src' => $format[$compatibility], 'compat' => $name);
                    }
                }
            }
        }
    }

    // if no source is found in metas
    if(sizeof($src) == 0){
        $src[] = array('src' => wp_get_attachment_url($attachment_id), 'compat' => 'none');
    }
    
    return $src;
}

/* ******************* BACKEND ******************* */

register_activation_hook(__FILE__, 'player_install'); 
function player_install() {
    // ajoute les options par défaut si elles n'existent pas encore
	add_option('player_video', array(
	    'skin' => '',
	    'height' => 300,
	    'width' => 400,
	    'versions' => array(
	        array('hd', 'webm', 'html5'),
	        array('sd', 'webm', 'html5'),
	        array('hd', 'h264'),
	        array('sd', 'h264')
	    ),
	    'controls' => 'over',
	    'poster' => 'attachment',
	    'mode' => 'html5',
	    'script' => ''
	));
	
	add_option('player_audio', array(
	    'skin' => '',
	    'versions' => array(
	        array('sd', 'ogg', 'html5'),
	        array('sd', 'mp3')
	    ),
	    'mode' => 'html5',
	    'script' => ''
	));
}

if ( is_admin() ){
    add_action('admin_print_styles-options-media.php', 'player_print_options_assets');
    function player_print_options_assets(){
        global $labels;
        wp_enqueue_script('1player', plugins_url('js/1player.js', __FILE__), array('jquery'));
        wp_localize_script('1player', 'labels', $labels);
        wp_enqueue_style('1player', plugins_url('css/1player.css', __FILE__));
    }
    
    add_action('admin_print_styles', 'player_print_dd_assets');
    function player_print_dd_assets(){
        wp_register_script("dropdown", plugins_url('msDropDown/jquery.dd.js', __FILE__ ), array('jquery'), '2.37.5');
        wp_register_style('dropdown', plugins_url('msDropDown/dd.css', __FILE__));
    }
    
    // page d'options
    add_action('admin_init', 'player_register_settings');
    function player_register_settings() {
        
        add_settings_section('player_video_main', __('Video player','1player'), 'player_video_settings_section', 'media');
        add_settings_section('player_audio_main', __('Audio player','1player'), 'player_audio_settings_section', 'media');
        
        function player_video_settings_section(){}
        function player_audio_settings_section(){}
        
        add_settings_field('player_video_script', '<label for="player_video_script">'.__('Player script','1player').'</label>', 'player_settings_video_script', 'media', 'player_video_main');
        function player_settings_video_script(){
            $options = get_option('player_video'); ?>
                <select name="player_video[script]" id="player_video_script">
                    <option value=""><?php _e('None', '1player') ?></option>
                    <?php foreach (scandir(plugin_dir_path(__FILE__).'/players') as $dir) :
                        $fulldir = plugin_dir_path(__FILE__).'/players/'.$dir;
                        if($dir != "." && $dir != ".." && is_dir($fulldir)) : ?>
                            <option value="<?php echo $dir ?>" <?php if($options['script'] == $dir) echo "selected" ?>><?php echo $dir ?></option>
                        <?php endif; 
                    endforeach; ?>
                </select>
            <?php
        }
        
        add_settings_field('player_video_size', __('Player size','1player'), 'player_settings_video_size', 'media', 'player_video_main');
        function player_settings_video_size(){
            $options = get_option('player_video'); ?>
                <label for="player_video_size_w"><?php _e('Width') ?></label>
                <input id="player_video_size_w" class="small-text" type="text" name="player_video[width]" value="<?php echo $options['width'] ?>" />
                <label for="player_video_size_h"><?php _e('Height') ?></label>
                <input id="player_video_size_h" class="small-text" type="text" name="player_video[height]" value="<?php echo $options['height'] ?>" />
            <?php
        }
        
        add_settings_field('player_video_skin', '<label for="player_skin">'.__('Skin','1player').'</label>', 'player_settings_video_skin', 'media', 'player_video_main');
        function player_settings_video_skin(){
            $options = get_option('player_video'); 
            $skins = array("none" => __('Default skin', '1player'));
            if($options['script'] != '') $skins = apply_filters($options['script'].'_video_skins_list', $skins);
            ?><select name="player_video[skin]">
                <?php foreach($skins as $name => $skin) : ?>
                <option value="<?php echo $name ?>" <?php if($options['skin'] == $name) echo "selected" ?>><?php echo $skin ?></option>
                <?php endforeach; ?>
            </select>
            <?php 
                $description = __('The selected player script does not provide skins', '1player');
                if($options['script'] != '') $description = apply_filters($options['script'].'_video_skins_description', $description);
            ?>
            <span class="description"><?php echo $description ?></span>
            <?php
        }
        
        add_settings_field('player_video_controls', '<label for="player_controls">'.__('Controls','1player').'</label>', 'player_settings_video_controls', 'media', 'player_video_main');
        function player_settings_video_controls(){
            $options = get_option('player_video'); 
                $positions = array("over" => __('Over', '1player'), "none" => __('None', '1player'));
                if($options['script'] != '') $positions = apply_filters($options['script'].'_controls_positions_list', $positions);
                foreach($positions as $name => $position) : ?>
                <label> <input name="player_video[controls]" type="radio" value="<?php echo $name ?>" <?php if($options['controls'] == $name) echo 'checked' ?> /> <?php echo $position ?></label>
                <?php endforeach; ?>
                <span class="description"><?php _e('Controls position', '1player') ?></span>
            <?php
        }
        
        add_settings_field('player_video_poster', '<label for="player_poster">'.__('Poster image','1player').'</label>', 'player_settings_video_poster', 'media', 'player_video_main');
        function player_settings_video_poster(){
            $options = get_option('player_video'); ?>
                <label> <input name="player_video[poster]" type="radio" value="attachment" <?php if($options['poster'] == "attachment") echo 'checked="checked"' ?> /> <?php _e('Video media attached image', '1player') ?></label>
                <label> <input name="player_video[poster]" type="radio" value="post_thumbnail" <?php if($options['poster'] == "post_thumbnail") echo 'checked="checked"' ?> /> <?php _e('Post featured image', '1player') ?></label>
            <?php
        }
        
        add_settings_field('player_video_versions', '<label for="player_versions">'.__('Video versions','1player').'</label>', 'player_settings_video_versions', 'media', 'player_video_main');
        function player_settings_video_versions(){
            global $labels;
            $options = get_option('player_video'); 
            if(!isset($options['versions']) || !is_array($options['versions'])) $options['versions'] = array();
            ?>
            <fieldset class="column" id="versions">
                <header><?php _e('Available versions', '1player') ?></header>
                <script type="text/template" class="template">
                    <div>
                        ${labels}
                        {{each(i, version) versions }}
                            <input type="hidden" name="player_video[versions][${index}][${i}]" value="${version}">
                        {{/each}}
                        <button class="button suppr_version_button"><?php _e('Delete') ?></button>
                    </div>
                </script>
                <?php foreach($options['versions'] as $i => $version) : ?>
                    <div>
                        <?php echo $labels[$version[0]].' - '.$labels[$version[1]]; if (sizeof($version)>2) echo ' - '.$labels[$version[2]] ?>
                        <input type="hidden" name="player_video[versions][<?php echo $i ?>][0]" value="<?php echo $version[0] ?>" />
                        <input type="hidden" name="player_video[versions][<?php echo $i ?>][1]" value="<?php echo $version[1] ?>" />
                        <?php if(sizeof($version)>2) :?>
                        <input type="hidden" name="player_video[versions][<?php echo $i ?>][2]" value="<?php echo $version[2] ?>" />
                        <?php endif; ?>
                        <button class="button suppr_version_button"><?php _e('Delete') ?></button>
                    </div>
                <?php endforeach; ?>
            </fieldset>
            <fieldset class="column">
                <header><?php _e('Add new version', '1player') ?></header>
                <div>
                    <?php _e('Playing mode', '1player') ?>: <input type="checkbox" checked="checked" name="type" value="html5"/><label><?php echo $labels['html5'] ?></label>
                                     <input type="checkbox" checked="checked" name="type" value="flash"/><label><?php echo $labels['flash'] ?></label>
                </div>
                <div>
                    <?php _e('Quality', '1player') ?>: <input type="radio" name="qualite" value="hd" checked="checked"/><label><?php echo $labels['hd'] ?></label> 
                             <input type="radio" name="qualite" value="sd"/><label><?php echo $labels['sd'] ?></label>
                </div>
                <div>
                    <?php _e('Format', '1player') ?>: <select name="format">
                                <option value="webm"><?php echo $labels['webm'] ?></option>
                                <option value="h264"><?php echo $labels['h264'] ?></option>
                                <option value="flv"><?php echo $labels['flv'] ?></option>
                            </select>
                </div>
                <button class="button add_version_button"><?php _e('Add new version', '1player') ?></button>
            </fieldset>
            <?php
        }
        
        add_settings_field('player_video_mode', __('Playing mode','1player'), 'player_settings_video_mode', 'media', 'player_video_main');
        function player_settings_video_mode(){
            $options = get_option('player_video'); 
            $modes = array("html5" => __('HTML5 only', '1player'));
            if($options['script'] != '') $modes = apply_filters($options['script'].'_video_modes_list', $modes);
            foreach($modes as $name => $mode) : ?>
                <label> <input name="player_video[mode]" type="radio" value="<?php echo $name ?>" <?php if($options['mode'] == $name) echo 'checked' ?> /> <?php echo $mode ?></label>
            <?php endforeach;
        }
        
        register_setting( 'media', 'player_video');
        
        add_settings_field('player_audio_script', __('Player script','1player'), 'player_settings_audio_script', 'media', 'player_audio_main');
        function player_settings_audio_script(){
            $options = get_option('player_audio'); ?>
                <select name="player_audio[script]" id="player_audio_script">
                    <option value=""><?php _e('None', '1player') ?></option>
                    <?php foreach (scandir(plugin_dir_path(__FILE__).'/players') as $dir) :
                        $fulldir = plugin_dir_path(__FILE__).'/players/'.$dir;
                        if($dir != "." && $dir != ".." && is_dir($fulldir)) : ?>
                            <option value="<?php echo $dir ?>" <?php if($options['script'] == $dir) echo "selected" ?>><?php echo $dir ?></option>
                        <?php endif; 
                    endforeach; ?>
                </select>
            <?php
        }
        
        add_settings_field('player_audio_size', __('Player size','1player'), 'player_settings_audio_size', 'media', 'player_audio_main');
        function player_settings_audio_size(){
            $options = get_option('player_audio'); ?>
                <label for="player_audio_size_w"><?php _e('Width') ?></label>
                <input id="player_audio_size_w" class="small-text" type="text" name="player_audio[width]" value="<?php echo $options['width'] ?>" />
                <label for="player_audio_size_h"><?php _e('Height') ?></label>
                <input id="player_audio_size_h" class="small-text" type="text" name="player_audio[height]" value="<?php echo $options['height'] ?>" />
            <?php
        }
        
        add_settings_field('player_audio_skin', '<label for="player_audio_skin">'.__('Skin','1player').'</label>', 'player_settings_audio_skin', 'media', 'player_audio_main');
        function player_settings_audio_skin(){
            $options = get_option('player_audio'); 
            $skins = array("none" => __('Default skin', '1player'));
            $skins = apply_filters('1player_skins_list', $skins);
            ?><select name="player_audio[skin]">
                <?php foreach($skins as $name => $skin) : ?>
                <option value="<?php echo $name ?>" <?php if($options['skin'] == $name) echo "selected" ?>><?php echo $skin ?></option>
                <?php endforeach; ?>
            </select>
            <span class="description"><?php echo apply_filters('1player_skins_description', __('The selected player script does not provide skins', '1player')) ?></span>
            <?php
        }
        
        add_settings_field('player_audio_versions', __('Audio versions','1player'), 'player_settings_audio_versions', 'media', 'player_audio_main');
        function player_settings_audio_versions(){
            global $labels;
            $options = get_option('player_audio'); 
            if(!isset($options['versions']) || !is_array($options['versions'])) $options['versions'] = array();
            ?>
            <fieldset class="column" id="versions">
                <header><?php _e('Available versions', '1player') ?></header>
                <script type="text/template" class="template">
                    <div>
                        ${labels}
                        {{each(i, version) versions }}
                            <input type="hidden" name="player_audio[versions][${index}][${i}]" value="${version}">
                        {{/each}}
                        <button class="button suppr_version_button"><?php _e('Delete') ?></button>
                    </div>
                </script>
                <?php foreach($options['versions'] as $i => $version) : ?>
                    <div>
                        <?php echo $labels[$version[0]].' - '.$labels[$version[1]]; if (sizeof($version)>2) echo ' - '.$labels[$version[2]] ?>
                        <input type="hidden" name="player_audio[versions][<?php echo $i ?>][0]" value="<?php echo $version[0] ?>" />
                        <input type="hidden" name="player_audio[versions][<?php echo $i ?>][1]" value="<?php echo $version[1] ?>" />
                        <?php if(sizeof($version)>2) :?>
                        <input type="hidden" name="player_audio[versions][<?php echo $i ?>][2]" value="<?php echo $version[2] ?>" />
                        <?php endif; ?>
                        <button class="button suppr_version_button"><?php _e('Delete') ?></button>
                    </div>
                <?php endforeach; ?>
            </fieldset>
            <fieldset class="column">
                <header><?php _e('Add new version', '1player') ?></header>
                <div>
                    <?php _e('Playing mode', '1player') ?>: <input type="checkbox" checked="checked" name="type" value="html5"/><label><?php echo $labels['html5'] ?></label>
                                     <input type="checkbox" checked="checked" name="type" value="flash"/><label><?php echo $labels['flash'] ?></label>
                </div>
                <div>
                    <?php _e('Quality', '1player') ?>: <input type="radio" name="qualite" value="hd" checked="checked"/><label><?php echo $labels['hd'] ?></label> 
                             <input type="radio" name="qualite" value="sd"/><label><?php echo $labels['sd'] ?></label>
                </div>
                <div>
                    <?php _e('Format', '1player') ?>: <select name="format">
                                <option value="ogg"><?php echo $labels['ogg'] ?></option>
                                <option value="mp3"><?php echo $labels['mp3'] ?></option>
                            </select>
                </div>
                <button class="button add_version_button"><?php _e('Add new version', '1player') ?></button>
            </fieldset>
            <?php
        }
        
        add_settings_field('player_audio_mode', __('Playing mode','1player'), 'player_settings_audio_mode', 'media', 'player_audio_main');
        function player_settings_audio_mode(){
            $options = get_option('player_audio');
            $modes = array("html5" => __('HTML5 only', '1player'));
            $modes = apply_filters('1player_modes_list', $modes);
            foreach($modes as $name => $mode) : ?>
                <label> <input name="player_audio[mode]" type="radio" value="<?php echo $name ?>" <?php if($options['mode'] == $name) echo 'checked' ?> /> <?php echo $mode ?></label>
            <?php endforeach;
        }
        
        register_setting( 'media', 'player_audio');
    }
    
    
    // gestion des champs supplémentaires pour les vidéos de la bibliothèque : édition
    add_filter('attachment_fields_to_edit', 'player_edit_fields', 10, 2);
    function player_edit_fields($form_fields, $post){
        global $labels;
    
        // seulement pour les vidéos et les audios
        if ( substr($post->post_mime_type, 0, 5) == 'video' || substr($post->post_mime_type, 0, 5) == 'audio' ) {
            $metas = get_post_meta($post->ID, "1player", true);
            $player = get_option("player_".substr($post->post_mime_type, 0, 5), true);
		
		    if(isset($player["poster"]) && $player["poster"] == "attachment") {
		        // sélectionner la miniature depuis la bibliothèque
		        $form_fields["poster"] = array(
                    "label" => __("Thumbnail"),
                    "input" => "html",
                    "html" => generateImageSelectorHTML($post->ID, get_posts(array(
                        "post_type" => "attachment",
                        "numberposts" => -1,
                        "post_status" => null,
                        "post_mime_type" => "image",
                        "post_parent" => null
                    ))),
                );
            }
            
            if(sizeof($player["versions"]) > 0) {
		        foreach($player["versions"] as $version){
		        
		            $src = (isset($metas['src']) && isset($metas['src'][$version[0]][$version[1]])) ? $metas['src'][$version[0]][$version[1]] : '';
		            if(isset($version[2]) && isset($src[$version[2]])) $src = $src[$version[2]];
		            
		            $name = '[src]['.$version[0].']['.$version[1].']'.(sizeof($version)>2 ? '['.$version[2].']' : '');
		            
		            $form_fields[$version[0].$version[1].(sizeof($version)>2 ? $version[2] : '')] = array(
		                'label' => $labels[$version[0]].' - '.$labels[$version[1]].(sizeof($version)>2 ? ' - '.$labels[$version[2]] : ''),
		                'input' => 'html',
		                'html' => '<input type="text" value="'.$src.'" name="attachments['.$post->ID.']'.$name.'" id="attachments['.$post->ID.']'.$name.'" class="text">',
		            );
		        }
		    }
            
            unset($form_fields["url"]);
        }

        return $form_fields;
    }
    
    // gestion des champs supplémentaires pour les vidéos de la bibliothèque : sauvegarde
    add_filter('attachment_fields_to_save', 'player_save_fields', 10, 2);
    function player_save_fields($post, $attachment){
        
        // seulement pour les vidéos
        if ( substr($post["post_mime_type"], 0, 5) == 'video' ) {
            $metas = array(
                'poster' => isset($attachment['poster']) ? intval($attachment['poster']) : '',
                'src' => isset($attachment['src']) ? $attachment['src'] : '',
            );
        } else if ( substr($post["post_mime_type"], 0, 5) == 'audio') {
            $metas = array(
                'src' => isset($attachment['src']) ? $attachment['src'] : '',
            );
        }
        
        // sauvegarde des metas
        if(isset($metas)) update_post_meta($post['ID'], "1player", $metas);
        
        return $post;
    }
    
    // gestion des champs supplémentaires pour les vidéos depuis le web
    add_filter( 'type_url_form_media', 'player_insert_video_from_url');
    function player_insert_video_from_url(){
    
        // code copié depuis wp_media_insert_url_form dans /wp-admin/includes/media.php et modifié
		if ( !apply_filters( 'disable_captions', '' ) ) {
				$caption = '
				<tr class="image-only">
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="caption">' . __('Image Caption') . '</label></span>
					</th>
					<td class="field"><input id="caption" name="caption" value="" type="text" /></td>
				</tr>';
		} else {
			$caption = '';
		}
        
        $default_align = get_option('image_default_align');
	    if ( empty($default_align) )
		    $default_align = 'none';

		$view = $table_class = 'not-image';

	    $return = '
	    <p class="media-types"><label><input type="radio" name="media_type" value="image" id="image-only"' . checked( 'image-only', $view, false ) . ' /> ' . __( 'Image' ) . '</label> &nbsp; &nbsp; <label><input type="radio" name="media_type" value="generic" id="not-image"' . checked( 'not-image', $view, false ) . ' /> ' . __( 'Audio, Video, or Other File' ) . '</label></p>
	    <table class="describe ' . $table_class . '"><tbody>
		    <tr>
			    <th valign="top" scope="row" class="label" style="width:130px;">
				    <span class="alignleft"><label for="src">' . __('URL') . '</label></span>
				    <span class="alignright"><abbr id="status_img" title="required" class="required">*</abbr></span>
			    </th>
			    <td class="field"><input id="src" name="src" value="" type="text" aria-required="true" onblur="addExtImage.getImageData()" /></td>
		    </tr>
		    
		    <tr>
			    <th valign="top" scope="row" class="label">
				    <span class="alignleft"><label for="title">' . __('Title') . '</label></span>
				    <span class="alignright"><abbr title="required" class="required">*</abbr></span>
			    </th>
			    <td class="field"><input id="title" name="title" value="" type="text" aria-required="true" /></td>
		    </tr>

		    <tr class="image-only">
			    <th valign="top" scope="row" class="label">
				    <span class="alignleft"><label for="alt">' . __('Alternate Text') . '</label></span>
			    </th>
			    <td class="field"><input id="alt" name="alt" value="" type="text" aria-required="true" />
			    <p class="help">' . __('Alt text for the image, e.g. &#8220;The Mona Lisa&#8221;') . '</p></td>
		    </tr>
		    ' . $caption . '
		    <tr class="align image-only">
			    <th valign="top" scope="row" class="label"><p><label for="align">' . __('Alignment') . '</label></p></th>
			    <td class="field">
				    <input name="align" id="align-none" value="none" onclick="addExtImage.align=\'align\'+this.value" type="radio"' . ($default_align == 'none' ? ' checked="checked"' : '').' />
				    <label for="align-none" class="align image-align-none-label">' . __('None') . '</label>
				    <input name="align" id="align-left" value="left" onclick="addExtImage.align=\'align\'+this.value" type="radio"' . ($default_align == 'left' ? ' checked="checked"' : '').' />
				    <label for="align-left" class="align image-align-left-label">' . __('Left') . '</label>
				    <input name="align" id="align-center" value="center" onclick="addExtImage.align=\'align\'+this.value" type="radio"' . ($default_align == 'center' ? ' checked="checked"' : '').' />
				    <label for="align-center" class="align image-align-center-label">' . __('Center') . '</label>
				    <input name="align" id="align-right" value="right" onclick="addExtImage.align=\'align\'+this.value" type="radio"' . ($default_align == 'right' ? ' checked="checked"' : '').' />
				    <label for="align-right" class="align image-align-right-label">' . __('Right') . '</label>
			    </td>
		    </tr>

		    <tr class="image-only">
			    <th valign="top" scope="row" class="label">
				    <span class="alignleft"><label for="url">' . __('Link Image To:') . '</label></span>
			    </th>
			    <td class="field"><input id="url" name="url" value="" type="text" /><br />

			    <button type="button" class="button" value="" onclick="document.forms[0].url.value=null">' . __('None') . '</button>
			    <button type="button" class="button" value="" onclick="document.forms[0].url.value=document.forms[0].src.value">' . __('Link to image') . '</button>
			    <p class="help">' . __('Enter a link URL or click above for presets.') . '</p></td>
		    </tr>
		    
		    <tr class="image-only">
			    <td></td>
			    <td>
				    <input type="button" class="button" id="go_button" style="color:#bbb;" onclick="addExtImage.insert()" value="' . esc_attr__('Insert into Post') . '" />
			    </td>
		    </tr>
		    <tr class="not-image">
			    <td></td>
			    <td>
				    ' . get_submit_button( __( 'Insert into Post' ), 'button', 'insertonlybutton', false ) . '
				    '/* BEGIN modif Sauvegarder sans insérer */ .'
				    ' . get_submit_button( __( 'Save' ), 'button', "savebutton", false ) . '
				    '/* END modif Sauvegarder sans insérer */ .'
			    </td>
		    </tr>
            
	    </tbody></table>
    ';
    return $return;
    }
    
    // Ajout d'une vidéo depuis le web : remplacer le lien par un shortcode
    add_filter('file_send_to_editor_url', 'player_insert_new_video', 10, 3);
    add_filter('video_send_to_editor_url', 'player_insert_new_video', 10, 3);
    function player_insert_new_video($html, $href, $title){
        global $mime_types;
        
        $post_id = isset($_REQUEST['post_id']) ? intval($_REQUEST['post_id']) : 0;
        
        // ne prendre que les vidéos
        if ( ( $ext = preg_replace( '/^.+?\.([^.]+)$/', '$1', $_REQUEST['src'] ) ) && ( wp_ext2type( $ext ) != 'video' ) ) return;
        
        // commencer par enregistrer la vidéo dans la bibliothèque
        $filetype = wp_check_filetype($_REQUEST['src']);
        $mime = $filetype['type'] ? $filetype['type'] : 'video/mp4';
        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => $mime,
            'post_parent' => $post_id,
            'post_title' => $_REQUEST['title'],
            'guid' => $_REQUEST['src'],
        ), false, $post_id);
        
        // enregistrer les metas
        $metas = array();
        if(isset($_REQUEST['poster'])) {
            $metas['poster'] = intval($_REQUEST['poster']);
        }
        
        if(sizeof($_REQUEST['src']) > 1) {
            $metas['src'] = $_REQUEST['src'];
        }
        
        if(sizeof($metas) > 0) {
            // sauvegarde des metas
            update_post_meta($attachment_id, "1player", $metas);
        }

        // ensuite, insérer le shortcode dans l'article
        return "[video id=\"$attachment_id\"]";
    }
    
    // Insertion d'une vidéo depuis la bibliothèque : remplacer le lien par un shortcode
    add_filter('media_send_to_editor', 'player_insert_video_shortcode', 15, 3);
    function player_insert_video_shortcode($html, $attachment_id, $attachment){
        $post =& get_post($attachment_id);
        
        // seulement dans le cas où on a une vidéo
	    if ( substr($post->post_mime_type, 0, 5) == 'video' ) {
		    $html = "[video id=\"$attachment_id\"]";
        }

        return $html;
    }
    
    add_action('media_upload_image', 'player_upload_new_video');
    function player_upload_new_video(){
        global $mime_types;
        
        if (isset($_POST['savebutton'])) {
            $post_id = isset($_REQUEST['post_id']) ? intval($_REQUEST['post_id']) : 0;
            
            // enregistrer le fichier dans la bibliothèque
            $filetype = wp_check_filetype($_REQUEST['src']);
            $mime = $filetype['type'] ? $filetype['type'] : 'video/mp4';
            $attachment_id = wp_insert_attachment(array(
                'post_mime_type' => $mime,
                'post_parent' => $post_id,
                'post_title' => $_REQUEST['title'],
                'guid' => $_REQUEST['src'],
            ), false, $post_id);
            
            // enregistrer les metas
            $metas = array();
            if(isset($_REQUEST['poster'])) {
                $metas['poster'] = intval($_REQUEST['poster']);
            }
            
            if(substr($mime, 0, 5) == "video" || substr($mime, 0, 5) == "audio") {
                $player = get_option("player_".substr($mime, 0, 5), true);
                if(sizeof($player["versions"]) > 0) {
		            foreach($player["versions"] as $version){
		                if(!isset($default)) $default = $mime_types[$version[1]];
		                if($mime_types[$version[1]] == $mime) {
		                    if(sizeof($version)==3) $metas['src'][$version[0]][$version[1]][$version[2]] = $_REQUEST['src'];
		                    else $metas['src'][$version[0]][$version[1]] = $_REQUEST['src'];
		                    break;
		                }
		            }
		        }
		    }
            
            if(sizeof($metas) > 0) {
                // sauvegarde des metas
                update_post_meta($attachment_id, "1player", $metas);
            }
            
            $errors['upload_notice'] = __('Saved.');
		    return media_upload_gallery();
        }
    }
    
    
    // utilitaire pour choisir une miniature
    /**
     * Generates the HTML for rendering the thumbnail image selector.
     * @param int $id The id of the current attachment.
     * @return string The HTML to render the image selector.
     */
    function generateImageSelectorHTML($id, $attachments) {
      wp_enqueue_script("dropdown");
      wp_enqueue_style("dropdown");
      $output = "";
      $sel = false;
      if ($attachments) {
        $name = $id==0 ? "poster" : "attachments[$id][poster]";
        $output .= "<script language='javascript'>jQuery(document).ready(function(e) {jQuery(\"#imageselector$id\").msDropDown({visibleRows:3, rowHeight:50});});</script>\n";
        $output .= "<select name='$name' id='imageselector$id' width='200' style='width:200px;'>\n";
        $output .= "<option value='-1'>".__("None")."</option>\n";
        
        $metas = get_post_meta($id, "1player", true);
        if (!isset($metas['poster'])) $metas['poster'] = -1;
        foreach($attachments as $post) {
          if (substr($post->post_mime_type, 0, 5) == "image") {
            if ($post->ID == $metas['poster']) {
              $selected = "selected='selected'";
              $sel = true;
            } else {
              $selected = "";
            }
            $output .= "<option value='" . $post->ID . "' title='" . array_shift(wp_get_attachment_image_src($post->ID, array(50, 75))) . "' " . $selected . ">" . $post->post_title . "</option>\n";
          }
        }
        if (!$sel && $metas['poster'] != -1) {
          $image_post = get_post($metas['poster']);
          $output .= "<option value='" . $image_post->ID . "' title='" . array_shift(wp_get_attachment_image_src($image_post->ID, array(50, 75))) . "' selected=selected >" . $image_post->post_title . "</option>\n";
        }
        $output .= "</select>\n";
      }
      return $output;
    }
    
    
}

?>
