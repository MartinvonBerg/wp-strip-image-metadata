function extract_webp_exif_data( string $filename ) :mixed {
	
	try {
		// check if file exists
		if ( !\file_exists( $filename )) {
			throw new Exception("Image file ". $filename ." does not exist");
		}

		// check if file is not empty
		$fileContents = \file_get_contents( $filename );
		if ( empty($fileContents)) {
			throw new Exception("Image file ". $filename ." is empty");
		}
		$fileContents = null;

		// check against PHP 8.0
		if (\version_compare(\phpversion(), '8.0.0', '<')) {
			throw new Exception("Wrong PHP version");
		}

		$imageData = \imagecreatefromwebp( $filename );
		$tempImagePath = \tempnam( \sys_get_temp_dir(), 'webp_to_jpeg');
		imagejpeg( $imageData, $tempImagePath);

		$exifData = \exif_read_data( $tempImagePath );
		unlink($tempImagePath);
		return $exifData;

	} catch ( \Exception $e ) {
		// Log the error
		\error_log("Error: " . $e->getMessage() );
	}
}

/**
 * Read out the required metadata from a Webp-file on the server. The result provides some more data than required.
 * Only tested for Nikon D7500 images after handling with Lightroom 6.14 and converson with imagemagick. Not done for all cameras that are around.
 * Title, caption and keywords are not found in EXIF-data. These are taken from XMP-data. 
 * This keys are set in the returned array: 
 * credit, copyright, title, caption, camera, keywords, GPS, make, 
 * orientation, lens, iso, exposure-time, aperture, focal-length, created-timestamp.
 * alt and description are not set.
 *
 * @param string $filename The complete path to the file in the directory.
 * @return array The exif data array similar to the JSON that is provided via the REST-API.
 */
function getWebpMetadata( string $filename ) 
{
	$parsedWebPData = extractMetadata( $filename );
	if ( ! $parsedWebPData ) {
		//return BROKEN_FILE;
		return [];
	}

	// other approach to get the EXIF-Data from the file
	$Exif = extract_webp_exif_data( $filename );

	$parsedWebPData['meta_version'] = WEBP_VERSION;
	return $parsedWebPData;
}