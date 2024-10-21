<?php
/**
 * Plugin Name: Strip Image Metadata for JPG and WEBP
 * Plugin URI: https://github.com/MartinvonBerg/wp-strip-image-metadata
 * Description: Strip image metadata from JPGs and WEBPs on upload or via bulk action, and view image EXIF data.
 * Version: 1.4.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Martin von Berg
 * Author URI: https://www.berg-reise-foto.de/software-wordpress-lightroom-plugins/wordpress-plugins-fotos-und-gpx/
 * License: GPL-2.0
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-strip-image-metadata
 * Domain Path: /languages
 *
 * @package WP_Strip_Image_Metadata
 */

// TBD: Is it useful? clean WP database as well! except title! Mind Description : contains SRCSET. alt-text, caption, Description. And other metadata of image. No Problem to keep this in database.


namespace mvbplugins\stripmetadata;

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/inc/extractMetadata.php';
require_once __DIR__ . '/inc/implode-all.php';

// load textdomain
add_action( 'init', '\mvbplugins\stripmetadata\wp_strip_meta_load_textdomain');

/**
 * Load the translation for the locale
 *
 * @return bool
 */
function wp_strip_meta_load_textdomain() :bool {
	$dir = dirname(plugin_basename(__FILE__)) . \DIRECTORY_SEPARATOR . 'languages';
	$result = load_plugin_textdomain( 'wp-strip-image-metadata', false, $dir ); 
	return $result;
}


/**
 * WP_Strip_Image_Metadata main class.
 */
final class WP_Strip_Image_Metadata {

	/**
	 * Image file types to strip metadata from.
	 * Modify types with the 'wp_strip_image_metadata_image_file_types' filter hook.
	 *
	 * @var array<string>
	 */
	public static array $image_file_types = [
		'image/jpg',
		'image/jpeg',
	];

	/**
	 * empty placeholder for the version
	 *
	 * @var string
	 */
	public static string $versionString = '';

