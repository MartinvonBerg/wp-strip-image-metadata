<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin Name: WP Strip Image Metadata (JPG + WEBP)
 * Plugin URI: https://github.com/samiff/wp-strip-image-metadata
 * Description: Strip image metadata on upload or via bulk action, and view image EXIF data.
 * Version: 1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Martin von Berg and Samiff
 * Author URI: https://www.berg-reise-foto.de
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: wp-strip-image-metadata
 */

// TODO: add size setting for admin page.
// TODO: make same handling for webp and jpeg.
// Todo: provide different images as templates for webp. sRGB is missing. 
// TODO: add github uri
// TODO: copy change of extractMetadata to fotorama_multi.


namespace mvbplugins\stripmetadata;

require_once __DIR__ . '/inc/extractMetadata.php';
require_once __DIR__ . '/inc/implode_all.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disable some PHPCS rules file wide.
 * phpcs:disable WordPress.PHP.YodaConditions.NotYoda
 */

/**
 * WP_Strip_Image_Metadata main class.
 */
class WP_Strip_Image_Metadata {

	/**
	 * Image file types to strip metadata from.
	 * Modify types with the 'wp_strip_image_metadata_image_file_types' filter hook.
	 *
	 * @var array
	 */
	public static $image_file_types = array(
		'image/jpg',
		'image/jpeg',
		'image/webp'
	);
	public static $sizeLimit = 200;
	public static $keepCopyright = true;
	public static $setColourSpaceToRGB = false;

	/**
	 * Initialize plugin hooks and resources.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu_init' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings_init' ) );
		add_action( 'wp_rest_mediacat_upload', array( __CLASS__, 'strip_meta_after_rest_mediacat'), 10, 2 );
		add_filter( 'wp_generate_attachment_metadata', array(__CLASS__,'strip_meta_after_generate_attachment_metadata'), 10, 3 );
		add_filter( 'bulk_actions-upload', array( __CLASS__, 'register_bulk_strip_action' ) );
		add_filter( 'handle_bulk_actions-upload', array( __CLASS__, 'handle_bulk_strip_action' ), 10, 3 );
		self::admin_notices();
		register_uninstall_hook( __FILE__, array( __CLASS__, 'plugin_cleanup' ) );
	}

	/**
	 * Function for `wp_generate_attachment_metadata` filter-hook. Strip metadata from files according to settings.
	 * 
	 * @param array  $metadata      An array of attachment meta data.
	 * @param int    $attachment_id Current attachment ID.
	 * @param string $context       Additional context. Can be 'create' when metadata was initially created for new attachment or 'update' when the metadata was updated.
	 *
	 * @return array returning unchanged $metadata
	 */
	public static function strip_meta_after_generate_attachment_metadata( $metadata, $attachment_id, $context ){
		// loop through images
		$paths = self::get_all_paths_for_image( $attachment_id );
		foreach ( $paths as $file) {
			self::strip_image_metadata( $file );
		}
		// returning unchanged $metadata is required for this filter
		return $metadata;
	}

	/**
	 * Function for `wp_generate_attachment_metadata` filter-hook. Strip metadata from files according to settings.
	 * 
	 * @param array  $metadata      An array of attachment meta data.
	 * @param int    $attachment_id Current attachment ID.
	 * @param string $context       Additional context. Can be 'create' when metadata was initially created for new attachment or 'update' when the metadata was updated.
	 *
	 * @return array returning unchanged $metadata
	 */
	public static function strip_meta_after_rest_mediacat( $attachment_id, $context ){
		if ( $context !== 'context-rest-upload') {return;}

		// loop through images
		$paths = self::get_all_paths_for_image( $attachment_id );
		foreach ( $paths as $file) {
			self::strip_image_metadata( $file );
		}
	
	}

	

	/**
	 * Register the submenu plugin item under WP Admin Settings.
	 *
	 * @return void
	 */
	public static function menu_init() {
		add_options_page(
			__( 'WP Strip Image Metadata', 'wp-strip-image-metadata' ),
			__( 'Strip Image Metadata', 'wp-strip-image-metadata' ),
			'manage_options',
			'wp_strip_image_metadata',
			array( __CLASS__, 'plugin_settings_page' ),
		);
	}

