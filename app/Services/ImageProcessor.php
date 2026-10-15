<?php

namespace App\Services;

use App\Exceptions\InvalidImageException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Throwable;

/**
 * Turns an untrusted upload into web-ready WebP variants:
 * verifies the real MIME type, decodes, auto-orients, strips EXIF/GPS and re-encodes.
 */
class ImageProcessor
{
    public const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public const int MAX_BYTES = 15 * 1024 * 1024;

    public const int LARGE_EDGE = 1600;

    public const int MEDIUM_EDGE = 960;

    private const int QUALITY = 82;

    private ImageManagerInterface $manager;

    public function __construct()
    {
        $this->manager = ImageManager::usingDriver(Driver::class, autoOrientation: true, decodeAnimation: false, strip: true);
    }

    /**
     * Processes a question photo into large and medium variants under a fresh opaque key.
     */
    public function processQuestionImage(Filesystem $disk, string $originalPath): ProcessedImage
    {
        $binary = $this->readVerified($disk, $originalPath);
        $directory = 'questions/'.Str::uuid();

        $large = $this->resize($binary, self::LARGE_EDGE);
        $largePath = "{$directory}/large.webp";
        $disk->put($largePath, $this->encode($large), 'public');

        $medium = $this->resize($binary, self::MEDIUM_EDGE);
        $mediumPath = "{$directory}/medium.webp";
        $disk->put($mediumPath, $this->encode($medium), 'public');

        return new ProcessedImage($largePath, $mediumPath, $large->width(), $large->height());
    }

    /**
     * Re-encodes an image cropped around its centre to the `$width`:`$height` ratio and at most that size
     * (a smaller image is not upscaled), so every quiz card shows a cover of the same shape.
     */
    public function processCroppedToWebp(string $binary, int $width, int $height): string
    {
        $this->assertAllowedBinary($binary);

        try {
            $image = $this->manager->decodeBinary($binary);
        } catch (Throwable $e) {
            throw new InvalidImageException('Image could not be decoded.', previous: $e);
        }

        // The largest centred box of the target ratio, then shrunk to the target size if bigger.
        $scale = min($image->width() / $width, $image->height() / $height, 1);

        return $this->encode($image->cover(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))));
    }

    public function assertAllowedBinary(string $binary): void
    {
        if (strlen($binary) > self::MAX_BYTES) {
            throw new InvalidImageException('Image exceeds the maximum allowed size.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);

        if (! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidImageException("Unsupported image type: {$mime}");
        }
    }

    private function readVerified(Filesystem $disk, string $path): string
    {
        $binary = $disk->get($path);

        if ($binary === null) {
            throw new InvalidImageException("Original image not found: {$path}");
        }

        $this->assertAllowedBinary($binary);

        return $binary;
    }

    private function resize(string $binary, int $maxEdge): ImageInterface
    {
        try {
            return $this->manager->decodeBinary($binary)->scaleDown($maxEdge, $maxEdge);
        } catch (Throwable $e) {
            throw new InvalidImageException('Image could not be decoded.', previous: $e);
        }
    }

    private function encode(ImageInterface $image): string
    {
        return (string) $image->encode(new WebpEncoder(quality: self::QUALITY, strip: true));
    }
}
