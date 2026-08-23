<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\Model;

interface ExceptionModelInterface
{
    public function getStatus(): int;

    public function getTitle(): string;

    public function setDetail(string $detail): void;

    public function getDetail(): string;

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array;

    public function addContext(string $key, mixed $value): void;
}
