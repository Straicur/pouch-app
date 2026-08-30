<?php

declare(strict_types = 1);

namespace App\Tests\Security;

use App\Security\SignedUrlService;
use App\Tests\SystemKernelTestCase;

class SignedUrlServiceTest extends SystemKernelTestCase
{
    public function testFreshlySignedUrlIsValid(): void
    {
        $service = self::getContainer()->get(SignedUrlService::class);

        $signed = $service->sign('item-download:1', 900);

        self::assertTrue($service->isValid('item-download:1', $signed['expires'], $signed['signature']));
    }

    public function testTamperedSignatureIsRejected(): void
    {
        $service = self::getContainer()->get(SignedUrlService::class);

        $signed = $service->sign('item-download:1', 900);

        self::assertFalse($service->isValid('item-download:1', $signed['expires'], 'garbage'));
    }

    public function testSignatureForADifferentResourceIsRejected(): void
    {
        $service = self::getContainer()->get(SignedUrlService::class);

        $signed = $service->sign('item-download:1', 900);

        self::assertFalse($service->isValid('item-download:2', $signed['expires'], $signed['signature']));
    }

    public function testExpiredSignatureIsRejectedEvenThoughItsGenuine(): void
    {
        $service = self::getContainer()->get(SignedUrlService::class);

        // Negative TTL: a genuinely-computed signature for an expiry already in the past.
        $signed = $service->sign('item-download:1', -1);

        self::assertFalse($service->isValid('item-download:1', $signed['expires'], $signed['signature']));
    }
}
