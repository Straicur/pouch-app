<?php

declare(strict_types = 1);

namespace App\DTO\Mapper;

use App\DTO\Response\PouchOverviewResponseDTO;
use App\DTO\Response\UserCreatedResponseDTO;
use App\DTO\Response\UserResponseDTO;
use App\Entity\User;
use App\Services\Pouch\PouchOverview;

use function array_map;

/**
 * Stateless, so — like the rest of src/DTO — it's excluded from the container in
 * services.yaml and just instantiated directly where needed.
 */
final class UserMapper
{
    public static function toResponseDTO(User $user): UserResponseDTO
    {
        return new UserResponseDTO(
            id: $user->getId(),
            email: $user->getEmail(),
            role: $user->getRoles()[0] ?? 'ROLE_USER',
            enabled: $user->isEnabled(),
            pouchId: $user->getPouch()->getId(),
            pouchName: $user->getPouch()->getName(),
        );
    }

    /**
     * @param list<User> $users
     *
     * @return list<UserResponseDTO>
     */
    public static function toResponseDTOList(array $users): array
    {
        return array_map(self::toResponseDTO(...), $users);
    }

    /**
     * @param array{user: User, temporaryPassword: string} $created
     */
    public static function toCreatedResponseDTO(array $created): UserCreatedResponseDTO
    {
        return new UserCreatedResponseDTO(
            user: self::toResponseDTO($created['user']),
            temporaryPassword: $created['temporaryPassword'],
        );
    }

    public static function toPouchOverviewResponseDTO(PouchOverview $overview): PouchOverviewResponseDTO
    {
        return new PouchOverviewResponseDTO(
            id: $overview->pouch->getId(),
            name: $overview->pouch->getName(),
            userCount: $overview->userCount,
            categoryCount: $overview->categoryCount,
            itemCount: $overview->itemCount,
        );
    }

    /**
     * @param list<PouchOverview> $overviews
     *
     * @return list<PouchOverviewResponseDTO>
     */
    public static function toPouchOverviewResponseDTOList(array $overviews): array
    {
        return array_map(self::toPouchOverviewResponseDTO(...), $overviews);
    }
}
