=== Strip Image Metadata for JPG and WEBP ===
Plugin Name: Strip Image Metadata for JPG and WEBP
Contributors: martinvonberg
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=CQA6XZ7LUMBJQ
Tags: metadata, image, exif, privacy, strip
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.4.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Strip Image Metadata for JPG and WEBP Files

== Description ==

This Plugin is based on the work of Samiff and the Plugin "WP Strip Image Metadata". If you are looking for a simple Plugin to strip all Metadata you should install this one. Available from the WordPress.org plugin repository here: https://wordpress.org/plugins/wp-strip-image-metadata/
This Plugin extends the Functionality of "WP Strip Image Metadata" with the following Functions:

= Extended Functionality =

-   Handle AVIF and WEBP-Images, too.
-   set / add / change Copyright, Artist or Credit Information in JPG and WEBP images Files.
-   Set an upper Size Limit for the Stripping. All Files with Width greater than Size Limit won't be stripped.
-   Show some more Information in the Image Edit Panel
-   Set a Minimum version for Imagick (3.4.4) and Gmagick (2.0.5) to handle files at all. Gmagick is still limited in functionality.

= Preparation of Copyright Template Files =

Both Imagick and Gmagick do not allow to set EXIF-Metadata directly. The author of this plugin does not know an Open Source PHP or WordPress Function with appropriate License to set metadata in an Image File. So a Template File is used that has to be prepared by the user. It's only possible to use one template File for
one Artist, only! The Plugin uses one Template File for the whole site. So, if you are on a Multi-User-Site this Plugin is not for you.

1.  Prepare a JPG, WEBP and AVIF File with very small size, e.g. 100x100 or so. Image dimensions do not matter, here.
2.  Strip all Metadata with exiftool (current version writes avif and webp, too):

``` 
exiftool.exe -all= ./yourfile.jpg -o copyright.jpg
exiftool.exe -all= ./anotherfile.webp -o copyright.webp
exiftool.exe -all= ./aviffile.jpg -o copyright.avif
```

3.  Add the Copyright (or other) EXIF-Metadata you prefer with Exiftool like so

```
exiftool.exe -copyright="Copyright by User of the Plugin and Site" ./copyright.jpg
exiftool.exe -artist="User of the Plugin and Site" ./copyright.jpg
```

The other functionality of the Plugin was not changed so the original Readme follows herafter.

= Original Description =

Strip image metadata on upload or via bulk action, and view image EXIF data.
WP Strip Image Metadata is a privacy focused WordPress plugin that helps in removing potentially sensitive metadata from your uploaded images.

= What is image metadata? =

Image metadata is extra information embedded in image files. This information is stored in a variety of formats and contains items like the model of the camera that took a photo.
However, image metadata may also contain identifying information such as the GPS location coordinates of an image taken with a smartphone for example.
This plugin provides an easy enabled/disabled setting so you can make the call on when image metadata should be removed.
**Note**: this plugin requires the "Imagick" or "Gmagick" PHP extension to function.

== Installation ==

1. Install the plugin through the WordPress plugins screen.
2. Activate the plugin on the WordPress plugins screen.

= Configuring =
EN: You configure the plugin in *Settings > Strip Image Metadata*.
DE: Die Konfiguration erfolgt in *Settings > Metadaten entfernen*.

== Frequently Asked Questions ==

### How will I know if I have the required Imagick or Gmagick extension on my site?

If you aren't sure, after installing the plugin, in WP Admin navigate to: Settings \> Strip Image Metadata

Under "Plugin Information" it will show if the required extension is active on the site or not and it the minimum required version is met.

If a compatible extension is not found, try asking your webhost or system administrator if either one can be enabled.

### Can I remove metadata from images uploaded before installing this plugin?

Yes, there is a bulk action included. In WP Admin navigate to the Media library and make sure you are using the List view.

Select which images you'd like to strip metadata from and then select the "WP Strip Image Metadata" bulk action.

This can be a resource intensive process, so please only select a handful of images at one time for processing.

### Will this work for all generated image subsizes (thumbnails)?

Yes, if metadata stripping is enabled then all generated subsizes, depending on Size Limit, at the time of upload will have the metadate removed.

The included bulk action will also remove metadata from all subsizes as well, depending on Size Limit.

### How do I know it's working?

In WP Admin, from your Media library you can select an image and click "Edit" (in List view) or "Edit more details" (in Grid view).

On the Edit page for the image, there will be an admin notice at the top with the "expand for image EXIF data" text. The Top Line shows, without expansion, the number of bytes for the different file sizes. This should work for jpg/jpeg, avif and webp files, but other image types may not display EXIF info. 

You might try uploading a test image with the "Image Metadata Stripping" setting disabled, and then the same image again with the setting enabled to view the difference.

Here are some good sample images for testing: https://github.com/ianare/exif-samples/tree/master/jpg/gps

Popular image editing applications such as Photoshop or Affinity Photo also have the capability to inspect image metadata which can prove useful. On the Commandline Exiftool or Exiv2 provide very good information.

### Once image metadata has been stripped, is it reversible?

No, removing image metadata is permanent. If you would like the metadata kept, you should keep an offsite backup copy of your images.

### What file types does this plugin work for?

By default the plugin will process jpg/jpeg, avif and webp files.

### What do the plugin settings do?

-   Image Metadata Stripping: whether image metadata is stripped from new uploads.
-   Preserve ICC Color Profile: whether to keep image color information which is helpful to some applications.
-   Preserve Image Orientation: whether to keep image orientation which can help rotate images as intended.
-   Set / Keep Copyright: whether to set / change or keep Copyright Information based on Template Files.
-   Size Limit: Only strip Images Files which width is SMALLER than size Limit.
-   Log Errors: whether to log error output which can be helpful for debugging purposes.

== Screenshots ==

1. Plugin settings page
2. Viewing image EXIF details
3. Bulk action for stripping image metadata

== Upgrade Notice ==
No need to upgrade right now, if you don't use Images in Avif-Format.

== Changelog ==

### 1.4.0 - 2024-10-21
- added AVIF-support, increased minimum PHP-version following the WP recommendation, 
- Minor PHP Bugfixes, e.g. updated focal length extraction for full-frame cameras.
- Changed the Imagick / GD check for supported files. Removed the version check completely.
- Tested with WordPress 6.6.2

### 1.3.0 - 2024-07-17
- Tested with WordPress 6.6. 

### 1.3.0 - 2024-04-03
- Tested with WordPress 6.5. 

### 1.2.0 - 2023-10-21
- Tested with WordPress 6.4. Bugfix for PHP 7.4 (mixed return type was wrong)

### 1.2.0 - 2023-08-09
- Tested with WordPress 6.3. First upload to Plugin-Direcotory.

### 1.2.0 - 2023-07-26
- Added checking of WP Nonces for security.

### 1.2.0 - 2023-07-26
- Formatting change for WPCS rules. Code change w/o functional change to follow plugin Plugin Rules. Mind: Semantic versioning will be changed correctly AFTER upload to the WP plugin directory.

### 1.2.0 - 2023-07-01
- Added a Button for stripping the image in media details view.

### 1.2.0 - 2023-07-01
- Updates for adding Plugin to Wordpress.org official directory. (Escaping for 4 echo added.). Updated this readme.

### 1.2.0 - 2023-05-02
- Updates for translation including bugfixes and German translation added.

### 1.1.0 - 2023-05-01
- Updates for phpstan check with level 8. Only 8 error messages remain, but these are due to inconstent type definitions in WP functions. No functional changes. Readme update.

### 1.0 - 2023-04-27
- Initial plugin release based on the work of Samiff
