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

function slavicd_gif_editor_activate() {
	if (!class_exists('WP_Image_Editor_Imagick')) {
		deactivate_plugins(plugin_basename( __FILE__ ));
		wp_die(
			sprintf( __( 'This plugin requires Imagick php extension.', 'gif-editor' ) ),
			'Gif Editor',
			array( 'back_link' => true )
		);
	}

	require_once plugin_dir_path( __FILE__ ) . 'class-gif-editor.php';

	if (!Slavicd_Gif_Editor::binaryPresent()) {
		deactivate_plugins(plugin_basename( __FILE__ ));
		wp_die(
			sprintf( __( 'This plugin requires %s binary present on the system.', 'gif-editor' ), Slavicd_Gif_Editor::BINARY_NAME ),
			'Gif Editor',
			array( 'back_link' => true )
		);
	}
}

function slavicd_gif_editor_init($editors) {

	$editors[0] = 'Slavicd_Gif_Editor';

	return $editors;
}

add_filter("wp_image_editors", "slavicd_gif_editor_init", 10, 1);
register_activation_hook( __FILE__, 'slavicd_gif_editor_activate' );