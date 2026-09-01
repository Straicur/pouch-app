<?php

declare(strict_types = 1);

namespace App\Services\User;

use App\Entity\Pouch;
use App\Entity\User;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use App\Repository\PouchRepository;
use App\Repository\UserRepository;
use Override;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function bin2hex;
use function random_bytes;

class UserService implements UserServiceInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PouchRepository $pouchRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Override]
    public function list(): array
    {
        return $this->userRepository->findAllOrderedByEmail();
    }

    #[Override]
    public function getById(int $id): User
    {
        $user = $this->userRepository->find($id);
        if (null === $user) {
            throw new NotFoundException(message: 'user.not_found');
        }

        return $user;
    }

    #[Override]
    public function create(string $email, string $role, ?int $pouchId, ?string $newPouchName): array
    {
        if (null !== $this->userRepository->findUserByEmail($email)) {
            throw new ConflictException(message: 'user.email_taken');
        }

        $pouch = $this->resolvePouch($pouchId, $newPouchName);

        $temporaryPassword = $this->generateTemporaryPassword();
        $user = new User($email, password: '', pouch: $pouch);
        $user->setRoles([$role]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $temporaryPassword));

        $this->userRepository->saveUser($user);

        return ['user' => $user, 'temporaryPassword' => $temporaryPassword];
    }

    #[Override]
    public function changeRole(int $id, string $role): User
    {
        $user = $this->getById($id);
        $user->setRoles([$role]);

        $this->userRepository->saveUser($user);

        return $user;
    }

    #[Override]
    public function setEnabled(int $id, bool $enabled, int $currentUserId): User
    {
        if ($id === $currentUserId) {
            throw new BadRequestException(message: 'user.cannot_modify_self');
        }

        $user = $this->getById($id);
        $user->setEnabled($enabled);

        $this->userRepository->saveUser($user);

        return $user;
    }

    #[Override]
    public function resetPassword(int $id): array
    {
        $user = $this->getById($id);

        $temporaryPassword = $this->generateTemporaryPassword();
        $user->setPassword($this->passwordHasher->hashPassword($user, $temporaryPassword));
        $this->userRepository->saveUser($user);

        return ['user' => $user, 'temporaryPassword' => $temporaryPassword];
    }

    #[Override]
    public function delete(int $id, int $currentUserId): void
    {
        if ($id === $currentUserId) {
            throw new BadRequestException(message: 'user.cannot_modify_self');
        }

        $user = $this->getById($id);
        $this->userRepository->remove($user);
    }

    /**
     * @throws NotFoundException   $pouchId is given but doesn't exist
     * @throws BadRequestException neither or both of $pouchId/$newPouchName were given
     */
    private function resolvePouch(?int $pouchId, ?string $newPouchName): Pouch
    {
        if (null !== $pouchId && null !== $newPouchName) {
            throw new BadRequestException(message: 'user.pouch_choice_ambiguous');
        }

        if (null !== $pouchId) {
            $pouch = $this->pouchRepository->find($pouchId);
            if (null === $pouch) {
                throw new NotFoundException(message: 'pouch.not_found');
            }

            return $pouch;
        }

        if (null !== $newPouchName) {
            $pouch = new Pouch($newPouchName);
            $this->pouchRepository->save($pouch);

            return $pouch;
        }

        throw new BadRequestException(message: 'user.pouch_choice_required');
    }

    private function generateTemporaryPassword(): string
    {
        return bin2hex(random_bytes(9));
    }
}
