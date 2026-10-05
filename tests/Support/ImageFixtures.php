<?php

namespace Tests\Support;

use Imagick;
use ImagickPixel;

class ImageFixtures
{
    /**
     * A JPEG of the given size with an EXIF block containing GPS coordinates (55°45'N).
     */
    public static function jpegWithGps(int $width = 2400, int $height = 1600): string
    {
        $image = new Imagick;
        $image->newImage($width, $height, new ImagickPixel('#8a6f4d'));
        $image->setImageFormat('jpeg');
        $jpeg = $image->getImageBlob();
        $image->clear();

        // Insert an APP1/Exif segment right after the SOI marker.
        $exif = 'Exif'."\0\0".self::tiffWithGps();
        $segment = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    private static function tiffWithGps(): string
    {
        // Little-endian TIFF: header, IFD0 with a GPSInfo pointer, GPS IFD with latitude ref + value.
        $header = 'II'.pack('v', 42).pack('V', 8);

        $ifd0Offset = 8;
        $ifd0 = pack('v', 1)
            .pack('vvVV', 0x8825, 4, 1, $ifd0Offset + 2 + 12 + 4)
            .pack('V', 0);

        $gpsOffset = $ifd0Offset + strlen($ifd0);
        $gpsEntries = 2;
        $valuesOffset = $gpsOffset + 2 + $gpsEntries * 12 + 4;
        $gps = pack('v', $gpsEntries)
            .pack('vvV', 0x0001, 2, 2)."N\0\0\0"
            .pack('vvVV', 0x0002, 5, 3, $valuesOffset)
            .pack('V', 0);
        $values = pack('VVVVVV', 55, 1, 45, 1, 0, 1);

        return $header.$ifd0.$gps.$values;
    }
}
