<?php
/*
 * Plugin Name: 1Player
 * Plugin URI: http://www.1nterval.com
 * Description: Advanced HTML5 video player
 * Author: Fabien Quatravaux
 * Version: 1.2
*/

// include external players glue if any
foreach (scandir(plugin_dir_path(__FILE__).'/players') as $dir){
    $fulldir = plugin_dir_path(__FILE__).'/players/'.$dir;
    if($dir != "." && $dir != ".." && is_dir($fulldir)) {
        $file = $fulldir.'/'.$dir.'.php';
        if(is_file($file)) require_once($file);
    }
}

add_action('init', 'player_init');
function player_init() {
    load_plugin_textdomain( '1player', false, basename(dirname(__FILE__)) );
    
    // labelisation des versions
    global $labels;
    $labels = array(
        'hd' => __('High quality', '1player'),
        'sd' => __('Low quality', '1player'),
        'h264' => __('MP4 h264', '1player'),
        'webm' => __('WebM', '1player'),
        'flv' => __('FLV', '1player'),
        'flash' => __('Flash', '1player'),
        'html5' => __('HTML5', '1player')
    );
}

add_action('admin_enqueue_scripts', 'player_scripts');
function player_scripts(){
    global $labels;
    wp_enqueue_script('1player', plugins_url('js/1player.js', __FILE__), array('jquery'));
    wp_localize_script('1player', 'labels', $labels);
}

$options = get_option('player');
if(isset($options['width']) && isset($options['height'])) 
    add_image_size('video-large', $options['width'], $options['height'], true); // grand poster de la vidéo

add_shortcode('video', 'player_shortcode');
function player_shortcode($atts) {
    static $instance = 0;
    $instance++;
    $options = get_option('player');
    
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
    if(preg_match('/flash/', $options['mode'])) $videos['flash'] = array();
    if(preg_match('/html5/', $options['mode'])) $videos['html5'] = array();
	
    if($src != '') {
    
        // point to a source that is not linked to any attachment
        if(preg_match('/flash/', $options['mode']))
            $videos['flash'][] = array('src' => $src, 'poster' => $poster, 'title' => $title, 'legend' => $legend, 'description' => $description);
        if(preg_match('/html5/', $options['mode']))
            $videos['html5'][] = array('src' => $src, 'poster' => $poster, 'title' => $title, 'legend' => $legend, 'description' => $description);
    
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
        
        foreach(array('html5', 'flash') as $mode){
            foreach ( $attachments as $attachment ) {
                
                // gestion flash vs. html5
                if(preg_match('/'.$mode.'/', $options['mode'])) {
                    unset($hd);
                    $src = player_find_source($attachment->ID, 'sd', $mode);
                    if(!isset($src) || $src == "") $src = player_find_source($attachment->ID, 'hd', $mode);
                    else $hd = player_find_source($attachment->ID, 'hd', $mode);

                    $metas = get_post_meta($attachment->ID, "1player", true);
                    if($options['poster'] == 'attachment') {
                        $image = wp_get_attachment_image_src($metas['poster'], 'video-large');
                        $poster = $image[0];
                    } else if($options['poster'] == 'post_thumbnail') {
                        $image = wp_get_attachment_image_src(get_post_thumbnail_id($attachment->post_parent), 'video-large');
                        $poster = $image[0];
                    }
                    
                    $videos[$mode][] = array('src' => $src, 'poster' => $poster, 'title' => addslashes($attachment->post_title), 'legend' => addslashes($attachment->post_excerpt), 'description' => addslashes($attachment->post_content));
                    if(isset($hd)) $videos[$mode][sizeof($videos[$mode])-1]['hd'] = $hd;
                }
                
            }
        }
    }
	
    do_action('player_render', array(
        'videos'    => $videos, 
        'width'     => $width,
        'height'    => $height,
        'skin'      => $skin,
        'autoplay'  => $autoplay,
        'loop'      => $loop,
        'controls'  => $controls,
        'instance'  => $instance,
    ));
	
    if(!has_action('player_render')){
        $attributes = '';
        if($controls != 'none') $attributes .= " controls";
        if($loop) $attributes .= " loop";
        if($autoplay) $attributes .= " autoplay";
        
	    ?><video<?php echo $attributes ?> id="player<?php echo $instance ?>" src="<?php echo $src ?>" poster="<?php echo $poster ?>" width="<?php echo $width ?>" height="<?php echo $height ?>"></video><?php
	}
}

