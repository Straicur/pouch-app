<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class WhoAmIResponseDTO
{
    public function __construct(
        private readonly string $email,
        private readonly bool $isAdmin,
    ) {}

    public function getEmail(): string
    {
        return $this->email;
    }

    // Symfony's serializer strips a leading "is"/"get"/"has" off the accessor
    // name to derive the property key — isIsAdmin() -> "isAdmin", the same way
    // ItemSummaryResponseDTO's isHasThumbnail() -> "hasThumbnail" already does.
    // A plain isAdmin() would serialize as "admin" instead.
    public function isIsAdmin(): bool
    {
        return $this->isAdmin;
    }
}
