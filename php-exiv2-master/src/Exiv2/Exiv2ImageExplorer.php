<?php
namespace Exiv2;

require EXIV2_BASE_PATH . "/src/Exiv2/Metadata/Exif/Exiv2MetadataExifImage.php";
require EXIV2_BASE_PATH . "/src/Exiv2/Metadata/Exif/Exiv2MetadataExifPhoto.php";
require EXIV2_BASE_PATH . "/src/Exiv2/Metadata/Exif/Exiv2MetadataExif.php";
require EXIV2_BASE_PATH . "/src/Exiv2/Metadata/Iptc/Exiv2MetadataIptc.php";
require EXIV2_BASE_PATH . "/src/Exiv2/Metadata/Xmp/Exiv2MetadataXmp.php";
require EXIV2_BASE_PATH . "/src/Exiv2/Metadata/Exiv2Metadata.php";

use Exiv2\Metadata\Exiv2Metadata;

class Exiv2ImageExplorer {
  const PM_SUMMARY = "s";
  const PM_EXIF_IPTC_XMP = "a";
  const PM_TRANSLATED_EXIF = "t";
  const PM_IPTC = "i";
  const PM_XMP = "x";

  private $env= '';
  private $_image;
  private $exiv2Version = '';


  private function checkExiv2Availability()
  {
    if (stripos(php_uname(), "windows") !== false) {
      $this->env = 'windows';
    } elseif (stripos(php_uname(), "linux") !== false) {
      $this->env = 'linux';
    } else {
      $this->env = '';
    }

    $version = $this->getExiv2Version();
    if ($version === null) {
      $this->env = '';
      return;
    } else {
      $this->exiv2Version = $version;
    }
  }
 

  public function __construct($image_file) {
    $this->_image = $image_file;
    $this->checkExiv2Availability();
  }

  private function parseSummary($raw_data) {
    $split_char = ":";

    $metadata = new Exiv2Metadata();

    foreach (explode("\n", $raw_data) as $line) {
      $key = trim(substr($line, 0, strpos($line, $split_char)));
      $value = trim(substr($line, strpos($line, $split_char) + 2));
      switch ($key) {
        case 'File name':
          $metadata->fileName = $value;
          break;
        case 'File size':
          $metadata->fileSize = $value;
          break;
        case 'MIME type':
          $metadata->mimeType = $value;
          break;
        case 'Image size':
          $metadata->imageSize = $value;
          break;
        case 'Camera make':
          $metadata->exif->image->make = $value;
          break;    
        case 'Camera model':
          $metadata->exif->image->model = $value;
          break;
        case 'Image timestamp':
          $metadata->exif->photo->dateTimeOriginal = $value;
          $metadata->xmp->createDate = $value;
          break;
        case 'Image number':
          $metadata->xmp->imageNumber = $value;
          break;
        case 'Exposure time':
          $metadata->exif->photo->exposureTime = $value;
          break;
        case 'Aperture':
          $metadata->exif->photo->apertureValue = $value;
          break;
        case 'Exposure bias':
          $metadata->exif->photo->exposureBiasValue = $value;
          break;
        case 'Flash':
          $metadata->exif->photo->flash = $value;
          break;
        case 'Focal length':
          $metadata->exif->photo->focalLength = $value;
          break;
        case 'Subject distance':
          $metadata->exif->photo->subjectDistanceRange = $value;
          break;
        case 'ISO speed':
          $metadata->exif->photo->isoSpeedRatings = $value;
          break;
        case 'Exposure mode':
          $metadata->exif->photo->exposureMode = $value;
          break;
        case 'Metering mode':
          $metadata->exif->photo->meteringMode = $value;
          break;
        case 'White balance':
          $metadata->exif->photo->whiteBalance = $value;
          break;
      }
    }

    return $metadata;
  }

  private function parseExifIptcXmp($raw_data) {
    $key_start = 0;
    $key_end = 44;
    $value_start = 60;

    $metadata = new Exiv2Metadata();

    foreach (explode("\n", $raw_data) as $line) {
      $key = trim(substr($line, $key_start, $key_end));
      $key = strtolower(substr($key, 0, strrpos($key, ".") + 1))
           . strtolower(substr($key, strrpos($key, ".") + 1, 1)) 
           . substr($key, strrpos($key, ".") + 2);
      $key = str_replace("envelope.", "", $key);
      $key = str_replace("application2.", "", $key);
      $key = str_replace("xmp.aux.", "xmp.", $key);
      $key = str_replace("xmp.xmp.", "xmp.", $key);
      $key = str_replace("iSOSpeed", "isoSpeed", $key);
      $key = str_replace("jPEG", "jpeg", $key);
      $key = str_replace("gPS", "gps", $key);
      $key = str_replace("iCCProfile", "iccProfile", $key);
      $value = trim(substr($line, $value_start));
      foreach (explode(".", $key) as $k) {
        if ($k == "") continue;
        if (!isset($x)) {
          $x = &$metadata->{$k};
        } else {
          $x = &$x->{$k};
        }
      }
      if (stripos($key, "keywords") !== false) {
        $x[] = $value;
      } else {
        $x = $value;
      }
      unset($x);
    }

    return $metadata;
  }

  public function getMetadata($mode = self::PM_SUMMARY) {
    $parsed = (object)[];

    if ( $this->env === 'windows' ) {
      $cmd = EXIV2_BASE_PATH . "/bin/win/exiv2.exe -p". $mode . " " . $this->_image;
      $raw_data = shell_exec($cmd);
    } else if ( $this->env === 'linux' ) {
      // TODO: Add Linux call
    } else {
      return $parsed;
    }

    switch ($mode) {
      case self::PM_SUMMARY:
        $parsed = $this->parseSummary($raw_data);
        break;
      case self::PM_EXIF_IPTC_XMP:
      case self::PM_TRANSLATED_EXIF:
      case self::PM_IPTC:
      case self::PM_XMP:
        $parsed = $this->parseExifIptcXmp($raw_data);
        break;
      default:
        $parsed = $raw_data;
        break;
    }

    return $parsed;
  }

  public function getExiv2Version() {
    $raw_data= false;

    if ( $this->env === 'windows' ) {
      $cmd = EXIV2_BASE_PATH . "/bin/win/exiv2.exe -V";
      $raw_data = shell_exec($cmd);
    } else if ( $this->env === 'linux' ) {
      
      $cmd = EXIV2_BASE_PATH . "/bin/linux/exiv2 -V";
      $raw_data = shell_exec($cmd);
    }

    if ($raw_data === null || $raw_data === false) {
      return null;
    } else {
      $this->exiv2Version = strtok($raw_data, "\n");
      return $this->exiv2Version;
    }
  }
} 