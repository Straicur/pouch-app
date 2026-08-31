<?php

declare(strict_types = 1);

namespace App\DTO\Mapper;

use App\DTO\Response\AccessGrantResponseDTO;
use App\Security\AccessKey\AccessGrant;

final class AccessGrantMapper
{
    public static function toResponseDTO(AccessGrant $grant): AccessGrantResponseDTO
    {
        return new AccessGrantResponseDTO(
            resource: $grant->resource,
            expires: $grant->expires,
            signature: $grant->signature,
        );
    }
}
