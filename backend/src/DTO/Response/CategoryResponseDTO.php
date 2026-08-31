<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class CategoryResponseDTO
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly ?int $parentId,
        // Część 13 — czy ta kategoria ma ustawiony własny klucz dostępu; patrz
        // ItemResponseDTO::$hasAccessKey dla pełnego uzasadnienia. AccessKeyPanel
        // używa tego, żeby pokazać "Ustaw klucz" albo "Zmień/Usuń klucz".
        private readonly bool $hasAccessKey,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function isHasAccessKey(): bool
    {
        return $this->hasAccessKey;
    }
}
