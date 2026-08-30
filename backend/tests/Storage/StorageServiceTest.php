<?php

declare(strict_types = 1);

namespace App\Tests\Storage;

use App\Exception\StorageException;
use App\Storage\StorageService;
use App\Tests\SystemKernelTestCase;
use Symfony\Component\Uid\Uuid;

use function fclose;
use function fopen;
use function fwrite;
use function random_bytes;
use function rewind;
use function stream_get_contents;

class StorageServiceTest extends SystemKernelTestCase
{
    public function testUploadDownloadDeleteRoundTrip(): void
    {
        // getService() goes through the runtime container, where an unreferenced
        // private service like this one gets inlined away — self::getContainer()
        // is the test-only container that can still fetch it directly.
        $storageService = self::getContainer()->get(StorageService::class);
        self::assertInstanceOf(StorageService::class, $storageService);

        $key = 'tests/'.Uuid::v4().'.txt';
        $content = 'pouch storage integration test '.\bin2hex(random_bytes(8));

        self::assertFalse($storageService->exists($key));

        $uploadStream = fopen('php://temp', 'r+');
        self::assertIsResource($uploadStream);
        fwrite($uploadStream, $content);
        rewind($uploadStream);

        $storageService->upload($key, $uploadStream);
        fclose($uploadStream);

        self::assertTrue($storageService->exists($key));

        $downloadStream = $storageService->download($key);
        $downloaded = stream_get_contents($downloadStream);
        fclose($downloadStream);

        self::assertSame($content, $downloaded);

        $storageService->delete($key);

        self::assertFalse($storageService->exists($key));
    }

    public function testDownloadOfMissingKeyThrows(): void
    {
        $storageService = self::getContainer()->get(StorageService::class);
        self::assertInstanceOf(StorageService::class, $storageService);

        $this->expectException(StorageException::class);

        $storageService->download('tests/'.Uuid::v4().'-missing.txt');
    }
}
