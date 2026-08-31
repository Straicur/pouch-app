<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use App\Enum\TtlPreset;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Part 10: "masowe przedłużenie ważności wybranych itemów" — same lifecycle
 * shape as creating an item (see ItemCreateRequestDTO), just applied to a
 * batch of existing ones instead of one new one.
 */
class AdminExtendExpiryRequestDTO
{
    /**
     * @param list<int> $itemIds
     */
    public function __construct(
        #[Assert\NotBlank(message: 'not_blank')]
        #[Assert\All([new Assert\Positive(message: 'positive')])]
        private readonly array $itemIds,
        private readonly bool $keepForever = false,
        #[Assert\Choice(callback: [TtlPreset::class, 'values'], message: 'invalid_choice')]
        private readonly ?string $ttlPreset = null,
        private readonly ?string $expiresAt = null,
    ) {}

    /**
     * @return list<int>
     */
    public function getItemIds(): array
    {
        return $this->itemIds;
    }

    public function isKeepForever(): bool
    {
        return $this->keepForever;
    }

    public function getTtlPreset(): ?string
    {
        return $this->ttlPreset;
    }

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }
}