	/**
	 * The plugin settings page contents.
	 *
	 * @return void
	 */
	public static function plugin_settings_page() {
		$image_lib = self::has_supported_image_library();
		?>
		<div id="wp_strip_image_metadata" class="wrap">
			<h1><?php esc_html_e( 'WP Strip Image Metadata', 'wp-strip-image-metadata' ); ?></h1>

			<form action="options.php" method="post">
				<?php
					settings_fields( 'wp_strip_image_metadata_settings' );
					do_settings_sections( 'wp_strip_image_metadata' );
					submit_button( __( 'Save settings', 'wp-strip-image-metadata' ), 'primary' );
				?>
			</form>
			<br>
			<h1><?php esc_html_e( 'Plugin Information', 'wp-strip-image-metadata' ); ?></h1>
			<?php
			if ( $image_lib ) {
				echo esc_html(
					sprintf(
					/* translators: %s is the image processing library name and version active on the site */
						__( 'Compatible image processing library active: %s', 'wp-strip-image-metadata' ),
						$image_lib . ' ' . phpversion( $image_lib )
					)
				);
			} else {
				esc_html_e( 'WP Strip Image Metadata: compatible image processing library not found. This plugin requires the "Imagick" or "Gmagick" PHP extension to function - please ask your webhost or system administrator if either can be enabled.', 'wp-strip-image-metadata' );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Register the plugin settings.
	 *
	 * @return void
	 */
	public static function settings_init() {
		$args = array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			'show_in_rest'      => false,
		);

		add_settings_section(
			'wp_strip_image_metadata_settings_section',
			__( 'Plugin Settings', 'wp-strip-image-metadata' ),
			array( __CLASS__, 'settings_section_text' ),
			'wp_strip_image_metadata',
		);

		add_settings_field(
			'wp_strip_image_metadata_setting_strip_active',
			__( 'Image Metadata Stripping', 'wp-strip-image-metadata' ),
			array( __CLASS__, 'setting_output' ),
			'wp_strip_image_metadata',
			'wp_strip_image_metadata_settings_section',
			'strip_active',
		);

		add_settings_field(
			'wp_strip_image_metadata_setting_preserve_icc',
			__( 'Preserve ICC Color Profile', 'wp-strip-image-metadata' ),
			array( __CLASS__, 'setting_output' ),
			'wp_strip_image_metadata',
			'wp_strip_image_metadata_settings_section',
			'preserve_icc',
		);

		add_settings_field(
			'wp_strip_image_metadata_setting_preserve_orientation',
			__( 'Preserve Image Orientation', 'wp-strip-image-metadata' ),
			array( __CLASS__, 'setting_output' ),
			'wp_strip_image_metadata',
			'wp_strip_image_metadata_settings_section',
			'preserve_orientation',
		);

		add_settings_field(
			'wp_strip_image_metadata_setting_sizelimit',
			__( 'Set Size Limit', 'wp-strip-image-metadata' ),
			array( __CLASS__, 'setting_output' ),
			'wp_strip_image_metadata',
			'wp_strip_image_metadata_settings_section',
			'sizelimit',
		);

		add_settings_field(
			'wp_strip_image_metadata_setting_logging',
			__( 'Log Errors', 'wp-strip-image-metadata' ),
			array( __CLASS__, 'setting_output' ),
			'wp_strip_image_metadata',
			'wp_strip_image_metadata_settings_section',
			'logging',
		);

		register_setting( 'wp_strip_image_metadata_settings', 'wp_strip_image_metadata_settings', $args );
	}

	/**
	 * The default plugin settings option values.
	 *
	 * @return array
	 */
	public static function default_plugin_settings() {
		return array(
			'strip_active'         => 'enabled',
			'preserve_icc'         => 'enabled',
			'preserve_orientation' => 'enabled',
			'logging'              => 'disabled',
			'sizelimit'            => 300,
		);
	}

	/**
	 * Set the default plugin settings option.
	 *
	 * @return void
	 */
	public static function set_default_plugin_settings() {
		update_option(
			'wp_strip_image_metadata_settings',
			self::default_plugin_settings(),
			false,
		);
	}

	/**
	 * Retrieves the plugin settings option value. Sets the option if it doesn't exist.
	 *
	 * @return array
	 */
	public static function get_plugin_settings() {
		$settings = get_option( 'wp_strip_image_metadata_settings' );

		if ( empty( $settings ) ) {
			self::set_default_plugin_settings();
			return self::default_plugin_settings();
		}

		return $settings;
	}

	/**
	 * Sanitize the user input settings.
	 *
	 * @param array $input Received settings to sanitize.
	 *
	 * @return array Sanitized settings saved.
	 */
	public static function sanitize_settings( $input ) {
		return array(
			'strip_active'         => $input['strip_active'] === 'disabled' ? 'disabled' : 'enabled',
			'preserve_icc'         => $input['preserve_icc'] === 'disabled' ? 'disabled' : 'enabled',
			'preserve_orientation' => $input['preserve_orientation'] === 'disabled' ? 'disabled' : 'enabled',
			'logging'              => $input['logging'] === 'disabled' ? 'disabled' : 'enabled',
		);
	}

	/**
	 * Plugin settings section text output.
	 *
	 * @return string
	 */
	public static function settings_section_text() {
		return '';
	}

	/**
	 * Settings field callback.
	 *
	 * @param string $setting The setting to output HTML for.
	 *
	 * @return void
	 */
	public static function setting_output( $setting ) {
		$settings      = self::get_plugin_settings();
		$setting_value = $settings[ $setting ];

		// Radio button options.
		$items = array( 'enabled', 'disabled' );

		foreach ( $items as $item ) {
			?>
				<label>
					<input
						type="radio"
						<?php echo checked( $setting_value, $item, false ); ?>
						value="<?php echo esc_attr( $item ); ?>"
						name="<?php echo esc_attr( "wp_strip_image_metadata_settings[${setting}]" ); ?>"
					/>
					<?php
					if ( $item === 'enabled' ) {
						esc_html_e( 'Enabled', 'wp-strip-image-metadata' );
					} else {
						esc_html_e( 'Disabled', 'wp-strip-image-metadata' );
					}
					?>
				</label>
				<br>
			<?php
		}
	}

	/**
	 * Check for a supported image processing library on the site.
	 *
	 * @todo Check for specific library versions.
	 *
	 * @return string|false The supported image library to use, or false if no support found.
	 */
	public static function has_supported_image_library() {
		// Test for Imagick support.
		$imagick = false;

		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick', false ) ) {
			$imagick = true;
		}

		if ( $imagick ) {
			return 'Imagick';
		}

		// Test for Gmagick support.
		$gmagick = false;

		if ( extension_loaded( 'gmagick' ) && class_exists( 'Gmagick', false ) ) {
			$gmagick = true;
		}

		if ( $gmagick ) {
			return 'Gmagick';
		}

		return false;
	}

