<?php

declare(strict_types = 1);

namespace App\ControllerHelper\Traits;

use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * Shared by ItemCreateController (createFile()/createPhoto()) and
 * ItemEditController (overwriteFile()) — same "file" multipart field, same
 * validation either way.
 */
trait ExtractsUploadedFileTrait
{
    /**
     * @throws BadRequestException
     */
    protected function extractUploadedFile(Request $request): UploadedFile
    {
        $file = $request->files->get('file');
        if (false === $file instanceof UploadedFile || false === $file->isValid()) {
            throw new BadRequestException(message: 'item.file_upload_missing');
        }

        return $file;
    }

    /**
     * @throws BadRequestException
     */
    protected function fileSize(UploadedFile $file): int
    {
        $size = $file->getSize();
        if (false === $size) {
            throw new BadRequestException(message: 'item.file_size_unknown');
        }

        return $size;
    }
}
