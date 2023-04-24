<?php
namespace Exiv2\Metadata;

use Exiv2\Metadata\Exif\Exiv2MetadataExifImage;
use Exiv2\Metadata\Exif\Exiv2MetadataExifPhoto;
use Exiv2\Metadata\Exif\Exiv2MetadataExif;
use Exiv2\Metadata\Iptc\Exiv2MetadataIptc;
use Exiv2\Metadata\Xmp\Exiv2MetadataXmp;

class Exiv2Metadata {
  public $exif;
  public $iptc;
  public $xmp;

  public $fileName;
  public $fileSize;
  public $mimeType;
  public $imageSize;

  public function __construct() {
    $this->exif = new Exiv2MetadataExif();
    $this->iptc = new Exiv2MetadataIptc();
    $this->xmp = new Exiv2MetadataXmp();
  }
}