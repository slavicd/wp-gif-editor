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
			$this->image = $resized;
			return $this->update_size( $dst_w, $dst_h );
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

		error_log($this->getCommand($args), LOG_DEBUG);
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

	/**
	 * Streams current image to browser.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param string $mime_type
	 * @return true|WP_Error
	 */
	public function stream( $mime_type = null )
	{
		if ($this->mime_type != 'image/gif') {
			return parent::stream($mime_type);
		}

		list( $filename, $extension, $mime_type ) = $this->get_output_format( null, $mime_type );

		try {
			// Temporarily change format for stream
			$this->image->setImageFormat( strtoupper( $extension ) );

			// Output stream of image content
			header( "Content-Type: $mime_type" );
			print $this->image->getImagesBlob();

			// Reset Image to original Format
			$this->image->setImageFormat( $this->get_extension( $this->mime_type ) );
		}
		catch ( Exception $e ) {
			return new WP_Error( 'image_stream_error', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Overrides gif creations
	 *
	 * @param Imagick $image
	 * @param string $filename
	 * @param string $mime_type
	 * @return array|WP_Error
	 */
	protected function _save( $image, $filename = null, $mime_type = null )
	{
		if ($this->mime_type != 'image/gif') {
			return parent::_save($image, $filename, $mime_type);
		}

		list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );

		if ( ! $filename )
			$filename = $this->generate_filename( null, null, $extension );

		error_log(date('H:i:s: ') . 'saving ' . $filename . '; ' . sizeof($image) . ' frames' . "\n", 3, ABSPATH . 'gif-edit.log');

		try {
			// Store initial Format
			$orig_format = $this->image->getImageFormat();

			$this->image->setImageFormat( strtoupper( $this->get_extension( $mime_type ) ) );
			$this->make_image( $filename, array( $image, 'writeImages' ), array($filename, true) );

			// Reset original Format
			$this->image->setImageFormat( $orig_format );
		}
		catch ( Exception $e ) {
			return new WP_Error( 'image_save_error', $e->getMessage(), $filename );
		}

		// Set correct file permissions
		$stat = stat( dirname( $filename ) );
		$perms = $stat['mode'] & 0000666; //same permissions as parent folder, strip off the executable bits
		@ chmod( $filename, $perms );

		/** This filter is documented in wp-includes/class-wp-image-editor-gd.php */
		return array(
			'path'      => $filename,
			'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
			'width'     => $this->size['width'],
			'height'    => $this->size['height'],
			'mime-type' => $mime_type,
		);
	}


    /**
     * Create an image sub-size and return the image meta data value for it.
     *
     * OVERRIDE CORRECTS A BUG WITH $imagick->getImage() LOSING GIF FRAMES
     *
     * @since 5.3.0
     *
     * @param array $size_data {
     *     Array of size data.
     *
     *     @type int  $width  The maximum width in pixels.
     *     @type int  $height The maximum height in pixels.
     *     @type bool $crop   Whether to crop the image to exact dimensions.
     * }
     * @return array|WP_Error The image data array for inclusion in the `sizes` array in the image meta,
     *                        WP_Error object on error.
     */
    public function make_subsize( $size_data ) {
        if ( ! isset( $size_data['width'] ) && ! isset( $size_data['height'] ) ) {
            return new WP_Error( 'image_subsize_create_error', __( 'Cannot resize the image. Both width and height are not set.' ) );
        }

        $orig_size  = $this->size;
        $orig_image = clone $this->image;

        if ( ! isset( $size_data['width'] ) ) {
            $size_data['width'] = null;
        }

        if ( ! isset( $size_data['height'] ) ) {
            $size_data['height'] = null;
        }

        if ( ! isset( $size_data['crop'] ) ) {
            $size_data['crop'] = false;
        }

        $resized = $this->resize( $size_data['width'], $size_data['height'], $size_data['crop'] );

        if ( is_wp_error( $resized ) ) {
            $saved = $resized;
        } else {
            $saved = $this->_save( $this->image );

            $this->image->clear();
            $this->image->destroy();
            $this->image = null;
        }

        $this->size  = $orig_size;
        $this->image = $orig_image;

        if ( ! is_wp_error( $saved ) ) {
            unset( $saved['path'] );
        }

        return $saved;
    }
}
