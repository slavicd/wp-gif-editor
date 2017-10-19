<?php

/*
Plugin Name: SlavicD's Gif Editor
Plugin URI:
Description: Enable WordPress to properly resize and optimize animated GIFs.
Author: Slavic Dragovtev
Version: 0.1.0
Author URI:
*/

defined( 'ABSPATH' ) || exit;

class Slavicd_GifEditor_Plugin
{
	public static function activate()
	{
		require_once ABSPATH . 'wp-includes/class-wp-image-editor.php';
		require_once ABSPATH . 'wp-includes/class-wp-image-editor-imagick.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-gif-editor.php';

		if (!extension_loaded('imagick')) {
			deactivate_plugins(plugin_basename( __FILE__ ));
			wp_die(
				sprintf( __( 'This plugin requires Imagick php extension.', 'gif-editor' ) ),
				'Gif Editor',
				array( 'back_link' => true )
			);
		}

		if (!Slavicd_Gif_Editor::binaryPresent()) {
			deactivate_plugins(plugin_basename( __FILE__ ));
			wp_die(
				sprintf( __( 'This plugin requires %s binary present on the system.', 'gif-editor' ), Slavicd_Gif_Editor::BINARY_NAME ),
				'Gif Editor',
				array( 'back_link' => true )
			);
		}
	}

	public static function init()
	{
		require_once ABSPATH . 'wp-includes/class-wp-image-editor.php';
		require_once ABSPATH . 'wp-includes/class-wp-image-editor-imagick.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-gif-editor.php';

		add_filter("wp_image_editors", [self::class, 'alterWpEditors'], 10, 1);
	}

	public static function alterWpEditors($editors)
	{
		//array_unshift($editors, 'Slavicd_Gif_Editor');

		//return $editors;
		return ['Slavicd_Gif_Editor'];
	}
}


register_activation_hook( __FILE__, array( Slavicd_GifEditor_Plugin::class, 'activate' ) );
add_action( 'init', array(Slavicd_GifEditor_Plugin::class, 'init') );