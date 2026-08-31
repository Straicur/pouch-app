<?php

declare(strict_types = 1);

namespace App\Security;

use App\Entity\User;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;
use App\Repository\UserRepository;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final readonly class AuthService implements AuthServiceInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private TokenStorageInterface $tokenStorage,
        private LoggerInterface $logger,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Override]
    public function getUserByEmailAndPassword(string $email, string $password): User
    {
        $user = $this->userRepository->findUserByEmail($email);

        if (null === $user) {
            throw new UnauthorizedException();
        }

        // Post-review fix: used to call password_verify() directly — this
        // goes through the same password_hashers config (security.yaml)
        // everything else keyed on PasswordAuthenticatedUserInterface does,
        // instead of assuming plain bcrypt.
        if (false === $this->passwordHasher->isPasswordValid($user, $password)) {
            throw new UnauthorizedException();
        }

        return $user;
    }

    #[Override]
    public function getUserFromAccessToken(): User
    {
        $token = $this->tokenStorage->getToken();

        if (false === $token instanceof TokenInterface) {
            $exception = new UnauthorizedException();
            $this->logger->error(message: $exception->getMessage());

            throw $exception;
        }

        /** @var ?User $user */
        $user = $token->getUser();

        if (null === $user) {
            $exception = new UnauthorizedException();
            $this->logger->error(message: $exception->getMessage());

            throw $exception;
        }

        return $user;
    }
}
