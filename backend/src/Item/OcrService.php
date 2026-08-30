<?php

declare(strict_types = 1);

namespace App\Item;

use Override;
use RuntimeException;
use thiagoalessio\TesseractOCR\TesseractNotFoundException;
use thiagoalessio\TesseractOCR\TesseractOCR;
use thiagoalessio\TesseractOCR\TesseractOcrException;
use thiagoalessio\TesseractOCR\UnsuccessfulCommandException;

use function sprintf;
use function trim;

final class OcrService implements OcrServiceInterface
{
    #[Override]
    public function extractText(string $imagePath): string
    {
        try {
            $ocr = new TesseractOCR($imagePath);
            /** @var string $result */
            // lang()/run() are handled via __call() — invisible to phpstan, hence
            // both the ignore and the explicit @var (run() otherwise types as mixed).
            // @phpstan-ignore-next-line method.notFound
            $result = $ocr->lang('eng', 'pol')->run();

            return trim($result);
        } catch (TesseractNotFoundException $exception) {
            // A missing binary is a real infra problem — worth surfacing/retrying,
            // unlike a photo that simply has no text in it.
            throw new RuntimeException(sprintf('OCR failed for "%s": %s', $imagePath, $exception->getMessage()), previous: $exception);
        } catch (UnsuccessfulCommandException) {
            // Tesseract exits this way (no output file at all) on images with no
            // detectable text — a blank photo isn't an OCR failure.
            return '';
        } catch (TesseractOcrException $exception) {
            throw new RuntimeException(sprintf('OCR failed for "%s": %s', $imagePath, $exception->getMessage()), previous: $exception);
        }
    }
}
