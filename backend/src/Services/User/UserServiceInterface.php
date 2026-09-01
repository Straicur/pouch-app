<?php

declare(strict_types = 1);

namespace App\Services\User;

use App\Entity\User;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;

interface UserServiceInterface
{
    /**
     * @return list<User>
     */
    public function list(): array;

    /** @throws NotFoundException */
    public function getById(int $id): User;

    /**
     * Exactly one of $pouchId/$newPouchName must be given — the caller (DTO
     * validation) is expected to have already enforced that.
     *
     * @return array{user: User, temporaryPassword: string}
     *
     * @throws ConflictException   $email is already taken
     * @throws NotFoundException   $pouchId is given but doesn't exist
     * @throws BadRequestException neither or both of $pouchId/$newPouchName were given
     */
    public function create(string $email, string $role, ?int $pouchId, ?string $newPouchName): array;

    /** @throws NotFoundException */
    public function changeRole(int $id, string $role): User;

    /**
     * @throws NotFoundException   $id doesn't exist
     * @throws BadRequestException $id is $currentUserId (an admin can't lock themselves out)
     */
    public function setEnabled(int $id, bool $enabled, int $currentUserId): User;

    /**
     * @return array{user: User, temporaryPassword: string}
     *
     * @throws NotFoundException
     */
    public function resetPassword(int $id): array;

    /**
     * @throws NotFoundException   $id doesn't exist
     * @throws BadRequestException $id is $currentUserId (an admin can't delete their own account)
     */
    public function delete(int $id, int $currentUserId): void;
}
