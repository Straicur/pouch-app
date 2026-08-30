<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class ItemUpdateTagsRequestDTO
{
    public function __construct(
        /**
         * @var list<string>
         */
        #[Assert\Count(max: 20, maxMessage: 'tag.too_many')]
        #[Assert\All([
            new Assert\Type('string', message: 'tag.invalid_type'),
            new Assert\Length(max: 50, maxMessage: 'tag.too_long'),
        ])]
        private readonly array $tags = [],
    ) {}

    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }
}
