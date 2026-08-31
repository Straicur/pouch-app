<?php

declare(strict_types = 1);

namespace App\Services\Item;

use GdImage;
use Override;
use RuntimeException;

use function imagecolorallocate;
use function imagecopyresampled;
use function imagecreatefromgif;
use function imagecreatefromjpeg;
use function imagecreatefrompng;
use function imagecreatefromwebp;
use function imagecreatetruecolor;
use function imagedestroy;
use function imagefill;
use function imagejpeg;
use function imagesx;
use function imagesy;
use function max;
use function min;
use function round;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;

final class ThumbnailService implements ThumbnailServiceInterface
{
    // Longest side, in pixels — plenty for a gallery card preview.
    private const int MAX_DIMENSION = 400;

    private const int JPEG_QUALITY = 82;

    #[Override]
    public function generate(string $sourcePath, string $mimeType): string
    {
        $source = $this->readImage($sourcePath, $mimeType);

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1.0, self::MAX_DIMENSION / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $resized = $this->createTrueColorImage($targetWidth, $targetHeight);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        // Flatten onto white — the source may have transparency, but the
        // thumbnail is always a JPEG (no alpha channel).
        $flattened = $this->createTrueColorImage($targetWidth, $targetHeight);
        $white = imagecolorallocate($flattened, 255, 255, 255);
        if (false === $white) {
            imagedestroy($resized);
            imagedestroy($flattened);

            throw new RuntimeException('Could not allocate color for thumbnail background');
        }

        imagefill($flattened, 0, 0, $white);
        imagecopyresampled($flattened, $resized, 0, 0, 0, 0, $targetWidth, $targetHeight, $targetWidth, $targetHeight);
        imagedestroy($resized);

        $thumbnailPath = tempnam(sys_get_temp_dir(), 'pouch-thumb-');
        if (false === $thumbnailPath) {
            imagedestroy($flattened);

            throw new RuntimeException('Could not create a temp file for the thumbnail');
        }

        $success = imagejpeg($flattened, $thumbnailPath, self::JPEG_QUALITY);
        imagedestroy($flattened);

        if (false === $success) {
            throw new RuntimeException('Could not encode thumbnail as JPEG');
        }

        return $thumbnailPath;
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     *
     * @throws RuntimeException
     */
    private function createTrueColorImage(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        if (false === $image) {
            throw new RuntimeException('Could not allocate a true-color image for the thumbnail');
        }

        return $image;
    }

    /**
     * @throws RuntimeException
     */
    private function readImage(string $sourcePath, string $mimeType): GdImage
    {
        $image = match ($mimeType) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($sourcePath),
            'image/png'  => imagecreatefrompng($sourcePath),
            'image/gif'  => imagecreatefromgif($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default      => throw new RuntimeException(sprintf('Unsupported image mime type for thumbnails: "%s"', $mimeType)),
        };

        if (false === $image) {
            throw new RuntimeException(sprintf('Could not decode image at "%s" (mime type "%s")', $sourcePath, $mimeType));
        }

        return $image;
    }
}
