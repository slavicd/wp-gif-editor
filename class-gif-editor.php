<?php
/**
 * Created by PhpStorm.
 * User: slavic
 * Date: 10/18/17
 * Time: 5:51 PM
 */

class Slavicd_Gif_Editor extends WP_Image_Editor_Imagick
{
	const BINARY_NAME = 'gifsicle';
	const SMALL_GIF_RESOLUTION = 250000;    //pixels

	/**
	 * Deals with certain Gif images itself or delegates to parent if not an applicable image
	 *
	 * @param int $max_w
	 * @param int $max_h
	 * @param bool|array $crop
	 *
	 * @return stdClass|WP_Error
	 */
	public function resize($max_w, $max_h, $crop = false)
	{
		if ($this->mime_type != 'image/gif') {
			return parent::resize($max_w, $max_h, $crop);
		}

		$dims = image_resize_dimensions( $this->size['width'], $this->size['height'], $max_w, $max_h, $crop );
		if ( ! $dims ) {
			return new WP_Error( 'error_getting_dimensions', __('Could not calculate resized image dimensions'), $this->file );
		}
		list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;

		$resized = $this->processGif($dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);

		if ( $resized instanceof Imagick ) {
			$this->update_size( $dst_w, $dst_h );
			return $resized;
		}

		return new WP_Error( 'image_resize_error', __('Image resize failed.'), $this->file );
	}

	/**
	 * Checks if gifsicle is installed on the system
	 * @return bool
	 */
	public static function binaryPresent()
	{
		$bin = self::BINARY_NAME;

		if (`which {$bin}`) {
			return true;
		}

		return false;
	}

	/**
	 * @param $dst_x
	 * @param $dst_y
	 * @param $src_x
	 * @param $src_y
	 * @param $dst_w
	 * @param $dst_h
	 * @param $src_w
	 * @param $src_h
	 *
	 * @return resource|WP_Error
	 */
	private function processGif($dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h)
	{
		$descriptorspec = array(
			0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
			1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		);

		$args = [
			"--resize {$dst_w}x{$dst_h}",
			"--crop {$src_x},{$src_y}+{$src_w}x{$src_h}",
			'--optimize=3',
		];

		$small_gif_args = [
			'--colors 64',
			'--color-method blend-diversity',
			'--dither',
		];

		if ($dst_w*$dst_h <= self::SMALL_GIF_RESOLUTION) {
			$args = array_merge($args, $small_gif_args);
		}

		$process = proc_open($this->getCommand($args), $descriptorspec, $pipes);

		if (is_resource($process)) {
			// $pipes now looks like this:
			// 0 => writeable handle connected to child stdin
			// 1 => readable handle connected to child stdout
			// Any error output will be appended to /tmp/error-output.txt

			$this->image->writeImagesFile($pipes[0]);
			//fwrite($pipes[0], '');
			fclose($pipes[0]);

			$result = stream_get_contents($pipes[1]);
			fclose($pipes[1]);

			// It is important that you close any pipes before calling
			// proc_close in order to avoid a deadlock
			$return_value = proc_close($process);
			if ($return_value != '0') {
				return new WP_Error( 'image_resize_error', __('Image resize failed: command failed.'), $this->file );
			}

			$imagick = new Imagick();
			$imagick->readImageBlob($result);
			$this->image = $imagick;

			return $imagick;
		} else {
			return new WP_Error( 'image_resize_error', __('Image resize failed: command failed.'), $this->file );
		}
	}

	private function getCommand($args=[])
	{
		$defaults = [
			'--output "-"'
		];

		return self::BINARY_NAME . ' ' . implode(' ', array_merge($defaults, $args));
	}
}