function player_is_video_attachment($post_id){
    $post = get_post($post_id);
    return substr($post->post_mime_type, 0, 5) == 'video';
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
    
    if(isset($metas['src']) && isset($metas['src'][$quality])){
        if(is_string($metas['src'][$quality])) $src = $metas['src'][$quality];
        else {
            foreach($metas['src'][$quality] as $format){
                if(is_string($format)) $src = $format;
                else if(isset($format[$compatibility])) {
                    $src = $format[$compatibility];
                    break;
                }
            }
        }
    }
    
    // may be undefined if no source is found
    return $src;
}

add_action('wp_print_scripts', 'player_add_scripts');
function player_add_scripts(){
    $options = get_option('player');
    if(is_admin() && $options['poster'] == 'attachment') {
        wp_enqueue_script("dropdown", plugins_url('msDropDown/jquery.dd.js', __FILE__ ), array('jquery'), '2.37.5');
    }
}

add_action('admin_print_styles', 'player_add_style');
function player_add_style(){
    $options = get_option('player');
    if($options['poster'] == 'attachment')
        wp_enqueue_style('dropdown', plugins_url('msDropDown/dd.css', __FILE__));
}


// galerie vidéo
add_filter('post_gallery', 'player_video_gallery', 10, 2);
function player_video_gallery($output, $attr){
    global $post;
    static $instance = 0;
	$instance++;
	
	$options = get_option('player');
        
    // We're trusting author input, so let's at least make sure it looks like a valid orderby statement
    if ( isset( $attr['orderby'] ) ) {
	    $attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
	    if ( !$attr['orderby'] )
		    unset( $attr['orderby'] );
    }

    extract(shortcode_atts(array(
	    'order'      => 'ASC',
	    'orderby'    => 'menu_order ID',
	    'id'         => $post->ID,
	    'include'    => '',
	    'exclude'    => '',
	    'width'      => $options['width'],
	    'height'     => $options['height']
    ), $attr));

    if ( !empty($include) ) {
	    $include = preg_replace( '/[^0-9,]+/', '', $include );
	    $_attachments = get_posts( array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'video', 'order' => $order, 'orderby' => $orderby) );

	    $attachments = array();
	    foreach ( $_attachments as $key => $val ) {
		    $attachments[$val->ID] = $_attachments[$key];
	    }
	    
	    if(!function_exists('player_sort_attachments')) {
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
        }
        uasort($attachments , 'player_sort_attachments' );  
    } elseif ( !empty($exclude) ) {
	    $exclude = preg_replace( '/[^0-9,]+/', '', $exclude );
	    $attachments = get_children( array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'video', 'order' => $order, 'orderby' => $orderby) );
    } else {
	    $attachments = get_children( array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'video', 'order' => $order, 'orderby' => $orderby) );
    }

    if ( empty($attachments) )
	    return '';
	    
	// tester si la gallerie contient des vidéos
	if (!function_exists("is_video_attachment")) {
	    function is_video_attachment($a){
            return substr($a->post_mime_type, 0, 5) == 'video';
	    }
	}
	$videos = array_filter($attachments, "is_video_attachment");
	
	if ( empty($videos) )
	    return '';

    $first = true;
    foreach ( $attachments as $attachment ) {
        if($first) {
            $playlistFlash = "[";
            $playlistHTML5 = "[";
            $first = false;
        } else {
            $playlistFlash .= ',';
            $playlistHTML5 .= ',';
        }
        
        $metas = get_post_meta($attachment->ID, "1player", true);
        
        if($options['poster'] == 'attachment') {
            $image = wp_get_attachment_image_src($metas['poster'], 'video-large');
            $poster = $image[0];
        } else if($options['poster'] == 'post_thumbnail') {
            $image = wp_get_attachment_image_src(get_post_thumbnail_id($attachment->post_parent), 'video-large');
            $poster = $image[0];
        }
        
        // recherche de la source flash
        if(preg_match('/flash/', $options['mode'])) {
            unset($src);
            if(isset($metas['src']) && isset($metas['src']['sd'])){
                if(is_string($metas['src']['sd'])) $src = $metas['src']['sd'];
                else {
                    foreach($metas['src']['sd'] as $format){
                        if(is_string($format)) $src = $format;
                        else if(isset($format['flash'])) {
                            $src = $format['flash'];
                            break;
                        }
                    }
                }
            }
            
            unset($hd);
            if(isset($metas['src']) && isset($metas['src']['hd'])){
                if(is_string($metas['src']['hd'])) $hd = $metas['src']['hd'];
                else {
                    foreach($metas['src']['hd'] as $format){
                        if(is_string($format)) $hd = $format;
                        else if(isset($format['flash'])) {
                            $hd = $format['flash'];
                            break;
                        }
                    }
                }
            }
            
            if(!isset($src) && isset($hd)) {
                $src = $hd;
                unset($hd);
            }
            if(!isset($src)) $src = $attachment->guid;
            $playlistFlash .= "{";
            $playlistFlash .= "'file':'".$src."',";
            $playlistFlash .= "'image':'".$poster."',";
            $playlistFlash .= isset($hd) ? "'hd.file':'".$hd."'," : "";
            $playlistFlash .= "'title':'".addslashes($attachment->post_title)."',";
            $playlistFlash .= "'legend':'".addslashes($attachment->post_excerpt)."',";
            $playlistFlash .= "'description':'".addslashes($attachment->post_content)."'";
            $playlistFlash .= "}";
        }
        
        // recherche de la source html5
        if(preg_match('/html5/', $options['mode'])) {
            unset($src);
            if(isset($metas['src']) && isset($metas['src']['sd'])){
                if(is_string($metas['src']['sd'])) $src = $metas['src']['sd'];
                else {
                    foreach($metas['src']['sd'] as $format){
                        if(is_string($format)) $src = $format;
                        else if(isset($format['html5'])) {
                            $src = $format['html5'];
                            break;
                        }
                    }
                }
            }
            
            unset($hd);
            if(isset($metas['src']) && isset($metas['src']['hd'])){
                if(is_string($metas['src']['hd'])) $hd = $metas['src']['hd'];
                else {
                    foreach($metas['src']['hd'] as $format){
                        if(is_string($format)) $hd = $format;
                        else if(isset($format['html5'])) {
                            $hd = $format['html5'];
                            break;
                        }
                    }
                }
            }
            
            if(!isset($src) && isset($hd)) {
                $src = $hd;
                unset($hd);
            }
            if(!isset($src)) $src = $attachment->guid;
            $playlistHTML5 .= "{";
            $playlistHTML5 .= "'file':'".$src."',";
            $playlistHTML5 .= "'image':'".$poster."',";
            $playlistHTML5 .= isset($hd) ? "'hd.file':'".$hd."'," : "";
            $playlistHTML5 .= "'title':'".addslashes($attachment->post_title)."',";
            $playlistHTML5 .= "'legend':'".addslashes($attachment->post_excerpt)."',";
            $playlistHTML5 .= "'description':'".addslashes($attachment->post_content)."'";
            $playlistHTML5 .= "}";
        }
        
    }
    $playlistFlash .= "]";
    $playlistHTML5 .= "]";
    
    // gérer les modes et leur priorité
    $modes = "";
    $mode = $options['mode'];
    for($i=1; $i<=2; $i++) {
        if($modes != "") $modes.=",\n";
        if(substr($mode, 0, 5) == "html5")
            $modes .= "{ type: \"html5\", config: {playlist: $playlistHTML5} }";
        else if(substr($mode, 0, 5) == "flash")
            $modes .= "{ type: \"flash\", src: \"".plugins_url( 'jwplayer/player.swf' , __FILE__ )."\", config: {playlist: $playlistFlash} }";
        $mode = substr($mode, 5);
        if($mode == "") break;
    }
    
    wp_enqueue_script("jwplayer");
    
    return "
	<video id=\"player-$id\" width=\"$width\" height=\"$height\" class=\"1player\"></video>
	<script type=\"text/javascript\">
        jQuery(document).ready(function($) {
            jwplayer(\"player-$id\").setup({
                height: $height,
                width: $width,
                modes: [$modes],
                plugins: {
                    'hd-2':''
                },
                controlbar: {position: '$options[controlbar]'},
                skin: '$options[skin]',
                dock: false
            });
        });
    </script>";
}

