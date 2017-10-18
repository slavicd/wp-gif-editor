<?php
/*
 * Testing script.
 */


/**
 * An emulator of the WP_Image_Editor_GD just for testing
 */
class WP_Image_Editor_Imagick
{
	public $image;
	public $file;
	protected $mime_type;
	protected $size;

	public function __construct($file)
	{
		$this->file = $file;
		$this->mime_type = mime_content_type($this->file);
		$this->image = new Imagick($this->file);
		$this->size = $this->image->getImageGeometry();
	}

	public function resize($max_w, $max_h, $crop)
	{
		die('Halted: parent resize does nothing!');
	}

	public function update_size($dst_w, $dst_h)
	{

	}
}

function image_resize_dimensions($orig_w, $orig_h, $dest_w, $dest_h, $crop) {
	return array( 0, 0, 0, 0, $dest_w, $dest_h, $orig_w, $orig_h );
}


require './class-gif-editor.php';

$editor = new Slavicd_Gif_Editor('input/001.gif');
$editor->resize(375, 195, true);
$editor->image->writeImages('input/001_out.gif', true);