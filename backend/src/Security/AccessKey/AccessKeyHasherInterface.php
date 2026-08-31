<?php

declare(strict_types = 1);

namespace App\Security\AccessKey;

interface AccessKeyHasherInterface
{
    public function hash(string $key): string;

    public function verify(string $key, string $hash): bool;
}
