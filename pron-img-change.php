<?php

/*
Plugin Name: Cr Img Change
Plugin URI: https://github.com/WP-Panda
Description: A brief description of the Plugin.
Version: 1.0
Author: Maksim (WP_Panda) Popov yoowordpress@yandex.ru
Author URI: https://github.com/WP-Panda
License: A "Slug" license name e.g. GPL2
*/



/**
 * активация и деактивация
 */
register_activation_hook( __FILE__, 'pron_img_change_activate' );
register_deactivation_hook(__FILE__, 'pron_img_change_deactivation');

function pron_img_change_activate() {
	$upload = wp_upload_dir();
	$upload_dir = $upload['basedir'];
	$upload_dir = $upload_dir . '/cr_img';
	if (! is_dir($upload_dir)) {
		mkdir( $upload_dir);
	}

	$notices= get_option('pron_img_change_deferred_admin_notices', array());
	$notices[]= "My Plugin: Custom Activation Message";
}

function pron_img_change_admin_init() {
	$current_version = 1;
	$version= get_option('pron_img_change_version');
	if ($version != $current_version) {
		// Do whatever upgrades needed here.
		update_option('pron_img_change_version', $current_version);
		$notices= get_option('pron_img_change_deferred_admin_notices', array());
		$notices[]= "Плагин Cr Img Change: Обновился с версии $version до $current_version.";
		update_option('pron_img_change_deferred_admin_notices', $notices);
	} else {
		//$notices= get_option('pron_img_change_deferred_admin_notices', array());
		$notices[]= "Плагин Cr Img Change: версии $version активирован.";
	}
}


function pron_img_change_admin_notices() {
	if ($notices= get_option('pron_img_change_deferred_admin_notices')) {
		foreach ($notices as $notice) {
			echo "<div class='updated'><p>$notice</p></div>";
		}
		delete_option('pron_img_change_deferred_admin_notices');
	}
}


function pron_img_change_deactivation() {
	//delete_option('pron_img_change_version');
	delete_option('pron_img_change_deferred_admin_notices');
}

add_action('admin_init', 'pron_img_change_admin_init');
add_action('admin_notices', 'pron_img_change_admin_notices');


/**
 * поддержка миниатюры
 */
function fff() {
	if ( function_exists( 'add_theme_support' ) ) {
		add_theme_support( 'post-thumbnails' );
		set_post_thumbnail_size( 147, 9999 );
	}
}
add_action('init','fff');


/**
 * основная тема
 */
function cr_get_all_posts(){
	$args = array(
		'post_type' => 'post',
		'post_status' => 'publish',
		'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'loads_img',
                'compare' => 'NOT EXISTS'
            )
        )
	);

	$upload = wp_upload_dir();
	$upload_dir = $upload['basedir'];
	$upload_dir = $upload_dir . '/cr_img';
	$uploads_dir = wp_upload_dir();

	$csv_query = new WP_Query($args);
	if ($csv_query->have_posts()):
		while ($csv_query->have_posts()) : $csv_query->the_post();
			$id_pos = get_the_ID();

			//имя папки
			$str = get_the_title();
			$pattern = '/\\[.*?\\]/';
			preg_match($pattern, $str,$file );
			$name = trim($file[0],'[]');

			//создаем папку
			$new_dir = $upload_dir . '/' . $name;
			if (! is_dir($new_dir)) {
				mkdir($new_dir);
			}

			//получаем картинки
			$content = get_the_content();
			preg_match_all('/<img[^>]+src="?\'?([^"\']+)"?\'?[^>]*>/i', $content, $images, PREG_SET_ORDER);

			//качаем картинки
			$n = 1;
			foreach ($images as $image) {
				$url = $image[1];
				if(strpos($url, 'pics.dmm') !== false) {
                    $site_name = $name .'_cover.jpg';
					$path = $new_dir .'/' . $site_name;
					$path_thumb = $new_dir .'/' . $name .'_thumb.jpg';

					$thumb = $name .'_thumb.jpg';
				} elseif ( strpos($url, 'imagebam') !== false) {
                    $site_name = $name .'_screenshot.jpg';
					$path = $new_dir .'/' . $site_name;

				}else{
					continue;
				}

                //качаем картинку
				file_put_contents($path, file_get_contents($url));

                //кропаем картинку
				$editor = wp_get_image_editor(  $path );
				$editor->resize( 575, 9999,'','',false );
				$size = $editor->get_size();
				$editor->save( $path );

				if(isset($path_thumb)){

                        //создаем миниатюру
						$editor->crop( 575/2, 0, 575/2,$size['height'], 147, $size['height']/(575/2/147) );
						$editor->save( $path_thumb );
						$editor->save( $uploads_dir['path'] . '/' . $thumb);


					$wp_filetype = wp_check_filetype($thumb, null );

					$attachment = array(
						'guid' => $wp_upload_dir['url'] . '/' . $thumb,
						'post_mime_type' => $wp_filetype['type'],
						'post_title' => sanitize_file_name($thumb),
						'post_content' => '',
						'post_status' => 'inherit'
					);

					$attach_id = wp_insert_attachment( $attachment, $uploads_dir['path'] . '/' . $thumb, $id_pos );
					require_once(ABSPATH . 'wp-admin/includes/image.php');
					$attach_data = wp_generate_attachment_metadata( $attach_id, $uploads_dir['path'] . '/' . $thumb );
					wp_update_attachment_metadata( $attach_id, $attach_data );

                    //задаем миниатюру
					update_post_meta($id_pos, '_thumbnail_id', $attach_id);
				}

                $content = str_replace($url, $upload['baseurl'].'/cr_img/' . $name . '/' . $site_name, $content);


				unset($path_thumb);
				/*echo '<pre>';
				print_r($image[1]);
                echo $n;
				echo '</pre>';*/
				$n++;
			}
            $my_post = array();
            $my_post['ID'] = $id_pos;
            $my_post['post_content'] = $content;
            wp_update_post( $my_post );

            //устанавливаем метку об обработке
            update_post_meta($id_pos, 'loads_img', 'yes');
		endwhile;
	else: endif;
}

add_shortcode('img_src','cr_get_all_posts');


function cr_empty_posts(){
    if(!is_single()) return;
    global $post;
    $tags = wp_get_post_tags($post->ID);
    $tags_array=array();
    foreach ($tags as $tag){
        $tags_array[] = $tag->term_id;
    }

    $args=array(
        'tag__in' => $tags_array,
        'post__not_in' => array($post->ID),
        'showposts'=>4,
        'caller_get_posts'=>1
    );
    $out = '';

    $out .='<h2>Other Posts</h2>';
    $related_query = new WP_Query($args);
    if( $related_query->have_posts() ) {

        $out .= '<ul>';
        while ($related_query->have_posts()) : $related_query->the_post();
            $out .='<li><a href="' . get_the_permalink() . '" rel="bookmark" title="'. get_the_title().'">'.get_the_title().'</a></li>';
        endwhile;
        $out .= '</ul>';
        wp_reset_query();
    }

    return $out;
}

function add_related_posts($content){
    $releted = cr_empty_posts();
    return $content . $releted;
}

add_filter('the_content', 'add_related_posts');