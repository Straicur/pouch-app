<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions;

enum ExceptionUuidEnum: string
{
    // API UUIDS
    case BAD_REQUEST = '9f8b3a2e-1c6d-4f9a-9b2e-3a7d6c5e4f01';

    case UNAUTHORIZED = 'b3d2f6a1-7c4e-4f6b-8a99-0d1e2c3b4a55';

    case FROBIDDEN = 'c7a1e9d4-2b3c-4d5e-9f01-23456789abcd';

    case NOT_FOUND = 'd4e5f6a7-8b9c-4cde-8123-4567890abcde';

    case UNPROCESSABLE_CONTENT = 'e1f2a3b4-5c6d-4e7f-8123-abcdef123456';

    case TOO_MANY_REQUESTS = 'a2b3c4d5-6e7f-4890-8123-fedcba987654';

    case INTERNAL_SERVER = 'f0e1d2c3-b4a5-4f6e-8123-112233445566';
}
