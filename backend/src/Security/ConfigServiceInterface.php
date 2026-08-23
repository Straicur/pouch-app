<?php

declare(strict_types = 1);

namespace App\Security;

interface ConfigServiceInterface
{
    public function getAccessTokenTimeToLive(): int;

    public function getRefreshTokenTimeToLive(): int;
}
