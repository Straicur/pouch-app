<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\Model;

use Override;

class ExceptionModel implements ExceptionModelInterface
{
    public const string UUID = 'uuid';

    public function __construct(
        protected readonly string $title,
        protected readonly int $status,
        protected string $detail,
        /**
         * @var array<string, mixed> $context
         */
        protected array $context = [],
    ) {}

    #[Override]
    public function getStatus(): int
    {
        return $this->status;
    }

    #[Override]
    public function getTitle(): string
    {
        return $this->title;
    }

    #[Override]
    public function setDetail(string $detail): void
    {
        $this->detail = $detail;
    }

    #[Override]
    public function getDetail(): string
    {
        return $this->detail;
    }

    #[Override]
    public function addContext(string $key, mixed $value): void
    {
        $this->context[$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getContext(): array
    {
        return $this->context;
    }
}