/* ******************* BACKEND ******************* */

register_activation_hook(__FILE__, 'player_install'); 
function player_install() {
    // ajoute les options par défaut si elles n'existent pas encore
	add_option('player', array(
	    'skin' => '',
	    'height' => 300,
	    'width' => 400,
	    'versions' => array(
	        array('hd', 'WebM', 'html5'),
	        array('sd', 'WebM', 'html5'),
	        array('hd', 'H264'),
	        array('sd', 'H264')
	    ),
	    'controls' => 'over',
	    'poster' => 'attachment'
	));
}

if ( is_admin() ){
    
    // page d'options
    add_action('admin_init', 'player_register_settings');
    function player_register_settings() {
        
        add_settings_section('player_main', __('Video player','1player'), 'player_settings_section', 'media');
        
        function player_settings_section(){}
        
        add_settings_field('player_size', __('Player size','1player'), 'player_settings_size', 'media', 'player_main');
        function player_settings_size(){
            $options = get_option('player'); ?>
                <label for="player_size_w"><?php _e('Width') ?></label>
                <input id="player_size_w" class="small-text" type="text" name="player[width]" value="<?php echo $options['width'] ?>" />
                <label for="player_size_h"><?php _e('Height') ?></label>
                <input id="player_size_h" class="small-text" type="text" name="player[height]" value="<?php echo $options['height'] ?>" />
            <?php
        }
        
        add_settings_field('player_skin', '<label for="player_skin">'.__('Skin','1player').'</label>', 'player_settings_skin', 'media', 'player_main');
        function player_settings_skin(){
            $options = get_option('player'); ?>
                <input id="player_skin" class="regular-text code" name="player[skin]" type="text" value="<?php echo $options['skin'] ?>" />
                <span class="description"><?php printf(__('Skins are located in %1$s folder', '1player'), '<code>'.plugin_dir_path(__FILE__ ).'jwplayer/skins</code>') ?></span>
            <?php
        }
        
        add_settings_field('player_controls', '<label for="player_controls">'.__('Controls','1player').'</label>', 'player_settings_controls', 'media', 'player_main');
        function player_settings_controls(){
            $options = get_option('player'); ?>
                <label> <input name="player[controls]" type="radio" value="bottom" <?php if($options['controls'] == "bottom") echo 'checked="checked"' ?> /> <?php _e('Bottom') ?></label>
                <label> <input name="player[controls]" type="radio" value="top" <?php if($options['controls'] == "top") echo 'checked="checked"' ?> /> <?php _e('Top') ?></label>
                <label> <input name="player[controls]" type="radio" value="over" <?php if($options['controls'] == "over") echo 'checked="checked"' ?> /> <?php _e('Over') ?></label>
                <label> <input name="player[controls]" type="radio" value="none" <?php if($options['controls'] == "none") echo 'checked="checked"' ?> /> <?php _e('None') ?></label>
                <span class="description"><?php _e('Controls position', '1player') ?></span>
            <?php
        }
        
        add_settings_field('player_poster', '<label for="player_poster">'.__('Poster image','1player').'</label>', 'player_settings_poster', 'media', 'player_main');
        function player_settings_poster(){
            $options = get_option('player'); ?>
                <label> <input name="player[poster]" type="radio" value="attachment" <?php if($options['poster'] == "attachment") echo 'checked="checked"' ?> /> <?php _e('Video media attached image', '1player') ?></label>
                <label> <input name="player[poster]" type="radio" value="post_thumbnail" <?php if($options['poster'] == "post_thumbnail") echo 'checked="checked"' ?> /> <?php _e('Post featured image', '1player') ?></label>
            <?php
        }
        
        add_settings_field('player_versions', '<label for="player_versions">'.__('Video versions','1player').'</label>', 'player_settings_versions', 'media', 'player_main');
        function player_settings_versions(){
            global $labels;
            $options = get_option('player'); 
            if(!isset($options['versions']) || !is_array($options['versions'])) $options['versions'] = array();
            ?>
            <fieldset class="column" id="versions">
                <header><?php _e('Available versions', '1player') ?></header>
                <?php foreach($options['versions'] as $i => $version) : ?>
                    <div>
                        <?php echo $labels[$version[0]].' - '.$labels[$version[1]]; if (sizeof($version)>2) echo ' - '.$labels[$version[2]] ?>
                        <input type="hidden" name="player[versions][<?php echo $i ?>][0]" value="<?php echo $version[0] ?>" />
                        <input type="hidden" name="player[versions][<?php echo $i ?>][1]" value="<?php echo $version[1] ?>" />
                        <?php if(sizeof($version)>2) :?>
                        <input type="hidden" name="player[versions][<?php echo $i ?>][2]" value="<?php echo $version[2] ?>" />
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
                
                <style>
                    fieldset.column{
                        display: inline-block;
                        border-left: solid 1px #DFDFDF;
                        padding: 10px;
                    }
                    
                    fieldset.column:first-child{
                        border-left: none;
                    }
                    
                    fieldset.column header{
                        text-decoration: underline;
                    }
                    
                    fieldset.column .variante{
                        margin-left: 10px;
                    }
                </style>
                
                <script>
                    jQuery(function($){
                        // pour ajouter une version
                        $('.add_version_button').click(function(e){
                            var $parent = $(e.currentTarget).parent();
                            
                            var type = undefined;
                            $parent.find('[name="type"]').each(function(i, elt){
                                console.log({checked:elt.checked,type:type});
                                if(elt.checked) {
                                    if(type == undefined) type = elt.value;
                                    else type = '';
                                }
                            });
                            if(type == undefined) type = '';
                            
                            var qualite;
                            $parent.find('[name="qualite"]').each(function(i, elt){
                                if(elt.checked) qualite = elt.value;
                            });
                            
                            var format = $parent.find('[name="format"]').val();
                            
                            var index = $('#versions div').length;
                            
                            $('#versions').append('<div>'
                                + labels[qualite] + ' - ' + labels[format] + (type == '' ? '' : ' - '+labels[type])
                                + '<input type="hidden" name="player[versions]['+index+'][0]" value="'+qualite+'">'
                                + '<input type="hidden" name="player[versions]['+index+'][1]" value="'+format+'">'
                                + (type == '' ? '' : '<input type="hidden" name="player[versions]['+index+'][2]" value="'+type+'">') 
                                +'</div>');
                            return false;
                        });
                        
                        // pour supprimer une variante
                        $('.suppr_version_button').live('click', function(e){
                            $(e.currentTarget).parent().remove();
                        });
                    });
                </script>
            <?php
        }
        
        add_settings_field('player_mode', '<label for="player_mode">'.__('Playing mode','1player').'</label>', 'player_settings_mode', 'media', 'player_main');
        function player_settings_mode(){
            $options = get_option('player'); ?>
                <label> <input name="player[mode]" type="radio" value="html5flash" <?php if($options['mode'] == "html5flash") echo 'checked="checked"' ?> /> <?php _e('HTML5 if possible, else Flash','1player') ?></label>
                <label> <input name="player[mode]" type="radio" value="flashhtml5" <?php if($options['mode'] == "flashhtml5") echo 'checked="checked"' ?> /> <?php _e('Flash if possible, else HTML5','1player') ?></label>
                <label> <input name="player[mode]" type="radio" value="html5" <?php if($options['mode'] == "html5") echo 'checked="checked"' ?> /> <?php _e('HTML5 only','1player') ?></label>
                <label> <input name="player[mode]" type="radio" value="flash" <?php if($options['mode'] == "flash") echo 'checked="checked"' ?> /> <?php _e('Flash only','1player') ?></label>
            <?php
        }
        
        register_setting( 'media', 'player');
    }
    
    
    // gestion des champs supplémentaires pour les vidéos de la bibliothèque : édition
    add_filter('attachment_fields_to_edit', 'player_edit_fields', 10, 2);
    function player_edit_fields($form_fields, $post){
        global $labels;
    
        // seulement pour les vidéos
        if ( substr($post->post_mime_type, 0, 5) == 'video' ) {
            $metas = get_post_meta($post->ID, "1player", true);
            $player = get_option("player", true);
		
		    if($player["poster"] == "attachment") {
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
		        
		            $src = isset($metas['src'][$version[0]][$version[1]]) ? $metas['src'][$version[0]][$version[1]] : '';
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
            
            // sauvegarde des metas
            update_post_meta($post['ID'], "1player", $metas);
        }
        return $post;
    }
    
    // gestion des champs supplémentaires pour les vidéos depuis le web
    add_filter( 'type_url_form_media', 'player_insert_video_from_url');
    function player_insert_video_from_url(){
        global $labels;
    
        $player = get_option("player");
        if(!isset($player["versions"]) || !is_array($player["versions"])) $player["versions"]=array();
        
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
		
		// contruction des champs pour les URLs de la vidéo
		$urls = '<tr class="not-image">
			    <th valign="top" scope="row" class="label" style="width:130px;">
				    <span class="alignleft"><label for="src">' . __('URL') . '</label></span>
				    <span class="alignright"><abbr id="status_img" title="required" class="required">*</abbr></span>
			    </th>
			    <td class="field">';
			    
		if(sizeof($player["versions"]) == 0) {
		    $urls .= '<input id="src" name="src" value="" type="text" aria-required="true" onblur="addExtImage.getImageData()" />';
		} else {
		    
		    foreach($player["versions"] as $version){
		        $urls .= $labels[$version[0]].' - '.$labels[$version[1]].(sizeof($version)>2 ? ' - '.$labels[$version[2]] : '')
		            .'<br><input type="text" name="src['.$version[0].']['.$version[1].']'.(sizeof($version)>2 ? '['.$version[2].']' : '').'"/>';
	        }
		}
		
		$urls .= '</td></tr>';

	    $return = '
	    <p class="media-types"><label><input type="radio" name="media_type" value="image" id="image-only"' . checked( 'image-only', $view, false ) . ' /> ' . __( 'Image' ) . '</label> &nbsp; &nbsp; <label><input type="radio" name="media_type" value="generic" id="not-image"' . checked( 'not-image', $view, false ) . ' /> ' . __( 'Audio, Video, or Other File' ) . '</label></p>
	    <table class="describe ' . $table_class . '"><tbody>
		    <tr class="image-only">
			    <th valign="top" scope="row" class="label" style="width:130px;">
				    <span class="alignleft"><label for="src">' . __('URL') . '</label></span>
				    <span class="alignright"><abbr id="status_img" title="required" class="required">*</abbr></span>
			    </th>
			    <td class="field"><input id="src" name="src" value="" type="text" aria-required="true" onblur="addExtImage.getImageData()" /></td>
		    </tr>
		    '.$urls.'
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
		    </tr>';
		    
		    if($player["poster"] == "attachment") {
		        /* DEBUT modif poster */
		        $return .= '
		    <tr class="not-image">
			    <th valign="top" scope="row" class="label">
				    <span class="alignleft"><label for="poster">' . __('Thumbnail') . '</label></span>
			    </th>
			    <td class="field">'.
			        generateImageSelectorHTML(0, get_posts(array(
                        "post_type" => "attachment",
                        "numberposts" => -1,
                        "post_status" => null,
                        "post_mime_type" => "image",
                        "post_parent" => null
                    )))
			    .'</td>
		    </tr>';
		        /* FIN modif poster */;
		    }
		    $return .= '
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
    add_filter('video_send_to_editor_url', 'player_insert_new_video', 10, 3);
    function player_insert_new_video($html, $href, $title){
        $post_id = isset($_REQUEST['post_id']) ? intval($_REQUEST['post_id']) : 0;

        // commencer par enregistrer la vidéo dans la bibliothèque
        // TODO : récupérer le vrai type mime de la vidéo
        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => 'video/mp4',
            'post_parent' => $post_id,
            'post_title' => $title,
            'guid' => $href,
        ), false, $post_id);
        
        // enregistrer les metas
        $metas = array();
        if(isset($_REQUEST['poster'])) {
            $metas['poster'] = intval($_REQUEST['poster']);
            $image = wp_get_attachment_image_src($metas['poster'], 'video-large');
		    $poster = "poster=\"$image[0]\"";
        }
        if(isset($_REQUEST['hd'])) {
            $metas['hd'] = $_REQUEST['hd'];
            $hd = "hd=\"$metas[hd]\"";
        }
        
        if(sizeof($metas) > 0) {
            // sauvegarde des metas
            update_post_meta($attachment_id, "1player", $metas);
        }

        // ensuite, insérer le shortcode dans l'article
        return "[video src=\"$href\" $poster $hd]";
    }
    
    // Insertion d'une vidéo depuis la bibliothèque : remplacer le lien par un shortcode
    add_filter('media_send_to_editor', 'player_insert_video_shortcode', 15, 3);
    function player_insert_video_shortcode($html, $attachment_id, $attachment){
        $post =& get_post($attachment_id);
        
        // seulement dans le cas où on a une vidéo
	    if ( substr($post->post_mime_type, 0, 5) == 'video' ) {
		    $url = $post->guid;

		    $metas = get_post_meta($attachment_id, "1player", true);
		    if(isset($metas['poster']) && $metas['poster'] != -1) {
		        // TODO : gérer la taille du poster
		        $image = wp_get_attachment_image_src($metas['poster'], array(425,749));
		        $poster = "poster=\"$image[0]\"";
		    } else {		    
		        $poster = "";
		    }
		
		    $hd = isset($metas['hd']) ? "hd=\"$metas[hd]\"" : "";
            
		    $html = "[video src=\"$url\" $poster $hd]";
        }

        return $html;
    }
    
    add_action('media_upload_image', 'player_upload_new_video');
    function player_upload_new_video(){
        if (isset($_POST['savebutton'])) {
            $post_id = isset($_REQUEST['post_id']) ? intval($_REQUEST['post_id']) : 0;
            // commencer par enregistrer la vidéo dans la bibliothèque
            
            $player = get_option("player");
            
            // récupérer la première valeur
            $src = $_REQUEST['src'];
            while(is_array($src)) $src = array_shift($src);
            
            // TODO : récupérer le vrai type mime de la vidéo
            $attachment_id = wp_insert_attachment(array(
                'post_mime_type' => 'video/mp4',
                'post_parent' => $post_id,
                'post_title' => $_REQUEST['title'],
                'guid' => $src,
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
            $output .= "<option value='" . $post->ID . "' title='" . $post->guid . "' " . $selected . ">" . $post->post_title . "</option>\n";
          }
        }
        if (!$sel && $metas['poster'] != -1) {
          $image_post = get_post($metas['poster']);
          $output .= "<option value='" . $image_post->ID . "' title='" . $image_post->guid . "' selected=selected >" . $image_post->post_title . "</option>\n";
        }
        $output .= "</select>\n";
      }
      return $output;
    }
    
    
}

?>
