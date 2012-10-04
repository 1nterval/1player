=== 1player ===
Contributors: fab1en
Tags: video, audio, html5, player
Requires at least: 3.0.1
Tested up to: 3.4
Stable tag: 1.3
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Display videos and songs with the native HTML5 player of browsers that have it or with the help of a javascript and flash library.

== Description ==

Wordpress can manage natively video and audio files, but when it comes to play it on your site, your need a plugin.
1player is such a plugin : when you insert a video or audio into a post, it will show a full featured HTML5 player that you can customize to your needs. You can choose to use the native HTML5 player provided by the latest browsers, or use a javascript/flash library to enhance it and provide the same level of service to older browsers.
Supported libraries are [MediaElementJS](http://www.mediaelementjs.com/) and [VideoJS](http://videojs.com/), but you can easily add new ones.

1player allows you to save in the Media Library files that are not hosted in your Wordpress installation (like a CDN). You can also specify several formats and qualities for each video and asign it a poster image from the Media Library.
Another feature is video or audio playlist : the same player is used to display several media, with next and prev buttons to got through the playlist.

== Installation ==

Upload the plugin files in your wp-content/plugins directory and go to the Plugins menu to activate it. 
Go to Settings > Media menu and customize your player.

== Frequently Asked Questions ==

= The HTML5 player ??? is not supported, can I add it ?  =

Yes, the plugin has been designed to allow easy extensions to support other HTML5 players. Let's suppose your player is named "super_html5".
You will have to create a new directory in `wp-content/plugins/1player/players/super_html5` that contains a super_html5.php with the following content :

`add_action('super_html5_video_render', 'super_html5_render');
function super_html5_render($args){
    // $args contains video sources and player options
    
    // display HTML5 video tag
    ?><video width="<?php echo $args['width'] ?>" height="<?php echo $args['height'] ?>" src="$args['videos'][0]['html5']['src']['src']" /><?php
}`

= Where can I find skins for my brand new player ? =

The HTML5 native player skin is provided by the visitor browser : Firefox one is different from Safari's or Chrome's. 
But you can use MediaElementJS or VideoJS to provide the same skin across all browsers. Those players are skinable with CSS. For a personal skin development, please contact [1nterval](http://www.1nterval.com).

== Screenshots ==

1. Admin area in Settings > Media menu
2. Admin area, when "add Media" button is clicked in the post edit page
3. Video player for [amazonie.arte.tv](http://amazonie.arte.tv) with playlist controlled by thumbnails on the right
4. Video player for [maisoncarree.eu](http://www.maisoncarree.eu)
5. Video player for [borgen.arte.tv](http://borgen.arte.tv) with playlist controlled by thumbnails on the left
6. Video player for [newyork.arte.tv](http://newyork.arte.tv) with playlist controlled by next/prev buttons
7. Audio player for [robertoalagna.net](http://www.robertoalagna.net) with dropdown playlist
8. Audio player for [juliettegreco.fr](http://www.juliettegreco.fr) with dropdown playlist

== Changelog ==

= 1.3 =
* Initial published version


