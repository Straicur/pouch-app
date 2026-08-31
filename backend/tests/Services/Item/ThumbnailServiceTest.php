<?php

declare(strict_types = 1);

namespace App\Tests\Services\Item;

use App\Services\Item\ThumbnailService;
use PHPUnit\Framework\TestCase;

class ThumbnailServiceTest extends TestCase
{
    public function testGeneratesAResizedJpegThumbnail(): void
    {
        $sourcePath = $this->createTestImage(1200, 800);

        $thumbnailPath = (new ThumbnailService())->generate($sourcePath, 'image/png');

        try {
            self::assertFileExists($thumbnailPath);

            $info = getimagesize($thumbnailPath);
            self::assertNotFalse($info);
            [$width, $height, $type] = $info;

            self::assertSame(\IMAGETYPE_JPEG, $type);
            // Longest side capped at 400px, aspect ratio preserved (1200x800 -> 400x267ish).
            self::assertSame(400, $width);
            self::assertSame(267, $height);
        } finally {
            unlink($sourcePath);
            unlink($thumbnailPath);
        }
    }

    public function testUpscalesNeverHappenForSmallImages(): void
    {
        $sourcePath = $this->createTestImage(50, 50);

        $thumbnailPath = (new ThumbnailService())->generate($sourcePath, 'image/png');

        try {
            $info = getimagesize($thumbnailPath);
            self::assertNotFalse($info);
            self::assertSame(50, $info[0]);
            self::assertSame(50, $info[1]);
        } finally {
            unlink($sourcePath);
            unlink($thumbnailPath);
        }
    }

    private function createTestImage(int $width, int $height): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pouch-thumbnail-test-') . '.png';
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 100, 150, 200));
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }
}
