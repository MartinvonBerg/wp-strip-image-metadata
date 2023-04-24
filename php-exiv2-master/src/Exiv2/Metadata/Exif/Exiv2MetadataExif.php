<?php
namespace Exiv2\Metadata\Exif;

class Exiv2MetadataExif {
  public $image;
  public $photo;

  public function __construct() {
    $this->image = new Exiv2MetadataExifImage();
    $this->photo = new Exiv2MetadataExifPhoto();
  }
}