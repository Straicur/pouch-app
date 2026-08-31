<?php

declare(strict_types = 1);

namespace App\Tests\Services\Item;

use App\Services\Item\OcrService;
use PHPUnit\Framework\TestCase;

use function imagecolorallocate;
use function imagecreatetruecolor;
use function imagedestroy;
use function imagefill;
use function imagepng;
use function imagestring;
use function strtolower;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Real tesseract binary (installed in the Docker image, see backend/Dockerfile)
 * run against a generated image — per the roadmap's own hint ("jeśli wynik da
 * się sensownie assertować"), the assertion is deliberately lenient
 * (case/whitespace-insensitive substring) since OCR on a synthetic bitmap-font
 * image isn't pixel-perfect the way real photos usually are either.
 */
class OcrServiceTest extends TestCase
{
    public function testExtractsTextFromAGeneratedImage(): void
    {
        $path = $this->createTextImage('POUCH');

        try {
            $text = (new OcrService())->extractText($path);

            self::assertStringContainsStringIgnoringCase('POUCH', $text);
        } finally {
            unlink($path);
        }
    }

    public function testReturnsEmptyStringForABlankImage(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pouch-ocr-blank-') . '.png';
        $image = imagecreatetruecolor(200, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagepng($image, $path);
        imagedestroy($image);

        try {
            $text = (new OcrService())->extractText($path);

            self::assertSame('', strtolower($text));
        } finally {
            unlink($path);
        }
    }

    private function createTextImage(string $text): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pouch-ocr-test-') . '.png';
        $image = imagecreatetruecolor(400, 120);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        $black = imagecolorallocate($image, 0, 0, 0);
        // Built-in bitmap font (no external .ttf needed) — large size 5, scaled up
        // by drawing it several times isn't necessary; font 5 at this canvas size
        // is legible enough for tesseract.
        imagestring($image, 5, 20, 40, $text, $black);
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }
}