	/**
	 * Strip metadata from an image.
	 *
	 * @param string $file The file path (not URL) to an uploaded media item.
	 *
	 * @return null
	 */
	public static function strip_image_metadata( $file ) {
		$mime = mime_content_type( $file );

		// Check for supported file type.
		if ( ! in_array( $mime, self::$image_file_types, true ) ) {
			return;
		} elseif ( $mime === 'image/jpg') {
			$mime = 'image/jpeg';
		}
		// check for image converter support
		$img_lib = self::has_supported_image_library();
		if ( ! $img_lib ) {
			return;
		}

		$settings             = self::get_plugin_settings();
		$preserve_icc         = array_key_exists( 'preserve_icc', $settings ) ? $settings['preserve_icc'] : 'enabled';
		$preserve_orientation = array_key_exists( 'preserve_orientation', $settings ) ? $settings['preserve_orientation'] : 'enabled';

		// Using the Imagick or Gmagick library for jpegs.
		if ( $mime === 'image/jpeg') {
			if ( $img_lib === 'Imagick' ) {

				try {
					$imagick = new \Imagick( $file );
				} catch ( \Exception $e ) {
					self::logger( 'WP Strip Image Metadata: error while opening image path using Imagick: ' . $e->getMessage() );
				}

				$icc_profile = null;
				$orientation = null;

				// Capture ICC profile if preferred.
				if ( $preserve_icc === 'enabled' ) {
					try {
						$icc_profile = $imagick->getImageProfile( 'icc' );
					} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
						// May not be set, ignore.
					}
				}

				// Capture image orientation if preferred.
				if ( $preserve_orientation === 'enabled' ) {
					try {
						$orientation = $imagick->getImageOrientation();
					} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
						// May not be set, ignore.
					}
				}

				// Strip the metadata.
				try {
					$imagick->stripImage();
				} catch ( \Exception $e ) {
					self::logger( 'WP Strip Image Metadata: error while stripping image metadata using Imagick: ' . $e->getMessage() );
				}

				// Add back $icc_profile if present.
				if ( $icc_profile ) {
					try {
						$imagick->setImageProfile( 'icc', $icc_profile );
					} catch ( \Exception $e ) {
						self::logger( 'WP Strip Image Metadata: error while setting ICC profile using Imagick: ' . $e->getMessage() );
					}
				}

				// Add back $orientation if present.
				if ( $orientation !== null ) {
					try {
						$imagick->setImageOrientation( $orientation );
					} catch ( \Exception $e ) {
						self::logger( 'WP Strip Image Metadata: error while setting image orientation using Imagick: ' . $e->getMessage() );
					}
				}

				$dimensions = $imagick->getImageGeometry(); 
				$width = $dimensions['width']; 
				$height = $dimensions['height']; 

				// Overwrite the image file path, including any metadata modifications made.
				try {
					if ( $width <= self::$sizeLimit ) {$imagick->writeImage( $file );}
				} catch ( \Exception $e ) {
					self::logger( 'WP Strip Image Metadata: error while overwriting image file using Imagick: ' . $e->getMessage() );
				}

				// Free $imagick object.
				$imagick->clear();

			} elseif ( $img_lib === 'Gmagick' ) {
				// Using the Gmagick library. 
				// TODO: add the sizeLimit if or handle size outside of if.

				try {
					$gmagick = new \Gmagick( $file );
				} catch ( \Exception $e ) {
					self::logger( 'WP Strip Image Metadata: error while opening image path using Gmagick: ' . $e->getMessage() );
				}

				$icc_profile = null;
				// $orientation = null; @todo: currently not capturing orientation via Gmagick.

				// Capture ICC profile if preferred.
				if ( $preserve_icc === 'enabled' ) {
					try {
						$icc_profile = $gmagick->getimageprofile( 'icc' );
					} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
						// May not be set, ignore.
					}
				}

