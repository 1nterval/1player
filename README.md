1player
=======

Wordpress plugin to display videos and songs with the native HTML5 player of browsers that have it or with the help of a javascript and flash library.

### Description

Wordpress can manage natively video and audio files, but when it comes to play it on your site, your need a plugin.
1player is such a plugin : when you insert a video or audio into a post, it will show a full featured HTML5 player that you can customize to your needs. You can choose to use the native HTML5 player provided by the latest browsers, or use a javascript/flash library to enhance it and provide the same level of service to older browsers.
Supported libraries are [MediaElementJS](http://www.mediaelementjs.com/) and [VideoJS](http://videojs.com/), but you can easily add new ones.

1player allows you to save in the Media Library files that are not hosted in your Wordpress installation (like a CDN). You can also specify several formats and qualities for each video and asign it a poster image from the Media Library.
Another feature is video or audio playlist : the same player is used to display several media, with next and prev buttons to got through the playlist.

### Extending

#### Add another HTML5 (javascript) player

The plugin has been designed to allow easy extensions to support other HTML5 players. Let's suppose your player is named "super_html5".
You will have to create a new directory in `wp-content/plugins/1player/players/super_html5` that contains a super_html5.php with the following content :

    add_action('super_html5_video_render', 'super_html5_render');
    function super_html5_render($args){
        // $args contains video sources and player options
        
        // display HTML5 video tag
        ?><video width="<?php echo $args['width'] ?>" height="<?php echo $args['height'] ?>" src="$args['videos'][0]['html5']['src']['src']" /><?php
    }

#### skins

The HTML5 native player skin is provided by the visitor browser : Firefox one is different from Safari's or Chrome's. 
But you can use MediaElementJS or VideoJS to provide the same skin across all browsers. Those players are skinable with CSS. 

For a personal skin development, please contact [1nterval](http://www.1nterval.com).

![Screenshot 3](https://raw.github.com/Fab1en/1player/master/assets/screenshot-3.png)
Video player for [amazonie.arte.tv](http://amazonie.arte.tv) with playlist controlled by thumbnails on the right

### License

© Copyright 2012 Fabien Quatravaux

1player is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

1player is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with 1player.  If not, see <http://www.gnu.org/licenses/>

[MediaElementJS](https://github.com/johndyer/mediaelement) is developped by [John Dyer](http://j.hn/) under GPLv2.

[VideoJS](https://github.com/zencoder/video-js) is developped by [zencoder](http://zencoder.com/) under LGPLv3

