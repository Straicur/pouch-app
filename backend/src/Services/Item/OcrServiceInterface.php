<?php

declare(strict_types = 1);

namespace App\Services\Item;

use RuntimeException;

interface OcrServiceInterface
{
    /**
     * @throws RuntimeException if the tesseract binary itself fails
     */
    public function extractText(string $imagePath): string;
}