				// Capture image orientation if preferred.
				// @todo Unlike Imagick, there isn't an equivalent getImageOrientation() helper.
				// Check into grabbing the orientation a different way.

				// Strip the metadata.
				try {
					$gmagick->stripimage();
				} catch ( \Exception $e ) {
					self::logger( 'WP Strip Image Metadata: error while stripping image metadata using Gmagick: ' . $e->getMessage() );
				}

				// Add back $icc_profile if present.
				if ( $icc_profile ) {
					try {
						$gmagick->setimageprofile( 'icc', $icc_profile );
					} catch ( \Exception $e ) {
						self::logger( 'WP Strip Image Metadata: error while setting ICC profile using Gmagick: ' . $e->getMessage() );
					}
				}

				// Add back $orientation if present.
				// @todo: currently not capturing orientation via Gmagick.

				// Overwrite the image file path, including any metadata modifications made.
				try {
					$gmagick->writeimage( $file );
				} catch ( \Exception $e ) {
					self::logger( 'WP Strip Image Metadata: error while overwriting image file using Gmagick: ' . $e->getMessage() );
				}

				// Free $gmagick object.
				$gmagick->destroy();
			}

		} elseif ( $mime === 'image/webp') {

			if ( $img_lib === 'Imagick' ) {

				// Open the copyright image with the correct EXIF data
				$pathToTemplateFile = __DIR__ . \DIRECTORY_SEPARATOR . 'images' . \DIRECTORY_SEPARATOR . 'copyright.webp';

				if (!\file_exists($pathToTemplateFile) ) {
					self::logger('WP Strip Image Metadata: File ' . $pathToTemplateFile . ' not found.');
					return;
				}

				// Open the image to alter and get its size
				$imageFile = new \Imagick($file);
				$dimensions = $imageFile->getImageGeometry(); 
				$width = $dimensions['width']; 
				$height = $dimensions['height']; 
				
				// do only for all images smaller than $sizeLimit. So $sizeLimit = 0 means no image at all. But $sizeLimit = 10000 means all images.
				if ( $width <= self::$sizeLimit  ) {

					$icc_profile = null;
					$orientation = null;

					// Capture ICC profile if preferred.
					if ( $preserve_icc === 'enabled' ) {
						try {
							$icc_profile = $imageFile->getImageProfile( 'icc' );
						} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
							// May not be set, ignore.
						}
					}

					// Capture image orientation if preferred. \Imagick::ORIENTATION_UNDEFINED = 0 : is undefined, so it is not written.
					if ( $preserve_orientation === 'enabled' ) {
						try {
							$orientation = $imageFile->getImageOrientation();
						} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
							// May not be set, ignore.
						}
					}

					// Strip the metadata.
					try {
						$imageFile->stripImage();
					} catch ( \Exception $e ) {
						self::logger( 'WP Strip Image Metadata: error while stripping image metadata using Imagick: ' . $e->getMessage() );
					}

					// Add back $icc_profile if present.
					if ( $icc_profile !== null) {
						try {
							$imageFile->setImageProfile( 'icc', $icc_profile );
						} catch ( \Exception $e ) {
							self::logger( 'WP Strip Image Metadata: error while setting ICC profile using Imagick: ' . $e->getMessage() );
						}
					}

					// Add back $orientation if present. \Imagick::ORIENTATION_UNDEFINED = 0 : is undefined and 0 = false!
					if ( $orientation ) {
						try {
							$imageFile->setImageOrientation( $orientation );
						} catch ( \Exception $e ) {
							self::logger( 'WP Strip Image Metadata: error while setting image orientation using Imagick: ' . $e->getMessage() );
						}
					}

					if ( self::$keepCopyright ) {
						// generate image with copyright information
						// source: https://stackoverflow.com/questions/37791236/add-copyright-string-to-jpeg-using-imagemagick-imagick-in-php
						try {
							$templateFile = new \Imagick(__DIR__ . \DIRECTORY_SEPARATOR . 'images' . \DIRECTORY_SEPARATOR. 'copyright.webp');

							// Resize the copyright and composite the image over the top
							$templateFile->resizeImage($width,$height,\imagick::FILTER_POINT,0,0);

							// Set compression Quality and generate the image
							$compressionQual = $imageFile->getCompressionQuality();
							$templateFile->setCompressionQuality( $compressionQual );
							$templateFile->compositeImage($imageFile,\imagick::COMPOSITE_SRCOVER ,0,0); 

							// set profile and orientation
							if ( $icc_profile !== null) { $templateFile->setImageProfile( 'icc', $icc_profile );}
							if ( $orientation ) { $templateFile->setImageOrientation( $orientation );}

							// write the new file
							$templateFile->writeImage($file);
							$templateFile->clear();

						} catch ( \Exception $e ) {
							self::logger( 'WP Strip Image Metadata: error while using Copyright file forwebp image file using Imagick: ' . $e->getMessage() );
						}

					} else {
						// Overwrite the image file path, including any metadata modifications made.
						try {
							$imageFile->writeImage( $file );
						} catch ( \Exception $e ) {
							self::logger( 'WP Strip Image Metadata: error while overwriting webp image file using Imagick: ' . $e->getMessage() );
						}
					}

				} 

				// clear imagick
				$imageFile->clear();
			
			} else {
				self::logger( 'WP Strip Image Metadata: No Image Handler defined for webp-Files. Only Imagick works' );
			}
		}
	}

	/**
	 * Add hooks for various admin notices.
	 *
	 * @return void
	 */
	public static function admin_notices() {
		$settings = self::get_plugin_settings();

		// If no supported image processing libraries are found, show an admin notice and bail.
		if ( ! self::has_supported_image_library() ) {
			add_action(
				'admin_notices',
				function () {
					?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'WP Strip Image Metadata: compatible image processing library not found. This plugin requires the "Imagick" or "Gmagick" PHP extension to function - please ask your webhost or system administrator if either can be enabled.', 'wp-strip-image-metadata' ); ?></p>
				</div>
					<?php
				}
			);

			return;
		}

		// On the Media upload page, show a notice when the image metadata stripping setting is disabled.
		if ( $settings['strip_active'] === 'disabled' ) {
			add_action(
				'load-upload.php',
				function () {
					add_action(
						'admin_notices',
						function () {
							?>
					<div class="notice notice-error is-dismissible">
						<p><?php esc_html_e( 'WP Strip Image Metadata: stripping is currently disabled.', 'wp-strip-image-metadata' ); ?></p>
					</div>
							<?php
						}
					);
				}
			);
		}

		// When viewing an attachment details page, show image EXIF data in an admin notice.
		add_action(
			'load-post.php',
			function () {
				$post     = isset( $_GET['post'] ) ? intval( $_GET['post'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$is_image = wp_attachment_is_image( $post );
				$mime = \get_post_mime_type( $post );
				$pathToOriginalImage = wp_get_original_image_path( $post );
				$exif = false;
				$paths = [];
				$paths = array_merge( $paths, self::get_all_paths_for_image( $post ) );

				// sanitize jpg mime type.
				if ( $mime === 'image/jpg') { $mime = 'image/jpeg'; }

				if ( $is_image && $mime === 'image/jpeg' ) {

					try {
						$exif = \mvbplugins\stripmetadata\getJpgMetadata( $pathToOriginalImage );
					} catch ( \Exception $e ) {
						self::logger( 'WP Strip Image Metadata: error reading jgp-EXIF data: ' . $e->getMessage() );
					}

				} elseif ( $is_image && $mime === 'image/webp' ) {

					try {
						$exif = \mvbplugins\stripmetadata\getWebpMetadata( $pathToOriginalImage );
					} catch ( \Exception $e ) {
						self::logger( 'WP Strip Image Metadata: error reading webp-EXIF data: ' . $e->getMessage() );
					}

				} else { return;}

				foreach ( $paths as $key => $path) {
					if ( $is_image && $mime === 'image/jpeg' ) {
						$exifData = \mvbplugins\stripmetadata\getJpgMetadata( $path );
						if ( \mvbplugins\stripmetadata\implode_all( ' ', $exifData) === " -- -- -- -- ---    0 notitle   ") {$exifData = '';}; 
					} elseif ( $is_image && $mime === 'image/webp' ) {
						$exifData = \mvbplugins\stripmetadata\getWebpMetadata( $path );
					}
					$size = \strlen( \mvbplugins\stripmetadata\implode_all( ' ', $exifData ) );
					$paths[ $key ] = 'Meta Size = ' . $size . ' of ' . $paths[ $key ];
				}
				sort( $paths );

				if ( $is_image ) {
					$exifAsStringLength = \strlen( \mvbplugins\stripmetadata\implode_all( ' ', $exif ) );
					
					add_action(
						'admin_notices',
						function () use ( $exif, $exifAsStringLength, $paths ) {
							?>
					<div class="notice notice-info is-dismissible">
						<details style="padding-top:8px;padding-bottom:8px;">
							<summary>
								<?php esc_html_e( 'WP Strip Image Metadata: expand for image EXIF data. Length : ' . $exifAsStringLength, 'wp-strip-image-metadata' ); ?>
							</summary>
							<div>
								<?php
									echo '<pre>' . esc_html( print_r($exif) ) . '</pre>'; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
									foreach ( $paths as $path) {
										echo '<p>'; esc_html( print_r( $path ) ); echo '</p>'; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
									}
								?>
							</div>
						</details>
					</div>
							<?php
						}
					);
				}
			}
		);

		// When using the custom bulk strip image metadata action, show how many images were modified.
		$img_count = isset( $_GET['bulk_wp_strip_img_meta'] ) ? intval( $_GET['bulk_wp_strip_img_meta'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $img_count ) {
			add_action(
				'admin_notices',
				function () use ( $img_count ) {
					?>
				<div class="notice notice-success is-dismissible">
					<p>
					<?php
						echo esc_html(
							sprintf(
							/* translators: placeholders are the number of images processed with the bulk action */
								_n(
									'WP Strip Image Metadata: %s image including generated thumbnail sizes was processed.',
									'WP Strip Image Metadata: %s images including generated thumbnail sizes were processed.',
									$img_count,
									'wp_strip_image_metadata'
								),
								$img_count
							)
						);
					?>
					</p>
				</div>
					<?php
				}
			);
		}

		// When the bulk strip action can't find one of the image file paths, show an error notice.
		$bulk_error = isset( $_GET['bulk_wp_strip_img_meta_err'] ) ? intval( $_GET['bulk_wp_strip_img_meta_err'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $bulk_error === 1 ) {
			add_action(
				'admin_notices',
				function () {
					?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e( 'WP Strip Image Metadata: unable to locate all image paths. This might be due to a non-standard WordPress uploads directory location.', 'wp-strip-image-metadata' ); ?></p>
				</div>
					<?php
				}
			);
		}
	}

	/**
	 * Register a custom bulk action for the upload admin screen (works for list view only not grid).
	 *
	 * @param array $bulk_actions Registered actions.
	 *
	 * @return array
	 */
	public static function register_bulk_strip_action( $bulk_actions ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $bulk_actions;
		}

		$bulk_actions['wp_strip_image_metadata'] = __( 'WP Strip Image Metadata', 'wp-strip-image-metadata' );
		return $bulk_actions;
	}

	/**
	 * Handles the custom bulk strip image metadata action.
	 *
	 * @param string $redirect_url The redirect URL.
	 * @param string $action       The bulk action being taken.
	 * @param array  $ids          The attachment IDs.
	 *
	 * @return string Redirect URL.
	 */
	public static function handle_bulk_strip_action( $redirect_url, $action, $ids ) {
		if ( $action !== 'wp_strip_image_metadata' ) {
			return $redirect_url;
		}

		// Filter for only images (not videos or other media items).
		$ids = array_filter(
			$ids,
			function ( $id ) {
				return wp_attachment_is_image( $id );
			}
		);

		$paths = array();
		foreach ( $ids as $id ) {
			$paths = array_merge( $paths, self::get_all_paths_for_image( $id ) );
		}

		foreach ( $paths as $path ) {
			if ( ! file_exists( $path ) ) {
				$redirect_url = remove_query_arg( 'bulk_wp_strip_img_meta', $redirect_url );
				$redirect_url = add_query_arg( 'bulk_wp_strip_img_meta_err', 1, $redirect_url );
				return $redirect_url;
			}
		}

		foreach ( $paths as $path ) {
			self::strip_image_metadata( $path );
		}

		$redirect_url = remove_query_arg( 'bulk_wp_strip_img_meta_err', $redirect_url );
		$redirect_url = add_query_arg( 'bulk_wp_strip_img_meta', count( $ids ), $redirect_url );
		return $redirect_url;
	}

	/**
	 * Given an attachment ID, fetch the path for that image and all paths for generated subsizes.
	 * Assumes that all subsizes are stored in the same directory as the passed attachment $id.
	 *
	 * @todo It'dimensions be nice if there was a more accurate way to discern the path for each generated subsize.
	 *
	 * @param int $id The attachment ID.
	 *
	 * @return array A unique array of image paths.
	 */
	public static function get_all_paths_for_image( $id ) {
		$paths = array();

		$attachment_path = wp_get_original_image_path( $id ); // The server path to the attachment.
		$attachment_meta = wp_get_attachment_metadata( $id ); // Array that contains all the subsize file names.
		$dir             = dirname( $attachment_path );

		$paths[] = $attachment_path; // The attachment $id path.

		if ( ! empty( $attachment_meta['file'] ) ) {
			// Includes a "scaled" image: https://make.wordpress.org/core/2019/10/09/introducing-handling-of-big-images-in-wordpress-5-3/ .
			$paths[] = $dir . '/' . basename( $attachment_meta['file'] );
		}

		if ( ! empty( $attachment_meta['original_image'] ) ) {
			$paths[] = $dir . '/' . $attachment_meta['original_image'];
		}

		if ( ! empty( $attachment_meta['sizes'] ) ) {
			foreach ( $attachment_meta['sizes'] as $size ) {
				$paths[] = $dir . '/' . $size['file'];
			}
		}

		$paths = array_unique( $paths );

		return $paths;
	}

	/**
	 * Utility error logger.
	 *
	 * @param string $msg The error message.
	 *
	 * @return void
	 */
	public static function logger( $msg ) {
		$settings = self::get_plugin_settings();
		$logging  = array_key_exists( 'logging', $settings ) ? $settings['logging'] : 'disabled';

		if ( $logging === 'enabled' ) {
			error_log( $msg ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Cleanup for plugin uninstall.
	 *
	 * @return void
	 */
	public static function plugin_cleanup() {
		delete_option( 'wp_strip_image_metadata_settings' );
	}
}

WP_Strip_Image_Metadata::init();
