<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * `key: null` (or omitted) removes the resource's own key. Symfony's
 * constraints treat null as valid by default, so Length only kicks in when a
 * key is actually given.
 */
class AccessKeySetRequestDTO
{
    public function __construct(
        #[Assert\Length(min: 4, max: 255, minMessage: 'min_length', maxMessage: 'max_length')]
        private readonly ?string $key = null,
    ) {}

    public function getKey(): ?string
    {
        return $this->key;
    }
}