	/**
	 * Initialize plugin hooks and resources.
	 *
	 * @return void
	 */
	public static function init() :void {
		add_action( 'admin_menu', array( __CLASS__, 'menu_init' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings_init' ) );
		add_action( 'wp_rest_mediacat_upload', array( __CLASS__, 'strip_meta_after_rest_mediacat'), 10, 2 );
		add_filter( 'wp_generate_attachment_metadata', array(__CLASS__,'strip_meta_after_generate_attachment_metadata'), 10, 3 );
		add_filter( 'bulk_actions-upload', array( __CLASS__, 'register_bulk_strip_action' ) );
		add_filter( 'handle_bulk_actions-upload', array( __CLASS__, 'handle_bulk_strip_action' ), 10, 3 );
		self::admin_notices();
		register_uninstall_hook( __FILE__, array( __CLASS__, 'plugin_cleanup' ) );
	}

	// ----------------------------------------------------------------------
	/**
	 * Register the submenu plugin item under WP Admin Settings.
	 *
	 * @return void
	 */
	public static function menu_init() :void {
		add_options_page(
			__( 'Strip Image Metadata', 'wp-strip-image-metadata' ),
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
	public static function plugin_settings_page() :void {
		$image_lib = self::has_supported_image_library();
		$pathToCopyrightFile_webp = __DIR__ . \DIRECTORY_SEPARATOR . 'images' . \DIRECTORY_SEPARATOR . 'copyright.webp';
		$pathToCopyrightFile_jpg = __DIR__ . \DIRECTORY_SEPARATOR . 'images' . \DIRECTORY_SEPARATOR . 'copyright.jpg';
		$pathToCopyrightFile_avif = __DIR__ . \DIRECTORY_SEPARATOR . 'images' . \DIRECTORY_SEPARATOR . 'copyright.avif';
		$exif_jpg = \is_file( $pathToCopyrightFile_jpg) ? \mvbplugins\stripmetadata\getJpgMetadata( $pathToCopyrightFile_jpg) : [];
		$exif_webp = \is_file( $pathToCopyrightFile_webp) ? \mvbplugins\stripmetadata\getWebpMetadata( $pathToCopyrightFile_webp) : [];
		$exif_avif = \is_file( $pathToCopyrightFile_avif) ? \mvbplugins\stripmetadata\getAvifMetadata( $pathToCopyrightFile_avif) : [];
		$exif_to_print = ['artist', 'copyright', 'credit'];

		?>
		<div id="wp_strip_image_metadata" class="wrap">
			<h1><?php esc_html_e( 'Strip Image Metadata', 'wp-strip-image-metadata' ); ?></h1>

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
			if ( ! empty($image_lib) ) {
				echo esc_html(
					sprintf(
					/* translators: %s is the image processing library name and version active on the site */
						__( 'Compatible image processing library active: %s. PHP-Library is: %s', 'wp-strip-image-metadata' ),
						$image_lib . ' ' . phpversion( $image_lib ), self::$versionString
					)
				);
				self::$image_file_types = array_unique( self::$image_file_types );
				$formats = implode_all(', ', self::$image_file_types);
				$formats = str_replace( 'image/', '', $formats );
				$formats = strtoupper( $formats );
				?>
				<br>
				<?php
				esc_html_e( 'Supported image formats: ', 'wp-strip-image-metadata' );
				/* translators: The $formats string is a list of supported image formats and therefore not to translate. */
				echo esc_html( $formats );
				
			} else {
				esc_html_e( 'WP Strip Image Metadata: compatible image processing library not found. This plugin requires the "Imagick" or "Gmagick" PHP extension to function - please ask your webhost or system administrator if either can be enabled.', 'wp-strip-image-metadata' );
			}
			?>

			<h2><?php esc_html_e( 'Copyright Information of available Files', 'wp-strip-image-metadata' ); ?></h2>
			<h4><?php esc_html_e( '- in copyright.jpg', 'wp-strip-image-metadata' ); ?></h4>

			<p>
			<?php
			if ( \is_file( $pathToCopyrightFile_jpg) ) {
				echo \esc_attr( 'Path: ' . $pathToCopyrightFile_jpg);
			} else {
				esc_html_e( 'File copyright.jpg not found', 'wp-strip-image-metadata' );
			}
			?>
			</p>

			<?php
			foreach ( $exif_to_print as $key) {
				if (\key_exists($key, $exif_jpg)) {
					?><p><?php echo esc_attr( $key . ' : ' . $exif_jpg[$key] ); ?></p><?php
				}
			};
			?>

			<h4><?php esc_html_e( '- in copyright.webp', 'wp-strip-image-metadata' ); ?></h4>

			<p>
			<?php
			if ( \is_file( $pathToCopyrightFile_webp ) ) {
				echo \esc_attr( 'Path: ' . $pathToCopyrightFile_webp );
			} else {
				esc_html_e( 'File copyright.jpg not found', 'wp-strip-image-metadata' );
			}
			?>
			</p>

			<?php
			foreach ( $exif_to_print as $key) {
				if (\key_exists($key, $exif_webp)) {
					?><p><?php echo esc_attr( $key . ' : ' . $exif_webp[$key] ); ?></p><?php
				}
			};
			?>

			<h4><?php esc_html_e( '- in copyright.avif', 'wp-strip-image-metadata' ); ?></h4>

			<p>
			<?php
			if ( \is_file( $pathToCopyrightFile_avif) ) {
				echo \esc_attr( 'Path: ' . $pathToCopyrightFile_avif);
			} else {
				esc_html_e( 'File copyright.avif not found', 'wp-strip-image-metadata' );
			}
			?>
			</p>

			<?php
			foreach ( $exif_to_print as $key) {
				if (\key_exists($key, $exif_avif)) {
					?><p><?php echo esc_attr( $key . ' : ' . $exif_avif[$key] ); ?></p><?php
				}
			};
			?>

		</div>
		<?php
	}

	/**
	 * Register the plugin settings.
	 *
	 * @return void
	 */
	public static function settings_init() :void {
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
			['strip_active'],
		);

		add_settings_field(
			'wp_strip_image_metadata_setting_preserve_icc',
			__( 'Preserve ICC Color Profile', 'wp-strip-image-metadata' ),
			array( __CLASS__, 'setting_output' ),
			'wp_strip_image_metadata',
			'wp_strip_image_metadata_settings_section',
			['preserve_icc'],
		);

		add_settings_field(
			'wp_strip_image_metadata_setting_preserve_orientation',
			__( 'Preserve Image Orientation', 'wp-strip-image-metadata' ),
			array( __CLASS__, 'setting_output' ),
			'wp_strip_image_metadata',
			'wp_strip_image_metadata_settings_section',
			['preserve_orientation'],
		);
		
		add_settings_field(
			'wp_strip_image_metadata_setting_sizelimit',
			__( 'Set Size Limit', 'wp-strip-image-metadata' ),
			array( __CLASS__, 'setting_output' ),
			'wp_strip_image_metadata',
			'wp_strip_image_metadata_settings_section',
			['sizelimit'],
		);
		
		add_settings_field(
			'wp_strip_image_metadata_setting_set_copyright',
			__( 'Set / Keep Copyright', 'wp-strip-image-metadata' ),
			array( __CLASS__, 'setting_output' ),
			'wp_strip_image_metadata',
			'wp_strip_image_metadata_settings_section',
			['set_copyright'],
		);

		add_settings_field(
			'wp_strip_image_metadata_setting_logging',
			__( 'Log Errors', 'wp-strip-image-metadata' ),
			array( __CLASS__, 'setting_output' ),
			'wp_strip_image_metadata',
			'wp_strip_image_metadata_settings_section',
			['logging'],
		);

		register_setting( 'wp_strip_image_metadata_settings', 'wp_strip_image_metadata_settings', $args );
	}

	/**
	 * The default plugin settings option values.
	 *
	 * @return array<string, int|string>
	 */
	public static function default_plugin_settings() :array {
		return array(
			'strip_active'         => 'disabled',
			'preserve_icc'         => 'disabled',
			'preserve_orientation' => 'disabled',
			'logging'              => 'disabled',
			'sizelimit'            => 10000,
			'set_copyright'        => 'disabled', // keep or event set copyright if not set
		);
	}

	/**
	 * Set the default plugin settings option.
	 *
	 * @return void
	 */
	public static function set_default_plugin_settings() :void {
		update_option(
			'wp_strip_image_metadata_settings',
			self::default_plugin_settings(),
			false,
		);
	}

	/**
	 * Retrieves the plugin settings option value. Sets the option if it doesn't exist.
	 *
	 * @return array<string, int|string>
	 */
	public static function get_plugin_settings() :array {
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
	 * @param array<string, int|string> $input Received settings to sanitize.
	 *
	 * @return array<string, int|string> Sanitized settings saved.
	 */
	public static function sanitize_settings( array $input ) :array {
		return array(
			'strip_active'         => $input['strip_active'] === 'disabled' ? 'disabled' : 'enabled',
			'preserve_icc'         => $input['preserve_icc'] === 'disabled' ? 'disabled' : 'enabled',
			'preserve_orientation' => $input['preserve_orientation'] === 'disabled' ? 'disabled' : 'enabled',
			'logging'              => $input['logging'] === 'disabled' ? 'disabled' : 'enabled',
			'sizelimit'            => $input['sizelimit'],
			'set_copyright'        => $input['set_copyright'] === 'disabled' ? 'disabled' : 'enabled',
		);
	}

	/**
	 * Plugin settings section text output.
	 *
	 * @return string
	 */
	public static function settings_section_text() :string {
		return '';
	}

	/**
	 * Settings field callback.
	 *
	 * @param array $setting The setting to output HTML for.
	 *
	 * @return void
	 */
	public static function setting_output( array $setting ) :void {
		$settings      = self::get_plugin_settings();
		$setting_value = $settings[ $setting[0] ];

		if ( $setting[0] !== 'sizelimit') {
			// Radio button options.
			$items = array( 'enabled', 'disabled' );

			foreach ( $items as $item ) {
				?>
					<label>
						<input
							type="radio"
							<?php echo esc_attr( checked( $setting_value, $item, false ) ); ?>
							value="<?php echo esc_attr( $item ); ?>"
							name="<?php echo esc_attr( "wp_strip_image_metadata_settings[${setting[0]}]" ); ?>"
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
		} else {
			// output the number input for sizelimit.
			?>
			<input type="number" min="0" max="10000" step="1" 
				name="<?php echo esc_attr( "wp_strip_image_metadata_settings[${setting[0]}]" ); ?>"
				id="<?php echo esc_attr( "wp_strip_image_metadata_settings[${setting[0]}]" ); ?>" 
				value="<?php echo esc_attr( $setting_value ); ?>">
			<label>Min: 0, Max: 10000. <?php esc_html_e('Set the Maximum Width of Image for Stripping Metadata. 0 means stripping no image at all. 10000 means stripping all images','wp-strip-image-metadata' );?>.</label>
			<?php
		}
	}

	// ---------------------------------------------------------------------
	/**
	 * Add hooks for various admin notices.
	 *
	 * @return void
	 */
	public static function admin_notices() :void {
		$settings = self::get_plugin_settings();

		// If no supported image processing libraries are found, show an admin notice and bail.
		if ( self::has_supported_image_library() === '' ) {
			add_action(
				'admin_notices',
				function () {
					?>
				<div class="notice notice-error is-dismissible">
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
					<div class="notice notice-warning is-dismissible">
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
			function () use ( $settings ) {
				$post     = isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0;
				$is_image = wp_attachment_is_image( $post );
				$mime = \get_post_mime_type( $post );
				$pathToOriginalImage = wp_get_original_image_path( $post );
				$exif = [];
				$paths = [];
				$paths = array_merge( $paths, self::get_all_paths_for_image( $post ) );

				// single stripping for image if button was clicked
				// check the nonce before. Just not in if() for readability.
				if ( isset( $_GET['wp_nonce'] ) ) {
					$checkNonce = \wp_verify_nonce( $_GET['wp_nonce'], 'strip_meta_button' . $_GET['action'] . $_GET['post']) === 1;
				} else {
					$checkNonce = false;
				}

				if (isset( $_POST['strip_meta_button']) && $settings['strip_active'] !== 'disabled' && $checkNonce ) {
					self::strip_meta_after_rest_mediacat( $post, 'context-rest-upload');
					self::logger( 'WP Strip Image Metadata: Stripped with Button');
				}

				// sanitize jpg mime type.
				if ( $mime === 'image/jpg') { $mime = 'image/jpeg'; }

				// get EXIF-Data from images
				if ( $is_image && $pathToOriginalImage !== false && $mime === 'image/jpeg' ) {

					try {
						$exif = \mvbplugins\stripmetadata\getJpgMetadata( $pathToOriginalImage );
					} catch ( \Exception $e ) {
						self::logger( 'WP Strip Image Metadata: error reading jgp-EXIF data: ' . $e->getMessage() );
					}

				} elseif ( $is_image && $pathToOriginalImage !== false  && $mime === 'image/webp' ) {

					try {
						$exif = \mvbplugins\stripmetadata\getWebpMetadata( $pathToOriginalImage );
					} catch ( \Exception $e ) {
						self::logger( 'WP Strip Image Metadata: error reading webp-EXIF data: ' . $e->getMessage() );
					}

				} elseif ( $is_image && $pathToOriginalImage !== false  && $mime === 'image/avif' ) {

					try {
						$exif = \mvbplugins\stripmetadata\getAvifMetadata( $pathToOriginalImage );
					} catch ( \Exception $e ) {
						self::logger( 'WP Strip Image Metadata: error reading webp-EXIF data: ' . $e->getMessage() );
					}

				}
				else {return;}

				$allsizes = '';
				foreach ( $paths as $key => $path) {
					if ( $mime === 'image/jpeg' ) {
						$exifData = \mvbplugins\stripmetadata\getJpgMetadata( $path );
						if ( \mvbplugins\stripmetadata\implode_all( ' ', $exifData) === " -- -- -- -- ---    0 notitle     ") {$exifData = '';}; 
					} elseif ( $mime === 'image/webp' ) {
						$exifData = \mvbplugins\stripmetadata\getWebpMetadata( $path );
					} elseif ( $mime === 'image/avif' ) {
						$exifData = \mvbplugins\stripmetadata\getAvifMetadata( $path );
					}
					else { $exifData = []; }
	
					$filesize = self::filesize_formatted( $path);
					$size = \strlen( \mvbplugins\stripmetadata\implode_all( ' ', $exifData ) );
					$allsizes = $allsizes . $size . ' / ';
					$paths[ $key ] = __('Meta Size','wp-strip-image-metadata') . ' : ' . strval($size) . ' ' . __(' and filesize','wp-strip-image-metadata') . ' : ' . $filesize  . __(' of ','wp-strip-image-metadata') . $paths[ $key ];
				}
				sort( $paths );

				//$exifAsStringLength = \strlen( \mvbplugins\stripmetadata\implode_all( ' ', $exif ) );
				$exifAsStringLength = rtrim($allsizes,' /') . ' ' . __('Meta Size','wp-strip-image-metadata') . ' in Bytes.';
				if ( \mvbplugins\stripmetadata\implode_all( ' ', $exif) === " -- -- -- -- ---    0 notitle     ") {$exif = '';};
				
				$current_uri = add_query_arg( NULL, NULL );
				if ( isset( $_POST['strip_meta_button']) ) {
					// strip metadata here and print success on success.
					$exifAsStringLength = $exifAsStringLength . ' ' . __('Stripped','wp-strip-image-metadata') . '!';
					self::logger('Button: '. $current_uri .' '. $exifAsStringLength);
				} else {
					$nonce = \wp_create_nonce( 'strip_meta_button' . 'edit' . strval($post));
					$current_uri = add_query_arg( 'wp_nonce', $nonce);
				}

				add_action(
					'admin_notices',
					function () use ( $exif, $exifAsStringLength, $paths, $current_uri ) {
						?>
				<div class="notice notice-info is-dismissible">
					<details style="padding-top:8px;padding-bottom:8px;">
						<summary>
							<?php esc_html_e( 'WP Strip Image Metadata: expand for image EXIF data. Length : ', 'wp-strip-image-metadata' ); echo esc_attr($exifAsStringLength) ?>
							<form action="<?php echo esc_attr( $current_uri ) ?>" method="POST">
								<input type="submit" name="strip_meta_button" id="strip_meta_button" value="<?php \esc_html_e('Strip Metadata','wp-strip-image-metadata')?>" class="button" style="margin-top: 8px;" /><br/>
							</form>
						</summary>
						<div>
							<?php
								/** @phpstan-ignore-next-line */
							echo '<p>'; esc_html( print_r( $exif ) ); echo '</p>'; 
							foreach ( $paths as $path) {
								/** @phpstan-ignore-next-line */
								echo '<p>'; esc_html( print_r( $path ) ); echo '</p>'; 
							}
							?>
						</div>
					</details>
				</div>
						<?php
					}
				);
			}
		);

		// When using the custom bulk strip image metadata action, show how many images were modified.
		$img_count = isset( $_GET['bulk_wp_strip_img_meta'] ) ? intval( $_GET['bulk_wp_strip_img_meta'] ) : null;
		$path_count = isset( $_GET['bulk_wp_strip_overall'] ) ? intval( $_GET['bulk_wp_strip_overall'] ) : null;
		$stripped_count = isset( $_GET['bulk_wp_strip_number_stripped'] ) ? intval( $_GET['bulk_wp_strip_number_stripped'] ) : null;

		if ( $img_count ) {
			add_action(
				'admin_notices',
				function () use ( $img_count, $path_count, $stripped_count ) {
					?>
				<div class="notice notice-success is-dismissible">
					<p>
					<?php
						/* translators: placeholders are the number of images processed with the bulk action */
						printf(
							_n( 'WP Strip Image Metadata: %s image including',
								'WP Strip Image Metadata: %s images including',
								$img_count, 
								'wp-strip-image-metadata'),
							$img_count, 
						);
						echo esc_attr( ' ' . $stripped_count . ' / ' . $path_count . ' '); 
						echo esc_attr( _n('subsizes was stripped',
										'subsizes were stripped',
										$img_count, 
										'wp-strip-image-metadata')
						);
					?>
					</p>
				</div>
					<?php
				}
			);
		}

		// When the bulk strip action can't find one of the image file paths, show an error notice.
		$bulk_error = isset( $_GET['bulk_wp_strip_img_meta_err'] ) ? intval( $_GET['bulk_wp_strip_img_meta_err'] ) : null;
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
	 * Check for a supported image processing library on the site.
	 *
	 * @return string The supported image library to use, or false if no support found.
	 */
	public static function has_supported_image_library() :string {
		
		// Test for Imagick support.
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick', false ) ) {
			
			$imagick = new \Imagick(); 
			$formats = $imagick->queryFormats(); // it is intentionally not to use Imagick::queryFormats() statically
			$formats = array_map( 'strtolower', $formats );
			$pos = \stripos( implode_all(' ', $formats), 'webp');
			if ( $pos > 1) { self::$image_file_types[] = 'image/webp';}

			$pos = \stripos( implode_all(' ', $formats), 'avif'); 
			if ( $pos > 1) { self::$image_file_types[] = 'image/avif';} 

			self::$versionString = $imagick->getVersion()['versionString']; // it is intentionally not to use Imagick::getVersion statically
			$imagick->clear();
			return 'Imagick';
		}

		// Test for Gmagick support.
		if ( extension_loaded( 'gmagick' ) && class_exists( 'Gmagick', false ) ) {
			
			$imagick = new \Gmagick();
			$formats = $imagick->queryFormats(); // it is intentionally not to use Imagick::queryFormats() statically
			$formats = array_map( 'strtolower', $formats );
			$pos = \stripos( implode_all(' ', $formats), 'webp');
			if ( $pos > 1) { self::$image_file_types[] = 'image/webp'; }
			
			$pos = \stripos( implode_all(' ', $formats), 'avif'); 
			if ( $pos > 1) { self::$image_file_types[] = 'image/avif';}

			self::$versionString = $imagick->getVersion()['versionString']; // it is intentionally not to use Imagick::getVersion statically
			$imagick->clear();
			return 'Gmagick';
		}

		return '';
	}

	/**
	 * Strip metadata from an image.
	 *
	 * @param string $file The file path (not URL) to an uploaded media item.
	 *
	 * @return bool the result as boolean. True on success.
	 */
	public static function strip_image_metadata( string $file ) :bool {
		$mime = \mvbplugins\stripmetadata\_mime_content_type( $file );
		$result = false;

		// Check for supported file type.
		if ( ! in_array( $mime, self::$image_file_types, true ) ) {
			return false;
		} elseif ( $mime === 'image/jpg') {
			$mime = 'image/jpeg';
		}
		// check for image converter support
		$img_lib = self::has_supported_image_library();
		if ( empty( $img_lib ) ) {
			self::logger( 'WP Strip Image Metadata: No Image Handler defined' );
			return false;
		}

		$settings             = self::get_plugin_settings();
		$preserve_icc         = array_key_exists( 'preserve_icc', $settings ) ? $settings['preserve_icc'] : 'enabled';
		$preserve_orientation = array_key_exists( 'preserve_orientation', $settings ) ? $settings['preserve_orientation'] : 'enabled';
		$keepCopyright        = array_key_exists( 'set_copyright', $settings ) ? $settings['set_copyright'] : 'enabled';
		$sizeLimit            = array_key_exists( 'sizelimit', $settings ) ? \intval( $settings['sizelimit']) : 0;

		// Using the Imagick or Gmagick library for jpegs and webps.
		if ( $img_lib === 'Gmagick' ) {
			// Using the Gmagick library. 

			// Open the copyright image with the correct EXIF data
			if ($mime === 'image/jpeg') {
				$pathToTemplateFile = __DIR__ . \DIRECTORY_SEPARATOR . 'images' . \DIRECTORY_SEPARATOR . 'copyright.jpg';
			} elseif ($mime === 'image/webp') {
				$pathToTemplateFile = __DIR__ . \DIRECTORY_SEPARATOR . 'images' . \DIRECTORY_SEPARATOR . 'copyright.webp';
			} elseif ($mime === 'image/avif') {
				$pathToTemplateFile = __DIR__ . \DIRECTORY_SEPARATOR . 'images' . \DIRECTORY_SEPARATOR . 'copyright.avif';
			}
			else { $pathToTemplateFile = ''; }

			if (!\file_exists($pathToTemplateFile)) {
				self::logger('WP Strip Image Metadata: File ' . $pathToTemplateFile . ' not found. Skipping Strip-Metadata.');
				return false;
			}

			// Open the image to alter and get its size
			try {
				$imageFile = new \Gmagick( $file );
			} catch ( \Exception $e ) {
				self::logger( 'WP Strip Image Metadata: error while opening image path using Gmagick: ' . $e->getMessage() );
				return false;
			};

			$width = $imageFile->getimagewidth();
			$height = $imageFile->getimageheight();

			// do only for all images smaller than $sizeLimit. So $sizeLimit = 0 means no image at all. But $sizeLimit = 10000 means all images.
			if ($width <= $sizeLimit) {
						
				$icc_profile = null;
				// $orientation = null; @todo: currently not capturing orientation via Gmagick.

				// Capture ICC profile if preferred.
				if ( $preserve_icc === 'enabled' ) {
					try {
						$icc_profile = $imageFile->getimageprofile( 'icc' );
					} catch ( \Exception $e ) { 
						// May not be set, ignore.
					}
				}

				// Capture image orientation if preferred.
				// @todo Unlike Imagick, there isn't an equivalent getImageOrientation() helper.
				// Check into grabbing the orientation a different way.

				// Strip the metadata.
				try {
					$imageFile->stripimage();
				} catch ( \Exception $e ) {
					self::logger( 'WP Strip Image Metadata: error while stripping image metadata using Gmagick: ' . $e->getMessage() );
				}

				// Add back $icc_profile if present.
				if ( $icc_profile !== null ) {
					try {
						$imageFile->setimageprofile( 'icc', $icc_profile );
					} catch ( \Exception $e ) {
						self::logger( 'WP Strip Image Metadata: error while setting ICC profile using Gmagick: ' . $e->getMessage() );
					}
				}

				// Add back $orientation if present.
				// @todo: currently not capturing orientation via Gmagick.

				if ($keepCopyright === 'enabled') {
					// generate image with copyright information
					// source: https://stackoverflow.com/questions/37791236/add-copyright-string-to-jpeg-using-imagemagick-imagick-in-php
					try {
						$templateFile = new \Gmagick($pathToTemplateFile);

						// Resize the copyright and composite the image over the top
						$templateFile->resizeImage($width, $height, \Gmagick::FILTER_POINT, 1);

						// Set compression Quality and generate the image
						//$compressionQual = $imageFile->getCompressionQuality();
						//$templateFile->setCompressionQuality($compressionQual);
						$templateFile->compositeImage($imageFile, \Gmagick::COMPOSITE_REPLACE, 0, 0);

						// set profile and orientation
						if ($icc_profile !== null) {
							$templateFile->setImageProfile('icc', $icc_profile);
						}
						//if ($orientation) {
						//	$templateFile->setImageOrientation($orientation);
						//}

						// write the new file
						$templateFile->writeImage($file);
						$templateFile->destroy();
						$result = true;

					} catch (\Exception $e) {
						self::logger('WP Strip Image Metadata: error while using Copyright file for image file using Imagick: ' . $e->getMessage());
					}
				} else {
					// Overwrite the image file path, including any metadata modifications made.
					try {
						$imageFile->writeImage($file);
						$result = true;
					} catch (\Exception $e) {
						self::logger('WP Strip Image Metadata: error while overwriting image file using Imagick: ' . $e->getMessage());
					} 
				}
				
			}

			// Free $gmagick object.
			$imageFile->destroy();
			return $result;

		} elseif ( $img_lib === 'Imagick' ) {

			// Open the copyright image with the correct EXIF data
			if ($mime === 'image/jpeg') {
				$pathToTemplateFile = __DIR__ . \DIRECTORY_SEPARATOR . 'images' . \DIRECTORY_SEPARATOR . 'copyright.jpg';
			} elseif ($mime === 'image/webp') {
				$pathToTemplateFile = __DIR__ . \DIRECTORY_SEPARATOR . 'images' . \DIRECTORY_SEPARATOR . 'copyright.webp';
			} elseif ($mime === 'image/avif') {
				$pathToTemplateFile = __DIR__ . \DIRECTORY_SEPARATOR . 'images' . \DIRECTORY_SEPARATOR . 'copyright.avif';
			}
			else { $pathToTemplateFile = '';}

			if (!\file_exists($pathToTemplateFile)) {
				self::logger('WP Strip Image Metadata: File ' . $pathToTemplateFile . ' not found. Skipping Strip-Metadata.');
				return false;
			}

			// Open the image to alter and get its size
			$imageFile = new \Imagick($file);
			$dimensions = $imageFile->getImageGeometry();
			$width = $dimensions['width'];
			$height = $dimensions['height'];

			// do only for all images smaller than $sizeLimit. So $sizeLimit = 0 means no image at all. But $sizeLimit = 10000 means all images.
			if ($width <= $sizeLimit) {

				$icc_profile = null;
				$orientation = null;

				// Capture ICC profile if preferred.
				if ($preserve_icc === 'enabled') {
					try {
						$icc_profile = $imageFile->getImageProfile('icc');
					} catch (\Exception $e) {
						// May not be set, ignore.
					}
				}

				// Capture image orientation if preferred. \Imagick::ORIENTATION_UNDEFINED = 0 : is undefined, so it is not written.
				if ($preserve_orientation === 'enabled') {
					try {
						$orientation = $imageFile->getImageOrientation();
					} catch (\Exception $e) {
						// May not be set, ignore.
					}
				}

				// Strip the metadata.
				try {
					$imageFile->stripImage();
				} catch (\Exception $e) {
					self::logger('WP Strip Image Metadata: error while stripping image metadata using Imagick: ' . $e->getMessage());
				}

				// Add back $icc_profile if present.
				if ($icc_profile !== null) {
					try {
						$imageFile->setImageProfile('icc', $icc_profile);
					} catch (\Exception $e) {
						self::logger('WP Strip Image Metadata: error while setting ICC profile using Imagick: ' . $e->getMessage());
					}
				}

				// Add back $orientation if present. \Imagick::ORIENTATION_UNDEFINED = 0 : is undefined and 0 = false!
				if ($orientation) {
					try {
						$imageFile->setImageOrientation($orientation);
					} catch (\Exception $e) {
						self::logger('WP Strip Image Metadata: error while setting image orientation using Imagick: ' . $e->getMessage());
					}
				}

				if ($keepCopyright === 'enabled') {
					// generate image with copyright information
					// source: https://stackoverflow.com/questions/37791236/add-copyright-string-to-jpeg-using-imagemagick-imagick-in-php
					try {
						$templateFile = new \Imagick($pathToTemplateFile);

						// Resize the copyright and composite the image over the top
						$templateFile->resizeImage($width, $height, \imagick::FILTER_POINT, 1.0);

						// Set compression Quality and generate the image
						$compressionQual = $imageFile->getCompressionQuality();
						$templateFile->setCompressionQuality($compressionQual);
						$templateFile->compositeImage($imageFile, \imagick::COMPOSITE_SRCOVER, 0, 0);

						// set profile and orientation
						if ($icc_profile !== null) {
							$templateFile->setImageProfile('icc', $icc_profile);
						}
						if ($orientation) {
							$templateFile->setImageOrientation($orientation);
						}

						// write the new file
						$result = $templateFile->writeImage($file);
						$result = $result === true;
						$templateFile->clear();

					} catch (\Exception $e) {
						self::logger('WP Strip Image Metadata: error while using Copyright file for image file using Imagick: ' . $e->getMessage());
					}
				} else {
					// Overwrite the image file path, including any metadata modifications made.
					try {
						$result = $imageFile->writeImage($file);
						$result = $result === true;
					} catch (\Exception $e) {
						self::logger('WP Strip Image Metadata: error while overwriting image file using Imagick: ' . $e->getMessage());
					}
				}
			}
			// clear imagick
			$imageFile->clear();
			return $result;
			
		} 
		return false;
	
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
	public static function strip_meta_after_generate_attachment_metadata( array $metadata, int $attachment_id, string $context ) :array {
		// loop through images
		$paths = self::get_all_paths_for_image( $attachment_id );
		foreach ( $paths as $file) {
			self::strip_image_metadata( $file );
		}
		// returning unchanged $metadata is required for this filter
		return $metadata;
	}

	/**
	 * Function for `wp_rest_mediacat_upload` action-hook. Strip metadata from files according to settings after files were uploaded via rest-api. 
	 * 
	 * @param int    $attachment_id Current attachment ID.
	 * @param string $context       context. Shall be 'context-rest-upload' when files were uploaded via rest-api.
	 *
	 * @return void
	 */
	public static function strip_meta_after_rest_mediacat( int $attachment_id, string $context ) :void {
		if ( $context !== 'context-rest-upload') {return;}

		// loop through images
		$paths = self::get_all_paths_for_image( $attachment_id );
		foreach ( $paths as $file) {
			self::strip_image_metadata( $file );
		}
	
	}
	
	/**
	 * Register a custom bulk action for the upload admin screen (works for list view only not grid).
	 *
	 * @param array $bulk_actions Registered actions.
	 *
	 * @return array
	 */
	public static function register_bulk_strip_action( array $bulk_actions ) :array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $bulk_actions;
		}

		$bulk_actions['wp_strip_image_metadata'] = __( 'Strip Image Metadata', 'wp-strip-image-metadata' );
		return $bulk_actions;
	}

	/**
	 * Handles the custom bulk strip image metadata action.
	 *
	 * @param string $redirect_url The redirect URL.
	 * @param string $action       The bulk action being taken.
	 * @param array<int>  $ids          The attachment IDs.
	 *
	 * @return string Redirect URL.
	 */
	public static function handle_bulk_strip_action( string $redirect_url, string $action, array $ids ) :string {
		if ( $action !== 'wp_strip_image_metadata' ) {
			return $redirect_url;
		}

		// verify the nonce for bulk-media
		if ( \wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-media' ) !== 1 ) {
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

		$nStripped = 0;

		foreach ( $paths as $path ) {
			$success = self::strip_image_metadata( $path );
			if ( $success) ++$nStripped;
		}

		// refine the success number
		$nIDs = count( $ids );
		$nPaths = count( $paths );

		$redirect_url = remove_query_arg( 'bulk_wp_strip_img_meta_err', $redirect_url );
		$redirect_url = add_query_arg( ['bulk_wp_strip_img_meta'=>$nIDs, 'bulk_wp_strip_overall'=>$nPaths, 'bulk_wp_strip_number_stripped'=> $nStripped], $redirect_url );
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
	 * @return array<string> A unique array of image paths.
	 */
	public static function get_all_paths_for_image( int $id ) :array {
		$paths = [];

		$attachment_path = wp_get_original_image_path( $id ); // The server path to the attachment.
		$attachment_meta = wp_get_attachment_metadata( $id ); // Array that contains all the subsize file names.
		if ( $attachment_path === false) { $attachment_path='';}
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
	public static function logger( string $msg ) :void {
		$settings = self::get_plugin_settings();
		$logging  = array_key_exists( 'logging', $settings ) ? $settings['logging'] : 'disabled';

		if ( $logging === 'enabled' ) {
			error_log( $msg );
		}
	}

	/**
	 * Cleanup for plugin uninstall.
	 *
	 * @return void
	 */
	public static function plugin_cleanup() :void {
		delete_option( 'wp_strip_image_metadata_settings' );
	}

	/**
	 * Provide a nicely formatted filesize.
	 *
	 * @source https://stackoverflow.com/questions/5501427/php-filesize-mb-kb-conversion stackoverflow-link.
	 * @param  string $path the full file-path
	 * @return string the nicely formatted filesize
	 */
	private static function filesize_formatted( string $path ) :string
	{
		$size = filesize($path);
		$units = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
		$power = $size > 0 ? floor( log($size, 1024) ) : 0;
		$formattedSize = number_format( $size / pow(1024, $power), 2, '.', ',' );
		$formattedUnit = $units[$power];
		return "{$formattedSize} {$formattedUnit}";
	}
}

WP_Strip_Image_Metadata::init();
