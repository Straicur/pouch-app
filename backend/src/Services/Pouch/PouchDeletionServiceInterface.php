<?php

declare(strict_types = 1);

namespace App\Services\Pouch;

use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;

interface PouchDeletionServiceInterface
{
    /**
     * An admin's self-service "usuń cały pouch" — unlike a regular account's
     * self-delete (UserServiceInterface::deleteOwnAccount()), this actually
     * removes the pouch and everything in it: every item (and its storage
     * objects — files, thumbnails, archived versions — in MinIO), every
     * category, every account in the pouch (including $currentUserId
     * itself), then the pouch row.
     *
     * @throws NotFoundException   $currentUserId doesn't exist
     * @throws ConflictException   another account still belongs to this pouch —
     *                             remove/reassign them first (admin panel)
     * @throws BadRequestException $currentUserId is the only ROLE_ADMIN account
     *                             system-wide (would leave nothing able to reach
     *                             the admin panel at all)
     */
    public function deleteOwnPouch(int $currentUserId): void;
}
